<?php
/**
 * 1.0.0 → 1.1.0 — `sales_orga` entre dans la clé unique.
 *
 * Motif : permettre un import PHOTOGRAPHIQUE PAR ORGANISATION. Avec l'ancienne
 * clé, réimporter le seul fichier KTM en mode photographique aurait supprimé
 * les relations Husqvarna et GasGas.
 *
 * ⚠️ Les lignes déjà en base ont été dédoublonnées SANS l'orga : elles restent
 * valides (elles sont un sous-ensemble), mais les relations mutualisées entre
 * marques manquent pour les orgas absorbées. Un RÉ-IMPORT des fichiers est
 * nécessaire pour retrouver un jeu complet.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_0($module)
{
    $db    = Db::getInstance();
    $table = _DB_PREFIX_ . 'ms_replacement';

    // DROP conditionnel : l'index n'existe pas sur une base fraîchement créée
    // par la 1.1.0 (createTable pose déjà la bonne clé).
    $existing = $db->executeS('SHOW INDEX FROM `' . $table . '` WHERE Key_name = "uk_replacement"');
    if (!empty($existing)) {
        $db->execute('ALTER TABLE `' . $table . '` DROP INDEX `uk_replacement`');
    }

    $db->execute(
        'ALTER TABLE `' . $table . '`
         ADD UNIQUE KEY `uk_replacement` (`sales_orga`, `ref_replaced`, `ref_replacement`)'
    );

    return true;
}
