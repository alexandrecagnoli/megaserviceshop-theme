/**
 * Bloc « Compatible avec » : filtre client-side de la liste des modèles + zébrage
 * recalculé sur les seules lignes visibles (le nth-child natif casserait dès
 * qu'on masque des lignes).
 */
(function () {
  'use strict';

  function normalize(s) {
    s = (s || '').toString().toLowerCase();
    if (s.normalize) {
      s = s.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }
    return s;
  }

  function init() {
    var input = document.querySelector('.js-ms-mount-search');
    var list  = document.querySelector('.js-ms-mount-list');
    if (!input || !list) { return; }

    var rows  = Array.prototype.slice.call(list.querySelectorAll('.ms-mount__row'));
    var empty = list.querySelector('.js-ms-mount-empty');

    function restripe(visible) {
      for (var i = 0; i < visible.length; i++) {
        visible[i].classList.toggle('is-alt', i % 2 === 1);
      }
    }

    function apply() {
      var q = normalize(input.value).trim();
      var visible = [];
      for (var i = 0; i < rows.length; i++) {
        var match = q === '' || normalize(rows[i].getAttribute('data-search')).indexOf(q) !== -1;
        rows[i].hidden = !match;
        if (match) { visible.push(rows[i]); }
      }
      restripe(visible);
      if (empty) { empty.hidden = visible.length !== 0; }
    }

    restripe(rows);
    input.addEventListener('input', apply);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
