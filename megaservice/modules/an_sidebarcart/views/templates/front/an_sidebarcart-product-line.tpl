{*
 * Override product-line pour le panneau latéral — thème Megaservice.
 * Layout : image | infos (titre, prix, dispo) | actions (qty + supprimer)
 * IMPORTANT : input + .quantity-button doivent rester siblings — la JS du plugin
 * fait $(this).siblings('input[type="text"]') pour lier le bouton à son input.
 *}
<div class="product-image">
  <a href="{$product.url|escape:'htmlall':'UTF-8'}" tabindex="-1">
    <img src="{$product.cover.bySize.cart_default.url|escape:'htmlall':'UTF-8'}" alt="{$product.name|escape:'htmlall':'UTF-8'}" class="img-fluid">
  </a>
</div>

<div class="product-infos">
  <a class="product-name" href="{$product.url|escape:'htmlall':'UTF-8'}">{$product.name|escape:'htmlall':'UTF-8'}</a>

  <div class="product-prices">
    <span class="product-price">{$product.price|escape:'htmlall':'UTF-8'}</span>
    {if $product.has_discount}
      <span class="product-regular-price">{$product.regular_price|escape:'htmlall':'UTF-8'}</span>
    {/if}
  </div>

  {foreach from=$product.attributes key="attribute" item="value"}
    <div class="product-line-info">
      <span class="label">{$attribute|escape:'htmlall':'UTF-8'}:</span>
      <span class="value">{$value|escape:'htmlall':'UTF-8'}</span>
    </div>
  {/foreach}

  <div class="product-availability product-availability--{$product.availability|escape:'htmlall':'UTF-8'}">
    {if $product.availability == 'available'}
      {l s='Disponible' d='Shop.Theme.Catalog'}
    {else}
      {$product.availability_message|escape:'htmlall':'UTF-8'}
    {/if}
  </div>
</div>

<div class="product-actions">
  {if $widget.quantity}
    <div class="product-qty">
      <div class="product-qty-container">
        <input
          class="product-qty-input js-cart-line-product-quantity"
          data-down-url="{$product.down_quantity_url|escape:'htmlall':'UTF-8'}{if isset($product.an_group_id)}&an_group_id={$product.an_group_id|intval}{/if}"
          data-up-url="{$product.up_quantity_url|escape:'htmlall':'UTF-8'}{if isset($product.an_group_id)}&an_group_id={$product.an_group_id|intval}{/if}"
          data-update-url="{$product.update_quantity_url|escape:'htmlall':'UTF-8'}{if isset($product.an_group_id)}&an_group_id={$product.an_group_id|intval}{/if}"
          data-product-id="{$product.id_product|intval}"
          type="text"
          step="1"
          value="{$product.quantity|escape:'htmlall':'UTF-8'}"
          name="product-qty-spin"
          min="{$product.minimal_quantity|escape:'htmlall':'UTF-8'}"
        >
        <div class="quantity-button quantity-up" aria-label="{l s='Augmenter la quantité' d='Shop.Theme.Actions'}">
          <svg xmlns="http://www.w3.org/2000/svg" width="10" height="6" viewBox="0 0 10 6" fill="none" aria-hidden="true">
            <path d="M1 5L5 1L9 5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <div class="quantity-button quantity-down" aria-label="{l s='Diminuer la quantité' d='Shop.Theme.Actions'}">
          <svg xmlns="http://www.w3.org/2000/svg" width="10" height="6" viewBox="0 0 10 6" fill="none" aria-hidden="true">
            <path d="M1 1L5 5L9 1" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
      </div>
    </div>
  {/if}

  <a
    class="remove-from-cart"
    rel="nofollow"
    href="{$product.remove_from_cart_url|escape:'htmlall':'UTF-8'}{if isset($product.an_group_id)}&an_group_id={$product.an_group_id|intval}{/if}"
    data-link-action="delete-from-cart"
    data-id-product="{$product.id_product|escape:'htmlall':'UTF-8'}"
    data-id-product-attribute="{$product.id_product_attribute|escape:'htmlall':'UTF-8'}"
    data-id-customization="{$product.id_customization|escape:'htmlall':'UTF-8'}"
  >{l s='Supprimer' d='Shop.Theme.Actions'}</a>
</div>
