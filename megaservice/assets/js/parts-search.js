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
  });
});
