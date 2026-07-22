<?php
/**
 * Megaservice — Références remplacées / remplaçantes (catalogues OEM Pierer).
 *
 * Les catalogues KTM / Husqvarna / GASGAS remplacent en permanence des
 * références : une pièce sortie du catalogue est remplacée soit par une
 * référence unique (1:1), soit par un ensemble de références (set 1:N).
 * Les clients arrivent quasi toujours avec l'ANCIENNE référence : le site doit
 * la reconnaître, informer du remplacement et router vers la/les réf actives.
 *
 * Spec : docs/SPEC_module_remplacement_MSS.md
 *
 * Choix d'architecture (mesurés sur les 6 fichiers constructeur réels) :
 *  - La référence constructeur est la CLÉ, jamais l'id PrestaShop : un
 *    remplaçant peut ne pas encore exister au catalogue. Résolution réf →
 *    id_product à l'exécution.
 *  - `sales_orga` est informatif et HORS clé unique : sur 4 503 références
 *    présentes dans plusieurs organisations de vente, seules 2 ont des cibles
 *    réellement divergentes → la fusion produit le sur-ensemble, comportement
 *    souhaitable. (Le reste n'était que des doublons inter/intra-fichier.)
 *  - Pas de pack natif PrestaShop pour les sets : ça créerait des produits à
 *    référence inventée, invisibles de G8 (prix/stock non synchronisés, lignes
 *    de commande non rapprochables en magasin).
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/MsReplacement.php';
require_once __DIR__ . '/classes/ChainResolver.php';
require_once __DIR__ . '/classes/CsvImporter.php';

class Megaservice_replacement extends Module
{
    public function __construct()
    {
        $this->name          = 'megaservice_replacement';
        $this->tab           = 'administration';
        $this->version       = '1.0.0';
        $this->author        = 'Megaservice';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap     = true;

        parent::__construct();

        $this->displayName = 'Megaservice — Références remplacées / remplaçantes';
        $this->description = 'Import du fichier de remplacement constructeur, résolution des chaînes et affichage du remplacement sur la fiche produit.';
    }

    public function install()
    {
        return parent::install()
            && $this->createTable();
    }

    /**
     * On NE supprime PAS la table à la désinstallation : un import complet
     * représente ~16 000 relations et plusieurs minutes de traitement. Le DROP
     * reste manuel si vraiment souhaité.
     */
    public function uninstall()
    {
        return parent::uninstall();
    }

    /**
     * Table des relations de remplacement.
     *
     * Clé unique (`ref_replaced`, `ref_replacement`) SANS `sales_orga` : elle
     * absorbe naturellement les doublons intra-fichier (216 constatés) et
     * inter-organisations (6 550 constatés) — 22 963 lignes brutes se réduisent
     * à 16 405 relations uniques (après rejet des 12 auto-références).
     */
    private function createTable()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'ms_replacement` (
            `id_replacement`  INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `sales_orga`      VARCHAR(8)  DEFAULT NULL,
            `ref_replaced`    VARCHAR(32) NOT NULL,
            `ref_replacement` VARCHAR(32) NOT NULL,
            `conversion_type` VARCHAR(8)  NOT NULL DEFAULT "replace",
            `quantity`        INT(11) UNSIGNED NOT NULL DEFAULT 1,
            `ref_final`       VARCHAR(32) DEFAULT NULL,
            `final_is_set`    TINYINT(1)  NOT NULL DEFAULT 0,
            `chain_depth`     TINYINT(3) UNSIGNED NOT NULL DEFAULT 1,
            `chain_status`    VARCHAR(16) NOT NULL DEFAULT "ok",
            `date_add`        DATETIME NOT NULL,
            `date_upd`        DATETIME NOT NULL,
            PRIMARY KEY (`id_replacement`),
            UNIQUE KEY `uk_replacement` (`ref_replaced`, `ref_replacement`),
            KEY `idx_ref_replaced` (`ref_replaced`),
            KEY `idx_ref_final` (`ref_final`),
            KEY `idx_chain_status` (`chain_status`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

        return (bool) Db::getInstance()->execute($sql);
    }
}
