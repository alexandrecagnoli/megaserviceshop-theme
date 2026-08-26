<?php
/**
 * Script CLI de validation manuelle du CsvReader.
 *
 * Usage : php modules/megaservice_microfiches/tests/cli/dump_csv.php [chemin.csv]
 *
 * Sans argument : itère sur les 3 samples motos versionnés.
 *
 * Pour chaque fichier, affiche :
 *   - encodage détecté
 *   - délimiteur détecté
 *   - headers normalisés
 *   - 2 premières rows (champs utiles uniquement)
 *   - total des rows valides (MODELNUMBER commençant par $M-)
 *
 * Ce script est volontairement standalone (aucune dépendance Presta) pour
 * pouvoir être lancé localement sans bootstrap.
 */

require_once __DIR__ . '/../../classes/importers/CsvReader.php';

const USEFUL_COLS = ['modelnumber', 'annee', 'article_number', 'picture', 'category_fr', 'model_name_fr'];

$args = array_slice($argv, 1);
$files = $args !== [] ? $args : [
    __DIR__ . '/../../samples/sample_KTM_MOTORCYCLES.csv',
    __DIR__ . '/../../samples/sample_HQV_MOTORCYCLES.csv',
    __DIR__ . '/../../samples/sample_GASGAS_MOTORCYCLES.csv',
];

foreach ($files as $file) {
    echo str_repeat('=', 80) . PHP_EOL;
    echo basename($file) . PHP_EOL;
    echo str_repeat('=', 80) . PHP_EOL;

    try {
        $reader = new CsvReader($file);
    } catch (Throwable $e) {
        echo "ERREUR: " . $e->getMessage() . PHP_EOL . PHP_EOL;
        continue;
    }

    echo 'Encoding  : ' . $reader->getEncoding() . PHP_EOL;
    echo 'Delimiter : ' . json_encode($reader->getDelimiter()) . PHP_EOL;
    echo 'Headers   : ' . count($reader->getHeaders()) . ' colonnes' . PHP_EOL;
    foreach ($reader->getHeaders() as $i => $h) {
        printf("  [%2d] %s%s\n", $i, $h, in_array($h, USEFUL_COLS, true) ? '  ← utile' : '');
    }
    echo PHP_EOL;

    $total = 0;
    $valides = 0;
    $samples = [];
    foreach ($reader->rows() as $row) {
        $total++;
        $mn = $row['modelnumber'] ?? '';
        if (is_string($mn) && strncmp($mn, '$M-', 3) === 0) {
            $valides++;
            if (count($samples) < 2) {
                $samples[] = $row;
            }
        }
    }
    echo "Rows totales : $total" . PHP_EOL;
    echo "Rows valides ('\$M-...' MODELNUMBER) : $valides" . PHP_EOL . PHP_EOL;

    foreach ($samples as $i => $row) {
        echo "--- sample row " . ($i + 1) . " ---" . PHP_EOL;
        foreach (USEFUL_COLS as $col) {
            $v = $row[$col] ?? '(absent)';
            if (is_string($v) && strlen($v) > 100) {
                $v = substr($v, 0, 100) . '...';
            }
            printf("  %-18s = %s\n", $col, $v);
        }
        echo PHP_EOL;
    }
}
