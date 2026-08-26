/**
 * PR7 — PDP microfiche : liaison visuelle pastille ↔ ligne pièce.
 * Clic/survol d'une pastille de la vue éclatée → surligne la ligne pièce
 * correspondante (et scroll vers elle), et réciproquement. Appariement par
 * l'attribut data-hotspot partagé entre la pastille et la ligne.
 *
 * Se garde de tourner hors PDP (présence du viewer).
 */
(function () {
  'use strict';

  function init() {
    var viewer = document.querySelector('.ms-pdp__viewer');
    if (!viewer) {
      return; // pas sur la PDP
    }

    var dots  = Array.prototype.slice.call(document.querySelectorAll('.ms-pdp__dot'));
    var parts = Array.prototype.slice.call(document.querySelectorAll('.ms-pdp__part'));

    function partsFor(id) {
      return parts.filter(function (p) { return p.getAttribute('data-hotspot') === id; });
    }
    function dotsFor(id) {
      return dots.filter(function (d) { return d.getAttribute('data-hotspot') === id; });
    }

    function clearActive() {
      dots.forEach(function (d) { d.classList.remove('is-active'); });
      parts.forEach(function (p) { p.classList.remove('is-active'); });
    }

    function activate(id, scrollToPart) {
      clearActive();
      dotsFor(id).forEach(function (d) { d.classList.add('is-active'); });
      var matched = partsFor(id);
      matched.forEach(function (p) { p.classList.add('is-active'); });
      if (scrollToPart && matched.length) {
        matched[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    }

    dots.forEach(function (dot) {
      var id = dot.getAttribute('data-hotspot');
      dot.addEventListener('mouseenter', function () { activate(id, false); });
      dot.addEventListener('click', function () { activate(id, true); });
    });

    parts.forEach(function (part) {
      var id = part.getAttribute('data-hotspot');
      part.addEventListener('mouseenter', function () { activate(id, false); });
    });

    // Quitter le survol de la zone → reset.
    var body = document.querySelector('.ms-pdp__body');
    if (body) {
      body.addEventListener('mouseleave', clearActive);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
