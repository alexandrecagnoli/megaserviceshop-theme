<div class="ms-product__add-to-cart js-product-add-to-cart">
  {if !$configuration.is_catalog}

    {block name='product_quantity'}
      <div class="ms-product__qty-row">
        <label class="ms-product__qty-label" for="quantity_wanted">{l s='Quantité' d='Shop.Theme.Catalog'}</label>
        <div class="ms-product__qty-control">
          <select
            name="qty"
            id="quantity_wanted"
            class="ms-product__qty-select"
            aria-label="{l s='Quantité' d='Shop.Theme.Actions'}"
          >
            {section name=qty loop=10 start=1}
              <option value="{$smarty.section.qty.index}" {if $smarty.section.qty.index == ($product.quantity_wanted|default:1)}selected{/if}>{$smarty.section.qty.index}</option>
            {/section}
          </select>
          <svg class="ms-product__qty-chevron" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <path d="M4 6L8 10L12 6" stroke="#000" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
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
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
            <path d="M15.75 3H3.75H3H1.5V4.5H3H3.225L5.6865 11.2687C6.00825 12.1545 6.85875 12.75 7.80075 12.75H14.25V11.25H7.80075C7.4865 11.25 7.203 11.0513 7.09575 10.7565L6.72975 9.75H13.6845C14.352 9.75 14.9445 9.3015 15.1268 8.66175L16.4715 3.95625C16.536 3.72975 16.4902 3.48675 16.3492 3.2985C16.2067 3.11025 15.9847 3 15.75 3ZM13.6845 8.25H6.1845L4.821 4.5H14.7562L13.6845 8.25Z" fill="white"/>
            <path d="M7.875 15.75C8.49632 15.75 9 15.2463 9 14.625C9 14.0037 8.49632 13.5 7.875 13.5C7.25368 13.5 6.75 14.0037 6.75 14.625C6.75 15.2463 7.25368 15.75 7.875 15.75Z" fill="white"/>
            <path d="M12.375 15.75C12.9963 15.75 13.5 15.2463 13.5 14.625C13.5 14.0037 12.9963 13.5 12.375 13.5C11.7537 13.5 11.25 14.0037 11.25 14.625C11.25 15.2463 11.7537 15.75 12.375 15.75Z" fill="white"/>
          </svg>
        </button>

        <button
          type="button"
          class="ms-product__btn-wishlist js-add-to-wishlist{if isset($ms_product_in_wishlist) && $ms_product_in_wishlist} is-active{/if}"
          data-action-url="{url entity='module' name='blockwishlist' controller='action'}"
          data-login-url="{url entity='authentication'}?back={$urls.current_url|escape:'url'}"
          data-customer-logged="{if isset($customer.is_logged) && $customer.is_logged}1{else}0{/if}"
          data-id-product="{$product.id}"
          data-id-product-attribute="{$product.id_product_attribute}"
          data-wishlist-id="{if isset($ms_wishlist_id)}{$ms_wishlist_id}{/if}"
          data-in-wishlist="{if isset($ms_product_in_wishlist) && $ms_product_in_wishlist}1{else}0{/if}"
          aria-label="{l s='Ajouter aux favoris' d='Shop.Theme.Actions'}"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M20.8401 4.60987C20.3294 4.09888 19.7229 3.69352 19.0555 3.41696C18.388 3.14039 17.6726 2.99805 16.9501 2.99805C16.2276 2.99805 15.5122 3.14039 14.8448 3.41696C14.1773 3.69352 13.5709 4.09888 13.0601 4.60987L12.0001 5.66987L10.9401 4.60987C9.90843 3.57818 8.50915 2.99858 7.05012 2.99858C5.59109 2.99858 4.19181 3.57818 3.16012 4.60987C2.12843 5.64156 1.54883 7.04084 1.54883 8.49987C1.54883 9.95891 2.12843 11.3582 3.16012 12.3899L4.22012 13.4499L12.0001 21.2299L19.7801 13.4499L20.8401 12.3899C21.3511 11.8791 21.7565 11.2727 22.033 10.6052C22.3096 9.93777 22.4519 9.22236 22.4519 8.49987C22.4519 7.77738 22.3096 7.06198 22.033 6.39452C21.7565 5.72706 21.3511 5.12063 20.8401 4.60987V4.60987Z" stroke="currentColor" fill="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
      </div>

      {if $product.add_to_cart_url}
        <button type="button" class="ms-product__btn-reserve">
          {l s='Réserver un essai' d='Shop.Theme.Catalog'}
        </button>
      {/if}
    </div>

    {block name='product_availability'}{/block}

    {block name='product_minimal_quantity'}
      {if $product.minimal_quantity > 1}
        <p class="ms-product__min-qty">
          {l s='Quantité minimum d\'achat : %quantity%' d='Shop.Theme.Checkout' sprintf=['%quantity%' => $product.minimal_quantity]}
        </p>
      {/if}
    {/block}

  {/if}
</div>
