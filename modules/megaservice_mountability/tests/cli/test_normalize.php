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

echo "\n1. Ligne réelle (fichier montabilite_ktm : reference;id_moto;marque;libelle_moto)\n";
$r = MsMountabilityImporter::normalizeRow(['150606800', 'F8401W5', 'KTM', '450 SX-F 2023']);
check('reference',            $r['reference'], '150606800');
check('serial constructeur',  $r['id_moto_constructeur'], 'F8401W5');
check('marque en MAJ',        $r['marque'], 'KTM');
$r = MsMountabilityImporter::normalizeRow(['10000042', '6100H1', 'KTM', '125 SXS 2008']);
check('serial ancien (sans F)', $r['id_moto_constructeur'], '6100H1');

echo "\n2. Espaces superflus\n";
$r = MsMountabilityImporter::normalizeRow(['  ABC123 ', '  M1  ', ' hqv ']);
check('reference trimée', $r['reference'], 'ABC123');
check('moto trimée',      $r['id_moto_constructeur'], 'M1');
check('marque trimée+MAJ', $r['marque'], 'HQV');

echo "\n3. Marque du formulaire si absente de la ligne\n";
$r = MsMountabilityImporter::normalizeRow(['REF', 'MOTO', ''], 'gg');
// Était 'GG' : la marque est désormais ramenée au référentiel ms_moto.
check('fallback marque normalisé', $r['marque'], 'GASGAS');

echo "\n4. La marque de la LIGNE prime sur le formulaire\n";
$r = MsMountabilityImporter::normalizeRow(['REF', 'MOTO', 'KTM'], 'GG');
check('ligne prioritaire', $r['marque'], 'KTM');

echo "\n5. Rejets\n";
check('reference vide',        MsMountabilityImporter::normalizeRow(['', 'M', 'KTM']), null);
check('id moto vide',          MsMountabilityImporter::normalizeRow(['R', '', 'KTM']), null);
check('marque absente partout', MsMountabilityImporter::normalizeRow(['R', 'M']), null);
check('moins de 2 colonnes',   MsMountabilityImporter::normalizeRow(['R']), null);
check('pas un tableau',        MsMountabilityImporter::normalizeRow('R;M;KTM'), null);

echo "\n6. Normalisation de la marque sur le référentiel ms_moto\n";
$r = MsMountabilityImporter::normalizeRow(['R', 'M', 'HUSQVARNA']);
check('HUSQVARNA → HQV',  $r['marque'], 'HQV');
$r = MsMountabilityImporter::normalizeRow(['R', 'M', 'Gas Gas']);
check('Gas Gas → GASGAS', $r['marque'], 'GASGAS');
$r = MsMountabilityImporter::normalizeRow(['R', 'M', 'ktm']);
check('ktm → KTM',        $r['marque'], 'KTM');
check('normalizeMarque inconnue', MsMountabilityImporter::normalizeMarque('SUSPENSIONS WP'), '');

echo "\n7. Fichier au mauvais format : la 3e colonne est une catégorie\n";
// ~24 900 lignes étaient entrées en prod sous des « marques » comme
// « PIÈCES DÉTACHÉES » ou « ACCESSOIRES POWE » (tronqué par VARCHAR(16)).
check('PIÈCES DÉTACHÉES rejeté',  MsMountabilityImporter::normalizeRow(['R', 'M', 'PIÈCES DÉTACHÉES']), null);
check('SUSPENSIONS WP rejeté',    MsMountabilityImporter::normalizeRow(['R', 'M', 'SUSPENSIONS WP']), null);
check('MAIN CATEGORY rejeté',     MsMountabilityImporter::normalizeRow(['R', 'M', 'MAIN CATEGORY']), null);

echo "\n8. En-tête non standard (régression : 1 ligne 'MARQUE' entrée en prod)\n";
// Un fichier dont les 2 premières colonnes portent des libellés inattendus
// passait les deux contrôles et son en-tête entrait en base comme donnée.
// La 3e colonne 'MARQUE' est le discriminant : aucune marque réelle ne l'est.
check('en-tête ref;modele;MARQUE', MsMountabilityImporter::normalizeRow(['REF', 'MODELE', 'MARQUE']), null);
check('en-tête en minuscules',     MsMountabilityImporter::normalizeRow(['ref', 'modele', 'marque']), null);
// Contre-épreuve : ces valeurs restent des données valides tant que la marque
// est une vraie marque — on n'a pas élargi le rejet aux libellés génériques.
$r = MsMountabilityImporter::normalizeRow(['REF', 'MOTO', 'KTM'], 'GG');
check('REF/MOTO reste une donnée', $r['id_moto_constructeur'], 'MOTO');

printf("\n%s  %d/%d assertions OK\n\n", $fail === 0 ? '✅ SUCCÈS' : '❌ ÉCHEC', $total - $fail, $total);
exit($fail === 0 ? 0 : 1);
