<?php

class ProductController extends ProductControllerCore
{
    /**
     * Expose à Smarty l'état "produit dans la wishlist par défaut" du customer logué,
     * ainsi que l'id de cette wishlist.
     *
     * Utilisé par le bouton .js-add-to-wishlist pour :
     *   - rendre le cœur orange plein si déjà liké (état initial persistant)
     *   - permettre au JS de toggle (delete OU add) sans devoir refetch la liste
     */
    public function initContent()
    {
        parent::initContent();

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
}
