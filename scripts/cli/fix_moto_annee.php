<?php
/**
 * Corrige l'année des motos dont le millésime en base contredit leur modelnumber.
 * ==============================================
 *
 * Ticket de recette #50 : sur la ligne d'échappement Akrapovič « Evolution Line »
 * (réf. A62405999000), la montabilité n'affichait pas la série 2024-2025-2026
 * attendue. Cause : la moto `$M-20251390SUPERDUKER` (serial F9903Y2, « 2025 KTM
 * 1390 SUPER DUKE R ») portait `annee = 2017`. Le millésime intermédiaire sortait
 * donc de la série et se rangeait en 2017.
 *
 * Six lignes de ps_ms_moto ont une année incohérente avec le millésime cité dans
 * leur nom. Le modelnumber tranche : pour cinq d'entre elles c'est le NOM qui est
 * faux (ex. `$M-950SUPERDUKER2014` affiché « 950 SUPER DUKE 2004 ») — autre
 * problème, cosmétique, hors de ce correctif. Une seule a bien l'`annee` fausse,
 * et c'est celle-ci. D'où une liste explicite plutôt qu'une règle automatique :
 * corriger `annee` d'après le nom aurait cassé les cinq autres.
 *
 * ATTENTION : ps_ms_moto est alimentée par un import. Ce correctif sera écrasé au
 * prochain import moto si la source n'est pas corrigée. À rejouer après import,
 * ou mieux : corriger la donnée en amont. Idempotent — ne touche une ligne que si
 * elle porte encore la valeur fautive.
 *
 * Usage (en SSH) :
 *   /opt/plesk/php/8.2/bin/php scripts/cli/fix_moto_annee.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script s'exécute en ligne de commande uniquement.\n");
    exit(1);
}

// [serial_constructeur, année attendue, année fautive attendue en base]
$corrections = [
    ['F9903Y2', 2025, 2017],
];

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

$db = Db::getInstance();
$table = _DB_PREFIX_ . 'ms_moto';
$changed = 0;

foreach ($corrections as $correction) {
    list($serial, $expected, $wrong) = $correction;

    $row = $db->getRow(
        'SELECT `id_moto`, `modelnumber`, `annee`, `nom_fr`
         FROM `' . $table . '`
         WHERE `serial_constructeur` = "' . pSQL($serial) . '"'
    );

    if (!$row) {
        printf("%s : introuvable en base — ignoré\n", $serial);
        continue;
    }

    printf("%s (%s) — « %s »\n", $serial, $row['modelnumber'], $row['nom_fr']);

    if ((int) $row['annee'] === $expected) {
        printf("  déjà à %d — rien à faire\n", $expected);
        continue;
    }
    if ((int) $row['annee'] !== $wrong) {
        printf(
            "  année %d inattendue (ni %d ni %d) — NON corrigé, à revérifier à la main\n",
            (int) $row['annee'],
            $expected,
            $wrong
        );
        continue;
    }

    $ok = $db->execute(
        'UPDATE `' . $table . '`
         SET `annee` = ' . (int) $expected . ', `date_upd` = NOW()
         WHERE `id_moto` = ' . (int) $row['id_moto'] . '
           AND `annee` = ' . (int) $wrong
    );

    if ($ok) {
        printf("  %d → %d corrigé\n", (int) $row['annee'], $expected);
        ++$changed;
    } else {
        printf("  ÉCHEC de la mise à jour\n");
    }
}

printf("\n%d ligne(s) corrigée(s).\n", $changed);
