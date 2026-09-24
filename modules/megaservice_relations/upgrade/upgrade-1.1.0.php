<?php
/**
 * 1.1.0 — relations en attente : table + hooks produit.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_0($module)
{
    require_once dirname(__DIR__) . '/classes/ProductRelationService.php';
    MsProductRelationService::ensurePendingTable();

    return $module->registerHook('actionObjectProductAddAfter')
        && $module->registerHook('actionObjectProductUpdateAfter');
}
