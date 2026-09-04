<?php
/**
 * Diagnostic disponibilité / bouton d'achat — reproduit le calcul réel de
 * PrestaShop pour un produit donné, sans hypothèse : dump brut de toutes les
 * colonnes ps_product / ps_product_shop / ps_stock_available concernées, PUIS
 * reconstruction via le même chemin (ProductAssembler + Presenter) que celui
 * emprunté par le front (cf. override/controllers/front/ProductController.php
 * ::presentProductsByIds(), déjà en prod pour les tabs Powerparts/suggestions —
 * on réutilise EXACTEMENT ce chemin validé plutôt que d'en deviner un nouveau).
 *
 * Usage (en SSH, à la racine du site ou n'importe où en dessous) :
 *   php scripts/cli/debug_product_availability.php 94479
 *
 * Ne touche à rien, ne modifie aucune donnée. Aucun risque HTTP — jamais
 * routé par le front, n'existe qu'en exécution CLI directe.
 */

// ── Bootstrap PrestaShop — remonte jusqu'à trouver config/config.inc.php ──
$dir = __DIR__;
$configPath = null;
for ($i = 0; $i < 15; ++$i) {
    $candidate = $dir . '/config/config.inc.php';
    if (is_file($candidate)) {
        $configPath = $candidate;
        break;
    }
    $parent = dirname($dir);
    if ($parent === $dir) {
        break;
    }
    $dir = $parent;
}
if (!$configPath) {
    fwrite(STDERR, "config/config.inc.php introuvable en remontant depuis " . __DIR__ . "\n");
    fwrite(STDERR, "Déplacer ce script sous la racine PrestaShop, ou ajuster le nombre de niveaux remontés.\n");
    exit(1);
}
define('_PS_ADMIN_DIR_', dirname($configPath) . '/admin'); // évite un warning de certaines versions
require_once $configPath;

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Ce script est réservé au CLI (SSH), pas au front HTTP.\n");
    exit(1);
}

$idProduct = isset($argv[1]) ? (int) $argv[1] : 0;
if (!$idProduct) {
    fwrite(STDERR, "Usage: php " . basename(__FILE__) . " <id_product>\n");
    exit(1);
}

function section($title)
{
    echo "\n" . str_repeat('=', 78) . "\n" . $title . "\n" . str_repeat('=', 78) . "\n";
}

$context  = Context::getContext();
$idLang   = (int) $context->language->id;
$idShop   = (int) $context->shop->id;

// ── 1. Config boutique ──────────────────────────────────────────────────
section('1. Configuration boutique');
printf("PS_ORDER_OUT_OF_STOCK = %s\n", var_export(Configuration::get('PS_ORDER_OUT_OF_STOCK'), true));
printf("PS_STOCK_MANAGEMENT   = %s\n", var_export(Configuration::get('PS_STOCK_MANAGEMENT'), true));
printf("PS_CATALOG_MODE       = %s\n", var_export(Configuration::get('PS_CATALOG_MODE'), true));
printf("id_shop (contexte)    = %d\n", $idShop);

// ── 2. Toutes les colonnes brutes de ps_product ─────────────────────────
section('2. ps_product — TOUTES colonnes (brut SQL, aucun filtre)');
$row = Db::getInstance()->getRow(
    'SELECT * FROM `' . _DB_PREFIX_ . 'product` WHERE id_product = ' . $idProduct
);
if (!$row) {
    fwrite(STDERR, "Produit $idProduct introuvable dans ps_product.\n");
    exit(1);
}
foreach ($row as $k => $v) {
    printf("  %-30s = %s\n", $k, var_export($v, true));
}

// ── 3. Toutes les colonnes brutes de ps_product_shop ────────────────────
// Sépare volontairement de ps_product : en multi-boutique certains champs
// (dont available_for_order) peuvent diverger entre les deux tables selon
// la façon dont l'import a écrit.
section('3. ps_product_shop — TOUTES colonnes (brut SQL, pour id_shop=' . $idShop . ')');
$rowShop = Db::getInstance()->getRow(
    'SELECT * FROM `' . _DB_PREFIX_ . 'product_shop`
     WHERE id_product = ' . $idProduct . ' AND id_shop = ' . $idShop
);
if (!$rowShop) {
    echo "  AUCUNE LIGNE pour ce produit + ce shop dans ps_product_shop.\n";
} else {
    foreach ($rowShop as $k => $v) {
        printf("  %-30s = %s\n", $k, var_export($v, true));
    }
}

// Alerte explicite si divergence entre les deux tables sur les colonnes clé.
$keysToCompare = ['available_for_order', 'available_date', 'active', 'visibility', 'show_price'];
$diffs = [];
foreach ($keysToCompare as $k) {
    if (isset($row[$k], $rowShop[$k]) && (string) $row[$k] !== (string) $rowShop[$k]) {
        $diffs[$k] = [$row[$k], $rowShop[$k]];
    }
}
if ($diffs) {
    echo "\n  ⚠ DIVERGENCE ps_product vs ps_product_shop :\n";
    foreach ($diffs as $k => list($a, $b)) {
        printf("    %-25s ps_product=%s  |  ps_product_shop=%s\n", $k, var_export($a, true), var_export($b, true));
    }
} else {
    echo "\n  (aucune divergence sur les colonnes usuelles entre ps_product et ps_product_shop)\n";
}

// ── 4. ps_stock_available — toutes colonnes ─────────────────────────────
section('4. ps_stock_available — TOUTES colonnes, toutes déclinaisons');
$stockRows = Db::getInstance()->executeS(
    'SELECT * FROM `' . _DB_PREFIX_ . 'stock_available` WHERE id_product = ' . $idProduct
);
foreach ($stockRows as $sr) {
    foreach ($sr as $k => $v) {
        printf("  %-30s = %s\n", $k, var_export($v, true));
    }
    echo "  ---\n";
}

// ── 5. Objet Product tel que chargé par PS (full=true, contexte réel) ──
section('5. new Product($id, true, $idLang, $idShop) — propriétés pertinentes');
$product = new Product($idProduct, true, $idLang, $idShop);
$fieldsOfInterest = [
    'id', 'active', 'available_for_order', 'show_price', 'visibility',
    'id_shop_default', 'quantity', 'out_of_stock', 'available_date',
    'available_later', 'available_now', 'condition', 'state',
];
foreach ($fieldsOfInterest as $f) {
    printf("  %-24s = %s\n", $f, var_export(isset($product->$f) ? $product->$f : '(absent)', true));
}

// ── 6. Reconstruction du tableau EXACTEMENT comme le front (listing) ───
// Même chemin que override/controllers/front/ProductController.php
// ::presentProductsByIds() — déjà validé fonctionnel en prod (tabs
// Powerparts / suggestions), donc fiable pour reproduire le calcul réel.
section('6. ProductAssembler + ProductListingPresenter — tableau complet retourné');
try {
    $assembler = new \ProductAssembler($context);
    $factory   = new \ProductPresenterFactory($context);
    $settings  = $factory->getPresentationSettings();
    $presenter = new \PrestaShop\PrestaShop\Adapter\Presenter\Product\ProductListingPresenter(
        new \PrestaShop\PrestaShop\Adapter\Image\ImageRetriever($context->link),
        $context->link,
        new \PrestaShop\PrestaShop\Adapter\Product\PriceFormatter(),
        new \PrestaShop\PrestaShop\Adapter\Product\ProductColorsRetriever(),
        $context->getTranslator()
    );
    $raw = $assembler->assembleProduct(['id_product' => $idProduct]);
    echo "-- \$raw (sortie de assembleProduct, AVANT présentation) --\n";
    foreach (['id_product', 'quantity', 'out_of_stock', 'available_for_order', 'available_date', 'active', 'show_price'] as $k) {
        printf("  %-24s = %s\n", $k, isset($raw[$k]) ? var_export($raw[$k], true) : '(absent du tableau brut)');
    }

    $presented = $presenter->present($settings, $raw, $context->language);
    echo "\n-- \$presented (sortie de Presenter::present(), CE QUE LE TEMPLATE LIT) --\n";
    foreach (['add_to_cart_url', 'available_for_order', 'show_price', 'quantity', 'availability', 'availability_message', 'available_date', 'allow_oosp'] as $k) {
        printf("  %-24s = %s\n", $k, isset($presented[$k]) ? var_export($presented[$k], true) : '(absent du tableau présenté)');
    }

    echo "\n-- Test isole : meme produit avec show_price force a 1 --\n";
    $raw2 = $raw;
    $raw2['show_price'] = 1;
    $presented2 = $presenter->present($settings, $raw2, $context->language);
    printf("  add_to_cart_url (show_price=1) = %s\n", isset($presented2['add_to_cart_url']) ? var_export($presented2['add_to_cart_url'], true) : '(absent)');
} catch (\Throwable $e) {
    echo "ERREUR pendant la reconstruction : " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

// ── 7. Répartition de show_price sur tout le catalogue ─────────────────
section('7. Répartition ps_product.show_price sur tout le catalogue');
$dist = Db::getInstance()->executeS(
    'SELECT show_price, COUNT(*) AS n FROM `' . _DB_PREFIX_ . 'product` GROUP BY show_price'
);
foreach ($dist as $d) {
    printf("  show_price = %-4s -> %s produits\n", $d['show_price'], $d['n']);
}

echo "\n\nFIN DU DIAGNOSTIC — coller la sortie complète telle quelle.\n";
