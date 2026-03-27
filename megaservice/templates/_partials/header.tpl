{block name='header'}
<header id="header" class="ms-header">

  {* ── Barre promo défilante ── *}
  <div class="ms-header__banner" role="marquee" aria-label="{l s='Promotional banner' d='Shop.Theme.Global'}">
    <div class="ms-header__banner-track">
      <span>Livraison offerte pour la 1ère commande dès 35€ avec le code KTMONFIRE en France Métropolitaine et en Belgique</span>
      <span aria-hidden="true">Livraison offerte pour la 1ère commande dès 35€ avec le code KTMONFIRE en France Métropolitaine et en Belgique</span>
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
        <svg class="ms-header__service-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
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
        <img src="{$urls.theme_assets}img/ico-bike.svg" alt="" aria-hidden="true" width="32" height="32">
        <span>{l s='Sélectionnez' d='Shop.Theme.Global'}<strong>{l s='votre modèle' d='Shop.Theme.Global'}</strong></span>
      </a>

      <div class="ms-header__actions">
        {hook h='displayTop' mod='ps_searchbar'}
        {hook h='displayTop' mod='ps_shoppingcart'}
        {hook h='displayTop' mod='ps_customersignin'}
      </div>

    </div>

  </div>

</header>
{/block}
