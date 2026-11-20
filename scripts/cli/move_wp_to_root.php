<?php
/**
 * Sort la catégorie « Suspensions WP » (332) et tout son sous-arbre de la branche
 * « Accessoires powerparts » (41) pour en faire un enfant direct d'Accueil.
 * ==============================================
 *
 * Contexte : la branche 41 va être vidée (pollution OEM + accessoires à réintégrer
 * ailleurs). Mais le sous-arbre WP (Suspensions WP → Pièces détachées WP/Amortisseur,
 * Produits WP/Fourche/Direction) contient ~469 produits WP qui n'ont de place QUE là.
 * On le préserve en le remontant sous Accueil, intact.
 *
 * Ne déplace QUE le nœud 332 (son sous-arbre suit automatiquement). Ne touche à AUCUNE
 * association produit-catégorie : les produits restent rattachés aux mêmes catégories,
 * qui changent seulement de parent. Les link_rewrite (URLs) sont id-based et ne
 * changent pas ; seul le fil d'Ariane se raccourcit (Accueil > Suspensions WP …).
 * Noms, état actif et ordre conservés. Régénère nleft/nright.
 *
 * Réversible (dump .sql avant apply). Idempotent. Dry-run par défaut.
 *
 * Usage :
 *   php scripts/cli/move_wp_to_root.php
 *   php scripts/cli/move_wp_to_root.php --apply
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(1); }
$apply = in_array('--apply', $argv, true);

$dir=__DIR__; $configPath=null;
for($i=0;$i<15;++$i){ $c=$dir.'/config/config.inc.php'; if(is_file($c)){$configPath=$c;break;} $p=dirname($dir); if($p===$dir)break; $dir=$p; }
if(!$configPath){ fwrite(STDERR,"config.inc.php introuvable.\n"); exit(1); }
define('_PS_ADMIN_DIR_', dirname($configPath).'/admin');
require_once $configPath;
$db=Db::getInstance(); $pref=_DB_PREFIX_; $il=(int)Configuration::get('PS_LANG_DEFAULT');
$HOME=(int)Configuration::get('PS_HOME_CATEGORY') ?: 2;

const WP_ROOT=332; // Suspensions WP

$cat=$db->getRow("SELECT c.id_category,c.id_parent,c.nleft,c.nright,c.active,c.position,
    (SELECT name FROM {$pref}category_lang WHERE id_category=c.id_category AND id_lang=$il) nm,
    (SELECT name FROM {$pref}category_lang WHERE id_category=c.id_parent AND id_lang=$il) pnm
    FROM {$pref}category c WHERE c.id_category=".WP_ROOT);
if(!$cat){ fwrite(STDERR,"Catégorie ".WP_ROOT." introuvable.\n"); exit(1); }

echo ($apply?"=== APPLICATION ===":"=== DRY-RUN (aucune écriture) ===")."\n\n";
printf("Nœud à déplacer : %d « %s »\n", WP_ROOT, $cat['nm']);
printf("  parent actuel : %d (%s)  →  cible : %d (Accueil)\n", $cat['id_parent'], $cat['pnm'], $HOME);
echo "  état actif : ".$cat['active']." (conservé)\n\n";

echo "Sous-arbre déplacé (suit automatiquement) + produits :\n";
$sub=$db->executeS("SELECT c.id_category,c.level_depth,
    (SELECT name FROM {$pref}category_lang WHERE id_category=c.id_category AND id_lang=$il) nm
    FROM {$pref}category c WHERE c.nleft>=".(int)$cat['nleft']." AND c.nright<=".(int)$cat['nright']." ORDER BY c.nleft");
$baseDepth=(int)$db->getValue("SELECT level_depth FROM {$pref}category WHERE id_category=".WP_ROOT);
$totalProd=0;
foreach($sub as $x){
    $np=(int)$db->getValue("SELECT COUNT(*) FROM {$pref}category_product WHERE id_category=".(int)$x['id_category']);
    printf("  %s%d %s (%d produits directs)\n", str_repeat('  ',$x['level_depth']-$baseDepth), $x['id_category'], $x['nm'], $np);
}
$subIds=implode(',',array_map(fn($x)=>(int)$x['id_category'],$sub));
$distinct=(int)$db->getValue("SELECT COUNT(DISTINCT id_product) FROM {$pref}category_product WHERE id_category IN ($subIds)");
echo "\nProduits distincts dans le sous-arbre WP : $distinct (inchangés — associations non touchées)\n";
echo "link_rewrite de 332 : ".$db->getValue("SELECT link_rewrite FROM {$pref}category_lang WHERE id_category=".WP_ROOT." AND id_lang=$il")." (inchangé → URL valide)\n";

if($cat['id_parent']==$HOME){ echo "\nDéjà sous Accueil — rien à faire.\n"; exit(0); }
if(!$apply){ echo "\nDry-run terminé. --apply après feu vert.\n"; exit(0); }

// ── APPLY ────────────────────────────────────────────────────────────────────
$ts=date('Ymd_His'); $bk=dirname(__DIR__).'/backups/wpmove_'.$ts.'.sql';
@mkdir(dirname($bk),0775,true);
$CREDS=[_DB_SERVER_,_DB_NAME_,_DB_USER_,_DB_PASSWD_];
putenv('MYSQL_PWD='.$CREDS[3]);
$tbls=$pref.'category '.$pref.'category_lang '.$pref.'category_shop '.$pref.'category_group';
@exec('mysqldump --no-tablespaces -h '.escapeshellarg($CREDS[0]).' -u '.escapeshellarg($CREDS[2]).' '.escapeshellarg($CREDS[1]).' '.$tbls.' > '.escapeshellarg($bk).' 2>/dev/null');
echo "\nSauvegarde : $bk (".(is_file($bk)?round(filesize($bk)/1024).' Ko':'ÉCHEC').")\n";

$c=new Category(WP_ROOT);
$c->id_parent=$HOME;
$c->update();
Category::regenerateEntireNtree();
Tools::clearSmartyCache();
if(function_exists('exec')) @exec('rm -rf '.escapeshellarg(_PS_ROOT_DIR_.'/var/cache/prod').'/*');
echo "332 déplacé sous Accueil, ntree régénéré, cache vidé.\n";
echo "Vérif : parent de 332 = ".(int)$db->getValue("SELECT id_parent FROM {$pref}category WHERE id_category=".WP_ROOT)."\n";
