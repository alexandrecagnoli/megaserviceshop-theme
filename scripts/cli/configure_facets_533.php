<?php
/**
 * Configure les facettes (ps_facetedsearch) sur la branche PowerParts refondue (533).
 * ==============================================
 *
 * 533 n'avait aucun filtre. On réplique le jeu de filtres des familles de l'ancienne
 * branche 41 : availability, extras, manufacturer, price + les 3 caractéristiques
 * (Motorisation 60, Matière de la pièce 61, Type de pièce 62).
 *
 * Le filtre « Catégories » (type=category) fait doublon avec le carousel de familles
 * du hero → on NE le configure QUE sur les familles (niveau 3), pas sur la RACINE 533.
 * Résultat : racine 533 = facettes sans « Catégories » ; familles = avec. (Pas besoin
 * d'override du template du module.)
 *
 * Idempotent (un couple (catégorie,filtre) déjà présent n'est pas redoublé). Purge
 * ps_layered_filter_block + cache. Dry-run par défaut, --apply ensuite.
 *
 * ⚠️ Config posée directement dans ps_layered_category (comme pour 41). Un ré-enregistrement
 * du gabarit « Powerparts » en back-office peut nécessiter de rejouer ce script.
 *
 * Usage :
 *   php scripts/cli/configure_facets_533.php
 *   php scripts/cli/configure_facets_533.php --apply
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(1); }
$apply = in_array('--apply', $argv, true);

$dir=__DIR__; $configPath=null;
for($i=0;$i<15;++$i){ $c=$dir.'/config/config.inc.php'; if(is_file($c)){$configPath=$c;break;} $p=dirname($dir); if($p===$dir)break; $dir=$p; }
if(!$configPath){ fwrite(STDERR,"config.inc.php introuvable.\n"); exit(1); }
define('_PS_ADMIN_DIR_', dirname($configPath).'/admin');
require_once $configPath;
$db=Db::getInstance(); $pref=_DB_PREFIX_; $il=(int)Configuration::get('PS_LANG_DEFAULT');
$idShop=(int)Configuration::get('PS_SHOP_DEFAULT') ?: 1;

const ROOT=533;
// Filtres communs (sans le filtre « category ») : type + id_value
$COMMON = [
    ['availability',''], ['extras',''], ['manufacturer',''], ['price',''],
    ['id_feature','60'], ['id_feature','61'], ['id_feature','62'],
];
// Sur les familles, on ajoute « category » EN TÊTE.
$FAMILY = array_merge([['category','']], $COMMON);

// Cibles : racine 533 (sans category) + familles directes (avec category)
$targets = [];
$targets[ROOT] = $COMMON;
$families = $db->executeS("SELECT c.id_category,(SELECT name FROM {$pref}category_lang WHERE id_category=c.id_category AND id_lang=$il) nm FROM {$pref}category c WHERE c.id_parent=".ROOT." ORDER BY c.position");
foreach($families as $f) $targets[(int)$f['id_category']] = $FAMILY;

echo ($apply?"=== APPLICATION ===":"=== DRY-RUN (aucune écriture) ===")."\n";
echo "Racine 533 : ".count($COMMON)." filtres (SANS « Catégories »)\n";
echo "Familles de 533 : ".count($families)." × ".count($FAMILY)." filtres (AVEC « Catégories »)\n\n";

$toAdd=0; $exists=0;
foreach($targets as $idCat=>$filters){
    $pos=1;
    foreach($filters as [$type,$val]){
        $where = "id_category=".(int)$idCat." AND type=\"".pSQL($type)."\" AND id_value".($val===''?" IS NULL OR id_value=''":"=\"".pSQL($val)."\"");
        $has=(bool)$db->getValue("SELECT 1 FROM {$pref}layered_category WHERE id_category=".(int)$idCat." AND type=\"".pSQL($type)."\" AND ".($val===''?"(id_value IS NULL OR id_value='')":"id_value=\"".pSQL($val)."\""));
        if($has){ $exists++; $pos++; continue; }
        if($apply){
            $db->insert('layered_category',[
                'id_shop'=>$idShop,'controller'=>'category','id_category'=>(int)$idCat,
                'id_value'=>($val===''?null:(int)$val),'type'=>$type,'position'=>$pos,
                'filter_type'=>0,'filter_show_limit'=>0,
            ],false,true,Db::INSERT_IGNORE);
        }
        $toAdd++; $pos++;
    }
}
printf("Filtres à ajouter : %d | déjà présents : %d\n", $toAdd, $exists);

if(!$apply){ echo "\nDry-run terminé. --apply après feu vert.\n"; exit(0); }

$db->execute("TRUNCATE TABLE {$pref}layered_filter_block");
Tools::clearSmartyCache();
if(function_exists('exec')) @exec('rm -rf '.escapeshellarg(_PS_ROOT_DIR_.'/var/cache/prod').'/*');
echo "Cache facettes purgé (layered_filter_block) + cache prod vidé.\n";
echo "Note : filtres masqués tant qu'aucun produit ne porte la valeur (normal).\n";
