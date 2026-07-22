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
 *  - `sales_orga` fait partie de la clé unique, pour permettre un import
 *    PHOTOGRAPHIQUE PAR ORGANISATION : réimporter le fichier KTM ne doit pas
 *    effacer les relations Husqvarna ou GasGas. En contrepartie une relation
 *    mutualisée entre marques existe en plusieurs exemplaires — le FRONT les
 *    regroupe à la lecture, car il ignore l'orga (la réf constructeur est
 *    globalement unique chez Pierer). Mesuré : 4 503 réfs sont multi-orga,
 *    et seules 2 ont des cibles réellement divergentes.
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
require_once __DIR__ . '/classes/FrontBlock.php';

class Megaservice_replacement extends Module
{
    public function __construct()
    {
        $this->name          = 'megaservice_replacement';
        $this->tab           = 'administration';
        $this->version       = '1.1.0';
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
            && $this->createTable()
            && $this->registerHook('displayBackOfficeHeader')
            && $this->registerHook('header')
            && $this->registerHook('displayProductAdditionalInfo');
    }

    /**
     * Bloc de CONTRÔLE sur la fiche produit BO : « cette référence est remplacée
     * par… » / « …remplace ces anciennes références ».
     *
     * ⚠️ LECTURE SEULE, volontairement. La table est réécrite intégralement à
     * chaque import du fichier constructeur — un champ éditable ici verrait
     * toute saisie manuelle écrasée au ré-import suivant, sans aucune trace.
     *
     * Le bloc n'est injecté QUE s'il y a quelque chose à dire : sinon ce serait
     * du bruit sur des dizaines de milliers de fiches.
     */
    public function hookDisplayBackOfficeHeader()
    {
        $idProduct = $this->currentProductId();
        if (!$idProduct) {
            return '';
        }

        $product = new Product($idProduct);
        if (!Validate::isLoadedObject($product) || $product->reference === '') {
            return '';
        }

        $data = MsReplacementRepository::forReference($product->reference);
        if ($data === null) {
            return '';
        }

        $this->context->controller->addCSS($this->_path . 'views/css/admin-product-replacement.css');
        $this->context->controller->addJS($this->_path . 'views/js/admin-product-replacement.js');

        return '<script>window.MS_REPLACEMENT_PANEL = ' . json_encode($data) . ';</script>';
    }

    /** @var array<string,mixed>|null|false Cache du bloc front (false = pas encore calculé). */
    private $frontBlockCache = false;

    /**
     * Bloc de remplacement calculé une seule fois par requête : le hook header
     * (assets) et le hook d'affichage le demandent tous les deux.
     *
     * @return array<string,mixed>|null
     */
    private function currentFrontBlock($reference = null)
    {
        if ($this->frontBlockCache !== false) {
            return $this->frontBlockCache;
        }

        if ($reference === null) {
            $idProduct = (int) Tools::getValue('id_product');
            if (!$idProduct) {
                return $this->frontBlockCache = null;
            }
            $product = new Product($idProduct);
            if (!Validate::isLoadedObject($product)) {
                return $this->frontBlockCache = null;
            }
            $reference = $product->reference;
        }

        if ((string) $reference === '') {
            return $this->frontBlockCache = null;
        }

        return $this->frontBlockCache = MsReplacementFrontBlock::build($reference, $this->context);
    }

    /**
     * Assets front — chargés uniquement sur une fiche produit effectivement
     * remplacée, pour ne rien peser sur le reste du catalogue.
     */
    public function hookHeader()
    {
        if (!isset($this->context->controller->php_self) || $this->context->controller->php_self !== 'product') {
            return;
        }
        if ($this->currentFrontBlock() === null) {
            return;
        }

        $this->context->controller->registerStylesheet(
            'ms-replacement-front',
            'modules/' . $this->name . '/views/css/front-replacement.css',
            ['media' => 'all', 'priority' => 150]
        );
        $this->context->controller->registerJavascript(
            'ms-replacement-front',
            'modules/' . $this->name . '/views/js/front-replacement.js',
            ['position' => 'bottom', 'priority' => 150]
        );
    }

    /**
     * Bloc « cette référence est remplacée » sur la fiche produit.
     *
     * La fiche reste VISIBLE et INDEXABLE — pas de redirection 301 : le client
     * doit pouvoir confirmer qu'il a trouvé la bonne ancienne référence (celle
     * gravée sur la pièce ou lue sur une facture), et on conserve le SEO acquis
     * sur ces anciennes références. Elle n'est simplement plus achetable.
     */
    public function hookDisplayProductAdditionalInfo($params)
    {
        $reference = '';
        if (isset($params['product']['reference'])) {
            $reference = (string) $params['product']['reference'];
        } elseif (isset($params['product']) && is_object($params['product'])) {
            $reference = (string) $params['product']->reference;
        }

        $block = $this->currentFrontBlock($reference !== '' ? $reference : null);
        if ($block === null) {
            return '';
        }

        $this->smarty->assign('ms_repl', $block);

        return $this->display(__FILE__, 'views/templates/hook/product-replacement.tpl');
    }

    /**
     * Id du produit en cours d'édition.
     * PrestaShop 8 sert la fiche produit via une route Symfony
     * (/sell/catalog/products/{id}/edit) : `id_product` n'est pas dans la query
     * string, il faut le lire dans l'URL. On garde le cas legacy en premier.
     */
    private function currentProductId()
    {
        $id = (int) Tools::getValue('id_product');
        if ($id > 0) {
            return $id;
        }

        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        if (preg_match('#/sell/catalog/products(?:-v2)?/(\d+)(?:/|$)#', $uri, $m)) {
            return (int) $m[1];
        }

        return 0;
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

        // Ces hooks ont été ajoutés APRÈS les premières installations :
        // install() ne rejoue pas, et un hook ne peut pas s'auto-enregistrer
        // depuis lui-même (il ne se déclenche pas tant qu'il ne l'est pas).
        // On le fait ici, seule page du module toujours atteignable. Idempotent.
        $late = [];
        foreach (['displayBackOfficeHeader', 'header', 'displayProductAdditionalInfo'] as $hook) {
            if (!$this->isRegisteredInHook($hook)) {
                $this->registerHook($hook);
                $late[] = $hook;
            }
        }
        if (!empty($late)) {
            $out .= $this->displayConfirmation(
                $this->l('Hooks activés :') . ' ' . implode(', ', $late)
            );
        }

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
     * Clé unique (`sales_orga`, `ref_replaced`, `ref_replacement`) : l'orga est
     * dans la clé pour permettre un import PHOTOGRAPHIQUE PAR ORGA — réimporter
     * le fichier KTM ne doit pas effacer les relations Husqvarna ou GasGas.
     *
     * Conséquence : une relation mutualisée entre marques existe en plusieurs
     * exemplaires. Le FRONT les regroupe à la lecture (il ignore l'orga, la réf
     * constructeur étant globalement unique chez Pierer).
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
            UNIQUE KEY `uk_replacement` (`sales_orga`, `ref_replaced`, `ref_replacement`),
            KEY `idx_ref_replaced` (`ref_replaced`),
            KEY `idx_ref_final` (`ref_final`),
            KEY `idx_chain_status` (`chain_status`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

        return (bool) Db::getInstance()->execute($sql);
    }
}
