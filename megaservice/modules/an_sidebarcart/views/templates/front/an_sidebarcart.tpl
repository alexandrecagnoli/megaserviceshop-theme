{*
 * Override du template an_sidebarcart pour le thème Megaservice.
 * On conserve toutes les classes/IDs que le JS du plugin attend :
 *   - #_desktop_cart, .blockcart[data-refresh-url], .js-sidebar-cart-trigger
 *   - .blockcart .header (remplacé sur updateCart)
 *   - .cart-products-count (compteur)
 *   - .cart-dropdown.js-cart-source (source du contenu sidebar, copié au ready)
 *   - .cart-dropdown-wrapper (remplacé sur updateCart)
 *}
<div id="_desktop_cart" class="ms-header__cart">
  <div
    class="blockcart cart-preview js-sidebar-cart-trigger {if $cart.products_count > 0}active{else}inactive{/if}"
    data-refresh-url="{$refresh_url|escape:'htmlall':'UTF-8'}"
  >
    <div class="header">
      <a class="blockcart-link ms-header__action-link" rel="nofollow" href="{$cart_url|escape:'htmlall':'UTF-8'}" aria-label="{l s='Panier' d='Shop.Theme.Actions'}">
        <span class="ms-header__cart-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 30 30" fill="none" aria-hidden="true">
            <path d="M27.2775 9.28875C27.0438 8.95125 26.66 8.75 26.25 8.75H9.16625L7.72375 5.2875C7.33375 4.3525 6.42875 3.75 5.41625 3.75H2.5V6.25H5.41625L11.3462 20.4813C11.54 20.9463 11.995 21.25 12.5 21.25H22.5C23.0212 21.25 23.4875 20.9262 23.6712 20.44L27.4212 10.44C27.565 10.055 27.5113 9.625 27.2775 9.28875ZM21.6337 18.75H13.3337L10.2087 11.25H24.4462L21.6337 18.75Z" fill="white"/>
            <path d="M13.125 26.25C14.1605 26.25 15 25.4105 15 24.375C15 23.3395 14.1605 22.5 13.125 22.5C12.0895 22.5 11.25 23.3395 11.25 24.375C11.25 25.4105 12.0895 26.25 13.125 26.25Z" fill="white"/>
            <path d="M21.875 26.25C22.9105 26.25 23.75 25.4105 23.75 24.375C23.75 23.3395 22.9105 22.5 21.875 22.5C20.8395 22.5 20 23.3395 20 24.375C20 25.4105 20.8395 26.25 21.875 26.25Z" fill="white"/>
          </svg>
          <span class="ms-header__cart-count cart-products-count"{if $cart.products_count < 1} hidden{/if}>{$cart.products_count|intval}</span>
        </span>
        <span>{l s='Panier' d='Shop.Theme.Actions'}</span>
      </a>
    </div>

    <div class="cart-dropdown js-cart-source" style="display:none;">
      <div class="cart-dropdown-wrapper">
        <div class="cart-title">
          <h4 class="cart-title__text">
            {l s='Mon panier' d='Shop.Theme.Checkout'}
            <span class="cart-title__count">({$cart.products_count|intval})</span>
          </h4>
        </div>

        {if $cart.products}
          <ul class="cart-items">
            {foreach from=$cart.products item=product}
              <li class="cart-product-line">{include 'module:an_sidebarcart/views/templates/front/an_sidebarcart-product-line.tpl' product=$product}</li>
            {/foreach}
          </ul>

          <div class="cart-bottom">
            <a href="{$cart_url|escape:'htmlall':'UTF-8'}" class="cart-cta">
              <span>{l s='Passer ma commande' d='Shop.Theme.Actions'}</span>
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="16" viewBox="0 0 24 16" fill="none" aria-hidden="true">
                <path d="M1 8H23M23 8L16 1M23 8L16 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </a>
          </div>
        {else}
          <div class="no-items">{l s='Votre panier est vide' d='Shop.Theme.Checkout'}</div>
        {/if}
      </div>
    </div>
  </div>
</div>
