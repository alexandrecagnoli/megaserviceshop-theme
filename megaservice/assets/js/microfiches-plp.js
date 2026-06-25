/**
 * PR6 — PLP moto : filtre catégorie client-side.
 * La grille des microfiches est rendue côté serveur (controllers/front/moto.php).
 * On montre/cache les cartes selon les catégories cochées + maj du compteur.
 *
 * Les facettes (.ms-plp-*) sont volontairement HORS de #search_filters /
 * .facet-label : sinon le handler capture de ps_facetedsearch (app.js) fait
 * stopImmediatePropagation() et bloque le toggle des cases. Étant découplé, un
 * simple écouteur `change` suffit.
 */
(function () {
  'use strict';

  function init() {
    var grid = document.querySelector('.js-ms-plp-grid');
    if (!grid) {
      return; // pas sur la PLP
    }

    var checkboxes = Array.prototype.slice.call(document.querySelectorAll('.js-ms-plp-cb'));
    var cards      = Array.prototype.slice.call(grid.querySelectorAll('[data-categorie]'));
    var countEl    = document.querySelector('.js-ms-plp-count');
    var emptyEl    = document.querySelector('.js-ms-plp-empty');
    var clearBtn   = document.querySelector('.js-ms-plp-clear');
    var resetBtns  = Array.prototype.slice.call(document.querySelectorAll('.js-ms-plp-reset'));

    var countTemplate = countEl ? countEl.textContent.replace(/\d+/, '%d') : '';

    function apply() {
      var selected = {};
      var any = false;
      checkboxes.forEach(function (cb) {
        if (cb.checked) { selected[cb.value] = true; any = true; }
      });

      var visible = 0;
      cards.forEach(function (card) {
        // any=false (aucune cochée) → on masque tout (état "Effacer").
        var show = any && selected[card.getAttribute('data-categorie')] === true;
        card.hidden = !show;
        if (show) { visible++; }
      });

      if (countEl && countTemplate) {
        countEl.textContent = countTemplate.replace('%d', visible);
      }
      if (emptyEl) {
        emptyEl.hidden = visible !== 0;
      }
    }

    checkboxes.forEach(function (cb) {
      cb.addEventListener('change', apply);
    });

    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        checkboxes.forEach(function (cb) { cb.checked = false; });
        apply();
      });
    }

    // "Réinitialiser" présent en plusieurs exemplaires (sidebar desktop + footer
    // sheet mobile) → on lie tous les .js-ms-plp-reset.
    resetBtns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        checkboxes.forEach(function (cb) { cb.checked = true; });
        apply();
      });
    });

    // "Voir plus / Voir moins" — déplie les catégories au-delà des 9 premières.
    var seemore = document.querySelector('.js-ms-plp-seemore');
    if (seemore) {
      var extras = Array.prototype.slice.call(document.querySelectorAll('.ms-plp-facet-extra'));
      seemore.addEventListener('click', function () {
        var opening = seemore.classList.toggle('is-open');
        extras.forEach(function (li) { li.hidden = !opening; });
        seemore.childNodes[0].nodeValue = (opening
          ? seemore.getAttribute('data-less')
          : seemore.getAttribute('data-more')) + ' ';
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
