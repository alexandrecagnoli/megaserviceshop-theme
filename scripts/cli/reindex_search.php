<?php
/**
 * Reconstruit l'index de recherche native PrestaShop (ps_search_index /
 * ps_search_word).
 *
 * Constaté le 30/09 : seuls 38 produits sur 47 918 actifs y figurent. L'import
 * catalogue écrit directement en SQL (comme pour les taxes et les relations en
 * attente) et ne déclenche jamais Product::save(), le seul point qui réindexe
 * automatiquement un produit — d'où un index quasiment vide malgré un réglage
 * correct (PS_SEARCH_WEIGHT_REF=10, le plus haut poids : une référence exacte
 * remonte en tête des résultats une fois indexée).
 *
 * Search::indexation(true) traite TOUT le catalogue non indexé en une seule
 * fois (boucle interne par lots de 50, cf. classes/Search.php) — pas de boucle
 * à gérer ici. Pour ~48 000 produits, prévoir plusieurs minutes : le CLI PHP
 * n'a pas la limite de temps d'une requête web, mais la commande SSH doit
 * avoir un timeout large ou tourner en arrière-plan.
 *
 * Usage (en SSH) :
 *   /opt/plesk/php/8.2/bin/php scripts/cli/reindex_search.php
 *
 * À relancer après chaque import catalogue, tant que l'import ne marque pas
 * les produits comme non indexés lui-même (cf. TECH_DEBT.md).
 */

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
$prefix = _DB_PREFIX_;

$before = (int) $db->getValue("SELECT COUNT(DISTINCT id_product) FROM {$prefix}search_index");
$active = (int) $db->getValue("SELECT COUNT(*) FROM {$prefix}product WHERE active = 1");
echo "Avant : {$before} produits indexés sur {$active} actifs.\n";

$start = microtime(true);
Search::indexation(true);
$elapsed = round(microtime(true) - $start, 1);

$after = (int) $db->getValue("SELECT COUNT(DISTINCT id_product) FROM {$prefix}search_index");
echo "Après : {$after} produits indexés (en {$elapsed}s).\n";

if ($after < $active) {
    echo "Reste non indexé : " . ($active - $after) . " — normal si certains sont hors visibilité recherche (visibility <> both/search).\n";
}
