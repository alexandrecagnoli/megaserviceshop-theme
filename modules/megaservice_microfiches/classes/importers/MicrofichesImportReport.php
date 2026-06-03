<?php
/**
 * Rapport d'exécution d'un import microfiches (1 CSV = 1 moto).
 *
 * Compte les rows lues / créées / mises à jour / skippées sur les 3 niveaux
 * (catégorie, microfiche, hotspot) + collecte erreurs SQL et catégories
 * auto-créées (à renommer en BO — cf. brief §6.1.E).
 */
class MicrofichesImportReport
{
    /** @var string */
    public $csvPath;
    /** @var string Serial moto déduit du nom de fichier (= ms_moto.serial_constructeur). */
    public $motoSerial;
    /** @var int|null id_moto résolu en BDD (null si moto pivot introuvable). */
    public $motoId = null;

    /** @var int Hotspots itérés dans le CSV (toutes, header exclu). */
    public $rowsRead = 0;
    /** @var int Hotspots skippés (vue_eclatee_type inconnu, données invalides). */
    public $rowsSkipped = 0;

    /** @var int Catégories créées (avec code TODO_<partie>_<num>). */
    public $categoriesCreated = 0;
    /** @var int Catégories déjà existantes (réutilisées). */
    public $categoriesReused = 0;

    /** @var int Microfiches nouvelles insérées. */
    public $microfichesInserted = 0;
    /** @var int Microfiches existantes mises à jour (réimport). */
    public $microfichesUpdated = 0;

    /** @var int Hotspots nouveaux insérés. */
    public $hotspotsInserted = 0;
    /** @var int Hotspots existants mis à jour (réimport). */
    public $hotspotsUpdated = 0;

    /** @var array<int, array{level: string, key: string, error: string}> */
    public $errors = [];

    /** @var array<int, array{partie: string, numero: int, code: string}> Catégories auto-créées à compléter en BO. */
    public $categoriesAutoCreated = [];

    /** @var float */
    private $startedAt;
    /** @var float|null */
    private $finishedAt = null;

    public function __construct(string $csvPath, string $motoSerial)
    {
        $this->csvPath    = $csvPath;
        $this->motoSerial = $motoSerial;
        $this->startedAt  = microtime(true);
    }

    public function setMotoId(?int $id): void           { $this->motoId = $id; }
    public function incRowsRead(): void                 { $this->rowsRead++; }
    public function incRowsSkipped(): void              { $this->rowsSkipped++; }
    public function incCategoriesCreated(): void        { $this->categoriesCreated++; }
    public function incCategoriesReused(): void         { $this->categoriesReused++; }
    public function incMicrofichesInserted(): void      { $this->microfichesInserted++; }
    public function incMicrofichesUpdated(): void       { $this->microfichesUpdated++; }
    public function incHotspotsInserted(): void         { $this->hotspotsInserted++; }
    public function incHotspotsUpdated(): void          { $this->hotspotsUpdated++; }

    public function addError(string $level, string $key, string $error): void
    {
        $this->errors[] = ['level' => $level, 'key' => $key, 'error' => $error];
    }

    public function addAutoCreatedCategory(string $partie, int $numero, string $code): void
    {
        $this->categoriesAutoCreated[] = ['partie' => $partie, 'numero' => $numero, 'code' => $code];
    }

    public function finish(): void
    {
        $this->finishedAt = microtime(true);
    }

    public function durationMs(): int
    {
        $end = $this->finishedAt ?? microtime(true);
        return (int) (($end - $this->startedAt) * 1000);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'csv'                   => basename($this->csvPath),
            'moto_serial'           => $this->motoSerial,
            'moto_id'               => $this->motoId,
            'rows_read'             => $this->rowsRead,
            'rows_skipped'          => $this->rowsSkipped,
            'categories_created'    => $this->categoriesCreated,
            'categories_reused'     => $this->categoriesReused,
            'microfiches_inserted'  => $this->microfichesInserted,
            'microfiches_updated'   => $this->microfichesUpdated,
            'hotspots_inserted'     => $this->hotspotsInserted,
            'hotspots_updated'      => $this->hotspotsUpdated,
            'errors'                => count($this->errors),
            'auto_created'          => count($this->categoriesAutoCreated),
            'duration_ms'           => $this->durationMs(),
        ];
    }
}
