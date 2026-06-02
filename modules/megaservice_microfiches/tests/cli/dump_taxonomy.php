<?php
/**
 * Script CLI de validation manuelle de MotosTaxonomy.
 *
 * Usage : php modules/megaservice_microfiches/tests/cli/dump_taxonomy.php
 *
 * Affiche, pour chaque sample motos, un tableau MODELNUMBER | category_fr →
 * core_name | cylindree | type | is_electric, puis un récap des compteurs par
 * type et la liste des motos tombées en 'Autres' (à corriger manuellement en BO).
 */

require_once __DIR__ . '/../../classes/importers/CsvReader.php';
require_once __DIR__ . '/../../classes/importers/MotosTaxonomy.php';

$samples = [
    'KTM'    => __DIR__ . '/../../samples/sample_KTM_MOTORCYCLES.csv',
    'HQV'    => __DIR__ . '/../../samples/sample_HQV_MOTORCYCLES.csv',
    'GASGAS' => __DIR__ . '/../../samples/sample_GASGAS_MOTORCYCLES.csv',
];

$grandTotal = [];
$grandAutres = [];

foreach ($samples as $marque => $file) {
    echo str_repeat('=', 110) . PHP_EOL;
    echo "$marque  —  " . basename($file) . PHP_EOL;
    echo str_repeat('=', 110) . PHP_EOL;

    $reader = new CsvReader($file);

    printf("%-26s %-30s %-26s %-8s %-12s %s\n",
        'MODELNUMBER', 'category_fr', 'core_name', 'cyl', 'type', 'electric');
    echo str_repeat('-', 110) . PHP_EOL;

    $counts = [];
    $autres = [];

    foreach ($reader->rows() as $row) {
        $mn = $row['modelnumber'] ?? '';
        if (strncmp($mn, '$M-', 3) !== 0) {
            continue;
        }
        $cat       = $row['category_fr'] ?? '';
        $core      = MotosTaxonomy::coreName($cat);
        $cyl       = MotosTaxonomy::cylindree($core);
        $type      = MotosTaxonomy::type($core);
        $electric  = MotosTaxonomy::isElectric($type);

        $counts[$type] = ($counts[$type] ?? 0) + 1;
        $grandTotal[$type] = ($grandTotal[$type] ?? 0) + 1;

        if ($type === MotosTaxonomy::TYPE_AUTRES) {
            $autres[]      = ['mn' => $mn, 'cat' => $cat, 'core' => $core];
            $grandAutres[] = ['marque' => $marque, 'mn' => $mn, 'cat' => $cat, 'core' => $core];
        }

        printf("%-26s %-30s %-26s %-8s %-12s %s\n",
            mb_substr($mn, 0, 26),
            mb_substr($cat, 0, 30),
            mb_substr($core, 0, 26),
            $cyl !== null ? (string) $cyl : '-',
            $type,
            $electric ? 'YES' : '-'
        );
    }

    echo PHP_EOL . "Récap $marque : ";
    ksort($counts);
    foreach ($counts as $t => $n) {
        echo "$t=$n  ";
    }
    echo PHP_EOL . PHP_EOL;
}

echo str_repeat('=', 110) . PHP_EOL;
echo "TOTAL 3 marques" . PHP_EOL;
echo str_repeat('=', 110) . PHP_EOL;
ksort($grandTotal);
foreach ($grandTotal as $t => $n) {
    printf("  %-12s : %d\n", $t, $n);
}

if ($grandAutres !== []) {
    echo PHP_EOL . "⚠️  Motos tombées en 'Autres' (à reviewer pour règles manquantes) :" . PHP_EOL;
    foreach ($grandAutres as $a) {
        printf("  [%s] %-26s %-30s → %s\n",
            $a['marque'], $a['mn'], $a['cat'], $a['core']);
    }
}
