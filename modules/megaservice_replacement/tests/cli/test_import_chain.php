<?php
/**
 * Parsing + résolution de chaînes, validés sur les VRAIS fichiers constructeur.
 *
 *   php modules/megaservice_replacement/tests/cli/test_import_chain.php
 *
 * Les fichiers `data/replacement_articles_*.csv` ne sont pas versionnés (données
 * constructeur) : le test se met en SKIP s'ils sont absents, il ne casse pas.
 *
 * Les valeurs attendues viennent de l'analyse exploratoire des 6 fichiers.
 * Si un nouveau fichier constructeur arrive, ces chiffres bougeront — c'est
 * VOULU : le test sert justement de détecteur d'écart de volumétrie.
 */

if (!class_exists('MsReplacement')) {
    class MsReplacement
    {
        const TYPE_REPLACE   = 'replace';
        const TYPE_SET       = 'set';
        const CHAIN_OK       = 'ok';
        const CHAIN_LOOP     = 'loop';
        const CHAIN_DEAD_END = 'dead_end';
    }
}

require_once __DIR__ . '/../../classes/CsvImporter.php';
require_once __DIR__ . '/../../classes/ChainResolver.php';

$failures = 0;
$total    = 0;

function check($label, $actual, $expected)
{
    global $failures, $total;
    ++$total;
    if ($actual === $expected) {
        printf("  ✓ %s\n", $label);
        return;
    }
    ++$failures;
    printf("  ✗ %s\n      attendu : %s\n      obtenu  : %s\n", $label, var_export($expected, true), var_export($actual, true));
}

function checkTrue($label, $cond)
{
    check($label, (bool) $cond, true);
}

$dataDir = __DIR__ . '/../../../../data';
$files   = glob($dataDir . '/replacement_articles_*.csv');

if (empty($files)) {
    echo "\n⏭️  SKIP — aucun fichier data/replacement_articles_*.csv (données constructeur non versionnées)\n\n";
    exit(0);
}

printf("\nFichiers constructeur trouvés : %d\n", count($files));

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== PARSING (contrôles §2.3) ===\n";
$parsed = MsReplacementCsvImporter::parseFiles($files);
$rows   = $parsed['rows'];
$rep    = $parsed['report'];

foreach ($rep as $k => $v) {
    if (is_array($v)) { $v = empty($v) ? '—' : json_encode($v); }
    printf("    %-28s %s\n", $k, $v);
}

check('les 6 fichiers sont lisibles', $rep['files'], count($files));
check('aucun en-tête invalide',       $rep['files_bad_header'], []);

// Invariant STRUCTUREL : rien ne se perd en route.
$accounted = $rep['accepted'] + $rep['duplicates']
    + $rep['rejected_empty'] + $rep['rejected_self_ref']
    + $rep['rejected_conversion_type'] + $rep['rejected_relation_mismatch']
    + $rep['rejected_quantity'];
check('conservation : accepté + doublons + rejets == lignes lues', $accounted, $rep['lines_read']);

// Qualité constatée sur les fichiers réels.
check('auto-références rejetées',        $rep['rejected_self_ref'], 12);
check('aucun ConversionType invalide',   $rep['rejected_conversion_type'], 0);
check('aucune incohérence RelationType', $rep['rejected_relation_mismatch'], 0);
check('aucune quantité invalide',        $rep['rejected_quantity'], 0);
check('aucune ligne vide',               $rep['rejected_empty'], 0);

checkTrue('des doublons ont bien été absorbés', $rep['duplicates'] > 0);

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== RÉSOLUTION DES CHAÎNES ===\n";
$resolved = MsReplacementChainResolver::resolve($rows);
check('la résolution conserve le nombre de lignes', count($resolved), count($rows));

$byStatus = [];
$maxDepth = 0;
$setStops = 0;
$nullFinalNotDeadEnd = 0;
foreach ($resolved as $r) {
    $byStatus[$r['chain_status']] = ($byStatus[$r['chain_status']] ?? 0) + 1;
    $maxDepth = max($maxDepth, $r['chain_depth']);
    if ($r['final_is_set']) { ++$setStops; }
    if ($r['ref_final'] === null && $r['chain_status'] !== MsReplacement::CHAIN_DEAD_END) {
        ++$nullFinalNotDeadEnd;
    }
}
ksort($byStatus);
foreach ($byStatus as $s => $n) { printf("    %-10s %d\n", $s, $n); }
printf("    %-10s %d\n", 'depth max', $maxDepth);
printf("    %-10s %d\n", 'arrêts set', $setStops);

// Invariant : seule une chaîne dead_end peut ne pas avoir de référence finale.
check('ref_final absente ⇒ statut dead_end', $nullFinalNotDeadEnd, 0);

// Statuts hors énumération = corruption silencieuse.
$unknown = array_diff(array_keys($byStatus), [MsReplacement::CHAIN_OK, MsReplacement::CHAIN_LOOP, MsReplacement::CHAIN_DEAD_END]);
check('aucun statut hors énumération', array_values($unknown), []);

// Les chaînes réelles sont courtes : le garde-fou à 10 est très large.
checkTrue('profondeur max ≤ MAX_DEPTH', $maxDepth <= MsReplacementChainResolver::MAX_DEPTH);
check('profondeur max constatée', $maxDepth, 3);

// Des cycles existent réellement : la détection n'est pas théorique.
checkTrue('des cycles sont détectés', ($byStatus[MsReplacement::CHAIN_LOOP] ?? 0) > 0);
checkTrue('des chaînes s\'arrêtent sur un set', $setStops > 0);

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=== SETS ===\n";
$setSizes = [];
foreach ($resolved as $r) {
    if ($r['conversion_type'] === MsReplacement::TYPE_SET) {
        $setSizes[$r['ref_replaced']] = ($setSizes[$r['ref_replaced']] ?? 0) + 1;
    }
}
arsort($setSizes);
$biggestRef  = key($setSizes);
$biggestSize = current($setSizes);
printf("    sets distincts : %d\n", count($setSizes));
printf("    plus gros set  : %s (%d composants)\n", $biggestRef, $biggestSize);

// ⚠️ La spec annonçait « jusqu'à 10 composants ». La réalité est bien au-delà :
// le front (cas B/C) et l'ajout panier multi-lignes doivent tenir cette taille.
checkTrue('le plus gros set dépasse largement les 10 composants annoncés', $biggestSize > 10);
check('plus gros set constaté', $biggestSize, 101);

printf("\n%s  %d/%d assertions OK\n\n", $failures === 0 ? '✅ SUCCÈS' : '❌ ÉCHEC', $total - $failures, $total);
exit($failures === 0 ? 0 : 1);
