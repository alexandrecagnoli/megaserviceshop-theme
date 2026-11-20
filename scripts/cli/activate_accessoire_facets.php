<?php
/**
 * Active (ou retire) les 3 caractéristiques « Accessoires moto » comme facettes
 * sur les catégories Powerparts qui ont déjà une colonne de filtres.
 * ==============================================
 *
 * Caractéristiques : Motorisation (60), Matière de la pièce (61), Type de pièce (62).
 * ps_facetedsearch ne propose une facette que si la catégorie la déclare dans
 * ps_layered_category. On ajoute donc une ligne type='id_feature' par couple
 * (catégorie, caractéristique), à la suite des filtres existants.
 *
 * Périmètre : les catégories actives du sous-arbre Powerparts (41) qui ont DÉJÀ
 * une configuration de facettes — la racine et ses familles. On ne crée pas de
 * config là où il n'y en a pas (les feuilles niveau 4, vidées par ailleurs).
 *
 * Idempotent : un couple déjà présent n'est jamais redoublé.
 * Réversible : --remove retire exactement ces lignes.
 *
 * ATTENTION : le filtre ne s'affiche que si des produits portent la valeur ; tant
 * que les Accessoires moto ne sont pas importés, la facette reste masquée.
 * Purge ps_layered_filter_block après coup (fait par ce script).
 *
 * Usage (préprod, SSH) :
 *   php scripts/cli/activate_accessoire_facets.php            # simulation
 *   php scripts/cli/activate_accessoire_facets.php --apply
 *   php scripts/cli/activate_accessoire_facets.php --remove --apply
 */

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(1); }

$apply  = in_array('--apply', $argv, true);
$remove = in_array('--remove', $argv, true);

$dir = __DIR__; $configPath = null;
for ($i = 0; $i < 15; ++$i) {
    $c = $dir . '/config/config.inc.php';
    if (is_file($c)) { $configPath = $c; break; }
    $p = dirname($dir); if ($p === $dir) break; $dir = $p;
}
if (!$configPath) { fwrite(STDERR, "config.inc.php introuvable.\n"); exit(1); }
define('_PS_ADMIN_DIR_', dirname($configPath) . '/admin');
require_once $configPath;

$db = Db::getInstance();

const POWERPARTS_ROOT = 41;
const FEATURES = [60, 61, 62]; // Motorisation, Matière de la pièce, Type de pièce
$idShop = (int) (Configuration::get('PS_SHOP_DEFAULT') ?: 1);

// Catégories cibles : sous-arbre Powerparts, actives, ayant déjà une config.
$root = $db->getRow('SELECT nleft, nright FROM `' . _DB_PREFIX_ . 'category` WHERE id_category = ' . POWERPARTS_ROOT);
$cats = $db->executeS(
    'SELECT c.id_category, cl.name
       FROM `' . _DB_PREFIX_ . 'category` c
       JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.id_category=c.id_category AND cl.id_lang=' . (int) Configuration::get('PS_LANG_DEFAULT') . '
      WHERE c.active=1 AND c.nleft >= ' . (int) $root['nleft'] . ' AND c.nright <= ' . (int) $root['nright'] . '
        AND EXISTS (SELECT 1 FROM `' . _DB_PREFIX_ . 'layered_category` lc WHERE lc.id_category=c.id_category)
      ORDER BY c.level_depth, cl.name'
) ?: [];

printf("Mode : %s%s\n", $apply ? 'APPLICATION' : 'simulation', $remove ? ' (RETRAIT)' : '');
printf("Caractéristiques : %s | Catégories cibles : %d\n\n", implode(',', FEATURES), count($cats));

$done = 0;
foreach ($cats as $c) {
    $id = (int) $c['id_category'];
    $touched = [];

    if ($remove) {
        foreach (FEATURES as $f) {
            if ($apply) {
                $db->execute(
                    'DELETE FROM `' . _DB_PREFIX_ . 'layered_category`
                      WHERE id_category=' . $id . ' AND type="id_feature" AND id_value=' . (int) $f
                );
            }
            $touched[] = $f;
        }
    } else {
        foreach (FEATURES as $f) {
            $exists = (bool) $db->getValue(
                'SELECT 1 FROM `' . _DB_PREFIX_ . 'layered_category`
                  WHERE id_category=' . $id . ' AND type="id_feature" AND id_value=' . (int) $f
            );
            if ($exists) { continue; }
            if ($apply) {
                $pos = (int) $db->getValue(
                    'SELECT COALESCE(MAX(position),0)+1 FROM `' . _DB_PREFIX_ . 'layered_category` WHERE id_category=' . $id
                );
                $db->insert('layered_category', [
                    'id_shop'           => $idShop,
                    'controller'        => 'category',
                    'id_category'       => $id,
                    'id_value'          => (int) $f,
                    'type'              => 'id_feature',
                    'position'          => $pos,
                    'filter_type'       => 0,
                    'filter_show_limit' => 0,
                ], false, true, Db::INSERT_IGNORE);
            }
            $touched[] = $f;
        }
    }

    if ($touched) {
        $done += count($touched);
        printf("  %-28s (id %d) : %s %s\n", $c['name'], $id, $remove ? 'retrait' : 'ajout', implode(',', $touched));
    }
}

printf("\n%d ligne(s) de facette %s%s.\n", $done, $remove ? 'à retirer' : 'à ajouter', $apply ? ' — fait' : '');

if ($apply) {
    $db->execute('TRUNCATE TABLE `' . _DB_PREFIX_ . 'layered_filter_block`');
    echo "Cache des blocs de facettes purgé.\n";
    echo "Note : la facette reste masquée tant qu'aucun produit ne porte la valeur (import à venir).\n";
}
