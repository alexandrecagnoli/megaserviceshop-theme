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
     * si oui, expose les 4 listes de produits liés (pour l'instant en dur,
     * à brancher sur la vraie source de données plus tard).
     */
    private function assignPowerpartsTabs()
    {
        $isPowerparts = $this->isProductInPowerpartsSubtree();

        $this->context->smarty->assign([
            'ms_show_powerparts_tabs'   => $isPowerparts,
            // 🔧 FAKE DATA — à remplacer par la vraie source (custom feature, module, etc.)
            // On réutilise $accessories pour avoir des cards visibles tout de suite.
            'ms_mandatory_products'     => $isPowerparts ? $this->getFakePowerpartsProducts(2) : [],
            'ms_excluded_products'      => $isPowerparts ? $this->getFakePowerpartsProducts(1) : [],
            'ms_recommended_products'   => $isPowerparts ? $this->getFakePowerpartsProducts(4) : [],
            'ms_spare_products'         => $isPowerparts ? $this->getFakePowerpartsSpareParts(3) : [],
        ]);
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
     * 🔧 FAKE DATA — réutilise les accessoires PS du produit pour fournir des cards visuelles.
     * À remplacer par la vraie source (relation custom, custom feature, module dédié, etc.)
     */
    private function getFakePowerpartsProducts($limit = 4)
    {
        $accessories = $this->context->smarty->getTemplateVars('accessories');
        if (empty($accessories) || !is_array($accessories)) {
            return [];
        }
        return array_slice($accessories, 0, $limit);
    }

    /**
     * 🔧 FAKE DATA — pièces de rechange en list view. Format minimal pour le rendu.
     */
    private function getFakePowerpartsSpareParts($count = 3)
    {
        $accessories = $this->context->smarty->getTemplateVars('accessories');
        $sample = (!empty($accessories) && is_array($accessories)) ? array_slice($accessories, 0, $count) : [];

        $rows = [];
        foreach ($sample as $i => $p) {
            $rows[] = [
                'id_product'     => isset($p['id_product']) ? (int) $p['id_product'] : 0,
                'name'           => isset($p['name']) ? $p['name'] : 'Ressort échappement FMF',
                'reference'      => isset($p['reference']) && $p['reference'] ? $p['reference'] : 'P97268',
                'price'          => isset($p['price']) ? $p['price'] : '240,00 €',
                'availability'   => 'available',
                'recommended_qty' => 1,
                'add_to_cart_url' => isset($p['add_to_cart_url']) ? $p['add_to_cart_url'] : '#',
            ];
        }
        return $rows;
    }
}
