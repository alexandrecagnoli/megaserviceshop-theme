/**
 * Bloc de CONTRÔLE « références remplacées / remplaçantes » sur la fiche
 * produit du back-office.
 *
 * Volontairement en LECTURE SEULE : la table est réécrite intégralement à
 * chaque import du fichier constructeur, donc toute saisie manuelle serait
 * écrasée sans trace au ré-import suivant.
 *
 * Les données sont poussées par le hook displayBackOfficeHeader dans
 * window.MS_REPLACEMENT_PANEL — pas d'appel AJAX : le bloc est statique.
 *
 * PrestaShop 8 rend la fiche produit de façon asynchrone : on réessaie
 * quelques fois avant d'abandonner (même stratégie que le module relations).
 */
(function () {
  'use strict';

  var MAX_TRIES = 20;
  var RETRY_MS  = 250;

  function esc(s) {
    return String(s === null || s === undefined ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  /** Point d'ancrage : cascade de replis, la page produit PS8 varie. */
  function findAnchor() {
    return document.querySelector('[data-role="form-product"]')
        || document.querySelector('form[name="product"]')
        || document.querySelector('.product-page form')
        || document.getElementById('product_form')
        || document.querySelector('main');
  }

  /**
   * URL d'édition d'un AUTRE produit, dérivée de la page courante.
   * On est forcément sur /…/sell/catalog/products/{id}/edit : substituer l'id
   * évite d'avoir à reconstruire une URL admin (route Symfony + token) côté PHP.
   * Retourne null si le motif ne correspond pas → on n'affiche pas de lien mort.
   */
  function siblingProductUrl(id) {
    var m = window.location.pathname.match(/^(.*\/sell\/catalog\/products(?:-v2)?\/)\d+(\/.*)$/);
    return m ? m[1] + id + m[2] : null;
  }

  function renderTarget(t) {
    var badges = '';

    if (t.missing) {
      // Cas E : le remplaçant n'existe pas au catalogue → écart de données.
      badges += '<span class="ms-repl__badge ms-repl__badge--danger">absent du catalogue</span>';
    }
    if (t.chain_status === 'loop') {
      badges += '<span class="ms-repl__badge ms-repl__badge--danger">chaîne circulaire</span>';
    }
    if (t.chain_depth > 1) {
      badges += '<span class="ms-repl__badge">chaîne · ' + esc(t.chain_depth) + ' niveaux</span>';
    }
    if (t.final_is_set) {
      badges += '<span class="ms-repl__badge">aboutit à un ensemble</span>';
    }

    var label = esc(t.ref_final || t.ref_replacement);
    var url   = t.target_id ? siblingProductUrl(t.target_id) : null;
    label = url
      ? '<a href="' + esc(url) + '" class="ms-repl__ref">' + label + '</a>'
      : '<span class="ms-repl__ref">' + label + '</span>';

    var qty = t.quantity > 1
      ? '<span class="ms-repl__qty">×' + esc(t.quantity) + '</span>' : '';

    var name = t.target_name
      ? '<span class="ms-repl__name">' + esc(t.target_name) + '</span>'
      : '<span class="ms-repl__name ms-repl__name--empty">—</span>';

    return '<li class="ms-repl__item">' + label + qty + name + badges + '</li>';
  }

  function build(data) {
    var html = ''
      + '<div class="ms-repl panel">'
      + '  <div class="ms-repl__head">'
      + '    <h3>Références remplacées / remplaçantes</h3>'
      + '    <span class="ms-repl__readonly" title="Donnée issue du fichier constructeur, réécrite à chaque import">lecture seule</span>'
      + '  </div>';

    if (data.is_replaced) {
      html += '<div class="ms-repl__section ms-repl__section--replaced">'
           +  '  <p class="ms-repl__title">Cette référence est remplacée par'
           +  (data.is_set
                ? ' un ensemble de ' + data.replaced_by.length + ' références :'
                : ' :')
           +  '</p><ul class="ms-repl__list">';
      data.replaced_by.forEach(function (t) { html += renderTarget(t); });
      html += '</ul></div>';
    }

    if (data.replaces && data.replaces.length) {
      html += '<div class="ms-repl__section">'
           +  '  <p class="ms-repl__title">Cette référence remplace '
           +  data.replaces_total + ' ancienne(s) référence(s) :</p>'
           +  '  <ul class="ms-repl__list ms-repl__list--compact">';
      data.replaces.forEach(function (r) {
        html += '<li class="ms-repl__item"><span class="ms-repl__ref">'
             +  esc(r.ref_replaced) + '</span></li>';
      });
      html += '</ul>';
      if (data.replaces_truncated) {
        html += '<p class="ms-repl__more">… liste tronquée aux 50 premières.</p>';
      }
      html += '</div>';
    }

    html += '</div>';
    return html;
  }

  function inject(tries) {
    if (document.querySelector('.ms-repl')) { return; } // déjà injecté

    var data = window.MS_REPLACEMENT_PANEL;
    if (!data) { return; }

    var anchor = findAnchor();
    if (!anchor) {
      if (tries < MAX_TRIES) {
        window.setTimeout(function () { inject(tries + 1); }, RETRY_MS);
      }
      return;
    }

    var wrap = document.createElement('div');
    wrap.innerHTML = build(data);
    anchor.insertBefore(wrap.firstChild, anchor.firstChild);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { inject(0); });
  } else {
    inject(0);
  }
})();
