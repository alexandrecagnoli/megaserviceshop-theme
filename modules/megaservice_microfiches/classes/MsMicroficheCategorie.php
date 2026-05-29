<?php
/**
 * ObjectModel pour `ps_ms_microfiche_categorie` — référentiel des catégories
 * constructeur utilisé comme filtre côté front.
 *
 * `nom_fr` est nullable : à l'import microfiche, si la catégorie (partie, numero)
 * n'existe pas, elle est auto-créée avec nom_fr=NULL et code="TODO_<partie>_<num>",
 * en attente de saisie FR en BO (cf. BRIEF §6.1.E).
 */
class MsMicroficheCategorie extends ObjectModel
{
    public $id_categorie;
    public $partie;
    public $numero_constructeur;
    public $code;
    public $nom_fr;
    public $ordre_affichage = 0;
    public $active = true;

    public const PARTIES = ['cycle', 'moteur'];

    public static $definition = [
        'table'   => 'ms_microfiche_categorie',
        'primary' => 'id_categorie',
        'fields'  => [
            'partie'              => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 8],
            'numero_constructeur' => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt', 'required' => true],
            'code'                => ['type' => self::TYPE_STRING, 'validate' => 'isAnything',    'required' => true, 'size' => 64],
            'nom_fr'              => ['type' => self::TYPE_STRING, 'validate' => 'isAnything',    'size' => 128],
            'ordre_affichage'     => ['type' => self::TYPE_INT,    'validate' => 'isInt'],
            'active'              => ['type' => self::TYPE_BOOL,   'validate' => 'isBool'],
        ],
    ];
}
