/**
 * Megaservice — Wishlist (blockwishlist)
 * --------------------------------------
 * Bouton toggle add/remove sur la fiche produit :
 *  - Logged-in + pas dans liste → addProductToWishList → toast + cœur orange
 *  - Logged-in + déjà dans liste → deleteProductFromWishList → toast + cœur vide
 *  - Non-loggé → toast 'Connectez-vous...' puis redirect login
 *
 * État initial (data-in-wishlist + data-wishlist-id) injecté côté serveur
 * par override/controllers/front/ProductController.php.
 */

document.addEventListener('click', function (e) {
  var btn = e.target.closest('.js-add-to-wishlist');
  if (!btn) return;
  e.preventDefault();

  if (btn.classList.contains('is-pending')) return;

  // Non-loggé → toast + redirect
  if (btn.dataset.customerLogged !== '1') {
    if (window.msToast) window.msToast('Connectez-vous pour ajouter à vos favoris', 1500);
    setTimeout(function () {
      window.location.href = btn.dataset.loginUrl || '/connexion';
    }, 1500);
    return;
  }

  var actionUrl          = btn.dataset.actionUrl;
  var idProduct          = parseInt(btn.dataset.idProduct, 10) || 0;
  var idProductAttribute = parseInt(btn.dataset.idProductAttribute, 10) || 0;
  var isInWishlist       = btn.dataset.inWishlist === '1';
  var cachedListId       = parseInt(btn.dataset.wishlistId, 10) || 0;

  if (!actionUrl || !idProduct) return;

  btn.classList.add('is-pending');

  // ── REMOVE ──
  if (isInWishlist && cachedListId) {
    postWishlistAction(actionUrl, 'deleteProductFromWishList', {
      idWishList: cachedListId,
      id_product: idProduct,
      id_product_attribute: idProductAttribute
    })
      .then(function (data) {
        btn.classList.remove('is-pending');
        if (data && data.success) {
          btn.classList.remove('is-active');
          btn.dataset.inWishlist = '0';
          if (window.msToast) window.msToast('Produit retiré de vos favoris');
        } else {
          if (window.msToast) window.msToast((data && data.message) || 'Erreur');
        }
      })
      .catch(function (err) {
        btn.classList.remove('is-pending');
        console.error('[megaservice] wishlist remove error:', err);
        if (window.msToast) window.msToast('Erreur lors du retrait');
      });
    return;
  }

  // ── ADD ──
  // Si on a déjà l'id de liste (page render), call direct.
  // Sinon (1er ajout pour ce customer), il faut d'abord call getAllWishList
  // qui auto-crée la liste par défaut côté serveur si absente.
  function addToList(idWishList) {
    return postWishlistAction(actionUrl, 'addProductToWishList', {
      idWishList: idWishList,
      id_product: idProduct,
      id_product_attribute: idProductAttribute,
      quantity: 1
    });
  }

  var addPromise;
  if (cachedListId) {
    addPromise = addToList(cachedListId);
  } else {
    addPromise = postWishlistAction(actionUrl, 'getAllWishList', {})
      .then(function (data) {
        var lists = (data && data.wishlists) || [];
        if (!lists.length) throw new Error('No wishlist returned');
        var defaultList = lists.find(function (l) { return parseInt(l.default, 10) === 1; }) || lists[0];
        var idWishList = parseInt(defaultList.id_wishlist, 10);
        btn.dataset.wishlistId = String(idWishList); // cache pour les clics suivants
        return addToList(idWishList);
      });
  }

  addPromise
    .then(function (data) {
      btn.classList.remove('is-pending');
      if (data && data.success) {
        btn.classList.add('is-active');
        btn.dataset.inWishlist = '1';
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
