<?php
/**
 * Tests unitaires des méthodes pures de MicrofichesImporter :
 *   - deduceMotoSerial() : extrait le serial depuis le nom de fichier
 *   - buildRow()         : mapping CSV row → row prête à insérer
 *
 * Pas d'accès DB → testable hors contexte Presta.
 *
 * Usage : php modules/megaservice_microfiches/tests/cli/test_microfiches_importer_units.php
 */

require_once __DIR__ . '/../../classes/importers/MicrofichesTaxonomy.php';
require_once __DIR__ . '/../../classes/importers/MicrofichesImporter.php';

$pass = 0;
$fail = 0;
$fails = [];

function check(string $label, $actual, $expected): void
{
    global $pass, $fail, $fails;
    if ($actual === $expected) {
        $pass++;
    } else {
        $fail++;
        $fails[] = sprintf(
            "  [FAIL] %s\n         attendu = %s\n         obtenu  = %s",
            $label, var_export($expected, true), var_export($actual, true)
        );
    }
}

// =====================================================================
// deduceMotoSerial()
// =====================================================================

check('deduceMotoSerial F0403X7.csv',           MicrofichesImporter::deduceMotoSerial('F0403X7.csv'),               'F0403X7');
check('deduceMotoSerial /path/F0403X7.csv',     MicrofichesImporter::deduceMotoSerial('/path/F0403X7.csv'),         'F0403X7');
check('deduceMotoSerial sample_F0403X7.csv',    MicrofichesImporter::deduceMotoSerial('sample_F0403X7.csv'),        'sample_F0403X7');
check('deduceMotoSerial sans .csv',             MicrofichesImporter::deduceMotoSerial('F0403X7'),                   'F0403X7');
check('deduceMotoSerial 00JG457B.csv',          MicrofichesImporter::deduceMotoSerial('00JG457B.csv'),              '00JG457B');

// =====================================================================
// buildRow() — happy path
// =====================================================================

$happyRow = [
    'vue_eclatee_type'         => 'engine',
    'vue_eclatee_number'       => '30',
    'vue_eclatee'              => 'ENGINE CASE',
    'vue_eclatee_image_preview'=> 'https://example.com/preview.gif',
    'vue_eclatee_image'        => 'https://example.com/full.png',
    'vue_eclatee_image_width'  => '565',
    'vue_eclatee_image_height' => '754',
    'article_id'               => 'A44030000044',
    'sequence_number'          => '1',
    'position_left'            => '286',
    'position_bottom'          => '643',
    'article'                  => 'Engine case cmpl.',
    'quantity'                 => '1',
];
$built = MicrofichesImporter::buildRow($happyRow);
check('buildRow partie engine→moteur',  $built['partie'],              'moteur');
check('buildRow numero_constructeur',   $built['numero_constructeur'], 30);
check('buildRow nom_constructeur',      $built['nom_constructeur'],    'ENGINE CASE');
check('buildRow image_full_url',        $built['image_full_url'],      'https://example.com/full.png');
check('buildRow image_thumb_url',       $built['image_thumb_url'],     'https://example.com/preview.gif');
check('buildRow image_width',           $built['image_width'],         565);
check('buildRow image_height',          $built['image_height'],        754);
check('buildRow article_ref',           $built['article_ref'],         'A44030000044');
check('buildRow article_label',         $built['article_label'],       'Engine case cmpl.');
check('buildRow sequence_number',       $built['sequence_number'],     1);
check('buildRow position_x',            $built['position_x'],          286);
check('buildRow position_y',            $built['position_y'],          643);
check('buildRow qty_recommended',       $built['qty_recommended'],     1);

// =====================================================================
// buildRow() — mapping partie
// =====================================================================

$frameRow = $happyRow;
$frameRow['vue_eclatee_type'] = 'frame';
$frameRow['vue_eclatee']      = 'FRONT FORK';
$built = MicrofichesImporter::buildRow($frameRow);
check('buildRow partie frame→cycle', $built['partie'], 'cycle');

// =====================================================================
// buildRow() — rejets (null si invalide)
// =====================================================================

check('buildRow rejette vue_eclatee_type inconnu', MicrofichesImporter::buildRow(
    array_merge($happyRow, ['vue_eclatee_type' => 'electrical'])
), null);

check('buildRow rejette vue_eclatee_number non num', MicrofichesImporter::buildRow(
    array_merge($happyRow, ['vue_eclatee_number' => 'XX'])
), null);

check('buildRow rejette vue_eclatee vide', MicrofichesImporter::buildRow(
    array_merge($happyRow, ['vue_eclatee' => ''])
), null);

check('buildRow rejette vue_eclatee_image vide', MicrofichesImporter::buildRow(
    array_merge($happyRow, ['vue_eclatee_image' => ''])
), null);

check('buildRow rejette article_id vide', MicrofichesImporter::buildRow(
    array_merge($happyRow, ['article_id' => ''])
), null);

check('buildRow rejette sequence_number non num', MicrofichesImporter::buildRow(
    array_merge($happyRow, ['sequence_number' => 'X'])
), null);

check('buildRow rejette position_left vide', MicrofichesImporter::buildRow(
    array_merge($happyRow, ['position_left' => ''])
), null);

check('buildRow rejette position_bottom vide', MicrofichesImporter::buildRow(
    array_merge($happyRow, ['position_bottom' => ''])
), null);

// =====================================================================
// buildRow() — variantes (thumb absent, quantity 0, label vide)
// =====================================================================

$variantRow = $happyRow;
$variantRow['vue_eclatee_image_preview'] = '';
$variantRow['article']                   = '';
$variantRow['quantity']                  = '0';
$built = MicrofichesImporter::buildRow($variantRow);
check('buildRow thumb vide → null',     $built['image_thumb_url'], null);
check('buildRow label vide → null',     $built['article_label'],   null);
check('buildRow quantity 0 (kit)',      $built['qty_recommended'], 0);

// quantity > 255 → clamp à 255 (TINYINT UNSIGNED)
$bigQtyRow = $happyRow;
$bigQtyRow['quantity'] = '999';
$built = MicrofichesImporter::buildRow($bigQtyRow);
check('buildRow quantity 999 clampée 255', $built['qty_recommended'], 255);

// quantity absente → défaut 1
$noQtyRow = $happyRow;
unset($noQtyRow['quantity']);
$built = MicrofichesImporter::buildRow($noQtyRow);
check('buildRow quantity absente → 1', $built['qty_recommended'], 1);

// Image width absente → null (champ optionnel)
$noWidthRow = $happyRow;
$noWidthRow['vue_eclatee_image_width']  = '';
$noWidthRow['vue_eclatee_image_height'] = '';
$built = MicrofichesImporter::buildRow($noWidthRow);
check('buildRow image_width vide → null',  $built['image_width'],  null);
check('buildRow image_height vide → null', $built['image_height'], null);

// =====================================================================
// buildRow() — robustesse aux espaces (trim)
// =====================================================================

$spacedRow = [
    'vue_eclatee_type'         => '  engine ',
    'vue_eclatee_number'       => ' 30 ',
    'vue_eclatee'              => '  ENGINE CASE  ',
    'vue_eclatee_image'        => ' https://example.com/full.png ',
    'vue_eclatee_image_preview'=> '',
    'vue_eclatee_image_width'  => '',
    'vue_eclatee_image_height' => '',
    'article_id'               => ' A44030000044 ',
    'sequence_number'          => '1',
    'position_left'            => '286',
    'position_bottom'          => '643',
    'article'                  => '',
    'quantity'                 => '1',
];
$built = MicrofichesImporter::buildRow($spacedRow);
check('buildRow trim partie',           $built['partie'],           'moteur');
check('buildRow trim nom_constructeur', $built['nom_constructeur'], 'ENGINE CASE');
check('buildRow trim image_full_url',   $built['image_full_url'],   'https://example.com/full.png');
check('buildRow trim article_ref',      $built['article_ref'],      'A44030000044');

// =====================================================================
// Résultat final
// =====================================================================

echo "Tests : $pass passés, $fail échoués" . PHP_EOL;
if ($fail > 0) {
    echo PHP_EOL . implode("\n", $fails) . PHP_EOL;
    exit(1);
}
echo "OK." . PHP_EOL;
exit(0);
