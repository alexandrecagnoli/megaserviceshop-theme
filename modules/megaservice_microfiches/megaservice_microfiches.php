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

    // =====================================================================
    // Page de configuration BO (PrestaShop appelle getContent() sur "Configurer").
    // V1 : minimal — un bouton d'import par marque + un bouton "Tout importer".
    // Les CSV doivent être déposés au préalable dans <PS root>/data/imports/.
    // =====================================================================

    public function getContent(): string
    {
        $importsDir = _PS_ROOT_DIR_ . '/data/imports';
        $csvs       = $this->scanMotosCsvs($importsDir);
        $output     = '';

        // Dispatch des boutons d'import.
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
        return $output;
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
        $dir = htmlspecialchars($importsDir);

        if ($csvs === []) {
            return '<div class="alert alert-warning">'
                . '<p><strong>Aucun CSV motos trouvé</strong> dans <code>' . $dir . '</code>.</p>'
                . '<p>Déposer les fichiers <code>KTM_MOTORCYCLES.csv</code>, <code>HQV_MOTORCYCLES.csv</code> et/ou <code>GASGAS_MOTORCYCLES.csv</code> dans ce dossier, puis recharger cette page.</p>'
                . '</div>';
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

        return '<div class="panel">'
            . '<h3>Import motos <small>depuis ' . $dir . '</small></h3>'
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

    private function renderAlert(string $type, string $msg): string
    {
        return sprintf(
            '<div class="alert alert-%s">%s</div>',
            htmlspecialchars($type),
            htmlspecialchars($msg)
        );
    }
}
