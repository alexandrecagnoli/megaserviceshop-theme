<?php
/**
 * Teardown « repartir propre » avant ré-import COMPLET avec le bon mapping.
 * ==============================================
 *
 * Remet à zéro UNIQUEMENT le périmètre « Accessoires moto » créé par l'import, pour
 * qu'un ré-import ba_importer mappé sur la colonne « Chemin catégorie » (séparateur /)
 * + « Visibilité » reconstruise un arbre imbriqué correct, sans doublon ni résidu.
 *
 * Fait, et RIEN d'autre :
 *   1. supprime toutes les associations catégorie des produits du CSV (clean slate) ;
 *   2. supprime les SOUS-catégories des 3 racines (531/533/560) — les racines sont
 *      CONSERVÉES (le ré-import les réutilise) ;
 *   3. supprime les 6 catégories orphelines restées à plat sous Accueil ;
 *   4. régénère nleft/nright.
 *
 * Ne touche JAMAIS PowerParts/WP ni aucune catégorie hors de ce périmètre, ni les
 * produits eux-mêmes (seules leurs associations catégorie sont retirées).
 *
 * Dry-run par défaut. `--apply` après feu vert.
 *
 * Usage :
 *   php scripts/cli/teardown_accessoires_tree.php scripts/cli/echantillon.csv
 *   php scripts/cli/teardown_accessoires_tree.php scripts/cli/echantillon.csv --apply
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(1); }
$apply = in_array('--apply', $argv, true);
$args  = array_values(array_filter(array_slice($argv, 1), fn($a) => strpos($a, '--') !== 0));
$csv   = $args[0] ?? null;
if (!$csv || !is_file($csv)) { fwrite(STDERR, "CSV introuvable.\n"); exit(1); }

$dir = __DIR__; $configPath = null;
for ($i=0;$i<15;++$i){ $c=$dir.'/config/config.inc.php'; if(is_file($c)){$configPath=$c;break;} $p=dirname($dir); if($p===$dir)break; $dir=$p; }
if(!$configPath){ fwrite(STDERR,"config.inc.php introuvable.\n"); exit(1); }
define('_PS_ADMIN_DIR_', dirname($configPath).'/admin');
require_once $configPath;
$db=Db::getInstance(); $pref=_DB_PREFIX_;
$idLang=(int)Configuration::get('PS_LANG_DEFAULT');
$HOME=(int)Configuration::get('PS_HOME_CATEGORY') ?: 2;

$ROOT_NAMES=['Accessoires moto','Kits pièces de rechange d\'origine','Outils spéciaux KTM'];
$ORPHAN_NAMES=['Pièces détachées protection','Accessoires valve d\'échappement','Accessoires carter moteur',
               'Pièces détachées refroidissement','Carter d\'allumage','Accessoires système d\'allumage'];

// refs CSV
$fh=fopen($csv,'r'); $bom=fread($fh,3); if($bom!=="\xEF\xBB\xBF") rewind($fh);
$header=fgetcsv($fh,0,';'); $ci=array_flip($header);
$refs=[];
while(($r=fgetcsv($fh,0,';'))!==false){ $v=trim((string)($r[$ci['Référence produit']]??'')); if($v!=='')$refs[$v]=true; }
fclose($fh);
$refs=array_keys($refs);

// racines (sous Accueil)
$rootIds=[];
foreach($ROOT_NAMES as $n){
    $id=(int)$db->getValue("SELECT c.id_category FROM {$pref}category c JOIN {$pref}category_lang cl ON cl.id_category=c.id_category AND cl.id_lang=$idLang WHERE cl.name='".pSQL($n)."' AND c.id_parent=$HOME");
    if($id) $rootIds[$n]=$id;
}

// 1) associations à supprimer (produits CSV)
$prodIds=[];
foreach(array_chunk($refs,300) as $ch){
    $in=implode(',',array_map(fn($x)=>'"'.pSQL($x).'"',$ch));
    $rows=$db->executeS("SELECT id_product FROM {$pref}product WHERE reference IN ($in)");
    foreach($rows as $x) $prodIds[]=(int)$x['id_product'];
}
$assocCount=0;
if($prodIds){
    $in=implode(',',$prodIds);
    $assocCount=(int)$db->getValue("SELECT COUNT(*) FROM {$pref}category_product WHERE id_product IN ($in)");
}

// 2) sous-catégories des racines (descendants, hors racine)
$descendants=[];
foreach($rootIds as $n=>$rid){
    $r=$db->getRow("SELECT nleft,nright FROM {$pref}category WHERE id_category=$rid");
    $d=$db->executeS("SELECT c.id_category,(SELECT name FROM {$pref}category_lang WHERE id_category=c.id_category AND id_lang=$idLang) nm
        FROM {$pref}category c WHERE c.nleft>".(int)$r['nleft']." AND c.nright<".(int)$r['nright']);
    foreach($d as $x) $descendants[(int)$x['id_category']]=$x['nm'];
}

// 3) orphelines sous Accueil
$orphans=[];
foreach($ORPHAN_NAMES as $n){
    $id=(int)$db->getValue("SELECT c.id_category FROM {$pref}category c JOIN {$pref}category_lang cl ON cl.id_category=c.id_category AND cl.id_lang=$idLang WHERE cl.name='".pSQL($n)."' AND c.id_parent=$HOME");
    if($id) $orphans[$id]=$n;
}

// ── Affichage ────────────────────────────────────────────────────────────────
echo ($apply?"=== APPLICATION ===":"=== DRY-RUN (aucune écriture) ===")."\n";
echo "CSV : $csv\n\n";
echo "Racines CONSERVÉES : ".implode(', ',array_map(fn($n,$i)=>"$n(id $i)",array_keys($rootIds),$rootIds))."\n\n";
printf("1) Associations catégorie à supprimer : %d  (sur %d produits du CSV)\n",$assocCount,count($prodIds));
printf("2) Sous-catégories à supprimer (descendants des 3 racines) : %d\n",count($descendants));
printf("3) Orphelines à plat à supprimer : %d  (%s)\n",count($orphans),implode(', ',$orphans));
echo "\n   Total catégories supprimées : ".(count($descendants)+count($orphans))."\n";
$totalCat=(int)$db->getValue("SELECT COUNT(*) FROM {$pref}category");
echo "   Catégories en base : $totalCat → ".($totalCat-count($descendants)-count($orphans))." après\n";

if(!$apply){ echo "\nDry-run terminé. --apply après feu vert. (Le ré-import ba_importer se fait ensuite dans le BO.)\n"; exit(0); }

// ── APPLY ────────────────────────────────────────────────────────────────────
echo "\n--- 1. suppression associations produits CSV ---\n";
if($prodIds){ $in=implode(',',$prodIds); $db->execute("DELETE FROM {$pref}category_product WHERE id_product IN ($in)"); echo "  $assocCount associations supprimées\n"; }

echo "--- 2+3. suppression catégories (descendants + orphelines) ---\n";
$del=0;
// supprime enfants directs des racines : Category::delete() cascade sur les descendants
foreach($rootIds as $rid){
    $kids=$db->executeS("SELECT id_category FROM {$pref}category WHERE id_parent=$rid");
    foreach($kids as $k){ $c=new Category((int)$k['id_category']); if(Validate::isLoadedObject($c)){ $c->delete(); $del++; } }
}
foreach(array_keys($orphans) as $oid){ $c=new Category($oid); if(Validate::isLoadedObject($c)){ $c->delete(); $del++; } }
echo "  sous-arbres/orphelines supprimés (racines de suppression : $del)\n";

Category::regenerateEntireNtree();
Tools::clearSmartyCache();
if(function_exists('exec')) @exec('rm -rf '.escapeshellarg(_PS_ROOT_DIR_.'/var/cache/prod').'/*');
echo "Ntree régénéré, cache vidé.\n";
echo "Prêt pour le ré-import COMPLET (mapping « Chemin catégorie » séparateur / + « Visibilité »).\n";
