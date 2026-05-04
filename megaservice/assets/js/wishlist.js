/**
 * Megaservice — Wishlist (blockwishlist)
 * --------------------------------------
 * Logged-in : POST AJAX vers le module → toast confirmation + cœur orange plein
 * Non-loggé : toast "connectez-vous" puis redirect vers la page de login
 */

document.addEventListener('click', function (e) {
  var btn = e.target.closest('.js-add-to-wishlist');
  if (!btn) return;
  e.preventDefault();

  // Anti double-click
  if (btn.classList.contains('is-pending') || btn.classList.contains('is-active')) return;

  // Non-loggé → toast + redirect login
  if (btn.dataset.customerLogged !== '1') {
    if (window.msToast) window.msToast('Connectez-vous pour ajouter à vos favoris', 1500);
    setTimeout(function () {
      window.location.href = btn.dataset.loginUrl || '/connexion';
    }, 1500);
    return;
  }

  var actionUrl = btn.dataset.actionUrl;
  var idProduct = parseInt(btn.dataset.idProduct, 10) || 0;
  var idProductAttribute = parseInt(btn.dataset.idProductAttribute, 10) || 0;
  if (!actionUrl || !idProduct) return;

  btn.classList.add('is-pending');

  // Étape 1 : récupérer la liste par défaut (auto-créée côté serveur si absente)
  postWishlistAction(actionUrl, 'getAllWishList', {})
    .then(function (data) {
      var lists = (data && data.wishlists) || [];
      if (!lists.length) throw new Error('No wishlist returned');
      var defaultList = lists.find(function (l) { return parseInt(l.default, 10) === 1; }) || lists[0];
      var idWishList = parseInt(defaultList.id_wishlist, 10);

      // Étape 2 : ajouter le produit à cette liste
      return postWishlistAction(actionUrl, 'addProductToWishList', {
        idWishList: idWishList,
        id_product: idProduct,
        id_product_attribute: idProductAttribute,
        quantity: 1
      });
    })
    .then(function (data) {
      btn.classList.remove('is-pending');
      if (data && data.success) {
        btn.classList.add('is-active');
        if (window.msToast) window.msToast('Produit ajouté à vos favoris');
      } else {
        if (window.msToast) window.msToast((data && data.message) || 'Erreur lors de l\'ajout');
      }
    })
    .catch(function (err) {
      btn.classList.remove('is-pending');
      console.error('[megaservice] wishlist add error:', err);
      if (window.msToast) window.msToast('Erreur lors de l\'ajout aux favoris');
    });
});

function postWishlistAction(url, action, params) {
  var body = new URLSearchParams();
  body.append('action', action);
  Object.keys(params).forEach(function (k) {
    body.append('params[' + k + ']', String(params[k]));
  });

  return fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: body.toString()
  }).then(function (r) { return r.json(); });
}
