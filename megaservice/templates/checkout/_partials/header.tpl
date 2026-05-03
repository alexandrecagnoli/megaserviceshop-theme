{block name='header_nav'}
  <header class="ms-checkout-header">
    <div class="ms-checkout-header__inner">

      <a href="{$urls.pages.index}" class="ms-checkout-header__logo" aria-label="{$shop.name}">
        <img src="{$urls.theme_assets}img/LOGO_MEGASERVICE_DARK.png" alt="{$shop.name}" loading="eager">
      </a>

      <span class="ms-checkout-header__secure">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
        <span>{l s='Paiement sécurisé' d='Shop.Theme.Checkout'}</span>
      </span>

    </div>
  </header>
{/block}

{block name='header_top'}{/block}
