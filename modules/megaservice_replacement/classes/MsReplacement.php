<?php
/**
 * Megaservice — Relation de remplacement OEM (référence remplacée → remplaçante).
 *
 * PRINCIPE CLÉ (cf. docs/SPEC_module_remplacement_MSS.md §3) : la **référence
 * constructeur est la clé**, jamais l'id PrestaShop. La résolution réf →
 * id_product se fait à l'exécution (affichage / ajout panier), jamais au
 * stockage — un remplaçant peut très bien ne pas encore exister au catalogue.
 *
 * On conserve À LA FOIS :
 *   - la relation BRUTE (`ref_replacement`) → audit / retraçabilité fichier
 *   - la relation RÉSOLUE (`ref_final`)     → ce que le front consomme
 *
 * `sales_orga` est purement informatif (traçabilité du fichier d'origine) et
 * ne fait PAS partie de la clé unique : la réf constructeur est globalement
 * unique dans l'écosystème Pierer, et le front ne filtre pas par orga.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MsReplacement extends ObjectModel
{
    /** @var string|null Organisation de vente d'origine (0140, 0150…) — informatif. */
    public $sales_orga;

    /** @var string Référence remplacée (celle que le client connaît). */
    public $ref_replaced;

    /** @var string Référence remplaçante, telle que dans le fichier constructeur. */
    public $ref_replacement;

    /** @var string 'replace' (1:1) ou 'set' (1:N — un composant par ligne). */
    public $conversion_type;

    /** @var int Quantité de la réf remplaçante dans le set (1 pour les 1:1). */
    public $quantity;

    /** @var string|null Référence finale après résolution transitive. */
    public $ref_final;

    /**
     * @var bool `ref_final` est-elle elle-même la tête d'un set ?
     *
     * La résolution s'arrête au maillon précédent quand elle rencontre un set
     * (on ne compose pas des sets de sets). Sans ce drapeau, le front devrait
     * refaire un lookup à l'aveugle pour savoir s'il doit éclater `ref_final`.
     */
    public $final_is_set;

    /** @var int Profondeur de chaîne (1 = remplacement direct). */
    public $chain_depth;

    /** @var string 'ok' | 'loop' | 'dead_end'. */
    public $chain_status;

    public $date_add;
    public $date_upd;

    public static $definition = [
        'table'   => 'ms_replacement',
        'primary' => 'id_replacement',
        'fields'  => [
            'sales_orga'      => ['type' => self::TYPE_STRING, 'validate' => 'isAnything', 'size' => 8],
            'ref_replaced'    => ['type' => self::TYPE_STRING, 'validate' => 'isAnything', 'size' => 32, 'required' => true],
            'ref_replacement' => ['type' => self::TYPE_STRING, 'validate' => 'isAnything', 'size' => 32, 'required' => true],
            'conversion_type' => ['type' => self::TYPE_STRING, 'validate' => 'isAnything', 'size' => 8],
            'quantity'        => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt'],
            'ref_final'       => ['type' => self::TYPE_STRING, 'validate' => 'isAnything', 'size' => 32],
            'final_is_set'    => ['type' => self::TYPE_BOOL,   'validate' => 'isBool'],
            'chain_depth'     => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt'],
            'chain_status'    => ['type' => self::TYPE_STRING, 'validate' => 'isAnything', 'size' => 16],
            'date_add'        => ['type' => self::TYPE_DATE,   'validate' => 'isDate'],
            'date_upd'        => ['type' => self::TYPE_DATE,   'validate' => 'isDate'],
        ],
    ];

    /** Types de conversion acceptés (colonne `ConversionType` du fichier). */
    const TYPE_REPLACE = 'replace';
    const TYPE_SET     = 'set';
    const CONVERSION_TYPES = [self::TYPE_REPLACE, self::TYPE_SET];

    /** Statuts de résolution de chaîne. */
    const CHAIN_OK       = 'ok';
    const CHAIN_LOOP     = 'loop';
    const CHAIN_DEAD_END = 'dead_end';
    const CHAIN_STATUSES = [self::CHAIN_OK, self::CHAIN_LOOP, self::CHAIN_DEAD_END];
}
