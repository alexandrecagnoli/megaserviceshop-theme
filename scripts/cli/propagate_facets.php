<?php
/**
 * Donne des facettes aux catégories feuilles qui n'en ont pas.
 * ==============================================
 *
 * Ticket : « le menu facettes est absent sur /74-guidon ».
 *
 * ps_facetedsearch ne lit QUE ps_layered_category, catégorie par catégorie. Une
 * catégorie absente de cette table n'affiche aucune facette — ce n'est pas un
 * défaut du module, c'est une configuration qui n'a jamais été faite. Les modèles
 * de filtres du back-office couvrent 63 catégories sur 150, et le trou suit la
 * profondeur : 41 des 53 catégories de niveau 3, mais 10 des 77 de niveau 4,
 * celles où le client arrive réellement en naviguant.
 *
 * Règle appliquée (actée avec Alex le 08/10) : une catégorie SANS SOUS-CATÉGORIE
 * hérite de TOUTE la configuration de son ancêtre configuré le plus proche, SAUF
 * le filtre `category`. Deux raisons à chacune des deux clauses :
 *
 *   - sans sous-catégorie : une feuille n'a rien à proposer sous elle, le filtre
 *     `category` y serait vide. Une catégorie qui a des enfants, elle, le garde —
 *     c'est par lui qu'on descend.
 *   - toute la configuration de SON ancêtre, et non un jeu uniforme : « Vêtements
 *     casual » offre Taille, Couleur, Genre, Pointure, Composition ; Powerparts
 *     offre Disponibilité, Sélections, Marque, Prix. Appliquer le second aux
 *     t-shirts leur retirerait le filtre Taille, le seul qui y serve vraiment.
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

/** Filtre « sous-catégories » : jamais hérité par une feuille. */
const EXCLUDED_TYPE = 'category';

$db     = Db::getInstance();
$catTbl = _DB_PREFIX_ . 'category';
$lcTbl  = _DB_PREFIX_ . 'layered_category';

// ── Catégories actives, hors racine et « Accueil »
$categories = $db->executeS(
    'SELECT c.`id_category`, c.`id_parent`, c.`level_depth`, cl.`name`,
            (SELECT COUNT(*) FROM `' . $catTbl . '` k
              WHERE k.`id_parent` = c.`id_category` AND k.`active` = 1) AS enfants
       FROM `' . $catTbl . '` c
       JOIN `' . _DB_PREFIX_ . 'category_lang` cl
         ON cl.`id_category` = c.`id_category` AND cl.`id_lang` = 1
      WHERE c.`active` = 1 AND c.`id_category` NOT IN (1, 2)
      ORDER BY c.`level_depth`, cl.`name`'
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

$parentOf = $nameOf = [];
foreach ($categories as $c) {
    $parentOf[(int) $c['id_category']] = (int) $c['id_parent'];
    $nameOf[(int) $c['id_category']]   = (string) $c['name'];
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
printf("Règle : les catégories SANS sous-catégorie héritent de leur ancêtre, sans le filtre « %s »\n\n", EXCLUDED_TYPE);

$plan     = [];
$inserted = 0;
$orphans  = [];
$skipped  = 0;

foreach ($categories as $c) {
    $id = (int) $c['id_category'];

    if (!empty($configured[$id])) {
        continue;
    }
    if ((int) $c['enfants'] > 0) {
        // Elle a des sous-catégories : hors de la règle.
        ++$skipped;
        continue;
    }

    $source = $nearestConfiguredAncestor($id);
    if (!$source) {
        $orphans[] = $c['name'] . ' (' . $id . ')';
        continue;
    }

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

    $plan[$source][] = ['name' => $c['name'], 'id' => $id, 'nb' => count($toInsert)];

    if ($apply) {
        foreach ($toInsert as $row) {
            if ($db->insert('layered_category', $row, false, true, Db::INSERT_IGNORE)) {
                ++$inserted;
            }
        }
    }
}

$totalCats  = 0;
$totalLines = 0;
foreach ($plan as $src => $list) {
    $totalCats += count($list);
    foreach ($list as $l) {
        $totalLines += $l['nb'];
    }
}

printf("Feuilles à compléter : %d\n", $totalCats);
printf("Catégories à enfants laissées en l'état : %d\n\n", $skipped);

foreach ($plan as $src => $list) {
    $types = [];
    foreach ($configured[$src] as $r) {
        if ($r['type'] !== EXCLUDED_TYPE) {
            $types[] = $r['type'];
        }
    }
    printf(
        "  hérite de %s (%d) → %d feuille(s), %d filtres : %s\n",
        isset($nameOf[$src]) ? $nameOf[$src] : ('cat ' . $src),
        $src,
        count($list),
        count($types),
        implode(', ', array_unique($types))
    );
    printf("      %s\n", implode(', ', array_map(function ($l) { return $l['name']; }, $list)));
}

printf(
    "\nLignes de filtres %s : %d\n",
    $apply ? 'insérées' : 'à insérer',
    $apply ? $inserted : $totalLines
);

if ($orphans) {
    printf("\n%d feuille(s) sans aucun ancêtre configuré — laissées en l'état :\n", count($orphans));
    echo '  ' . implode(', ', $orphans) . "\n";
    echo "  (il leur faut un modèle de filtres créé en back-office)\n";
}

if (!$apply) {
    echo "\nRelancer avec --apply pour écrire.\n";
}
