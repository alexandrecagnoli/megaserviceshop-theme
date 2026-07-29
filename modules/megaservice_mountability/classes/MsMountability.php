<?php
/**
 * Megaservice — Montabilité produit ↔ moto.
 *
 * Établit qu'un produit (accessoire PowerParts / SuperParts) est compatible
 * avec un modèle de moto. Suite à l'abandon d'EveryParts, la montabilité est
 * internalisée : ce module en est désormais LA source de vérité. (L'OEM, lui,
 * passe par les microfiches — module megaservice_microfiches.)
 *
 * PRINCIPE PROJET (identique à replacement / microfiches) : on stocke des
 * RÉFÉRENCES constructeur, jamais des id PrestaShop. La résolution
 * réf → id_product / id_moto se fait à l'EXÉCUTION :
 *   - une réf absente du catalogue aujourd'hui est stockée quand même ; elle
 *     devient active dès que le produit est importé, sans retraiter le fichier.
 *
 * ObjectModel minimal + méthodes de service. Le module N'intègre PAS le front
 * lui-même au-delà du bloc « Motos compatibles » : le hub moto consommera
 * getCompatibleProducts() depuis le chantier microfiches.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MsMountability extends ObjectModel
{
    /** @var string Référence constructeur (produit ou déclinaison). */
    public $reference;

    /** @var string Identifiant moto du fichier constructeur. */
    public $id_moto_constructeur;

    /** @var string Marque (permet le rechargement par marque). */
    public $marque;

    public $date_add;

    public static $definition = [
        'table'   => 'ms_mountability',
        'primary' => 'id_ms_mountability',
        'fields'  => [
            'reference'            => ['type' => self::TYPE_STRING, 'validate' => 'isAnything', 'required' => true, 'size' => 64],
            'id_moto_constructeur' => ['type' => self::TYPE_STRING, 'validate' => 'isAnything', 'required' => true, 'size' => 64],
            'marque'               => ['type' => self::TYPE_STRING, 'validate' => 'isAnything', 'required' => true, 'size' => 16],
            'date_add'             => ['type' => self::TYPE_DATE,   'validate' => 'isDate'],
        ],
    ];

    /**
     * Colonne de ms_moto sur laquelle se résout `id_moto_constructeur`.
     *
     * CONFIRMÉ sur échantillon réel (montabilite_ktm) : la colonne « id_moto »
     * du fichier contient des SERIALS constructeur (F8401W5, 6301H0, F9903N2…),
     * pas le modelnumber d'enrichissement ($M-…). C'est le même identifiant que
     * `ms_moto.serial_constructeur` (cf. importer microfiches, qui pivote lui
     * aussi les microfiches sur cette colonne).
     *
     * ⚠️ Cardinalité : le constructeur émet un serial par variante
     * moteur/couleur, plus fin que ms_moto (1 ligne = 1 modèle/année, 1 serial
     * représentatif). Certains serials du fichier n'ont donc pas de ligne
     * ms_moto → comptés dans `unresolved_motos` du rapport d'import. C'est
     * attendu ; le SELECT DISTINCT allume la moto dès qu'UN serial de la famille
     * produit matche.
     */
    const MOTO_JOIN_COLUMN = 'serial_constructeur';

    // ─────────────────────────────────────────────────────────────────────────
    // Service : produit → motos compatibles
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Motos compatibles avec une référence produit.
     *
     * Si la référence est une déclinaison d'un produit à déclinaisons, on fait
     * l'UNION des motos de TOUTES les déclinaisons du parent : la compatibilité
     * s'affiche roll-upée au niveau du produit, pas de la seule variante.
     *
     * @return array<int,array{id_moto:int,label:string,marque:string,url:string}>
     */
    public static function getCompatibleMotos($reference)
    {
        $reference = trim((string) $reference);
        if ($reference === '') {
            return [];
        }

        $refs = self::productFamilyReferences($reference);
        $in   = implode(',', array_map(static function ($r) {
            return '"' . pSQL($r) . '"';
        }, $refs));

        $rows = Db::getInstance()->executeS(
            'SELECT DISTINCT mo.`id_moto`, mo.`nom_fr`, mo.`core_name`, mo.`marque`
             FROM `' . _DB_PREFIX_ . 'ms_mountability` c
             INNER JOIN `' . _DB_PREFIX_ . 'ms_moto` mo
                     ON mo.`' . bqSQL(self::MOTO_JOIN_COLUMN) . '` = c.`id_moto_constructeur`
             WHERE c.`reference` IN (' . $in . ')
               AND mo.`active` = 1
             ORDER BY mo.`marque` ASC, mo.`core_name` ASC'
        ) ?: [];

        $link = Context::getContext()->link;
        $out  = [];
        foreach ($rows as $r) {
            $id  = (int) $r['id_moto'];
            $out[] = [
                'id_moto' => $id,
                'label'   => $r['nom_fr'] !== '' ? $r['nom_fr'] : $r['core_name'],
                'marque'  => $r['marque'],
                'url'     => $link->getModuleLink('megaservice_microfiches', 'moto', [
                    'id_moto' => $id,
                    'slug'    => Tools::str2url($r['nom_fr'] !== '' ? $r['nom_fr'] : $r['core_name']),
                ]),
            ];
        }

        return $out;
    }

    /**
     * Motos compatibles GROUPÉES par modèle, pour l'affichage « Compatible avec ».
     *
     * La donnée reste année par année (chaque moto-année est distincte) ; c'est
     * uniquement la présentation qui roll-up : une ligne = un modèle (marque +
     * core_name + type), avec la liste triée de ses années — chaque année
     * pointant vers sa page hub. Tri alphabétique des modèles.
     *
     * @return array<int,array{model:string,type:string,years:array<int,array{annee:int,url:string}>,search:string}>
     */
    public static function getCompatibleMotosGrouped($reference)
    {
        $reference = trim((string) $reference);
        if ($reference === '') {
            return [];
        }

        $refs = self::productFamilyReferences($reference);
        $in   = implode(',', array_map(static function ($r) {
            return '"' . pSQL($r) . '"';
        }, $refs));

        $rows = Db::getInstance()->executeS(
            'SELECT DISTINCT mo.`id_moto`, mo.`marque`, mo.`core_name`, mo.`nom_fr`, mo.`type`, mo.`annee`
             FROM `' . _DB_PREFIX_ . 'ms_mountability` c
             INNER JOIN `' . _DB_PREFIX_ . 'ms_moto` mo
                     ON mo.`' . bqSQL(self::MOTO_JOIN_COLUMN) . '` = c.`id_moto_constructeur`
             WHERE c.`reference` IN (' . $in . ')
               AND mo.`active` = 1'
        ) ?: [];

        $link   = Context::getContext()->link;
        $groups = [];
        foreach ($rows as $r) {
            $model = trim($r['marque'] . ' ' . $r['core_name']);
            $type  = (string) $r['type'];
            $key   = $model . '|' . $type;
            if (!isset($groups[$key])) {
                $groups[$key] = ['model' => $model, 'type' => $type, 'years' => []];
            }
            $annee = (int) $r['annee'];
            // Une seule entrée par année (les variantes couleur d'une même
            // moto-année collapsent) ; on garde le premier hub rencontré.
            if ($annee > 0 && !isset($groups[$key]['years'][$annee])) {
                $label = $r['nom_fr'] !== '' ? $r['nom_fr'] : $r['core_name'];
                $groups[$key]['years'][$annee] = $link->getModuleLink('megaservice_microfiches', 'moto', [
                    'id_moto' => (int) $r['id_moto'],
                    'slug'    => Tools::str2url($label),
                ]);
            }
        }

        $out = [];
        foreach ($groups as $g) {
            ksort($g['years']); // années croissantes
            $years = [];
            foreach ($g['years'] as $annee => $url) {
                $years[] = ['annee' => $annee, 'url' => $url];
            }
            $out[] = [
                'model'  => $g['model'],
                'type'   => $g['type'],
                'years'  => $years,
                'search' => Tools::strtolower($g['model'] . ' ' . $g['type'] . ' ' . implode(' ', array_keys($g['years']))),
            ];
        }

        usort($out, static function ($a, $b) {
            return strcasecmp($a['model'], $b['model']);
        });

        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Service : moto → produits compatibles (consommé par le hub moto)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * id_product compatibles avec une moto (notre id_moto PrestaShop), prêts à
     * être rendus en miniatures natives du thème.
     *
     * @return int[]
     */
    public static function getCompatibleProducts($idMoto)
    {
        $idMoto = (int) $idMoto;
        if (!$idMoto) {
            return [];
        }

        // Notre PK → identifiant constructeur (clé du fichier de montabilité).
        $motoRef = Db::getInstance()->getValue(
            'SELECT `' . bqSQL(self::MOTO_JOIN_COLUMN) . '`
             FROM `' . _DB_PREFIX_ . 'ms_moto`
             WHERE `id_moto` = ' . $idMoto
        );
        if ($motoRef === false || $motoRef === null || $motoRef === '') {
            return [];
        }

        // Références compatibles → id_product (produit OU déclinaison).
        $rows = Db::getInstance()->executeS(
            'SELECT DISTINCT p.`id_product`
             FROM `' . _DB_PREFIX_ . 'ms_mountability` c
             INNER JOIN `' . _DB_PREFIX_ . 'product` p ON p.`reference` = c.`reference`
             WHERE c.`id_moto_constructeur` = "' . pSQL($motoRef) . '"
             UNION
             SELECT DISTINCT pa.`id_product`
             FROM `' . _DB_PREFIX_ . 'ms_mountability` c
             INNER JOIN `' . _DB_PREFIX_ . 'product_attribute` pa ON pa.`reference` = c.`reference`
             WHERE c.`id_moto_constructeur` = "' . pSQL($motoRef) . '"'
        ) ?: [];

        return array_map('intval', array_column($rows, 'id_product'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SEO : moto active (URL source de vérité) + URL filtrée
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * id_moto du filtre actif. L'URL (`?moto=2587-slug`) est SOURCE DE VÉRITÉ ;
     * le cookie « garage » n'est que le secours. Quand la moto vient de l'URL, on
     * réarme le cookie (cohérence navigation). 0 si aucun filtre.
     *
     * Partagé par l'override CategoryController et le hook facetedsearch pour que
     * lecture et priorité soient identiques des deux côtés.
     */
    public static function resolveActiveMoto()
    {
        $ctx = Context::getContext();

        $raw = Tools::getValue('moto');
        if ($raw !== false && $raw !== null && $raw !== '') {
            $id = (int) $raw; // "2587-ktm-1290..." → 2587
            if ($id > 0) {
                if ((int) $ctx->cookie->ms_moto !== $id) {
                    $ctx->cookie->ms_moto = $id; // réarme le garage
                }
                return $id;
            }
        }

        return (int) $ctx->cookie->ms_moto;
    }

    /** Slug SEO d'une moto : « marque modèle année » (cosmétique, routing id-based). */
    public static function motoSlug($idMoto)
    {
        $row = Db::getInstance()->getRow(
            'SELECT `marque`, `core_name`, `annee`
             FROM `' . _DB_PREFIX_ . 'ms_moto` WHERE `id_moto` = ' . (int) $idMoto
        );
        if (!$row) {
            return '';
        }

        return Tools::str2url(trim($row['marque'] . ' ' . $row['core_name'] . ' ' . $row['annee']));
    }

    /** URL catégorie filtrée sur une moto : `/{cat}?moto=id-slug`. */
    public static function motoFilteredCategoryUrl($idCategory, $idMoto)
    {
        $base  = Context::getContext()->link->getCategoryLink((int) $idCategory);
        $slug  = self::motoSlug((int) $idMoto);
        $token = (int) $idMoto . ($slug !== '' ? '-' . $slug : '');

        return $base . (strpos($base, '?') !== false ? '&' : '?') . 'moto=' . rawurlencode($token);
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Toutes les références de la « famille » d'un produit : la fiche parente
     * + ses déclinaisons. Permet le roll-up de la montabilité au produit.
     *
     * Si la référence n'existe pas au catalogue (produit pas encore importé),
     * on la renvoie telle quelle : la montabilité reste consultable dès que la
     * fiche existe.
     *
     * @return string[]
     */
    private static function productFamilyReferences($reference)
    {
        $db  = Db::getInstance();
        $ref = pSQL($reference);

        $idProduct = (int) $db->getValue(
            'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product` WHERE `reference` = "' . $ref . '"'
        );
        if (!$idProduct) {
            $idProduct = (int) $db->getValue(
                'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product_attribute` WHERE `reference` = "' . $ref . '"'
            );
        }
        if (!$idProduct) {
            return [$reference];
        }

        $refs = [];
        $parent = $db->getValue(
            'SELECT `reference` FROM `' . _DB_PREFIX_ . 'product` WHERE `id_product` = ' . $idProduct
        );
        if ($parent !== false && $parent !== null && $parent !== '') {
            $refs[] = $parent;
        }
        foreach ($db->executeS(
            'SELECT `reference` FROM `' . _DB_PREFIX_ . 'product_attribute`
             WHERE `id_product` = ' . $idProduct . ' AND `reference` <> ""'
        ) ?: [] as $r) {
            $refs[] = $r['reference'];
        }

        $refs = array_values(array_unique(array_filter($refs)));

        return empty($refs) ? [$reference] : $refs;
    }
}
