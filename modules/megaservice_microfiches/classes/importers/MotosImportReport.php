<?php
/**
 * Rapport d'exécution d'un import motos (1 CSV).
 *
 * Compte les rows lues / insérées / mises à jour / dédupliquées / skippées,
 * collecte les erreurs SQL et les motos tombées en taxonomie 'Autres'
 * (pour correction manuelle en BO — brief §4.2).
 */
class MotosImportReport
{
    /** @var string */
    public $csvPath;
    /** @var string */
    public $marque;

    /** @var int Rows itérées dans le CSV (toutes, header exclu). */
    public $rowsRead = 0;
    /** @var int Rows ignorées car invalides (bruit HTML, MODELNUMBER manquant, etc.). */
    public $rowsSkipped = 0;
    /** @var int Doublons MODELNUMBER après la 1ère occurrence. */
    public $rowsDeduped = 0;
    /** @var int Nouvelles motos insérées. */
    public $rowsInserted = 0;
    /** @var int Motos existantes mises à jour. */
    public $rowsUpdated = 0;

    /** @var array<int, array{modelnumber: string, error: string}> */
    public $errors = [];

    /** @var array<int, array{modelnumber: string, core_name: string}> Motos en 'Autres'. */
    public $autresFound = [];

    /** @var float */
    private $startedAt;
    /** @var float|null */
    private $finishedAt = null;

    public function __construct(string $csvPath, string $marque)
    {
        $this->csvPath   = $csvPath;
        $this->marque    = $marque;
        $this->startedAt = microtime(true);
    }

    public function incRowsRead(): void     { $this->rowsRead++; }
    public function incRowsSkipped(): void  { $this->rowsSkipped++; }
    public function incRowsDeduped(): void  { $this->rowsDeduped++; }
    public function incRowsInserted(): void { $this->rowsInserted++; }
    public function incRowsUpdated(): void  { $this->rowsUpdated++; }

    public function addError(string $modelnumber, string $error): void
    {
        $this->errors[] = ['modelnumber' => $modelnumber, 'error' => $error];
    }

    public function addAutres(string $modelnumber, string $coreName): void
    {
        $this->autresFound[] = ['modelnumber' => $modelnumber, 'core_name' => $coreName];
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
            'csv'         => basename($this->csvPath),
            'marque'      => $this->marque,
            'read'        => $this->rowsRead,
            'inserted'    => $this->rowsInserted,
            'updated'     => $this->rowsUpdated,
            'deduped'     => $this->rowsDeduped,
            'skipped'     => $this->rowsSkipped,
            'errors'      => count($this->errors),
            'autres'      => count($this->autresFound),
            'duration_ms' => $this->durationMs(),
        ];
    }
}
