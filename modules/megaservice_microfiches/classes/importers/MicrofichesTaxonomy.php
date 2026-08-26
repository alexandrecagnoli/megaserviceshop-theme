<?php
/**
 * Dérivations métier pour le mapping CSV microfiches → ms_microfiche_categorie.
 *
 * Toutes les méthodes sont pures (statiques, sans dépendance Presta) → testables
 * unitairement et utilisables hors contexte ObjectModel.
 *
 * Spec : voir docs/microfiches/BRIEF.md §4.3 et §6.1.E.
 *
 * Mapping vue_eclatee_type → partie :
 *   "engine" → 'moteur'
 *   "frame"  → 'cycle'
 *
 * Auto-création catégorie absente :
 *   - code      = "TODO_<partie>_<numero>" (ex. "TODO_moteur_30")
 *   - nom_fr    = NULL (à saisir manuellement en BO)
 *   - active    = true (visible mais flagué TODO)
 *   - ordre     = numero_constructeur (par défaut)
 */
class MicrofichesTaxonomy
{
    public const PARTIE_CYCLE  = 'cycle';
    public const PARTIE_MOTEUR = 'moteur';

    /** Mapping vue_eclatee_type (CSV constructeur) → partie (notre modèle). */
    private const TYPE_MAP = [
        'engine' => self::PARTIE_MOTEUR,
        'frame'  => self::PARTIE_CYCLE,
    ];

    /**
     * Convertit "engine"/"frame" (CSV constructeur) en 'moteur'/'cycle' (notre modèle).
     * Insensible à la casse. Retourne null si type non reconnu.
     */
    public static function partieFromVueEclateeType(string $vueEclateeType): ?string
    {
        $key = strtolower(trim($vueEclateeType));
        return self::TYPE_MAP[$key] ?? null;
    }

    /**
     * Code TODO pour une catégorie auto-créée (en attente de nom FR en BO).
     * Ex : "TODO_moteur_30".
     */
    public static function autoCreatedCode(string $partie, int $numero): string
    {
        return sprintf('TODO_%s_%d', $partie, $numero);
    }

    /**
     * Détecte si un `code` de catégorie est un placeholder auto-créé.
     * Permet au BO de filtrer les catégories à renommer.
     */
    public static function isAutoCreatedCode(string $code): bool
    {
        return strncmp($code, 'TODO_', 5) === 0;
    }
}
