<?php
/**
 * Reconstruction de la hiérarchie des 3 racines « Accessoires moto » après un
 * import qui a créé toutes les catégories À PLAT (enfants directs d'Accueil).
 * ==============================================
 *
 * Source de vérité : colonnes « Main category / Cat 2 / Cat 3 » du CSV d'import.
 *   Racines (niveau 1, enfants d'Accueil) :
 *     - Accessoires moto
 *     - Kits pièces de rechange d'origine
 *     - Outils spéciaux KTM
 *   Niveau 2 = Cat 2 distinctes sous chaque racine ; niveau 3 = Cat 3 sous leur Cat 2.
 *   Lignes à Cat 2 vide (archives) : restent sous la racine, sans sous-catégorie.
 *
 * Résolution par les ASSOCIATIONS PRODUITS (pas par simple nom) : pour chaque nœud
 * du CSV, on cherche la catégorie à laquelle les produits de ce chemin sont réellement
 * rattachés. Cela évite de confondre deux catégories homonymes et détecte les nœuds
 * manquants ou fusionnés — qui sont SIGNALÉS, jamais tranchés d'office.
 *
 * Ne touche QUE les catégories résolues depuis le CSV. N'effleure ni PowerWear, ni
 * l'arbre existant, ni une seule association produit-catégorie. Idempotent.
 *
 * Dry-run par défaut (n'écrit rien). `--apply` pour appliquer (après feu vert).
 *
 * Usage (préprod, SSH) :
 *   php scripts/cli/rebuild_accessoires_tree.php scripts/cli/echantillon.csv
 *   php scripts/cli/rebuild_accessoires_tree.php scripts/cli/echantillon.csv --apply
 */

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI uniquement.\n"); exit(1); }

$apply = in_array('--apply', $argv, true);
$args  = array_values(array_filter(array_slice($argv, 1), fn($a) => strpos($a, '--') !== 0));
$csv   = $args[0] ?? null;
if (!$csv || !is_file($csv)) { fwrite(STDERR, "CSV introuvable : " . ($csv ?: '(aucun)') . "\n"); exit(1); }

$dir = __DIR__; $configPath = null;
for ($i = 0; $i < 15; ++$i) {
    $c = $dir . '/config/config.inc.php';
    if (is_file($c)) { $configPath = $c; break; }
    $p = dirname($dir); if ($p === $dir) break; $dir = $p;
}
if (!$configPath) { fwrite(STDERR, "config.inc.php introuvable.\n"); exit(1); }
define('_PS_ADMIN_DIR_', dirname($configPath) . '/admin');
require_once $configPath;

$db   = Db::getInstance();
$pref = _DB_PREFIX_;
$idLang = (int) Configuration::get('PS_LANG_DEFAULT');
$HOME = (int) Configuration::get('PS_HOME_CATEGORY') ?: 2;

$ROOTS_ORDER = ['Accessoires moto', "Kits pièces de rechange d'origine", 'Outils spéciaux KTM'];
$LAST_FAMILY = 'Extensions logicielles'; // placé en dernier sous « Accessoires moto »

// ── 1. Lecture CSV ──────────────────────────────────────────────────────────
$fh = fopen($csv, 'r');
$bom = fread($fh, 3); if ($bom !== "\xEF\xBB\xBF") rewind($fh);
$header = fgetcsv($fh, 0, ';');
$ci = array_flip($header);
$need = ['Référence produit', 'Main category', 'Cat 2', 'Cat 3'];
foreach ($need as $n) if (!isset($ci[$n])) { fwrite(STDERR, "Colonne manquante : $n\n"); exit(1); }

$rows = [];
while (($r = fgetcsv($fh, 0, ';')) !== false) {
    $rows[] = [
        'ref'  => trim((string)($r[$ci['Référence produit']] ?? '')),
        'main' => trim((string)($r[$ci['Main category']] ?? '')),
        'cat2' => trim((string)($r[$ci['Cat 2']] ?? '')),
        'cat3' => trim((string)($r[$ci['Cat 3']] ?? '')),
    ];
}
fclose($fh);

// Arbre-cible + comptes CSV par nœud
$tree = [];          // root => [cat2 => [cat3 => true]]
$csvCount = ['root' => [], 'cat2' => [], 'cat3' => []];
foreach ($rows as $row) {
    $m = $row['main']; if ($m === '') continue;
    $tree[$m] = $tree[$m] ?? [];
    $csvCount['root'][$m] = ($csvCount['root'][$m] ?? 0) + 1;
    if ($row['cat2'] !== '') {
        $tree[$m][$row['cat2']] = $tree[$m][$row['cat2']] ?? [];
        $csvCount['cat2']["$m\x1f{$row['cat2']}"] = ($csvCount['cat2']["$m\x1f{$row['cat2']}"] ?? 0) + 1;
        if ($row['cat3'] !== '') {
            $tree[$m][$row['cat2']][$row['cat3']] = true;
            $csvCount['cat3']["$m\x1f{$row['cat2']}\x1f{$row['cat3']}"] = ($csvCount['cat3']["$m\x1f{$row['cat2']}\x1f{$row['cat3']}"] ?? 0) + 1;
        }
    }
}

// ── 2. Associations produits → (nom => id) par référence ────────────────────
$refs = array_values(array_unique(array_filter(array_map(fn($r) => $r['ref'], $rows))));
$assoc = []; // ref => [name => [ids]]
foreach (array_chunk($refs, 200) as $chunk) {
    $in = implode(',', array_map(fn($x) => '"' . pSQL($x) . '"', $chunk));
    $res = $db->executeS(
        "SELECT p.reference ref, cl.id_category id, cl.name name
           FROM {$pref}product p
           JOIN {$pref}category_product cp ON cp.id_product = p.id_product
           JOIN {$pref}category_lang cl ON cl.id_category = cp.id_category AND cl.id_lang = $idLang
          WHERE p.reference IN ($in)"
    ) ?: [];
    foreach ($res as $x) {
        $assoc[$x['ref']][$x['name']][(int)$x['id']] = true;
    }
}

// Résolveur : nœud (chemin) → ensemble d'ids rattachés dont le nom == libellé du nœud
function resolveNode($rows, $assoc, $level, $root, $cat2, $cat3) {
    $label = $level === 'root' ? $root : ($level === 'cat2' ? $cat2 : $cat3);
    $ids = [];
    foreach ($rows as $r) {
        if ($r['main'] !== $root) continue;
        if ($level !== 'root' && $r['cat2'] !== $cat2) continue;
        if ($level === 'cat3' && $r['cat3'] !== $cat3) continue;
        $map = $assoc[$r['ref']] ?? [];
        if (isset($map[$label])) foreach ($map[$label] as $id => $_) $ids[$id] = true;
    }
    return array_keys($ids);
}

/**
 * Classe les candidats d'un nœud : on ne déplace QUE les catégories créées à plat
 * par l'import (parent actuel = Accueil). Une catégorie homonyme déjà rangée ailleurs
 * dans l'arbre (PowerParts, etc.) n'est JAMAIS déplacée : l'import a fusionné le chemin
 * dans une catégorie existante → on le signale.
 * Retourne ['flat'=>[ids parent==HOME], 'ext'=>[[id,parent,nprod]...]].
 */
function classifyCandidates($db, $pref, $idLang, $HOME, array $ids) {
    $flat = []; $ext = [];
    foreach ($ids as $id) {
        $info = catInfo($db, $pref, $idLang, $id);
        if ((int)$info['id_parent'] === $HOME || (int)$info['id_parent'] === 0) {
            $flat[] = (int)$id;
        } else {
            $ext[] = ['id'=>(int)$id, 'parent'=>(int)$info['id_parent'], 'pnm'=>$info['pnm'], 'nprod'=>(int)$info['nprod']];
        }
    }
    return ['flat'=>$flat, 'ext'=>$ext];
}

// Infos catégorie
function catInfo($db, $pref, $idLang, $id) {
    $r = $db->getRow("SELECT c.id_category, c.id_parent, c.level_depth,
        (SELECT name FROM {$pref}category_lang WHERE id_category=c.id_category AND id_lang=$idLang) nm,
        (SELECT name FROM {$pref}category_lang WHERE id_category=c.id_parent AND id_lang=$idLang) pnm
        FROM {$pref}category c WHERE c.id_category=" . (int)$id);
    $r['nprod'] = (int)$db->getValue("SELECT COUNT(*) FROM {$pref}category_product WHERE id_category=" . (int)$id);
    return $r;
}

// ── 3. Résolution + plan ────────────────────────────────────────────────────
// Statuts : OK (déjà bon parent), MOVE (catégorie à plat à déplacer),
//           MERGED (fusionné dans une catégorie existante — NON déplacé, signalé),
//           MISSING (aucune catégorie — à créer), AMBIG (plusieurs cat. à plat).
$plan = [];
$issues = [];     // points à valider (fusion / manquant / ambigu)
$rootIds = [];

echo ($apply ? "=== MODE APPLICATION ===" : "=== DRY-RUN (aucune écriture) ===") . "\n";
echo "CSV : $csv  |  Home id=$HOME\n\n";

// Racines
foreach ($ROOTS_ORDER as $root) {
    if (!isset($tree[$root])) { $issues[] = "Racine absente du CSV : '$root'"; continue; }
    $cl = classifyCandidates($db, $pref, $idLang, $HOME, resolveNode($rows, $assoc, 'root', $root, '', ''));
    if (!$cl['flat']) { $issues[] = "RACINE non trouvée à plat : '$root'"; continue; }
    $rid = $cl['flat'][0]; $rootIds[$root] = $rid;
    $info = catInfo($db, $pref, $idLang, $rid);
    $plan[] = ['id'=>$rid,'label'=>$root,'level'=>1,'status'=>((int)$info['id_parent']===$HOME?'OK':'MOVE'),
               'cur'=>(int)$info['id_parent'],'tgt'=>$HOME,'nprod'=>(int)$info['nprod'],'csv'=>$csvCount['root'][$root]??0,'note'=>''];
}

// Familles + sous-catégories
foreach ($ROOTS_ORDER as $root) {
    if (!isset($rootIds[$root])) continue;
    $rid = $rootIds[$root];
    $cat2s = array_keys($tree[$root]); sort($cat2s, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($cat2s as $c2) {
        $csv = $csvCount['cat2']["$root\x1f$c2"] ?? 0;
        $cl = classifyCandidates($db, $pref, $idLang, $HOME, resolveNode($rows, $assoc, 'cat2', $root, $c2, ''));
        $cid = null;
        if (count($cl['flat']) === 1) {
            $cid = $cl['flat'][0];
            $info = catInfo($db, $pref, $idLang, $cid);
            $note = $info['nprod'] != $csv ? "prod base {$info['nprod']}≠csv $csv" : '';
            if ($cl['ext']) $note = trim($note . " ; homonyme existante ignorée id=" . implode(',', array_column($cl['ext'],'id')));
            $plan[] = ['id'=>$cid,'label'=>"$root > $c2",'level'=>2,'status'=>((int)$info['id_parent']===$rid?'OK':'MOVE'),
                       'cur'=>(int)$info['id_parent'],'tgt'=>$rid,'nprod'=>(int)$info['nprod'],'csv'=>$csv,'note'=>$note];
        } elseif (!$cl['flat'] && $cl['ext']) {
            $e = $cl['ext'][0];
            $plan[] = ['id'=>$e['id'],'label'=>"$root > $c2",'level'=>2,'status'=>'MERGED','cur'=>$e['parent'],'tgt'=>null,
                       'nprod'=>$e['nprod'],'csv'=>$csv,'note'=>"fusionné dans catégorie existante '{$e['pnm']}' (id parent {$e['parent']})"];
            $issues[] = "FUSION — famille '$root > $c2' : l'import a rattaché les produits à la catégorie existante id={$e['id']} (sous '{$e['pnm']}', {$e['nprod']} prod). Aucune catégorie à plat créée. NON déplacée.";
        } elseif (count($cl['flat']) > 1) {
            $plan[] = ['id'=>$cl['flat'][0],'label'=>"$root > $c2",'level'=>2,'status'=>'AMBIG','cur'=>0,'tgt'=>null,'nprod'=>0,'csv'=>$csv,'note'=>'ids à plat: '.implode(',',$cl['flat'])];
            $issues[] = "AMBIGU — '$root > $c2' : plusieurs catégories à plat ".implode(',',$cl['flat']);
        } else {
            $issues[] = "MANQUANT — famille '$root > $c2' : aucune catégorie.";
            $plan[] = ['id'=>null,'label'=>"$root > $c2",'level'=>2,'status'=>'MISSING','cur'=>null,'tgt'=>null,'nprod'=>0,'csv'=>$csv,'note'=>'à créer'];
        }

        // sous-catégories : rattachables seulement si la famille est résolue à plat
        $cat3s = array_keys($tree[$root][$c2]); sort($cat3s, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($cat3s as $c3) {
            $csv3 = $csvCount['cat3']["$root\x1f$c2\x1f$c3"] ?? 0;
            $cl3 = classifyCandidates($db, $pref, $idLang, $HOME, resolveNode($rows, $assoc, 'cat3', $root, $c2, $c3));
            if (count($cl3['flat']) === 1) {
                $cid3 = $cl3['flat'][0];
                $info3 = catInfo($db, $pref, $idLang, $cid3);
                if ($cid === null) {
                    $plan[] = ['id'=>$cid3,'label'=>"$root > $c2 > $c3",'level'=>3,'status'=>'ORPHAN','cur'=>(int)$info3['id_parent'],'tgt'=>null,'nprod'=>(int)$info3['nprod'],'csv'=>$csv3,'note'=>'parent famille non résolu → non déplacé'];
                    $issues[] = "PARENT NON RÉSOLU — '$root > $c2 > $c3' (id={$cid3}) : sa famille n'a pas de catégorie à plat. Non déplacé.";
                } else {
                    $note = $info3['nprod'] != $csv3 ? "prod base {$info3['nprod']}≠csv $csv3" : '';
                    if ($cl3['ext']) $note = trim($note." ; homonyme existante ignorée id=".implode(',',array_column($cl3['ext'],'id')));
                    $plan[] = ['id'=>$cid3,'label'=>"$root > $c2 > $c3",'level'=>3,'status'=>((int)$info3['id_parent']===$cid?'OK':'MOVE'),
                               'cur'=>(int)$info3['id_parent'],'tgt'=>$cid,'nprod'=>(int)$info3['nprod'],'csv'=>$csv3,'note'=>$note];
                }
            } elseif (!$cl3['flat'] && $cl3['ext']) {
                $e = $cl3['ext'][0];
                $plan[] = ['id'=>$e['id'],'label'=>"$root > $c2 > $c3",'level'=>3,'status'=>'MERGED','cur'=>$e['parent'],'tgt'=>null,'nprod'=>$e['nprod'],'csv'=>$csv3,'note'=>"fusionné dans existante '{$e['pnm']}' (parent {$e['parent']})"];
                $issues[] = "FUSION — sous-cat '$root > $c2 > $c3' : rattaché à l'existante id={$e['id']} (sous '{$e['pnm']}', {$e['nprod']} prod). NON déplacée.";
            } else {
                $issues[] = "MANQUANT — sous-cat '$root > $c2 > $c3'.";
                $plan[] = ['id'=>null,'label'=>"$root > $c2 > $c3",'level'=>3,'status'=>'MISSING','cur'=>null,'tgt'=>null,'nprod'=>0,'csv'=>$csv3,'note'=>'à créer'];
            }
        }
    }
}

// ── 4. Affichage du plan ────────────────────────────────────────────────────
$cnt = ['OK'=>0,'MOVE'=>0,'MERGED'=>0,'MISSING'=>0,'AMBIG'=>0,'ORPHAN'=>0];
foreach ($plan as $p) {
    $cnt[$p['status']] = ($cnt[$p['status']] ?? 0) + 1;
    $indent = str_repeat('  ', $p['level'] - 1);
    $lbl = explode(' > ', $p['label']); $lbl = end($lbl);
    $tgt = $p['tgt'] === null ? '—' : $p['tgt'];
    $cur = $p['cur'] === null ? '—' : $p['cur'];
    $note = $p['note'] ? '  ⚠ ' . $p['note'] : '';
    printf("%s[%-7s] id=%-5s parent %s→%s  prod=%d (csv %d)  %s%s\n",
        $indent, $p['status'], $p['id'] ?? '—', $cur, $tgt, $p['nprod'], $p['csv'], $lbl, $note);
}

echo "\n--- Résumé ---\n";
printf("À DÉPLACER (MOVE) : %d | déjà OK : %d | FUSION existante : %d | MANQUANT : %d | ORPHAN : %d | AMBIGU : %d\n",
    $cnt['MOVE'],$cnt['OK'],$cnt['MERGED'],$cnt['MISSING'],$cnt['ORPHAN'],$cnt['AMBIG']);
if ($issues) {
    echo "\n⚠ POINTS À VALIDER (" . count($issues) . ") — non traités d'office :\n";
    foreach ($issues as $m) echo "  - $m\n";
}

// ── 5. Application (feu vert requis) ────────────────────────────────────────
if (!$apply) {
    echo "\nDry-run terminé. Relancer avec --apply pour appliquer (après feu vert). Seuls les nœuds MOVE seront déplacés.\n";
    exit(0);
}

echo "\n=== APPLICATION (nœuds MOVE uniquement) ===\n";
foreach ($plan as $p) {
    if ($p['status'] !== 'MOVE' || $p['tgt'] === null) continue;
    $c = new Category((int)$p['id']);
    $c->id_parent = (int)$p['tgt'];
    $c->update();
    echo "  déplacé id={$p['id']} → parent {$p['tgt']}  ({$p['label']})\n";
}
Category::regenerateEntireNtree();
echo "Arbre nleft/nright régénéré.\n";

// Positions : racines dans l'ordre fixé ; familles alpha (Extensions logicielles en dernier) ; sous-cats alpha.
foreach ($ROOTS_ORDER as $pos => $root) {
    if (!isset($rootIds[$root])) continue;
    $c = new Category($rootIds[$root]); $c->position = $pos; $c->update();
}
foreach ($ROOTS_ORDER as $root) {
    if (!isset($rootIds[$root])) continue;
    $cat2s = array_keys($tree[$root]);
    sort($cat2s, SORT_NATURAL | SORT_FLAG_CASE);
    if ($root === 'Accessoires moto' && in_array($LAST_FAMILY, $cat2s, true)) {
        $cat2s = array_values(array_filter($cat2s, fn($x) => $x !== $LAST_FAMILY));
        $cat2s[] = $LAST_FAMILY;
    }
    foreach ($cat2s as $pos => $c2) {
        $cl = classifyCandidates($db, $pref, $idLang, $HOME, resolveNode($rows, $assoc, 'cat2', $root, $c2, ''));
        if (count($cl['flat']) === 1) { $c = new Category($cl['flat'][0]); $c->position = $pos; $c->update(); }
    }
}
echo "Positions appliquées.\n";

// Cache
if (method_exists('Category', 'regenerateEntireNtree')) { /* déjà fait */ }
Tools::clearSmartyCache();
Tools::clearXMLCache();
if (file_exists(_PS_ROOT_DIR_ . '/var/cache/prod')) {
    exec('rm -rf ' . escapeshellarg(_PS_ROOT_DIR_ . '/var/cache/prod') . '/*');
}
echo "Cache vidé.\n";
echo "Terminé.\n";
