/**
 * PR6 — PLP moto : filtre catégorie client-side.
 * La grille des microfiches est rendue côté serveur (controllers/front/moto.php).
 * On montre/cache les cartes selon les catégories cochées + maj du compteur.
 *
 * IMPORTANT — collision avec ps_facetedsearch (app.js) :
 * app.js enregistre un handler click en phase CAPTURE sur document qui, pour
 * tout clic dans `#search_filters .facet-label`, fait preventDefault() +
 * stopImmediatePropagation(). On réutilise justement ce conteneur pour le
 * design des facettes → ce handler tuait l'événement avant tout listener bubble.
 *
 * Parade : on enregistre NOTRE handler aussi en CAPTURE sur document, mais
 * MAINTENANT (au moment de l'import de ce module, qui s'exécute AVANT le corps
 * d'app.js et donc avant son addEventListener capture). Étant enregistré en
 * premier, il s'exécute en premier ; sur nos checkboxes on prend la main
 * (toggle manuel + stopImmediatePropagation). Les vraies facettes ps (sans
 * .js-ms-plp-cb) passent intactes — on return avant de couper quoi que ce soit.
 */
(function () {
  'use strict';

  var countTemplate = null;

  function ctx() {
    var grid = document.querySelector('.js-ms-plp-grid');
    if (!grid) {
      return null;
    }
    return {
      checkboxes: Array.prototype.slice.call(document.querySelectorAll('.js-ms-plp-cb')),
      cards: Array.prototype.slice.call(grid.querySelectorAll('[data-categorie]')),
      countEl: document.querySelector('.js-ms-plp-count'),
      emptyEl: document.querySelector('.js-ms-plp-empty')
    };
  }

  function apply(c) {
    var selected = {};
    var any = false;
    c.checkboxes.forEach(function (cb) {
      if (cb.checked) { selected[cb.value] = true; any = true; }
    });

    var visible = 0;
    c.cards.forEach(function (card) {
      // any=false (aucune cochée) → on masque tout (état "Effacer").
      var show = any && selected[card.getAttribute('data-categorie')] === true;
      card.hidden = !show;
      if (show) { visible++; }
    });

    if (c.countEl) {
      if (countTemplate === null) {
        countTemplate = c.countEl.textContent.replace(/\d+/, '%d');
      }
      c.countEl.textContent = countTemplate.replace('%d', visible);
    }
    if (c.emptyEl) {
      c.emptyEl.hidden = visible !== 0;
    }
  }

  // --- Handler CAPTURE, enregistré dès l'import (avant celui d'app.js) ---
  document.addEventListener('click', function (e) {
    var label = e.target.closest('.facet-label');
    if (!label) {
      return;
    }
    var cb = label.querySelector('.js-ms-plp-cb');
    if (!cb) {
      return; // facette ps_facetedsearch réelle → on laisse app.js gérer
    }
    var c = ctx();
    if (!c) {
      return;
    }
    // C'est une de nos cases : on prend la main avant le handler natif.
    e.preventDefault();
    e.stopImmediatePropagation();
    cb.checked = !cb.checked;
    apply(c);
  }, true);

  // --- Boutons Effacer / Réinitialiser (hors .facet-label, non interceptés) ---
  function initButtons() {
    var c = ctx();
    if (!c) {
      return;
    }
    var clearBtn = document.querySelector('.js-ms-plp-clear');
    var resetBtn = document.querySelector('.js-ms-plp-reset');
    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        c.checkboxes.forEach(function (cb) { cb.checked = false; });
        apply(c);
      });
    }
    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        c.checkboxes.forEach(function (cb) { cb.checked = true; });
        apply(c);
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initButtons);
  } else {
    initButtons();
  }
})();
