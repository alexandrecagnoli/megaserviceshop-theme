/**
 * Repli / dépli de la liste des motos compatibles au-delà du seuil.
 * Chargé uniquement sur les fiches ayant des motos compatibles.
 */
(function () {
  'use strict';

  function init() {
    var btn = document.querySelector('.js-ms-mount-more');
    var list = document.querySelector('.js-ms-mount-list');
    if (!btn || !list) { return; }

    btn.addEventListener('click', function () {
      var opened = list.classList.toggle('is-open');
      btn.textContent = opened ? btn.getAttribute('data-less') : btn.getAttribute('data-more');
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
