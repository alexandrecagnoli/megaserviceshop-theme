/**
 * Sélecteur « Afficher N » de la pagination catalogue.
 * ==============================================
 *
 * PrestaShop attend le nombre de produits par page dans le paramètre d'URL
 * `resultsPerPage`. On repart de l'URL courante pour conserver les facettes,
 * le tri et le filtre de montabilité, et on retire `page` : passer de 20 à 500
 * réduit le nombre de pages, rester sur ?page=7 tomberait hors bornes.
 *
 * Écouteur délégué sur le document : PrestaShop remplace tout le bloc
 * #js-product-list en AJAX à chaque changement de filtre, un écouteur posé
 * directement sur le <select> ne survivrait pas au premier filtrage.
 */
document.addEventListener('change', (event) => {
  const select = event.target.closest('.js-ms-per-page');
  if (!select) {
    return;
  }

  const perPage = parseInt(select.value, 10);
  if (!perPage) {
    return;
  }

  const url = new URL(window.location.href);
  url.searchParams.set('resultsPerPage', String(perPage));
  url.searchParams.delete('page');
  window.location.assign(url.toString());
});
