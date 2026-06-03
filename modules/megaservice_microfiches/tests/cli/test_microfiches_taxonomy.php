<?php
/**
 * Tests unitaires de MicrofichesTaxonomy (mapping engine/frame → partie + code TODO).
 *
 * Usage : php modules/megaservice_microfiches/tests/cli/test_microfiches_taxonomy.php
 */

require_once __DIR__ . '/../../classes/importers/MicrofichesTaxonomy.php';

$tests = [
    // ---- partieFromVueEclateeType : mapping basique ---------------------
    ['partie', 'engine',  'moteur'],
    ['partie', 'frame',   'cycle'],

    // Insensible à la casse + trim
    ['partie', 'ENGINE',  'moteur'],
    ['partie', 'Frame',   'cycle'],
    ['partie', ' engine ', 'moteur'],

    // Types inconnus → null (déclenchera un skip dans l'importer)
    ['partie', 'electrical', null],
    ['partie', '',           null],
    ['partie', 'engin',      null], // typo source CSV

    // ---- autoCreatedCode : format TODO_<partie>_<num> -------------------
    ['code', ['moteur', 30],  'TODO_moteur_30'],
    ['code', ['cycle',  1],   'TODO_cycle_1'],
    ['code', ['moteur', 999], 'TODO_moteur_999'],

    // ---- isAutoCreatedCode : détecte les placeholders -------------------
    ['isAuto', 'TODO_moteur_30',     true],
    ['isAuto', 'TODO_cycle_1',       true],
    ['isAuto', 'MOTEUR_BAS',         false], // catégorie nommée manuellement
    ['isAuto', '',                   false],
    ['isAuto', 'todo_moteur_30',     false], // case sensitive (intentionnel)
];

$pass = 0;
$fail = 0;
$fails = [];

foreach ($tests as $i => [$method, $input, $expected]) {
    if ($method === 'partie') {
        $actual = MicrofichesTaxonomy::partieFromVueEclateeType($input);
    } elseif ($method === 'code') {
        [$partie, $num] = $input;
        $actual = MicrofichesTaxonomy::autoCreatedCode($partie, $num);
    } elseif ($method === 'isAuto') {
        $actual = MicrofichesTaxonomy::isAutoCreatedCode($input);
    } else {
        throw new RuntimeException("Méthode inconnue: $method");
    }

    if ($actual === $expected) {
        $pass++;
    } else {
        $fail++;
        $fails[] = sprintf(
            "  [#%d] %s(%s) → attendu=%s, obtenu=%s",
            $i, $method, json_encode($input),
            var_export($expected, true), var_export($actual, true)
        );
    }
}

echo "Tests : $pass passés, $fail échoués sur " . count($tests) . PHP_EOL;
if ($fail > 0) {
    echo PHP_EOL . implode("\n", $fails) . PHP_EOL;
    exit(1);
}
echo "OK." . PHP_EOL;
exit(0);
