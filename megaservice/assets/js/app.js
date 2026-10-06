/**
 * Megaservice Theme - Main JavaScript File
 * ==============================================
 */

import EventEmitter from 'events';
import './carousel.js';
import './menu.js';
import './parts-search.js';
import './model-selector.js';
import './product.js';
import './wishlist.js';
import './filters.js';
import './per-page.js';
import './checkout.js';
import './microfiches-plp.js';
import './microfiches-pdp.js';
import './subcat-nav.js';

// Initialise l'EventEmitter sur l'objet prestashop — requis par core.js et tous les modules PS.
// Défensif : si PS core (ou un module) a déjà initialisé prestashop.on/emit, on NE TOUCHE PAS.
// Écraser briserait les listeners déjà enregistrés par d'autres modules (ex: sidebar cart plugin).
(function initPrestashopEventEmitter() {
  const ps = window.prestashop || {};
  if (typeof ps.on === 'function' && typeof ps.emit === 'function') {
    window.prestashop = ps;
    return;
  }
  const emitter = new EventEmitter();
  emitter.setMaxListeners(100);
  ps.on             = emitter.on.bind(emitter);
  ps.once           = emitter.once.bind(emitter);
  ps.off            = emitter.off.bind(emitter);
  ps.emit           = emitter.emit.bind(emitter);
  ps.addListener    = emitter.addListener.bind(emitter);
  ps.removeListener = emitter.removeListener.bind(emitter);
  window.prestashop = ps;
}());

// ── ps_facetedsearch AJAX handlers ──────────────────────────────────────────

function fetchFacetUpdate(url) {
  fetch(url, {
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      window.prestashop.emit('updateProductList', data);
      if (data.current_url) {
        window.history.pushState('facets', '', data.current_url);
      }
    })
    .catch(function(err) { console.error('[megaservice] fetchFacetUpdate error:', err); });
}

// Fallback event from ps_facetedsearch (slider emits this)
window.prestashop.on('updateFacets', function(url) {
  fetchFacetUpdate(url);
});

// Direct handler for all filter interactions.
// ps_facetedsearch's handlers only fire on specific targets — we intercept at a broader level.
document.addEventListener('click', function(e) {
  // Any a.js-search-link anywhere in the filters block
  // (covers "Effacer tout" in #_desktop_search_filters_clear_all AND facet links)
  var searchLink = e.target.closest('#search_filters a.js-search-link');
  if (searchLink && searchLink.getAttribute('href')) {
    e.preventDefault();
    e.stopImmediatePropagation();
    fetchFacetUpdate(searchLink.getAttribute('href'));
    return;
  }

  // Button "Effacer tout" (when rendered as <button> not <a>)
  var clearBtn = e.target.closest('.js-search-filters-clear-all');
  if (clearBtn) {
    e.preventDefault();
    e.stopImmediatePropagation();
    var clearUrl = clearBtn.getAttribute('href') || clearBtn.dataset.searchUrl || clearBtn.dataset.url;
    if (clearUrl) {
      fetchFacetUpdate(clearUrl);
    } else {
      // Fallback: navigate to base URL without query params
      fetchFacetUpdate(window.location.pathname);
    }
    return;
  }

  // Any link in the active filters block
  var activeLink = e.target.closest('#js-active-search-filters a');
  if (activeLink && activeLink.getAttribute('href')) {
    e.preventDefault();
    e.stopImmediatePropagation();
    fetchFacetUpdate(activeLink.getAttribute('href'));
    return;
  }

  // Checkbox visual click (target is inside .facet-label but NOT the link itself)
  var facetLabel = e.target.closest('#search_filters .facet-label');
  if (facetLabel && !e.target.closest('a.js-search-link')) {
    e.preventDefault();
    e.stopImmediatePropagation();
    var labelLink = facetLabel.querySelector('a.js-search-link');
    if (labelLink && labelLink.getAttribute('href')) {
      fetchFacetUpdate(labelLink.getAttribute('href'));
    }
    return;
  }
}, true);

// updateProductList : émis après chaque mise à jour AJAX
window.prestashop.on('updateProductList', function(data) {
  function swapBlock(selector, html) {
    if (!html) return;
    var $doc = jQuery(html);
    var $match = $doc.filter(selector);
    jQuery(selector).replaceWith($match.length ? $match : $doc);
  }

  swapBlock('#js-product-list',        data.rendered_products);
  swapBlock('#js-product-list-top',    data.rendered_products_top);
  swapBlock('#js-product-list-bottom', data.rendered_products_bottom);

  if (data.rendered_facets) {
    var $facets = jQuery(data.rendered_facets).filter('#search_filters');
    if ($facets.length) {
      jQuery('#search_filters').replaceWith($facets);
    }
  }

  if (data.rendered_active_filters) {
    var $activeFilters = jQuery(data.rendered_active_filters).filter('#js-active-search-filters');
    if ($activeFilters.length) {
      jQuery('#js-active-search-filters').replaceWith($activeFilters);
    }
  }

  initializeSortDropdown();
});

// ── Handlers AJAX panier (delete + qty update) ───────────────────────────────
// En l'absence du core.js Classic, ces interactions naviguent ou restent bloquées.
// On utilise jQuery (fourni par PS) pour rester aligné sur le système d'events
// du plugin an_sidebarcart (qui fait .trigger('focusout') via jQuery).
(function initCartAjaxHandlers() {
  if (!window.jQuery) { return; }
  var $ = window.jQuery;

  // Delete from cart — match le pattern Classic cart.js à la lettre
  $(document).on('click', '[data-link-action="delete-from-cart"]', function(e) {
    e.preventDefault();
    var $link = $(this);
    var $line = $link.closest('.cart-product-line');
    var url = $link.prop('href');  // prop() au lieu d'attr() → URL totalement résolue (pas d'encoding &amp;)
    if (!url) return;

    $line.addClass('is-removing');

    // Safety net : si le DOM n'est pas remplacé dans les 2s, on retire la classe
    var safetyTimer = setTimeout(function() { $line.removeClass('is-removing'); }, 2000);

    $.post(url, { ajax: 1, action: 'update' }, null, 'json')
      .done(function(resp) {
        clearTimeout(safetyTimer);
        window.prestashop.emit('updateCart', {
          reason: {
            idProduct: parseInt($link.data('id-product'), 10) || 0,
            idProductAttribute: parseInt($link.data('id-product-attribute'), 10) || 0,
            linkAction: 'delete-from-cart',
            cart: resp && resp.cart || null
          },
          resp: resp
        });
      })
      .fail(function(xhr) {
        clearTimeout(safetyTimer);
        console.error('[megaservice] delete-from-cart failed:', xhr.status, xhr.responseText ? xhr.responseText.substring(0, 300) : '(no body)');
        $line.removeClass('is-removing');
      });
  });

  // Quantity update — sur change d'un <select> (1..10)
  $(document).on('change', '.js-cart-line-product-quantity', function() {
    var $select = $(this);
    var updateUrl = $select.data('update-url');
    if (!updateUrl) return;

    var newVal  = parseInt($select.val(), 10);

    // La quantité d'avant : nécessaire parce que CartController attend un DELTA
    // (`qty` + `op=up|down`), jamais une quantité cible.
    //
    // Elle était lue dans `data-current-qty`, que le template ne pose pas (REC-25) :
    // le champ a été converti de <select> en <input type="number"> sans reporter
    // l'attribut. `baseVal` valait donc NaN, le garde-fou ci-dessous coupait, et
    // AUCUNE requête ne partait — d'où un prix qui ne bougeait pas et une quantité
    // revenue à 1 au rechargement.
    //
    // On se replie sur `defaultValue`, c'est-à-dire l'attribut `value` rendu par le
    // serveur, donc la quantité réellement en panier. Plus rien à maintenir en
    // double dans le template : le bug ne peut pas se reproduire si quelqu'un
    // retouche le champ.
    var baseVal = parseInt($select.data('current-qty'), 10);
    if (isNaN(baseVal)) {
      baseVal = parseInt(this.defaultValue, 10);
    }
    if (isNaN(newVal) || isNaN(baseVal) || newVal === baseVal) return;

    var diff = newVal - baseVal;
    var op   = diff > 0 ? 'up' : 'down';
    var qty  = Math.abs(diff);

    // Met à jour le state local au cas où l'utilisateur rechange avant updateCart
    $select.data('current-qty', newVal);

    var sep = updateUrl.indexOf('?') === -1 ? '?' : '&';
    var url = updateUrl + sep + 'qty=' + qty + '&op=' + op;

    $.post(url, { ajax: 1, action: 'update' }, null, 'json')
      .done(function(resp) {
        window.prestashop.emit('updateCart', {
          reason: {
            idProduct: parseInt($select.data('product-id'), 10) || 0,
            linkAction: 'update-quantity-in-cart',
            cart: resp && resp.cart || null
          },
          resp: resp
        });
      })
      .fail(function(xhr) {
        console.error('[megaservice] update-qty failed:', xhr.status, xhr.responseText ? xhr.responseText.substring(0, 300) : '(no body)');
      });
  });

  // ── Rafraîchissement de l'affichage après modification du panier ──────────
  //
  // Le thème ÉMETTAIT updateCart sans que personne ne l'écoute. Même requête
  // partie et base à jour, l'écran gardait les anciens montants jusqu'au
  // rechargement : c'est le second volet de REC-25 (« pas de mise à jour du
  // prix final »). Corriger le delta seul n'aurait rien changé à l'écran.
  //
  // Le cœur rend déjà les blocs pour nous (CartController, action=refresh) :
  // on les injecte plutôt que de recomposer les montants en JS, sans quoi il
  // faudrait réimplémenter remises, écotaxe, frais de port et arrondis.
  //
  // Chaque bloc est remplacé seulement s'il est présent : la page panier et le
  // tunnel de commande n'en affichent pas les mêmes.
  var CART_BLOCKS = [
    ['.js-cart',                           'cart_detailed'],
    ['.js-cart-detailed-totals',           'cart_detailed_totals'],
    ['.js-cart-summary-products',          'cart_summary_products'],
    ['.js-cart-summary-subtotals-container', 'cart_summary_subtotals_container'],
    ['.js-cart-summary-totals',            'cart_summary_totals'],
    ['#items-subtotal',                    'cart_summary_items_subtotal']
  ];

  window.prestashop.on('updateCart', function() {
    // L'URL est posée par le template sur la racine du panier détaillé.
    var refreshUrl = $('.js-cart').data('refresh-url');
    if (!refreshUrl) return;

    $.get(refreshUrl, null, null, 'json')
      .done(function(resp) {
        if (!resp) return;
        CART_BLOCKS.forEach(function(pair) {
          var $target = $(pair[0]);
          var html    = resp[pair[1]];
          if ($target.length && typeof html === 'string' && html !== '') {
            $target.replaceWith(html);
          }
        });
      })
      .fail(function(xhr) {
        // Pas de repli silencieux : mieux vaut un rechargement visible qu'un
        // panier affichant des montants faux.
        console.error('[megaservice] cart refresh failed:', xhr.status);
        window.location.reload();
      });
  });
}());

// ── Add-to-cart AJAX pour les miniatures de listing + pièces de rechange ────
// Sans intercept, le POST natif navigue vers /panier.
// Sélecteur générique : tous les formulaires marqués js-ajax-add-to-cart, plus
// rétrocompatibilité des classes existantes (.ms-product-card__add-form,
// .ms-spare-list__add-form). Émet updateCart pour réveiller le sidebar plugin.
document.addEventListener('submit', function(e) {
  const form = e.target.closest('.ms-product-card__add-form, .ms-spare-list__add-form, .js-ajax-add-to-cart');
  if (!form) return;
  e.preventDefault();

  const formData = new FormData(form);
  formData.append('ajax', '1');
  formData.append('action', 'update');
  formData.append('add', '1');

  fetch(form.getAttribute('action'), {
    method: 'POST',
    body: formData,
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.hasError && data.errors && data.errors.length) {
        alert(data.errors.join('\n'));
        return;
      }
      window.prestashop.emit('updateCart', {
        reason: {
          idProduct: parseInt(formData.get('id_product'), 10) || 0,
          idProductAttribute: parseInt(formData.get('id_product_attribute'), 10) || 0,
          linkAction: 'add-to-cart',
          cart: data.cart || null
        },
        resp: data
      });
    })
    .catch(function(err) {
      console.error('[megaservice] add-to-cart error:', err);
    });
});

// ── Sync qty select → hidden input des pièces de rechange ────────────────────
// Le <select class="ms-spare-list__qty"> est en dehors du <form>. Sans sync,
// le POST envoie toujours la recommended_qty figée. On reflète la valeur du
// select dans <input type="hidden" name="qty"> du form sibling dans la même <li>.
document.addEventListener('change', function(e) {
  const select = e.target.closest('.ms-spare-list__qty');
  if (!select) return;
  const row = select.closest('.ms-spare-list__row');
  if (!row) return;
  const hidden = row.querySelector('.ms-spare-list__add-form input[name="qty"]');
  if (hidden) hidden.value = select.value;
});

// Event delegation : marche pour tout .products-sort-order, même ajouté
// dynamiquement par un bundle Vue après DOMContentLoaded (cas wishlist).
function closeAllSortDropdowns() {
  document.querySelectorAll('.products-sort-order .dropdown-menu').forEach(function(m) {
    m.classList.remove('show');
  });
}

document.addEventListener('click', function(e) {
  var trigger = e.target.closest('.products-sort-order .select-title');
  if (trigger) {
    e.stopPropagation();
    var menu = trigger.closest('.products-sort-order').querySelector('.dropdown-menu');
    if (!menu) return;
    var isOpen = menu.classList.contains('show');
    closeAllSortDropdowns();
    if (!isOpen) menu.classList.add('show');
    return;
  }
  // Clic ailleurs → ferme tous les dropdowns
  closeAllSortDropdowns();
});

// Stub pour compat avec les appels existants (updateProductList le call encore)
function initializeSortDropdown() { /* noop : l'event delegation fait tout */ }
