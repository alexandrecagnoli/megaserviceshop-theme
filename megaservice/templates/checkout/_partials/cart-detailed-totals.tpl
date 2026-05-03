{block name='cart_detailed_totals'}
<div class="ms-cart-totals cart-detailed-totals js-cart-detailed-totals">

  <div class="ms-cart-totals__subs cart-detailed-subtotals js-cart-detailed-subtotals">
    {foreach from=$cart.subtotals item="subtotal"}
      {if $subtotal && $subtotal.value|count_characters > 0 && $subtotal.type !== 'tax'}
        <div class="ms-cart-totals__line cart-summary-line" id="cart-subtotal-{$subtotal.type}">
          <span class="ms-cart-totals__label{if 'products' === $subtotal.type} js-subtotal{/if}">
            {if 'products' == $subtotal.type}{$cart.summary_string}{else}{$subtotal.label}{/if}
          </span>
          <span class="ms-cart-totals__value">
            {if 'discount' == $subtotal.type}-&nbsp;{/if}{$subtotal.value}
          </span>
          {if $subtotal.type === 'shipping'}
            <div class="ms-cart-totals__hint">{hook h='displayCheckoutSubtotalDetails' subtotal=$subtotal}</div>
          {/if}
        </div>
      {/if}
    {/foreach}
  </div>

  {block name='cart_summary_totals'}
    {include file='checkout/_partials/cart-summary-totals.tpl' cart=$cart}
  {/block}

  {block name='cart_voucher'}
    {include file='checkout/_partials/cart-voucher.tpl'}
  {/block}

</div>
{/block}
