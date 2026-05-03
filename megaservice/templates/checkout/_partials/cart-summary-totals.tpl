<div class="ms-cart-totals__final cart-summary-totals js-cart-summary-totals">

  {block name='cart_summary_total'}
    {if !$configuration.display_prices_tax_incl && $configuration.taxes_enabled}
      <div class="ms-cart-totals__line cart-summary-line">
        <span class="ms-cart-totals__label">{$cart.totals.total.label}&nbsp;{$cart.labels.tax_short}</span>
        <span class="ms-cart-totals__value">{$cart.totals.total.value}</span>
      </div>
      <div class="ms-cart-totals__line ms-cart-totals__line--total cart-summary-line cart-total">
        <span class="ms-cart-totals__label">{$cart.totals.total_including_tax.label}</span>
        <span class="ms-cart-totals__value">{$cart.totals.total_including_tax.value}</span>
      </div>
    {else}
      <div class="ms-cart-totals__line ms-cart-totals__line--total cart-summary-line cart-total">
        <span class="ms-cart-totals__label">{$cart.totals.total.label}&nbsp;{if $configuration.display_taxes_label && $configuration.taxes_enabled}{$cart.labels.tax_short}{/if}</span>
        <span class="ms-cart-totals__value">{$cart.totals.total.value}</span>
      </div>
    {/if}
  {/block}

  {block name='cart_summary_tax'}
    {if $cart.subtotals.tax}
      <div class="ms-cart-totals__line ms-cart-totals__line--tax cart-summary-line">
        <span class="ms-cart-totals__label sub">{l s='%label%:' sprintf=['%label%' => $cart.subtotals.tax.label] d='Shop.Theme.Global'}</span>
        <span class="ms-cart-totals__value sub">{$cart.subtotals.tax.value}</span>
      </div>
    {/if}
  {/block}

</div>
