<?php
/**
 * Pose les relations Powerparts en attente dont les produits existent désormais.
 *
 * Filet de sécurité : le module megaservice_relations le fait déjà à chaque
 * import CSV et à chaque création/mise à jour de produit passant par les hooks
 * PS. Ce script couvre les produits écrits directement en SQL (ex. import
 * catalogue) — à lancer après un import, ou en cron.
 *
 * Usage (en SSH) :
 *   /opt/plesk/php/8.2/bin/php scripts/cli/resolve_pending_relations.php
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
define('_PS_ADMIN_DIR_', dirname($configPath) . '/admin');
require_once $configPath;
require_once _PS_MODULE_DIR_ . 'megaservice_relations/classes/ProductRelationService.php';

$res = MsProductRelationService::resolvePending();
echo 'Posées : ' . $res['resolved'] . ' — encore en attente : ' . $res['remaining'] . "\n";
