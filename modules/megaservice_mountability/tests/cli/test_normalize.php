<?php
/**
 * Tests de normalisation d'une ligne de montabilité (méthode pure, hors PS).
 *   php modules/megaservice_mountability/tests/cli/test_normalize.php
 */

// La classe est un fichier de module (garde _PS_VERSION_). normalizeRow est
// pure — on satisfait juste le garde pour pouvoir la charger en CLI.
if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '8.2.0');
}
require_once __DIR__ . '/../../classes/MountabilityImporter.php';

$fail = 0; $total = 0;
function check($label, $actual, $expected)
{
    global $fail, $total; ++$total;
    if ($actual === $expected) { printf("  ✓ %s\n", $label); return; }
    ++$fail;
    printf("  ✗ %s\n      attendu : %s\n      obtenu  : %s\n", $label, var_export($expected, true), var_export($actual, true));
}

echo "\n1. Ligne valide (3 colonnes)\n";
$r = MsMountabilityImporter::normalizeRow(['3PW240069700', 'KTM_990_RC_R', 'ktm']);
check('reference',   $r['reference'], '3PW240069700');
check('id moto',     $r['id_moto_constructeur'], 'KTM_990_RC_R');
check('marque en MAJ', $r['marque'], 'KTM');

echo "\n2. Espaces superflus\n";
$r = MsMountabilityImporter::normalizeRow(['  ABC123 ', '  M1  ', ' hqv ']);
check('reference trimée', $r['reference'], 'ABC123');
check('moto trimée',      $r['id_moto_constructeur'], 'M1');
check('marque trimée+MAJ', $r['marque'], 'HQV');

echo "\n3. Marque du formulaire si absente de la ligne\n";
$r = MsMountabilityImporter::normalizeRow(['REF', 'MOTO', ''], 'gg');
check('fallback marque', $r['marque'], 'GG');

echo "\n4. La marque de la LIGNE prime sur le formulaire\n";
$r = MsMountabilityImporter::normalizeRow(['REF', 'MOTO', 'KTM'], 'GG');
check('ligne prioritaire', $r['marque'], 'KTM');

echo "\n5. Rejets\n";
check('reference vide',        MsMountabilityImporter::normalizeRow(['', 'M', 'KTM']), null);
check('id moto vide',          MsMountabilityImporter::normalizeRow(['R', '', 'KTM']), null);
check('marque absente partout', MsMountabilityImporter::normalizeRow(['R', 'M']), null);
check('moins de 2 colonnes',   MsMountabilityImporter::normalizeRow(['R']), null);
check('pas un tableau',        MsMountabilityImporter::normalizeRow('R;M;KTM'), null);

printf("\n%s  %d/%d assertions OK\n\n", $fail === 0 ? '✅ SUCCÈS' : '❌ ÉCHEC', $total - $fail, $total);
exit($fail === 0 ? 0 : 1);
