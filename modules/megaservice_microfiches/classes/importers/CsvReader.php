<?php
/**
 * Lecteur CSV avec détection auto de l'encodage source et du délimiteur,
 * et normalisation des entêtes de colonnes.
 *
 * Conçu comme utilitaire pur (aucune dépendance PrestaShop) → testable
 * unitairement et réutilisable entre MotosImporter et MicrofichesImporter.
 *
 * Encodage source détecté : UTF-8 (strict) ou ISO-8859-1 par défaut.
 *   - Le contenu est converti en UTF-8 en mémoire avant parsing.
 *   - Volumétrie max attendue : ~32 Mo (KTM_MOTORCYCLES.csv) → on charge tout
 *     en mémoire. Pour des fichiers plus gros, prévoir une stratégie streaming.
 *
 * Délimiteur détecté : ';' ',' "\t" '|' (celui qui apparaît le plus dans
 * la 1ère ligne).
 *
 * Normalisation header : lowercase + ASCII (strip accents) + non-alphanum→'_'
 * + déduplication. Exemples :
 *   "année"        → "annee"
 *   "ANNEE"        → "annee"
 *   "model name (FR)" → "model_name_fr"
 *
 * Usage :
 *   $reader = new CsvReader('/path/to/file.csv');
 *   foreach ($reader->rows() as $row) {
 *       // $row = ['modelnumber' => '$M-...', 'annee' => '2024', ...]
 *   }
 */
class CsvReader
{
    public const ENC_UTF8   = 'UTF-8';
    public const ENC_LATIN1 = 'ISO-8859-1';

    private const SNIFF_BYTES = 8192;
    private const DELIMITERS  = [';', ',', "\t", '|'];

    /** @var string */
    private $path;
    /** @var string */
    private $encoding;
    /** @var string */
    private $delimiter;
    /** @var string[] */
    private $headers;
    /** @var string|null Cache du contenu converti en UTF-8 (chargé à la 1ère ouverture). */
    private $contentUtf8;

    public function __construct(string $path, ?string $forcedEncoding = null, ?string $forcedDelimiter = null)
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("CsvReader: fichier introuvable ou non lisible: $path");
        }
        $this->path     = $path;
        $this->encoding = $forcedEncoding ?? $this->detectEncoding();
        // Délimiteur et headers nécessitent un stream en UTF-8 → on déclenche la conversion.
        $this->delimiter = $forcedDelimiter ?? $this->detectDelimiter();
        $this->headers   = $this->readHeaders();
    }

    public function getPath(): string      { return $this->path; }
    public function getEncoding(): string  { return $this->encoding; }
    public function getDelimiter(): string { return $this->delimiter; }

    /** @return string[] */
    public function getHeaders(): array    { return $this->headers; }

    /**
     * Itère sur les lignes de données (header skippé).
     * @return Generator<int, array<string, string|null>>
     */
    public function rows(): Generator
    {
        $handle = $this->openUtf8Stream();
        // Skip header
        fgetcsv($handle, 0, $this->delimiter, '"', '\\');

        $idx = 0;
        while (($values = fgetcsv($handle, 0, $this->delimiter, '"', '\\')) !== false) {
            // fgetcsv retourne [null] sur une ligne vide
            if ($values === [null]) {
                continue;
            }
            $row = [];
            foreach ($this->headers as $i => $name) {
                $v = $values[$i] ?? null;
                $row[$name] = is_string($v) ? $v : $v;
            }
            yield $idx++ => $row;
        }
        fclose($handle);
    }

    /**
     * Détecte l'encodage source en sniffant les premiers octets.
     * UTF-8 strict si valide, ISO-8859-1 sinon.
     */
    private function detectEncoding(): string
    {
        $sample = file_get_contents($this->path, false, null, 0, self::SNIFF_BYTES);
        if ($sample === false) {
            throw new RuntimeException("CsvReader: lecture sample impossible: {$this->path}");
        }
        return mb_check_encoding($sample, self::ENC_UTF8) ? self::ENC_UTF8 : self::ENC_LATIN1;
    }

    /**
     * Détecte le délimiteur dominant sur la 1ère ligne (déjà en UTF-8).
     */
    private function detectDelimiter(): string
    {
        $handle = $this->openUtf8Stream();
        $line   = fgets($handle);
        fclose($handle);
        if ($line === false) {
            throw new RuntimeException("CsvReader: fichier vide: {$this->path}");
        }
        $counts = [];
        foreach (self::DELIMITERS as $d) {
            $counts[$d] = substr_count($line, $d);
        }
        arsort($counts);
        $winner = array_key_first($counts);
        if ($counts[$winner] === 0) {
            throw new RuntimeException("CsvReader: aucun délimiteur (;,\\t|) détecté en 1ère ligne de {$this->path}");
        }
        return $winner;
    }

    /**
     * Lit la 1ère ligne et la normalise en clés exploitables.
     * @return string[]
     */
    private function readHeaders(): array
    {
        $handle = $this->openUtf8Stream();
        $raw    = fgetcsv($handle, 0, $this->delimiter, '"', '\\');
        fclose($handle);
        if ($raw === false) {
            throw new RuntimeException("CsvReader: header illisible: {$this->path}");
        }
        $out  = [];
        $seen = [];
        foreach ($raw as $h) {
            $key  = self::normalizeHeader((string) $h);
            $base = $key !== '' ? $key : 'col';
            $key  = $base;
            $i    = 2;
            while (isset($seen[$key])) {
                $key = $base . '_' . $i++;
            }
            $seen[$key] = true;
            $out[]      = $key;
        }
        return $out;
    }

    /**
     * Normalise un nom de colonne : lowercase + accents strippés + non-alphanum → '_'.
     * Public static pour pouvoir être testé / réutilisé.
     *
     * Note : on n'utilise PAS iconv('ASCII//TRANSLIT') — la libiconv BSD (macOS)
     * strip silencieusement les caractères accentués sans les convertir.
     * Table de remplacement explicite → déterministe et portable.
     */
    public static function normalizeHeader(string $header): string
    {
        $header = self::stripAccents(trim($header));
        $header = strtolower($header);
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? $header;
        return trim($header, '_');
    }

    private static function stripAccents(string $s): string
    {
        return strtr($s, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
            'ñ' => 'n', 'ç' => 'c',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ý' => 'Y',
            'Ñ' => 'N', 'Ç' => 'C',
        ]);
    }

    /**
     * Ouvre un stream php://temp contenant le CSV converti en UTF-8.
     * Le contenu source est lu une seule fois et caché en mémoire.
     * @return resource
     */
    private function openUtf8Stream()
    {
        if ($this->contentUtf8 === null) {
            $raw = file_get_contents($this->path);
            if ($raw === false) {
                throw new RuntimeException("CsvReader: lecture impossible: {$this->path}");
            }
            $this->contentUtf8 = ($this->encoding === self::ENC_UTF8)
                ? $raw
                : mb_convert_encoding($raw, self::ENC_UTF8, $this->encoding);
        }
        $handle = fopen('php://temp', 'r+');
        if (!$handle) {
            throw new RuntimeException('CsvReader: ouverture php://temp impossible');
        }
        fwrite($handle, $this->contentUtf8);
        rewind($handle);
        return $handle;
    }
}
