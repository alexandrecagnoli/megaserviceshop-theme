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
      <select
        class="product-qty-select js-cart-line-product-quantity"
        data-update-url="{$product.update_quantity_url|escape:'htmlall':'UTF-8'}{if isset($product.an_group_id)}&an_group_id={$product.an_group_id|intval}{/if}"
        data-product-id="{$product.id_product|intval}"
        data-current-qty="{$product.quantity|intval}"
        aria-label="{l s='Quantité' d='Shop.Theme.Actions'}"
      >
        {* Si la qté actuelle dépasse 10, on l'expose en haut pour ne pas la perdre *}
        {if $product.quantity > 10}
          <option value="{$product.quantity|intval}" selected>{$product.quantity|intval}</option>
        {/if}
        {section name=qty loop=10 start=1}
          <option value="{$smarty.section.qty.index}"{if $smarty.section.qty.index == $product.quantity} selected{/if}>{$smarty.section.qty.index}</option>
        {/section}
      </select>
      <svg class="product-qty-chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
        <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
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
