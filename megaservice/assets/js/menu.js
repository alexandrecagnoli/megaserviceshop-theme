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
    
    // Dropdowns dans le menu
    this.menuLinks = document.querySelectorAll('.ms-menu__link[data-toggle]');

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

    // Gestion des dropdowns
    this.menuLinks.forEach((link) => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        this.toggleSubmenu(link);
      });
    });

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

  toggleSubmenu(link) {
    const submenuId = link.getAttribute('data-toggle');
    const submenu = document.querySelector(submenuId);

    if (!submenu) return;

    link.classList.toggle('is-open');
    submenu.classList.toggle('is-open');
  }
}

// Initialiser quand le DOM est prêt
document.addEventListener('DOMContentLoaded', () => {
  new MenuToggle();
});
