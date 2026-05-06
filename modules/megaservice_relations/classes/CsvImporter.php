<?php
/**
 * Parser + importer CSV pour les relations Powerparts.
 *
 * Format CSV attendu (3 cols requises, 2 optionnelles) :
 *   id_product_source, relation_type, id_product_target [, position] [, recommended_qty]
 *
 * Robustesse :
 * - Auto-détection du séparateur (, ou ;)
 * - Strip du BOM UTF-8 si présent
 * - fgetcsv() ligne par ligne (pas de chargement complet → gros fichiers OK)
 * - Erreurs collectées avec numéro de ligne (pas de fail-fast)
 * - 3 modes : append / replace_for_listed / full_replace
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/ProductRelationService.php';

class MsRelationsCsvImporter
{
    const REQUIRED_COLS = ['id_product_source', 'relation_type', 'id_product_target'];
    const OPTIONAL_COLS = ['position', 'recommended_qty'];

    /**
     * @param string $filepath Chemin du fichier uploadé (tmp_name)
     * @param string $mode     'append' | 'replace_for_listed' | 'full_replace'
     * @return array stats : ['imported' => int, 'skipped' => int, 'deleted' => int, 'errors' => [['line', 'msg']]]
     */
    public function import($filepath, $mode = 'append')
    {
        $stats = ['imported' => 0, 'skipped' => 0, 'deleted' => 0, 'errors' => []];

        $fh = @fopen($filepath, 'r');
        if (!$fh) {
            $stats['errors'][] = ['line' => 0, 'msg' => 'Impossible d\'ouvrir le fichier'];
            return $stats;
        }

        // ── Strip BOM UTF-8 si présent
        $first = fread($fh, 3);
        if ($first !== "\xEF\xBB\xBF") {
            rewind($fh);
        }

        // ── Auto-détection du délimiteur (Excel FR exporte en ;)
        $sniffPos = ftell($fh);
        $sniffLine = fgets($fh);
        fseek($fh, $sniffPos);
        $delim = (substr_count($sniffLine, ';') > substr_count($sniffLine, ',')) ? ';' : ',';

        // ── Header
        $header = fgetcsv($fh, 0, $delim);
        if (!$header) {
            $stats['errors'][] = ['line' => 1, 'msg' => 'Fichier vide ou sans header'];
            fclose($fh);
            return $stats;
        }

        // Normalize header (trim, lowercase, strip BOM résiduel)
        $header = array_map(function ($h) {
            return strtolower(trim((string) $h, " \t\n\r\0\x0B\xEF\xBB\xBF"));
        }, $header);

        $missing = array_diff(self::REQUIRED_COLS, $header);
        if (!empty($missing)) {
            $stats['errors'][] = [
                'line' => 1,
                'msg'  => 'Colonnes manquantes : ' . implode(', ', $missing) . ' (header trouvé : ' . implode(', ', $header) . ')',
            ];
            fclose($fh);
            return $stats;
        }

        $colIdx = array_flip($header);

        // ── Mode : full_replace → vide la table avant tout
        if ($mode === 'full_replace') {
            $deleted = (int) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'megaservice_product_relation`'
            );
            Db::getInstance()->execute(
                'DELETE FROM `' . _DB_PREFIX_ . 'megaservice_product_relation`'
            );
            $stats['deleted'] = $deleted;
        }

        // ── Mode : replace_for_listed → 1ère passe pour collecter les (source, type) à vider
        if ($mode === 'replace_for_listed') {
            $rows = $this->readAllRows($fh, $delim);
            $sourcesToReset = $this->collectSourceTypePairs($rows, $colIdx);
            $stats['deleted'] = $this->resetRelations($sourcesToReset);
            foreach ($rows as $r) {
                $this->processRow($r['data'], $r['line'], $colIdx, $stats);
            }
        } else {
            // append OU full_replace : process line by line
            $lineNum = 1;
            while (($row = fgetcsv($fh, 0, $delim)) !== false) {
                $lineNum++;
                if ($this->isEmptyRow($row)) continue;
                $this->processRow($row, $lineNum, $colIdx, $stats);
            }
        }

        fclose($fh);
        return $stats;
    }

    private function readAllRows($fh, $delim)
    {
        $rows = [];
        $lineNum = 1;
        while (($row = fgetcsv($fh, 0, $delim)) !== false) {
            $lineNum++;
            if ($this->isEmptyRow($row)) continue;
            $rows[] = ['line' => $lineNum, 'data' => $row];
        }
        return $rows;
    }

    private function collectSourceTypePairs(array $rows, array $colIdx)
    {
        $pairs = [];
        foreach ($rows as $r) {
            $idSrc = (int) ($r['data'][$colIdx['id_product_source']] ?? 0);
            $type  = trim((string) ($r['data'][$colIdx['relation_type']] ?? ''));
            if ($idSrc && $type) {
                $pairs[$idSrc][$type] = true;
            }
        }
        return $pairs;
    }

    private function resetRelations(array $sourcesToReset)
    {
        $deleted = 0;
        foreach ($sourcesToReset as $idSrc => $types) {
            foreach (array_keys($types) as $type) {
                $countSql = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'megaservice_product_relation`
                             WHERE `id_product_source` = ' . (int) $idSrc . '
                             AND `relation_type` = "' . pSQL($type) . '"';
                $deleted += (int) Db::getInstance()->getValue($countSql);
                Db::getInstance()->execute(
                    'DELETE FROM `' . _DB_PREFIX_ . 'megaservice_product_relation`
                     WHERE `id_product_source` = ' . (int) $idSrc . '
                     AND `relation_type` = "' . pSQL($type) . '"'
                );
            }
        }
        return $deleted;
    }

    private function processRow(array $row, $lineNum, array $colIdx, array &$stats)
    {
        $idSrc    = (int) ($row[$colIdx['id_product_source']] ?? 0);
        $type     = trim((string) ($row[$colIdx['relation_type']] ?? ''));
        $idTarget = (int) ($row[$colIdx['id_product_target']] ?? 0);
        $position = isset($colIdx['position']) ? (int) ($row[$colIdx['position']] ?? 0) : 0;
        $qty      = isset($colIdx['recommended_qty']) ? (int) ($row[$colIdx['recommended_qty']] ?? 1) : 1;
        $qty      = max(1, $qty);

        // Validation
        if (!$idSrc) {
            $stats['errors'][] = ['line' => $lineNum, 'msg' => 'id_product_source manquant ou non numérique'];
            return;
        }
        if (!$idTarget) {
            $stats['errors'][] = ['line' => $lineNum, 'msg' => 'id_product_target manquant ou non numérique'];
            return;
        }
        if ($idSrc === $idTarget) {
            $stats['errors'][] = ['line' => $lineNum, 'msg' => 'id_product_source et id_product_target identiques (' . $idSrc . ')'];
            return;
        }
        if (!in_array($type, MsProductRelationService::allTypes(), true)) {
            $stats['errors'][] = [
                'line' => $lineNum,
                'msg'  => 'relation_type invalide : "' . $type . '" (attendu : ' . implode(', ', MsProductRelationService::allTypes()) . ')',
            ];
            return;
        }

        // Vérifie que les produits existent
        if (!$this->productExists($idSrc)) {
            $stats['errors'][] = ['line' => $lineNum, 'msg' => 'id_product_source ' . $idSrc . ' inexistant'];
            return;
        }
        if (!$this->productExists($idTarget)) {
            $stats['errors'][] = ['line' => $lineNum, 'msg' => 'id_product_target ' . $idTarget . ' inexistant'];
            return;
        }

        // Insert via service (ON DUPLICATE KEY UPDATE → dédoublonne via UNIQUE)
        $existedBefore = $this->relationExists($idSrc, $type, $idTarget);
        $ok = MsProductRelationService::addRelation($idSrc, $type, $idTarget, $position ?: null, $qty);
        if (!$ok) {
            $stats['errors'][] = ['line' => $lineNum, 'msg' => 'Erreur SQL à l\'insert'];
            return;
        }
        if ($existedBefore) {
            $stats['skipped']++; // existait déjà → row mise à jour mais pas un "nouveau" ajout
        } else {
            $stats['imported']++;
        }
    }

    private function isEmptyRow($row)
    {
        if ($row === null || $row === false) return true;
        if (count($row) === 1 && trim((string) $row[0]) === '') return true;
        return empty(array_filter($row, function ($v) { return trim((string) $v) !== ''; }));
    }

    private $productExistsCache = [];

    private function productExists($idProduct)
    {
        $idProduct = (int) $idProduct;
        if (isset($this->productExistsCache[$idProduct])) {
            return $this->productExistsCache[$idProduct];
        }
        $val = Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'product` WHERE `id_product` = ' . $idProduct
        );
        return $this->productExistsCache[$idProduct] = (bool) $val;
    }

    private function relationExists($idSrc, $type, $idTarget)
    {
        $val = Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'megaservice_product_relation`
             WHERE `id_product_source` = ' . (int) $idSrc . '
             AND `relation_type` = "' . pSQL($type) . '"
             AND `id_product_target` = ' . (int) $idTarget
        );
        return (bool) $val;
    }
}
