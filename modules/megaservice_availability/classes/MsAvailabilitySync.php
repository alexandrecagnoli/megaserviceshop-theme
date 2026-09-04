<?php
/**
 * Synchronise `show_price` sur `available_for_order`, produit par produit ou
 * en masse.
 *
 * Contexte complet : docs/SPEC_disponibilite_stock.md §8-11. `show_price` est
 * une colonne persistante de `ps_product`/`ps_product_shop` (pas un calcul
 * dynamique) qui masque prix ET bouton d'achat quand elle vaut 0 — le module
 * d'import (ba_importer, alias BO « Advance Importing Pro ») l'écrit à 0 sur
 * la quasi-totalité du catalogue importé, par un mécanisme non visible dans
 * son mapping ni ses réglages (code source non accessible, module tiers).
 * Vérifié le 04/09/2026 : le DEFAULT SQL de la colonne vaut déjà 1 sur les
 * deux tables — le plugin l'écrase donc activement, sans mapping identifiable.
 *
 * Cette classe ne fait qu'une chose : remettre `show_price` en cohérence avec
 * `available_for_order`, natif PS et déjà correctement écrit par l'import.
 */
class MsAvailabilitySync
{
    /**
     * Synchronise un seul produit (ps_product + ps_product_shop).
     *
     * SQL direct, jamais Product::update() : le hook qui appelle cette méthode
     * (actionObjectProductAddAfter/UpdateAfter) est émis par l'ORM à chaque
     * add()/update() — repasser par l'ORM ici redéclencherait le même hook en
     * boucle. Un UPDATE SQL brut ne déclenche aucun hook ObjectModel.
     *
     * @return bool true si une valeur a été corrigée, false si déjà cohérent
     *              (ou produit invalide/inexistant)
     */
    public static function syncProduct($idProduct)
    {
        $idProduct = (int) $idProduct;
        if (!$idProduct) {
            return false;
        }

        $db      = Db::getInstance();
        $changed = false;

        foreach (['product', 'product_shop'] as $table) {
            $db->execute(
                'UPDATE `' . _DB_PREFIX_ . $table . '`
                 SET `show_price` = `available_for_order`
                 WHERE `id_product` = ' . $idProduct . '
                   AND `show_price` != `available_for_order`'
            );
            if ((int) $db->Affected_rows() > 0) {
                $changed = true;
            }
        }

        return $changed;
    }

    /**
     * Resynchronise tout le catalogue en une passe. Filet de sécurité si le
     * hook temps réel ne s'est jamais déclenché (ex. si l'import écrit en SQL
     * direct plutôt que via l'ORM Product — non vérifiable sans le code source
     * du plugin), et rattrapage immédiat du catalogue déjà importé.
     *
     * @return array{incoherents_avant:int, ps_product_corriges:int, ps_product_shop_corriges:int}
     */
    public static function syncAll()
    {
        $db = Db::getInstance();

        $before = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product` WHERE `show_price` != `available_for_order`'
        );

        $db->execute(
            'UPDATE `' . _DB_PREFIX_ . 'product`
             SET `show_price` = `available_for_order`
             WHERE `show_price` != `available_for_order`'
        );
        $updatedProduct = (int) $db->Affected_rows();

        $db->execute(
            'UPDATE `' . _DB_PREFIX_ . 'product_shop`
             SET `show_price` = `available_for_order`
             WHERE `show_price` != `available_for_order`'
        );
        $updatedProductShop = (int) $db->Affected_rows();

        return [
            'incoherents_avant'        => $before,
            'ps_product_corriges'      => $updatedProduct,
            'ps_product_shop_corriges' => $updatedProductShop,
        ];
    }
}
