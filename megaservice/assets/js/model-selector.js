/**
 * Sélecteur de moto — Modal desktop / Bottomsheet mobile
 */

document.addEventListener('DOMContentLoaded', function () {
  var modal    = document.querySelector('.js-model-modal');
  var overlay  = document.querySelector('.js-model-overlay');
  var triggers = document.querySelectorAll('.ms-header__model-btn, .js-model-trigger-mobile');

  if (!modal) return;

  var headerBtn   = document.querySelector('.ms-header__model-btn');
  var mobileBarEl = document.querySelector('.js-model-trigger-mobile');
  var STORAGE_KEY = 'ms_selected_moto';

  // ── Open / Close ─────────────────────────────

  function openModal() {
    modal.removeAttribute('hidden');
    overlay.removeAttribute('hidden');
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    modal.setAttribute('hidden', '');
    overlay.setAttribute('hidden', '');
    document.body.style.overflow = '';
  }

  triggers.forEach(function (trigger) {
    trigger.addEventListener('click', function (e) {
      e.preventDefault();
      openModal();
    });
  });

  if (mobileBarEl) {
    mobileBarEl.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        openModal();
      }
    });
  }

  if (overlay) overlay.addEventListener('click', closeModal);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeModal();
  });

  // ── Tabs ─────────────────────────────────────

  var tabs  = modal.querySelectorAll('.ms-model-modal__tab');
  var forms = modal.querySelectorAll('.ms-model-modal__form');

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      var target = tab.dataset.tab;
      tabs.forEach(function (t) { t.classList.remove('is-active'); });
      forms.forEach(function (f) { f.classList.remove('is-active'); });
      tab.classList.add('is-active');
      var form = modal.querySelector('[data-form="' + target + '"]');
      if (form) form.classList.add('is-active');
    });
  });

  // ── Helpers ──────────────────────────────────

  function selectedText(select) {
    if (!select || select.selectedIndex < 0) return '';
    var opt = select.options[select.selectedIndex];
    return opt && opt.value ? opt.text : '';
  }

  function applyFilled(label) {
    document.querySelectorAll('.js-model-current-name').forEach(function (el) {
      el.textContent = label;
    });
    if (headerBtn)   headerBtn.classList.add('has-model');
    if (mobileBarEl) mobileBarEl.classList.add('has-model');
    document.body.classList.add('has-moto-selected');
  }

  function applyEmpty() {
    if (headerBtn)   headerBtn.classList.remove('has-model');
    if (mobileBarEl) mobileBarEl.classList.remove('has-model');
    document.body.classList.remove('has-moto-selected');
  }

  // Restore depuis localStorage (en attendant le plugin serveur)
  var stored = null;
  try { stored = localStorage.getItem(STORAGE_KEY); } catch (err) {}
  if (stored) applyFilled(stored);

  // ── Tab Modèle : submit disabled tant que selects pas remplis ────

  var modelForm    = modal.querySelector('[data-form="model"]');
  var modelSubmit  = modal.querySelector('.js-model-submit');
  var modelReset   = modal.querySelector('.js-model-reset');
  var selectMarque = modal.querySelector('[name="marque"]');
  var selectAnnee  = modal.querySelector('[name="annee"]');
  var selectGamme  = modal.querySelector('[name="gamme"]');
  var selectModele = modal.querySelector('[name="modele"]');

  function modelFormReady() {
    return [selectMarque, selectAnnee, selectGamme, selectModele].every(function (s) {
      return s && s.value;
    });
  }

  function refreshModelSubmit() {
    if (modelSubmit) modelSubmit.disabled = !modelFormReady();
  }

  [selectMarque, selectAnnee, selectGamme, selectModele].forEach(function (s) {
    if (s) s.addEventListener('change', refreshModelSubmit);
  });

  if (modelForm) {
    modelForm.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!modelFormReady()) return;

      var label = [selectedText(selectAnnee), selectedText(selectMarque), selectedText(selectModele)]
        .filter(Boolean).join(' ');

      applyFilled(label);
      try { localStorage.setItem(STORAGE_KEY, label); } catch (err) {}
      closeModal();
    });
  }

  if (modelReset) {
    modelReset.addEventListener('click', function () {
      [selectMarque, selectAnnee, selectGamme, selectModele].forEach(function (s) {
        if (!s) return;
        s.selectedIndex = 0;
        if (s.name !== 'marque') s.disabled = true;
      });
      refreshModelSubmit();
      applyEmpty();
      try { localStorage.removeItem(STORAGE_KEY); } catch (err) {}
    });
  }

  // ── Tab VIN : loupe → recherche → result + enable submit ────────

  var vinForm        = modal.querySelector('[data-form="vin"]');
  var vinFieldsBlock = modal.querySelector('.js-vin-fields');
  var vinResultBlock = modal.querySelector('.js-vin-result');
  var vinResultName  = modal.querySelector('.js-vin-result-name');
  var vinResultVin   = modal.querySelector('.js-vin-result-vin');
  var vinResultRm    = modal.querySelector('.js-vin-result-remove');
  var vinSearchBtn   = modal.querySelector('.js-vin-search');
  var vinInput       = modal.querySelector('.js-vin-input');
  var vinSubmit      = modal.querySelector('.js-vin-submit');
  var vinReset       = modal.querySelector('.js-vin-reset');

  function showVinResult(label, vin) {
    if (vinResultName)  vinResultName.textContent = label;
    if (vinResultVin)   vinResultVin.textContent  = vin;
    if (vinFieldsBlock) vinFieldsBlock.setAttribute('hidden', '');
    if (vinResultBlock) vinResultBlock.removeAttribute('hidden');
    if (vinSubmit)      vinSubmit.disabled = false;
  }

  function hideVinResult() {
    if (vinFieldsBlock) vinFieldsBlock.removeAttribute('hidden');
    if (vinResultBlock) vinResultBlock.setAttribute('hidden', '');
    if (vinSubmit)      vinSubmit.disabled = true;
    if (vinInput)       vinInput.value = '';
  }

  if (vinSearchBtn) {
    vinSearchBtn.addEventListener('click', function () {
      var vin = vinInput ? vinInput.value.trim() : '';
      if (!vin) return;
      // Démo : à brancher sur l'API du plugin pour récupérer le vrai modèle
      showVinResult('KTM 990 RC R Track', vin);
    });
  }

  if (vinInput) {
    vinInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        if (vinSearchBtn) vinSearchBtn.click();
      }
    });
  }

  if (vinForm) {
    vinForm.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!vinResultName || !vinResultName.textContent) return;
      var label = vinResultName.textContent;
      applyFilled(label);
      try { localStorage.setItem(STORAGE_KEY, label); } catch (err) {}
      closeModal();
    });
  }

  if (vinResultRm) {
    vinResultRm.addEventListener('click', hideVinResult);
  }

  if (vinReset) {
    vinReset.addEventListener('click', function () {
      hideVinResult();
      applyEmpty();
      try { localStorage.removeItem(STORAGE_KEY); } catch (err) {}
    });
  }

  // ── Cascade des selects (démo — à brancher sur l'API du plugin) ──

  if (selectMarque) {
    selectMarque.addEventListener('change', function () {
      if (selectAnnee) {
        selectAnnee.disabled = false;
        selectAnnee.innerHTML = '<option value="" disabled selected>Année</option>';
        var currentYear = new Date().getFullYear();
        for (var y = currentYear; y >= 2000; y--) {
          var opt = document.createElement('option');
          opt.value = y;
          opt.textContent = y;
          selectAnnee.appendChild(opt);
        }
      }
      resetSelect(selectGamme);
      resetSelect(selectModele);
      refreshModelSubmit();
    });
  }

  if (selectAnnee) {
    selectAnnee.addEventListener('change', function () {
      if (selectGamme) {
        selectGamme.disabled = false;
        selectGamme.innerHTML = '<option value="" disabled selected>Gamme</option>'
          + '<option value="motocross">Motocross</option>'
          + '<option value="enduro">Enduro</option>'
          + '<option value="supersport">Supersport</option>'
          + '<option value="naked">Naked</option>'
          + '<option value="adventure">Adventure</option>';
      }
      resetSelect(selectModele);
      refreshModelSubmit();
    });
  }

  if (selectGamme) {
    selectGamme.addEventListener('change', function () {
      if (selectModele) {
        selectModele.disabled = false;
        selectModele.innerHTML = '<option value="" disabled selected>Modèle</option>'
          + '<option value="ktm-450-sx-f-2026">KTM 450 SX-F</option>'
          + '<option value="ktm-990-duke-r">KTM 990 DUKE R</option>'
          + '<option value="ktm-990-rc-r-track">KTM 990 RC R Track</option>'
          + '<option value="ktm-890-adventure">KTM 890 Adventure</option>';
      }
      refreshModelSubmit();
    });
  }

  if (selectModele) {
    selectModele.addEventListener('change', refreshModelSubmit);
  }

  function resetSelect(select) {
    if (!select) return;
    select.disabled = true;
    select.selectedIndex = 0;
    var placeholder = select.options[0];
    select.innerHTML = '';
    select.appendChild(placeholder);
  }
});
