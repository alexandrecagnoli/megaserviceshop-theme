/**
 * Filters Bottom Sheet (mobile)
 * Handlers : open, close, apply (= close), reset (trigger PS clear-all).
 */

document.addEventListener('DOMContentLoaded', function () {
  const sheet   = document.querySelector('.js-filters-sheet');
  const overlay = document.querySelector('.js-filters-overlay');
  const trigger = document.querySelector('.js-search-toggler');
  if (!sheet || !overlay || !trigger) return;

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

  trigger.addEventListener('click', function (e) {
    e.preventDefault();
    open();
  });

  overlay.addEventListener('click', close);

  sheet.addEventListener('click', function (e) {
    if (e.target.closest('.js-filters-close, .js-filters-apply')) {
      e.preventDefault();
      close();
    }
    if (e.target.closest('.js-filters-reset')) {
      e.preventDefault();
      const clearBtn = sheet.querySelector('.js-search-filters-clear-all');
      if (clearBtn) clearBtn.click();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && sheet.classList.contains('is-open')) close();
  });
});
