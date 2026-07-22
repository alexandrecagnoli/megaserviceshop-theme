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
require_once __DIR__ . '/classes/ReplacementRepository.php';

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

    /** Répertoire serveur scanné par défaut pour les fichiers constructeur. */
    const DEFAULT_IMPORT_DIR = 'data';

    /**
     * Écran d'import (BO → Modules → Configurer).
     */
    public function getContent()
    {
        $out = '';

        if (Tools::isSubmit('submitMsReplacementImport')) {
            $out .= $this->handleImport();
        }

        return $out . $this->renderImportForm();
    }

    /**
     * Récupère les fichiers (upload prioritaire, sinon scan du répertoire),
     * parse, puis écrit en base.
     */
    private function handleImport()
    {
        $paths = $this->collectUploadedFiles();
        $from  = 'fichiers téléversés';

        if (empty($paths)) {
            $dir   = trim((string) Tools::getValue('import_dir')) ?: (_PS_ROOT_DIR_ . '/' . self::DEFAULT_IMPORT_DIR);
            $paths = glob(rtrim($dir, '/') . '/replacement_articles_*.csv') ?: [];
            $from  = 'répertoire ' . htmlspecialchars($dir);
        }

        if (empty($paths)) {
            return $this->displayError(
                $this->l('Aucun fichier trouvé. Téléversez des CSV ou indiquez un répertoire contenant des fichiers replacement_articles_*.csv.')
            );
        }

        $parsed = MsReplacementCsvImporter::parseFiles($paths);

        if (empty($parsed['rows'])) {
            return $this->displayError($this->l('Aucune ligne exploitable dans les fichiers fournis.'))
                . $this->renderReport($parsed['report'], null, $from);
        }

        $purge  = (bool) Tools::getValue('purge');
        $result = MsReplacementRepository::import($parsed['rows'], ['purge' => $purge]);

        return $this->displayConfirmation($this->l('Import terminé.'))
            . $this->renderReport($parsed['report'], $result, $from);
    }

    /**
     * Déplace les CSV téléversés vers un emplacement temporaire.
     * Seule l'extension .csv est acceptée.
     *
     * @return string[]
     */
    private function collectUploadedFiles()
    {
        if (empty($_FILES['replacement_files']['name'][0])) {
            return [];
        }

        $paths = [];
        foreach ($_FILES['replacement_files']['name'] as $i => $name) {
            if ($_FILES['replacement_files']['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }
            if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'csv') {
                continue;
            }
            $dest = _PS_UPLOAD_DIR_ . 'ms_replacement_' . md5(uniqid('', true)) . '.csv';
            if (move_uploaded_file($_FILES['replacement_files']['tmp_name'][$i], $dest)) {
                $paths[] = $dest;
            }
        }

        return $paths;
    }

    /** Rapport de parsing + rapport d'écriture. */
    private function renderReport(array $parseReport, $importReport, $source)
    {
        $rows = '';
        foreach ($parseReport as $k => $v) {
            if (is_array($v)) {
                $v = empty($v) ? '—' : htmlspecialchars(json_encode($v));
            }
            $rows .= '<tr><td>' . htmlspecialchars((string) $k) . '</td><td><strong>' . htmlspecialchars((string) $v) . '</strong></td></tr>';
        }

        $importRows = '';
        if (is_array($importReport)) {
            foreach ($importReport as $k => $v) {
                if (is_bool($v)) {
                    $v = $v ? 'oui' : 'non';
                }
                $importRows .= '<tr><td>' . htmlspecialchars((string) $k) . '</td><td><strong>' . htmlspecialchars((string) $v) . '</strong></td></tr>';
            }
        }

        return '<div class="panel">
            <h3><i class="icon-list"></i> ' . $this->l('Rapport d\'import') . '</h3>
            <p class="text-muted">' . $this->l('Source :') . ' ' . $source . '</p>
            <div class="row">
                <div class="col-lg-6">
                    <h4>' . $this->l('Lecture des fichiers') . '</h4>
                    <table class="table"><tbody>' . $rows . '</tbody></table>
                </div>
                <div class="col-lg-6">
                    <h4>' . $this->l('Écriture en base') . '</h4>
                    <table class="table"><tbody>' . ($importRows ?: '<tr><td>' . $this->l('non exécutée') . '</td></tr>') . '</tbody></table>
                </div>
            </div>
        </div>';
    }

    private function renderImportForm()
    {
        $defaultDir = _PS_ROOT_DIR_ . '/' . self::DEFAULT_IMPORT_DIR;
        $count      = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'ms_replacement`');

        return '<div class="panel">
            <h3><i class="icon-refresh"></i> ' . $this->l('Import des références remplacées') . '</h3>
            <p>' . sprintf($this->l('Relations actuellement en base : %d'), $count) . '</p>

            <form method="post" enctype="multipart/form-data" class="form-horizontal">
                <div class="form-group">
                    <label class="control-label col-lg-3">' . $this->l('Fichiers CSV') . '</label>
                    <div class="col-lg-9">
                        <input type="file" name="replacement_files[]" multiple accept=".csv">
                        <p class="help-block">' . $this->l('Fichiers replacement_articles_*.csv. Laissez vide pour scanner le répertoire ci-dessous.') . '</p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="control-label col-lg-3">' . $this->l('Répertoire serveur') . '</label>
                    <div class="col-lg-9">
                        <input type="text" name="import_dir" class="form-control" value="' . htmlspecialchars($defaultDir) . '">
                        <p class="help-block">' . $this->l('Scanné uniquement si aucun fichier n\'est téléversé.') . '</p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="control-label col-lg-3">' . $this->l('Mode photographique') . '</label>
                    <div class="col-lg-9">
                        <label style="font-weight:normal">
                            <input type="checkbox" name="purge" value="1">
                            ' . $this->l('Supprimer les relations absentes des fichiers importés') . '
                        </label>
                        <p class="help-block" style="color:#c00">
                            <strong>' . $this->l('Attention :') . '</strong>
                            ' . $this->l('à n\'activer que si vous importez la TOTALITÉ des fichiers constructeur en une seule fois. Avec un seul fichier, cela supprimerait les relations des autres organisations de vente.') . '
                        </p>
                    </div>
                </div>

                <div class="panel-footer">
                    <button type="submit" name="submitMsReplacementImport" class="btn btn-default pull-right">
                        <i class="process-icon-refresh"></i> ' . $this->l('Lancer l\'import') . '
                    </button>
                </div>
            </form>
        </div>';
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
