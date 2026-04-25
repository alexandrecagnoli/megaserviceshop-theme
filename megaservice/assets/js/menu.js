/**
 * Menu Sidebar Toggle
 * Gère l'ouverture et la fermeture du menu latéral
 */

class MenuToggle {
  constructor() {
    this.menuToggle = document.querySelector('.ms-header__burger');
    this.menu = document.querySelector('#ms-sidebar');
    this.menuClose = document.querySelector('.ms-menu__close');
    this.body = document.body;
    
    // Boutons chevron pour collapse — séparés des liens (qui restent navigables)
    this.toggles = document.querySelectorAll('.ms-menu__toggle[data-toggle="true"]');

    this.init();
  }

  init() {
    if (!this.menuToggle || !this.menu) return;

    // Ouverture du menu
    this.menuToggle.addEventListener('click', () => this.open());

    // Fermeture du menu
    this.menuClose.addEventListener('click', () => this.close());

    // Fermeture au clic sur l'overlay
    this.menu.addEventListener('click', (e) => {
      if (e.target === this.menu) this.close();
    });

    // Gestion des dropdowns — uniquement sur le chevron
    this.toggles.forEach((toggle) => {
      toggle.addEventListener('click', (e) => {
        e.preventDefault();
        this.toggleSubmenu(toggle);
      });
    });

    // Fermeture des sous-menus quand on ferme le menu
    this.menuClose.addEventListener('click', () => this.closeAllSubmenus());

    // Fermeture au clic sur Escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') this.close();
    });
  }

  open() {
    this.menu.classList.add('is-open');
    this.body.classList.add('menu-open');
  }

  close() {
    this.menu.classList.remove('is-open');
    this.body.classList.remove('menu-open');
  }

  toggleSubmenu(toggle) {
    const wrap = toggle.parentElement.querySelector(':scope > .ms-menu__submenu-wrap');
    if (!wrap) return;

    const isOpen = wrap.classList.contains('is-open');

    // Ferme tous les autres
    this.toggles.forEach((t) => {
      const w = t.parentElement.querySelector(':scope > .ms-menu__submenu-wrap');
      if (w) {
        w.classList.remove('is-open');
        t.classList.remove('is-open');
        t.setAttribute('aria-expanded', 'false');
      }
    });

    if (!isOpen) {
      wrap.classList.add('is-open');
      toggle.classList.add('is-open');
      toggle.setAttribute('aria-expanded', 'true');
    }
  }

  closeAllSubmenus() {
    this.toggles.forEach((toggle) => {
      const wrap = toggle.parentElement.querySelector(':scope > .ms-menu__submenu-wrap');
      if (wrap) {
        wrap.classList.remove('is-open');
        toggle.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }
}

// Initialiser quand le DOM est prêt
document.addEventListener('DOMContentLoaded', () => {
  new MenuToggle();
});
