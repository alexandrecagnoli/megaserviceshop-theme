{extends file='checkout/cart.tpl'}

{block name='continue_shopping'}
  <a class="ms-cart__continue" href="{$urls.pages.index}">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
    </svg>
    <span>{l s='Continuer mes achats' d='Shop.Theme.Actions'}</span>
  </a>
{/block}

{block name='cart_actions'}
  <button type="button" class="ms-cart-actions__cta is-disabled" disabled>{l s='Passer ma commande' d='Shop.Theme.Actions'}</button>
{/block}

{block name='cart_voucher'}{/block}
{block name='display_reassurance'}{/block}
