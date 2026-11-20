<?php
/**
 * Rattache chaque produit de la branche PowerParts (533) à TOUTE sa chaîne de
 * catégories (racine 533 + famille + sous-famille + feuille), pas seulement à la
 * feuille finale.
 * ==============================================
 *
 * ba_importer (searchByPath) n'associe le produit qu'à la catégorie FINALE (feuille)
 * + Accueil. Résultat : filtrer/afficher par la racine 533 ou par une famille ne
 * remonte presque rien (le produit n'y est pas rattaché « en direct »).
 *
 * On ajoute donc, pour chaque produit, une association à TOUS ses ancêtres de
 * catégorie SITUÉS DANS le sous-arbre 533 (via nleft/nright). Après : un produit
 * d'une feuille niveau 4 est aussi dans sa famille (niv.3) et dans 533 (niv.2).
 *
 * N'ajoute QUE des associations (INSERT IGNORE) — ne retire rien, ne touche pas à
 * Accueil ni aux autres branches. Réversible (backup .sql des lignes ajoutées).
 * Idempotent. Dry-run par défaut.
 *
 * ⚠️ Corrige l'ÉTAT ACTUEL. Pour les futurs imports, à rejouer après chaque import
 * (ba_importer n'associe toujours qu'à la feuille).
 *
 * Usage :
 *   php scripts/cli/associate_full_path_533.php
 *   php scripts/cli/associate_full_path_533.php --apply
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(1); }
$apply = in_array('--apply', $argv, true);

$dir=__DIR__; $configPath=null;
for($i=0;$i<15;++$i){ $c=$dir.'/config/config.inc.php'; if(is_file($c)){$configPath=$c;break;} $p=dirname($dir); if($p===$dir)break; $dir=$p; }
if(!$configPath){ fwrite(STDERR,"config.inc.php introuvable.\n"); exit(1); }
define('_PS_ADMIN_DIR_', dirname($configPath).'/admin');
require_once $configPath;
$db=Db::getInstance(); $pref=_DB_PREFIX_;
$idShop=(int)Configuration::get('PS_SHOP_DEFAULT') ?: 1;

const ROOT=533;
$r=$db->getRow("SELECT nleft,nright FROM {$pref}category WHERE id_category=".ROOT);
$nl=(int)$r['nleft']; $nr=(int)$r['nright'];

// Table temporaire : pour chaque produit, tous ses ancêtres de catégorie dans le
// sous-arbre 533 (A ancêtre de D si A.nleft<=D.nleft et A.nright>=D.nright).
$db->execute("DROP TEMPORARY TABLE IF EXISTS tmp_fullpath");
$db->execute(
  "CREATE TEMPORARY TABLE tmp_fullpath (id_category INT NOT NULL, id_product INT NOT NULL, PRIMARY KEY(id_category,id_product)) ENGINE=MEMORY
   SELECT DISTINCT A.id_category, cp.id_product
   FROM {$pref}category_product cp
   JOIN {$pref}category D ON D.id_category=cp.id_category AND D.nleft>=$nl AND D.nright<=$nr
   JOIN {$pref}category A ON A.nleft<=D.nleft AND A.nright>=D.nright AND A.nleft>=$nl AND A.nright<=$nr");

$totPairs=(int)$db->getValue("SELECT COUNT(*) FROM tmp_fullpath");
// nouvelles (pas déjà dans category_product)
$new=(int)$db->getValue(
  "SELECT COUNT(*) FROM tmp_fullpath t
   LEFT JOIN {$pref}category_product cp ON cp.id_category=t.id_category AND cp.id_product=t.id_product
   WHERE cp.id_product IS NULL");

echo ($apply?"=== APPLICATION ===":"=== DRY-RUN (aucune écriture) ===")."\n";
echo "Branche 533 : ".((int)$db->getValue("SELECT COUNT(DISTINCT id_product) FROM tmp_fullpath"))." produits\n";
echo "Associations chaîne complète (total attendu) : $totPairs\n";
echo "Associations À AJOUTER (manquantes) : $new\n";
// aperçu : combien de produits rattachés à 533 et à une famille APRÈS
echo "\nAprès application :\n";
echo "  produits directement sur 533 : ".(int)$db->getValue("SELECT COUNT(DISTINCT id_product) FROM tmp_fullpath WHERE id_category=".ROOT)."\n";
$fam=implode(',',array_map('intval',array_column($db->executeS("SELECT id_category FROM {$pref}category WHERE id_parent=".ROOT),'id_category')));
echo "  produits dans une famille (niv.3) : ".(int)$db->getValue("SELECT COUNT(DISTINCT id_product) FROM tmp_fullpath WHERE id_category IN ($fam)")."\n";

if(!$apply){ echo "\nDry-run terminé. --apply après feu vert.\n"; exit(0); }

// backup des lignes AJOUTÉES (pour pouvoir les retirer)
$ts=date('Ymd_His'); $bk=dirname(__DIR__).'/backups/fullpath533_'.$ts.'.sql';
@mkdir(dirname($bk),0775,true);
$fh=fopen($bk,'w'); fwrite($fh,"-- Lignes category_product AJOUTEES (chaine complete 533), $ts — pour annuler : DELETE\n");
foreach($db->executeS(
  "SELECT t.id_category,t.id_product FROM tmp_fullpath t
   LEFT JOIN {$pref}category_product cp ON cp.id_category=t.id_category AND cp.id_product=t.id_product
   WHERE cp.id_product IS NULL") as $x)
  fwrite($fh,"DELETE FROM {$pref}category_product WHERE id_category=".(int)$x['id_category']." AND id_product=".(int)$x['id_product'].";\n");
fclose($fh);
echo "\nSauvegarde (annulation) : $bk ($new lignes)\n";

$db->execute(
  "INSERT IGNORE INTO {$pref}category_product (id_category,id_product,position)
   SELECT id_category,id_product,0 FROM tmp_fullpath");
$db->execute("DROP TEMPORARY TABLE IF EXISTS tmp_fullpath");
echo "$new associations ajoutées.\n";

// rafraîchir la position (facultatif) + cache
if($db->getValue("SELECT 1 FROM {$pref}layered_filter_block LIMIT 1")!==false) $db->execute("TRUNCATE TABLE {$pref}layered_filter_block");
Tools::clearSmartyCache();
if(function_exists('exec')) @exec('rm -rf '.escapeshellarg(_PS_ROOT_DIR_.'/var/cache/prod').'/*');
echo "Cache vidé. Terminé.\n";
