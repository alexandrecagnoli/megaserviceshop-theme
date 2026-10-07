<?php
/**
 * Donne aux sous-catégories de Powerparts les facettes de Powerparts.
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
 * Règle appliquée (actée avec Alex le 08/10) : toute sous-catégorie de
 * Powerparts reçoit les filtres de Powerparts, SAUF le filtre `category`
 * (« sous-catégories ») — une feuille n'a pas d'enfant à proposer. Soit, pour la
 * configuration actuelle de la racine : Disponibilité, Sélections, Marque, Prix.
 *
 * Volontairement limité à Powerparts : les autres branches ont leurs propres
 * modèles de filtres et leurs propres besoins, leur couverture se décide à part.
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

/**
 * Catégorie dont la configuration sert de modèle à toutes les autres.
 * « Accessoires powerparts » : Disponibilité, Sélections, Marque, Prix.
 */
const SOURCE_CATEGORY = 41;

/**
 * Profondeur traitée, au sens PrestaShop : Accueil 1, une grande famille 2
 * (Powerparts), une sous-catégorie 3 (Guidon & commandes), une sous-sous-
 * catégorie 4 (Guidon). C'est ce dernier niveau qui est massivement dépourvu de
 * facettes — 10 catégories configurées sur 77 — et c'est là que le client
 * arrive en naviguant.
 */
const TARGET_DEPTH = 4;

/**
 * Le filtre « sous-catégories » n'est pas hérité : une feuille n'a pas d'enfant
 * à proposer, et la racine l'affiche déjà pour descendre d'un cran.
 */
const EXCLUDED_TYPE = 'category';

$db     = Db::getInstance();
$catTbl = _DB_PREFIX_ . 'category';
$lcTbl  = _DB_PREFIX_ . 'layered_category';

// ── Configuration de la racine : c'est elle qu'on duplique
$source = $db->executeS(
    'SELECT `id_shop`, `controller`, `id_value`, `type`, `filter_type`, `filter_show_limit`
       FROM `' . $lcTbl . '`
      WHERE `id_category` = ' . (int) SOURCE_CATEGORY . '
        AND `type` <> "' . pSQL(EXCLUDED_TYPE) . '"
      ORDER BY `position`'
) ?: [];

if (!$source) {
    fwrite(STDERR, sprintf(
        "La catégorie modèle %d n'a aucune facette configurée : rien à propager.\n",
        SOURCE_CATEGORY
    ));
    exit(1);
}

// Sous-sous-catégories actives sans configuration propre, toutes branches
// confondues. Le LEFT JOIN est ce qui rend le script rejouable : une catégorie
// déjà servie n'est jamais retouchée.
$targets = $db->executeS(
    'SELECT c.`id_category`, c.`id_parent`, cl.`name`,
            (SELECT pl.`name` FROM `' . _DB_PREFIX_ . 'category_lang` pl
              WHERE pl.`id_category` = c.`id_parent` AND pl.`id_lang` = 1) AS parent_name
       FROM `' . $catTbl . '` c
       JOIN `' . _DB_PREFIX_ . 'category_lang` cl
         ON cl.`id_category` = c.`id_category` AND cl.`id_lang` = 1
       LEFT JOIN (SELECT DISTINCT `id_category` FROM `' . $lcTbl . '`) lc
         ON lc.`id_category` = c.`id_category`
      WHERE c.`active` = 1
        AND c.`level_depth` = ' . (int) TARGET_DEPTH . '
        AND lc.`id_category` IS NULL
      ORDER BY parent_name, cl.`name`'
) ?: [];

printf("Mode : %s\n", $apply ? 'APPLICATION' : 'simulation (aucune écriture)');
printf("Profondeur traitée : %d (sous-sous-catégories)\n", TARGET_DEPTH);
printf("Configuration modèle : catégorie %d\n", SOURCE_CATEGORY);
printf(
    "Filtres hérités : %s\n\n",
    implode(', ', array_map(function ($r) { return $r['type']; }, $source))
);

if (!$targets) {
    echo "Toutes les sous-sous-catégories sont déjà configurées.\n";
    exit(0);
}

// Regroupées par parent : c'est ce qui permet de relire la liste et de repérer
// une branche qui n'aurait rien à faire là.
$byParent = [];
foreach ($targets as $t) {
    $byParent[(string) $t['parent_name']][] = $t;
}

printf("Sous-sous-catégories à compléter : %d, dans %d branches\n\n", count($targets), count($byParent));
ksort($byParent);
foreach ($byParent as $parent => $list) {
    printf("  %s (%d)\n", $parent, count($list));
    printf("      %s\n", implode(', ', array_map(function ($t) { return $t['name']; }, $list)));
}

$inserted = 0;
if ($apply) {
    foreach ($targets as $t) {
        $position = 1;
        foreach ($source as $r) {
            $row = [
                'id_shop'           => (int) $r['id_shop'],
                'controller'        => pSQL($r['controller']),
                'id_category'       => (int) $t['id_category'],
                'id_value'          => $r['id_value'] === null ? null : (int) $r['id_value'],
                'type'              => pSQL($r['type']),
                'position'          => $position++,
                'filter_type'       => (int) $r['filter_type'],
                'filter_show_limit' => (int) $r['filter_show_limit'],
            ];
            if ($db->insert('layered_category', $row, false, true, Db::INSERT_IGNORE)) {
                ++$inserted;
            }
        }
    }
}

printf(
    "\nLignes de filtres %s : %d\n",
    $apply ? 'insérées' : 'à insérer',
    $apply ? $inserted : count($targets) * count($source)
);

if (!$apply) {
    echo "\nRelancer avec --apply pour écrire.\n";
}
