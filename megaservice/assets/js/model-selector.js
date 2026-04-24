/**
 * Sélecteur de moto — Modal desktop / Bottomsheet mobile
 */

document.addEventListener('DOMContentLoaded', function () {
  var modal    = document.querySelector('.js-model-modal');
  var overlay  = document.querySelector('.js-model-overlay');
  var triggers = document.querySelectorAll('.ms-header__model-btn, .js-model-trigger-mobile');

  if (!modal) return;

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

  // Mobile bar : keyboard support
  var mobileBar = document.querySelector('.js-model-trigger-mobile');
  if (mobileBar) {
    mobileBar.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        openModal();
      }
    });
  }

  if (overlay) {
    overlay.addEventListener('click', closeModal);
  }

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

  // ── État : résultat trouvé ────────────────────

  var fieldsBlock    = modal.querySelector('.js-model-fields');
  var resultBlock    = modal.querySelector('.js-model-result');
  var resultName     = modal.querySelector('.js-model-result-name');
  var resultVin      = modal.querySelector('.js-model-result-vin');
  var resultCount    = modal.querySelector('.js-model-result-count');
  var resultRemove   = modal.querySelector('.js-model-result-remove');
  var secondaryBtn   = modal.querySelector('.js-model-secondary');
  var secondaryLabel = modal.querySelector('.js-model-secondary-label');
  var headerBtn      = document.querySelector('.ms-header__model-btn');
  var headerSpan     = headerBtn ? headerBtn.querySelector('span') : null;

  // Simule la sélection après soumission du form modèle
  var modelForm = modal.querySelector('[data-form="model"]');
  if (modelForm) {
    modelForm.addEventListener('submit', function (e) {
      e.preventDefault();

      var marque = modelForm.querySelector('[name="marque"]').value;
      var annee  = modelForm.querySelector('[name="annee"]').value;
      var gamme  = modelForm.querySelector('[name="gamme"]').value;
      var modele = modelForm.querySelector('[name="modele"]').value;

      if (!modele) return;

      var label = [annee, marque, modele].filter(Boolean).join(' ');

      // Affiche le résultat
      if (resultName)  resultName.textContent  = label;
      if (resultVin)   resultVin.textContent    = '';
      if (resultCount) resultCount.textContent  = '1 véhicule correspond à votre recherche';

      if (fieldsBlock) fieldsBlock.setAttribute('hidden', '');
      if (resultBlock) resultBlock.removeAttribute('hidden');

      // Bouton secondaire → "Nouvelle recherche"
      if (secondaryLabel) secondaryLabel.textContent = 'Nouvelle recherche';

      // Met à jour le bouton header
      updateHeaderBtn(label, true);
    });
  }

  // Réinitialiser / Nouvelle recherche
  if (secondaryBtn) {
    secondaryBtn.addEventListener('click', function () {
      resetModel();
    });
  }

  // Supprimer le résultat
  if (resultRemove) {
    resultRemove.addEventListener('click', function () {
      resetModel();
    });
  }

  function resetModel() {
    if (fieldsBlock) fieldsBlock.removeAttribute('hidden');
    if (resultBlock) resultBlock.setAttribute('hidden', '');

    // Reset les selects
    var selects = modal.querySelectorAll('.js-model-select');
    selects.forEach(function (s) {
      s.selectedIndex = 0;
      if (s.name !== 'marque') s.disabled = true;
    });

    updateHeaderBtn('', false);
  }

  function updateHeaderBtn(label, hasModel) {
    if (!headerBtn) return;

    if (hasModel) {
      headerBtn.classList.add('has-model');
      if (headerSpan) {
        headerSpan.innerHTML = label;
      }
    } else {
      headerBtn.classList.remove('has-model');
      if (headerSpan) {
        headerSpan.innerHTML = 'Sélectionnez<strong>votre modèle</strong>';
      }
    }
  }

  // ── Cascade des selects ───────────────────────
  // (à brancher sur l'API réelle — pour l'instant structure de démo)

  var selectMarque = modal.querySelector('[name="marque"]');
  var selectAnnee  = modal.querySelector('[name="annee"]');
  var selectGamme  = modal.querySelector('[name="gamme"]');
  var selectModele = modal.querySelector('[name="modele"]');

  if (selectMarque) {
    selectMarque.addEventListener('change', function () {
      // Active le select suivant et peuple avec années (démo)
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
    });
  }

  if (selectAnnee) {
    selectAnnee.addEventListener('change', function () {
      if (selectGamme) {
        selectGamme.disabled = false;
        // TODO: brancher sur API — gammes démo
        selectGamme.innerHTML = '<option value="" disabled selected>Gamme</option>'
          + '<option value="motocross">Motocross</option>'
          + '<option value="enduro">Enduro</option>'
          + '<option value="supersport">Supersport</option>'
          + '<option value="naked">Naked</option>'
          + '<option value="adventure">Adventure</option>';
      }
      resetSelect(selectModele);
    });
  }

  if (selectGamme) {
    selectGamme.addEventListener('change', function () {
      if (selectModele) {
        selectModele.disabled = false;
        // TODO: brancher sur API — modèles démo
        selectModele.innerHTML = '<option value="" disabled selected>Modèle</option>'
          + '<option value="ktm-450-sx-f-2026">KTM 450 SX-F 2026</option>'
          + '<option value="ktm-990-duke-r">KTM 990 DUKE R</option>'
          + '<option value="ktm-890-adventure">KTM 890 Adventure</option>';
      }
    });
  }

  function resetSelect(select) {
    if (!select) return;
    select.disabled = true;
    select.selectedIndex = 0;
    // Garde seulement le placeholder
    var placeholder = select.options[0];
    select.innerHTML = '';
    select.appendChild(placeholder);
  }
});
