/**
 * Megaservice — Product page interactions
 * Tabs, accordion livraison, gallery thumbnails, quantity +/-
 */

document.addEventListener('DOMContentLoaded', function () {

  // ── Tabs produit ────────────────────────────────────────────────────────────
  document.querySelectorAll('.ms-product__tabs-section').forEach(function (section) {
    var tabBtns = section.querySelectorAll('.js-product-tab-btn');
    var tabPanels = section.querySelectorAll('.js-product-tab-panel');

    tabBtns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var target = btn.dataset.tab;

        tabBtns.forEach(function (b) { b.classList.remove('is-active'); });
        tabPanels.forEach(function (p) { p.classList.remove('is-active'); });

        btn.classList.add('is-active');
        var panel = section.querySelector('[data-panel="' + target + '"]');
        if (panel) panel.classList.add('is-active');
      });
    });
  });

  // ── Feature group nav (fiche technique) ─────────────────────────────────────
  document.querySelectorAll('.ms-product__features').forEach(function (features) {
    var groupBtns = features.querySelectorAll('.js-feature-group-btn');
    var groups = features.querySelectorAll('.js-feature-group');

    groupBtns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var target = btn.dataset.group;

        groupBtns.forEach(function (b) { b.classList.remove('is-active'); });
        groups.forEach(function (g) { g.classList.remove('is-active'); });

        btn.classList.add('is-active');
        var group = features.querySelector('.js-feature-group[data-group="' + target + '"]');
        if (group) group.classList.add('is-active');
      });
    });
  });

  // ── Accordion livraison ──────────────────────────────────────────────────────
  document.querySelectorAll('.js-accordion-trigger').forEach(function (trigger) {
    trigger.addEventListener('click', function () {
      var expanded = trigger.getAttribute('aria-expanded') === 'true';
      var body = trigger.nextElementSibling;
      var iconPlus = trigger.querySelector('.ms-product__accordion-icon--plus');
      var iconMinus = trigger.querySelector('.ms-product__accordion-icon--minus');

      trigger.setAttribute('aria-expanded', String(!expanded));
      if (expanded) {
        body.setAttribute('hidden', '');
        if (iconPlus) iconPlus.removeAttribute('hidden');
        if (iconMinus) iconMinus.setAttribute('hidden', '');
      } else {
        body.removeAttribute('hidden');
        if (iconPlus) iconPlus.setAttribute('hidden', '');
        if (iconMinus) iconMinus.removeAttribute('hidden');
      }
    });
  });

  // ── Galerie — thumbnails ─────────────────────────────────────────────────────
  document.querySelectorAll('.js-gallery-thumbs').forEach(function (thumbsEl) {
    var gallery = thumbsEl.closest('.ms-product__gallery');
    if (!gallery) return;
    var mainImg = gallery.querySelector('.ms-product__gallery-img');
    var thumbBtns = thumbsEl.querySelectorAll('.js-gallery-thumb');

    thumbBtns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var src = btn.dataset.imageLargeSrc;
        if (!src || !mainImg) return;

        mainImg.style.opacity = '0';
        setTimeout(function () {
          mainImg.src = src;
          mainImg.style.opacity = '1';
        }, 150);

        thumbBtns.forEach(function (b) { b.classList.remove('is-active'); });
        btn.classList.add('is-active');
      });
    });
  });

  // ── Quantité +/- ─────────────────────────────────────────────────────────────
  document.querySelectorAll('.js-product-add-to-cart').forEach(function (form) {
    var minusBtn = form.querySelector('.js-qty-minus');
    var plusBtn = form.querySelector('.js-qty-plus');
    var input = form.querySelector('.ms-product__qty-input');

    if (!minusBtn || !plusBtn || !input) return;

    minusBtn.addEventListener('click', function () {
      var val = parseInt(input.value, 10) || 1;
      var min = parseInt(input.min, 10) || 1;
      if (val > min) {
        input.value = val - 1;
        input.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    plusBtn.addEventListener('click', function () {
      var val = parseInt(input.value, 10) || 1;
      input.value = val + 1;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
  });

});
