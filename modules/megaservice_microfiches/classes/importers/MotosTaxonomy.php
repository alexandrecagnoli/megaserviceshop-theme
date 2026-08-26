<?php
/**
 * Dérivations métier pour le mapping CSV motos → ms_moto.
 *
 * Toutes les méthodes sont pures (statiques, sans dépendance Presta) → testables
 * unitairement et utilisables hors contexte ObjectModel.
 *
 * Spec : voir docs/microfiches/BRIEF.md §4.1 et §4.2.
 *
 *   category_fr  → coreName()    : retire le motif d'année final
 *                                  ex. "125 Duke 2026" → "125 Duke"
 *   coreName     → cylindree()   : 1er nombre du core_name
 *                                  ex. "125 Duke" → 125 ; "Norden 901 Expedition" → 901
 *   coreName     → type()        : dictionnaire ordonné (premier match gagne)
 *                                  voir RULES ci-dessous
 *   type         → isElectric()  : type === 'Electrique'
 */
class MotosTaxonomy
{
    public const TYPE_ELECTRIQUE = 'Electrique';
    public const TYPE_TRIAL      = 'Trial';
    public const TYPE_ADVENTURE  = 'Adventure';
    public const TYPE_SUPERMOTO  = 'Supermoto';
    public const TYPE_NAKED      = 'Naked';
    public const TYPE_ENDURO     = 'Enduro';
    public const TYPE_MOTOCROSS  = 'Motocross';
    public const TYPE_AUTRES     = 'Autres';

    /**
     * Règles de mapping, ordre IMPORTANT (premier match gagne).
     * Du plus spécifique au plus général. Brief §4.2.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const RULES = [
        // 1. ÉLECTRIQUE (priorité absolue : avant SX/MC/Freeride en non-électrique).
        ['/\b(SX-E|MC-E|EE|Freeride\s*E)\b/i', self::TYPE_ELECTRIQUE],

        // 2. TRIAL (GASGAS TXT + KTM Freeride non électrique).
        //    Freeride E ne tombe pas ici (déjà capturé en 1).
        ['/\b(TXT|TXT\s+GP|TXT\s+Racing|Freeride)\b/i', self::TYPE_TRIAL],

        // 3. ADVENTURE / RALLY RAID (Adventure, Norden, Rally Replica/Factory, SMT).
        ['/\b(Adventure|Norden|Rally\s+(Replica|Factory)|SMT)\b/i', self::TYPE_ADVENTURE],

        // 4. SUPERMOTO (SMC / SMR / SM<sp> / FS<sp> / Supermoto / ES = GASGAS Enduro Street).
        ['/\b(SMC|SMR|SM\s|FS\s|Supermoto|ES)\b/i', self::TYPE_SUPERMOTO],

        // 5. NAKED (Duke, RC<num> avec ou sans espace ni suffixe lettre, Svartpilen,
        //    Vitpilen, Brabus). Couvre RC 125/200/250/390, RC8, RC8C.
        //    Note historique : ancien pattern `RC\s+\d` (1 chiffre) loupait RC 390
        //    car \b après \d échouait quand suivait un chiffre — bug du brief.
        ['/\b(Duke|RC\s*\d+[A-Z]?|Svartpilen|Vitpilen|Brabus)\b/i', self::TYPE_NAKED],

        // 6. ENDURO routier (Enduro R / 701 Enduro).
        ['/\b(Enduro\s+R|701\s+Enduro)\b/i', self::TYPE_ENDURO],

        // 7. ENDURO compétition :
        //    KTM : EXC, EX, XCF, XC, MXC (Motocross Cross-Country), EGS / E-GS (vieux 2T)
        //    HQV : TE, TX, FE, FX
        //    GASGAS : EC (2T et 4T car EC 350F matche \bEC\b), EW (Enduro Wild), RX (Rally Cross)
        ['/\b(EXC|EX|XCF|XC|TE|TX|FE|FX|EC|EW|RX|MXC|EGS|E-GS)\b/i', self::TYPE_ENDURO],

        // 8. MOTOCROSS — EN DERNIER (SX-E déjà attrapé en 1).
        //    KTM : SX
        //    HQV : TC (2T), FC (4T) — absents du brief, ajoutés après mesure
        //    GASGAS : MC
        ['/\b(SX|MC|TC|FC)\b/i', self::TYPE_MOTOCROSS],
    ];

    /**
     * Retire le motif d'année final ("\s*\d{4}\s*$") de category_fr.
     * Ex : "125 Duke 2026" → "125 Duke" ; "Svartpilen 401 2025" → "Svartpilen 401".
     */
    public static function coreName(string $categoryFr): string
    {
        $stripped = preg_replace('/\s*\d{4}\s*$/', '', $categoryFr);
        return trim($stripped ?? $categoryFr);
    }

    /**
     * Premier entier détecté dans le core_name. null si aucun.
     * Ex : "125 Duke" → 125 ; "Norden 901 Expedition" → 901 ; "Duke" → null.
     */
    public static function cylindree(string $coreName): ?int
    {
        if (preg_match('/\d+/', $coreName, $m)) {
            return (int) $m[0];
        }
        return null;
    }

    /**
     * Applique le dictionnaire RULES, retourne 'Autres' si aucun match.
     */
    public static function type(string $coreName): string
    {
        foreach (self::RULES as [$pattern, $type]) {
            if (preg_match($pattern, $coreName)) {
                return $type;
            }
        }
        return self::TYPE_AUTRES;
    }

    public static function isElectric(string $type): bool
    {
        return $type === self::TYPE_ELECTRIQUE;
    }
}
