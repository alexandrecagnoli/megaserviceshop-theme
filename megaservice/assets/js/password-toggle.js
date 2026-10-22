/**
 * Bouton « Afficher / Masquer » des champs mot de passe.
 *
 * Le gabarit (form-fields.tpl) pose bien le bouton [data-action="show-password"]
 * et les libellés data-text-show / data-text-hide, mais aucun script du thème ne
 * les gérait : le clic ne faisait rien (le JS qui s'en chargeait vivait dans le
 * thème Classic de PrestaShop, pas dans celui-ci).
 */

document.addEventListener('click', function (e) {
  var btn = e.target.closest('[data-action="show-password"]');
  if (!btn) return;

  var group = btn.closest('.input-group');
  var input = group && group.querySelector('input.js-visible-password');
  if (!input) return;

  e.preventDefault();

  var reveal = input.getAttribute('type') === 'password';
  input.setAttribute('type', reveal ? 'text' : 'password');
  btn.textContent = btn.getAttribute(reveal ? 'data-text-hide' : 'data-text-show') || btn.textContent;
  btn.setAttribute('aria-pressed', reveal ? 'true' : 'false');
});
