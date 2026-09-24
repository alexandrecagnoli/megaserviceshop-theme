import { initMotoCascade } from './moto-cascade.js';

/**
 * Section « Recherche de pièces compatibles » — home et pages catégorie.
 *
 * Ce fichier ne gérait QUE le changement d'onglet : les listes Année, Gamme et
 * Modèle étaient des <option> vides que rien ne remplissait. La section n'a donc
 * jamais fonctionné depuis avril 2026 — on choisissait une marque et plus rien
 * ne se passait. La cascade est désormais branchée sur le même endpoint que la
 * modale du header, via le module partagé moto-cascade.
 */

document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.ms-parts-search').forEach(function (section) {
    const tabs = section.querySelectorAll('.ms-parts-search__tab');
    const forms = section.querySelectorAll('.ms-parts-search__form');

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        const target = tab.dataset.tab;

        tabs.forEach(function (t) { t.classList.remove('is-active'); });
        forms.forEach(function (f) { f.classList.remove('is-active'); });

        tab.classList.add('is-active');
        const form = section.querySelector('[data-form="' + target + '"]');
        if (form) form.classList.add('is-active');
      });
    });

    // Moto déjà active côté serveur (cookie ms_moto) : on préremplit la cascade au
    // lieu de la laisser vierge alors que le header affiche la moto.
    let initial = null;
    try {
      initial = JSON.parse(section.getAttribute('data-garage-sel') || 'null');
    } catch (err) { initial = null; }

    initMotoCascade(section, {
      form: section.querySelector('[data-form="model"]'),
      submit: '[data-form="model"] .ms-parts-search__submit',
      initial: initial,
    });
  });
});
