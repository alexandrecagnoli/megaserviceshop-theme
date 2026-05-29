<?php
/**
 * Module megaservice_microfiches — Vues éclatées OEM pour motos KTM/HQV/GASGAS.
 *
 * V0.1.0 — squelette : install/uninstall + création des 4 tables + ObjectModels.
 * Les hooks sont enregistrés à l'install pour éviter une re-install plus tard,
 * les méthodes hookXxx seront implémentées au fil des PRs suivantes.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/MsMoto.php';
require_once __DIR__ . '/classes/MsMicroficheCategorie.php';
require_once __DIR__ . '/classes/MsMicrofiche.php';
require_once __DIR__ . '/classes/MsMicroficheHotspot.php';

class Megaservice_microfiches extends Module
{
    public function __construct()
    {
        $this->name = 'megaservice_microfiches';
        $this->tab = 'front_office_features';
        $this->version = '0.1.0';
        $this->author = 'Megaservice';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '8.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Megaservice Microfiches');
        $this->description = $this->l('Gestion des vues éclatées (microfiches OEM) pour motos KTM/Husqvarna/GASGAS.');
        $this->confirmUninstall = $this->l('Désinstaller le module ? Les données en base sont conservées.');
    }

    public function install(): bool
    {
        return parent::install()
            && $this->installSql()
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayBackOfficeHeader')
            && $this->registerHook('actionProductAdd')
            && $this->registerHook('actionProductUpdate');
    }

    /**
     * On NE drop PAS les tables à l'uninstall — protection anti-perte de données.
     * Pour réellement nettoyer la base, appeler dropTables() explicitement.
     */
    public function uninstall(): bool
    {
        return parent::uninstall();
    }

    private function installSql(): bool
    {
        return $this->runSqlFile(__DIR__ . '/sql/install.sql');
    }

    /**
     * Drop manuel des 4 tables — à appeler explicitement (non câblé à uninstall).
     */
    public function dropTables(): bool
    {
        return $this->runSqlFile(__DIR__ . '/sql/uninstall.sql');
    }

    private function runSqlFile(string $path): bool
    {
        if (!file_exists($path)) {
            return false;
        }

        $sql = (string) file_get_contents($path);
        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);

        foreach (array_filter(array_map('trim', explode(';', $sql))) as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }
}
