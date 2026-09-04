<?php
/**
 * Resynchronisation en masse, en CLI — équivalent du bouton BO, pour l'usage
 * ponctuel (rattrapage du catalogue déjà importé, ou vérification après un
 * import) sans passer par l'interface.
 *
 * Usage (en SSH, sous la racine PrestaShop) :
 *   php modules/megaservice_availability/tests/cli/sync_all.php
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

$r = MsAvailabilitySync::syncAll();

printf("Produits incohérents avant correction : %d\n", $r['incoherents_avant']);
printf("Lignes ps_product corrigées           : %d\n", $r['ps_product_corriges']);
printf("Lignes ps_product_shop corrigées      : %d\n", $r['ps_product_shop_corriges']);
