<?php
/**
 * Megaservice — Import des fichiers de montabilité.
 *
 * Fichier : montabilite_<marque>.csv — séparateur « ; », UTF-8, colonnes
 *   reference ; id_moto ; marque
 * Un fichier par marque, COMPLET par construction (pas de delta).
 * Volumétrie : jusqu'à ~500 000 lignes (KTM).
 *
 * Stratégie : TRUNCATE & RELOAD par marque.
 *   DELETE WHERE marque = X, puis insertion en masse par paquets.
 * On lit le fichier en STREAMING (fgetcsv ligne à ligne) : jamais 500 k lignes
 * en mémoire. La normalisation d'une ligne est isolée (méthode pure) pour être
 * testable hors PrestaShop.
 *
 * Dédoublonnage : assuré par l'index UNIQUE (reference, id_moto_constructeur)
 * via INSERT IGNORE. Le nombre de doublons se déduit après coup (lignes valides
 * − lignes réellement en base), sans maintenir un set de 500 k clés en mémoire.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MsMountabilityImporter
{
    /** Lignes par requête d'insertion groupée. */
    const CHUNK = 2000;

    /**
     * @param string $path   fichier CSV
     * @param string $marque marque de rechargement (KTM / HQV / GG…)
     * @return array<string,mixed> rapport d'exécution
     */
    /**
     * Libellés rencontrés dans les fichiers constructeur → valeur du référentiel
     * `ms_moto.marque` (ENUM KTM / HQV / GASGAS).
     *
     * Sans cette normalisation, les fichiers Husqvarna s'écrivaient en base sous
     * « HUSQVARNA » alors que tout le reste du système dit « HQV ». Conséquences
     * observées en prod : rapport d'import à 0 % (countUnresolved interroge la
     * marque du formulaire), et purge de rechargement inopérante (le DELETE ne
     * percutait aucune ligne). Le front, lui, n'a jamais été touché : il joint
     * sur le serial et ignore la marque.
     */
    const MARQUE_ALIASES = [
        'KTM'       => 'KTM',
        'HQV'       => 'HQV',
        'HUSQVARNA' => 'HQV',
        'HUSKY'     => 'HQV',
        'GASGAS'    => 'GASGAS',
        'GAS GAS'   => 'GASGAS',
        'GAS-GAS'   => 'GASGAS',
        'GG'        => 'GASGAS',
    ];

    /**
     * Ramène un libellé de marque au référentiel, ou '' s'il n'en est pas un.
     *
     * Le '' est ce qui protège la table : l'importer acceptait auparavant
     * n'importe quelle chaîne, si bien que des fichiers au mauvais format (3e
     * colonne = nom de catégorie) ont chargé ~24 900 lignes sous des « marques »
     * telles que « PIÈCES DÉTACHÉES » ou « SUSPENSIONS WP », tronquées à 16
     * caractères par la colonne. Une marque inconnue vaut désormais rejet.
     */
    public static function normalizeMarque($raw)
    {
        $key = strtoupper(trim((string) $raw));
        $key = preg_replace('/\s+/', ' ', $key);

        return isset(self::MARQUE_ALIASES[$key]) ? self::MARQUE_ALIASES[$key] : '';
    }

    public static function import($path, $marque)
    {
        $marque = self::normalizeMarque($marque);
        $report = [
            'marque'            => $marque,
            'lines_read'        => 0,
            'rejected_format'   => 0,
            'valid'             => 0,
            'loaded'            => 0,
            'duplicates'        => 0,
            'unresolved_refs'   => 0,
            'distinct_serials'  => 0,
            'resolved_serials'  => 0,
            'motos_resolues'    => 0,
            'unresolved_motos'  => 0,
            'unresolved_moto_list' => [],
        ];

        if ($marque === '') {
            $report['error'] = 'Marque manquante ou inconnue (attendu : KTM, HQV/Husqvarna, GASGAS).';
            return $report;
        }
        $fh = @fopen($path, 'r');
        if ($fh === false) {
            $report['error'] = 'Fichier illisible.';
            return $report;
        }

        $db  = Db::getInstance();
        $now = date('Y-m-d H:i:s');

        // Rechargement : on efface la marque avant de réinsérer.
        $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'ms_mountability` WHERE `marque` = "' . pSQL($marque) . '"');

        $buffer = [];
        $first  = true;

        while (($raw = fgetcsv($fh, 0, ';', '"', '')) !== false) {
            // Ligne 1 = en-tête (reference;id_moto;marque). Détection tolérante
            // au BOM UTF-8 et à la casse → jamais importée comme donnée.
            if ($first) {
                $first = false;
                if (self::looksLikeHeader($raw)) {
                    continue;
                }
            }

            ++$report['lines_read'];

            $row = self::normalizeRow($raw, $marque);
            if ($row === null) {
                ++$report['rejected_format'];
                continue;
            }
            ++$report['valid'];

            $buffer[] = '("' . pSQL($row['reference']) . '","' . pSQL($row['id_moto_constructeur'])
                      . '","' . pSQL($row['marque']) . '","' . $now . '")';

            if (count($buffer) >= self::CHUNK) {
                self::flush($db, $buffer);
                $buffer = [];
            }
        }
        if (!empty($buffer)) {
            self::flush($db, $buffer);
        }
        fclose($fh);

        // Lignes réellement en base pour cette marque (l'index UNIQUE a dédoublonné).
        $report['loaded']     = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'ms_mountability` WHERE `marque` = "' . pSQL($marque) . '"'
        );
        $report['duplicates'] = max(0, $report['valid'] - $report['loaded']);

        self::countUnresolved($db, $marque, $report);

        return $report;
    }

    /**
     * Valide et normalise une ligne brute (3 colonnes). PURE : aucun accès base,
     * testable en CLI. Retourne null si la ligne est à rejeter.
     *
     * @param array<int,string|null> $raw
     * @return array{reference:string,id_moto_constructeur:string,marque:string}|null
     */
    public static function normalizeRow($raw, $marqueDefault = '')
    {
        if (!is_array($raw) || count($raw) < 2) {
            return null;
        }

        $reference = isset($raw[0]) ? self::stripBom(trim((string) $raw[0])) : '';
        $idMoto    = isset($raw[1]) ? trim((string) $raw[1]) : '';
        // La marque du fichier fait foi si présente, sinon celle du formulaire.
        // Elle est ramenée au référentiel ms_moto : une valeur hors référentiel
        // (nom de catégorie d'un fichier au mauvais format, en-tête résiduel)
        // rend la ligne invalide plutôt que de créer une pseudo-marque en base.
        $marque    = isset($raw[2]) && trim((string) $raw[2]) !== ''
            ? self::normalizeMarque($raw[2])
            : self::normalizeMarque($marqueDefault);

        if ($reference === '' || $idMoto === '' || $marque === '') {
            return null;
        }

        // Garde-fou : une ligne d'en-tête qui aurait échappé au skip (fichier
        // sans BOM standard, casse ou séparateur inattendu) ne doit JAMAIS
        // entrer en base comme donnée. On rejette les valeurs littérales.
        if (strcasecmp($reference, 'reference') === 0
            || strcasecmp($idMoto, 'id_moto') === 0
            || strcasecmp($idMoto, 'id_moto_constructeur') === 0
            || strcasecmp($marque, 'marque') === 0) {
            return null;
        }

        return [
            'reference'            => $reference,
            'id_moto_constructeur' => $idMoto,
            'marque'               => $marque,
        ];
    }

    /**
     * Détecte une ligne d'en-tête `reference;id_moto;marque`, tolérante au BOM
     * UTF-8 et à la casse (compare aux noms de colonnes attendus).
     *
     * @param array<int,string|null> $raw
     */
    private static function looksLikeHeader($raw)
    {
        if (!is_array($raw)) {
            return false;
        }
        $c0 = isset($raw[0]) ? strtolower(self::stripBom(trim((string) $raw[0]))) : '';
        $c1 = isset($raw[1]) ? strtolower(trim((string) $raw[1])) : '';
        $c2 = isset($raw[2]) ? strtolower(trim((string) $raw[2])) : '';

        // La 3e colonne compte aussi : un fichier dont les deux premières
        // colonnes portent des libellés inattendus (« ref;modele;marque ») ne
        // déclenchait aucun des tests ci-dessous et son en-tête entrait en base
        // comme une ligne de données. Constaté en prod : 1 ligne parasite à
        // `marque = 'MARQUE'` parmi 1,95 M — inoffensive, mais elle prouve que
        // reconnaître l'en-tête sur deux colonnes ne suffit pas.
        //
        // On s'en tient à des libellés qu'aucune donnée réelle ne peut porter :
        // élargir à « ref » / « modele » / « moto » ferait rejeter des valeurs
        // légitimes, le gain ne vaut pas ce risque.
        return $c0 === 'reference'
            || $c1 === 'id_moto'
            || $c1 === 'id_moto_constructeur'
            || $c2 === 'marque';
    }

    /** Retire un BOM UTF-8 en tête de chaîne s'il est présent. */
    private static function stripBom($s)
    {
        return strncmp((string) $s, "\xEF\xBB\xBF", 3) === 0 ? substr((string) $s, 3) : (string) $s;
    }

    /** Insertion groupée (IGNORE : l'index UNIQUE absorbe les doublons intra-fichier). */
    private static function flush(Db $db, array $buffer)
    {
        $db->execute(
            'INSERT IGNORE INTO `' . _DB_PREFIX_ . 'ms_mountability`
                (`reference`, `id_moto_constructeur`, `marque`, `date_add`)
             VALUES ' . implode(',', $buffer)
        );
    }

    /**
     * Diagnostic post-import.
     *
     * L'indicateur qui compte est `motos_resolues` : le nombre de MOTOS
     * distinctes qui s'allument. Le nombre de serials non résolus n'est PAS un
     * indicateur d'échec en soi : le constructeur émet un serial par variante
     * (couleur, assemblage moteur, CKD/export) alors que ms_moto ne garde qu'une
     * ligne par moto-année. Un serial non résolu dont la moto-année est déjà
     * allumée par un serial frère est sans conséquence sur l'affichage.
     *
     * On expose donc : serials distincts, serials résolus, motos allumées, et le
     * détail des non-résolus (plafonné à 200) pour repérer un vrai trou.
     */
    private static function countUnresolved(Db $db, $marque, array &$report)
    {
        $m    = pSQL($marque);
        $col  = bqSQL(MsMountability::MOTO_JOIN_COLUMN);
        $cTbl = _DB_PREFIX_ . 'ms_mountability';
        $mTbl = _DB_PREFIX_ . 'ms_moto';

        $report['unresolved_refs'] = (int) $db->getValue(
            'SELECT COUNT(DISTINCT c.`reference`)
             FROM `' . $cTbl . '` c
             LEFT JOIN `' . _DB_PREFIX_ . 'product` p            ON p.`reference` = c.`reference`
             LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa ON pa.`reference` = c.`reference`
             WHERE c.`marque` = "' . $m . '"
               AND p.`id_product` IS NULL AND pa.`id_product_attribute` IS NULL'
        );

        $report['distinct_serials'] = (int) $db->getValue(
            'SELECT COUNT(DISTINCT `id_moto_constructeur`) FROM `' . $cTbl . '`
             WHERE `marque` = "' . $m . '"'
        );

        // Serials du fichier ayant une ligne ms_moto (numérateur du taux).
        $report['resolved_serials'] = (int) $db->getValue(
            'SELECT COUNT(DISTINCT c.`id_moto_constructeur`)
             FROM `' . $cTbl . '` c
             INNER JOIN `' . $mTbl . '` mo ON mo.`' . $col . '` = c.`id_moto_constructeur`
             WHERE c.`marque` = "' . $m . '"'
        );

        // Le vrai indicateur : motos distinctes réellement allumées.
        $report['motos_resolues'] = (int) $db->getValue(
            'SELECT COUNT(DISTINCT mo.`id_moto`)
             FROM `' . $cTbl . '` c
             INNER JOIN `' . $mTbl . '` mo ON mo.`' . $col . '` = c.`id_moto_constructeur`
             WHERE c.`marque` = "' . $m . '"'
        );

        // Total réel de serials non résolus (non plafonné).
        $report['unresolved_motos'] = max(0, $report['distinct_serials'] - $report['resolved_serials']);

        // Détail plafonné (les plus fréquents d'abord) pour repérer un vrai trou.
        $report['unresolved_moto_list'] = $db->executeS(
            'SELECT c.`id_moto_constructeur`, COUNT(*) AS nb
             FROM `' . $cTbl . '` c
             LEFT JOIN `' . $mTbl . '` mo ON mo.`' . $col . '` = c.`id_moto_constructeur`
             WHERE c.`marque` = "' . $m . '" AND mo.`id_moto` IS NULL
             GROUP BY c.`id_moto_constructeur`
             ORDER BY nb DESC
             LIMIT 200'
        ) ?: [];
    }
}
