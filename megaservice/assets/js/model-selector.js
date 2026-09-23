/**
 * Sélecteur de moto — Modal desktop / Bottomsheet mobile
 */

document.addEventListener('DOMContentLoaded', function () {
  var modal    = document.querySelector('.js-model-modal');
  var overlay  = document.querySelector('.js-model-overlay');
  var triggers = document.querySelectorAll('.ms-header__model-btn, .js-model-trigger-mobile, .js-model-trigger');

  if (!modal) return;

  var headerBtn   = document.querySelector('.ms-header__model-btn');
  var mobileBarEl = document.querySelector('.js-model-trigger-mobile');
  var STORAGE_KEY = 'ms_selected_moto';

  // ── Open / Close ─────────────────────────────

  var restored = false;

  function openModal() {
    modal.removeAttribute('hidden');
    overlay.removeAttribute('hidden');
    document.body.style.overflow = 'hidden';
    // Rejoue la sélection mémorisée une fois (DOM neuf après navigation) pour
    // que « Afficher les pièces compatibles » soit ré-accessible directement.
    if (!restored) {
      restored = true;
      var sel = readSelection();
      if (sel) restoreSelection(sel);
    }
  }

  // Lecture tolérante du localStorage : nouveau format JSON, ou ancien libellé brut.
  function readSelection() {
    var raw = null;
    try { raw = localStorage.getItem(STORAGE_KEY); } catch (err) {}
    if (!raw) return null;
    try {
      var obj = JSON.parse(raw);
      return (obj && typeof obj === 'object') ? obj : { label: raw };
    } catch (err) {
      return { label: raw };
    }
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

  // « Retirer le filtre » des bandeaux de contexte moto.
  //
  // Ces liens pointaient vers ?ms_clear_moto=1, qui ne vide QUE le cookie
  // serveur : le sélecteur du header, lui, se restaure depuis localStorage et
  // continuait d'afficher la moto comme active. Symétrique exact du bug inverse
  // corrigé le 23/09, où le reset de la modale ne vidait que localStorage.
  //
  // On passe donc par clearMotoFilter(), qui purge LES DEUX sources. En
  // délégation : les bandeaux sont rendus côté serveur sur des pages variées
  // (catégorie, hub moto, microfiche) et remplacés par le JS des facettes.
  //
  // Le href reste en place et sert de repli sans JS sur les pages catégorie.
  document.addEventListener('click', function (e) {
    var clear = e.target.closest('.js-model-clear');
    if (!clear) return;
    e.preventDefault();
    clearMotoFilter();
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

  // Le filtre moto a deux supports : localStorage (affichage, ci-dessus) et le
  // cookie PS `ms_moto` (filtrage réel des catégories, via ps_facetedsearch).
  // Vider le seul localStorage laissait le back-end filtrer sur l'ancienne moto
  // — interface « aucune moto » et catalogue toujours filtré. On purge donc les
  // deux, le cookie via l'endpoint du module montabilité (joignable depuis
  // n'importe quelle page, contrairement à ?ms_clear_moto=1 qui n'est traité
  // que par l'override CategoryController).
  var CLEAR_ENDPOINT = modal.getAttribute('data-clear-endpoint') || '';

  function clearMotoFilter() {
    try { localStorage.removeItem(STORAGE_KEY); } catch (err) {}
    applyEmpty();

    if (!CLEAR_ENDPOINT) return;

    fetch(CLEAR_ENDPOINT, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        // Rechargement UNIQUEMENT si un filtre était réellement armé : le
        // contenu affiché était alors filtré et ment désormais. Sinon on ne
        // touche à rien, la modale reste ouverte pour choisir une autre moto.
        if (data && data.cleared) {
          // PAS reload() : l'URL courante peut porter ?moto=…, que le serveur
          // relit pour ARMER le garage. On vidait donc le cookie, puis on le
          // réarmait dans la foulée — le filtre « se désactivait un instant et
          // revenait ». On recharge sur l'URL débarrassée des paramètres qui
          // réarment.
          var u = new URL(window.location.href);
          u.searchParams.delete('moto');
          u.searchParams.delete('ms_clear_moto');
          window.location.href = u.pathname + (u.search === '?' ? '' : u.search) + u.hash;
        }
      })
      .catch(function () { /* réseau HS : le localStorage est déjà purgé */ });
  }

  // État « moto sélectionnée » du header et de la barre mobile.
  //
  // LE SERVEUR FAIT FOI. Il connaît le garage (cookie ms_moto) quelle que soit la
  // voie d'entrée : modale, section « Recherche de pièces », lien ?moto=, ou
  // simple retour du client sur le site. localStorage, lui, n'était écrit que par
  // cette modale — d'où un header affichant « Sélectionnez votre modèle » alors
  // qu'une moto était bien active, et l'inverse après expiration du cookie.
  //
  // localStorage n'est plus qu'un cache : on le resynchronise sur le serveur.
  var serverHasMoto = modal.getAttribute('data-garage') === '1';
  var serverLabel   = modal.getAttribute('data-garage-label') || '';
  var storedSel     = readSelection();

  if (serverHasMoto && serverLabel) {
    applyFilled(serverLabel);
    if (!storedSel || storedSel.label !== serverLabel) {
      // Entrée par une autre voie que la modale : on complète le cache. La
      // sélection détaillée (marque/année/…) reste inconnue, mais le libellé
      // suffit à l'affichage, et la modale se recharge depuis le serveur.
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ label: serverLabel }));
      } catch (err) {}
    }
  } else {
    // Garage vide côté serveur : un reliquat de localStorage ne doit pas faire
    // croire à un filtre actif.
    applyEmpty();
    if (storedSel) {
      try { localStorage.removeItem(STORAGE_KEY); } catch (err) {}
    }
  }

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
      // On mémorise la sélection COMPLÈTE pour pouvoir rejouer la cascade à la
      // réouverture de la modale (pas seulement le libellé d'affichage).
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify({
          marque:      selectMarque.value,
          annee:       selectAnnee.value,
          type:        selectGamme.value,
          modeleUrl:   selectModele.value,
          modeleLabel: selectedText(selectModele),
          label:       label
        }));
      } catch (err) {}

      // selectModele.value = URL de la page hub de la moto → on y navigue.
      var url = selectModele ? selectModele.value : '';
      if (url) {
        window.location.href = url;
      } else {
        closeModal();
      }
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
      clearMotoFilter();
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
      clearMotoFilter();
    });
  }

  // ── Cascade AJAX (Marque → Année → Pratique → Modèle) ────────────

  var ENDPOINT = modal.getAttribute('data-selector-endpoint') || '';

  function fetchStep(params, cb) {
    if (!ENDPOINT) { cb([]); return; }
    var qs = Object.keys(params).map(function (k) {
      return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
    }).join('&');
    var url = ENDPOINT + (ENDPOINT.indexOf('?') === -1 ? '?' : '&') + qs;

    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) { cb((d && d.items) || []); })
      .catch(function () { cb([]); });
  }

  // Remplit un select avec les items reçus (value/label) + placeholder en tête.
  function fillSelect(select, placeholder, items) {
    if (!select) return;
    select.innerHTML = '';
    var ph = document.createElement('option');
    ph.value = ''; ph.disabled = true; ph.selected = true;
    ph.textContent = placeholder;
    select.appendChild(ph);
    items.forEach(function (it) {
      var opt = document.createElement('option');
      opt.value = it.value;
      opt.textContent = it.label;
      select.appendChild(opt);
    });
    select.disabled = items.length === 0;
  }

  // Rejoue la cascade pour re-sélectionner marque → année → pratique → modèle
  // depuis une sélection mémorisée (chaînage async car chaque niveau est AJAX).
  function restoreSelection(sel) {
    if (!sel || !sel.marque || !selectMarque) return;
    selectMarque.value = sel.marque;
    fetchStep({ marque: sel.marque }, function (years) {
      fillSelect(selectAnnee, 'Année', years);
      if (!sel.annee) { refreshModelSubmit(); return; }
      selectAnnee.value = sel.annee;
      fetchStep({ marque: sel.marque, annee: sel.annee }, function (types) {
        fillSelect(selectGamme, 'Pratique', types);
        if (!sel.type) { refreshModelSubmit(); return; }
        selectGamme.value = sel.type;
        fetchStep({ marque: sel.marque, annee: sel.annee, type: sel.type }, function (models) {
          fillSelect(selectModele, 'Modèle', models);
          if (sel.modeleUrl) selectModele.value = sel.modeleUrl;
          refreshModelSubmit();
        });
      });
    });
  }

  if (selectMarque) {
    selectMarque.addEventListener('change', function () {
      resetSelect(selectAnnee);
      resetSelect(selectGamme);
      resetSelect(selectModele);
      refreshModelSubmit();
      if (!selectMarque.value) return;
      fetchStep({ marque: selectMarque.value }, function (items) {
        fillSelect(selectAnnee, 'Année', items);
      });
    });
  }

  if (selectAnnee) {
    selectAnnee.addEventListener('change', function () {
      resetSelect(selectGamme);
      resetSelect(selectModele);
      refreshModelSubmit();
      if (!selectAnnee.value) return;
      fetchStep({ marque: selectMarque.value, annee: selectAnnee.value }, function (items) {
        fillSelect(selectGamme, 'Pratique', items);
      });
    });
  }

  if (selectGamme) {
    selectGamme.addEventListener('change', function () {
      resetSelect(selectModele);
      refreshModelSubmit();
      if (!selectGamme.value) return;
      fetchStep({ marque: selectMarque.value, annee: selectAnnee.value, type: selectGamme.value }, function (items) {
        fillSelect(selectModele, 'Modèle', items);
      });
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
    if (placeholder) select.appendChild(placeholder);
  }
});
