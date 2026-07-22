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
 *   2. replier les très gros ensembles (jusqu'à 101 composants constatés).
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

  /** Repli / dépli de la liste quand l'ensemble est volumineux. */
  function bindSetToggle() {
    var btn = document.querySelector('.js-ms-repl-more');
    var list = document.querySelector('.js-ms-repl-list');
    if (!btn || !list) { return; }

    btn.addEventListener('click', function () {
      var opened = list.classList.toggle('is-open');
      btn.textContent = opened
        ? btn.getAttribute('data-less')
        : btn.getAttribute('data-more');
    });
  }

  function init() {
    if (!document.querySelector('.ms-repl-front')) { return; }
    disablePurchase();
    bindSetToggle();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
