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

        <div class="ms-product__delivery-badge">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M18.5 21C19.8807 21 21 19.8807 21 18.5C21 17.1193 19.8807 16 18.5 16C17.1193 16 16 17.1193 16 18.5C16 19.8807 17.1193 21 18.5 21Z" stroke="#FE6604" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M5.5 21C6.88071 21 8 19.8807 8 18.5C8 17.1193 6.88071 16 5.5 16C4.11929 16 3 17.1193 3 18.5C3 19.8807 4.11929 21 5.5 21Z" stroke="#FE6604" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M16 8H20L23 11V16H16V8Z" stroke="#FE6604" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M16 3H1V16H16V3Z" stroke="#FE6604" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <span><strong>{l s='Livraison offerte' d='Shop.Theme.Catalog'}</strong> {l s='dès 150€' d='Shop.Theme.Catalog'}</span>
        </div>
      </div>
    {/block}

  </div>
{/if}
