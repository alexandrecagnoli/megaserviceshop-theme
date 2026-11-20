<?php
/**
 * Nettoyage : retire de la branche PowerParts (41) les produits qui sont en
 * réalité des OEM (présents dans la branche « Pièces détachées d'origine », 12).
 * ==============================================
 *
 * Diagnostic (2026-11-20) : ~30 414 pièces OEM, importées en lot d'août 2026, ont
 * été rangées À TORT dans la taxonomie détaillée PowerParts (41) par le
 * rattachement PAR NOM de ba_importer (searchByName global sur les libellés
 * « Culasse et Distribution », « Carter moteur », etc. qui existaient déjà sous 41).
 * L'export actuel est hors de cause. Règle cible confirmée côté export : un OEM ne
 * doit jamais être sous « Accessoires powerparts ».
 *
 * Ce script retire UNIQUEMENT les associations vers des catégories du sous-arbre 41,
 * et SEULEMENT pour les produits qui sont AUSSI dans le sous-arbre 12 (donc rangés
 * en OEM par ailleurs — ils ne perdent pas leur place). Il ne touche :
 *   - ni la branche 12 (OEM), ni la branche 533 (Accessoires moto), ni rien d'autre ;
 *   - ni les produits « 41 seulement » (non présents en OEM) — laissés tels quels.
 * Si la catégorie par défaut d'un produit tombe dans 41, elle est rebasculée sur sa
 * catégorie OEM la plus profonde.
 *
 * Réversible : en --apply, écrit d'abord un fichier .sql de restauration des lignes
 * supprimées. Idempotent. Dry-run par défaut.
 *
 * Usage :
 *   php scripts/cli/cleanup_oem_from_powerparts.php
 *   php scripts/cli/cleanup_oem_from_powerparts.php --apply
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(1); }
$apply = in_array('--apply', $argv, true);

$dir = __DIR__; $configPath = null;
for ($i=0;$i<15;++$i){ $c=$dir.'/config/config.inc.php'; if(is_file($c)){$configPath=$c;break;} $p=dirname($dir); if($p===$dir)break; $dir=$p; }
if(!$configPath){ fwrite(STDERR,"config.inc.php introuvable.\n"); exit(1); }
define('_PS_ADMIN_DIR_', dirname($configPath).'/admin');
require_once $configPath;
$db=Db::getInstance(); $pref=_DB_PREFIX_; $il=(int)Configuration::get('PS_LANG_DEFAULT');

const POWERPARTS=41; const OEM=12;
$r41=$db->getRow("SELECT nleft,nright FROM {$pref}category WHERE id_category=".POWERPARTS);
$r12=$db->getRow("SELECT nleft,nright FROM {$pref}category WHERE id_category=".OEM);

// Catégories de chaque sous-arbre
$cat41=array_map('intval',array_column($db->executeS("SELECT id_category FROM {$pref}category WHERE nleft>=".$r41['nleft']." AND nright<=".$r41['nright']),'id_category'));
$in41=implode(',',$cat41);

// Sous-requêtes "produits d'un sous-arbre"
$q41="SELECT DISTINCT cp.id_product FROM {$pref}category_product cp JOIN {$pref}category c ON c.id_category=cp.id_category WHERE c.nleft>=".$r41['nleft']." AND c.nright<=".$r41['nright'];
$q12="SELECT DISTINCT cp.id_product FROM {$pref}category_product cp JOIN {$pref}category c ON c.id_category=cp.id_category WHERE c.nleft>=".$r12['nleft']." AND c.nright<=".$r12['nright'];

// Produits concernés = dans 41 ET dans 12
$nBoth=(int)$db->getValue("SELECT COUNT(*) FROM ($q41) a JOIN ($q12) b ON a.id_product=b.id_product");
// Associations 41 à retirer pour ces produits
$assocToRemove=(int)$db->getValue(
  "SELECT COUNT(*) FROM {$pref}category_product cp
   WHERE cp.id_category IN ($in41)
     AND cp.id_product IN ($q41) AND cp.id_product IN ($q12)");
// Produits dont la catégorie par défaut tombe dans 41 (à rebasculer)
$defIn41=(int)$db->getValue(
  "SELECT COUNT(*) FROM {$pref}product p
   WHERE p.id_category_default IN ($in41)
     AND p.id_product IN ($q41) AND p.id_product IN ($q12)");

echo ($apply?"=== APPLICATION ===":"=== DRY-RUN (aucune écriture) ===")."\n";
echo "Branche PowerParts=".POWERPARTS." (".count($cat41)." catégories) | OEM=".OEM."\n\n";
printf("Produits OEM rangés dans PowerParts (41 ET 12) : %d\n",$nBoth);
printf("Associations vers la branche 41 à RETIRER       : %d\n",$assocToRemove);
printf("Catégories par défaut dans 41 à rebasculer OEM  : %d\n",$defIn41);

echo "\nTop catégories 41 vidées (produits communs) :\n";
foreach($db->executeS(
  "SELECT cp.id_category, (SELECT name FROM {$pref}category_lang WHERE id_category=cp.id_category AND id_lang=$il) nm, COUNT(*) n
   FROM {$pref}category_product cp
   WHERE cp.id_category IN ($in41) AND cp.id_product IN ($q41) AND cp.id_product IN ($q12)
   GROUP BY cp.id_category ORDER BY n DESC LIMIT 12") as $x)
  printf("  %-6s %-30s %6d\n",$x['id_category'],mb_substr($x['nm'],0,30),$x['n']);

// Contrôle de sûreté : après retrait, ces produits gardent-ils une catégorie ? (oui, la branche 12)
$wouldOrphan=(int)$db->getValue(
  "SELECT COUNT(*) FROM ($q41) a JOIN ($q12) b ON a.id_product=b.id_product
   WHERE a.id_product NOT IN (
     SELECT cp.id_product FROM {$pref}category_product cp WHERE cp.id_category NOT IN ($in41) AND cp.id_product=a.id_product
   )");
printf("\nProduits qui deviendraient SANS catégorie après retrait : %d  (doit être 0)\n",$wouldOrphan);

echo "\nBilan 41 : ".(int)$db->getValue("SELECT COUNT(*) FROM ($q41) t")." produits avant → ";
echo (int)($db->getValue("SELECT COUNT(*) FROM ($q41) t")-$nBoth)." après (reste les PowerParts non-OEM)\n";

if(!$apply){ echo "\nDry-run terminé. --apply après feu vert.\n"; exit(0); }
if($wouldOrphan>0){ fwrite(STDERR,"REFUS : $wouldOrphan produits deviendraient orphelins. Abandon.\n"); exit(1); }

// ── APPLY ────────────────────────────────────────────────────────────────────
// 0) table temporaire des produits cibles (dans 41 ET 12) — évite l'erreur MySQL
//    1093 (DELETE sur une table référencée dans sa propre sous-requête).
$db->execute("DROP TEMPORARY TABLE IF EXISTS tmp_oem41");
$db->execute("CREATE TEMPORARY TABLE tmp_oem41 (id_product INT NOT NULL PRIMARY KEY) ENGINE=MEMORY");
$db->execute("INSERT INTO tmp_oem41 (id_product) SELECT a.id_product FROM ($q41) a JOIN ($q12) b ON a.id_product=b.id_product");
echo "\nProduits cibles (table temp) : ".(int)$db->getValue("SELECT COUNT(*) FROM tmp_oem41")."\n";

// 1) sauvegarde des lignes supprimées (restauration)
$ts=date('Ymd_His'); $restore=dirname(__DIR__).'/backups/restore_oem41_'.$ts.'.sql';
@mkdir(dirname($restore),0775,true);
$fh=fopen($restore,'w'); fwrite($fh,"-- Restauration des associations category_product retirées (OEM hors PowerParts), $ts\n");
$rows=$db->executeS(
  "SELECT cp.id_category,cp.id_product,cp.position FROM {$pref}category_product cp
   JOIN tmp_oem41 t ON t.id_product=cp.id_product
   WHERE cp.id_category IN ($in41)");
foreach($rows as $r) fwrite($fh,"INSERT IGNORE INTO {$pref}category_product (id_category,id_product,position) VALUES (".(int)$r['id_category'].",".(int)$r['id_product'].",".(int)$r['position'].");\n");
fclose($fh);
echo "Sauvegarde restauration : $restore (".count($rows)." lignes)\n";

// 2) rebascule des catégories par défaut tombant dans 41 → catégorie OEM la plus profonde
$db->execute(
  "UPDATE {$pref}product p
   JOIN tmp_oem41 t ON t.id_product=p.id_product
   SET p.id_category_default = (
     SELECT cp.id_category FROM {$pref}category_product cp
     JOIN {$pref}category c ON c.id_category=cp.id_category
     WHERE cp.id_product=p.id_product AND c.nleft>=".$r12['nleft']." AND c.nright<=".$r12['nright']."
     ORDER BY c.level_depth DESC LIMIT 1)
   WHERE p.id_category_default IN ($in41)");
$db->execute(
  "UPDATE {$pref}product_shop ps
   JOIN {$pref}product p ON p.id_product=ps.id_product
   JOIN tmp_oem41 t ON t.id_product=ps.id_product
   SET ps.id_category_default=p.id_category_default");
echo "Catégories par défaut rebasculées sur OEM.\n";

// 3) suppression des associations branche 41 (cible = category_product, source = temp)
$db->execute(
  "DELETE cp FROM {$pref}category_product cp
   JOIN tmp_oem41 t ON t.id_product=cp.id_product
   WHERE cp.id_category IN ($in41)");
echo "Associations branche 41 supprimées.\n";
$db->execute("DROP TEMPORARY TABLE IF EXISTS tmp_oem41");

Tools::clearSmartyCache();
if(function_exists('exec')) @exec('rm -rf '.escapeshellarg(_PS_ROOT_DIR_.'/var/cache/prod').'/*');
echo "Cache vidé. Terminé.\n";
