/**
 * PR6 — PLP moto : filtre catégorie client-side.
 * La grille des microfiches est rendue côté serveur (controllers/front/moto.php).
 * Ici on se contente de montrer/cacher les cartes selon les catégories cochées,
 * et de tenir à jour le compteur de résultats + l'état vide.
 *
 * Aucun appel réseau : tout est déjà dans le DOM (peu de microfiches par moto).
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
    var resetBtn   = document.querySelector('.js-ms-plp-reset');

    var countTemplate = countEl ? countEl.textContent.replace(/\d+/, '%d') : '';

    function selectedCategories() {
      var set = {};
      checkboxes.forEach(function (cb) {
        if (cb.checked) {
          set[cb.value] = true;
        }
      });
      return set;
    }

    function apply() {
      var selected = selectedCategories();
      var noneSelected = Object.keys(selected).length === 0;
      var visible = 0;

      cards.forEach(function (card) {
        // noneSelected = aucune case cochée → on masque tout (état "Effacer").
        var show = !noneSelected && selected[card.getAttribute('data-categorie')] === true;
        card.hidden = !show;
        if (show) {
          visible++;
        }
      });

      if (countEl && countTemplate) {
        countEl.textContent = countTemplate.replace('%d', visible);
      }
      if (emptyEl) {
        emptyEl.hidden = visible !== 0;
      }
    }

    // ATTENTION : on ne peut PAS se fier à l'événement `change` natif des
    // checkboxes. Le moteur de facettes natif (app.js) pose un handler global
    // qui fait e.preventDefault() sur tout clic dans `#search_filters .facet-label`
    // (pour piloter ps_facetedsearch). Comme nos checkboxes sont dans ce conteneur
    // — réutilisé pour le design — le toggle natif est bloqué et `change` ne part
    // jamais. On gère donc le clic nous-mêmes : toggle manuel de la case + apply.
    checkboxes.forEach(function (cb) {
      var label = cb.closest('.facet-label') || cb.parentNode;
      label.addEventListener('click', function (e) {
        e.preventDefault();
        cb.checked = !cb.checked;
        apply();
      });
    });

    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        checkboxes.forEach(function (cb) { cb.checked = false; });
        apply();
      });
    }

    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        checkboxes.forEach(function (cb) { cb.checked = true; });
        apply();
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
