<?php
/**
 * Megaservice — Écriture en base des relations de remplacement.
 *
 * Seule couche du module qui touche PrestaShop : le parsing
 * (MsReplacementCsvImporter) et la résolution (MsReplacementChainResolver)
 * restent purs et testables en CLI.
 *
 * Déroulé d'un import :
 *   1. constitution du jeu FINAL (photographique ou cumulatif, cf. $purge)
 *   2. résolution transitive sur ce jeu COMPLET
 *   3. upsert en masse (une requête par paquet, pas 16 000 requêtes)
 *   4. purge éventuelle des relations disparues
 *
 * ⚠️ La résolution tourne sur la table ENTIÈRE, pas sur les seules lignes
 * importées : une chaîne A→B→C peut enjamber deux fichiers. Résoudre les seules
 * lignes entrantes produirait des `ref_final` faux dès qu'on importe un fichier
 * isolé.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MsReplacementRepository
{
    /** Nombre de lignes par requête d'insertion groupée. */
    const CHUNK = 500;

    /**
     * @param array<int,array<string,mixed>> $rows lignes normalisées (CsvImporter)
     * @param array<string,mixed> $options
     *        purge : true = fichier PHOTOGRAPHIQUE (les relations absentes du
     *                jeu importé sont supprimées).
     *
     *        ⚠️ N'activer QUE si l'on importe la TOTALITÉ des fichiers
     *        constructeur en une fois. Avec un seul fichier, la purge
     *        supprimerait les relations des 5 autres organisations.
     *        Par défaut false : un import partiel est non destructif.
     *
     * @return array<string,mixed> rapport d'exécution
     */
    public static function import(array $rows, array $options = [])
    {
        $purge  = !empty($options['purge']);
        $report = [
            'incoming'      => count($rows),
            'purge_enabled' => $purge,
            'existing'      => 0,
            'upserted'      => 0,
            'purged'        => 0,
            'chain_ok'      => 0,
            'chain_loop'    => 0,
            'chain_dead_end' => 0,
            'max_depth'     => 0,
        ];

        // ── 1. Jeu final ────────────────────────────────────────────────────
        $incoming = [];
        foreach ($rows as $r) {
            $incoming[self::key($r)] = $r;
        }

        $existing = self::fetchExisting();
        $report['existing'] = count($existing);

        if ($purge) {
            // Photographique : le fichier fait foi, le reste disparaît.
            $final = $incoming;
        } else {
            // Cumulatif : on complète l'existant, l'entrant est prioritaire.
            $final = array_merge($existing, $incoming);
        }

        // ── 2. Résolution sur le jeu COMPLET ────────────────────────────────
        $resolved = MsReplacementChainResolver::resolve(array_values($final));

        foreach ($resolved as $r) {
            $report['max_depth'] = max($report['max_depth'], (int) $r['chain_depth']);
            $k = 'chain_' . $r['chain_status'];
            if (isset($report[$k])) {
                ++$report[$k];
            }
        }

        // ── 3. Upsert en masse ──────────────────────────────────────────────
        $report['upserted'] = self::bulkUpsert($resolved);

        // ── 4. Purge des relations disparues ────────────────────────────────
        if ($purge) {
            $report['purged'] = self::purgeAbsent(array_keys($incoming));
        }

        return $report;
    }

    /** Clé métier d'une relation (sans l'organisation de vente). */
    private static function key(array $row)
    {
        return $row['ref_replaced'] . '|' . $row['ref_replacement'];
    }

    /**
     * Relations déjà en base, au même format que les lignes du parseur.
     *
     * @return array<string,array<string,mixed>>
     */
    private static function fetchExisting()
    {
        $sql = 'SELECT `sales_orga`, `ref_replaced`, `ref_replacement`, `conversion_type`, `quantity`
                FROM `' . _DB_PREFIX_ . 'ms_replacement`';

        $out = [];
        foreach (Db::getInstance()->executeS($sql) ?: [] as $r) {
            $row = [
                'sales_orga'      => $r['sales_orga'],
                'ref_replaced'    => $r['ref_replaced'],
                'ref_replacement' => $r['ref_replacement'],
                'conversion_type' => $r['conversion_type'],
                'quantity'        => (int) $r['quantity'],
            ];
            $out[self::key($row)] = $row;
        }

        return $out;
    }

    /**
     * INSERT ... ON DUPLICATE KEY UPDATE par paquets.
     * `date_add` est préservé sur les lignes déjà connues (audit).
     *
     * @param array<int,array<string,mixed>> $rows lignes résolues
     * @return int nombre de lignes traitées
     */
    private static function bulkUpsert(array $rows)
    {
        if (empty($rows)) {
            return 0;
        }

        $db   = Db::getInstance();
        $now  = date('Y-m-d H:i:s');
        $done = 0;

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            $values = [];
            foreach ($chunk as $r) {
                $values[] = sprintf(
                    '(%s, "%s", "%s", "%s", %d, %s, %d, %d, "%s", "%s", "%s")',
                    $r['sales_orga'] === null ? 'NULL' : '"' . pSQL($r['sales_orga']) . '"',
                    pSQL($r['ref_replaced']),
                    pSQL($r['ref_replacement']),
                    pSQL($r['conversion_type']),
                    (int) $r['quantity'],
                    $r['ref_final'] === null ? 'NULL' : '"' . pSQL($r['ref_final']) . '"',
                    !empty($r['final_is_set']) ? 1 : 0,
                    (int) $r['chain_depth'],
                    pSQL($r['chain_status']),
                    $now,
                    $now
                );
            }

            $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'ms_replacement`
                        (`sales_orga`, `ref_replaced`, `ref_replacement`, `conversion_type`,
                         `quantity`, `ref_final`, `final_is_set`, `chain_depth`, `chain_status`,
                         `date_add`, `date_upd`)
                    VALUES ' . implode(',', $values) . '
                    ON DUPLICATE KEY UPDATE
                        `sales_orga`      = VALUES(`sales_orga`),
                        `conversion_type` = VALUES(`conversion_type`),
                        `quantity`        = VALUES(`quantity`),
                        `ref_final`       = VALUES(`ref_final`),
                        `final_is_set`    = VALUES(`final_is_set`),
                        `chain_depth`     = VALUES(`chain_depth`),
                        `chain_status`    = VALUES(`chain_status`),
                        `date_upd`        = VALUES(`date_upd`)';

            if ($db->execute($sql)) {
                $done += count($chunk);
            }
        }

        return $done;
    }

    /**
     * Supprime les relations absentes du jeu importé (mode photographique).
     * On procède par paquets de clés à CONSERVER plutôt qu'un NOT IN géant.
     *
     * @param string[] $keepKeys clés métier "ref_replaced|ref_replacement"
     * @return int nombre de lignes supprimées
     */
    private static function purgeAbsent(array $keepKeys)
    {
        $db   = Db::getInstance();
        $keep = array_flip($keepKeys);

        $all = $db->executeS(
            'SELECT `id_replacement`, `ref_replaced`, `ref_replacement`
             FROM `' . _DB_PREFIX_ . 'ms_replacement`'
        ) ?: [];

        $toDelete = [];
        foreach ($all as $r) {
            $k = $r['ref_replaced'] . '|' . $r['ref_replacement'];
            if (!isset($keep[$k])) {
                $toDelete[] = (int) $r['id_replacement'];
            }
        }

        if (empty($toDelete)) {
            return 0;
        }

        $deleted = 0;
        foreach (array_chunk($toDelete, self::CHUNK) as $chunk) {
            if ($db->execute(
                'DELETE FROM `' . _DB_PREFIX_ . 'ms_replacement`
                 WHERE `id_replacement` IN (' . implode(',', $chunk) . ')'
            )) {
                $deleted += count($chunk);
            }
        }

        return $deleted;
    }

    /**
     * Informations de remplacement d'une référence produit, dans les DEUX sens,
     * pour le bloc de contrôle de la fiche produit BO.
     *
     * ⚠️ LECTURE SEULE. La table est intégralement réécrite à chaque import du
     * fichier constructeur : toute saisie manuelle serait écrasée sans trace.
     *
     * Retourne null s'il n'y a rien à afficher → le bloc n'apparaît pas
     * (sinon c'est du bruit sur des dizaines de milliers de fiches).
     *
     * @return array<string,mixed>|null
     */
    public static function forReference($reference)
    {
        $reference = trim((string) $reference);
        if ($reference === '') {
            return null;
        }

        $ref    = pSQL($reference);
        $idLang = (int) Context::getContext()->language->id;
        $db     = Db::getInstance();

        // Sens DESCENDANT : cette référence est remplacée par…
        // Le LEFT JOIN sur `product` détecte le cas E (remplaçant absent du catalogue).
        $replacedBy = $db->executeS(
            'SELECT r.`ref_replacement`, r.`conversion_type`, r.`quantity`, r.`ref_final`,
                    r.`final_is_set`, r.`chain_depth`, r.`chain_status`,
                    p.`id_product` AS target_id, pl.`name` AS target_name
             FROM `' . _DB_PREFIX_ . 'ms_replacement` r
             LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.`reference` = r.`ref_final`
             LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                    ON pl.`id_product` = p.`id_product` AND pl.`id_lang` = ' . $idLang . '
             WHERE r.`ref_replaced` = "' . $ref . '"
             ORDER BY r.`ref_replacement`'
        ) ?: [];

        // Sens REMONTANT : quelles anciennes références aboutissent sur ce produit.
        // On interroge `ref_final` (destination réelle après résolution), pas
        // `ref_replacement` : c'est là qu'atterrit le client.
        $replaces = $db->executeS(
            'SELECT DISTINCT r.`ref_replaced`, r.`conversion_type`, r.`chain_depth`
             FROM `' . _DB_PREFIX_ . 'ms_replacement` r
             WHERE r.`ref_final` = "' . $ref . '"
             ORDER BY r.`ref_replaced`
             LIMIT 51'
        ) ?: [];

        if (empty($replacedBy) && empty($replaces)) {
            return null;
        }

        foreach ($replacedBy as &$r) {
            $r['target_id']    = (int) $r['target_id'];
            $r['quantity']     = (int) $r['quantity'];
            $r['chain_depth']  = (int) $r['chain_depth'];
            $r['final_is_set'] = (bool) $r['final_is_set'];
            $r['missing']      = ($r['target_id'] === 0); // cas E
        }
        unset($r);

        $truncated = count($replaces) > 50;

        return [
            'reference'          => $reference,
            'replaced_by'        => $replacedBy,
            'is_replaced'        => !empty($replacedBy),
            'is_set'             => count($replacedBy) > 1,
            'replaces'           => array_slice($replaces, 0, 50),
            'replaces_total'     => count($replaces),
            'replaces_truncated' => $truncated,
        ];
    }

    /**
     * Relations dont la référence finale n'existe pas au catalogue PrestaShop
     * (cas E de la spec) — export d'anomalies du BO.
     *
     * ⚠️ Calculé À LA DEMANDE, jamais figé à l'import : un remplaçant peut être
     * créé après coup par l'import OEM. Un `chain_status` stocké deviendrait
     * faux sans que rien ne le signale.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function findMissingTargets($limit = 0)
    {
        $sql = 'SELECT r.`ref_replaced`, r.`ref_final`, r.`conversion_type`, r.`chain_status`
                FROM `' . _DB_PREFIX_ . 'ms_replacement` r
                LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.`reference` = r.`ref_final`
                WHERE r.`ref_final` IS NOT NULL AND p.`id_product` IS NULL
                ORDER BY r.`ref_replaced`';

        if ((int) $limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        return Db::getInstance()->executeS($sql) ?: [];
    }
}
