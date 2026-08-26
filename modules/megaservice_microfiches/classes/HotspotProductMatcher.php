<?php
/**
 * PR8 — Rattachement hotspot.id_product ↔ ps_product.reference.
 *
 * Un hotspot porte une référence OEM constructeur (`article_ref`). Quand un
 * produit PrestaShop existe avec cette même `reference`, on relie le hotspot
 * au produit (id_product) pour que la PDP microfiche (PR7) puisse afficher
 * prix / dispo / ajout panier.
 *
 * Deux régimes (cf. décision politique de lien) :
 *   - matchProduct() : NON-DESTRUCTIF. Appelé par les hooks actionProductAdd /
 *     actionProductUpdate. Ne remplit que les hotspots non liés (id_product
 *     NULL) — ne clobbere jamais un lien fait à la main en BO (AdminMsHotspots).
 *   - matchAll() : AUTORITATIF. Déclenché par le bouton BO « Rematcher tout ».
 *     Réaligne entièrement id_product sur la référence : lie ce qui matche,
 *     délie ce qui n'a plus de produit correspondant.
 *
 * Collation : les tables ms_* sont en utf8mb4_general_ci (décision #6, alignée
 * sur PrestaShop) → le JOIN `article_ref = reference` ne lève pas d'erreur 1267.
 *
 * Critère « produit liable » : actif (active=1) ET référence non vide. En cas
 * de plusieurs produits partageant une référence, on prend le plus petit
 * id_product (déterministe).
 */
class HotspotProductMatcher
{
    /**
     * NON-DESTRUCTIF — lie les hotspots ORPHELINS (id_product NULL) dont
     * l'article_ref == la référence du produit donné. Utilisé par les hooks.
     *
     * @return int Nombre de hotspots nouvellement liés.
     */
    public static function matchProduct(int $idProduct, string $reference): int
    {
        $reference = trim($reference);
        if ($idProduct <= 0 || $reference === '') {
            return 0;
        }

        $db  = Db::getInstance();
        $sql = 'UPDATE `' . _DB_PREFIX_ . 'ms_microfiche_hotspot` '
             . 'SET `id_product` = ' . $idProduct . ' '
             . 'WHERE `article_ref` = "' . pSQL($reference) . '" '
             . '  AND `id_product` IS NULL';

        if (!$db->execute($sql)) {
            return 0;
        }

        return (int) $db->Affected_Rows();
    }

    /**
     * AUTORITATIF — réaligne tous les hotspots sur l'état courant du catalogue.
     * 1) lie/relie chaque hotspot au produit dont la référence == article_ref ;
     * 2) délie (id_product = NULL) tout hotspot dont l'article_ref n'a plus
     *    aucun produit liable correspondant.
     *
     * @return array{linked:int, unlinked:int} Compteurs de changements effectifs.
     */
    public static function matchAll(): array
    {
        $db     = Db::getInstance();
        $hotspot = '`' . _DB_PREFIX_ . 'ms_microfiche_hotspot`';

        // Sous-requête « produits liables » : 1 ligne par référence (plus petit
        // id_product), actifs, référence non vide.
        $liables = '(SELECT `reference`, MIN(`id_product`) AS `id_product` '
                 . 'FROM `' . _DB_PREFIX_ . 'product` '
                 . 'WHERE `active` = 1 AND `reference` <> "" '
                 . 'GROUP BY `reference`)';

        // 1) Lier / relier. La condition WHERE évite les updates no-op pour que
        //    Affected_Rows reflète les vrais changements.
        $linkSql = 'UPDATE ' . $hotspot . ' h '
                 . 'INNER JOIN ' . $liables . ' pm ON pm.`reference` = h.`article_ref` '
                 . 'SET h.`id_product` = pm.`id_product` '
                 . 'WHERE h.`article_ref` <> "" '
                 . '  AND (h.`id_product` IS NULL OR h.`id_product` <> pm.`id_product`)';
        $linked = $db->execute($linkSql) ? (int) $db->Affected_Rows() : 0;

        // 2) Délier les hotspots dont la référence n'a plus de produit liable.
        $unlinkSql = 'UPDATE ' . $hotspot . ' h '
                   . 'LEFT JOIN ' . $liables . ' pm ON pm.`reference` = h.`article_ref` '
                   . 'SET h.`id_product` = NULL '
                   . 'WHERE pm.`reference` IS NULL AND h.`id_product` IS NOT NULL';
        $unlinked = $db->execute($unlinkSql) ? (int) $db->Affected_Rows() : 0;

        return ['linked' => $linked, 'unlinked' => $unlinked];
    }

    /**
     * Stats d'état pour l'affichage BO.
     *
     * @return array{total:int, linked:int, orphans:int}
     */
    public static function stats(): array
    {
        $db    = Db::getInstance();
        $table = '`' . _DB_PREFIX_ . 'ms_microfiche_hotspot`';

        $total   = (int) $db->getValue('SELECT COUNT(*) FROM ' . $table);
        $linked  = (int) $db->getValue('SELECT COUNT(*) FROM ' . $table . ' WHERE `id_product` IS NOT NULL');

        return [
            'total'   => $total,
            'linked'  => $linked,
            'orphans' => $total - $linked,
        ];
    }
}
