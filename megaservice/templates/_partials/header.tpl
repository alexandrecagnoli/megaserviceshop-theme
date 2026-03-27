{block name='header'}
  <header id="header" class="ms-header">

    {* Barre du haut : infos, compte, panier, etc. *}
    <div class="ms-header__topbar">
      <div class="ms-container ms-header__topbar-inner">

        <div class="ms-header__topbar-left">
          {hook h='displayNav1'}
        </div>

        <div class="ms-header__topbar-right">
          {hook h='displayNav2'}
        </div>

      </div>
    </div>

    {* Bande principale : logo + menu + recherche / compte / panier *}
    <div class="ms-header__main">
      <div class="ms-container ms-header__main-inner">

        <div class="ms-header__logo">
          <a href="{$urls.pages.index}">
            {if isset($shop.logo)}
              <img src="{$shop.logo}" alt="{$shop.name}" loading="lazy">
            {/if}
          </a>
        </div>

        <nav class="ms-header__nav" aria-label="{l s='Main navigation' d='Shop.Theme.Global'}">

          <div class="ms-header__nav-left">
            {hook h='displayTop' mod='ps_mainmenu'}
          </div>

          <div class="ms-header__nav-right">
            {hook h='displayTop' mod='ps_searchbar'}
            {hook h='displayTop' mod='ps_customersignin'}
            {hook h='displayTop' mod='ps_shoppingcart'}
          </div>

        </nav>

      </div>
    </div>

  </header>
{/block}
