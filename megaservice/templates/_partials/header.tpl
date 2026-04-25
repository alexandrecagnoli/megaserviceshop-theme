{block name='header'}
<header id="header" class="ms-header">

  {* ── Barre promo défilante ── *}
  <div class="ms-header__banner" role="marquee" aria-label="{l s='Promotional banner' d='Shop.Theme.Global'}">
    <div class="ms-header__banner-track">
      <span>{l s='Livraison offerte pour la 1ère commande dès 35€ avec le code KTMONFIRE en France Métropolitaine et en Belgique' d='Shop.Theme.Global'}</span>
      <span aria-hidden="true">{l s='Livraison offerte pour la 1ère commande dès 35€ avec le code KTMONFIRE en France Métropolitaine et en Belgique' d='Shop.Theme.Global'}</span>
    </div>
  </div>

  {* ── Header principal ── *}
  <div class="ms-header__main">

    {* Gauche *}
    <div class="ms-header__left">

      <button class="ms-header__burger" aria-label="{l s='Open menu' d='Shop.Theme.Global'}" aria-expanded="false" aria-controls="ms-sidebar">
        <span></span>
        <span></span>
        <span></span>
      </button>

      <div class="ms-header__brands">
        <img src="{$urls.theme_assets}img/brands/KTM_HUSQVARNA_GASGAS_LOGO.png" alt="KTM · Husqvarna · GasGas Authorized Dealer" loading="lazy">
      </div>

      <a href="tel:0134864926" class="ms-header__service">
        <svg class="ms-header__service-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path fill-rule="evenodd" clip-rule="evenodd" d="M15.9514 13.4159C15.3994 13.4159 14.9464 12.9689 14.9464 12.4159C14.9464 11.8629 15.3894 11.4159 15.9424 11.4159H15.9514C16.5034 11.4159 16.9514 11.8629 16.9514 12.4159C16.9514 12.9689 16.5034 13.4159 15.9514 13.4159ZM11.9424 13.4159C11.3894 13.4159 10.9374 12.9689 10.9374 12.4159C10.9374 11.8629 11.3814 11.4159 11.9334 11.4159H11.9424C12.4944 11.4159 12.9424 11.8629 12.9424 12.4159C12.9424 12.9689 12.4944 13.4159 11.9424 13.4159ZM7.93337 13.4159C7.38137 13.4159 6.92937 12.9689 6.92937 12.4159C6.92937 11.8629 7.37137 11.4159 7.92437 11.4159H7.93337C8.48537 11.4159 8.93337 11.8629 8.93337 12.4159C8.93337 12.9689 8.48537 13.4159 7.93337 13.4159ZM19.4274 4.57593C17.4464 2.59493 14.8094 1.50293 12.0034 1.50293C9.19737 1.50293 6.56037 2.59493 4.57937 4.57593C1.47737 7.67893 0.631368 12.4409 2.45637 16.3749C2.48937 16.5369 2.40037 17.1959 2.33637 17.6779C2.09437 19.4839 1.97037 20.8099 2.58337 21.4239C3.19537 22.0369 4.52037 21.9129 6.32637 21.6699C6.80837 21.6059 7.47537 21.5199 7.57937 21.5309C8.98537 22.1819 10.4884 22.4969 11.9804 22.4969C14.7184 22.4969 17.4204 21.4349 19.4274 19.4269C23.5204 15.3319 23.5214 8.66993 19.4274 4.57593Z" fill="white"/>
        </svg>
        <span>
          <small>{l s='Service client' d='Shop.Theme.Global'}</small>
          01 34 86 49 26
        </span>
      </a>

    </div>

    {* Centre — Logo *}
    <div class="ms-header__logo">
      <a href="{$urls.pages.index}" aria-label="{$shop.name}">
        <img src="{$urls.theme_assets}img/LOGO_MEGASERVICE_DARK.png" alt="{$shop.name}" loading="eager">
      </a>
    </div>

    {* Droite *}
    <div class="ms-header__right">

      <a href="#" class="ms-header__model-btn">
        {* État vide *}
        <span class="ms-header__model-empty">
          <img src="{$urls.theme_assets}img/ico-bike.svg" alt="" aria-hidden="true" width="32" height="32">
          <span>{l s='Sélectionnez' d='Shop.Theme.Global'}<strong>{l s='votre modèle' d='Shop.Theme.Global'}</strong></span>
        </span>
        {* État rempli — visible quand .has-model *}
        <span class="ms-header__model-filled">
          <span class="ms-header__model-icon-wrap">
            <img src="{$urls.theme_assets}img/ico-bike.svg" alt="" aria-hidden="true" width="32" height="32">
            <span class="ms-header__model-check" aria-hidden="true">
              <svg xmlns="http://www.w3.org/2000/svg" width="10" height="8" viewBox="0 0 10 8" fill="none">
                <path d="M1 4.5L3.5 7L9 1.5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </span>
          </span>
          <span class="ms-header__model-info">
            <span class="ms-header__model-info-label">{l s='Catalogue filtré sur' d='Shop.Theme.Global'}</span>
            <strong class="js-model-current-name"></strong>
          </span>
          <span class="ms-header__model-edit" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
        </span>
      </a>

      <div class="ms-header__actions">
        {hook h='displayTop' mod='ps_searchbar'}
        {hook h='displayNav2' mod='an_sidebarcart'}
        <a href="{$urls.pages.my_account}" class="ms-header__action-link" aria-label="{l s='Compte' d='Shop.Theme.Actions'}">
          <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 30 30" fill="none" aria-hidden="true">
            <path d="M15 2.5C11.5538 2.5 8.75 5.30375 8.75 8.75C8.75 12.1962 11.5538 15 15 15C18.4462 15 21.25 12.1962 21.25 8.75C21.25 5.30375 18.4462 2.5 15 2.5ZM15 12.5C12.9325 12.5 11.25 10.8175 11.25 8.75C11.25 6.6825 12.9325 5 15 5C17.0675 5 18.75 6.6825 18.75 8.75C18.75 10.8175 17.0675 12.5 15 12.5ZM26.25 26.25V25C26.25 20.1763 22.3237 16.25 17.5 16.25H12.5C7.675 16.25 3.75 20.1763 3.75 25V26.25H6.25V25C6.25 21.5537 9.05375 18.75 12.5 18.75H17.5C20.9463 18.75 23.75 21.5537 23.75 25V26.25H26.25Z" fill="white"/>
          </svg>
          <span>{l s='Compte' d='Shop.Theme.Actions'}</span>
        </a>
      </div>

    </div>

  </div>

  {* ── Sidebar Menu ── *}
  <nav id="ms-sidebar" class="ms-menu" aria-label="{l s='Menu principal' d='Shop.Theme.Global'}">

    <div class="ms-menu__header">
      <div class="ms-menu__logo">
        <a href="{$urls.pages.index}" aria-label="{$shop.name}">
          <img src="{$urls.theme_assets}img/LOGO_MEGASERVICE_LIGHT.png" alt="{$shop.name}" class="ms-menu__logo-mega" loading="eager">
        </a>
        <img src="{$urls.theme_assets}img/brands/KTM_HUSQVARNA_GASGAS_LOGO.png" alt="KTM · Husqvarna · GasGas" class="ms-menu__logo-brands" loading="lazy">
      </div>
      <button class="ms-menu__close" aria-label="{l s='Fermer le menu' d='Shop.Theme.Global'}">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>

    <div class="ms-menu__nav">
      {hook h='displayTop' mod='ps_mainmenu'}
    </div>

    <div class="ms-menu__footer">
      <a href="tel:0134864926" class="ms-menu__customer-service">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path fill-rule="evenodd" clip-rule="evenodd" d="M15.9514 13.4159C15.3994 13.4159 14.9464 12.9689 14.9464 12.4159C14.9464 11.8629 15.3894 11.4159 15.9424 11.4159H15.9514C16.5034 11.4159 16.9514 11.8629 16.9514 12.4159C16.9514 12.9689 16.5034 13.4159 15.9514 13.4159ZM11.9424 13.4159C11.3894 13.4159 10.9374 12.9689 10.9374 12.4159C10.9374 11.8629 11.3814 11.4159 11.9334 11.4159H11.9424C12.4944 11.4159 12.9424 11.8629 12.9424 12.4159C12.9424 12.9689 12.4944 13.4159 11.9424 13.4159ZM7.93337 13.4159C7.38137 13.4159 6.92937 12.9689 6.92937 12.4159C6.92937 11.8629 7.37137 11.4159 7.92437 11.4159H7.93337C8.48537 11.4159 8.93337 11.8629 8.93337 12.4159C8.93337 12.9689 8.48537 13.4159 7.93337 13.4159ZM19.4274 4.57593C17.4464 2.59493 14.8094 1.50293 12.0034 1.50293C9.19737 1.50293 6.56037 2.59493 4.57937 4.57593C1.47737 7.67893 0.631368 12.4409 2.45637 16.3749C2.48937 16.5369 2.40037 17.1959 2.33637 17.6779C2.09437 19.4839 1.97037 20.8099 2.58337 21.4239C3.19537 22.0369 4.52037 21.9129 6.32637 21.6699C6.80837 21.6059 7.47537 21.5199 7.57937 21.5309C8.98537 22.1819 10.4884 22.4969 11.9804 22.4969C14.7184 22.4969 17.4204 21.4349 19.4274 19.4269C23.5204 15.3319 23.5214 8.66993 19.4274 4.57593Z" fill="currentColor"/>
        </svg>
        <div class="ms-menu__customer-service-info">
          <small>{l s='Service client' d='Shop.Theme.Global'}</small>
          <strong>01 34 86 49 26</strong>
        </div>
      </a>
      <a href="{$urls.pages.my_account}" class="ms-menu__account-btn">{l s='MON COMPTE' d='Shop.Theme.Global'}</a>
    </div>

  </nav>

</header>

{include file='_partials/model-selector.tpl'}

{/block}
