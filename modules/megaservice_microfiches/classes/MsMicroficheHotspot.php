<?php
/**
 * ObjectModel pour `ps_ms_microfiche_hotspot` — 1 point cliquable sur une microfiche.
 *
 * `id_product` est nullable : un hotspot peut référencer une ref OEM dont la
 * fiche `ps_product` n'existe pas encore. Le matching est rejoué via le hook
 * actionProductAdd (PR ultérieure). `article_ref` reste la clé de matching.
 *
 * Clé naturelle (UNIQUE en base) : (id_microfiche, article_ref, sequence_number,
 * position_x, position_y) — une même pièce peut apparaître plusieurs fois sur
 * une vue éclatée à des endroits différents (cf. TECH_DEBT).
 *
 * position_x_original / position_y_original : copie figée des positions du
 * dernier CSV constructeur, conservées pour permettre un "revert" après
 * édition manuelle. Toujours rafraîchies à chaque réimport, même si le
 * hotspot a été manuellement édité.
 *
 * manually_edited : flag de protection contre l'écrasement. Quand l'admin
 * déplace un hotspot dans l'éditeur visuel, ce flag passe à 1, et
 * MicrofichesImporter::upsertHotspot() préserve alors position_x/y et
 * qty_recommended au réimport (cf. SQL ON DUPLICATE KEY UPDATE avec IF).
 */
class MsMicroficheHotspot extends ObjectModel
{
    public $id_hotspot;
    public $id_microfiche;
    public $id_product;
    public $article_ref;
    public $article_label;
    public $sequence_number;
    public $position_x;
    public $position_y;
    public $position_x_original;
    public $position_y_original;
    public $qty_recommended = 1;
    public $manually_edited = 0;

    public static $definition = [
        'table'   => 'ms_microfiche_hotspot',
        'primary' => 'id_hotspot',
        'fields'  => [
            'id_microfiche'       => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt', 'required' => true],
            'id_product'          => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt'],
            'article_ref'         => ['type' => self::TYPE_STRING, 'validate' => 'isAnything',    'required' => true, 'size' => 64],
            'article_label'       => ['type' => self::TYPE_STRING, 'validate' => 'isAnything',    'size' => 255],
            'sequence_number'     => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt', 'required' => true],
            'position_x'          => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt', 'required' => true],
            'position_y'          => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt', 'required' => true],
            'position_x_original' => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt'],
            'position_y_original' => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt'],
            'qty_recommended'     => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt'],
            'manually_edited'     => ['type' => self::TYPE_BOOL,   'validate' => 'isBool'],
        ],
    ];
}
