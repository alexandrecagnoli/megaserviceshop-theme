{if $product.show_price}
  <div class="ms-product__prices js-product-prices">

    {block name='product_discount'}
      {if $product.has_discount}
        <span class="ms-product__price-old">{$product.regular_price}</span>
      {/if}
    {/block}

    {block name='product_price'}
      <div class="ms-product__price-row">
        <span class="ms-product__price{if $product.has_discount} has-discount{/if}">
          {capture name='custom_price'}{hook h='displayProductPriceBlock' product=$product type='custom_price' hook_origin='product_sheet'}{/capture}
          {if '' !== $smarty.capture.custom_price}
            {$smarty.capture.custom_price nofilter}
          {else}
            {$product.price}
          {/if}
        </span>
        {if $product.has_discount}
          {if $product.discount_type === 'percentage'}
            <span class="ms-product__price-badge">{$product.discount_percentage_absolute}</span>
          {else}
            <span class="ms-product__price-badge">-{$product.discount_to_display}</span>
          {/if}
        {/if}
      </div>
    {/block}

    <div class="ms-product__delivery-badge">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <rect x="1" y="3" width="15" height="13" rx="1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M16 8h4l3 3v5h-7V8z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        <circle cx="5.5" cy="18.5" r="2.5" stroke="currentColor" stroke-width="1.8"/>
        <circle cx="18.5" cy="18.5" r="2.5" stroke="currentColor" stroke-width="1.8"/>
      </svg>
      {l s='Livraison offerte dès 150€' d='Shop.Theme.Catalog'}
    </div>

  </div>
{/if}
