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
require_once __DIR__ . '/classes/importers/CsvReader.php';
require_once __DIR__ . '/classes/importers/MotosTaxonomy.php';
require_once __DIR__ . '/classes/importers/MotosImportReport.php';
require_once __DIR__ . '/classes/importers/MotosImporter.php';
require_once __DIR__ . '/classes/importers/MicrofichesTaxonomy.php';
require_once __DIR__ . '/classes/importers/MicrofichesImportReport.php';
require_once __DIR__ . '/classes/importers/MicrofichesImporter.php';

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
            && $this->installTabs()
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayBackOfficeHeader')
            && $this->registerHook('actionProductAdd')
            && $this->registerHook('actionProductUpdate');
    }

    /**
     * On NE drop PAS les tables à l'uninstall — protection anti-perte de données.
     * Les tabs BO sont supprimés (cosmétique, recréables).
     * Pour réellement nettoyer la base, appeler dropTables() explicitement.
     */
    public function uninstall(): bool
    {
        $this->uninstallTabs();
        return parent::uninstall();
    }

    /**
     * Installe l'arborescence Tabs BO sous le menu "Catalogue" :
     *   AdminCatalog (Catalogue) [existant PS]
     *     └── Microfiches (parent)
     *         ├── Motos          → AdminMsMotos
     *         ├── Microfiches    → AdminMsMicrofiches
     *         └── Catégories     → AdminMsCategories
     *
     * Note PS 8 : on NE peut PAS ajouter un Tab top-level (id_parent=0) qui
     * apparaîtra dans le menu — la racine utilise une whitelist côté
     * Symfony. Il faut s'accrocher sous un Tab existant (Catalogue = logique
     * pour des données catalogue motos/microfiches).
     */
    private function installTabs(): bool
    {
        $catalogId = (int) Tab::getIdFromClassName('AdminCatalog');
        if ($catalogId <= 0) {
            // Très improbable (AdminCatalog est core PS) — fallback id_parent=0.
            $catalogId = 0;
        }

        $parentId = (int) Tab::getIdFromClassName(self::TAB_PARENT_CLASS);
        if ($parentId <= 0) {
            $parentId = $this->createTab(self::TAB_PARENT_CLASS, 'Microfiches', $catalogId);
            if ($parentId <= 0) {
                return false;
            }
        }
        foreach (self::CHILD_TABS as $className => $name) {
            if ((int) Tab::getIdFromClassName($className) > 0) {
                continue; // déjà installé (cas d'un upgrade partiel)
            }
            if ($this->createTab($className, $name, $parentId) <= 0) {
                return false;
            }
        }
        return true;
    }

    private function uninstallTabs(): bool
    {
        // Enfants d'abord pour éviter un parent orphelin (PrestaShop le gère
        // mais on reste explicite).
        $ok = true;
        foreach (self::CHILD_TABS as $className => $_) {
            $ok = $this->deleteTabByClass($className) && $ok;
        }
        $ok = $this->deleteTabByClass(self::TAB_PARENT_CLASS) && $ok;
        return $ok;
    }

    private function createTab(string $className, string $name, int $parentId): int
    {
        $tab             = new Tab();
        $tab->class_name = $className;
        // Tous nos tabs portent le module : le parent "Microfiches" est aussi
        // créé par nous (le module owner permet à PS de les déréférencer
        // proprement à l'uninstall si appelé via Module::uninstall()).
        $tab->module     = $this->name;
        $tab->id_parent  = $parentId;
        $tab->active     = 1;
        // Nom localisé pour toutes les langues installées.
        $tab->name = [];
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int) $lang['id_lang']] = $name;
        }
        return $tab->add() ? (int) $tab->id : 0;
    }

    private function deleteTabByClass(string $className): bool
    {
        $id = (int) Tab::getIdFromClassName($className);
        if ($id <= 0) {
            return true; // rien à supprimer
        }
        $tab = new Tab($id);
        return (bool) $tab->delete();
    }

    /**
     * Hook BO appelé sur chaque page admin. Sert ici à s'assurer que les
     * Tabs sont à jour sans exiger une visite manuelle de la page Configurer.
     *
     * On garde un flag Configuration::MS_MICROFICHES_TABS_VERSION qui doit
     * matcher self::TABS_VERSION (constante incrémentée à chaque ajout de
     * Tab) — si différent, on lance installTabs() et on met à jour le flag.
     * Coût : 1 SELECT Configuration::get() par pageview BO une fois sync'd.
     */
    public function hookDisplayBackOfficeHeader(): void
    {
        if ((int) Configuration::get('MS_MICROFICHES_TABS_VERSION') < self::TABS_VERSION) {
            if ($this->installTabs()) {
                Configuration::updateValue('MS_MICROFICHES_TABS_VERSION', self::TABS_VERSION);
            }
        }
    }

    /**
     * Version courante de la liste CHILD_TABS. À incrémenter à chaque ajout
     * d'un nouveau Tab dans CHILD_TABS pour déclencher l'auto-install sur
     * les installations existantes (sans désinstall/réinstall manuel).
     *
     * Historique :
     *   1 : Motos, Microfiches, Catégories (PR4)
     *   2 : + Hotspots (PR5-bis)
     */
    private const TABS_VERSION = 2;

    /** Classname du Tab parent (top-level) — référencé par les sous-tabs. */
    private const TAB_PARENT_CLASS = 'AdminMsMicrofichesParent';

    /**
     * Sous-tabs enfants : classname → libellé (FR).
     * Ajoutés au fur et à mesure des PRs. installTabs() est idempotent
     * (skip les Tabs déjà installés), donc un nouveau classname dans cette
     * liste est créé automatiquement au prochain appel de getContent().
     */
    private const CHILD_TABS = [
        'AdminMsMotos'       => 'Motos',
        'AdminMsMicrofiches' => 'Microfiches',
        'AdminMsCategories'  => 'Catégories',
        'AdminMsHotspots'    => 'Hotspots',
    ];

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

        // Strip les commentaires '--' AVANT de splitter sur ';' : sinon un
        // commentaire qui contient ';' (ex. "spec ; on a vu...") casse le
        // parser naïf et MariaDB voit la 2e moitié comme du SQL invalide.
        $lines = preg_split('/\R/', $sql) ?: [];
        $clean = [];
        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || strncmp($trimmed, '--', 2) === 0) {
                continue;
            }
            $clean[] = $line;
        }
        $sql = implode("\n", $clean);

        foreach (array_filter(array_map('trim', explode(';', $sql))) as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    // =====================================================================
    // Page de configuration BO (PrestaShop appelle getContent() sur "Configurer").
    // 2 modes :
    //   - upload direct d'un CSV depuis le navigateur → déposé dans
    //     data/imports/<MARQUE>_MOTORCYCLES.csv puis import immédiat
    //   - import des CSV déjà présents dans data/imports/ (SCP/SSH)
    // =====================================================================

    public function getContent(): string
    {
        $importsDir = _PS_ROOT_DIR_ . '/data/imports';
        $output     = '';

        // Idempotent : crée les Tabs ajoutés via mise à jour du module sans
        // exiger un désinstall/réinstall manuel (utile quand on ajoute un
        // nouveau controller admin entre deux versions).
        $this->installTabs();

        // Crée le dossier s'il n'existe pas (cas d'un premier déploiement).
        if (!is_dir($importsDir) && !@mkdir($importsDir, 0755, true)) {
            return $this->renderAlert('danger', sprintf(
                'Impossible de créer le dossier %s — vérifier les droits.',
                $importsDir
            ));
        }

        // -- Motos --------------------------------------------------------
        if (Tools::isSubmit('submitUploadMotosCsv')) {
            $output .= $this->handleUploadAndImport($importsDir);
        }
        $csvs = $this->scanMotosCsvs($importsDir);
        if (Tools::isSubmit('submitImportAllMotos') && $csvs !== []) {
            $output .= $this->runMotosImports($csvs);
        } else {
            foreach (MsMoto::MARQUES as $marque) {
                if (Tools::isSubmit('submitImportMotos_' . $marque) && isset($csvs[$marque])) {
                    $output .= $this->runMotosImports([$marque => $csvs[$marque]]);
                    break;
                }
            }
        }
        $output .= $this->renderImportPage($importsDir, $csvs);

        // -- Microfiches --------------------------------------------------
        if (Tools::isSubmit('submitUploadMicrofichesCsv')) {
            $output .= $this->handleUploadAndImportMicrofiches($importsDir);
        }
        if (Tools::isSubmit('submitUploadMicrofichesZip')) {
            $output .= $this->handleUploadZipMicrofiches($importsDir);
        }
        $microficheCsvs = $this->scanMicrofichesCsvs($importsDir);
        if (Tools::isSubmit('submitImportNewMicrofiches')) {
            // Filtre les CSV jamais importés (nb_microfiches = 0)
            $newCsvs = array_filter($microficheCsvs, static fn($info) => ($info['nb_microfiches'] ?? 0) === 0);
            if ($newCsvs !== []) {
                $output .= $this->runMicrofichesImports($newCsvs);
                // Rafraîchir les stats après import
                $microficheCsvs = $this->scanMicrofichesCsvs($importsDir);
            } else {
                $output .= $this->renderAlert('info', 'Aucun CSV à importer : tous ont déjà été traités au moins une fois.');
            }
        } else {
            foreach ($microficheCsvs as $serial => $info) {
                if (Tools::isSubmit('submitImportMicrofiches_' . $serial)) {
                    $output .= $this->runMicrofichesImports([$serial => $info]);
                    $microficheCsvs = $this->scanMicrofichesCsvs($importsDir);
                    break;
                }
            }
        }
        $output .= $this->renderMicrofichesPage($importsDir, $microficheCsvs);

        return $output;
    }

    /**
     * Gère l'upload d'un CSV motos depuis le BO, le déplace dans data/imports/
     * sous le nom canonique <MARQUE>_MOTORCYCLES.csv puis lance l'import.
     */
    private function handleUploadAndImport(string $importsDir): string
    {
        $upload = $_FILES['csv_file'] ?? null;
        if ($upload === null || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $this->renderAlert('danger', $this->describeUploadError($upload['error'] ?? UPLOAD_ERR_NO_FILE));
        }

        $originalName = (string) ($upload['name'] ?? '');
        if (strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION)) !== 'csv') {
            return $this->renderAlert('danger', 'Le fichier doit avoir l\'extension .csv (reçu : '
                . htmlspecialchars($originalName) . ')');
        }

        try {
            $marque = MotosImporter::deduceMarque($originalName);
        } catch (Throwable $e) {
            return $this->renderAlert('danger',
                'Marque indéterminable depuis le nom du fichier : doit contenir KTM, HQV ou GASGAS (reçu : '
                . htmlspecialchars($originalName) . ')');
        }

        $destPath = $importsDir . '/' . $marque . '_MOTORCYCLES.csv';
        if (!move_uploaded_file((string) $upload['tmp_name'], $destPath)) {
            return $this->renderAlert('danger', sprintf(
                'Impossible de déplacer le fichier uploadé vers %s — vérifier les droits.',
                htmlspecialchars($destPath)
            ));
        }

        $sizeMb = round(filesize($destPath) / 1024 / 1024, 2);
        $out    = $this->renderAlert('success', sprintf(
            'Upload réussi : %s (%s Mo). Import en cours…',
            $marque, $sizeMb
        ));

        $out .= $this->runMotosImports([
            $marque => [
                'path'     => $destPath,
                'size_mb'  => $sizeMb,
                'modified' => date('Y-m-d H:i:s'),
            ],
        ]);

        return $out;
    }

    private function describeUploadError(int $code): string
    {
        $umax = ini_get('upload_max_filesize');
        $pmax = ini_get('post_max_size');
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
                return "Fichier trop volumineux pour PHP (upload_max_filesize=$umax). "
                    . "Augmenter cette valeur dans le php.ini ou via .htaccess.";
            case UPLOAD_ERR_FORM_SIZE:
                return 'Fichier trop volumineux (limite côté formulaire dépassée).';
            case UPLOAD_ERR_PARTIAL:
                return "Upload interrompu (post_max_size=$pmax peut être trop bas).";
            case UPLOAD_ERR_NO_FILE:
                return 'Aucun fichier sélectionné.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Dossier temporaire PHP introuvable côté serveur.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Impossible d\'écrire le fichier uploadé sur le disque côté serveur.';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload bloqué par une extension PHP.';
            default:
                return "Erreur d'upload inconnue (code $code).";
        }
    }

    /**
     * Scanne <PS root>/data/imports/ pour les CSV motos attendus.
     * @return array<string, array{path: string, size_mb: float, modified: string}>
     */
    private function scanMotosCsvs(string $importsDir): array
    {
        $found = [];
        foreach (MsMoto::MARQUES as $marque) {
            $path = $importsDir . '/' . $marque . '_MOTORCYCLES.csv';
            if (is_file($path) && is_readable($path)) {
                $found[$marque] = [
                    'path'     => $path,
                    'size_mb'  => round(filesize($path) / 1024 / 1024, 2),
                    'modified' => date('Y-m-d H:i:s', filemtime($path)),
                ];
            }
        }
        return $found;
    }

    /**
     * @param array<string, array{path: string, size_mb: float, modified: string}> $csvs
     */
    private function runMotosImports(array $csvs): string
    {
        $importer = new MotosImporter();
        $reports  = [];
        foreach ($csvs as $marque => $csv) {
            try {
                $reports[$marque] = $importer->importFile($csv['path'], $marque);
            } catch (Throwable $e) {
                return $this->renderAlert('danger', sprintf(
                    'Erreur fatale sur %s : %s',
                    $marque,
                    $e->getMessage()
                ));
            }
        }
        return $this->renderReports($reports);
    }

    /**
     * @param array<string, array{path: string, size_mb: float, modified: string}> $csvs
     */
    private function renderImportPage(string $importsDir, array $csvs): string
    {
        $dir   = htmlspecialchars($importsDir);
        $umax  = htmlspecialchars((string) ini_get('upload_max_filesize'));
        $pmax  = htmlspecialchars((string) ini_get('post_max_size'));
        $out   = '';

        // -- Panel d'upload (toujours visible) --------------------------------
        $out .= '<div class="panel">'
            . '<h3>Téléverser un CSV motos</h3>'
            . '<form method="post" action="" enctype="multipart/form-data">'
            . '<p>Sélectionner un fichier <code>.csv</code> dont le nom contient '
            . '<strong>KTM</strong>, <strong>HQV</strong> ou <strong>GASGAS</strong> '
            . '(ex. <code>KTM_MOTORCYCLES.csv</code>). Le fichier est déposé dans '
            . '<code>' . $dir . '</code> sous le nom canonique '
            . '<code>&lt;MARQUE&gt;_MOTORCYCLES.csv</code> puis importé immédiatement.</p>'
            . '<p><input type="file" name="csv_file" accept=".csv" required /> '
            . '<button type="submit" name="submitUploadMotosCsv" value="1" class="btn btn-primary">'
            . 'Téléverser et importer</button></p>'
            . '<p class="help-block"><em>Limites PHP serveur : upload_max_filesize=' . $umax
            . ', post_max_size=' . $pmax . '. KTM_MOTORCYCLES.csv fait ~32 Mo — '
            . 'si l\'upload échoue, augmenter ces valeurs dans php.ini ou .htaccess.</em></p>'
            . '</form>'
            . '</div>';

        // -- Panel des CSV déjà présents --------------------------------------
        if ($csvs === []) {
            $out .= '<div class="alert alert-info">'
                . '<p>Aucun CSV motos présent dans <code>' . $dir . '</code> pour l\'instant. '
                . 'Utiliser le panneau d\'upload ci-dessus pour en déposer un.</p>'
                . '</div>';
            return $out;
        }

        $rows = '';
        foreach (MsMoto::MARQUES as $marque) {
            if (!isset($csvs[$marque])) {
                $rows .= sprintf(
                    '<tr><td><strong>%s</strong></td><td colspan="2"><em>fichier absent</em></td><td>&mdash;</td></tr>',
                    $marque
                );
                continue;
            }
            $c = $csvs[$marque];
            $rows .= sprintf(
                '<tr><td><strong>%s</strong></td><td>%s Mo</td><td>%s</td>'
                . '<td><button type="submit" name="submitImportMotos_%s" value="1" class="btn btn-default">Importer %s</button></td></tr>',
                $marque,
                $c['size_mb'],
                $c['modified'],
                $marque,
                $marque
            );
        }

        $out .= '<div class="panel">'
            . '<h3>Réimporter un CSV déjà présent</h3>'
            . '<form method="post" action="">'
            . '<table class="table">'
            . '<thead><tr><th>Marque</th><th>Taille</th><th>Modifié</th><th>Action</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table>'
            . '<p><button type="submit" name="submitImportAllMotos" value="1" class="btn btn-primary">'
            . 'Importer les ' . count($csvs) . ' CSV disponibles'
            . '</button></p>'
            . '<p class="help-block"><em>Idempotent : un réimport met à jour les motos existantes (clé : MODELNUMBER), ne réactive pas une moto désactivée manuellement.</em></p>'
            . '</form>'
            . '</div>';

        return $out;
    }

    /**
     * @param array<string, MotosImportReport> $reports
     */
    private function renderReports(array $reports): string
    {
        $out = '<div class="panel"><h3>Rapport d\'import</h3>';
        foreach ($reports as $marque => $r) {
            $a          = $r->toArray();
            $alertClass = count($r->errors) > 0 ? 'alert-warning' : 'alert-success';
            $out .= sprintf(
                '<div class="alert %s">'
                . '<h4>%s &mdash; %s</h4>'
                . '<ul>'
                . '<li>Lues : <strong>%d</strong></li>'
                . '<li>Insérées : <strong>%d</strong></li>'
                . '<li>Mises à jour : <strong>%d</strong></li>'
                . '<li>Dédupliquées (MODELNUMBER doublon) : <strong>%d</strong></li>'
                . '<li>Skippées (bruit / invalides) : <strong>%d</strong></li>'
                . '<li>Tombées en <code>Autres</code> (à corriger en BO) : <strong>%d</strong></li>'
                . '<li>Durée : <strong>%d ms</strong></li>'
                . '<li>Erreurs SQL : <strong>%d</strong></li>'
                . '</ul>'
                . '</div>',
                $alertClass,
                htmlspecialchars($marque),
                htmlspecialchars($a['csv']),
                $a['read'], $a['inserted'], $a['updated'], $a['deduped'],
                $a['skipped'], $a['autres'], $a['duration_ms'], count($r->errors)
            );

            if (count($r->errors) > 0) {
                $out .= '<h5>Erreurs SQL (10 premières) :</h5><ul>';
                foreach (array_slice($r->errors, 0, 10) as $err) {
                    $out .= sprintf(
                        '<li><code>%s</code> &mdash; %s</li>',
                        htmlspecialchars($err['modelnumber']),
                        htmlspecialchars($err['error'])
                    );
                }
                if (count($r->errors) > 10) {
                    $out .= sprintf('<li><em>&hellip; et %d autres</em></li>', count($r->errors) - 10);
                }
                $out .= '</ul>';
            }

            if (count($r->autresFound) > 0) {
                $out .= '<h5>Motos classées <code>Autres</code> (10 premières) :</h5><ul>';
                foreach (array_slice($r->autresFound, 0, 10) as $au) {
                    $out .= sprintf(
                        '<li><code>%s</code> &rarr; %s</li>',
                        htmlspecialchars($au['modelnumber']),
                        htmlspecialchars($au['core_name'])
                    );
                }
                if (count($r->autresFound) > 10) {
                    $out .= sprintf('<li><em>&hellip; et %d autres</em></li>', count($r->autresFound) - 10);
                }
                $out .= '</ul>';
            }
        }
        $out .= '</div>';
        return $out;
    }

    // =====================================================================
    // Microfiches : upload + import + listing
    // =====================================================================

    /**
     * Gère l'upload d'UN OU PLUSIEURS CSV microfiches depuis le BO.
     * Le nom de chaque fichier (sans .csv) sert de pivot moto via
     * ms_moto.serial_constructeur. Validation + déplacement + import sont
     * appliqués indépendamment fichier par fichier — un échec sur un fichier
     * n'arrête pas le traitement des autres.
     *
     * Le champ form `microfiches_csv_files[]` est un input type=file multiple.
     * $_FILES['microfiches_csv_files'] a alors une structure transposée
     * (clés name/tmp_name/error/size deviennent des arrays).
     */
    private function handleUploadAndImportMicrofiches(string $importsDir): string
    {
        $uploads = $_FILES['microfiches_csv_files'] ?? null;
        if ($uploads === null || empty($uploads['name'])) {
            return $this->renderAlert('danger', 'Aucun fichier sélectionné.');
        }

        $files         = self::transposeMultipleUpload($uploads);
        $perFileReport = [];
        $csvsToImport  = [];

        foreach ($files as $file) {
            $name = (string) ($file['name'] ?? '');
            if ($name === '') {
                continue; // slot vide (rare en multiple, mais safe)
            }

            $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($err !== UPLOAD_ERR_OK) {
                $perFileReport[] = ['file' => $name, 'ok' => false, 'msg' => $this->describeUploadError($err)];
                continue;
            }

            if (strtolower((string) pathinfo($name, PATHINFO_EXTENSION)) !== 'csv') {
                $perFileReport[] = ['file' => $name, 'ok' => false, 'msg' => 'Extension .csv requise.'];
                continue;
            }

            $serial = pathinfo($name, PATHINFO_FILENAME);
            if ($serial === '' || preg_match('/^[A-Za-z0-9_-]+$/', $serial) !== 1) {
                $perFileReport[] = ['file' => $name, 'ok' => false, 'msg' => 'Nom invalide (attendu : alphanumérique + "_" + "-" uniquement).'];
                continue;
            }

            $destPath = $importsDir . '/' . $serial . '.csv';
            if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $destPath)) {
                $perFileReport[] = ['file' => $name, 'ok' => false, 'msg' => 'Échec du déplacement vers ' . htmlspecialchars($destPath)];
                continue;
            }

            $csvsToImport[$serial] = [
                'path'     => $destPath,
                'size_ko'  => round(filesize($destPath) / 1024, 1),
                'modified' => date('Y-m-d H:i:s'),
            ];
            $perFileReport[] = ['file' => $name, 'ok' => true, 'msg' => 'Déposé : ' . basename($destPath)];
        }

        $out = $this->renderUploadBatchSummary($perFileReport);
        if ($csvsToImport !== []) {
            $out .= $this->runMicrofichesImports($csvsToImport);
        }
        return $out;
    }

    /**
     * Transforme $_FILES['xxx'] tel que reçu pour un input multiple
     * (arrays parallèles) en liste d'uploads individuels indexés.
     *
     * @return array<int, array{name: string, tmp_name: string, error: int, size: int}>
     */
    private static function transposeMultipleUpload(array $uploads): array
    {
        $files = [];
        $names = (array) ($uploads['name'] ?? []);
        foreach (array_keys($names) as $i) {
            $files[] = [
                'name'     => (string) ($uploads['name'][$i]     ?? ''),
                'tmp_name' => (string) ($uploads['tmp_name'][$i] ?? ''),
                'error'    => (int)    ($uploads['error'][$i]    ?? UPLOAD_ERR_NO_FILE),
                'size'     => (int)    ($uploads['size'][$i]     ?? 0),
            ];
        }
        return $files;
    }

    /**
     * Récap visuel du batch d'upload : 1 alerte agrégée + liste détaillée
     * des fichiers en erreur. Les fichiers OK ne sont pas listés individuellement
     * pour ne pas noyer l'affichage en cas de batch volumineux.
     *
     * @param array<int, array{file: string, ok: bool, msg: string}> $reports
     */
    private function renderUploadBatchSummary(array $reports): string
    {
        if ($reports === []) {
            return '';
        }
        $okCount = 0;
        $koReports = [];
        foreach ($reports as $r) {
            if ($r['ok']) {
                $okCount++;
            } else {
                $koReports[] = $r;
            }
        }
        $alertType = $koReports !== [] ? ($okCount > 0 ? 'warning' : 'danger') : 'success';
        $out = sprintf(
            '<div class="alert alert-%s"><p><strong>Upload :</strong> %d réussi(s), %d erreur(s).</p>',
            $alertType, $okCount, count($koReports)
        );
        if ($koReports !== []) {
            $out .= '<ul>';
            foreach ($koReports as $r) {
                $out .= sprintf(
                    '<li><code>%s</code> &mdash; %s</li>',
                    htmlspecialchars($r['file']),
                    $r['msg'] // msg contient déjà du HTML safe (htmlspecialchars sur destPath)
                );
            }
            $out .= '</ul>';
        }
        $out .= '</div>';
        return $out;
    }

    /**
     * Gère l'upload d'un ZIP contenant N CSV microfiches.
     * Bypass la limite post_max_size + upload_max_filesize en ne payant
     * qu'un seul transfert HTTP au lieu de N.
     *
     * Sécurité :
     *   - Chaque entrée du ZIP est aplatie via basename() pour éviter le
     *     path traversal (entrée "../etc/passwd" → ignorée car basename = "passwd"
     *     sans extension .csv reconnue).
     *   - Seuls les .csv avec nom serial valide (alphanum + _ + -) sont extraits.
     *   - Garde-fou anti zip-bomb : maxFiles=5000.
     *   - ZIP temporaire supprimé après extraction.
     */
    private function handleUploadZipMicrofiches(string $importsDir): string
    {
        if (!class_exists('ZipArchive')) {
            return $this->renderAlert('danger', 'L\'extension PHP ZipArchive n\'est pas disponible sur ce serveur.');
        }

        $upload = $_FILES['microfiches_zip_file'] ?? null;
        if ($upload === null || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $this->renderAlert('danger', $this->describeUploadError($upload['error'] ?? UPLOAD_ERR_NO_FILE));
        }

        $originalName = (string) ($upload['name'] ?? '');
        if (strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION)) !== 'zip') {
            return $this->renderAlert('danger', 'Le fichier doit avoir l\'extension .zip (reçu : '
                . htmlspecialchars($originalName) . ')');
        }

        $zipTmp = $importsDir . '/.upload_' . uniqid('', true) . '.zip';
        if (!move_uploaded_file((string) $upload['tmp_name'], $zipTmp)) {
            return $this->renderAlert('danger', 'Impossible de déplacer le ZIP uploadé.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipTmp) !== true) {
            @unlink($zipTmp);
            return $this->renderAlert('danger', 'Impossible d\'ouvrir le ZIP (corrompu ou format invalide).');
        }

        $perFileReport = [];
        $csvsToImport  = [];
        $maxFiles      = 5000; // garde-fou anti zip-bomb

        for ($i = 0; $i < $zip->numFiles && $i < $maxFiles; $i++) {
            $entryName = (string) $zip->getNameIndex($i);
            $baseName  = basename($entryName);

            // Skip silencieux : dossiers + métadonnées macOS (__MACOSX/, ._*)
            // + autres fichiers cachés type .DS_Store. Ce sont des artefacts
            // de l'archiveur (Finder Apple par défaut), pas des fichiers
            // utilisateur — pas la peine de les reporter comme erreurs.
            if ($baseName === '' || substr($entryName, -1) === '/') {
                continue;
            }
            if (strpos($entryName, '__MACOSX/') === 0
                || strpos($baseName, '._') === 0
                || $baseName === '.DS_Store') {
                continue;
            }
            // Ignore tout ce qui n'est pas .csv
            if (strtolower((string) pathinfo($baseName, PATHINFO_EXTENSION)) !== 'csv') {
                $perFileReport[] = ['file' => $entryName, 'ok' => false, 'msg' => 'Ignoré (pas un .csv).'];
                continue;
            }
            // Valide le serial
            $serial = pathinfo($baseName, PATHINFO_FILENAME);
            if (preg_match('/^[A-Za-z0-9_-]+$/', $serial) !== 1) {
                $perFileReport[] = ['file' => $entryName, 'ok' => false, 'msg' => 'Nom invalide (attendu : alphanumérique + "_" + "-").'];
                continue;
            }

            $destPath = $importsDir . '/' . $serial . '.csv';
            $content  = $zip->getFromIndex($i);
            if ($content === false || file_put_contents($destPath, $content) === false) {
                $perFileReport[] = ['file' => $entryName, 'ok' => false, 'msg' => 'Échec extraction.'];
                continue;
            }

            $csvsToImport[$serial] = [
                'path'     => $destPath,
                'size_ko'  => round(filesize($destPath) / 1024, 1),
                'modified' => date('Y-m-d H:i:s'),
            ];
            $perFileReport[] = ['file' => $entryName, 'ok' => true, 'msg' => 'Extrait : ' . basename($destPath)];
        }

        $zip->close();
        @unlink($zipTmp);

        $out = $this->renderUploadBatchSummary($perFileReport);
        if ($csvsToImport !== []) {
            $out .= $this->runMicrofichesImports($csvsToImport);
        }
        return $out;
    }

    /**
     * Scanne data/imports/ pour tout .csv qui n'est PAS un CSV motos, et
     * enrichit chaque entrée avec les statistiques BDD (statut import,
     * nb microfiches, nb hotspots, date du dernier import).
     *
     * @return array<string, array{
     *   path: string, size_ko: float, modified: string,
     *   id_moto: ?int, nb_microfiches: int, nb_hotspots: int, last_import: ?string,
     * }>
     */
    private function scanMicrofichesCsvs(string $importsDir): array
    {
        $excluded = [];
        foreach (MsMoto::MARQUES as $marque) {
            $excluded[$marque . '_MOTORCYCLES.csv'] = true;
        }
        $found = [];
        $files = @scandir($importsDir);
        if ($files === false) {
            return $found;
        }
        foreach ($files as $f) {
            if (substr($f, -4) !== '.csv' || isset($excluded[$f])) {
                continue;
            }
            $path = $importsDir . '/' . $f;
            if (!is_file($path) || !is_readable($path)) {
                continue;
            }
            $serial = pathinfo($f, PATHINFO_FILENAME);
            $found[$serial] = [
                'path'           => $path,
                'size_ko'        => round(filesize($path) / 1024, 1),
                'modified'       => date('Y-m-d H:i:s', filemtime($path)),
                'id_moto'        => null,
                'nb_microfiches' => 0,
                'nb_hotspots'    => 0,
                'last_import'    => null,
            ];
        }
        ksort($found);

        // Enrichissement BDD : 1 SELECT pour les stats microfiches, 1 pour
        // les hotspots (ces 2 jointures cumulées en une seule génèreraient
        // un produit cartésien lourd).
        if ($found !== []) {
            $serials    = array_keys($found);
            $serialList = "'" . implode("','", array_map('pSQL', $serials)) . "'";
            $db         = Db::getInstance();

            $statsRows = (array) $db->executeS(
                'SELECT m.`serial_constructeur` AS `serial`, m.`id_moto`, '
                . 'COUNT(DISTINCT mf.`id_microfiche`) AS `nb_microfiches`, '
                . 'MAX(mf.`date_upd`) AS `last_import` '
                . 'FROM `' . _DB_PREFIX_ . 'ms_moto` m '
                . 'LEFT JOIN `' . _DB_PREFIX_ . 'ms_microfiche` mf ON mf.`id_moto` = m.`id_moto` '
                . "WHERE m.`serial_constructeur` IN ($serialList) "
                . 'GROUP BY m.`id_moto`'
            );
            foreach ($statsRows as $r) {
                $s = (string) $r['serial'];
                if (isset($found[$s])) {
                    $found[$s]['id_moto']        = (int) $r['id_moto'];
                    $found[$s]['nb_microfiches'] = (int) $r['nb_microfiches'];
                    $found[$s]['last_import']    = ($r['last_import'] && $r['last_import'] !== '0000-00-00 00:00:00')
                        ? (string) $r['last_import'] : null;
                }
            }

            $hotspotsRows = (array) $db->executeS(
                'SELECT m.`serial_constructeur` AS `serial`, COUNT(*) AS `nb_hotspots` '
                . 'FROM `' . _DB_PREFIX_ . 'ms_moto` m '
                . 'JOIN `' . _DB_PREFIX_ . 'ms_microfiche` mf ON mf.`id_moto` = m.`id_moto` '
                . 'JOIN `' . _DB_PREFIX_ . 'ms_microfiche_hotspot` h ON h.`id_microfiche` = mf.`id_microfiche` '
                . "WHERE m.`serial_constructeur` IN ($serialList) "
                . 'GROUP BY m.`id_moto`'
            );
            foreach ($hotspotsRows as $r) {
                $s = (string) $r['serial'];
                if (isset($found[$s])) {
                    $found[$s]['nb_hotspots'] = (int) $r['nb_hotspots'];
                }
            }
        }

        return $found;
    }

    /**
     * @param array<string, array{path: string, size_ko: float, modified: string}> $csvs
     */
    private function runMicrofichesImports(array $csvs): string
    {
        $importer = new MicrofichesImporter();
        $reports  = [];
        foreach ($csvs as $serial => $csv) {
            try {
                $reports[$serial] = $importer->importFile($csv['path'], $serial);
            } catch (Throwable $e) {
                return $this->renderAlert('danger', sprintf(
                    'Erreur fatale sur %s : %s',
                    $serial, $e->getMessage()
                ));
            }
        }
        return $this->renderMicrofichesReports($reports);
    }

    /**
     * @param array<string, array{path: string, size_ko: float, modified: string}> $csvs
     */
    private function renderMicrofichesPage(string $importsDir, array $csvs): string
    {
        $dir  = htmlspecialchars($importsDir);
        $umax = htmlspecialchars((string) ini_get('upload_max_filesize'));
        $pmax = htmlspecialchars((string) ini_get('post_max_size'));
        $out  = '';

        // -- Panel d'upload microfiches (multi-file) ----------------------
        $out .= '<div class="panel">'
            . '<h3>Téléverser un ou plusieurs CSV microfiches</h3>'
            . '<form method="post" action="" enctype="multipart/form-data">'
            . '<p>Sélectionner <strong>un ou plusieurs</strong> fichiers <code>.csv</code> '
            . '(Ctrl+clic / Cmd+clic pour multi-sélection). Le nom de chaque fichier (sans extension) '
            . 'doit correspondre au <strong>serial constructeur</strong> d\'une moto déjà en BDD '
            . '(ex. <code>F0403X7.csv</code> pour la GASGAS EC 300 2024). '
            . 'Chaque fichier est déposé dans <code>' . $dir . '</code> puis importé indépendamment '
            . '(un échec sur un fichier ne bloque pas les autres).</p>'
            . '<p><input type="file" name="microfiches_csv_files[]" accept=".csv" multiple required /> '
            . '<button type="submit" name="submitUploadMicrofichesCsv" value="1" class="btn btn-primary">'
            . 'Téléverser et importer</button></p>'
            . '<p class="help-block"><em>Pré-requis : importer d\'abord le CSV motos correspondant '
            . '(la moto pivot doit exister dans <code>ps_ms_moto</code>). '
            . 'Limites PHP serveur : upload_max_filesize=' . $umax . ', post_max_size=' . $pmax
            . ' (somme des fichiers).</em></p>'
            . '</form>'
            . '</div>';

        // -- Panel d'upload microfiches (ZIP) -----------------------------
        $out .= '<div class="panel">'
            . '<h3>Téléverser un ZIP contenant N CSV microfiches</h3>'
            . '<form method="post" action="" enctype="multipart/form-data">'
            . '<p>Pour les très gros batches (au-delà des limites HTTP du multi-file), envoyer '
            . 'un <strong>fichier ZIP</strong> contenant les CSV à plat (1 seul niveau, pas de sous-dossiers). '
            . 'Chaque CSV à l\'intérieur doit suivre la convention '
            . '<code>&lt;SERIAL&gt;.csv</code> (autres fichiers ignorés).</p>'
            . '<p><input type="file" name="microfiches_zip_file" accept=".zip,application/zip" required /> '
            . '<button type="submit" name="submitUploadMicrofichesZip" value="1" class="btn btn-primary">'
            . 'Téléverser le ZIP</button></p>'
            . '<p class="help-block"><em>Sécurité : seuls les <code>.csv</code> à nom valide sont '
            . 'extraits, les chemins sont aplatis (basename). Garde-fou anti zip-bomb à 5000 fichiers.</em></p>'
            . '</form>'
            . '</div>';

        // -- Panel des CSV déjà présents ---------------------------------
        if ($csvs === []) {
            $out .= '<div class="alert alert-info">'
                . '<p>Aucun CSV microfiches présent dans <code>' . $dir . '</code> pour l\'instant.</p>'
                . '</div>';
            return $out;
        }

        // Compte les CSV jamais importés pour informer le bouton "Tout importer".
        $newCount = 0;
        foreach ($csvs as $c) {
            if (($c['nb_microfiches'] ?? 0) === 0) {
                $newCount++;
            }
        }

        $rows = '';
        foreach ($csvs as $serial => $c) {
            $serialEsc = htmlspecialchars($serial);
            $isImported = ($c['nb_microfiches'] ?? 0) > 0;
            if ($isImported) {
                $statusLabel = sprintf(
                    '<span class="label label-success" title="Importé le %s">✓ Importé</span>',
                    htmlspecialchars((string) $c['last_import'])
                );
                $lastImport = htmlspecialchars((string) ($c['last_import'] ?? ''));
            } else {
                $statusLabel = '<span class="label label-warning">À importer</span>';
                $lastImport  = '<em class="text-muted">&mdash;</em>';
            }
            $rows .= sprintf(
                '<tr data-serial="%s">'
                . '<td><strong>%s</strong></td>'
                . '<td>%s</td>'
                . '<td style="text-align:right">%d</td>'
                . '<td style="text-align:right">%d</td>'
                . '<td>%s</td>'
                . '<td>%s Ko</td>'
                . '<td>%s</td>'
                . '<td><button type="submit" name="submitImportMicrofiches_%s" value="1" class="btn btn-default">%s</button></td>'
                . '</tr>',
                strtolower($serialEsc),
                $serialEsc,
                $statusLabel,
                (int) ($c['nb_microfiches'] ?? 0),
                (int) ($c['nb_hotspots'] ?? 0),
                $lastImport,
                $c['size_ko'],
                htmlspecialchars((string) ($c['modified'] ?? '')),
                $serialEsc,
                $isImported ? 'Réimporter' : 'Importer'
            );
        }

        // Search JS inline : filtre les rows par data-serial (lowercase) qui contient le texte tapé.
        $searchJs = "(function(input){"
            . "var q=(input.value||'').toLowerCase();"
            . "document.querySelectorAll('#ms-microfiches-csv-table tbody tr').forEach(function(r){"
            . "r.style.display=(r.dataset.serial||'').indexOf(q)!==-1?'':'none';"
            . "});"
            . "})(this);";

        $out .= '<div class="panel">'
            . '<h3>CSV microfiches présents dans <code>' . $dir . '</code></h3>'
            . '<form method="post" action="">'
            . '<div class="row" style="margin-bottom:10px">'
            . '<div class="col-md-6">'
            . '<input type="text" class="form-control" placeholder="Filtrer par serial moto (ex. F040)..." '
            . 'oninput="' . $searchJs . '" />'
            . '</div>'
            . '<div class="col-md-6" style="text-align:right">'
            . sprintf(
                '<button type="submit" name="submitImportNewMicrofiches" value="1" class="btn btn-primary" %s>'
                . 'Tout importer ce qui est nouveau (%d)</button>',
                $newCount === 0 ? 'disabled' : '',
                $newCount
            )
            . '</div>'
            . '</div>'
            . '<table id="ms-microfiches-csv-table" class="table">'
            . '<thead><tr>'
            . '<th>Serial moto</th>'
            . '<th>Statut</th>'
            . '<th style="text-align:right">Microfiches</th>'
            . '<th style="text-align:right">Hotspots</th>'
            . '<th>Dernier import</th>'
            . '<th>Taille</th>'
            . '<th>Fichier modifié</th>'
            . '<th>Action</th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table>'
            . '<p class="help-block"><em>Idempotent : un réimport met à jour les microfiches '
            . 'et hotspots existants (clés : (moto+catégorie+nom) et (microfiche+article+sequence)). '
            . '<strong>Tout importer ce qui est nouveau</strong> traite uniquement les CSV avec '
            . '0 microfiche en BDD (statut "À importer") — utile après un upload batch ZIP ou multi-file.</em></p>'
            . '</form>'
            . '</div>';

        return $out;
    }

    /**
     * @param array<string, MicrofichesImportReport> $reports
     */
    private function renderMicrofichesReports(array $reports): string
    {
        $out = '<div class="panel"><h3>Rapport d\'import microfiches</h3>';
        foreach ($reports as $serial => $r) {
            $a = $r->toArray();
            if ($r->motoId === null) {
                $out .= sprintf(
                    '<div class="alert alert-warning">'
                    . '<h4>%s &mdash; %s</h4>'
                    . '<p>⚠️ <strong>Moto pivot introuvable</strong> : <code>%s</code> n\'existe pas dans <code>ps_ms_moto.serial_constructeur</code>. '
                    . 'Importer d\'abord le CSV motos correspondant, puis relancer cet import.</p>'
                    . '</div>',
                    htmlspecialchars($serial), htmlspecialchars($a['csv']), htmlspecialchars($serial)
                );
                continue;
            }
            $alertClass = count($r->errors) > 0 ? 'alert-warning' : 'alert-success';
            $out .= sprintf(
                '<div class="alert %s">'
                . '<h4>%s &mdash; %s</h4>'
                . '<ul>'
                . '<li>Moto résolue : id_moto = <strong>%d</strong></li>'
                . '<li>Hotspots lus : <strong>%d</strong></li>'
                . '<li>Hotspots skippés (invalides) : <strong>%d</strong></li>'
                . '<li>Catégories créées (auto) / réutilisées : <strong>%d / %d</strong></li>'
                . '<li>Microfiches insérées / mises à jour : <strong>%d / %d</strong></li>'
                . '<li>Hotspots insérés / mis à jour : <strong>%d / %d</strong></li>'
                . '<li>Durée : <strong>%d ms</strong></li>'
                . '<li>Erreurs SQL : <strong>%d</strong></li>'
                . '</ul>'
                . '</div>',
                $alertClass,
                htmlspecialchars($serial), htmlspecialchars($a['csv']),
                $a['moto_id'], $a['rows_read'], $a['rows_skipped'],
                $a['categories_created'], $a['categories_reused'],
                $a['microfiches_inserted'], $a['microfiches_updated'],
                $a['hotspots_inserted'], $a['hotspots_updated'],
                $a['duration_ms'], count($r->errors)
            );

            if (count($r->errors) > 0) {
                $out .= '<h5>Erreurs SQL (10 premières) :</h5><ul>';
                foreach (array_slice($r->errors, 0, 10) as $err) {
                    $out .= sprintf(
                        '<li><code>[%s] %s</code> &mdash; %s</li>',
                        htmlspecialchars($err['level']),
                        htmlspecialchars($err['key']),
                        htmlspecialchars($err['error'])
                    );
                }
                if (count($r->errors) > 10) {
                    $out .= sprintf('<li><em>&hellip; et %d autres</em></li>', count($r->errors) - 10);
                }
                $out .= '</ul>';
            }

            if (count($r->categoriesAutoCreated) > 0) {
                $out .= '<h5>Catégories auto-créées (à renommer en BO &mdash; brief §6.1.E) :</h5><ul>';
                foreach ($r->categoriesAutoCreated as $cat) {
                    $out .= sprintf(
                        '<li><code>%s</code> (partie=%s, numero=%d) &mdash; nom_fr = NULL</li>',
                        htmlspecialchars($cat['code']),
                        htmlspecialchars($cat['partie']),
                        $cat['numero']
                    );
                }
                $out .= '</ul>';
            }
        }
        $out .= '</div>';
        return $out;
    }

    private function renderAlert(string $type, string $msg): string
    {
        return sprintf(
            '<div class="alert alert-%s">%s</div>',
            htmlspecialchars($type),
            htmlspecialchars($msg)
        );
    }
}
