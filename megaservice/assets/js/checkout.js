/**
 * Checkout — micro handlers (collapse, etc.)
 * - .js-show-details : toggle l'affichage de la liste produits dans le récap
 * - .promo-code-button : toggle le formulaire voucher
 *   (Bootstrap data-toggle="collapse" n'est pas câblé dans le thème)
 * - .nav-link[data-toggle="tab"] : onglets Invité / Connexion de l'étape 1.
 *   Sans Bootstrap JS, les deux formulaires s'affichaient empilés.
 */

document.addEventListener('DOMContentLoaded', function () {
  initCollapseToggles();
  initTabs();
});

function initCollapseToggles() {
  // Délégation : couvre les contenus rechargés en AJAX (cart updates)
  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('[data-toggle="collapse"]');
    if (!trigger) return;

    var targetSelector = trigger.getAttribute('data-target') || trigger.getAttribute('href');
    if (!targetSelector) return;

    var target = document.querySelector(targetSelector);
    if (!target) return;

    e.preventDefault();
    var isOpen = target.classList.contains('in') || target.classList.contains('show');
    if (isOpen) {
      target.classList.remove('in', 'show');
      trigger.setAttribute('aria-expanded', 'false');
      trigger.classList.remove('is-open');
    } else {
      target.classList.add('in', 'show');
      trigger.setAttribute('aria-expanded', 'true');
      trigger.classList.add('is-open');
    }
  });
}

function initTabs() {
  document.addEventListener('click', function (e) {
    var link = e.target.closest('.nav-link[data-toggle="tab"]');
    if (!link) return;

    var panel = document.querySelector(link.getAttribute('href'));
    if (!panel || !panel.parentNode) return;

    e.preventDefault();

    var nav = link.closest('.nav');
    if (nav) {
      nav.querySelectorAll('.nav-link').forEach(function (l) {
        l.classList.remove('active');
        l.removeAttribute('aria-selected');
      });
    }
    link.classList.add('active');
    link.setAttribute('aria-selected', 'true');

    panel.parentNode.querySelectorAll(':scope > .tab-pane').forEach(function (p) {
      var on = p === panel;
      p.classList.toggle('active', on);
      if (on) { p.removeAttribute('aria-hidden'); } else { p.setAttribute('aria-hidden', 'true'); }
    });
  });
}
