<?php
/**
 * Import CSV des liaisons produits (obligatoires / exclues / recommandées / rechange).
 * ==============================================
 *
 * Le module megaservice_relations n'exposait son importeur que par le
 * back-office : un formulaire d'upload, donc un fichier à passer à la main dans
 * un navigateur. Pour un fichier de 10 000 lignes généré par l'application
 * d'export, et pour pouvoir le rejouer après un import catalogue, il fallait un
 * point d'entrée en ligne de commande.
 *
 * Modes (voir MsRelationsCsvImporter::import) :
 *   replace_for_listed  (défaut) — pour chaque couple (source, type) présent dans
 *                       le fichier, vide puis réécrit. Les produits absents du
 *                       fichier ne sont pas touchés. Idempotent : rejouer le même
 *                       fichier donne le même résultat.
 *   append              — ajoute sans rien supprimer. Laisse en place d'anciennes
 *                       liaisons devenues fausses.
 *   full_replace        — vide TOUTE la table avant import, y compris ce qui
 *                       aurait été saisi à la main en back-office.
 *
 * Une liaison dont la référence cible n'existe pas encore au catalogue part en
 * table d'attente, et sera posée par resolve_pending_relations.php (ou par le
 * hook produit) dès que le produit apparaît.
 *
 * Usage (en SSH) :
 *   /opt/plesk/php/8.2/bin/php scripts/cli/import_relations.php fichier.csv
 *   /opt/plesk/php/8.2/bin/php scripts/cli/import_relations.php fichier.csv full_replace
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script s'exécute en ligne de commande uniquement.\n");
    exit(1);
}

$file = $argv[1] ?? null;
$mode = $argv[2] ?? 'replace_for_listed';

if (!$file) {
    fwrite(STDERR, "Usage : php import_relations.php <fichier.csv> [replace_for_listed|append|full_replace]\n");
    exit(1);
}
if (!is_file($file) || !is_readable($file)) {
    fwrite(STDERR, "Fichier introuvable ou illisible : $file\n");
    exit(1);
}
if (!in_array($mode, ['replace_for_listed', 'append', 'full_replace'], true)) {
    fwrite(STDERR, "Mode inconnu : $mode\n");
    exit(1);
}

// Remontée jusqu'à config/config.inc.php : le script peut vivre ailleurs que
// dans l'arborescence PrestaShop (scripts/ n'est pas déployé par la CI).
$dir = __DIR__;
$configPath = null;
for ($i = 0; $i < 15; ++$i) {
    $candidate = $dir . '/config/config.inc.php';
    if (is_file($candidate)) {
        $configPath = $candidate;
        break;
    }
    $parent = dirname($dir);
    if ($parent === $dir) {
        break;
    }
    $dir = $parent;
}
if (!$configPath) {
    fwrite(STDERR, "config/config.inc.php introuvable en remontant depuis " . __DIR__ . "\n");
    exit(1);
}

define('_PS_ADMIN_DIR_', dirname($configPath) . '/admin');
require_once $configPath;
require_once _PS_MODULE_DIR_ . 'megaservice_relations/classes/ProductRelationService.php';
require_once _PS_MODULE_DIR_ . 'megaservice_relations/classes/CsvImporter.php';

$before = (int) Db::getInstance()->getValue(
    'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'megaservice_product_relation`'
);

echo "Fichier : $file\n";
echo "Mode    : $mode\n";
echo "Liaisons en base avant : $before\n\n";

$importer = new MsRelationsCsvImporter();
$stats = $importer->import($file, $mode);

$after = (int) Db::getInstance()->getValue(
    'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'megaservice_product_relation`'
);

printf("Posées        : %d\n", $stats['imported']);
printf("Supprimées    : %d\n", $stats['deleted']);
printf("En attente    : %d (dont %d résolues ce coup-ci)\n", $stats['pending'], $stats['resolved']);
printf("Ignorées      : %d\n", $stats['skipped']);
printf("Liaisons en base après : %d (%+d)\n", $after, $after - $before);

$errors = $stats['errors'];
if (!empty($errors)) {
    printf("\n%d erreur(s) — 20 premières :\n", count($errors));
    foreach (array_slice($errors, 0, 20) as $e) {
        printf("  ligne %s : %s\n", $e['line'], $e['msg']);
    }
}

exit(empty($errors) ? 0 : 0); // les refs absentes du catalogue ne sont pas un échec
