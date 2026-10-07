<?php

/**
 * Recherche native — normalise les références constructeur saisies avec des
 * séparateurs cosmétiques (espace, tiret, point).
 *
 * Constat du 07/10 (ticket "Recherche par référence, combler les écarts") :
 * les références du catalogue ne contiennent JAMAIS l'espace que le client
 * tape (ex. "3AG 26003350X" saisi pour "3AG26003350X" en base). Les tirets,
 * eux, existent parfois réellement (803 produits), mais l'indexeur natif
 * (Search::fillProductArray) les retire déjà au moment d'indexer : un produit
 * référencé "00029013002-02" est indexé sous le mot "0002901300202". Le trou
 * est uniquement côté SAISIE : la requête n'est jamais soumise à la même
 * normalisation avant d'être tokenisée.
 *
 * Repli volontairement DÉCLENCHÉ PAR UNE RÈGLE STRICTE, pas une tentative
 * "au cas où" : il ne faut jamais mordre sur une recherche en langage naturel
 * (taille, année de moto, quantité...). Voir looksLikeReference().
 */
class SearchController extends SearchControllerCore
{
    /**
     * Séquence de chiffres consécutifs à partir de laquelle une chaîne est
     * considérée comme une référence plutôt qu'un mot de langage naturel.
     *
     * Calibré le 07/10 sur les références réelles à séparateur de la
     * préprod (803 produits) :
     *   - "BT40000GG-CSV-1" (famille la plus représentée de l'échantillon) →
     *     plus long run : 5 chiffres ("40000"). Seuil 6 l'aurait ratée.
     *   - "3AG 26003350X", "4054 0400" (cas signalés) → 8 chiffres.
     * À 5, aucune requête en langage naturel observée ne déclenche à tort :
     * une taille ("42"), une année de moto ("2026") ou une quantité n'ont
     * jamais 5 chiffres consécutifs. Un repli connu et accepté : une réf. à
     * run de 4 chiffres ou moins (ex. "T1402 S") ne sera pas normalisée —
     * descendre le seuil mordrait sur les années de moto, bien plus fréquentes
     * dans les recherches réelles.
     */
    const MIN_DIGIT_RUN = 5;

    public function init()
    {
        parent::init();

        if (!$this->search_string) {
            return;
        }

        $normalized = $this->normalizeIfReference($this->search_string);
        if ($normalized === null) {
            return;
        }

        $this->search_string = $normalized;
        $this->context->smarty->assign('search_string', $normalized);
    }

    /**
     * @return string|null La forme normalisée si $raw ressemble à une référence
     *                      avec séparateurs, sinon null (ne rien changer).
     */
    private function normalizeIfReference($raw)
    {
        if (!preg_match('/[\s\-.]/', $raw)) {
            return null; // aucun séparateur à retirer
        }

        $stripped = preg_replace('/[\s\-.]+/', '', $raw);
        if ($stripped === '' || !preg_match('/^[A-Za-z0-9]+$/', $stripped)) {
            return null; // un accent ou un caractère non alphanumérique survit au retrait : pas une référence
        }

        if (!preg_match('/\d{' . self::MIN_DIGIT_RUN . ',}/', $stripped)) {
            return null; // pas de suite de chiffres assez longue : probablement du langage naturel
        }

        return $stripped;
    }
}
