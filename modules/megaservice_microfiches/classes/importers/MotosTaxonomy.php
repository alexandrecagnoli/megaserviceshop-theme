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
        // ÉLECTRIQUE en premier (avant que SX-E matche SX en Motocross).
        ['/\b(SX-E|MC-E|EE|Freeride\s*E)\b/i', self::TYPE_ELECTRIQUE],
        // TRIAL (GASGAS uniquement)
        ['/\b(TXT|TXT\s+GP|TXT\s+Racing)\b/i', self::TYPE_TRIAL],
        // ADVENTURE / TRAVEL
        ['/\b(Adventure|Norden)\b/i', self::TYPE_ADVENTURE],
        // SUPERMOTO (SMC / SMR / SM<sp> / FS<sp> / Supermoto)
        ['/\b(SMC|SMR|SM\s|FS\s|Supermoto)\b/i', self::TYPE_SUPERMOTO],
        // NAKED / ROADSTER (Duke, RC<num>, Svartpilen, Vitpilen)
        ['/\b(Duke|RC\s+\d|Svartpilen|Vitpilen)\b/i', self::TYPE_NAKED],
        // ENDURO routier (Enduro R / 701 Enduro)
        ['/\b(Enduro\s+R|701\s+Enduro)\b/i', self::TYPE_ENDURO],
        // ENDURO compétition (EXC, EX, XC, TE, TX, FE, FX)
        ['/\b(EXC|EX|XC|TE|TX|FE|FX)\b/i', self::TYPE_ENDURO],
        // MOTOCROSS (SX, MC) — laissé EN DERNIER car SX matche aussi SX-E
        // (déjà attrapé par la 1re règle Électrique).
        ['/\b(SX|MC)\b/i', self::TYPE_MOTOCROSS],
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
