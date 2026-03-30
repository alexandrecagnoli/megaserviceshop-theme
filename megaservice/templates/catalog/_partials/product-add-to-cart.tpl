<div class="ms-product__add-to-cart js-product-add-to-cart">
  {if !$configuration.is_catalog}

    {block name='product_quantity'}
      <div class="ms-product__qty-row">
        <label class="ms-product__qty-label" for="quantity_wanted">{l s='Quantité' d='Shop.Theme.Catalog'}</label>
        <div class="ms-product__qty-control">
          <button type="button" class="ms-product__qty-btn js-qty-minus" aria-label="{l s='Diminuer la quantité' d='Shop.Theme.Actions'}">−</button>
          <input
            type="number"
            name="qty"
            id="quantity_wanted"
            inputmode="numeric"
            pattern="[0-9]*"
            {if $product.quantity_wanted}
              value="{$product.quantity_wanted}"
              min="{$product.minimal_quantity}"
            {else}
              value="1"
              min="1"
            {/if}
            class="ms-product__qty-input"
            aria-label="{l s='Quantité' d='Shop.Theme.Actions'}"
          >
          <button type="button" class="ms-product__qty-btn js-qty-plus" aria-label="{l s='Augmenter la quantité' d='Shop.Theme.Actions'}">+</button>
        </div>
      </div>
    {/block}

    <div class="ms-product__cta-group">
      <div class="ms-product__cta-row">
        <button
          class="ms-product__btn-cart"
          data-button-action="add-to-cart"
          type="submit"
          {if !$product.add_to_cart_url}disabled{/if}
        >
          {l s='Ajouter au panier' d='Shop.Theme.Actions'}
        </button>
        <button type="button" class="ms-product__btn-wishlist js-wishlist-btn" aria-label="{l s='Ajouter aux favoris' d='Shop.Theme.Actions'}">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
      </div>
      <button type="button" class="ms-product__btn-reserve">
        {l s='Réserver un essai' d='Shop.Theme.Catalog'}
      </button>
    </div>

    {block name='product_availability'}
      <span id="product-availability" class="ms-product__availability-msg js-product-availability">
        {if $product.show_availability && $product.availability_message}
          {$product.availability_message}
        {/if}
      </span>
    {/block}

    {block name='product_minimal_quantity'}
      {if $product.minimal_quantity > 1}
        <p class="ms-product__min-qty">
          {l s='Quantité minimum d\'achat : %quantity%' d='Shop.Theme.Checkout' sprintf=['%quantity%' => $product.minimal_quantity]}
        </p>
      {/if}
    {/block}

  {/if}
</div>
