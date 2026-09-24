<?php

class ProductController extends ProductControllerCore
{
    /**
     * Catégories racines dont les produits affichent le bloc "tabs Powerparts"
     * (pièces obligatoires / exclues / recommandées / rechange).
     * S'applique à tous les produits descendants de ces catégories.
     */
    private static $POWERPARTS_ROOT_IDS = [41]; // Accessoires Powerparts

    /**
     * Racine « Motos neuves ». Seuls ses produits affichent « Réserver un essai ».
     * Les motos d'occasion (catégorie 11) en sont exclues — demande du client.
     */
    private static $NEW_MOTO_ROOT_IDS = [10];

    /**
     * Expose à Smarty :
     *   - état wishlist du produit pour le user logué (cf. js-add-to-wishlist)
     *   - flag d'affichage du bloc tabs Powerparts + données hard-codées (à dynamiser)
     */
    public function initContent()
    {
        // AVANT parent::initContent() : PrestaShop re-rend les blocs de la fiche
        // en AJAX (action=refresh) dès qu'un produit a des déclinaisons, et le
        // parent interrompt alors la requête. Une variable assignée après lui
        // n'existe pas dans ce rendu — c'est ce qui désactivait le bouton panier
        // (réf. 6070990104430) et ferait clignoter « Réserver un essai ».
        $this->context->smarty->assign(
            'ms_is_new_moto',
            $this->isProductInSubtreeOf(self::$NEW_MOTO_ROOT_IDS)
        );

        parent::initContent();

        $this->assignPowerpartsTabs();
        $this->assignSuggestedProducts();

        $customer = $this->context->customer;
        if (!$customer || !$customer->isLogged()) {
            return;
        }

        $idCustomer = (int) $customer->id;
        $idShop     = (int) $this->context->shop->id;

        // Récupère la wishlist par défaut (sans la créer si absente — seul le clic la créera).
        // ATTENTION : Db::getValue() ajoute automatiquement LIMIT 1 → ne pas le mettre dans le SQL.
        $idWishList = (int) Db::getInstance()->getValue(
            'SELECT `id_wishlist` FROM `' . _DB_PREFIX_ . 'wishlist`
             WHERE `id_customer` = ' . $idCustomer . '
             AND `id_shop` = ' . $idShop . '
             AND `default` = 1'
        );

        if (!$idWishList) {
            return;
        }

        $idProduct          = (int) Tools::getValue('id_product');
        $idProductAttribute = (int) Tools::getValue('id_product_attribute', 0);

        $isInWishlist = (bool) Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'wishlist_product`
             WHERE `id_wishlist` = ' . $idWishList . '
             AND `id_product` = ' . $idProduct . '
             AND `id_product_attribute` = ' . $idProductAttribute
        );

        $this->context->smarty->assign([
            'ms_wishlist_id'          => $idWishList,
            'ms_product_in_wishlist'  => $isInWishlist,
        ]);
    }

    /**
     * Détecte si le produit courant est dans une catégorie Powerparts et,
     * si oui, expose les 4 listes de produits liés.
     *
     * Source de données : module `megaservice_relations` (table polymorphe).
     * Si le module n'est pas installé OU si aucune relation n'est définie pour
     * un type donné, on retombe sur de la fake data pour garder du visuel
     * pendant la phase de dev/intégration.
     */
    private function assignPowerpartsTabs()
    {
        $isPowerparts = $this->isProductInPowerpartsSubtree();

        if (!$isPowerparts) {
            $this->context->smarty->assign([
                'ms_show_powerparts_tabs' => false,
                'ms_mandatory_products'   => [],
                'ms_excluded_products'    => [],
                'ms_recommended_products' => [],
                'ms_spare_products'       => [],
                'ms_moto_compatible'      => false,
            ]);
            return;
        }

        $idProduct = (int) $this->product->id;
        $hasService = $this->loadRelationService();

        // Mandatory
        $mandatory = $hasService
            ? \MsProductRelationService::getPresentedRelations($idProduct, 'mandatory', $this->context)
            : [];
        // Excluded
        $excluded = $hasService
            ? \MsProductRelationService::getPresentedRelations($idProduct, 'excluded', $this->context)
            : [];
        // Recommended
        $recommended = $hasService
            ? \MsProductRelationService::getPresentedRelations($idProduct, 'recommended', $this->context)
            : [];
        // Spare (avec qty)
        $spare = $hasService
            ? \MsProductRelationService::getSpareRows($idProduct, $this->context)
            : [];

        // Aucun repli sur des produits pris au hasard : faute de relations en
        // base (18 lignes dans megaservice_product_relation au 24/09), tous les
        // produits Powerparts affichaient les mêmes 12 articles — deux t-shirts
        // annoncés « pièces obligatoires » sur une pièce de frein, par exemple.
        // Le template a ses états vides (« Aucune pièce obligatoire »), qui
        // disent la vérité : on n'a pas encore la donnée.

        $this->context->smarty->assign([
            'ms_show_powerparts_tabs' => true,
            'ms_mandatory_products'   => $mandatory,
            'ms_excluded_products'    => $excluded,
            'ms_recommended_products' => $recommended,
            'ms_spare_products'       => $spare,
            'ms_moto_compatible'      => $this->isCompatibleWithGarage(),
        ]);
    }

    /**
     * Charge la classe MsProductRelationService du module si installé/présent.
     * Retourne true si la classe est utilisable.
     */
    private function loadRelationService()
    {
        if (class_exists('MsProductRelationService', false)) {
            return true;
        }
        $path = _PS_MODULE_DIR_ . 'megaservice_relations/classes/ProductRelationService.php';
        if (!file_exists($path)) {
            return false;
        }
        require_once $path;
        return class_exists('MsProductRelationService', false);
    }

    /**
     * Section "Produits qui pourraient vous plaire" sous les tabs.
     * Utilise les accessoires PS si le BO en a linké, sinon fake data.
     */
    private function assignSuggestedProducts()
    {
        $accessories = $this->context->smarty->getTemplateVars('accessories');
        if (!empty($accessories) && is_array($accessories)) {
            $this->context->smarty->assign('ms_suggested_products', array_slice($accessories, 0, 4));
            return;
        }
        $this->context->smarty->assign(
            'ms_suggested_products',
            $this->presentProductsByIds($this->fetchFakeProductIds(4))
        );
    }

    private function isProductInPowerpartsSubtree()
    {
        return $this->isProductInSubtreeOf(self::$POWERPARTS_ROOT_IDS);
    }

    /**
     * Le produit est-il rattaché à l'une de ces racines, ou à l'un de leurs
     * descendants ?
     *
     * @param int[] $rootIds
     *
     * @return bool
     */
    private function isProductInSubtreeOf(array $rootIds)
    {
        if (empty($rootIds) || !isset($this->product) || !$this->product->id) {
            return false;
        }

        $productCategories = Product::getProductCategories((int) $this->product->id);
        if (empty($productCategories)) {
            return false;
        }

        foreach ($rootIds as $rootId) {
            $root = new Category((int) $rootId);
            if (!$root->id) {
                continue;
            }
            foreach ($productCategories as $catId) {
                $cat = new Category((int) $catId);
                if (!$cat->id) {
                    continue;
                }
                if ($cat->nleft >= $root->nleft && $cat->nright <= $root->nright) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 🔧 Récupère N IDs de produits actifs du shop (excluant le produit courant).
     * Sert uniquement pour la fake data des tabs Powerparts et "Produits suggérés".
     */
    private function fetchFakeProductIds($limit)
    {
        $idShop      = (int) $this->context->shop->id;
        $idCurrent   = (int) $this->product->id;
        $sql = 'SELECT p.`id_product` FROM `' . _DB_PREFIX_ . 'product` p
                INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON p.`id_product` = ps.`id_product`
                WHERE ps.`id_shop` = ' . $idShop . ' AND ps.`active` = 1
                AND p.`id_product` != ' . $idCurrent . '
                ORDER BY p.`id_product` DESC
                LIMIT ' . (int) $limit;
        $rows = Db::getInstance()->executeS($sql);
        return is_array($rows) ? array_map('intval', array_column($rows, 'id_product')) : [];
    }

    /**
     * 🔧 Présente une liste d'IDs de produits au format miniature (mêmes clés
     * que $accessories), via le ProductListingPresenter de PS.
     */
    private function presentProductsByIds(array $productIds)
    {
        if (empty($productIds)) {
            return [];
        }

        $assembler = new \ProductAssembler($this->context);
        $factory   = new \ProductPresenterFactory($this->context);
        $settings  = $factory->getPresentationSettings();
        $presenter = new \PrestaShop\PrestaShop\Adapter\Presenter\Product\ProductListingPresenter(
            new \PrestaShop\PrestaShop\Adapter\Image\ImageRetriever($this->context->link),
            $this->context->link,
            new \PrestaShop\PrestaShop\Adapter\Product\PriceFormatter(),
            new \PrestaShop\PrestaShop\Adapter\Product\ProductColorsRetriever(),
            $this->context->getTranslator()
        );

        $out = [];
        foreach ($productIds as $id) {
            try {
                $raw = $assembler->assembleProduct(['id_product' => (int) $id]);
                $out[] = $presenter->present($settings, $raw, $this->context->language);
            } catch (\Exception $e) {
                // Silencieusement skip les produits qui plantent au présentation
            }
        }
        return $out;
    }

    /**
     * Ce produit est-il réellement monté sur la moto du garage ?
     *
     * Le bandeau « Compatible avec [moto] » s'affichait pour tout produit
     * Powerparts dès qu'une moto était sélectionnée, sans jamais consulter la
     * montabilité : il a affirmé du faux (réf. 116907 annoncée compatible
     * EC 300 2024 alors qu'elle ne figure pas dans la liste).
     *
     * @return bool
     */
    private function isCompatibleWithGarage()
    {
        if (!class_exists('MsMountability')) {
            $file = _PS_MODULE_DIR_ . 'megaservice_mountability/classes/MsMountability.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
        if (!class_exists('MsMountability')) {
            return false;
        }

        $idMoto = (int) MsMountability::resolveActiveMoto();
        if (!$idMoto || empty($this->product->id)) {
            return false;
        }

        return in_array(
            (int) $this->product->id,
            array_map('intval', MsMountability::getCompatibleProducts($idMoto)),
            true
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Fil d'Ariane
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Rétablit les niveaux de catégorie absents du fil d'Ariane natif.
     *
     * PrestaShop construit le chemin depuis `id_category_default`. Or l'import
     * catalogue pose comme catégorie principale un ancêtre de la catégorie réelle
     * du produit : sur 47 916 produits actifs, 42 851 (89 %) sont rattachés à une
     * catégorie PLUS PROFONDE que leur catégorie principale. Le fil s'arrêtait
     * donc un ou plusieurs crans trop haut — « Accueil › Équipement pilotes ›
     * S-M 5 HELMET CHEEK PADS » au lieu de passer par « Accessoires casque ».
     *
     * Corrigé à l'affichage et non en base : `ba_importer` réécrit
     * `id_category_default` à chaque import, une correction de donnée ne tiendrait
     * pas. On n'ajoute que des DESCENDANTS de la catégorie principale, donc le
     * chemin ne contredit jamais l'URL du produit, elle-même bâtie sur celle-ci.
     */
    public function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();

        $chain = $this->missingCategoryPath();
        if (empty($chain) || empty($breadcrumb['links'])) {
            return $breadcrumb;
        }

        // Le dernier maillon posé par le natif est le produit : on le met de côté,
        // on insère les catégories manquantes, puis on le remet en queue.
        $product = array_pop($breadcrumb['links']);

        foreach ($chain as $category) {
            $breadcrumb['links'][] = [
                'title' => $category->name,
                'url'   => $this->context->link->getCategoryLink($category),
            ];
        }

        $breadcrumb['links'][] = $product;

        return $breadcrumb;
    }

    /**
     * Catégories à insérer entre la catégorie principale (exclue) et la catégorie
     * réelle du produit (incluse), de la plus haute à la plus basse.
     *
     * @return Category[]
     */
    private function missingCategoryPath()
    {
        if (empty($this->product->id)) {
            return [];
        }

        $idLang = (int) $this->context->language->id;
        $default = new Category((int) $this->product->id_category_default, $idLang);

        // Mêmes conditions que le natif : s'il n'a pas posé la catégorie
        // principale, nos descendants n'auraient rien à quoi se rattacher.
        if (!Validate::isLoadedObject($default)
            || !$default->active
            || $default->is_root_category
            || !$default->id_parent
        ) {
            return [];
        }

        $leaf = $this->deepestVisibleDescendant($default);
        if ($leaf === null) {
            return [];
        }

        // Le chemin complet en une requête, grâce au nested set : toute catégorie
        // qui contient la feuille et qui est contenue par la principale.
        $rows = Db::getInstance()->executeS(
            'SELECT c.id_category
             FROM `' . _DB_PREFIX_ . 'category` c
             WHERE c.active = 1
               AND c.nleft  > ' . (int) $default->nleft . '
               AND c.nright < ' . (int) $default->nright . '
               AND c.nleft  <= ' . (int) $leaf['nleft'] . '
               AND c.nright >= ' . (int) $leaf['nright'] . '
             ORDER BY c.level_depth ASC'
        );

        $chain = [];
        foreach ((array) $rows as $row) {
            $chain[] = new Category((int) $row['id_category'], $idLang);
        }

        return $chain;
    }

    /**
     * Catégorie la plus profonde associée au produit ET située sous sa catégorie
     * principale, visible par le groupe du visiteur.
     *
     * @return array|null bornes nested set de la catégorie, ou null
     */
    private function deepestVisibleDescendant(Category $default)
    {
        $groups = FrontController::getCurrentCustomerGroups();
        $inGroups = $groups
            ? 'IN (' . implode(',', array_map('intval', $groups)) . ')'
            : '= ' . (int) Configuration::get('PS_UNIDENTIFIED_GROUP');

        $row = Db::getInstance()->getRow(
            'SELECT c.nleft, c.nright
             FROM `' . _DB_PREFIX_ . 'category_product` cp
             JOIN `' . _DB_PREFIX_ . 'category` c ON c.id_category = cp.id_category
             JOIN `' . _DB_PREFIX_ . 'category_shop` cs
                  ON cs.id_category = c.id_category AND cs.id_shop = ' . (int) $this->context->shop->id . '
             WHERE cp.id_product = ' . (int) $this->product->id . '
               AND c.active = 1
               AND c.nleft  > ' . (int) $default->nleft . '
               AND c.nright < ' . (int) $default->nright . '
               AND EXISTS (
                   SELECT 1 FROM `' . _DB_PREFIX_ . 'category_group` cg
                   WHERE cg.id_category = c.id_category AND cg.id_group ' . $inGroups . '
               )
             ORDER BY c.level_depth DESC, c.nleft ASC'
        );

        return $row ?: null;
    }
}
