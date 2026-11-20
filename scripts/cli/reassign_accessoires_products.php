<?php
/**
 * Squelette d'arbre DÉDIÉ + réassociation produits pour « Accessoires moto ».
 * ==============================================
 *
 * Suite de rebuild_accessoires_tree.php (qui a rangé les catégories à plat). Ici on :
 *
 *  a) garantit que CHAQUE nœud (Main / Cat 2 / Cat 3) du CSV existe DANS les 3 racines
 *     (Accessoires moto, Kits pièces de rechange d'origine, Outils spéciaux KTM), avec
 *     le bon parent. Les 9 libellés homonymes de PowerParts (Moteur, Protection,
 *     Refroidissement, Couronnes, Élément de suspension, Disques de frein, ABS,
 *     Amortisseur, Fourche) sont créés en catégories DÉDIÉES sous leur parent cible.
 *     On ne réutilise JAMAIS une catégorie hors des 3 racines ; on ne déplace / ne
 *     modifie AUCUNE catégorie PowerParts/WP existante.
 *     Une catégorie à plat restée sous Accueil et portant exactement le libellé cible
 *     (les 6 orphelines) est RÉUTILISÉE (reparentée), pas dupliquée.
 *
 *  b) réassocie chaque produit du CSV à sa catégorie cible (racine + famille + sous-cat),
 *     RETIRE son association à l'homonyme PowerParts, fixe la catégorie par défaut sur le
 *     nœud le plus profond, et retire l'association parasite à « Accueil ». Ne touche à
 *     aucun autre produit.
 *
 * Idempotent. Dry-run par défaut. `--apply` après feu vert.
 *
 * Usage (préprod, SSH) :
 *   php scripts/cli/reassign_accessoires_products.php scripts/cli/echantillon.csv
 *   php scripts/cli/reassign_accessoires_products.php scripts/cli/echantillon.csv --apply
 */

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(1); }
$apply = in_array('--apply', $argv, true);
$args  = array_values(array_filter(array_slice($argv, 1), fn($a) => strpos($a, '--') !== 0));
$csv   = $args[0] ?? null;
if (!$csv || !is_file($csv)) { fwrite(STDERR, "CSV introuvable.\n"); exit(1); }

$dir = __DIR__; $configPath = null;
for ($i = 0; $i < 15; ++$i) { $c = $dir.'/config/config.inc.php'; if (is_file($c)) { $configPath=$c; break; } $p=dirname($dir); if ($p===$dir) break; $dir=$p; }
if (!$configPath) { fwrite(STDERR, "config.inc.php introuvable.\n"); exit(1); }
define('_PS_ADMIN_DIR_', dirname($configPath).'/admin');
require_once $configPath;

$db=Db::getInstance(); $pref=_DB_PREFIX_;
$idLang=(int)Configuration::get('PS_LANG_DEFAULT');
$idShop=(int)Configuration::get('PS_SHOP_DEFAULT') ?: 1;
$HOME=(int)Configuration::get('PS_HOME_CATEGORY') ?: 2;
$ROOTS=['Accessoires moto','Kits pièces de rechange d\'origine','Outils spéciaux KTM'];

// ── CSV ─────────────────────────────────────────────────────────────────────
$fh=fopen($csv,'r'); $bom=fread($fh,3); if($bom!=="\xEF\xBB\xBF") rewind($fh);
$header=fgetcsv($fh,0,';'); $ci=array_flip($header);
$rows=[];
while(($r=fgetcsv($fh,0,';'))!==false){
    $rows[]=['ref'=>trim((string)($r[$ci['Référence produit']]??'')),
             'main'=>trim((string)($r[$ci['Main category']]??'')),
             'cat2'=>trim((string)($r[$ci['Cat 2']]??'')),
             'cat3'=>trim((string)($r[$ci['Cat 3']]??''))];
}
fclose($fh);

// Racines → id (enfant direct d'Accueil)
$rootId=[];
foreach($ROOTS as $r){
    $id=(int)$db->getValue("SELECT c.id_category FROM {$pref}category c JOIN {$pref}category_lang cl ON cl.id_category=c.id_category AND cl.id_lang=$idLang WHERE cl.name='".pSQL($r)."' AND c.id_parent=$HOME");
    if(!$id){ fwrite(STDERR,"Racine introuvable sous Accueil : $r\n"); exit(1); }
    $rootId[$r]=$id;
}

$childByParentName=function($parent,$name) use($db,$pref,$idLang){
    return (int)$db->getValue("SELECT c.id_category FROM {$pref}category c JOIN {$pref}category_lang cl ON cl.id_category=c.id_category AND cl.id_lang=$idLang WHERE cl.name='".pSQL($name)."' AND c.id_parent=".(int)$parent);
};

// ── a) Plan squelette ────────────────────────────────────────────────────────
$toCreate=[];   // [name,parent_label,parent_id|null]
$toReparent=[]; // [id,name,from,to_label,to_id|null]
$nodeId=[];     // "parentId\x1fname" => id (réel) | "NEW#k" (virtuel dry-run)
$vcount=0;

// $parentRef = id réel (int) OU identifiant virtuel (string "NEW#k") d'une cat à créer.
$ensure=function($parentRef,$parentLabel,$name) use(&$ensure,&$nodeId,&$toCreate,&$toReparent,&$vcount,$childByParentName,$HOME){
    $key=$parentRef."\x1f".$name;
    if(isset($nodeId[$key])) return $nodeId[$key];
    // 1) déjà enfant du parent cible (seulement si parent réel)
    if(is_int($parentRef)){
        $id=$childByParentName($parentRef,$name);
        if($id){ $nodeId[$key]=$id; return $id; }
    }
    // 2) catégorie à plat (sous Accueil) de même libellé → réutiliser (reparent)
    $flat=$childByParentName($HOME,$name);
    if($flat){
        $toReparent[]=['id'=>$flat,'name'=>$name,'to_label'=>$parentLabel,'to_id'=>$parentRef];
        $nodeId[$key]=$flat; return $flat;
    }
    // 3) à créer
    $vid='NEW#'.(++$vcount);
    $toCreate[]=['name'=>$name,'parent_label'=>$parentLabel,'parent_id'=>$parentRef,'vid'=>$vid];
    $nodeId[$key]=$vid;
    return $vid;
};

// construit l'arbre (ordre : familles puis sous-cat)
foreach($rows as $row){
    $m=$row['main']; if(!isset($rootId[$m])) continue;
    $rid=$rootId[$m];
    if($row['cat2']!==''){
        $c2=$ensure($rid,$m,$row['cat2']);
        if($row['cat3']!==''){
            $ensure($c2,$row['cat2'],$row['cat3']); // $c2 peut être réel ou virtuel
        }
    }
}

// ── b) Plan réassociation produits ──────────────────────────────────────────
// target ids par ref
function nodeResolve(&$nodeId,$childByParentName,$HOME,$rootId,$m,$cat2,$cat3){
    $rid=$rootId[$m]; $res=['root'=>$rid,'cat2'=>null,'cat3'=>null];
    if($cat2!==''){
        $res['cat2']=$nodeId[$rid."\x1f".$cat2]??null; // int réel ou "NEW#k"
        if($cat3!=='' && $res['cat2']!==null){
            $res['cat3']=$nodeId[$res['cat2']."\x1f".$cat3]??null; // la clé marche pour parent réel OU virtuel
        }
    }
    return $res;
}

$refs=array_values(array_unique(array_filter(array_map(fn($r)=>$r['ref'],$rows))));
// associations courantes
$cur=[]; // ref => [id=>name]
foreach(array_chunk($refs,200) as $ch){
    $in=implode(',',array_map(fn($x)=>'"'.pSQL($x).'"',$ch));
    $res=$db->executeS("SELECT p.reference ref,p.id_product,cp.id_category id,cl.name name,p.id_category_default def
        FROM {$pref}product p JOIN {$pref}category_product cp ON cp.id_product=p.id_product
        JOIN {$pref}category_lang cl ON cl.id_category=cp.id_category AND cl.id_lang=$idLang
        WHERE p.reference IN ($in)");
    foreach($res as $x){ $cur[$x['ref']]['id']=$x['id_product']; $cur[$x['ref']]['def']=$x['def']; $cur[$x['ref']]['cats'][(int)$x['id']]=$x['name']; }
}

$statAdd=0;$statRemHom=0;$statRemHome=0;$statDef=0;$remHomList=[];$homeList=[];
foreach($rows as $row){
    $ref=$row['ref']; if(!isset($cur[$ref])) continue;
    $r=nodeResolve($nodeId,$childByParentName,$HOME,$rootId,$row['main'],$row['cat2'],$row['cat3']);
    $targets=array_filter([$r['root'],$r['cat2'],$r['cat3']],fn($v)=>$v!==null);
    $curCats=$cur[$ref]['cats']??[];
    // ajouts (ids réels manquants ; les virtuels seront ajoutés à la création)
    foreach($targets as $t){ if(is_int($t) && !isset($curCats[$t])) $statAdd++; elseif(!is_int($t)) $statAdd++; }
    // retrait homonyme : association dont le nom == cat2/cat3 mais id != cible et hors 3 racines
    foreach([$row['cat2']=>$r['cat2'],$row['cat3']=>$r['cat3']] as $lbl=>$tid){
        if($lbl==='') continue;
        foreach($curCats as $cid=>$cname){
            if($cname===$lbl && $cid!==$tid && (!is_int($tid) || $cid!==$tid)){
                // est-ce l'homonyme PowerParts ? (pas une de nos cibles)
                if(!in_array($cid,array_filter($targets,'is_int'),true)){ $statRemHom++; $remHomList[$cid]=$cname; }
            }
        }
    }
    // retrait Accueil parasite
    if(isset($curCats[$HOME])){ $statRemHome++; $homeList[$ref]=true; }
    // catégorie par défaut = nœud le plus profond
    $deepest=$r['cat3']??($r['cat2']??$r['root']); if($r['cat3']===null)$deepest=$r['cat2']??$r['root'];
    $deepest = $r['cat3'] !== null ? $r['cat3'] : ($r['cat2'] !== null ? $r['cat2'] : $r['root']);
    if(is_int($deepest) && (int)($cur[$ref]['def']??0)!==$deepest) $statDef++;
    elseif(!is_int($deepest)) $statDef++;
}

// ── Affichage ────────────────────────────────────────────────────────────────
echo ($apply?"=== APPLICATION ===":"=== DRY-RUN (aucune écriture) ===")."\n";
echo "CSV : $csv\n\n";

echo "a) SQUELETTE\n";
echo "  Catégories DÉDIÉES à créer : ".count($toCreate)."\n";
foreach($toCreate as $c) printf("     + '%s' sous '%s' (parent id %s)\n",$c['name'],$c['parent_label'],is_int($c['parent_id'])?$c['parent_id']:$c['parent_id']);
echo "  Orphelines à réutiliser (reparenter) : ".count($toReparent)."\n";
foreach($toReparent as $c) printf("     ~ id=%d '%s' → sous '%s' (id %s)\n",$c['id'],$c['name'],$c['to_label'],is_int($c['to_id'])?$c['to_id']:$c['to_id']);

echo "\nb) PRODUITS (120 réf du CSV)\n";
printf("  Associations cibles à ajouter   : %d\n",$statAdd);
printf("  Associations homonyme à retirer : %d  (ids %s)\n",$statRemHom,implode(',',array_keys($remHomList)));
printf("  Association « Accueil » à retirer: %d produits\n",$statRemHome);
printf("  Catégorie par défaut à corriger : %d\n",$statDef);

if(!$apply){ echo "\nDry-run terminé. --apply après feu vert.\n"; exit(0); }

// ── APPLY ────────────────────────────────────────────────────────────────────
echo "\n--- création catégories ---\n";
$realId=[]; // vid => real id
// recrée dans l'ordre : il faut créer parents avant enfants ; toCreate est déjà en ordre d'insertion
foreach($toCreate as $c){
    $parent = is_int($c['parent_id']) ? $c['parent_id'] : ($realId[$c['parent_id']] ?? null);
    if($parent===null){ echo "  ! parent non résolu pour {$c['name']}\n"; continue; }
    $cat=new Category();
    $cat->id_parent=$parent;
    $cat->active=1;
    $cat->name=[]; $cat->link_rewrite=[];
    foreach(Language::getLanguages(true) as $l){ $cat->name[$l['id_lang']]=$c['name']; $cat->link_rewrite[$l['id_lang']]=Tools::str2url($c['name']); }
    $cat->add();
    $realId[$c['vid']]=(int)$cat->id;
    $db->execute("INSERT IGNORE INTO {$pref}category_shop (id_category,id_shop) VALUES (".(int)$cat->id.",".$idShop.")");
    echo "  + créé '{$c['name']}' id={$cat->id} sous $parent\n";
}
foreach($toReparent as $c){
    $to = is_int($c['to_id']) ? $c['to_id'] : ($realId[$c['to_id']]??null);
    if($to===null){ echo "  ! parent non résolu pour reparent {$c['name']}\n"; continue; }
    $cat=new Category($c['id']); $cat->id_parent=$to; $cat->update();
    echo "  ~ reparent id={$c['id']} → $to\n";
}
Category::regenerateEntireNtree();

echo "\n--- réassociation produits ---\n";
// map virtuel → réel pour nodeId
$resolveVid=function($v) use($realId){ return is_int($v)?$v:($realId[$v]??null); };
$nA=0;$nR=0;$nH=0;$nD=0;
foreach($rows as $row){
    $ref=$row['ref']; if(!isset($cur[$ref])) continue;
    $pid=(int)$cur[$ref]['id'];
    $r=nodeResolve($nodeId,$childByParentName,$HOME,$rootId,$row['main'],$row['cat2'],$row['cat3']);
    $root=$resolveVid($r['root']); $c2=$r['cat2']!==null?$resolveVid($r['cat2']):null; $c3=$r['cat3']!==null && $r['cat3']!=='(dépend parent créé)'?$resolveVid($r['cat3']):null;
    $targets=array_values(array_filter([$root,$c2,$c3],fn($v)=>is_int($v)));
    $curCats=array_keys($cur[$ref]['cats']??[]);
    foreach($targets as $t){ if(!in_array($t,$curCats,true)){ $db->execute("INSERT IGNORE INTO {$pref}category_product (id_category,id_product,position) VALUES ($t,$pid,0)"); $nA++; } }
    // retrait homonyme
    foreach([$row['cat2']=>$c2,$row['cat3']=>$c3] as $lbl=>$tid){
        if($lbl==='') continue;
        foreach(($cur[$ref]['cats']??[]) as $cid=>$cname){
            if($cname===$lbl && $cid!==$tid && !in_array($cid,$targets,true)){
                $db->execute("DELETE FROM {$pref}category_product WHERE id_category=".(int)$cid." AND id_product=$pid"); $nR++;
            }
        }
    }
    // retrait Accueil
    if(isset($cur[$ref]['cats'][$HOME])){ $db->execute("DELETE FROM {$pref}category_product WHERE id_category=$HOME AND id_product=$pid"); $nH++; }
    // défaut
    $deepest = $c3!==null ? $c3 : ($c2!==null ? $c2 : $root);
    if(is_int($deepest) && (int)$cur[$ref]['def']!==$deepest){ $db->execute("UPDATE {$pref}product SET id_category_default=$deepest WHERE id_product=$pid"); $db->execute("UPDATE {$pref}product_shop SET id_category_default=$deepest WHERE id_product=$pid"); $nD++; }
}
echo "  associations ajoutées=$nA retirées(homonyme)=$nR Accueil retiré=$nH défaut corrigé=$nD\n";
Tools::clearSmartyCache();
if(function_exists('exec')) @exec('rm -rf '.escapeshellarg(_PS_ROOT_DIR_.'/var/cache/prod').'/*');
echo "Cache vidé. Terminé.\n";
