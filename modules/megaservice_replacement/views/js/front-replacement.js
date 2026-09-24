/**
 * Fiche produit d'une référence REMPLACÉE.
 *
 * Ce script n'est chargé que sur les fiches effectivement remplacées (le hook
 * header ne l'enregistre que dans ce cas) — il ne pèse rien sur le reste du
 * catalogue.
 *
 * Rôle :
 *   1. neutraliser l'achat de la référence remplacée. La fiche reste visible et
 *      indexable (pas de 301) pour que le client confirme qu'il a trouvé la
 *      bonne ancienne référence, mais elle n'est plus commandable : c'est le
 *      bloc de remplacement qui porte le parcours d'achat.
 *   2. ajout groupé des composants cochés (bloc 1:N, cf. bindGroupAdd).
 */
(function () {
  'use strict';

  /** Neutralise les commandes d'achat de la fiche courante. */
  function disablePurchase() {
    var selectors = [
      '[data-button-action="add-to-cart"]',
      '.add-to-cart',
      '.product-add-to-cart button[type="submit"]'
    ];

    selectors.forEach(function (sel) {
      Array.prototype.forEach.call(document.querySelectorAll(sel), function (btn) {
        // Le bloc de remplacement contient des liens vers les fiches cibles,
        // jamais de bouton d'achat : rien à protéger de ce côté.
        if (btn.closest('.ms-repl-front')) { return; }
        btn.disabled = true;
        btn.setAttribute('aria-disabled', 'true');
        btn.classList.add('ms-repl-front__disabled');
      });
    });

    var qty = document.querySelector('.product-quantity, .product-quantities');
    if (qty) { qty.setAttribute('hidden', 'hidden'); }
  }

  /**
   * Ajout groupé des composants cochés (bloc 1:N).
   *
   * - Envois à la SUITE, jamais en parallèle : sans panier, des requêtes
   *   simultanées en créeraient chacune un.
   * - Un composant refusé (stock, quantité minimum…) n'arrête pas les autres ;
   *   on les signale à la fin.
   * - UN SEUL `updateCart` à la fin, avec la dernière réponse : le panier latéral
   *   s'ouvre et se rafraîchit une fois, pas N.
   * - Le bloc est dans le <form> d'achat : les champs n'ont pas de `name`, rien
   *   ne part avec l'ajout du produit principal.
   */
  function bindGroupAdd() {
    var block = document.querySelector('.ms-repl-front[data-group-add]');
    if (!block) { return; }

    var addBtn   = block.querySelector('.js-ms-repl-add');
    var allBox   = block.querySelector('.js-ms-repl-all');
    var totalEl  = block.querySelector('.js-ms-repl-total');
    var feedback = block.querySelector('.js-ms-repl-feedback');
    var rows     = Array.prototype.slice.call(block.querySelectorAll('.ms-repl-front__item[data-id-product]'));
    var busy     = false;

    var money;
    try {
      money = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: block.getAttribute('data-currency') || 'EUR' });
    } catch (e) { money = null; }

    function rowQty(row) {
      var n = parseInt(row.querySelector('.js-ms-repl-qty').value, 10);
      return n > 0 ? n : 1;
    }

    function selectedRows() {
      return rows.filter(function (r) { return r.querySelector('.js-ms-repl-check').checked; });
    }

    // Lignes dont la case est utilisable : une ligne à déclinaisons est inactive
    // tant que le client n'a pas choisi la sienne.
    function selectableRows() {
      return rows.filter(function (r) { return !r.querySelector('.js-ms-repl-check').disabled; });
    }

    function refresh() {
      var sel = selectedRows();
      var total = sel.reduce(function (sum, r) {
        return sum + (parseFloat(r.getAttribute('data-price')) || 0) * rowQty(r);
      }, 0);
      totalEl.textContent = (sel.length && money) ? money.format(total) : '';
      addBtn.disabled = busy || sel.length === 0;
      addBtn.textContent = addBtn.getAttribute('data-label') + (sel.length ? ' (' + sel.length + ')' : '');
      var usable = selectableRows().length;
      allBox.disabled = usable === 0;
      allBox.checked = usable > 0 && sel.length === usable;
      allBox.indeterminate = sel.length > 0 && sel.length < usable;
    }

    function say(msg, isError) {
      feedback.textContent = msg;
      feedback.hidden = !msg;
      feedback.classList.toggle('is-error', !!isError);
    }

    function addOne(row) {
      var body = new FormData();
      body.append('token', block.getAttribute('data-token'));
      body.append('id_product', row.getAttribute('data-id-product'));
      body.append('id_product_attribute', row.getAttribute('data-id-attr') || '0');
      body.append('qty', String(rowQty(row)));
      body.append('add', '1');
      body.append('action', 'update');
      body.append('ajax', '1');

      return fetch(block.getAttribute('data-cart-url'), {
        method: 'POST',
        body: body,
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      }).then(function (r) { return r.json(); });
    }

    block.addEventListener('change', function (e) {
      if (e.target === allBox) {
        selectableRows().forEach(function (r) { r.querySelector('.js-ms-repl-check').checked = allBox.checked; });
      }
      // Choix d'une déclinaison : on retient son id et son prix, on active la case
      // et on la coche ; « Choisir » (vide) la désactive et la décoche.
      if (e.target.classList.contains('js-ms-repl-variant')) {
        var row = e.target.closest('.ms-repl-front__item');
        var check = row.querySelector('.js-ms-repl-check');
        var opt = e.target.options[e.target.selectedIndex];
        var chosen = !!e.target.value;
        row.setAttribute('data-id-attr', chosen ? e.target.value : '0');
        if (chosen && opt) { row.setAttribute('data-price', opt.getAttribute('data-price') || '0'); }
        check.disabled = !chosen;
        check.checked = chosen;
      }
      refresh();
    });
    block.addEventListener('input', function (e) {
      if (e.target.classList.contains('js-ms-repl-qty')) { refresh(); }
    });

    addBtn.addEventListener('click', function () {
      var sel = selectedRows();
      if (busy || !sel.length) { return; }
      busy = true;
      say('', false);
      addBtn.classList.add('is-loading');
      refresh();

      var failed = [];
      var added = 0;
      var last = null;
      var lastId = 0;

      // Chaîne de promesses : chaque ajout attend le précédent.
      sel.reduce(function (chain, row) {
        return chain.then(function () {
          return addOne(row).then(function (data) {
            var name = (row.querySelector('.ms-repl-front__name') || {}).textContent || row.getAttribute('data-id-product');
            if (data && data.hasError) {
              failed.push(name.trim() + ' — ' + ((data.errors && data.errors[0]) || 'refusé'));
            } else {
              added++;
              last = data;
              lastId = parseInt(row.getAttribute('data-id-product'), 10) || 0;
            }
          }).catch(function () {
            var name = (row.querySelector('.ms-repl-front__name') || {}).textContent || row.getAttribute('data-id-product');
            failed.push(name.trim() + ' — erreur réseau');
          });
        });
      }, Promise.resolve()).then(function () {
        busy = false;
        addBtn.classList.remove('is-loading');
        refresh();

        if (added && window.prestashop && typeof window.prestashop.emit === 'function') {
          window.prestashop.emit('updateCart', {
            reason: { idProduct: lastId, idProductAttribute: 0, linkAction: 'add-to-cart', cart: (last && last.cart) || null },
            resp: last
          });
        }
        if (failed.length) {
          say((added ? added + ' ajouté(s). ' : '') + 'Non ajouté : ' + failed.join(' ; '), true);
        } else if (added) {
          say(added + (added > 1 ? ' références ajoutées au panier.' : ' référence ajoutée au panier.'), false);
        }
      });
    });

    refresh();
  }

  function init() {
    if (!document.querySelector('.ms-repl-front')) { return; }
    // Référence encore achetable (1:N : le bloc n'est qu'une composition) :
    // son bouton reste actif, seule la liste est repliable.
    var block = document.querySelector('.ms-repl-front');
    if (!block.hasAttribute('data-orderable')) { disablePurchase(); }
    bindGroupAdd();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
