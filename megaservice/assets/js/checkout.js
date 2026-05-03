/**
 * Checkout — micro handlers (collapse, etc.)
 * - .js-show-details : toggle l'affichage de la liste produits dans le récap
 * - .promo-code-button : toggle le formulaire voucher
 *   (Bootstrap data-toggle="collapse" n'est pas câblé dans le thème)
 */

document.addEventListener('DOMContentLoaded', function () {
  initCollapseToggles();
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
