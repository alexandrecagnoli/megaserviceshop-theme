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
    var cards      = Array.prototype.slice.call(grid.querySelectorAll('.ms-plp__card-wrap'));
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

    checkboxes.forEach(function (cb) {
      cb.addEventListener('change', apply);
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
