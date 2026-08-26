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
     *        purge : true = fichier PHOTOGRAPHIQUE. La purge est SCOPÉE AUX
     *                ORGANISATIONS PRÉSENTES dans les fichiers importés : les
     *                relations disparues sont supprimées pour ces orgas
     *                uniquement, les autres marques ne sont jamais touchées.
     *                Réimporter le seul fichier KTM est donc sans danger.
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

        // Organisations couvertes par cet import = périmètre de la purge.
        $orgas = array_values(array_unique(array_filter(
            array_map(static function ($r) { return (string) $r['sales_orga']; }, $rows)
        )));
        $report['orgas'] = implode(', ', $orgas);

        if ($purge) {
            // Photographique PAR ORGA : on retire de l'existant tout ce qui
            // appartient aux orgas importées, puis on repose l'entrant. Les
            // relations des autres marques sont conservées telles quelles.
            $kept = [];
            foreach ($existing as $k => $r) {
                if (!in_array((string) $r['sales_orga'], $orgas, true)) {
                    $kept[$k] = $r;
                }
            }
            $final = $kept + $incoming;
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
            $report['purged'] = self::purgeAbsent($incoming, $orgas);
        }

        return $report;
    }

    /**
     * Clé métier d'une relation : (orga, remplacée, remplaçante).
     * Doit rester identique à MsReplacementCsvImporter::key().
     */
    private static function key(array $row)
    {
        return (string) $row['sales_orga'] . '|' . $row['ref_replaced'] . '|' . $row['ref_replacement'];
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
     * Supprime les relations disparues, UNIQUEMENT pour les organisations
     * couvertes par l'import (photographique par orga).
     *
     * @param array<string,array<string,mixed>> $incoming jeu importé, indexé par clé
     * @param string[] $orgas organisations concernées
     * @return int nombre de lignes supprimées
     */
    private static function purgeAbsent(array $incoming, array $orgas)
    {
        if (empty($orgas)) {
            return 0;
        }

        $db   = Db::getInstance();
        $keep = $incoming;

        $in = implode(',', array_map(static function ($o) {
            return '"' . pSQL($o) . '"';
        }, $orgas));

        $all = $db->executeS(
            'SELECT `id_replacement`, `sales_orga`, `ref_replaced`, `ref_replacement`
             FROM `' . _DB_PREFIX_ . 'ms_replacement`
             WHERE `sales_orga` IN (' . $in . ')'
        ) ?: [];

        $toDelete = [];
        foreach ($all as $r) {
            $k = self::key($r);
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
        // GROUP BY : une même relation peut exister dans plusieurs organisations
        // de vente (mutualisation inter-marques Pierer). Le front ignore l'orga
        // — la réf constructeur est globalement unique — donc on collapse, sinon
        // le même remplaçant s'afficherait plusieurs fois.
        //
        // Les agrégats sont sûrs : la résolution de chaîne est déterministe pour
        // un couple (remplacée, remplaçante) donné, donc identique d'une orga à
        // l'autre. Seul `conversion_type` peut diverger (typé `replace` ici,
        // `set` là) → `set` l'emporte, il porte l'information la plus complète.
        $replacedBy = $db->executeS(
            'SELECT r.`ref_replacement`,
                    CASE WHEN SUM(r.`conversion_type` = "set") > 0 THEN "set" ELSE "replace" END AS conversion_type,
                    MAX(r.`quantity`)     AS quantity,
                    MAX(r.`ref_final`)    AS ref_final,
                    MAX(r.`final_is_set`) AS final_is_set,
                    MAX(r.`chain_depth`)  AS chain_depth,
                    MIN(r.`chain_status`) AS chain_status,
                    MAX(p.`id_product`)   AS target_id,
                    MAX(pl.`name`)        AS target_name
             FROM `' . _DB_PREFIX_ . 'ms_replacement` r
             LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.`reference` = r.`ref_final`
             LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                    ON pl.`id_product` = p.`id_product` AND pl.`id_lang` = ' . $idLang . '
             WHERE r.`ref_replaced` = "' . $ref . '"
             GROUP BY r.`ref_replacement`
             ORDER BY r.`ref_replacement`'
        ) ?: [];

        // Sens REMONTANT : quelles anciennes références aboutissent sur ce produit.
        // On interroge `ref_final` (destination réelle après résolution), pas
        // `ref_replacement` : c'est là qu'atterrit le client.
        // Même collapse multi-orga que ci-dessus. La convergence N:1 est
        // LÉGITIME (jusqu'à 18 anciennes réfs vers une seule constatées) : on
        // regroupe les doublons d'orga, jamais les réfs distinctes.
        $replaces = $db->executeS(
            'SELECT r.`ref_replaced`,
                    CASE WHEN SUM(r.`conversion_type` = "set") > 0 THEN "set" ELSE "replace" END AS conversion_type,
                    MAX(r.`chain_depth`) AS chain_depth
             FROM `' . _DB_PREFIX_ . 'ms_replacement` r
             WHERE r.`ref_final` = "' . $ref . '"
             GROUP BY r.`ref_replaced`
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
