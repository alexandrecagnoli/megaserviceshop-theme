<?php
/**
 * Test d'intégration de MsAvailabilitySync::syncProduct(), en conditions
 * réelles (bootstrap PS complet + vraies requêtes SQL) — pas de logique pure
 * à isoler ici, contrairement à MountabilityImporter::normalizeRow().
 *
 * Tout le test tourne dans une transaction annulée (ROLLBACK) en fin de
 * script, succès ou échec : rien n'est jamais persisté en base.
 *
 * Usage (en SSH, sous la racine PrestaShop) :
 *   php modules/megaservice_availability/tests/cli/test_sync.php
 */

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
    exit(1);
}
require_once $configPath;

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Ce script est réservé au CLI (SSH).\n");
    exit(1);
}

require_once _PS_MODULE_DIR_ . 'megaservice_availability/classes/MsAvailabilitySync.php';

$db = Db::getInstance();
$fail = 0;
$total = 0;

function check($label, $actual, $expected)
{
    global $fail, $total;
    ++$total;
    if ($actual === $expected) {
        printf("  ✓ %s\n", $label);
        return;
    }
    ++$fail;
    printf("  ✗ %s\n      attendu : %s\n      obtenu  : %s\n", $label, var_export($expected, true), var_export($actual, true));
}

$db->execute('START TRANSACTION');

try {
    // Produit de test minimal — colonnes obligatoires seulement.
    $db->execute(
        "INSERT INTO `" . _DB_PREFIX_ . "product`
         (id_shop_default, available_for_order, show_price, active, date_add, date_upd)
         VALUES (1, 1, 0, 1, NOW(), NOW())"
    );
    $idProduct = (int) $db->Insert_ID();
    $db->execute(
        "INSERT INTO `" . _DB_PREFIX_ . "product_shop`
         (id_product, id_shop, available_for_order, show_price, active, date_add, date_upd)
         VALUES ($idProduct, 1, 1, 0, 1, NOW(), NOW())"
    );

    echo "\n1. Produit commandable (available_for_order=1), show_price désynchronisé (0)\n";
    $changed = MsAvailabilitySync::syncProduct($idProduct);
    check('syncProduct signale un changement', $changed, true);
    check('ps_product.show_price corrigé à 1', (int) $db->getValue("SELECT show_price FROM `" . _DB_PREFIX_ . "product` WHERE id_product = $idProduct"), 1);
    check('ps_product_shop.show_price corrigé à 1', (int) $db->getValue("SELECT show_price FROM `" . _DB_PREFIX_ . "product_shop` WHERE id_product = $idProduct"), 1);

    echo "\n2. Rappel sur un produit déjà cohérent — aucun changement signalé\n";
    $changed2 = MsAvailabilitySync::syncProduct($idProduct);
    check('syncProduct ne signale rien', $changed2, false);

    echo "\n3. Produit non commandable (available_for_order=0), show_price resté à 1 (résidu)\n";
    $db->execute("UPDATE `" . _DB_PREFIX_ . "product` SET available_for_order = 0 WHERE id_product = $idProduct");
    $db->execute("UPDATE `" . _DB_PREFIX_ . "product_shop` SET available_for_order = 0 WHERE id_product = $idProduct");
    $changed3 = MsAvailabilitySync::syncProduct($idProduct);
    check('syncProduct signale un changement', $changed3, true);
    check('ps_product.show_price repasse à 0', (int) $db->getValue("SELECT show_price FROM `" . _DB_PREFIX_ . "product` WHERE id_product = $idProduct"), 0);
    check('ps_product_shop.show_price repasse à 0', (int) $db->getValue("SELECT show_price FROM `" . _DB_PREFIX_ . "product_shop` WHERE id_product = $idProduct"), 0);

    echo "\n4. id_product invalide — pas d'erreur, pas de changement\n";
    check('id_product=0 renvoie false', MsAvailabilitySync::syncProduct(0), false);
    check('id_product inexistant renvoie false', MsAvailabilitySync::syncProduct(999999999), false);
} finally {
    $db->execute('ROLLBACK');
    echo "\n(transaction annulée — rien de persisté en base)\n";
}

printf("\n%s  %d/%d assertions OK\n\n", $fail === 0 ? '✅ SUCCÈS' : '❌ ÉCHEC', $total - $fail, $total);
exit($fail === 0 ? 0 : 1);
