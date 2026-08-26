<?php
/**
 * Tests unitaires "à la main" des méthodes pures de MotosImporter :
 *   - buildRow()       : mapping CSV row → row prête à insérer
 *   - deduceMarque()   : détection marque depuis nom de fichier
 *
 * Pas d'accès DB → testable hors contexte Presta avec un stub MsMoto.
 *
 * Usage : php modules/megaservice_microfiches/tests/cli/test_motos_importer_units.php
 */

// Stub minimal pour pouvoir charger MotosImporter hors Presta (MARQUES seulement utilisé).
if (!class_exists('MsMoto')) {
    class MsMoto {
        public const MARQUES = ['KTM', 'HQV', 'GASGAS'];
    }
}

require_once __DIR__ . '/../../classes/importers/MotosTaxonomy.php';
require_once __DIR__ . '/../../classes/importers/MotosImporter.php';

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
            $label,
            var_export($expected, true),
            var_export($actual, true)
        );
    }
}

// =====================================================================
// deduceMarque()
// =====================================================================

check('deduceMarque KTM',           MotosImporter::deduceMarque('KTM_MOTORCYCLES.csv'),                'KTM');
check('deduceMarque HQV',           MotosImporter::deduceMarque('/path/HQV_MOTORCYCLES.csv'),          'HQV');
check('deduceMarque GASGAS',        MotosImporter::deduceMarque('GASGAS.csv'),                        'GASGAS');
check('deduceMarque sample_KTM',    MotosImporter::deduceMarque('sample_KTM_MOTORCYCLES.csv'),        'KTM');
check('deduceMarque sample_GASGAS', MotosImporter::deduceMarque('sample_GASGAS_MOTORCYCLES.csv'),     'GASGAS');
check('deduceMarque case-insensitive', MotosImporter::deduceMarque('ktm_motorcycles.csv'),            'KTM');

try {
    MotosImporter::deduceMarque('something_else.csv');
    check('deduceMarque unknown throws', 'no throw', 'should throw');
} catch (RuntimeException $e) {
    check('deduceMarque unknown throws', true, true);
}

// =====================================================================
// buildRow() — happy path
// =====================================================================

$happy = MotosImporter::buildRow([
    'modelnumber'   => '$M-125DUKE2026',
    'annee'         => '2026',
    'category_fr'   => '125 Duke 2026',
    'model_name_fr' => 'KTM 125 Duke 2026',
    'picture'       => 'pho_bike.png',
    'article_number'=> '00JG457B',
    'text_fr'       => 'Description longue...',
], 'KTM');

check('buildRow modelnumber',         $happy['modelnumber'],         '$M-125DUKE2026');
check('buildRow marque',              $happy['marque'],              'KTM');
check('buildRow annee',               $happy['annee'],               2026);
check('buildRow category_fr',         $happy['category_fr'],         '125 Duke 2026');
check('buildRow core_name',           $happy['core_name'],           '125 Duke');
check('buildRow type=Naked (Duke)',   $happy['type'],                'Naked');
check('buildRow cylindree',           $happy['cylindree'],           125);
check('buildRow is_electric=0',       $happy['is_electric'],         0);
check('buildRow nom_fr',              $happy['nom_fr'],              'KTM 125 Duke 2026');
check('buildRow description_fr',      $happy['description_fr'],      'Description longue...');
check('buildRow picture_main',        $happy['picture_main'],        'pho_bike.png');
check('buildRow serial_constructeur', $happy['serial_constructeur'], '00JG457B');

// =====================================================================
// buildRow() — variantes (electric, sans serial, sans description)
// =====================================================================

$elec = MotosImporter::buildRow([
    'modelnumber'   => '$M-MCE52024',
    'annee'         => '2024',
    'category_fr'   => 'MC-E 5 2024',
    'model_name_fr' => 'GASGAS MC-E 5 2024',
    'picture'       => 'pic.png',
    'article_number'=> '',
    'text_fr'       => '',
], 'GASGAS');
check('buildRow type=Electrique',      $elec['type'],                'Electrique');
check('buildRow is_electric=1',        $elec['is_electric'],         1);
check('buildRow serial null si vide',  $elec['serial_constructeur'], null);
check('buildRow desc null si vide',    $elec['description_fr'],      null);
check('buildRow cylindree 5',          $elec['cylindree'],           5);

// =====================================================================
// buildRow() — rejets (rows invalides → null)
// =====================================================================

check('buildRow rejette HTML noise', MotosImporter::buildRow([
    'modelnumber' => '<td style="...">',
], 'KTM'), null);

// Cross-badging CFMOTO / FTI : rejete (decision client 2026-06-05)
check('buildRow rejette serial _CFMOTO', MotosImporter::buildRow([
    'modelnumber'   => '$M-300EXCSDTPI2022',
    'annee'         => '2022',
    'category_fr'   => '300 EXC TPI SIX DAYS 2022',
    'model_name_fr' => 'KTM 300 EXC TPI SIX DAYS 2022',
    'article_number'=> '7487V2_CFMOTO',
], 'KTM'), null);

check('buildRow rejette serial _FTI', MotosImporter::buildRow([
    'modelnumber'   => '$M-NORDEN9012024',
    'annee'         => '2024',
    'category_fr'   => 'Norden 901 2024',
    'model_name_fr' => 'NORDEN 901 - 2024',
    'article_number'=> '2887X1_FTI',
], 'HQV'), null);

// Le serial F-prefixe equivalent doit etre accepte
$accepted = MotosImporter::buildRow([
    'modelnumber'   => '$M-NORDEN9012024',
    'annee'         => '2024',
    'category_fr'   => 'Norden 901 2024',
    'model_name_fr' => 'NORDEN 901 - 2024',
    'article_number'=> 'F2887X1',
], 'HQV');
check('buildRow accepte serial F-prefixe equivalent', $accepted['serial_constructeur'], 'F2887X1');

check('buildRow rejette MODELNUMBER absent', MotosImporter::buildRow([
    'modelnumber' => '',
], 'KTM'), null);

check('buildRow rejette préfixe absent', MotosImporter::buildRow([
    'modelnumber' => 'M-125DUKE2026', // sans $
], 'KTM'), null);

check('buildRow rejette annee non numérique', MotosImporter::buildRow([
    'modelnumber'   => '$M-X',
    'annee'         => 'XYZ',
    'category_fr'   => 'X',
    'model_name_fr' => 'X',
], 'KTM'), null);

check('buildRow rejette annee trop ancienne', MotosImporter::buildRow([
    'modelnumber'   => '$M-X',
    'annee'         => '1980',
    'category_fr'   => 'X',
    'model_name_fr' => 'X',
], 'KTM'), null);

check('buildRow rejette annee trop future', MotosImporter::buildRow([
    'modelnumber'   => '$M-X',
    'annee'         => '2100',
    'category_fr'   => 'X',
    'model_name_fr' => 'X',
], 'KTM'), null);

check('buildRow rejette category_fr vide', MotosImporter::buildRow([
    'modelnumber'   => '$M-X',
    'annee'         => '2024',
    'category_fr'   => '',
    'model_name_fr' => 'X',
], 'KTM'), null);

check('buildRow rejette nom_fr vide', MotosImporter::buildRow([
    'modelnumber'   => '$M-X',
    'annee'         => '2024',
    'category_fr'   => 'X 2024',
    'model_name_fr' => '',
], 'KTM'), null);

// =====================================================================
// buildRow() — robustesse aux espaces (trim)
// =====================================================================

$trimmed = MotosImporter::buildRow([
    'modelnumber'   => '  $M-X 2024  ',
    'annee'         => ' 2024 ',
    'category_fr'   => '  X 2024  ',
    'model_name_fr' => ' X ',
], 'KTM');
check('buildRow trim modelnumber',  $trimmed['modelnumber'], '$M-X 2024');
check('buildRow trim annee',        $trimmed['annee'],       2024);
check('buildRow trim category_fr',  $trimmed['category_fr'], 'X 2024');
check('buildRow trim nom_fr',       $trimmed['nom_fr'],      'X');

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
