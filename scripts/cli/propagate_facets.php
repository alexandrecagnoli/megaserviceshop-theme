<?php
/**
 * Propage la configuration des facettes aux sous-catégories qui n'en ont pas.
 * ==============================================
 *
 * Ticket : « le menu facettes est absent sur /74-guidon ».
 *
 * ps_facetedsearch ne lit QUE ps_layered_category, catégorie par catégorie. Une
 * catégorie absente de cette table n'a aucune facette — ce n'est pas un défaut
 * du module, c'est une configuration qui n'a jamais été faite. Les huit modèles
 * de filtres créés en back-office couvrent 63 catégories sur 150, et le trou
 * suit la profondeur : 41 des 53 catégories de niveau 3, mais seulement 10 des
 * 77 de niveau 4 — celles où le client arrive réellement en naviguant.
 *
 * Règle appliquée (actée avec Alex le 08/10) : une sous-catégorie hérite des
 * filtres de son ancêtre configuré le plus proche, SAUF le filtre `category`
 * (« sous-catégories »). Une feuille n'a pas d'enfant à proposer, et c'est
 * précisément ce que montrent les pages qui fonctionnent : Disponibilité,
 * Sélections, Marque, Prix.
 *
 * ATTENTION : enregistrer un modèle de filtres en back-office RECONSTRUIT
 * ps_layered_category pour ses catégories et effacera donc ces lignes. Le script
 * est idempotent et rejouable — à relancer après une modification des modèles,
 * ou après un import qui crée des catégories.
 *
 * Usage (en SSH) :
 *   php scripts/cli/propagate_facets.php            # simulation, n'écrit rien
 *   php scripts/cli/propagate_facets.php --apply    # applique
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script s'exécute en ligne de commande uniquement.\n");
    exit(1);
}

$apply = in_array('--apply', $argv, true);

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

$db      = Db::getInstance();
$catTbl  = _DB_PREFIX_ . 'category';
$lcTbl   = _DB_PREFIX_ . 'layered_category';

// Le filtre « sous-catégories » n'est pas hérité : une feuille n'a pas d'enfant
// à proposer, et le parent l'affiche déjà pour descendre d'un cran.
const EXCLUDED_TYPE = 'category';

// ── Catégories actives, hors racine et « Accueil »
$categories = $db->executeS(
    'SELECT `id_category`, `id_parent`, `level_depth`
       FROM `' . $catTbl . '`
      WHERE `active` = 1 AND `id_category` NOT IN (1, 2)
      ORDER BY `level_depth`, `id_category`'
) ?: [];

// ── Configuration existante, groupée par catégorie
$rows = $db->executeS(
    'SELECT `id_shop`, `controller`, `id_category`, `id_value`, `type`,
            `position`, `filter_type`, `filter_show_limit`
       FROM `' . $lcTbl . '`
      WHERE `id_category` > 0
      ORDER BY `id_category`, `position`'
) ?: [];

$configured = [];
foreach ($rows as $r) {
    $configured[(int) $r['id_category']][] = $r;
}

$parentOf = [];
foreach ($categories as $c) {
    $parentOf[(int) $c['id_category']] = (int) $c['id_parent'];
}

/** Ancêtre configuré le plus proche, ou 0. */
$nearestConfiguredAncestor = function ($idCategory) use ($parentOf, $configured) {
    $seen = [];
    $id   = isset($parentOf[$idCategory]) ? $parentOf[$idCategory] : 0;

    // Garde-fou : un arbre corrompu a déjà bloqué les facettes une fois
    // (cf. data/sql/2026-09-23_reparation_arbre_categories.sql).
    while ($id > 2 && !isset($seen[$id])) {
        if (!empty($configured[$id])) {
            return $id;
        }
        $seen[$id] = true;
        $id = isset($parentOf[$id]) ? $parentOf[$id] : 0;
    }

    return 0;
};

printf("Mode : %s\n", $apply ? 'APPLICATION' : 'simulation (aucune écriture)');
printf("Catégories actives : %d — déjà configurées : %d\n\n", count($categories), count($configured));

$planned   = 0;
$inserted  = 0;
$orphans   = [];
$byAncestor = [];

foreach ($categories as $c) {
    $id = (int) $c['id_category'];

    if (!empty($configured[$id])) {
        continue;
    }

    $source = $nearestConfiguredAncestor($id);
    if (!$source) {
        $orphans[] = $id;
        continue;
    }

    // Filtres hérités, sans le filtre « sous-catégories », positions renumérotées.
    $toInsert = [];
    $position = 1;
    foreach ($configured[$source] as $r) {
        if ($r['type'] === EXCLUDED_TYPE) {
            continue;
        }
        $toInsert[] = [
            'id_shop'           => (int) $r['id_shop'],
            'controller'        => pSQL($r['controller']),
            'id_category'       => $id,
            'id_value'          => $r['id_value'] === null ? null : (int) $r['id_value'],
            'type'              => pSQL($r['type']),
            'position'          => $position++,
            'filter_type'       => (int) $r['filter_type'],
            'filter_show_limit' => (int) $r['filter_show_limit'],
        ];
    }

    if (!$toInsert) {
        continue;
    }

    $planned += count($toInsert);
    $byAncestor[$source] = isset($byAncestor[$source]) ? $byAncestor[$source] + 1 : 1;

    if ($apply) {
        foreach ($toInsert as $row) {
            if ($db->insert('layered_category', $row, false, true, Db::INSERT_IGNORE)) {
                ++$inserted;
            }
        }
        // La catégorie compte désormais comme configurée : ses propres enfants
        // hériteront d'elle, et non d'un ancêtre plus lointain.
        $configured[$id] = $toInsert;
    }
}

printf("Catégories à compléter : %d\n", array_sum($byAncestor));
printf("Lignes de filtres %s : %d\n", $apply ? 'insérées' : 'à insérer', $apply ? $inserted : $planned);

if ($byAncestor) {
    echo "\nHéritage par ancêtre :\n";
    arsort($byAncestor);
    foreach (array_slice($byAncestor, 0, 15, true) as $src => $n) {
        $name = $db->getValue(
            'SELECT `name` FROM `' . _DB_PREFIX_ . 'category_lang`
              WHERE `id_category` = ' . (int) $src . ' AND `id_lang` = 1'
        );
        printf("  %-40s (id %d) → %d catégorie(s)\n", $name, $src, $n);
    }
}

if ($orphans) {
    printf("\n%d catégorie(s) sans aucun ancêtre configuré — laissées en l'état :\n", count($orphans));
    echo '  ' . implode(', ', array_slice($orphans, 0, 30)) . (count($orphans) > 30 ? ' …' : '') . "\n";
    echo "  (il faut leur créer un modèle de filtres en back-office)\n";
}

if (!$apply) {
    echo "\nRelancer avec --apply pour écrire.\n";
}
