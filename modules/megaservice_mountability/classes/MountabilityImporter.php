<?php
/**
 * Megaservice — Import des fichiers de montabilité.
 *
 * Fichier : montabilite_<marque>.csv — séparateur « ; », UTF-8, colonnes
 *   reference ; id_moto ; marque
 * Un fichier par marque, COMPLET par construction (pas de delta).
 * Volumétrie : jusqu'à ~500 000 lignes (KTM).
 *
 * Stratégie : TRUNCATE & RELOAD par marque.
 *   DELETE WHERE marque = X, puis insertion en masse par paquets.
 * On lit le fichier en STREAMING (fgetcsv ligne à ligne) : jamais 500 k lignes
 * en mémoire. La normalisation d'une ligne est isolée (méthode pure) pour être
 * testable hors PrestaShop.
 *
 * Dédoublonnage : assuré par l'index UNIQUE (reference, id_moto_constructeur)
 * via INSERT IGNORE. Le nombre de doublons se déduit après coup (lignes valides
 * − lignes réellement en base), sans maintenir un set de 500 k clés en mémoire.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MsMountabilityImporter
{
    /** Lignes par requête d'insertion groupée. */
    const CHUNK = 2000;

    /**
     * @param string $path   fichier CSV
     * @param string $marque marque de rechargement (KTM / HQV / GG…)
     * @return array<string,mixed> rapport d'exécution
     */
    public static function import($path, $marque)
    {
        $marque = strtoupper(trim((string) $marque));
        $report = [
            'marque'            => $marque,
            'lines_read'        => 0,
            'rejected_format'   => 0,
            'valid'             => 0,
            'loaded'            => 0,
            'duplicates'        => 0,
            'unresolved_refs'   => 0,
            'unresolved_motos'  => 0,
            'unresolved_moto_list' => [],
        ];

        if ($marque === '') {
            $report['error'] = 'Marque manquante.';
            return $report;
        }
        $fh = @fopen($path, 'r');
        if ($fh === false) {
            $report['error'] = 'Fichier illisible.';
            return $report;
        }

        $db  = Db::getInstance();
        $now = date('Y-m-d H:i:s');

        // Rechargement : on efface la marque avant de réinsérer.
        $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'ms_mountability` WHERE `marque` = "' . pSQL($marque) . '"');

        $buffer = [];
        $first  = true;

        while (($raw = fgetcsv($fh, 0, ';', '"', '')) !== false) {
            // Saute une éventuelle ligne d'en-tête.
            if ($first) {
                $first = false;
                if (isset($raw[0]) && strtolower(trim((string) $raw[0])) === 'reference') {
                    continue;
                }
            }

            ++$report['lines_read'];

            $row = self::normalizeRow($raw, $marque);
            if ($row === null) {
                ++$report['rejected_format'];
                continue;
            }
            ++$report['valid'];

            $buffer[] = '("' . pSQL($row['reference']) . '","' . pSQL($row['id_moto_constructeur'])
                      . '","' . pSQL($row['marque']) . '","' . $now . '")';

            if (count($buffer) >= self::CHUNK) {
                self::flush($db, $buffer);
                $buffer = [];
            }
        }
        if (!empty($buffer)) {
            self::flush($db, $buffer);
        }
        fclose($fh);

        // Lignes réellement en base pour cette marque (l'index UNIQUE a dédoublonné).
        $report['loaded']     = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'ms_mountability` WHERE `marque` = "' . pSQL($marque) . '"'
        );
        $report['duplicates'] = max(0, $report['valid'] - $report['loaded']);

        self::countUnresolved($db, $marque, $report);

        return $report;
    }

    /**
     * Valide et normalise une ligne brute (3 colonnes). PURE : aucun accès base,
     * testable en CLI. Retourne null si la ligne est à rejeter.
     *
     * @param array<int,string|null> $raw
     * @return array{reference:string,id_moto_constructeur:string,marque:string}|null
     */
    public static function normalizeRow($raw, $marqueDefault = '')
    {
        if (!is_array($raw) || count($raw) < 2) {
            return null;
        }

        $reference = isset($raw[0]) ? trim((string) $raw[0]) : '';
        $idMoto    = isset($raw[1]) ? trim((string) $raw[1]) : '';
        // La marque du fichier fait foi si présente, sinon celle du formulaire.
        $marque    = isset($raw[2]) && trim((string) $raw[2]) !== ''
            ? strtoupper(trim((string) $raw[2]))
            : strtoupper(trim((string) $marqueDefault));

        if ($reference === '' || $idMoto === '' || $marque === '') {
            return null;
        }

        return [
            'reference'            => $reference,
            'id_moto_constructeur' => $idMoto,
            'marque'               => $marque,
        ];
    }

    /** Insertion groupée (IGNORE : l'index UNIQUE absorbe les doublons intra-fichier). */
    private static function flush(Db $db, array $buffer)
    {
        $db->execute(
            'INSERT IGNORE INTO `' . _DB_PREFIX_ . 'ms_mountability`
                (`reference`, `id_moto_constructeur`, `marque`, `date_add`)
             VALUES ' . implode(',', $buffer)
        );
    }

    /**
     * Compte les réfs non résolues côté catalogue et les motos non résolues côté
     * ms_moto — signal d'un delta de référentiel à remonter. La liste des motos
     * inconnues est plafonnée (téléchargeable en entier via l'écran BO).
     */
    private static function countUnresolved(Db $db, $marque, array &$report)
    {
        $m = pSQL($marque);

        $report['unresolved_refs'] = (int) $db->getValue(
            'SELECT COUNT(DISTINCT c.`reference`)
             FROM `' . _DB_PREFIX_ . 'ms_mountability` c
             LEFT JOIN `' . _DB_PREFIX_ . 'product` p            ON p.`reference` = c.`reference`
             LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa ON pa.`reference` = c.`reference`
             WHERE c.`marque` = "' . $m . '"
               AND p.`id_product` IS NULL AND pa.`id_product_attribute` IS NULL'
        );

        $unknownMotos = $db->executeS(
            'SELECT c.`id_moto_constructeur`, COUNT(*) AS nb
             FROM `' . _DB_PREFIX_ . 'ms_mountability` c
             LEFT JOIN `' . _DB_PREFIX_ . 'ms_moto` mo
                    ON mo.`' . bqSQL(MsMountability::MOTO_JOIN_COLUMN) . '` = c.`id_moto_constructeur`
             WHERE c.`marque` = "' . $m . '" AND mo.`id_moto` IS NULL
             GROUP BY c.`id_moto_constructeur`
             ORDER BY nb DESC
             LIMIT 200'
        ) ?: [];

        $report['unresolved_motos']     = count($unknownMotos);
        $report['unresolved_moto_list'] = $unknownMotos;
    }
}
