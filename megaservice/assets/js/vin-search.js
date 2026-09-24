/**
 * Recherche par VIN — module partagé.
 * ==============================================
 *
 * La logique vivait uniquement dans model-selector.js, scopée à la modale du
 * header (`modal.querySelector(...)`). La grande section « Recherche de pièces
 * compatibles » de l'accueil et des pages catégorie avait bien un onglet VIN,
 * mais son formulaire n'était câblé nulle part : saisir un VIN n'y faisait rien.
 *
 * Plutôt qu'une deuxième copie — c'est exactement ce qui avait fait diverger la
 * cascade Marque/Année/Modèle avant moto-cascade.js — la logique est ici, et les
 * deux appelants la partagent.
 *
 * Déroulé en deux étapes :
 *   1. champ VIN + « Rechercher mon modèle »
 *   2. « 1 véhicule correspond » + « Afficher les pièces compatibles », qui
 *      navigue vers le hub de la moto — c'est LUI qui arme le garage (cookie).
 *
 * Le VIN est résolu côté serveur (module microfiches, contrôleur vinlookup) :
 * il n'existe aucun moyen de le décoder ici.
 */

const VIN_PATTERN = /^[A-HJ-NPR-Z0-9]{17}$/; // ISO 3779 : ni I, ni O, ni Q

const VIN_MESSAGES = {
  invalid:     'Le VIN comporte 17 caractères (lettres et chiffres, sans I, O ni Q). Vérifiez votre saisie.',
  not_found:   'Aucun véhicule ne correspond à ce VIN. Vérifiez la saisie, ou utilisez la recherche par modèle.',
  unavailable: 'La recherche par VIN est momentanément indisponible. Réessayez dans un instant, ou utilisez la recherche par modèle.',
  throttled:   'Trop de recherches successives. Patientez quelques minutes, ou utilisez la recherche par modèle.'
};

function normalizeVin(value) {
  return String(value || '').replace(/\s+/g, '').toUpperCase();
}

/**
 * @param {Element} root    conteneur portant les crochets js-vin-*
 * @param {Object}  options
 *   endpoint {string}    URL du contrôleur vinlookup
 *   onReset  {Function}  appelé par « Réinitialiser » (purge du filtre moto).
 *                        Absent : le bouton se contente de revenir à l'étape 1.
 */
export function initVinSearch(root, options) {
  if (!root) return;

  const opts     = options || {};
  const endpoint = opts.endpoint || '';

  const q = (sel) => root.querySelector(sel);

  const form        = q('[data-form="vin"]');
  const fieldsBlock = q('.js-vin-fields');
  const resultBlock = q('.js-vin-result');
  const resultName  = q('.js-vin-result-name');
  const resultVin   = q('.js-vin-result-vin');
  const resultRm    = q('.js-vin-result-remove');
  const searchIcon  = q('.js-vin-search');      // loupe dans le champ
  const searchMain  = q('.js-vin-search-btn');  // bouton principal, étape 1
  const input       = q('.js-vin-input');
  const submit      = q('.js-vin-submit');      // bouton principal, étape 2
  const reset       = q('.js-vin-reset');       // secondaire, étape 1
  const newSearch   = q('.js-vin-new');         // secondaire, étape 2
  const errorBox    = q('.js-vin-error');

  if (!input) return;

  let targetUrl = '';
  let busy = false;

  function showError(message) {
    if (errorBox) {
      errorBox.textContent = message || '';
      errorBox.hidden = !message;
    }
    input.classList.toggle('has-error', !!message);
  }

  function setBusy(value) {
    busy = value;
    [searchMain, searchIcon].forEach(function (b) {
      if (!b) return;
      b.disabled = value;
      b.classList.toggle('is-loading', value);
    });
  }

  // Bascule entre les deux étapes : champ + boutons de l'étape 1, ou résultat +
  // boutons de l'étape 2.
  function setStep(step) {
    const two = step === 2;
    if (fieldsBlock) fieldsBlock.hidden = two;
    if (resultBlock) resultBlock.hidden = !two;
    if (searchMain)  searchMain.hidden  = two;
    if (reset)       reset.hidden       = two;
    if (submit)      submit.hidden      = !two;
    if (newSearch)   newSearch.hidden   = !two;
  }

  function showResult(label, vin, url) {
    if (resultName) resultName.textContent = label;
    if (resultVin)  resultVin.textContent  = 'n° ' + vin;
    targetUrl = url;
    showError('');
    setStep(2);
  }

  // Retour à l'étape 1, champ vidé. Ne touche PAS au filtre moto actif : chercher
  // un autre véhicule n'est pas retirer celui du garage.
  function hideResult() {
    targetUrl = '';
    input.value = '';
    showError('');
    setStep(1);
    input.focus();
  }

  function search() {
    if (busy) return;
    const vin = normalizeVin(input.value);
    input.value = vin;

    if (!VIN_PATTERN.test(vin)) {
      showError(VIN_MESSAGES.invalid);
      return;
    }
    if (!endpoint) {
      showError(VIN_MESSAGES.unavailable);
      return;
    }

    showError('');
    setBusy(true);

    // POST : le VIN ne doit pas figurer dans l'URL (journaux d'accès, historique).
    const body = new FormData();
    body.append('vin', vin);

    fetch(endpoint, {
      method: 'POST',
      body: body,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.ok ? r.json() : { status: 'unavailable' }; })
      .then(function (data) {
        if (data && data.status === 'ok' && data.url) {
          showResult(data.label || '', data.vin || vin, data.url);
        } else {
          showError(VIN_MESSAGES[data && data.status] || VIN_MESSAGES.unavailable);
        }
      })
      .catch(function () { showError(VIN_MESSAGES.unavailable); })
      .then(function () { setBusy(false); });
  }

  if (searchIcon) searchIcon.addEventListener('click', search);
  if (searchMain) searchMain.addEventListener('click', search);

  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      search();
    }
  });
  // L'erreur affichée ne vaut plus dès que la saisie change.
  input.addEventListener('input', function () { showError(''); });

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      // Étape 1 : le bouton principal cherche. Étape 2 : il navigue vers le hub,
      // qui arme le garage côté serveur.
      if (targetUrl) {
        window.location.href = targetUrl;
      } else {
        search();
      }
    });
  }

  if (resultRm)  resultRm.addEventListener('click', hideResult);
  if (newSearch) newSearch.addEventListener('click', hideResult);

  if (reset) {
    reset.addEventListener('click', function () {
      hideResult();
      if (typeof opts.onReset === 'function') opts.onReset();
    });
  }

  setStep(1);
}
