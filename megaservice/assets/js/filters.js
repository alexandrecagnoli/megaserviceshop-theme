/**
 * Filters Bottom Sheet (mobile)
 * - Trigger via délégation : le bouton .js-search-toggler est dans #js-product-list-top
 *   qui est remplacé par le JS PS facetedsearch sur updateProductList. Sans délégation,
 *   on perd le listener après chaque application de filtre.
 * - Sheet/overlay/header/footer : éléments stables, on peut bind directement dessus.
 */

document.addEventListener('DOMContentLoaded', function () {
  initFiltersSheet();
  initToast();
});

function initFiltersSheet() {
  const sheet   = document.querySelector('.js-filters-sheet');
  const overlay = document.querySelector('.js-filters-overlay');
  if (!sheet || !overlay) return;

  function open() {
    sheet.classList.add('is-open');
    overlay.removeAttribute('hidden');
    overlay.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  }

  function close() {
    sheet.classList.remove('is-open');
    overlay.classList.remove('is-open');
    overlay.setAttribute('hidden', '');
    document.body.style.overflow = '';
  }

  // Trigger via délégation (le bouton est replaced sur AJAX)
  document.addEventListener('click', function (e) {
    if (e.target.closest('.js-search-toggler')) {
      e.preventDefault();
      open();
    }
  });

  overlay.addEventListener('click', close);

  sheet.addEventListener('click', function (e) {
    if (e.target.closest('.js-filters-close')) {
      e.preventDefault();
      close();
    } else if (e.target.closest('.js-filters-apply')) {
      e.preventDefault();
      close();
      window.msToast && window.msToast('Filtres appliqués');
    } else if (e.target.closest('.js-filters-reset')) {
      e.preventDefault();
      const clearBtn = sheet.querySelector('.js-search-filters-clear-all');
      if (clearBtn) clearBtn.click();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && sheet.classList.contains('is-open')) close();
  });
}

/**
 * Toast léger réutilisable — exposé en window.msToast(message, duration?)
 * Crée le DOM une seule fois, le réutilise.
 */
function initToast() {
  let toastEl;
  let timer;

  window.msToast = function (msg, duration) {
    duration = duration || 1800;

    if (!toastEl) {
      toastEl = document.createElement('div');
      toastEl.className = 'ms-toast';
      toastEl.setAttribute('role', 'status');
      toastEl.setAttribute('aria-live', 'polite');
      toastEl.innerHTML =
        '<span class="ms-toast__icon">' +
          '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">' +
            '<path d="M5 12L10 17L20 7" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>' +
          '</svg>' +
        '</span>' +
        '<span class="ms-toast__msg"></span>';
      document.body.appendChild(toastEl);
    }

    toastEl.querySelector('.ms-toast__msg').textContent = msg;

    // Force reflow puis ajoute la classe pour garantir l'animation in
    void toastEl.offsetWidth;
    toastEl.classList.add('is-visible');

    clearTimeout(timer);
    timer = setTimeout(function () {
      toastEl.classList.remove('is-visible');
    }, duration);
  };
}
