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
 * `--replace` : après l'import, DÉSACTIVE les motos de la marque absentes du
 * fichier. Nécessaire depuis que l'export est passé au marché européen seul —
 * sans lui, les modèles des autres marchés restent actifs et proposés au client.
 * On désactive sans supprimer : microfiches et montabilité pointent sur
 * `id_moto`.
 *
 * ⚠️  **À N'UTILISER QUE SUR UN EXPORT COMPLET.** Le fichier devient la liste de
 * référence de la marque : tout ce qui n'y figure pas est retiré du front. Un
 * fichier INCRÉMENTAL — par exemple « les motos 2028 » livrées seules l'an
 * prochain — doit s'importer SANS ce drapeau, sinon il désactiverait tout le
 * reste du catalogue.
 *
 * Garde-fou : au-delà de la moitié du parc de la marque, le script refuse et
 * demande `--force`. Il rattrape donc le cas ci-dessus — un fichier 2028 seul
 * serait rejeté — mais pas un fichier partiel à 60 % : le drapeau reste une
 * décision humaine, prise à chaque import.
 *
 * Usage (en SSH) :
 *   /opt/plesk/php/8.2/bin/php scripts/cli/import_motos.php data/imports/KTM_MOTORCYCLES.csv
 *   /opt/plesk/php/8.2/bin/php scripts/cli/import_motos.php data/imports/KTM_MOTORCYCLES.csv --replace
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script s'exécute en ligne de commande uniquement.\n");
    exit(1);
}

$args    = array_slice($argv, 1);
$replace = in_array('--replace', $args, true);
$force   = in_array('--force', $args, true);
$args    = array_values(array_filter($args, function ($a) {
    return strpos($a, '--') !== 0;
}));

$file   = $args[0] ?? null;
$marque = $args[1] ?? null;

if (!$file) {
    fwrite(STDERR, "Usage : php import_motos.php <fichier.csv> [KTM|HQV|GASGAS] [--replace] [--force]\n");
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
printf("Serials liés  : %d\n", $a['serials']);
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

// ── Mode remplacement ───────────────────────────────────────────────────────
//
// L'import seul est un upsert : il ne retire jamais rien. Quand l'export est
// passé au marché européen seulement, les ~450 modèles des autres marchés sont
// restés en base, actifs, et toujours proposés dans les sélecteurs.
//
// On DÉSACTIVE, on ne supprime pas : les microfiches et la montabilité pointent
// sur `id_moto`, et une suppression casserait ces liens. Une moto désactivée
// disparaît du front tout en gardant son identifiant.
if ($replace) {
    $seen = array_keys($report->modelnumbers);
    if (!$seen) {
        fwrite(STDERR, "\nAucun MODELNUMBER retenu : remplacement annulé.\n");
        exit(1);
    }

    $in = implode(',', array_map(function ($m) { return '"' . pSQL($m) . '"'; }, $seen));

    $obsolete = (int) Db::getInstance()->getValue(
        'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'ms_moto`
          WHERE `marque` = "' . pSQL($a['marque']) . '" AND `active` = 1
            AND `modelnumber` NOT IN (' . $in . ')'
    );

    $actives = (int) Db::getInstance()->getValue(
        'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'ms_moto`
          WHERE `marque` = "' . pSQL($a['marque']) . '" AND `active` = 1'
    );

    printf("\nRemplacement — %s\n", $a['marque']);
    printf("  actives en base : %d\n", $actives);
    printf("  absentes du fichier : %d\n", $obsolete);

    // Garde-fou : un fichier tronqué ou mal généré désactiverait le catalogue
    // entier sans que rien ne le signale. Au-delà de la moitié, on exige --force.
    if ($actives > 0 && $obsolete > $actives / 2 && !$force) {
        fwrite(STDERR, sprintf(
            "\nREFUS : %d désactivations sur %d motos actives (plus de la moitié).\n"
            . "Le fichier est-il complet ? Relancer avec --force si c'est voulu.\n",
            $obsolete, $actives
        ));
        exit(1);
    }

    if ($obsolete > 0) {
        Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'ms_moto`
                SET `active` = 0, `date_upd` = NOW()
              WHERE `marque` = "' . pSQL($a['marque']) . '" AND `active` = 1
                AND `modelnumber` NOT IN (' . $in . ')'
        );
        printf("  désactivées : %d\n", $obsolete);
    } else {
        echo "  rien à désactiver\n";
    }
}

exit(0);
