<?php
/**
 * ObjectModel pour `ps_ms_microfiche` — 1 vue éclatée d'une partie d'une moto.
 *
 * Clé naturelle (UNIQUE en base) : (id_moto, id_categorie, nom_constructeur).
 * Cette clé sert à l'idempotence des imports microfiches.
 */
class MsMicrofiche extends ObjectModel
{
    public $id_microfiche;
    public $id_moto;
    public $id_categorie;
    public $nom_constructeur;
    public $nom_fr;
    public $image_full_url;
    public $image_thumb_url;
    public $image_local;
    public $image_width;
    public $image_height;
    public $active = true;
    public $date_add;
    public $date_upd;

    public static $definition = [
        'table'   => 'ms_microfiche',
        'primary' => 'id_microfiche',
        'fields'  => [
            'id_moto'          => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt', 'required' => true],
            'id_categorie'     => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt', 'required' => true],
            'nom_constructeur' => ['type' => self::TYPE_STRING, 'validate' => 'isAnything',    'required' => true, 'size' => 255],
            'nom_fr'           => ['type' => self::TYPE_STRING, 'validate' => 'isAnything',    'size' => 255],
            'image_full_url'   => ['type' => self::TYPE_STRING, 'validate' => 'isAbsoluteUrl', 'required' => true, 'size' => 512],
            'image_thumb_url'  => ['type' => self::TYPE_STRING, 'validate' => 'isAbsoluteUrl', 'size' => 512],
            'image_local'      => ['type' => self::TYPE_STRING, 'validate' => 'isAnything',    'size' => 255],
            'image_width'      => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt'],
            'image_height'     => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt'],
            'active'           => ['type' => self::TYPE_BOOL,   'validate' => 'isBool'],
            'date_add'         => ['type' => self::TYPE_DATE,   'validate' => 'isDate'],
            'date_upd'         => ['type' => self::TYPE_DATE,   'validate' => 'isDate'],
        ],
    ];
}
