<?php
/**
 * Endpoint JSON de retrait du filtre moto (« garage »).
 *
 * POURQUOI : le filtre moto a DEUX supports, et ils n'étaient pas synchronisés.
 * Le sélecteur mémorise sa sélection dans `localStorage` (affichage : bandeau,
 * état du header, réouverture de la modale), tandis que le filtrage réel des
 * catégories lit le cookie PrestaShop `ms_moto` (cf. MsMountability::
 * resolveActiveMoto + hookActionFacetedSearchFilters). Le bouton
 * « Réinitialiser » ne vidait que le premier : l'interface affichait « aucune
 * moto » pendant que le back-end continuait de filtrer sur l'ancienne.
 *
 * Le seul chemin de purge existant, `?ms_clear_moto=1`, n'est traité que par
 * l'override CategoryController — donc inopérant depuis une fiche produit, la
 * home ou une page microfiche. Cet endpoint le rend accessible partout.
 *
 * GET (ou POST) /module/megaservice_mountability/clearmoto
 * Réponse : { "cleared": true|false }
 *   cleared=true  → un filtre était armé et vient d'être retiré (le JS recharge
 *                   la page, dont le contenu était filtré).
 *   cleared=false → aucun filtre n'était actif : rien à recharger.
 *
 * Pas de token CSRF : l'action est idempotente et sans effet de bord au-delà
 * d'une préférence d'affichage de la session courante — même surface que le
 * lien `?ms_clear_moto=1` du bandeau, qui est un simple GET.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Megaservice_mountabilityClearmotoModuleFrontController extends ModuleFrontController
{
    /** @var bool Pas de rendu de page : on ne sert que du JSON. */
    public $ajax = true;

    public function initContent()
    {
        $was = (int) $this->context->cookie->ms_moto;

        if ($was) {
            $this->context->cookie->ms_moto = 0;
            $this->context->cookie->write();
        }

        header('Content-Type: application/json; charset=utf-8');
        die(json_encode(['cleared' => (bool) $was]));
    }
}
