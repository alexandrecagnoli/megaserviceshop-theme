/**
 * Panneau Relations Powerparts — page produit BO (PS 8 moderne).
 *
 * STRATÉGIE : injection JS depuis displayBackOfficeHeader.
 * Les hooks "Step" (displayAdminProductsMainStepLeftColumnBottom) ne dispatchent
 * pas vers les modules legacy dans la page produit moderne — le LegacyHookSubscriber
 * de PS 8 a un comportement inconsistant. On contourne en :
 *   1. Détectant la page produit BO via URL (/products-v2/<id>/edit)
 *   2. AJAX getState pour vérifier is_powerparts + récupérer les relations
 *   3. Si Powerparts → on construit le panneau HTML et on l'injecte dans le DOM
 *
 * Une fois injecté, l'init existant prend le relais (même bindings autocomplete,
 * drag-drop, save).
 */
(function () {
  'use strict';

  var TYPES = [
    { key: 'mandatory',   label: 'Pièces obligatoires' },
    { key: 'excluded',    label: 'Pièces exclues (incompatibilités)' },
    { key: 'recommended', label: 'Pièces recommandées' },
    { key: 'spare',       label: 'Pièces de rechange', withQty: true }
  ];

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  function boot() {
    // 1. Détecter la page produit BO via URL — pattern /products-v2/<id>/edit
    var match = window.location.pathname.match(/\/products-v2\/(\d+)\/edit/);
    if (!match) return;
    var idProduct = parseInt(match[1], 10);
    var ajaxUrl = window.MS_RELATIONS_AJAX_URL || (window.location.origin + '/index.php?fc=module&module=megaservice_relations&controller=admin');

    // 2. Fetch state initial
    ajaxFetch(ajaxUrl, { action: 'getState', id_product: idProduct })
      .then(function (resp) {
        if (!resp || !resp.success || !resp.is_powerparts) return;
        injectPanel(idProduct, ajaxUrl, resp.relations || {});
      })
      .catch(function (err) {
        console.error('[ms_relations] getState failed', err);
      });
  }

  // 3. Construit le HTML du panneau et l'injecte dans la page
  function injectPanel(idProduct, ajaxUrl, initialState) {
    if (document.querySelector('.ms-relations')) return; // déjà injecté

    var panelHTML =
      '<div class="form-wrapper ms-relations" data-ms-id-product="' + idProduct + '" data-ms-ajax-url="' + escapeAttr(ajaxUrl) + '">' +
        '<h3>Relations Powerparts</h3>' +
        '<p class="text-muted small">Glissez-déposez pour réordonner. Les modifications sont enregistrées automatiquement.</p>' +
        '<div class="ms-relations__panels">' +
          TYPES.map(function (t) {
            return (
              '<div class="ms-relations__panel' + (t.withQty ? ' ms-relations__panel--with-qty' : '') + '" data-type="' + t.key + '">' +
                '<h4>' + escapeHtml(t.label) + ' <span class="ms-relations__count badge">0</span></h4>' +
                '<div class="ms-relations__search-wrap">' +
                  '<input type="text" class="ms-relations__search form-control" placeholder="Rechercher un produit (nom ou référence)…" autocomplete="off">' +
                  '<ul class="ms-relations__results"></ul>' +
                '</div>' +
                '<ul class="ms-relations__items"></ul>' +
              '</div>'
            );
          }).join('') +
        '</div>' +
      '</div>';

    // Injection inline sous le bloc "Produits associés" de la page produit.
    // On NE tente PAS d'ajouter un onglet à la nav — PS 8 utilise Vue pour les
    // onglets natifs et toute manipulation DOM est fragile / hacky. Approche
    // propre : section visible, scroll naturel, pas de fight avec le framework.
    var anchor =
      document.getElementById('related-product') ||
      document.querySelector('[data-role="form-product"]') ||
      document.querySelector('form[name="product"]') ||
      document.querySelector('.product-page form') ||
      document.querySelector('main');
    if (!anchor) {
      document.body.insertAdjacentHTML('beforeend', panelHTML);
    } else {
      anchor.insertAdjacentHTML('afterend', panelHTML);
    }

    var root = document.querySelector('.ms-relations');
    if (!root) return;
    root.dataset.msInited = '1';
    root.querySelectorAll('.ms-relations__panel').forEach(function (panel) {
      var type = panel.dataset.type;
      var items = initialState[type] || [];
      bindPanel(panel, type, items, idProduct, ajaxUrl);
      renderItems(panel, items);
    });
  }

  function escapeAttr(s) { return String(s == null ? '' : s).replace(/"/g, '&quot;'); }

  function safeParse(s) {
    try { return JSON.parse(s); } catch (e) { return null; }
  }

  // ─── Bind d'un panneau ────────────────────────────────────────────────────
  function bindPanel(panel, type, items, idProduct, ajaxUrl) {
    var searchInput = panel.querySelector('.ms-relations__search');
    var resultsEl   = panel.querySelector('.ms-relations__results');
    var listEl      = panel.querySelector('.ms-relations__items');

    var searchTimer = null;
    var activeIndex = -1;

    // Recherche debounced
    searchInput.addEventListener('input', function () {
      clearTimeout(searchTimer);
      var q = searchInput.value.trim();
      if (q.length < 2) { closeResults(resultsEl); return; }
      searchTimer = setTimeout(function () { runSearch(q); }, 250);
    });

    searchInput.addEventListener('keydown', function (e) {
      if (!resultsEl.classList.contains('is-open')) return;
      var resultItems = resultsEl.querySelectorAll('.ms-relations__result-item:not(.ms-relations__result-item--empty)');
      if (e.key === 'ArrowDown') { e.preventDefault(); activeIndex = Math.min(activeIndex + 1, resultItems.length - 1); highlight(resultItems, activeIndex); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); activeIndex = Math.max(activeIndex - 1, 0); highlight(resultItems, activeIndex); }
      else if (e.key === 'Enter') { e.preventDefault(); if (resultItems[activeIndex]) resultItems[activeIndex].click(); }
      else if (e.key === 'Escape') { closeResults(resultsEl); }
    });

    document.addEventListener('click', function (e) {
      if (!panel.contains(e.target)) closeResults(resultsEl);
    });

    function runSearch(q) {
      ajaxFetch(ajaxUrl, { action: 'search', q: q, exclude: idProduct })
        .then(function (resp) {
          activeIndex = -1;
          if (!resp || !resp.success) { closeResults(resultsEl); return; }
          renderResults(resultsEl, resp.products || [], function (product) {
            addItem(product);
            searchInput.value = '';
            closeResults(resultsEl);
            searchInput.focus();
          });
        })
        .catch(function () { closeResults(resultsEl); });
    }

    function addItem(product) {
      // Évite les doublons sur le même type
      if (items.some(function (it) { return it.id_product === product.id_product; })) return;
      items.push({
        id_product: product.id_product,
        name: product.name,
        reference: product.reference,
        cover_url: product.cover_url,
        recommended_qty: 1
      });
      renderItems(panel, items);
      save();
    }

    listEl.addEventListener('click', function (e) {
      var btn = e.target.closest('.ms-relations__remove');
      if (!btn) return;
      var li = btn.closest('.ms-relations__item');
      var idTarget = parseInt(li.dataset.idProduct, 10);
      var idx = items.findIndex(function (it) { return it.id_product === idTarget; });
      if (idx !== -1) {
        items.splice(idx, 1);
        renderItems(panel, items);
        save();
      }
    });

    listEl.addEventListener('change', function (e) {
      var input = e.target.closest('.ms-relations__qty');
      if (!input) return;
      var li = input.closest('.ms-relations__item');
      var idTarget = parseInt(li.dataset.idProduct, 10);
      var item = items.find(function (it) { return it.id_product === idTarget; });
      if (item) {
        item.recommended_qty = Math.max(1, parseInt(input.value, 10) || 1);
        input.value = item.recommended_qty;
        save();
      }
    });

    // Drag & drop natif
    listEl.addEventListener('dragstart', function (e) {
      var li = e.target.closest('.ms-relations__item');
      if (!li) return;
      li.classList.add('is-dragging');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', li.dataset.idProduct);
    });
    listEl.addEventListener('dragend', function (e) {
      var li = e.target.closest('.ms-relations__item');
      if (li) li.classList.remove('is-dragging');
      listEl.querySelectorAll('.is-drag-over').forEach(function (el) { el.classList.remove('is-drag-over'); });
    });
    listEl.addEventListener('dragover', function (e) {
      var li = e.target.closest('.ms-relations__item');
      if (!li || li.classList.contains('is-dragging')) return;
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      li.classList.add('is-drag-over');
    });
    listEl.addEventListener('dragleave', function (e) {
      var li = e.target.closest('.ms-relations__item');
      if (li) li.classList.remove('is-drag-over');
    });
    listEl.addEventListener('drop', function (e) {
      e.preventDefault();
      var draggedId = parseInt(e.dataTransfer.getData('text/plain'), 10);
      var li = e.target.closest('.ms-relations__item');
      if (!li || !draggedId) return;
      var targetId = parseInt(li.dataset.idProduct, 10);
      reorder(items, draggedId, targetId);
      renderItems(panel, items);
      save();
    });

    function save() {
      panel.classList.add('is-saving');
      ajaxFetch(ajaxUrl, {
        action: 'save',
        id_product_source: idProduct,
        type: type,
        items: JSON.stringify(items.map(function (it) {
          return { id_product_target: it.id_product, recommended_qty: it.recommended_qty };
        }))
      }).finally(function () { panel.classList.remove('is-saving'); });
    }
  }

  // ─── Render des résultats d'autocomplete ──────────────────────────────────
  function renderResults(resultsEl, products, onSelect) {
    resultsEl.innerHTML = '';
    if (!products.length) {
      var empty = document.createElement('li');
      empty.className = 'ms-relations__result-item ms-relations__result-item--empty';
      empty.textContent = 'Aucun produit trouvé';
      resultsEl.appendChild(empty);
    } else {
      products.forEach(function (p) {
        var li = document.createElement('li');
        li.className = 'ms-relations__result-item';
        li.innerHTML =
          '<img class="ms-relations__result-img" src="' + escapeHtml(p.cover_url || '') + '" alt="">' +
          '<div class="ms-relations__result-info">' +
            '<span class="ms-relations__result-name">' + escapeHtml(p.name) + '</span>' +
            '<span class="ms-relations__result-ref">' + escapeHtml(p.reference || '') + ' · #' + p.id_product + '</span>' +
          '</div>';
        li.addEventListener('click', function () { onSelect(p); });
        resultsEl.appendChild(li);
      });
    }
    resultsEl.classList.add('is-open');
  }

  function closeResults(resultsEl) { resultsEl.classList.remove('is-open'); resultsEl.innerHTML = ''; }

  function highlight(items, index) {
    items.forEach(function (el, i) {
      el.classList.toggle('is-active', i === index);
      if (i === index) el.scrollIntoView({ block: 'nearest' });
    });
  }

  // ─── Render des items liés ────────────────────────────────────────────────
  function renderItems(panel, items) {
    var listEl = panel.querySelector('.ms-relations__items');
    var countEl = panel.querySelector('.ms-relations__count');
    var withQty = panel.classList.contains('ms-relations__panel--with-qty');

    listEl.innerHTML = '';
    if (!items.length) {
      listEl.classList.add('is-empty');
    } else {
      listEl.classList.remove('is-empty');
      items.forEach(function (it) {
        var li = document.createElement('li');
        li.className = 'ms-relations__item';
        li.draggable = true;
        li.dataset.idProduct = it.id_product;
        li.innerHTML =
          '<span class="ms-relations__handle" aria-hidden="true">⋮⋮</span>' +
          '<img class="ms-relations__item-img" src="' + escapeHtml(it.cover_url || '') + '" alt="">' +
          '<div class="ms-relations__item-info">' +
            '<span class="ms-relations__item-name">' + escapeHtml(it.name) + '</span>' +
            '<span class="ms-relations__item-ref">' + escapeHtml(it.reference || '') + ' · #' + it.id_product + '</span>' +
          '</div>' +
          (withQty
            ? '<input type="number" min="1" class="ms-relations__qty" value="' + (parseInt(it.recommended_qty, 10) || 1) + '" title="Quantité recommandée">'
            : '') +
          '<button type="button" class="ms-relations__remove" title="Retirer">×</button>';
        listEl.appendChild(li);
      });
    }
    if (countEl) countEl.textContent = items.length;
  }

  function reorder(items, draggedId, targetId) {
    var fromIdx = items.findIndex(function (it) { return it.id_product === draggedId; });
    var toIdx   = items.findIndex(function (it) { return it.id_product === targetId; });
    if (fromIdx === -1 || toIdx === -1) return;
    var moved = items.splice(fromIdx, 1)[0];
    items.splice(toIdx, 0, moved);
  }

  // ─── AJAX helper ──────────────────────────────────────────────────────────
  function ajaxFetch(url, params) {
    var body = new URLSearchParams();
    Object.keys(params).forEach(function (k) { body.append(k, params[k]); });
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: body.toString()
    }).then(function (r) { return r.json(); });
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }
})();
