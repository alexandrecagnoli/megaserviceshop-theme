<?php
/**
 * Ingestion d'un CSV microfiches (1 fichier = 1 moto) → tables ms_microfiche_*.
 *
 * Spec : docs/microfiches/BRIEF.md §4.3, §4.4, §6.1.E.
 *
 * Pipeline :
 *   1. CsvReader   : encodage + délimiteur + headers normalisés
 *   2. Pivot moto  : SELECT id_moto FROM ps_ms_moto WHERE serial_constructeur=?
 *                    Si introuvable → skip TOUTE l'import (compteur dans rapport).
 *   3. Pour chaque row (= 1 hotspot) :
 *      a. partieFromVueEclateeType (engine→moteur, frame→cycle).
 *         Si type inconnu → skip row.
 *      b. Resolve catégorie (cache mémoire + auto-création si absente avec
 *         code=TODO_<partie>_<num>, nom_fr=NULL — brief §6.1.E).
 *      c. Upsert microfiche (cache + INSERT ON DUPLICATE KEY UPDATE,
 *         clé naturelle UNIQUE(id_moto, id_categorie, nom_constructeur)).
 *      d. Upsert hotspot (INSERT ON DUPLICATE KEY UPDATE,
 *         clé UNIQUE(id_microfiche, article_ref, sequence_number)).
 *
 * Cache mémoire (catégories + microfiches) : évite des centaines de SELECT
 * redondants (sur 1466 hotspots groupés en 45 microfiches sur 25 catégories,
 * on passe de ~3000 requêtes à ~150).
 *
 * Idempotence : ON DUPLICATE KEY UPDATE partout → id_microfiche stable
 * (les hotspots ne perdent pas leur lien). L'admin peut réimporter sans
 * casser le BO.
 */
class MicrofichesImporter
{
    /** Cache catégories : "partie:numero" => id_categorie */
    private $catCache = [];
    /** Cache microfiches : "id_moto:id_categorie:nom_constructeur" => id_microfiche */
    private $micCache = [];

    /**
     * Importe un CSV microfiches complet.
     *
     * @param string      $csvPath     Chemin absolu vers le CSV.
     * @param string|null $motoSerial  Si null, déduit depuis le nom de fichier (sans .csv).
     */
    public function importFile(string $csvPath, ?string $motoSerial = null): MicrofichesImportReport
    {
        if ($motoSerial === null) {
            $motoSerial = self::deduceMotoSerial($csvPath);
        }
        $report = new MicrofichesImportReport($csvPath, $motoSerial);

        $motoId = $this->resolveMotoId($motoSerial);
        $report->setMotoId($motoId);
        if ($motoId === null) {
            $report->addError('moto', $motoSerial, 'Moto introuvable (serial_constructeur absent de ps_ms_moto)');
            $report->finish();
            return $report;
        }

        $reader = new CsvReader($csvPath);

        foreach ($reader->rows() as $row) {
            $report->incRowsRead();
            $built = self::buildRow($row);
            if ($built === null) {
                $report->incRowsSkipped();
                continue;
            }

            $idCategorie = $this->resolveCategoryId($built['partie'], $built['numero_constructeur'], $report);
            if ($idCategorie === null) {
                $report->incRowsSkipped();
                continue;
            }

            $idMicrofiche = $this->upsertMicrofiche($motoId, $idCategorie, $built, $report);
            if ($idMicrofiche === null) {
                $report->incRowsSkipped();
                continue;
            }

            $this->upsertHotspot($idMicrofiche, $built, $report);
        }

        $report->finish();
        return $report;
    }

    /**
     * Déduit le serial moto depuis le nom de fichier (sans extension).
     * Ex : "/path/F0403X7.csv" → "F0403X7"
     */
    public static function deduceMotoSerial(string $csvPath): string
    {
        return pathinfo($csvPath, PATHINFO_FILENAME);
    }

    /**
     * Transforme une row CSV brute en row exploitable, ou null si invalide.
     *
     * Pure : pas de dépendance Presta. Testable.
     *
     * @param array<string, string|null> $csvRow Keys normalisées par CsvReader.
     * @return array<string, mixed>|null
     */
    public static function buildRow(array $csvRow): ?array
    {
        // -- Mapping partie (engine/frame → moteur/cycle) -----------------
        $partie = MicrofichesTaxonomy::partieFromVueEclateeType(
            (string) ($csvRow['vue_eclatee_type'] ?? '')
        );
        if ($partie === null) {
            return null;
        }

        $numeroRaw = trim((string) ($csvRow['vue_eclatee_number'] ?? ''));
        if (!ctype_digit($numeroRaw)) {
            return null;
        }
        $numero = (int) $numeroRaw;

        $nomConstructeur = trim((string) ($csvRow['vue_eclatee'] ?? ''));
        if ($nomConstructeur === '') {
            return null;
        }

        $imageFullUrl = trim((string) ($csvRow['vue_eclatee_image'] ?? ''));
        if ($imageFullUrl === '') {
            return null; // une microfiche sans URL d'image n'a aucun sens
        }
        $imageThumbUrl = trim((string) ($csvRow['vue_eclatee_image_preview'] ?? ''));

        $width  = self::parseIntOrNull($csvRow['vue_eclatee_image_width']  ?? null);
        $height = self::parseIntOrNull($csvRow['vue_eclatee_image_height'] ?? null);

        $articleRef = trim((string) ($csvRow['article_id'] ?? ''));
        if ($articleRef === '') {
            return null;
        }

        $sequenceNumberRaw = trim((string) ($csvRow['sequence_number'] ?? ''));
        if (!ctype_digit($sequenceNumberRaw)) {
            return null;
        }
        $sequenceNumber = (int) $sequenceNumberRaw;

        $posX = self::parseIntOrNull($csvRow['position_left']   ?? null);
        $posY = self::parseIntOrNull($csvRow['position_bottom'] ?? null);
        if ($posX === null || $posY === null) {
            return null;
        }

        $articleLabel = trim((string) ($csvRow['article'] ?? ''));
        $qty          = self::parseIntOrNull($csvRow['quantity'] ?? null);

        return [
            // catégorie
            'partie'              => $partie,
            'numero_constructeur' => $numero,
            // microfiche
            'nom_constructeur'    => $nomConstructeur,
            'image_full_url'      => $imageFullUrl,
            'image_thumb_url'     => $imageThumbUrl !== '' ? $imageThumbUrl : null,
            'image_width'         => $width,
            'image_height'        => $height,
            // hotspot
            'article_ref'         => $articleRef,
            'article_label'       => $articleLabel !== '' ? $articleLabel : null,
            'sequence_number'     => $sequenceNumber,
            'position_x'          => $posX,
            'position_y'          => $posY,
            'qty_recommended'     => $qty !== null ? max(0, min(255, $qty)) : 1,
        ];
    }

    private static function parseIntOrNull($value): ?int
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }
        // accepte aussi "0", "12", etc. mais pas "12.5"
        if (!preg_match('/^-?\d+$/', $s)) {
            return null;
        }
        return (int) $s;
    }

    // ======================================================================
    // Résolution / upsert SQL (dépendent de Presta)
    // ======================================================================

    private function resolveMotoId(string $serial): ?int
    {
        // NB : Db::getValue() ajoute automatiquement LIMIT 1 → ne PAS le mettre ici.
        $sql = 'SELECT `id_moto` FROM `' . _DB_PREFIX_ . 'ms_moto` '
             . "WHERE `serial_constructeur` = '" . pSQL($serial) . "'";
        $val = Db::getInstance()->getValue($sql);
        return ($val === false || $val === null || $val === '') ? null : (int) $val;
    }

    private function resolveCategoryId(string $partie, int $numero, MicrofichesImportReport $report): ?int
    {
        $key = "$partie:$numero";
        if (isset($this->catCache[$key])) {
            return $this->catCache[$key];
        }

        $db    = Db::getInstance();
        $table = _DB_PREFIX_ . 'ms_microfiche_categorie';
        // NB : Db::getValue() ajoute automatiquement LIMIT 1 → ne PAS le mettre ici.
        $sql   = "SELECT `id_categorie` FROM `$table` "
               . "WHERE `partie` = '" . pSQL($partie) . "' AND `numero_constructeur` = $numero";
        $val   = $db->getValue($sql);
        if ($val !== false && $val !== null && $val !== '') {
            $report->incCategoriesReused();
            return $this->catCache[$key] = (int) $val;
        }

        // Auto-création (brief §6.1.E) : code TODO_<partie>_<num>, nom_fr NULL
        $code = MicrofichesTaxonomy::autoCreatedCode($partie, $numero);
        $ok   = $db->execute(
            "INSERT INTO `$table` (`partie`, `numero_constructeur`, `code`, `nom_fr`, `ordre_affichage`, `active`) "
            . "VALUES ('" . pSQL($partie) . "', $numero, '" . pSQL($code) . "', NULL, $numero, 1)"
        );
        if (!$ok) {
            $report->addError('categorie', $key, $db->getMsgError());
            return null;
        }
        $newId = (int) $db->Insert_ID();
        $report->incCategoriesCreated();
        $report->addAutoCreatedCategory($partie, $numero, $code);
        return $this->catCache[$key] = $newId;
    }

    private function upsertMicrofiche(int $motoId, int $catId, array $data, MicrofichesImportReport $report): ?int
    {
        $key = "$motoId:$catId:" . $data['nom_constructeur'];
        if (isset($this->micCache[$key])) {
            return $this->micCache[$key];
        }

        $db    = Db::getInstance();
        $table = _DB_PREFIX_ . 'ms_microfiche';

        $cols = [
            'id_moto'          => $motoId,
            'id_categorie'     => $catId,
            'nom_constructeur' => $data['nom_constructeur'],
            'image_full_url'   => $data['image_full_url'],
            'image_thumb_url'  => $data['image_thumb_url'],
            'image_width'      => $data['image_width'],
            'image_height'     => $data['image_height'],
        ];

        $sql = $this->buildUpsert($table, $cols, ['id_moto', 'id_categorie', 'nom_constructeur'], true);
        if (!$db->execute($sql)) {
            $report->addError('microfiche', $key, $db->getMsgError());
            return null;
        }
        $affected = (int) $db->Affected_Rows();
        if ($affected === 1) {
            $report->incMicrofichesInserted();
        } elseif ($affected === 2) {
            $report->incMicrofichesUpdated();
        }
        // Affected_Rows = 0 (UPDATE no-op) : pas de compteur, OK.

        // Récupérer l'id (Insert_ID ne marche que sur INSERT, pas sur UPDATE no-op).
        // NB : Db::getValue() ajoute automatiquement LIMIT 1 → ne PAS le mettre ici.
        $idMicrofiche = (int) $db->getValue(
            "SELECT `id_microfiche` FROM `$table` "
            . "WHERE `id_moto` = $motoId AND `id_categorie` = $catId "
            . "AND `nom_constructeur` = '" . pSQL($data['nom_constructeur']) . "'"
        );
        return $this->micCache[$key] = $idMicrofiche;
    }

    /**
     * SQL specialise (pas via buildUpsert generique) car on a besoin de logique
     * conditionnelle a base de IF(manually_edited = 1, ...) pour proteger les
     * modifications manuelles de l'admin contre l'ecrasement par un reimport
     * du CSV constructeur.
     *
     * PREREQUIS CLE (cf. migration 005) : la UNIQUE KEY uk_hotspot_naturel porte
     * sur (id_microfiche, article_ref, sequence_number, position_x_original,
     * position_y_original) -- la position CONSTRUCTEUR, PAS la position vivante.
     * C'est ce qui rend la protection effective : un drag manuel modifie
     * position_x/position_y mais pas les *_original, donc le reimport percute
     * toujours la bonne ligne -> ON DUPLICATE -> IF(manually_edited=1,...).
     * Si la cle portait sur les positions vivantes, un reimport apres drag ne
     * matcherait plus la ligne deplacee et creerait un doublon (bug corrige 005).
     *
     * Champs proteges par manually_edited :
     *   - position_x, position_y : preservees si flag = 1, sinon updatees CSV
     *   - qty_recommended        : idem
     *
     * Champs TOUJOURS updates au reimport (independants du flag) :
     *   - position_x_original, position_y_original : referencent toujours la
     *     derniere position CSV connue (= la cle naturelle). L'auto-assignation
     *     VALUES(position_x) sur match est un no-op (la cle a deja matche dessus).
     *   - article_label : libelle constructeur, peut evoluer
     *
     * Champ JAMAIS touche par l'import :
     *   - id_product : rempli par le cron de rematching produit ou
     *     manuellement par l'admin via AdminMsHotspots — l'import ne doit
     *     surtout pas remettre NULL un lien produit deja resolu
     */
    private function upsertHotspot(int $microficheId, array $data, MicrofichesImportReport $report): void
    {
        $db    = Db::getInstance();
        $table = _DB_PREFIX_ . 'ms_microfiche_hotspot';

        $articleRef   = pSQL((string) $data['article_ref'], true);
        $articleLabel = $data['article_label'] !== null
            ? "'" . pSQL((string) $data['article_label'], true) . "'"
            : 'NULL';
        $sequence = (int) $data['sequence_number'];
        $posX     = (int) $data['position_x'];
        $posY     = (int) $data['position_y'];
        $qty      = (int) $data['qty_recommended'];

        $sql = "INSERT INTO `$table` "
             . '(`id_microfiche`, `id_product`, `article_ref`, `article_label`, '
             . ' `sequence_number`, `position_x`, `position_y`, '
             . ' `position_x_original`, `position_y_original`, '
             . ' `qty_recommended`, `manually_edited`) '
             . "VALUES ($microficheId, NULL, '$articleRef', $articleLabel, "
             . " $sequence, $posX, $posY, "
             . " $posX, $posY, "  /* original = current au 1er insert */
             . " $qty, 0) "
             . 'ON DUPLICATE KEY UPDATE '
             . ' `article_label`       = VALUES(`article_label`), '
             . ' `position_x`          = IF(`manually_edited` = 1, `position_x`, VALUES(`position_x`)), '
             . ' `position_y`          = IF(`manually_edited` = 1, `position_y`, VALUES(`position_y`)), '
             . ' `position_x_original` = VALUES(`position_x`), '
             . ' `position_y_original` = VALUES(`position_y`), '
             . ' `qty_recommended`     = IF(`manually_edited` = 1, `qty_recommended`, VALUES(`qty_recommended`))';

        if (!$db->execute($sql)) {
            $report->addError('hotspot', "$microficheId:{$data['article_ref']}:{$data['sequence_number']}", $db->getMsgError());
            return;
        }
        $affected = (int) $db->Affected_Rows();
        if ($affected === 1) {
            $report->incHotspotsInserted();
        } elseif ($affected === 2) {
            $report->incHotspotsUpdated();
        }
    }

    /**
     * Construit un SQL "INSERT ... ON DUPLICATE KEY UPDATE" générique.
     * @param array<string, mixed> $data           colonne => valeur (null pour NULL SQL)
     * @param string[]             $keyCols        colonnes de la clé naturelle, exclues du UPDATE
     * @param bool                 $withDateColumns ajoute date_add (insert) + date_upd (insert+update)
     */
    private function buildUpsert(string $table, array $data, array $keyCols, bool $withDateColumns): string
    {
        $cols = array_keys($data);
        $vals = [];
        foreach ($cols as $c) {
            $v = $data[$c];
            if ($v === null) {
                $vals[] = 'NULL';
            } elseif (is_int($v)) {
                $vals[] = (string) $v;
            } else {
                $vals[] = "'" . pSQL((string) $v, true) . "'";
            }
        }
        $colList = '`' . implode('`, `', $cols) . '`';
        $valList = implode(', ', $vals);

        $updates = [];
        foreach ($cols as $c) {
            if (in_array($c, $keyCols, true)) {
                continue;
            }
            $updates[] = "`$c` = VALUES(`$c`)";
        }

        if ($withDateColumns) {
            $colList .= ', `date_add`, `date_upd`';
            $valList .= ', NOW(), NOW()';
            $updates[] = '`date_upd` = NOW()';
        }
        $updatesSql = implode(', ', $updates);

        return "INSERT INTO `$table` ($colList) VALUES ($valList) ON DUPLICATE KEY UPDATE $updatesSql";
    }
}
