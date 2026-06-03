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
     * Installe l'arborescence Tabs BO :
     *   Microfiches (parent, top-level)
     *     ├── Motos          → AdminMsMotos
     *     ├── Microfiches    → AdminMsMicrofiches
     *     └── Catégories     → AdminMsCategories
     *
     * Les controllers correspondants sont dans controllers/admin/ et seront
     * ajoutés en commits suivants. À l'install d'un commit qui n'inclut pas
     * encore le controller, le tab existe mais cliquer dessus donnera un 404
     * Presta — sans gravité, le tab disparaît après désinstallation.
     */
    private function installTabs(): bool
    {
        $parentId = (int) Tab::getIdFromClassName(self::TAB_PARENT_CLASS);
        if ($parentId <= 0) {
            $parentId = $this->createTab(self::TAB_PARENT_CLASS, 'Microfiches', 0);
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
        $tab->module     = $parentId === 0 ? '' : $this->name; // parent top-level n'a pas de module
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

    /** Classname du Tab parent (top-level) — référencé par les sous-tabs. */
    private const TAB_PARENT_CLASS = 'AdminMsMicrofichesParent';

    /**
     * Sous-tabs enfants : classname → libellé (FR).
     * Les controllers correspondants seront livrés dans les commits suivants
     * de la PR4 (Catégories d'abord car action immédiate la plus utile).
     */
    private const CHILD_TABS = [
        'AdminMsMotos'       => 'Motos',
        'AdminMsMicrofiches' => 'Microfiches',
        'AdminMsCategories'  => 'Catégories',
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
        $microficheCsvs = $this->scanMicrofichesCsvs($importsDir);
        foreach ($microficheCsvs as $serial => $info) {
            if (Tools::isSubmit('submitImportMicrofiches_' . $serial)) {
                $output .= $this->runMicrofichesImports([$serial => $info]);
                break;
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
     * Gère l'upload d'un CSV microfiches depuis le BO. Le nom du fichier
     * (sans .csv) sert de pivot moto via ms_moto.serial_constructeur.
     */
    private function handleUploadAndImportMicrofiches(string $importsDir): string
    {
        $upload = $_FILES['microfiches_csv_file'] ?? null;
        if ($upload === null || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $this->renderAlert('danger', $this->describeUploadError($upload['error'] ?? UPLOAD_ERR_NO_FILE));
        }

        $originalName = (string) ($upload['name'] ?? '');
        if (strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION)) !== 'csv') {
            return $this->renderAlert('danger', 'Le fichier doit avoir l\'extension .csv (reçu : '
                . htmlspecialchars($originalName) . ')');
        }
        $serial = pathinfo($originalName, PATHINFO_FILENAME);
        if ($serial === '' || preg_match('/^[A-Za-z0-9_-]+$/', $serial) !== 1) {
            return $this->renderAlert('danger',
                'Nom de fichier invalide : doit être de la forme <code>&lt;SERIAL&gt;.csv</code> (caractères alphanumériques, "_" et "-") où SERIAL correspond à ms_moto.serial_constructeur (reçu : '
                . htmlspecialchars($originalName) . ')');
        }

        $destPath = $importsDir . '/' . $serial . '.csv';
        if (!move_uploaded_file((string) $upload['tmp_name'], $destPath)) {
            return $this->renderAlert('danger', sprintf(
                'Impossible de déplacer le fichier uploadé vers %s — vérifier les droits.',
                htmlspecialchars($destPath)
            ));
        }

        $sizeKo = round(filesize($destPath) / 1024, 1);
        $out    = $this->renderAlert('success', sprintf(
            'Upload réussi : %s (%s Ko). Import en cours…',
            $serial, $sizeKo
        ));

        $out .= $this->runMicrofichesImports([
            $serial => [
                'path'     => $destPath,
                'size_ko'  => $sizeKo,
                'modified' => date('Y-m-d H:i:s'),
            ],
        ]);

        return $out;
    }

    /**
     * Scanne data/imports/ pour tout .csv qui n'est PAS un CSV motos.
     * @return array<string, array{path: string, size_ko: float, modified: string}>
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
                'path'     => $path,
                'size_ko'  => round(filesize($path) / 1024, 1),
                'modified' => date('Y-m-d H:i:s', filemtime($path)),
            ];
        }
        ksort($found);
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

        // -- Panel d'upload microfiches -----------------------------------
        $out .= '<div class="panel">'
            . '<h3>Téléverser un CSV microfiches</h3>'
            . '<form method="post" action="" enctype="multipart/form-data">'
            . '<p>Sélectionner un fichier <code>.csv</code> dont le nom (sans extension) '
            . 'correspond au <strong>serial constructeur</strong> d\'une moto déjà en BDD '
            . '(ex. <code>F0403X7.csv</code> pour la GASGAS EC 300 2024). '
            . 'Le fichier est déposé dans <code>' . $dir . '</code> puis importé immédiatement.</p>'
            . '<p><input type="file" name="microfiches_csv_file" accept=".csv" required /> '
            . '<button type="submit" name="submitUploadMicrofichesCsv" value="1" class="btn btn-primary">'
            . 'Téléverser et importer</button></p>'
            . '<p class="help-block"><em>Pré-requis : importer d\'abord le CSV motos correspondant '
            . '(la moto pivot doit exister dans <code>ps_ms_moto</code>). '
            . 'Limites PHP serveur : upload_max_filesize=' . $umax . ', post_max_size=' . $pmax . '.</em></p>'
            . '</form>'
            . '</div>';

        // -- Panel des CSV déjà présents ---------------------------------
        if ($csvs === []) {
            $out .= '<div class="alert alert-info">'
                . '<p>Aucun CSV microfiches présent dans <code>' . $dir . '</code> pour l\'instant.</p>'
                . '</div>';
            return $out;
        }

        $rows = '';
        foreach ($csvs as $serial => $c) {
            $rows .= sprintf(
                '<tr><td><strong>%s</strong></td><td>%s Ko</td><td>%s</td>'
                . '<td><button type="submit" name="submitImportMicrofiches_%s" value="1" class="btn btn-default">Réimporter</button></td></tr>',
                htmlspecialchars($serial), $c['size_ko'], $c['modified'],
                htmlspecialchars($serial)
            );
        }

        $out .= '<div class="panel">'
            . '<h3>Réimporter un CSV microfiches déjà présent</h3>'
            . '<form method="post" action="">'
            . '<table class="table">'
            . '<thead><tr><th>Serial moto</th><th>Taille</th><th>Modifié</th><th>Action</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table>'
            . '<p class="help-block"><em>Idempotent : un réimport met à jour les microfiches '
            . 'et hotspots existants (clés : (moto+catégorie+nom) et (microfiche+article+sequence)).</em></p>'
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
