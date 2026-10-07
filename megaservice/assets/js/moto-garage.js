/**
 * Purge du filtre moto — le « garage ».
 * ==============================================
 *
 * La logique vivait uniquement dans model-selector.js, scopée à la modale du
 * header. La section « Recherche de pièces compatibles » n'avait aucun bouton
 * pour retirer le filtre : on pouvait en choisir un, jamais s'en défaire, alors
 * que la modale le permettait. Plutôt qu'une seconde copie — c'est exactement ce
 * qui avait fait diverger la cascade avant moto-cascade.js, et la recherche VIN
 * avant vin-search.js — la logique est ici et les deux appelants la partagent.
 *
 * Le filtre a DEUX supports : localStorage (ce que le header affiche) et le
 * cookie PS `ms_moto` (le filtrage réel des catégories, via ps_facetedsearch).
 * Ne vider que le premier laissait le back-end filtrer sur l'ancienne moto :
 * interface « aucune moto » et catalogue toujours filtré.
 */

/** Clé du cache d'affichage. Le cookie serveur, lui, fait foi. */
export const GARAGE_STORAGE_KEY = 'ms_selected_moto';

/** Oublie la moto mémorisée côté navigateur. Le cookie n'est pas touché ici. */
export function forgetStoredMoto() {
  try {
    localStorage.removeItem(GARAGE_STORAGE_KEY);
  } catch (err) {
    /* navigation privée, quota : le cookie reste la source de vérité */
  }
}

/**
 * Vide le garage, puis recharge la page si un filtre était réellement armé.
 *
 * @param {string}   endpoint  contrôleur clearmoto du module montabilité —
 *                             joignable depuis n'importe quelle page, au
 *                             contraire de ?ms_clear_moto=1 que seul l'override
 *                             CategoryController traite.
 * @param {Function} onBefore  purge locale de l'appelant (cache, état d'UI),
 *                             jouée avant l'appel réseau pour que l'interface
 *                             réponde tout de suite.
 */
export function clearMotoFilter(endpoint, onBefore) {
  if (typeof onBefore === 'function') {
    onBefore();
  }

  if (!endpoint) {
    return;
  }

  fetch(endpoint, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin'
  })
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (data) {
      // Rechargement UNIQUEMENT si un filtre était réellement armé : le contenu
      // affiché était alors filtré et ment désormais. Sinon on ne touche à rien.
      if (!data || !data.cleared) {
        return;
      }

      // PAS reload() : l'URL courante peut porter ?moto=…, que le serveur relit
      // pour ARMER le garage. On viderait le cookie puis on le réarmerait dans
      // la foulée — le filtre « se désactivait un instant et revenait ».
      var u = new URL(window.location.href);
      u.searchParams.delete('moto');
      u.searchParams.delete('ms_clear_moto');
      window.location.href = u.pathname + (u.search === '?' ? '' : u.search) + u.hash;
    })
    .catch(function () { /* réseau HS : le cache local est déjà purgé */ });
}
