<?php
/**
 * Megaservice — Parsing des fichiers de remplacement constructeur.
 *
 * Format (SPEC §2.1) : séparateur `;`, valeurs entre guillemets doubles,
 * UTF-8, CRLF. Colonnes :
 *   ArticleNumber ; ArticleNumberReplace ; ConversionType ; SalesOrga ;
 *   RelationType ; UnitQuantity
 *
 * Logique VOLONTAIREMENT pure (aucune dépendance PrestaShop / base) : le
 * parsing et les contrôles sont testables en CLI sur les vrais fichiers.
 * L'écriture en base est faite par l'appelant.
 *
 * Dédoublonnage : la clé métier est (ref_replaced, ref_replacement) SANS
 * l'organisation de vente. Mesuré sur les 6 fichiers réels : 22 963 lignes →
 * 16 405 relations uniques (6 546 doublons absorbés, 12 auto-références rejetées).
 */

class MsReplacementCsvImporter
{
    const COL_FROM = 'ArticleNumber';
    const COL_TO   = 'ArticleNumberReplace';
    const COL_CONV = 'ConversionType';
    const COL_ORGA = 'SalesOrga';
    const COL_REL  = 'RelationType';
    const COL_QTY  = 'UnitQuantity';

    const REQUIRED_COLS = [
        self::COL_FROM, self::COL_TO, self::COL_CONV, self::COL_QTY,
    ];

    /**
     * Parse plusieurs fichiers en un seul jeu dédoublonné.
     *
     * @param string[] $paths
     * @return array{rows: array<int,array<string,mixed>>, report: array<string,mixed>}
     */
    public static function parseFiles(array $paths)
    {
        $rows   = [];   // clé "from|to" → ligne normalisée
        $report = self::emptyReport();

        foreach ($paths as $path) {
            self::readInto($path, $rows, $report);
        }

        $report['accepted'] = count($rows);

        return ['rows' => array_values($rows), 'report' => $report];
    }

    /**
     * @return array{rows: array<int,array<string,mixed>>, report: array<string,mixed>}
     */
    public static function parseFile($path)
    {
        return self::parseFiles([$path]);
    }

    /**
     * @param array<string,array<string,mixed>> $rows  accumulateur (par clé métier)
     * @param array<string,mixed>               $report
     */
    private static function readInto($path, array &$rows, array &$report)
    {
        $fh = @fopen($path, 'r');
        if ($fh === false) {
            $report['files_unreadable'][] = $path;
            return;
        }
        ++$report['files'];

        // 5e paramètre (escape) explicite : évite la dépréciation PHP 8.4 et
        // les surprises sur les refs contenant un antislash.
        $header = fgetcsv($fh, 0, ';', '"', '');
        if (!is_array($header)) {
            $report['files_unreadable'][] = $path;
            fclose($fh);
            return;
        }
        $header = array_map(static function ($h) {
            return trim((string) $h, " \t\n\r\0\x0B\"\xEF\xBB\xBF"); // + BOM UTF-8
        }, $header);

        $missing = array_diff(self::REQUIRED_COLS, $header);
        if (!empty($missing)) {
            $report['files_bad_header'][basename($path)] = array_values($missing);
            fclose($fh);
            return;
        }

        while (($raw = fgetcsv($fh, 0, ';', '"', '')) !== false) {
            if ($raw === [null] || count($raw) < count($header)) {
                ++$report['rejected_malformed'];
                continue;
            }
            ++$report['lines_read'];

            $assoc = array_combine($header, array_slice($raw, 0, count($header)));
            $row   = self::normalize($assoc, $report);
            if ($row === null) {
                continue;
            }

            $key = $row['ref_replaced'] . '|' . $row['ref_replacement'];

            if (isset($rows[$key])) {
                ++$report['duplicates'];
                // Un même couple peut arriver typé `replace` dans un fichier et
                // `set` dans un autre (9 lignes concernées sur les fichiers
                // réels). `set` porte l'information la plus complète → il
                // l'emporte, sinon on perdrait des composants du set.
                if ($row['conversion_type'] === MsReplacement::TYPE_SET
                    && $rows[$key]['conversion_type'] !== MsReplacement::TYPE_SET) {
                    $rows[$key]['conversion_type'] = MsReplacement::TYPE_SET;
                    $rows[$key]['quantity']        = max($rows[$key]['quantity'], $row['quantity']);
                    ++$report['upgraded_to_set'];
                }
                continue;
            }

            $rows[$key] = $row;
        }

        fclose($fh);
    }

    /**
     * Normalise et valide une ligne. Retourne null si elle est rejetée
     * (le motif est comptabilisé dans le rapport).
     *
     * @param array<string,string> $assoc
     * @param array<string,mixed>  $report
     * @return array<string,mixed>|null
     */
    private static function normalize(array $assoc, array &$report)
    {
        $from = trim((string) $assoc[self::COL_FROM]);
        $to   = trim((string) $assoc[self::COL_TO]);

        if ($from === '' || $to === '') {
            ++$report['rejected_empty'];
            return null;
        }

        // Auto-référence : une pièce ne se remplace pas elle-même (12 cas réels).
        if ($from === $to) {
            ++$report['rejected_self_ref'];
            return null;
        }

        $conv = strtolower(trim((string) $assoc[self::COL_CONV]));
        if ($conv !== MsReplacement::TYPE_REPLACE && $conv !== MsReplacement::TYPE_SET) {
            ++$report['rejected_conversion_type'];
            return null;
        }

        // RelationType est redondant avec ConversionType : on s'en sert comme
        // contrôle de cohérence du fichier, pas comme source.
        $rel = trim((string) ($assoc[self::COL_REL] ?? ''));
        if ($rel !== '') {
            $expected = ($conv === MsReplacement::TYPE_SET) ? '1:N' : '1:1';
            if ($rel !== $expected) {
                ++$report['rejected_relation_mismatch'];
                return null;
            }
        }

        // Format constructeur : "1.000". Quantité entière strictement positive.
        $qtyRaw = str_replace(',', '.', trim((string) $assoc[self::COL_QTY]));
        $qty    = (float) $qtyRaw;
        if ($qtyRaw === '' || $qty <= 0 || floor($qty) != $qty) {
            ++$report['rejected_quantity'];
            return null;
        }

        return [
            'sales_orga'      => trim((string) ($assoc[self::COL_ORGA] ?? '')) ?: null,
            'ref_replaced'    => $from,
            'ref_replacement' => $to,
            'conversion_type' => $conv,
            'quantity'        => (int) $qty,
        ];
    }

    /** @return array<string,mixed> */
    private static function emptyReport()
    {
        return [
            'files'                     => 0,
            'files_unreadable'          => [],
            'files_bad_header'          => [],
            'lines_read'                => 0,
            'accepted'                  => 0,
            'duplicates'                => 0,
            'upgraded_to_set'           => 0,
            'rejected_malformed'        => 0,
            'rejected_empty'            => 0,
            'rejected_self_ref'         => 0,
            'rejected_conversion_type'  => 0,
            'rejected_relation_mismatch' => 0,
            'rejected_quantity'         => 0,
        ];
    }
}
