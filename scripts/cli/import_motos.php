<?php
/**
 * Import d'un CSV motos constructeur (KTM / HQV / GASGAS) → ps_ms_moto.
 * ==============================================
 *
 * Même constat que pour les liaisons : l'import n'existait que derrière le
 * formulaire d'upload du back-office. Or les CSV constructeur vivent déjà sur
 * le serveur dans data/imports/, pèsent plusieurs dizaines de Mo, et doivent
 * pouvoir être rejoués après correction d'une donnée source — ce qui est
 * précisément le cas du ticket REC-50.
 *
 * L'import est idempotent par construction : UNIQUE(modelnumber) +
 * INSERT ... ON DUPLICATE KEY UPDATE, donc les id_moto restent stables et les
 * microfiches qui pointent dessus ne cassent pas. `active`, `picture_cycle`,
 * `picture_moteur` et `date_add` ne sont volontairement pas réécrits.
 *
 * La marque est déduite du nom de fichier (KTM_*, HQV_*, GASGAS_*) sauf si
 * elle est passée en second argument.
 *
 * Usage (en SSH) :
 *   /opt/plesk/php/8.2/bin/php scripts/cli/import_motos.php data/imports/KTM_MOTORCYCLES.csv
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script s'exécute en ligne de commande uniquement.\n");
    exit(1);
}

$file   = $argv[1] ?? null;
$marque = $argv[2] ?? null;

if (!$file) {
    fwrite(STDERR, "Usage : php import_motos.php <fichier.csv> [KTM|HQV|GASGAS]\n");
    exit(1);
}
if (!is_file($file) || !is_readable($file)) {
    fwrite(STDERR, "Fichier introuvable ou illisible : $file\n");
    exit(1);
}

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

$base = _PS_MODULE_DIR_ . 'megaservice_microfiches/classes/';
require_once $base . 'MsMoto.php';
require_once $base . 'importers/CsvReader.php';
require_once $base . 'importers/MotosTaxonomy.php';
require_once $base . 'importers/MotosImportReport.php';
require_once $base . 'importers/MotosImporter.php';

$before = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'ms_moto`');

echo "Fichier : $file\n";
echo "Motos en base avant : $before\n\n";

$importer = new MotosImporter();
$report   = $importer->importFile($file, $marque);
$a        = $report->toArray();

$after = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'ms_moto`');

printf("Marque        : %s\n", $a['marque']);
printf("Lues          : %d\n", $a['read']);
printf("Insérées      : %d\n", $a['inserted']);
printf("Mises à jour  : %d\n", $a['updated']);
printf("Dédupliquées  : %d\n", $a['deduped']);
printf("Skippées      : %d\n", $a['skipped']);
printf("Classées Autres : %d\n", $a['autres']);
printf("Durée         : %d ms\n", $a['duration_ms']);
printf("Motos en base après : %d (%+d)\n", $after, $after - $before);

if (count($report->yearMismatches) > 0) {
    printf("\nAnnée contredite par le nom du modèle : %d\n", count($report->yearMismatches));
    echo "(importées telles quelles — à corriger à la source)\n";
    foreach (array_slice($report->yearMismatches, 0, 20) as $ym) {
        printf("  %-28s colonne année : %d, nom du modèle : %d\n",
            $ym['modelnumber'], $ym['annee'], $ym['declared']);
    }
    if (count($report->yearMismatches) > 20) {
        printf("  … et %d autres\n", count($report->yearMismatches) - 20);
    }
}

if (count($report->errors) > 0) {
    printf("\n%d erreur(s) SQL — 10 premières :\n", count($report->errors));
    foreach (array_slice($report->errors, 0, 10) as $e) {
        printf("  %s : %s\n", $e['modelnumber'], $e['error']);
    }
    exit(1);
}

exit(0);
