/**
 * Megaservice Theme - Main JavaScript File
 * ==============================================
 */

import EventEmitter from 'events';
import './carousel.js';
import './menu.js';
import './parts-search.js';
import './product.js';

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

  // Quantity update — écoute via jQuery pour capter .trigger("focusout") du plugin
  $(document).on('focusout', '.js-cart-line-product-quantity', function() {
    var $input = $(this);
    var updateUrl = $input.data('update-url');
    if (!updateUrl) return;

    var newVal  = parseInt($input.val(), 10);
    var baseVal = parseInt($input.attr('value'), 10);
    if (isNaN(newVal) || isNaN(baseVal) || newVal === baseVal) return;

    var diff = newVal - baseVal;
    var op   = diff > 0 ? 'up' : 'down';
    var qty  = Math.abs(diff);

    $input.attr('value', newVal);

    var sep = updateUrl.indexOf('?') === -1 ? '?' : '&';
    var url = updateUrl + sep + 'qty=' + qty + '&op=' + op;

    $.ajax({
      url: url,
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      dataType: 'json',
      data: { ajax: 1, action: 'update' }
    }).done(function(resp) {
      window.prestashop.emit('updateCart', {
        reason: {
          idProduct: parseInt($input.data('product-id'), 10) || 0,
          linkAction: 'update-quantity-in-cart',
          cart: resp.cart || null
        },
        resp: resp
      });
    }).fail(function(xhr) {
      console.error('[megaservice] update-qty error:', xhr.status, xhr.responseText);
      // Libère .adding pour permettre de réessayer
      $input.closest('.product-qty-container').removeClass('adding');
    });
  });
}());

// ── Add-to-cart AJAX pour les miniatures de listing ──────────────────────────
// Le formulaire sur .ms-product-card__add-form POST classique navigue vers la page panier.
// On intercepte, on fait un POST AJAX et on émet updateCart pour que les modules
// (ex: sidebar cart plugin) se déclenchent.
document.addEventListener('submit', function(e) {
  const form = e.target.closest('.ms-product-card__add-form');
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

function initializeSortDropdown() {
  document.querySelectorAll('.products-sort-order').forEach(function(dropdown) {
    const trigger = dropdown.querySelector('.select-title');
    const menu = dropdown.querySelector('.dropdown-menu');
    if (!trigger || !menu) return;

    trigger.addEventListener('click', function(e) {
      e.stopPropagation();
      const isOpen = menu.classList.contains('show');
      closeAllSortDropdowns();
      if (!isOpen) menu.classList.add('show');
    });
  });

  document.addEventListener('click', closeAllSortDropdowns);

  function closeAllSortDropdowns() {
    document.querySelectorAll('.products-sort-order .dropdown-menu').forEach(function(m) {
      m.classList.remove('show');
    });
  }
}

(function() {
  'use strict';

  document.addEventListener('DOMContentLoaded', function() {
    initializeSearchWidget();
    initializeSortDropdown();
  });

  function initializeSearchWidget() {
    const widget = document.querySelector('.ms-search-widget');
    if (!widget) return;

    const toggle = widget.querySelector('.ms-search-widget__toggle');
    const form = widget.querySelector('.ms-search-widget__form');
    if (!toggle || !form) return;

    toggle.addEventListener('click', function() {
      const isHidden = form.hasAttribute('hidden');
      if (isHidden) {
        form.removeAttribute('hidden');
        toggle.setAttribute('aria-expanded', 'true');
        form.querySelector('input[type="text"]').focus();
      } else {
        form.setAttribute('hidden', '');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });

    document.addEventListener('click', function(e) {
      if (!widget.contains(e.target)) {
        form.setAttribute('hidden', '');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }
})();
