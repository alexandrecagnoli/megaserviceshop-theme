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

    /** Variante statique (quand on n'a pas d'objet MsMoto hydraté, ex. requête groupée). */
    public static function buildSlug($marque, $coreName, $annee, $type)
    {
        return Tools::str2url(trim($marque . ' ' . $coreName . ' ' . $annee . ' ' . $type));
    }
}
