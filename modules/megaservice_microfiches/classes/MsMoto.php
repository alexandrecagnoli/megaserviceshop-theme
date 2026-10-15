<?php
/**
 * ObjectModel pour `ps_ms_moto` — 1 ligne = 1 modèle/année moto.
 * Clé naturelle = `modelnumber` (UNIQUE en base).
 *
 * Notes :
 * - `marque` et `type` sont des ENUM SQL. ObjectModel ne supporte pas nativement
 *   les ENUM ; la validation stricte des valeurs autorisées passe par les
 *   constantes MARQUES et TYPES ci-dessous (à appeler côté importer/controller).
 * - Les champs alimentés par CSV constructeur (modelnumber, nom_fr, core_name…)
 *   sont validés en `isAnything` : on contrôle la qualité de la source dans
 *   l'importer, pas à l'hydration.
 */
class MsMoto extends ObjectModel
{
    public $id_moto;
    public $modelnumber;
    public $serial_constructeur;
    public $marque;
    public $annee;
    public $category_fr;
    public $core_name;
    public $type;
    public $cylindree;
    public $is_electric = false;
    public $nom_fr;
    public $description_fr;
    public $picture_main;
    public $picture_cycle;
    public $picture_moteur;
    public $active = true;
    public $date_add;
    public $date_upd;

    public const MARQUES = ['KTM', 'HQV', 'GASGAS'];

    public const TYPES = [
        'Motocross',
        'Enduro',
        'Naked',
        'Adventure',
        'Supermoto',
        'Electrique',
        'Trial',
        'Autres',
    ];

    public static $definition = [
        'table'   => 'ms_moto',
        'primary' => 'id_moto',
        'fields'  => [
            'modelnumber'         => ['type' => self::TYPE_STRING, 'validate' => 'isAnything',    'required' => true, 'size' => 64],
            'serial_constructeur' => ['type' => self::TYPE_STRING, 'validate' => 'isAnything',    'size' => 32],
            'marque'              => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 8],
            'annee'               => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt', 'required' => true],
            'category_fr'         => ['type' => self::TYPE_STRING, 'validate' => 'isAnything',    'required' => true, 'size' => 255],
            'core_name'           => ['type' => self::TYPE_STRING, 'validate' => 'isAnything',    'required' => true, 'size' => 255],
            'type'                => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 16],
            'cylindree'           => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt'],
            'is_electric'         => ['type' => self::TYPE_BOOL,   'validate' => 'isBool'],
            'nom_fr'              => ['type' => self::TYPE_STRING, 'validate' => 'isAnything',    'required' => true, 'size' => 255],
            'description_fr'      => ['type' => self::TYPE_HTML,   'validate' => 'isCleanHtml'],
            'picture_main'        => ['type' => self::TYPE_STRING, 'validate' => 'isAnything',    'size' => 255],
            'picture_cycle'       => ['type' => self::TYPE_STRING, 'validate' => 'isAnything',    'size' => 255],
            'picture_moteur'      => ['type' => self::TYPE_STRING, 'validate' => 'isAnything',    'size' => 255],
            'active'              => ['type' => self::TYPE_BOOL,   'validate' => 'isBool'],
            'date_add'            => ['type' => self::TYPE_DATE,   'validate' => 'isDate'],
            'date_upd'            => ['type' => self::TYPE_DATE,   'validate' => 'isDate'],
        ],
    ];

    /**
     * Slug SEO enrichi et CANONIQUE d'une moto : marque + modèle + année + type.
     * Ex. « ktm-250-exc-f-2024-enduro ».
     *
     * Le routing microfiches est id-based → le slug est purement cosmétique
     * (un slug obsolète charge quand même la bonne page). On centralise ici pour
     * que TOUS les getModuleLink produisent le même slug enrichi.
     */
    public function slug()
    {
        return self::buildSlug($this->marque, $this->core_name, $this->annee, $this->type);
    }

    /**
     * URL de la page hub d'une moto. `moto=` EN PLUS de id_moto : c'est ce paramètre
     * — et lui seul — que MsMountability::resolveActiveMoto() lit pour ARMER le garage
     * (cookie ms_moto). Partagé par le sélecteur en cascade et la recherche par VIN :
     * deux copies de cette règle finiraient par diverger.
     */
    /**
     * URL publique de la photo du modèle, détourée si elle existe.
     *
     * Deux jeux de visuels coexistent sous img/ms_moto/ :
     *   1. `detoures/<nom>.webp` — fond transparent, indispensable sur les
     *      cartes et bandeaux sombres, et bien plus léger (113 Mo pour 1 755
     *      fichiers contre 375 Mo pour les originaux) ;
     *   2. `<picture_main>` — le fichier constructeur, nom et extension exacts.
     *
     * Le repli n'est pas une précaution de principe : 98 motos actives n'ont pas
     * de version détourée (déjà transparentes à la source, ou écartées du
     * traitement). Sans lui, autant de motos perdraient leur image.
     *
     * L'extension diffère entre les deux jeux (.png/.jpg → .webp) alors que la
     * base ne stocke que le nom d'origine : le détouré est dérivé en remplaçant
     * l'extension, jamais attendu du CSV.
     *
     * Rend '' si rien n'est disponible — l'appelant a sa réserve. Un fichier
     * absent doit donner une image par défaut, pas une image cassée.
     *
     * @param string $filename valeur de `picture_main`, nom de fichier nu
     */
    public static function pictureUrlFromFilename($filename)
    {
        $rel = trim((string) $filename);
        if ($rel === '' || strpos($rel, '..') !== false || strpos($rel, '/') !== false) {
            return '';
        }

        $candidates = [
            'detoures/' . pathinfo($rel, PATHINFO_FILENAME) . '.webp',
            $rel,
        ];

        foreach ($candidates as $candidate) {
            if (is_file(_PS_ROOT_DIR_ . '/img/ms_moto/' . $candidate)) {
                return __PS_BASE_URI__ . 'img/ms_moto/' . $candidate;
            }
        }

        return '';
    }

    /** Photo du modèle pour CETTE moto. Cf. pictureUrlFromFilename(). */
    public function mainPictureUrl()
    {
        return self::pictureUrlFromFilename($this->picture_main);
    }

    public static function hubUrl(Context $context, $idMoto, $marque, $coreName, $annee, $type)
    {
        $idMoto = (int) $idMoto;
        $slug   = self::buildSlug($marque, $coreName, $annee, $type);

        return $context->link->getModuleLink('megaservice_microfiches', 'moto', [
            'id_moto' => $idMoto,
            'slug'    => $slug,
            'moto'    => $idMoto . ($slug !== '' ? '-' . $slug : ''),
        ]);
    }

    /** Variante statique (quand on n'a pas d'objet MsMoto hydraté, ex. requête groupée). */
    public static function buildSlug($marque, $coreName, $annee, $type)
    {
        return Tools::str2url(trim($marque . ' ' . $coreName . ' ' . $annee . ' ' . $type));
    }
}
