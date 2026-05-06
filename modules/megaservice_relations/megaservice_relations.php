<?php
/**
 * Megaservice — Relations produits Powerparts
 * Gère 4 types de relations entre produits :
 *   - mandatory  : pièces obligatoires (à avoir aussi)
 *   - excluded   : pièces exclues (incompatibles)
 *   - recommended: pièces recommandées (suggestion)
 *   - spare      : pièces de rechange (avec quantité recommandée)
 *
 * Une seule table polymorphe `ps_megaservice_product_relation`.
 * Relations unidirectionnelles (A→B n'implique pas B→A).
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/ProductRelationService.php';

class Megaservice_relations extends Module
{
    public function __construct()
    {
        $this->name             = 'megaservice_relations';
        $this->tab              = 'administration';
        $this->version          = '1.0.0';
        $this->author           = 'Megaservice';
        $this->need_instance    = 0;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap        = true;

        parent::__construct();

        $this->displayName = 'Megaservice — Relations produits Powerparts';
        $this->description = 'Gère les liaisons obligatoires / exclues / recommandées / pièces de rechange pour les produits Powerparts.';
    }

    public function install()
    {
        return parent::install()
            && $this->createTable()
            && $this->registerHook('displayAdminProductsExtra')
            && $this->registerHook('displayBackOfficeHeader');
    }

    public function uninstall()
    {
        // On NE supprime PAS la table à l'uninstall pour ne pas perdre les données
        // si l'admin se trompe. La SQL drop reste manuelle si besoin.
        return parent::uninstall();
    }

    public function dropTable()
    {
        return Db::getInstance()->execute(
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'megaservice_product_relation`'
        );
    }

    private function createTable()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'megaservice_product_relation` (
            `id_relation`        INT(11) NOT NULL AUTO_INCREMENT,
            `id_product_source`  INT(11) NOT NULL,
            `id_product_target`  INT(11) NOT NULL,
            `relation_type`      ENUM(\'mandatory\',\'excluded\',\'recommended\',\'spare\') NOT NULL,
            `position`           INT(11) NOT NULL DEFAULT 0,
            `recommended_qty`    INT(11) NOT NULL DEFAULT 1,
            PRIMARY KEY (`id_relation`),
            INDEX `idx_source_type` (`id_product_source`, `relation_type`),
            UNIQUE KEY `u_rel` (`id_product_source`, `id_product_target`, `relation_type`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

        return Db::getInstance()->execute($sql);
    }

    /**
     * Hook page produit BO — Phase 3 : panneau autocomplete + drag-drop.
     */
    public function hookDisplayAdminProductsExtra($params)
    {
        // TODO Phase 3
        return '';
    }

    /**
     * Hook header BO — charge JS/CSS sur la page produit. Phase 3.
     */
    public function hookDisplayBackOfficeHeader()
    {
        // TODO Phase 3
    }
}
