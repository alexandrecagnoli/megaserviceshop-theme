{block name='cart_detailed_actions'}
  <div class="ms-cart-actions checkout cart-detailed-actions js-cart-detailed-actions">
    {if $cart.minimalPurchaseRequired}
      <p class="ms-cart-actions__alert">{$cart.minimalPurchaseRequired}</p>
      <button type="button" class="ms-cart-actions__cta is-disabled" disabled>{l s='Passer ma commande' d='Shop.Theme.Actions'}</button>
    {elseif empty($cart.products)}
      <button type="button" class="ms-cart-actions__cta is-disabled" disabled>{l s='Passer ma commande' d='Shop.Theme.Actions'}</button>
    {else}
      <a href="{$urls.pages.order}" class="ms-cart-actions__cta">
        <span>{l s='Passer ma commande' d='Shop.Theme.Actions'}</span>
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="14" viewBox="0 0 24 16" fill="none" aria-hidden="true">
          <path d="M1 8H23M23 8L16 1M23 8L16 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </a>
      {hook h='displayExpressCheckout'}
    {/if}
  </div>
{/block}
