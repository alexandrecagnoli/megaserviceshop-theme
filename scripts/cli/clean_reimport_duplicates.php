<?php
/**
 * Nettoyage post-ré-import : supprime les familles créées EN DOUBLON à plat sous
 * Accueil, et l'association parasite à Accueil des produits du CSV.
 * ==============================================
 *
 * Le ré-import par « Chemin catégorie » a bien construit l'arbre imbriqué, mais les
 * colonnes Cat 2 / Cat 3 étaient restées mappées en plus → chaque famille a aussi été
 * créée à plat sous Accueil. Ces catégories à plat ont TOUTES un équivalent imbriqué
 * (vérifié) ; on les supprime. Les produits gardent leur feuille imbriquée (défaut).
 *
 * Supprime UNIQUEMENT : les catégories dont le parent = Accueil, créées par ce
 * ré-import (id >= seuil) ET qui possèdent un homonyme rangé ailleurs (= le nœud
 * imbriqué). Retire aussi l'association Accueil des produits du CSV. Rien d'autre.
 *
 * Dry-run par défaut. `--apply` après feu vert.
 *
 * Usage :
 *   php scripts/cli/clean_reimport_duplicates.php scripts/cli/echantillon.csv [seuil_id]
 *   php scripts/cli/clean_reimport_duplicates.php scripts/cli/echantillon.csv 608 --apply
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(1); }
$apply = in_array('--apply', $argv, true);
$args  = array_values(array_filter(array_slice($argv, 1), fn($a) => strpos($a, '--') !== 0));
$csv   = $args[0] ?? null;
$seuil = isset($args[1]) ? (int)$args[1] : 608;
if (!$csv || !is_file($csv)) { fwrite(STDERR, "CSV introuvable.\n"); exit(1); }

$dir = __DIR__; $configPath = null;
for ($i=0;$i<15;++$i){ $c=$dir.'/config/config.inc.php'; if(is_file($c)){$configPath=$c;break;} $p=dirname($dir); if($p===$dir)break; $dir=$p; }
if(!$configPath){ fwrite(STDERR,"config.inc.php introuvable.\n"); exit(1); }
define('_PS_ADMIN_DIR_', dirname($configPath).'/admin');
require_once $configPath;
$db=Db::getInstance(); $pref=_DB_PREFIX_;
$idLang=(int)Configuration::get('PS_LANG_DEFAULT');
$HOME=(int)Configuration::get('PS_HOME_CATEGORY') ?: 2;

// Candidats : enfants directs d'Accueil, id >= seuil, AVEC homonyme rangé ailleurs
$cand=$db->executeS("SELECT c.id_category,(SELECT name FROM {$pref}category_lang WHERE id_category=c.id_category AND id_lang=$idLang) nm
    FROM {$pref}category c WHERE c.id_parent=$HOME AND c.id_category>=$seuil ORDER BY c.id_category");
$toDelete=[]; $skip=[];
foreach($cand as $c){
    $nested=(int)$db->getValue("SELECT COUNT(*) FROM {$pref}category cc JOIN {$pref}category_lang cl ON cl.id_category=cc.id_category AND cl.id_lang=$idLang
        WHERE cl.name='".pSQL($c['nm'])."' AND cc.id_parent<>$HOME");
    if($nested>0) $toDelete[(int)$c['id_category']]=$c['nm'];
    else $skip[(int)$c['id_category']]=$c['nm'];
}

// refs CSV → produits avec association Accueil
$fh=fopen($csv,'r'); $bom=fread($fh,3); if($bom!=="\xEF\xBB\xBF") rewind($fh);
$header=fgetcsv($fh,0,';'); $ci=array_flip($header); $refs=[];
while(($r=fgetcsv($fh,0,';'))!==false){ $v=trim((string)($r[$ci['Référence produit']]??'')); if($v!=='')$refs[$v]=true; }
fclose($fh);
$refs=array_keys($refs); $prodIds=[];
foreach(array_chunk($refs,300) as $ch){
    $in=implode(',',array_map(fn($x)=>'"'.pSQL($x).'"',$ch));
    foreach($db->executeS("SELECT id_product FROM {$pref}product WHERE reference IN ($in)") as $x) $prodIds[]=(int)$x['id_product'];
}
$accueilAssoc=0;
if($prodIds){ $in=implode(',',$prodIds); $accueilAssoc=(int)$db->getValue("SELECT COUNT(*) FROM {$pref}category_product WHERE id_category=$HOME AND id_product IN ($in)"); }

echo ($apply?"=== APPLICATION ===":"=== DRY-RUN (aucune écriture) ===")."\n";
echo "CSV : $csv | seuil id >= $seuil\n\n";
printf("Doublons à plat à supprimer : %d\n",count($toDelete));
echo "   ".implode(', ',array_slice($toDelete,0,8))." ...\n";
if($skip){ echo "⚠ ignorées (pas d'équivalent imbriqué, NON supprimées) : ".count($skip)." → ".implode(', ',$skip)."\n"; }
printf("Associations « Accueil » à retirer (produits CSV) : %d\n",$accueilAssoc);

if(!$apply){ echo "\nDry-run terminé. --apply après feu vert.\n"; exit(0); }

echo "\n--- suppression doublons ---\n";
$d=0;
foreach(array_keys($toDelete) as $id){ $c=new Category($id); if(Validate::isLoadedObject($c)){ $c->delete(); $d++; } }
echo "  $d catégories à plat supprimées\n";
if($prodIds){ $in=implode(',',$prodIds); $db->execute("DELETE FROM {$pref}category_product WHERE id_category=$HOME AND id_product IN ($in)"); echo "  $accueilAssoc associations Accueil retirées\n"; }
Category::regenerateEntireNtree();
Tools::clearSmartyCache();
if(function_exists('exec')) @exec('rm -rf '.escapeshellarg(_PS_ROOT_DIR_.'/var/cache/prod').'/*');
echo "Ntree régénéré, cache vidé. Terminé.\n";
