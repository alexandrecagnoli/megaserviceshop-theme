/**
 * Cascade de sélection moto — Marque → Année → Pratique → Modèle.
 *
 * Module partagé. Il existait déjà une implémentation dans model-selector.js
 * (modale du header), et la section de la home avait, elle, des listes vides que
 * RIEN ne remplissait : elle n'a jamais fonctionné depuis avril 2026. C'est
 * précisément ce qu'on évite en factorisant — deux copies d'une même mécanique
 * divergent, et l'une des deux finit par ne plus être entretenue.
 *
 * Alimenté par le front controller `selectordata` du module microfiches.
 * Réponse : { items: [ {value, label}, … ] }. Pour le dernier niveau, `value`
 * est directement l'URL de la page moto : on y navigue au submit.
 */

export function initMotoCascade(root, options) {
  options = options || {};

  var endpoint = root.getAttribute('data-selector-endpoint') || '';
  var marque   = root.querySelector('[name="marque"]');
  var annee    = root.querySelector('[name="annee"]');
  var gamme    = root.querySelector('[name="gamme"]');
  var modele   = root.querySelector('[name="modele"]');
  var form     = options.form || root.querySelector('form');
  var submit   = options.submit ? root.querySelector(options.submit) : null;

  if (!endpoint || !marque || !annee || !gamme || !modele) {
    return; // balisage incomplet : on ne casse rien
  }

  // Les niveaux dépendants démarrent désactivés : un select ouvrable mais vide
  // laisse croire à une panne. On les rouvre au fur et à mesure.
  [annee, gamme, modele].forEach(function (s) { s.disabled = true; });

  function placeholderOf(select) {
    return select.options.length ? select.options[0] : null;
  }

  function reset(select) {
    var ph = placeholderOf(select);
    select.innerHTML = '';
    if (ph) { select.appendChild(ph); }
    select.selectedIndex = 0;
    select.disabled = true;
  }

  function fill(select, items) {
    var ph = placeholderOf(select);
    select.innerHTML = '';
    if (ph) { select.appendChild(ph); }
    items.forEach(function (it) {
      var opt = document.createElement('option');
      opt.value = it.value;
      opt.textContent = it.label;
      select.appendChild(opt);
    });
    select.selectedIndex = 0;
    select.disabled = items.length === 0;
  }

  function fetchStep(params, cb) {
    var qs = Object.keys(params).map(function (k) {
      return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
    }).join('&');

    fetch(endpoint + (endpoint.indexOf('?') !== -1 ? '&' : '?') + qs, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.ok ? r.json() : { items: [] }; })
      .then(function (d) { cb((d && d.items) || []); })
      .catch(function () { cb([]); });
  }

  function refreshSubmit() {
    if (submit) { submit.disabled = !modele.value; }
  }

  marque.addEventListener('change', function () {
    reset(annee); reset(gamme); reset(modele); refreshSubmit();
    if (!marque.value) { return; }
    fetchStep({ marque: marque.value }, function (items) { fill(annee, items); });
  });

  annee.addEventListener('change', function () {
    reset(gamme); reset(modele); refreshSubmit();
    if (!annee.value) { return; }
    fetchStep({ marque: marque.value, annee: annee.value }, function (items) { fill(gamme, items); });
  });

  gamme.addEventListener('change', function () {
    reset(modele); refreshSubmit();
    if (!gamme.value) { return; }
    fetchStep({ marque: marque.value, annee: annee.value, type: gamme.value }, function (items) {
      fill(modele, items);
    });
  });

  modele.addEventListener('change', refreshSubmit);
  refreshSubmit();

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      // value du dernier niveau = URL de la page moto (cf. selectordata).
      if (modele.value) { window.location.href = modele.value; }
    });
  }
}
