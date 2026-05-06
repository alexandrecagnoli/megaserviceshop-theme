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
     * Expose à Smarty :
     *   - état wishlist du produit pour le user logué (cf. js-add-to-wishlist)
     *   - flag d'affichage du bloc tabs Powerparts + données hard-codées (à dynamiser)
     */
    public function initContent()
    {
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

        // 🔧 FALLBACK FAKE DATA — uniquement si aucune relation réelle n'existe
        // pour faciliter la validation visuelle. À retirer quand toutes les
        // catégories Powerparts auront leurs relations en BDD.
        if (empty($mandatory) && empty($excluded) && empty($recommended) && empty($spare)) {
            $pool = $this->presentProductsByIds($this->fetchFakeProductIds(12));
            $mandatory   = array_slice($pool, 0, 2);
            $excluded    = array_slice($pool, 2, 1);
            $recommended = array_slice($pool, 3, 4);
            $spare       = $this->buildSpareRows(array_slice($pool, 7, 3));
        }

        $this->context->smarty->assign([
            'ms_show_powerparts_tabs' => true,
            'ms_mandatory_products'   => $mandatory,
            'ms_excluded_products'    => $excluded,
            'ms_recommended_products' => $recommended,
            'ms_spare_products'       => $spare,
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
        if (empty(self::$POWERPARTS_ROOT_IDS) || !isset($this->product) || !$this->product->id) {
            return false;
        }

        $productCategories = Product::getProductCategories((int) $this->product->id);
        if (empty($productCategories)) {
            return false;
        }

        foreach (self::$POWERPARTS_ROOT_IDS as $rootId) {
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
     * 🔧 Convertit une liste de produits présentés en lignes "spare parts"
     * (format minimal pour la list view : nom, ref, prix, dispo, qty reco).
     */
    private function buildSpareRows(array $presentedProducts)
    {
        $rows = [];
        foreach ($presentedProducts as $p) {
            $rows[] = [
                'id_product'      => isset($p['id_product']) ? (int) $p['id_product'] : 0,
                'name'            => isset($p['name']) ? $p['name'] : 'Pièce',
                'reference'       => !empty($p['reference']) ? $p['reference'] : 'P00000',
                'price'           => isset($p['price']) ? $p['price'] : '0,00 €',
                'availability'    => isset($p['availability']) ? $p['availability'] : 'available',
                'recommended_qty' => 1,
                'add_to_cart_url' => isset($p['add_to_cart_url']) ? $p['add_to_cart_url'] : '#',
            ];
        }
        return $rows;
    }
}
