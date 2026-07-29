<?php
/**
 * Megaservice — Montabilité produit ↔ moto.
 *
 * Suite à l'abandon d'EveryParts, la montabilité (compatibilité d'un accessoire
 * avec un modèle de moto) est internalisée : ce module en est la source de
 * vérité. Une app externe génère un fichier par marque depuis le XML
 * constructeur ; ce module l'importe (TRUNCATE & RELOAD par marque) et l'expose.
 *
 * L'OEM ne passe PAS par ici : il est géré par les microfiches
 * (megaservice_microfiches, table ms_moto — même référentiel moto).
 *
 * Spec : conversation COPROJ, périmètre v1.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/MsMountability.php';
require_once __DIR__ . '/classes/MountabilityImporter.php';

class Megaservice_mountability extends Module
{
    /** Marques gérées (une par fichier constructeur). */
    const BRANDS = ['KTM', 'HQV', 'GG'];

    public function __construct()
    {
        $this->name          = 'megaservice_mountability';
        $this->tab           = 'administration';
        $this->version       = '1.0.0';
        $this->author        = 'Megaservice';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap     = true;

        parent::__construct();

        $this->displayName = 'Megaservice — Montabilité produit / moto';
        $this->description = 'Import des compatibilités produit ↔ moto et affichage des motos compatibles sur la fiche produit.';
    }

    public function install()
    {
        return parent::install()
            && $this->createTable()
            && $this->ensureCatalogIndexes()
            && $this->registerHook('displayFooterProduct')
            && $this->registerHook('actionFrontControllerSetMedia')
            && $this->registerHook('actionFacetedSearchFilters');
    }

    /**
     * On NE supprime PAS la table à la désinstallation : un import complet peut
     * représenter des centaines de milliers de lignes. Le DROP reste manuel.
     */
    public function uninstall()
    {
        return parent::uninstall();
    }

    private function createTable()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'ms_mountability` (
            `id_ms_mountability`   INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `reference`            VARCHAR(64) NOT NULL,
            `id_moto_constructeur` VARCHAR(64) NOT NULL,
            `marque`               VARCHAR(16) NOT NULL,
            `date_add`             DATETIME NOT NULL,
            PRIMARY KEY (`id_ms_mountability`),
            UNIQUE KEY `uk_mountability` (`reference`, `id_moto_constructeur`),
            KEY `idx_moto` (`id_moto_constructeur`),
            KEY `idx_marque` (`marque`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

        return (bool) Db::getInstance()->execute($sql);
    }

    /**
     * La résolution réf → produit se fait sur `ps_product.reference` et
     * `ps_product_attribute.reference`, non indexées par défaut. À l'échelle de
     * 500 k lignes, sans index, le comptage des non-résolus rampe. On les pose
     * si absentes (idempotent).
     */
    private function ensureCatalogIndexes()
    {
        $this->ensureIndex('product', 'reference', 'idx_ms_reference');
        $this->ensureIndex('product_attribute', 'reference', 'idx_ms_pa_reference');

        return true;
    }

    private function ensureIndex($table, $column, $indexName)
    {
        // Index de PERF uniquement (accélère le comptage des non-résolus). Il ne
        // doit JAMAIS faire échouer l'install : `ps_product.reference` est un
        // VARCHAR(64), un préfixe (191) y était illégal (erreur 1089) et
        // interrompait l'install avant l'enregistrement du hook. On indexe la
        // colonne entière (courte) et on avale toute erreur.
        try {
            $db  = Db::getInstance();
            $tbl = _DB_PREFIX_ . $table;
            $exists = $db->executeS('SHOW INDEX FROM `' . $tbl . '` WHERE `Key_name` = "' . pSQL($indexName) . '"');
            if (empty($exists)) {
                $db->execute('ALTER TABLE `' . $tbl . '` ADD INDEX `' . bqSQL($indexName) . '` (`' . bqSQL($column) . '`)');
            }
        } catch (Exception $e) {
            // Non bloquant : l'absence d'index ne dégrade que la vitesse du rapport.
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Front : bloc « Compatible avec » en bas de fiche produit
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Les assets DOIVENT être enregistrés avant le rendu du <head>.
     * registerStylesheet appelé depuis displayFooterProduct (en plein <body>)
     * arrive trop tard → le <link> n'est jamais injecté. On les pose donc ici,
     * sur les fiches produit uniquement.
     */
    public function hookActionFrontControllerSetMedia($params)
    {
        if ($this->context->controller->php_self !== 'product') {
            return;
        }
        $this->context->controller->registerStylesheet(
            'ms-mountability-front',
            'modules/' . $this->name . '/views/css/front-mountability.css',
            ['media' => 'all', 'priority' => 150]
        );
        $this->context->controller->registerJavascript(
            'ms-mountability-front',
            'modules/' . $this->name . '/views/js/front-mountability.js',
            ['position' => 'bottom', 'priority' => 150]
        );
    }

    public function hookDisplayFooterProduct($params)
    {
        $reference = '';
        if (isset($params['product']['reference'])) {
            $reference = (string) $params['product']['reference'];
        } elseif (isset($params['product']) && is_object($params['product'])) {
            $reference = (string) $params['product']->reference;
        }
        if ($reference === '') {
            return '';
        }

        $groups = MsMountability::getCompatibleMotosGrouped($reference);
        if (empty($groups)) {
            return ''; // masqué si aucune moto résolue
        }

        $this->smarty->assign([
            'ms_mount_groups' => $groups,
            'ms_mount_count'  => count($groups),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/product-motos.tpl');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Filtre catégorie : injection dans ps_facetedsearch (option B)
    // ─────────────────────────────────────────────────────────────────────────

    /** Racine dont le sous-arbre est filtrable par montabilité. */
    const POWERPARTS_ROOT_CATEGORY = 41;

    /**
     * ps_facetedsearch tire ce hook en fin de construction de sa requête
     * (Search::initSearch). Quand une moto est en garage et qu'on est dans le
     * sous-arbre Powerparts, on injecte `id_product IN (compatibles)` dans SA
     * requête → facettes, produits ET compteurs tous croisés avec la
     * compatibilité, nativement (même mécanisme que le pool de recherche natif).
     */
    public function hookActionFacetedSearchFilters($params)
    {
        if (empty($params['search']) || empty($params['query'])) {
            return;
        }
        $idMoto = (int) $this->context->cookie->ms_moto;
        if (!$idMoto) {
            return;
        }
        $idCategory = (int) $params['query']->getIdCategory();
        if (!$idCategory || !$this->isPowerpartsSubtree($idCategory)) {
            return;
        }

        $ids = MsMountability::getCompatibleProducts($idMoto);

        // Vide → aucun produit compatible : on force un résultat vide (comme le
        // fait le provider natif de facetedsearch pour un pool de recherche vide).
        $params['search']->getSearchAdapter()->addFilter(
            'id_product',
            empty($ids) ? ['NULL'] : array_map('intval', $ids)
        );
    }

    /** La catégorie appartient-elle au sous-arbre Powerparts (nested set) ? */
    private function isPowerpartsSubtree($idCategory)
    {
        $root = new Category(self::POWERPARTS_ROOT_CATEGORY);
        if (!$root->id) {
            return false;
        }
        $cat = new Category((int) $idCategory);

        return $cat->id && $cat->nleft >= $root->nleft && $cat->nright <= $root->nright;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Back-office : écran d'import
    // ─────────────────────────────────────────────────────────────────────────

    public function getContent()
    {
        $out = '';

        // Self-heal : un install() historique a pu échouer avant registerHook
        // (cf. bug d'index 1089), ou tourner avant l'ajout d'un hook. Un simple
        // passage sur cet écran raccroche les hooks manquants, sans réinstaller.
        // (Noms canoniques → isRegisteredInHook fiable.)
        $healed = [];
        foreach (['displayFooterProduct', 'actionFrontControllerSetMedia', 'actionFacetedSearchFilters'] as $h) {
            if (!$this->isRegisteredInHook($h)) {
                $this->registerHook($h);
                $healed[] = $h;
            }
        }
        if ($healed) {
            $out .= $this->displayConfirmation(
                $this->l('Hooks ré-enregistrés : ') . implode(', ', $healed)
                . $this->l('. Le bloc « Compatible avec » et son style vont s\'afficher en fiche produit.')
            );
        }

        if (Tools::isSubmit('submitMsMountabilityImport')) {
            $out .= $this->handleImport();
        }

        return $out . $this->renderForm();
    }

    private function handleImport()
    {
        $marque = strtoupper((string) Tools::getValue('marque'));
        if (!in_array($marque, self::BRANDS, true)) {
            return $this->displayError($this->l('Marque invalide.'));
        }

        if (empty($_FILES['csv']['tmp_name']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
            return $this->displayError($this->l('Aucun fichier reçu (vérifiez la taille max d\'upload PHP pour les gros fichiers).'));
        }
        if (strtolower(pathinfo((string) $_FILES['csv']['name'], PATHINFO_EXTENSION)) !== 'csv') {
            return $this->displayError($this->l('Le fichier doit être un .csv.'));
        }

        $dest = _PS_UPLOAD_DIR_ . 'ms_mountability_' . md5(uniqid('', true)) . '.csv';
        if (!move_uploaded_file($_FILES['csv']['tmp_name'], $dest)) {
            return $this->displayError($this->l('Échec de la réception du fichier.'));
        }

        $report = MsMountabilityImporter::import($dest, $marque);
        @unlink($dest);

        return $this->renderReport($report);
    }

    private function renderReport(array $r)
    {
        if (!empty($r['error'])) {
            return $this->displayError($r['error']);
        }

        // Taux de couverture serials (informatif).
        $ds   = (int) ($r['distinct_serials'] ?? 0);
        $rs   = (int) ($r['resolved_serials'] ?? 0);
        $rate = $ds > 0 ? round(100 * $rs / $ds) : 0;

        $rows = '';
        foreach ([
            'marque'           => $this->l('Marque rechargée'),
            'motos_resolues'   => $this->l('▶ Motos allumées (motos distinctes affichées sur les fiches)'),
            'loaded'           => $this->l('Relations en base (après dédoublonnage)'),
            'lines_read'       => $this->l('Lignes lues'),
            'valid'            => $this->l('Lignes valides'),
            'duplicates'       => $this->l('Doublons absorbés'),
            'rejected_format'  => $this->l('Lignes rejetées (format)'),
            'unresolved_refs'  => $this->l('Références sans produit au catalogue'),
        ] as $k => $label) {
            $rows .= '<tr><td>' . $label . '</td><td><strong>' . htmlspecialchars((string) $r[$k]) . '</strong></td></tr>';
        }
        $rows .= '<tr><td>' . $this->l('Serials résolus / distincts')
            . '</td><td><strong>' . $rs . ' / ' . $ds . ' (' . $rate . '%)</strong></td></tr>';

        $motoList = '';
        if (!empty($r['unresolved_moto_list'])) {
            $motoList = '<p><em>' . sprintf(
                $this->l('%d serials sans ligne ms_moto. Attendu : ce sont surtout des variantes (couleur, CKD/export) d\'une moto-année déjà allumée par un serial frère, ou des modèles hors référentiel. À surveiller seulement si une moto-année n\'apparaît nulle part. Top 200 :'),
                (int) $r['unresolved_motos']
            ) . '</em></p><ul>';
            foreach ($r['unresolved_moto_list'] as $m) {
                $motoList .= '<li><code>' . htmlspecialchars($m['id_moto_constructeur']) . '</code> — '
                    . (int) $m['nb'] . ' ' . $this->l('lignes') . '</li>';
            }
            $motoList .= '</ul>';
        }

        return $this->displayConfirmation(sprintf(
            $this->l('Import terminé — %d motos allumées (%d%% des serials résolus).'),
            (int) $r['motos_resolues'], $rate
        ))
            . '<div class="panel"><h3><i class="icon-list"></i> ' . $this->l('Rapport d\'import') . '</h3>'
            . '<table class="table"><tbody>' . $rows . '</tbody></table>' . $motoList . '</div>';
    }

    private function renderForm()
    {
        $counts = Db::getInstance()->executeS(
            'SELECT `marque`, COUNT(*) AS nb FROM `' . _DB_PREFIX_ . 'ms_mountability` GROUP BY `marque`'
        ) ?: [];
        $summary = '';
        foreach ($counts as $c) {
            $summary .= '<li>' . htmlspecialchars($c['marque']) . ' : <strong>' . (int) $c['nb'] . '</strong></li>';
        }

        $options = '';
        foreach (self::BRANDS as $b) {
            $options .= '<option value="' . $b . '">' . $b . '</option>';
        }

        return '<div class="panel">
            <h3><i class="icon-upload"></i> ' . $this->l('Import de la montabilité') . '</h3>
            ' . ($summary ? '<p>' . $this->l('En base :') . '</p><ul>' . $summary . '</ul>' : '<p>' . $this->l('Aucune donnée en base.') . '</p>') . '
            <form method="post" enctype="multipart/form-data" class="form-horizontal">
                <div class="form-group">
                    <label class="control-label col-lg-3">' . $this->l('Marque') . '</label>
                    <div class="col-lg-4"><select name="marque" class="form-control">' . $options . '</select></div>
                </div>
                <div class="form-group">
                    <label class="control-label col-lg-3">' . $this->l('Fichier CSV') . '</label>
                    <div class="col-lg-6">
                        <input type="file" name="csv" accept=".csv">
                        <p class="help-block">' . $this->l('montabilite_<marque>.csv — reference;id_moto;marque. Rechargement complet de la marque sélectionnée.') . '</p>
                    </div>
                </div>
                <div class="panel-footer">
                    <button type="submit" name="submitMsMountabilityImport" class="btn btn-default pull-right">
                        <i class="process-icon-upload"></i> ' . $this->l('Importer') . '
                    </button>
                </div>
            </form>
        </div>';
    }
}
