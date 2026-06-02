<?php
/**
 * Tests unitaires "à la main" du dictionnaire MotosTaxonomy, sans PHPUnit.
 *
 * Couvre tous les patterns du brief §4.2 avec des cas forgés représentatifs
 * de chaque marque. Sert de spec exécutable pour le mapping `type`.
 *
 * Exit code : 0 si tous les tests passent, 1 sinon.
 *
 * Usage : php modules/megaservice_microfiches/tests/cli/test_taxonomy_units.php
 */

require_once __DIR__ . '/../../classes/importers/MotosTaxonomy.php';

$tests = [
    // ---- coreName : strip d'année finale --------------------------------
    ['coreName',   '125 Duke 2026',          '125 Duke'],
    ['coreName',   'Svartpilen 401 2025',    'Svartpilen 401'],
    ['coreName',   'Norden 901 Expedition',  'Norden 901 Expedition'], // pas d'année
    ['coreName',   '',                       ''],
    ['coreName',   '2025',                   ''],

    // ---- cylindree : 1er nombre du core_name ----------------------------
    ['cylindree',  '125 Duke',               125],
    ['cylindree',  'Svartpilen 401',         401],
    ['cylindree',  'Norden 901 Expedition',  901],
    ['cylindree',  'Duke',                   null],
    ['cylindree',  'RC 8 R',                 8],

    // ---- type : Electrique (priorité 1) ---------------------------------
    ['type',       'SX-E 5',                 'Electrique'], // KTM électrique
    ['type',       'MC-E 5',                 'Electrique'], // GASGAS électrique
    ['type',       'EE 5',                   'Electrique'], // HQV électrique
    ['type',       'Freeride E',             'Electrique'],
    ['type',       'Freeride  E',            'Electrique'], // espace multiple OK

    // ---- type : Trial (GASGAS) ------------------------------------------
    ['type',       'TXT 250',                'Trial'],
    ['type',       'TXT GP',                 'Trial'],
    ['type',       'TXT Racing',             'Trial'],

    // ---- type : Adventure -----------------------------------------------
    ['type',       '390 Adventure',          'Adventure'],
    ['type',       '1290 Super Adventure R', 'Adventure'],
    ['type',       'Norden 901',             'Adventure'], // HQV
    ['type',       'Norden 901 Expedition',  'Adventure'],

    // ---- type : Supermoto ------------------------------------------------
    ['type',       '690 SMC R',              'Supermoto'],
    ['type',       '450 SMR',                'Supermoto'],
    ['type',       'SM 510 R',               'Supermoto'], // HQV SM<sp>
    ['type',       'FS 450',                 'Supermoto'], // HQV FS<sp>
    ['type',       '50 Supermoto',           'Supermoto'],

    // ---- type : Naked ----------------------------------------------------
    ['type',       '125 Duke',               'Naked'],
    ['type',       'Duke 990 R',             'Naked'],
    ['type',       'RC 8 R',                 'Naked'], // RC<sp><num>
    ['type',       'Svartpilen 401',         'Naked'],
    ['type',       'Vitpilen 701',           'Naked'],

    // ---- type : Enduro routier (Enduro R / 701 Enduro) -------------------
    ['type',       '390 Enduro R',           'Enduro'],
    ['type',       '701 Enduro',             'Enduro'],

    // ---- type : Enduro compétition (EXC, EX, XC, TE, TX, FE, FX) --------
    ['type',       '300 EXC',                'Enduro'],
    ['type',       'TE 300i',                'Enduro'],
    ['type',       'FE 350',                 'Enduro'],
    ['type',       'FX 450',                 'Enduro'],
    ['type',       'TX 300',                 'Enduro'],
    ['type',       '350 XC-W',               'Enduro'],

    // ---- type : Motocross (SX, MC) — en dernier --------------------------
    ['type',       '250 SX-F',               'Motocross'], // SX matche, pas Electrique
    ['type',       '65 SX',                  'Motocross'],
    ['type',       'MC 250F',                'Motocross'],
    ['type',       'MC 85 17/14',            'Motocross'],

    // ---- type : Autres (fallback) ----------------------------------------
    ['type',       '125 LC2 80',             'Autres'],
    ['type',       '125 Sting',              'Autres'],
    ['type',       '',                       'Autres'],

    // ---- type : ordre prioritaire — Electrique gagne sur Motocross ------
    // Vérifie que "SX-E" ne tombe pas dans la règle SX/MC en dernier.
    ['type',       'SX-E 5 2025',            'Electrique'],
    ['type',       'MC-E 5 2025',            'Electrique'],

    // ---- isElectric ------------------------------------------------------
    ['isElectric', 'Electrique',             true],
    ['isElectric', 'Motocross',              false],
    ['isElectric', 'Autres',                 false],
];

$pass = 0;
$fail = 0;
$fails = [];

foreach ($tests as $i => [$method, $input, $expected]) {
    if ($method === 'coreName') {
        $actual = MotosTaxonomy::coreName($input);
    } elseif ($method === 'cylindree') {
        // cylindree prend un core_name, ici input EST déjà le core_name
        $actual = MotosTaxonomy::cylindree($input);
    } elseif ($method === 'type') {
        // type prend un core_name → on calcule via coreName d'abord
        $core   = MotosTaxonomy::coreName($input);
        $actual = MotosTaxonomy::type($core);
    } elseif ($method === 'isElectric') {
        $actual = MotosTaxonomy::isElectric($input);
    } else {
        throw new RuntimeException("Méthode inconnue: $method");
    }

    if ($actual === $expected) {
        $pass++;
    } else {
        $fail++;
        $fails[] = [
            'idx'      => $i,
            'method'   => $method,
            'input'    => $input,
            'expected' => $expected,
            'actual'   => $actual,
        ];
    }
}

echo "Tests : $pass passés, $fail échoués sur " . count($tests) . PHP_EOL;

if ($fail > 0) {
    echo PHP_EOL . "ÉCHECS :" . PHP_EOL;
    foreach ($fails as $f) {
        printf("  [#%d] %s(%s) → attendu=%s, obtenu=%s\n",
            $f['idx'],
            $f['method'],
            json_encode($f['input']),
            var_export($f['expected'], true),
            var_export($f['actual'], true)
        );
    }
    exit(1);
}

echo "OK." . PHP_EOL;
exit(0);
