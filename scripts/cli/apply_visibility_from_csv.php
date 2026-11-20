<?php
/**
 * Applique la visibilité produit d'après la colonne « Visibilité » du CSV.
 * ==============================================
 *
 * Rattrapage base : l'import ne mappait pas la colonne « Visibilité » (champ
 * `visibility` du module ba_importer non configuré) → tous les produits sont
 * restés `both`. Ce script lit le CSV et pose la bonne visibilité, par référence.
 *
 * Correspondance (identique au module) :
 *   Everywhere → both | Catalog only → catalog | Search only → search | Nowhere → none
 *
 * Ne touche QUE les produits présents dans le CSV dont la visibilité diffère.
 * Idempotent. Dry-run par défaut. `--apply` pour écrire.
 *
 * Usage :
 *   php scripts/cli/apply_visibility_from_csv.php scripts/cli/echantillon.csv
 *   php scripts/cli/apply_visibility_from_csv.php scripts/cli/echantillon.csv --apply
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(1); }
$apply = in_array('--apply', $argv, true);
$args  = array_values(array_filter(array_slice($argv, 1), fn($a) => strpos($a, '--') !== 0));
$csv   = $args[0] ?? null;
if (!$csv || !is_file($csv)) { fwrite(STDERR, "CSV introuvable.\n"); exit(1); }

$dir = __DIR__; $configPath = null;
for ($i=0;$i<15;++$i){ $c=$dir.'/config/config.inc.php'; if(is_file($c)){$configPath=$c;break;} $p=dirname($dir); if($p===$dir)break; $dir=$p; }
if (!$configPath){ fwrite(STDERR,"config.inc.php introuvable.\n"); exit(1); }
define('_PS_ADMIN_DIR_', dirname($configPath).'/admin');
require_once $configPath;
$db=Db::getInstance(); $pref=_DB_PREFIX_;

$MAP = ['everywhere'=>'both','catalog only'=>'catalog','search only'=>'search','nowhere'=>'none'];

$fh=fopen($csv,'r'); $bom=fread($fh,3); if($bom!=="\xEF\xBB\xBF") rewind($fh);
$header=fgetcsv($fh,0,';'); $ci=array_flip($header);
if(!isset($ci['Référence produit'])||!isset($ci['Visibilité'])){ fwrite(STDERR,"Colonnes « Référence produit » / « Visibilité » manquantes.\n"); exit(1); }

$want=[]; // ref => visibility PS
while(($r=fgetcsv($fh,0,';'))!==false){
    $ref=trim((string)($r[$ci['Référence produit']]??''));
    $vis=strtolower(trim((string)($r[$ci['Visibilité']]??'')));
    if($ref==='' || $vis==='') continue;
    if(!isset($MAP[$vis])){ fwrite(STDERR,"Valeur Visibilité inconnue pour $ref : '$vis'\n"); continue; }
    $want[$ref]=$MAP[$vis];
}
fclose($fh);

printf("Mode : %s | refs CSV avec visibilité : %d\n\n", $apply?'APPLICATION':'simulation', count($want));

$changes=[]; $counts=['both'=>0,'catalog'=>0,'search'=>0,'none'=>0];
foreach($want as $ref=>$vis){
    $counts[$vis]++;
    $cur=$db->getValue("SELECT visibility FROM {$pref}product WHERE reference='".pSQL($ref)."'");
    if($cur===false || $cur===null) continue;
    if($cur!==$vis) $changes[$ref]=[$cur,$vis];
}
echo "Cible par visibilité (CSV) : ".json_encode($counts)."\n";
printf("Produits à corriger : %d\n", count($changes));
foreach($changes as $ref=>$cv) printf("   %-16s %s → %s\n",$ref,$cv[0],$cv[1]);

if(!$apply){ echo "\nDry-run terminé. --apply pour écrire.\n"; exit(0); }

$n=0;
foreach($changes as $ref=>$cv){
    $id=(int)$db->getValue("SELECT id_product FROM {$pref}product WHERE reference='".pSQL($ref)."'");
    if(!$id) continue;
    $db->execute("UPDATE {$pref}product SET visibility='".pSQL($cv[1])."' WHERE id_product=$id");
    $db->execute("UPDATE {$pref}product_shop SET visibility='".pSQL($cv[1])."' WHERE id_product=$id");
    $n++;
}
Tools::clearSmartyCache();
if(function_exists('exec')) @exec('rm -rf '.escapeshellarg(_PS_ROOT_DIR_.'/var/cache/prod').'/*');
echo "\n$n produit(s) mis à jour. Cache vidé.\n";
