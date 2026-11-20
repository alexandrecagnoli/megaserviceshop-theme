<?php
/**
 * Création idempotente des caractéristiques produit « Accessoires moto ».
 * ==============================================
 *
 * Crée trois caractéristiques (Catalogue > Caractéristiques) et leurs valeurs
 * prédéfinies, pour l'import du fichier Accessoires moto du 20/11/2026 :
 *
 *   - Motorisation         : 2 temps, 4 temps
 *   - Matière de la pièce   : Carbone, Aluminium anodisé  (DISTINCTE de « Matière(s) »)
 *   - Type de pièce         : valeurs distinctes de la colonne du CSV passé en argument
 *
 * L'import PrestaShop rapproche par LIBELLÉ : les noms et valeurs sont donc créés
 * exactement tels quels (casse et accents compris).
 *
 * Idempotent : rejouable sans doublon. Une caractéristique ou une valeur de même
 * libellé (lang courante) n'est jamais recréée. Ne touche à AUCUN produit, à
 * aucune autre caractéristique (« Pratique », « Matière(s) » restent intacts), et
 * n'active aucun filtre à facettes.
 *
 * Usage (préprod, en SSH) :
 *   php scripts/cli/create_features_accessoires.php /chemin/import_accessoires.csv
 *   php scripts/cli/create_features_accessoires.php /chemin/import.csv --apply
 *
 * Sans --apply : simulation, n'écrit rien.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ce script s'exécute en ligne de commande uniquement.\n");
    exit(1);
}

$args  = array_values(array_filter(array_slice($argv, 1), function ($a) { return strpos($a, '--') !== 0; }));
$apply = in_array('--apply', $argv, true);
$csv   = $args[0] ?? null;

if (!$csv || !is_file($csv) || !is_readable($csv)) {
    fwrite(STDERR, "CSV introuvable ou illisible : " . ($csv ?: '(aucun)') . "\n");
    fwrite(STDERR, "Usage : php create_features_accessoires.php <import.csv> [--apply]\n");
    exit(1);
}

// Remontée jusqu'à config.inc.php
$dir = __DIR__;
$configPath = null;
for ($i = 0; $i < 15; ++$i) {
    $c = $dir . '/config/config.inc.php';
    if (is_file($c)) { $configPath = $c; break; }
    $p = dirname($dir);
    if ($p === $dir) break;
    $dir = $p;
}
if (!$configPath) {
    fwrite(STDERR, "config/config.inc.php introuvable depuis " . __DIR__ . "\n");
    exit(1);
}
define('_PS_ADMIN_DIR_', dirname($configPath) . '/admin');
require_once $configPath;

$db = Db::getInstance();

// ── Langues actives et boutiques : une caractéristique doit exister pour chaque
//    langue et être associée à chaque boutique, sinon elle n'apparaît pas en BO.
$langs = array_map('intval', array_column(Language::getLanguages(true), 'id_lang'));
$shops = array_map('intval', array_column(Shop::getShops(true, null, true), 0) ?: Shop::getContextListShopID());
if (!$shops) { $shops = [(int) Configuration::get('PS_SHOP_DEFAULT') ?: 1]; }
$refLang = (int) Configuration::get('PS_LANG_DEFAULT') ?: $langs[0];

/**
 * Colonne « Type de pièce » du CSV : valeurs distinctes non vides, triées.
 * Même logique que l'extraction montrée à la validation (trim, dédoublonnage).
 */
function readTypeDePiece($path)
{
    $fh = fopen($path, 'r');
    if (!$fh) return [];
    // BOM UTF-8
    $bom = fread($fh, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($fh);
    $header = fgetcsv($fh, 0, ';');
    $idx = array_search('Type de pièce', $header, true);
    if ($idx === false) {
        fwrite(STDERR, "Colonne « Type de pièce » absente du CSV.\n");
        fclose($fh);
        exit(1);
    }
    $seen = [];
    while (($row = fgetcsv($fh, 0, ';')) !== false) {
        if (!isset($row[$idx])) continue;
        $v = trim((string) $row[$idx]);
        if ($v !== '') $seen[$v] = true;
    }
    fclose($fh);
    $out = array_keys($seen);
    sort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return $out;
}

$FEATURES = [
    'Motorisation'        => ['2 temps', '4 temps'],
    'Matière de la pièce' => ['Carbone', 'Aluminium anodisé'],
    'Type de pièce'       => readTypeDePiece($csv),
];

/** id_feature d'une caractéristique par libellé exact (langue de référence), ou 0. */
function findFeature($db, $name, $refLang)
{
    return (int) $db->getValue(
        'SELECT id_feature FROM `' . _DB_PREFIX_ . 'feature_lang`
          WHERE id_lang = ' . (int) $refLang . ' AND name = "' . pSQL($name) . '"'
    );
}

/** Une valeur prédéfinie existe-t-elle déjà sous cette caractéristique (libellé exact) ? */
function valueExists($db, $idFeature, $value, $refLang)
{
    return (bool) $db->getValue(
        'SELECT fv.id_feature_value
           FROM `' . _DB_PREFIX_ . 'feature_value` fv
           JOIN `' . _DB_PREFIX_ . 'feature_value_lang` fvl
             ON fvl.id_feature_value = fv.id_feature_value AND fvl.id_lang = ' . (int) $refLang . '
          WHERE fv.id_feature = ' . (int) $idFeature . ' AND fv.custom = 0
            AND fvl.value = "' . pSQL($value) . '"'
    );
}

printf("Mode   : %s\n", $apply ? 'APPLICATION' : 'simulation (aucune écriture)');
printf("Langues: %s   Boutiques: %s\n\n", implode(',', $langs), implode(',', $shops));

$summary = [];

foreach ($FEATURES as $name => $values) {
    $values = array_values(array_unique($values));
    $idFeature = findFeature($db, $name, $refLang);
    $featureCreated = false;

    if (!$idFeature) {
        if ($apply) {
            $feature = new Feature();
            $feature->name = [];
            foreach ($langs as $l) { $feature->name[$l] = $name; }
            $feature->add();
            $idFeature = (int) $feature->id;
            // Association boutique explicite (sécurité : selon la version, add()
            // ne peuple pas toujours feature_shop).
            foreach ($shops as $s) {
                $db->execute(
                    'INSERT IGNORE INTO `' . _DB_PREFIX_ . 'feature_shop` (id_feature, id_shop)
                     VALUES (' . (int) $idFeature . ', ' . (int) $s . ')'
                );
            }
        }
        $featureCreated = true;
    }

    $added = 0; $kept = 0;
    foreach ($values as $value) {
        if ($idFeature && valueExists($db, $idFeature, $value, $refLang)) {
            $kept++;
            continue;
        }
        if ($apply && $idFeature) {
            $fv = new FeatureValue();
            $fv->id_feature = $idFeature;
            $fv->custom = 0;
            $fv->value = [];
            foreach ($langs as $l) { $fv->value[$l] = $value; }
            $fv->add();
        }
        $added++;
    }

    $summary[] = [
        'name'    => $name,
        'id'      => $idFeature ?: '(à créer)',
        'created' => $featureCreated,
        'total'   => count($values),
        'added'   => $added,
        'kept'    => $kept,
    ];
}

echo "Résultat :\n";
foreach ($summary as $s) {
    printf(
        "  %-22s id=%-8s %s | valeurs : %d au total, %s %d, déjà présentes %d\n",
        $s['name'],
        (string) $s['id'],
        $s['created'] ? 'CRÉÉE' : 'existait',
        $s['total'],
        $apply ? 'ajoutées' : 'à ajouter',
        $s['added'],
        $s['kept']
    );
}

if (!$apply) {
    echo "\nRelancer avec --apply pour écrire.\n";
}
