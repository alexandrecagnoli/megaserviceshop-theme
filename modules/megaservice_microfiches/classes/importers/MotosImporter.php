<?php
/**
 * Ingestion d'un CSV motos (KTM / HQV / GASGAS) → table ps_ms_moto.
 *
 * Spec : docs/microfiches/BRIEF.md §4.1, §4.2, §6.1.
 *
 * Pipeline :
 *   CsvReader (encodage/delim/headers normalisés)
 *     → buildRow() : mapping + dérivations via MotosTaxonomy
 *     → dédup par MODELNUMBER (1ère occurrence)
 *     → INSERT ... ON DUPLICATE KEY UPDATE en SQL direct
 *
 * Idempotence : UNIQUE(modelnumber) + ON DUPLICATE KEY UPDATE → l'id_moto
 * reste stable (les microfiches qui pointent dessus ne cassent pas).
 *
 * Champs NON touchés à l'UPDATE (intentionnel) :
 *   - active : l'admin peut désactiver une moto en BO, un réimport ne doit
 *     pas la réactiver.
 *   - picture_cycle / picture_moteur : alimentés via les microfiches (PR future),
 *     pas depuis le CSV motos.
 *   - date_add : préserver la date d'insertion originelle.
 *
 * Rows invalides : skip silencieux + compteur dans MotosImportReport. Une row
 * est invalide si MODELNUMBER absent / ne commence pas par '$M-', si annee
 * est hors [1990..2099], ou si category_fr / nom_fr sont vides. Cf. les rows
 * "bruit HTML" rencontrées dans les CSV constructeur.
 */
class MotosImporter
{
    /** Année plancher du garde-fou de validation (rows hors range = skip). */
    private const MIN_YEAR = 1990;
    /** Année plafond du garde-fou. */
    private const MAX_YEAR = 2099;

    /** Préfixe MODELNUMBER attendu — sinon row ignorée. */
    private const MODELNUMBER_PREFIX = '$M-';

    /**
     * Suffixes d'article_number (= serial_constructeur) qui identifient une
     * moto cross-badging hors marche europeen (decision client MSS du
     * 2026-06-05). Les motos correspondantes sont skippees a l'import.
     * L'equivalent europeen existe deja en base sous serial = 'F' + base
     * (ex: 7487V2_CFMOTO -> F7487V2 deja present).
     */
    private const BLACKLISTED_SERIAL_SUFFIXES = ['_CFMOTO', '_FTI'];

    /**
     * Importe un CSV motos complet.
     *
     * @param string      $csvPath  Chemin absolu vers le CSV.
     * @param string|null $marque   Si null, déduit depuis le nom de fichier.
     */
    public function importFile(string $csvPath, ?string $marque = null): MotosImportReport
    {
        if ($marque === null) {
            $marque = self::deduceMarque($csvPath);
        }
        if (!in_array($marque, MsMoto::MARQUES, true)) {
            throw new RuntimeException(sprintf(
                "MotosImporter: marque invalide '%s' (attendu : %s)",
                $marque, implode(', ', MsMoto::MARQUES)
            ));
        }

        $reader = new CsvReader($csvPath);
        $report = new MotosImportReport($csvPath, $marque);
        $seen   = [];

        foreach ($reader->rows() as $row) {
            $report->incRowsRead();
            $built = self::buildRow($row, $marque);
            if ($built === null) {
                $report->incRowsSkipped();
                continue;
            }
            $mn = $built['modelnumber'];
            if (isset($seen[$mn])) {
                $report->incRowsDeduped();
                continue;
            }
            $seen[$mn] = true;

            if ($built['type'] === MotosTaxonomy::TYPE_AUTRES) {
                $report->addAutres($mn, $built['core_name']);
            }

            $this->upsert($built, $report);
        }

        $report->finish();
        return $report;
    }

    /**
     * Construit la row prête à insérer depuis un row CSV brut, ou null si invalide.
     *
     * Pure : pas de dépendance Presta. Testable.
     *
     * @param array<string, string|null> $csvRow Keys normalisées par CsvReader.
     * @return array<string, mixed>|null
     */
    public static function buildRow(array $csvRow, string $marque): ?array
    {
        $mn = trim((string) ($csvRow['modelnumber'] ?? ''));
        if (strncmp($mn, self::MODELNUMBER_PREFIX, strlen(self::MODELNUMBER_PREFIX)) !== 0) {
            return null;
        }

        $anneeRaw = trim((string) ($csvRow['annee'] ?? ''));
        if (!ctype_digit($anneeRaw)) {
            return null;
        }
        $annee = (int) $anneeRaw;
        if ($annee < self::MIN_YEAR || $annee > self::MAX_YEAR) {
            return null;
        }

        $categoryFr = trim((string) ($csvRow['category_fr'] ?? ''));
        $nomFr      = trim((string) ($csvRow['model_name_fr'] ?? ''));
        if ($categoryFr === '' || $nomFr === '') {
            return null;
        }

        $serial = trim((string) ($csvRow['article_number'] ?? ''));

        // Filtre cross-badging CFMOTO / FTI : ces motos sont fabriquees pour
        // d'autres marches que l'europeen, le client MSS les considere comme
        // hors perimetre. Une moto europeenne equivalente existe deja en base
        // (serial obtenu en retirant le suffixe et ajoutant 'F' devant) — pas
        // besoin de la creer en plus. Decision actee avec le client le 2026-06-05.
        foreach (self::BLACKLISTED_SERIAL_SUFFIXES as $suffix) {
            if (substr($serial, -strlen($suffix)) === $suffix) {
                return null;
            }
        }

        $coreName  = MotosTaxonomy::coreName($categoryFr);
        if ($coreName === '') {
            $coreName = $categoryFr;
        }
        $type       = MotosTaxonomy::type($coreName);
        $cylindree  = MotosTaxonomy::cylindree($coreName);
        $isElectric = MotosTaxonomy::isElectric($type);
        $picture    = trim((string) ($csvRow['picture'] ?? ''));
        $textFr     = trim((string) ($csvRow['text_fr'] ?? ''));

        return [
            'modelnumber'         => $mn,
            'serial_constructeur' => $serial !== '' ? $serial : null,
            'marque'              => $marque,
            'annee'               => $annee,
            'category_fr'         => $categoryFr,
            'core_name'           => $coreName,
            'type'                => $type,
            'cylindree'           => $cylindree,
            'is_electric'         => $isElectric ? 1 : 0,
            'nom_fr'              => $nomFr,
            'description_fr'      => $textFr !== '' ? $textFr : null,
            'picture_main'        => $picture !== '' ? $picture : null,
        ];
    }

    /**
     * Déduit la marque depuis le nom de fichier (KTM_..., HQV_..., GASGAS_...).
     */
    public static function deduceMarque(string $csvPath): string
    {
        $name = strtoupper(basename($csvPath));
        foreach (MsMoto::MARQUES as $m) {
            if (strpos($name, $m) !== false) {
                return $m;
            }
        }
        throw new RuntimeException(sprintf(
            "MotosImporter: marque indéterminable depuis le nom de fichier '%s' (attendu : KTM_*, HQV_* ou GASGAS_*)",
            basename($csvPath)
        ));
    }

    /**
     * INSERT ... ON DUPLICATE KEY UPDATE sur ps_ms_moto.
     *
     * UPDATE n'inclut pas : modelnumber (clé), id_moto, date_add, active,
     * picture_cycle, picture_moteur.
     */
    private function upsert(array $data, MotosImportReport $report): void
    {
        $db    = Db::getInstance();
        $table = _DB_PREFIX_ . 'ms_moto';

        $cols   = array_keys($data);
        $values = [];
        foreach ($cols as $c) {
            $v = $data[$c];
            if ($v === null) {
                $values[] = 'NULL';
            } elseif (is_int($v)) {
                $values[] = (string) $v;
            } else {
                $values[] = "'" . pSQL((string) $v, true) . "'";
            }
        }
        $colList = '`' . implode('`, `', $cols) . '`';
        $valList = implode(', ', $values);

        // Colonnes à mettre à jour sur conflit (toutes sauf modelnumber).
        $updates = [];
        foreach ($cols as $c) {
            if ($c === 'modelnumber') {
                continue;
            }
            $updates[] = "`$c` = VALUES(`$c`)";
        }
        $updates[] = '`date_upd` = NOW()';
        $updatesSql = implode(', ', $updates);

        $sql = "INSERT INTO `$table` ($colList, `date_add`, `date_upd`) "
             . "VALUES ($valList, NOW(), NOW()) "
             . "ON DUPLICATE KEY UPDATE $updatesSql";

        if (!$db->execute($sql)) {
            $report->addError($data['modelnumber'], $db->getMsgError());
            return;
        }
        // MySQL : Affected_Rows = 1 pour INSERT, 2 pour UPDATE qui change, 0 pour UPDATE no-op.
        $affected = (int) $db->Affected_Rows();
        if ($affected === 1) {
            $report->incRowsInserted();
        } else {
            $report->incRowsUpdated();
        }
    }
}
