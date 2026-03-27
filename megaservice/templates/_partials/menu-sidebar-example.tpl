<!-- Menu Hamburger (visible sur mobile) -->
<button class="ms-menu-toggle" aria-label="Ouvrir le menu">
  <span>☰</span>
</button>

<!-- Sidebar Menu -->
<nav class="ms-menu" aria-label="Menu principal">
  <!-- Menu Header -->
  <div class="ms-menu__header">
    <div class="ms-menu__logo">
      <a href="/" aria-label="Accueil">
        <img src="/logo-mega.svg" alt="MEGA" height="32">
      </a>
      <!-- Logos partenaires si souhaité -->
    </div>
    <button class="ms-menu__close" aria-label="Fermer le menu">
      <span>✕</span>
    </button>
  </div>

  <!-- Menu Items -->
  <ul class="ms-menu__list">
    <!-- Lien simple -->
    <li class="ms-menu__item">
      <a href="/best-sellers" class="ms-menu__link">
        BEST SELLERS
      </a>
    </li>

    <!-- Lien simple -->
    <li class="ms-menu__item">
      <a href="/motos-neuves" class="ms-menu__link">
        MOTOS NEUVES
      </a>
    </li>

    <!-- Lien simple -->
    <li class="ms-menu__item">
      <a href="/motos-occasion" class="ms-menu__link">
        MOTOS D'OCCASION
      </a>
    </li>

    <!-- Lien avec dropdown -->
    <li class="ms-menu__item">
      <button 
        type="button"
        class="ms-menu__link" 
        data-toggle="#submenu-pieces"
        aria-expanded="false"
        aria-controls="submenu-pieces"
      >
        PIÈCES DÉTACHÉES D'ORIGINE
        <span class="ms-menu__chevron">▼</span>
      </button>
      <ul id="submenu-pieces" class="ms-menu__submenu">
        <li class="ms-menu__subitem">
          <a href="/pieces/moteur">Moteur</a>
        </li>
        <li class="ms-menu__subitem">
          <a href="/pieces/chassis">Châssis</a>
        </li>
        <li class="ms-menu__subitem">
          <a href="/pieces/electrique">Électrique</a>
        </li>
      </ul>
    </li>

    <!-- Lien avec dropdown -->
    <li class="ms-menu__item">
      <button 
        type="button"
        class="ms-menu__link" 
        data-toggle="#submenu-equipements"
        aria-expanded="false"
        aria-controls="submenu-equipements"
      >
        ÉQUIPEMENTS PILOTE
        <span class="ms-menu__chevron">▼</span>
      </button>
      <ul id="submenu-equipements" class="ms-menu__submenu">
        <li class="ms-menu__subitem">
          <a href="/equipements/casques">Casques</a>
        </li>
        <li class="ms-menu__subitem">
          <a href="/equipements/combinaisons">Combinaisons</a>
        </li>
        <li class="ms-menu__subitem">
          <a href="/equipements/gants">Gants</a>
        </li>
      </ul>
    </li>

    <!-- Lien avec dropdown -->
    <li class="ms-menu__item">
      <button 
        type="button"
        class="ms-menu__link" 
        data-toggle="#submenu-sportswear"
        aria-expanded="false"
        aria-controls="submenu-sportswear"
      >
        SPORTSWEAR
        <span class="ms-menu__chevron">▼</span>
      </button>
      <ul id="submenu-sportswear" class="ms-menu__submenu">
        <li class="ms-menu__subitem">
          <a href="/sportswear/tshirts">T-Shirts</a>
        </li>
        <li class="ms-menu__subitem">
          <a href="/sportswear/sweats">Sweats</a>
        </li>
      </ul>
    </li>

    <!-- Lien avec dropdown -->
    <li class="ms-menu__item">
      <button 
        type="button"
        class="ms-menu__link" 
        data-toggle="#submenu-powerparts"
        aria-expanded="false"
        aria-controls="submenu-powerparts"
      >
        ACCESSOIRES POWERPARTS
        <span class="ms-menu__chevron">▼</span>
      </button>
      <ul id="submenu-powerparts" class="ms-menu__submenu">
        <li class="ms-menu__subitem">
          <a href="/powerparts/echappements">Échappements</a>
        </li>
        <li class="ms-menu__subitem">
          <a href="/powerparts/filtres">Filtres</a>
        </li>
      </ul>
    </li>

    <!-- Lien avec dropdown -->
    <li class="ms-menu__item">
      <button 
        type="button"
        class="ms-menu__link" 
        data-toggle="#submenu-apropos"
        aria-expanded="false"
        aria-controls="submenu-apropos"
      >
        À PROPOS
        <span class="ms-menu__chevron">▼</span>
      </button>
      <ul id="submenu-apropos" class="ms-menu__submenu">
        <li class="ms-menu__subitem">
          <a href="/apropos">Qui sommes-nous</a>
        </li>
        <li class="ms-menu__subitem">
          <a href="/contact">Nous contacter</a>
        </li>
        <li class="ms-menu__subitem">
          <a href="/mentions-legales">Mentions légales</a>
        </li>
      </ul>
    </li>
  </ul>

  <!-- Menu Footer (visible sur mobile) -->
  <div class="ms-menu__footer">
    <!-- Service Client -->
    <div class="ms-menu__customer-service">
      <div class="icon">📞</div>
      <div class="info">
        <div class="label">Service client</div>
        <div class="phone">01 34 86 49 26</div>
      </div>
    </div>

    <!-- Bouton Mon Compte -->
    <a href="/login" class="ms-menu__account-btn">MON COMPTE</a>
  </div>
</nav>

<!-- Scripts -->
<script src="/js/menu.js"></script>
