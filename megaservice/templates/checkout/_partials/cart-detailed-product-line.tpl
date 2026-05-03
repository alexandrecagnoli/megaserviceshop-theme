<div class="ms-cart-line">

  {* ── Image ── *}
  <a class="ms-cart-line__media" href="{$product.url}">
    {if $product.default_image}
      <picture>
        {if !empty($product.default_image.bySize.cart_default.sources.avif)}<source srcset="{$product.default_image.bySize.cart_default.sources.avif}" type="image/avif">{/if}
        {if !empty($product.default_image.bySize.cart_default.sources.webp)}<source srcset="{$product.default_image.bySize.cart_default.sources.webp}" type="image/webp">{/if}
        <img src="{$product.default_image.bySize.cart_default.url}" alt="{$product.name|escape:'html':'UTF-8'}" loading="lazy">
      </picture>
    {else}
      <img src="{$urls.no_picture_image.bySize.cart_default.url}" alt="" loading="lazy">
    {/if}
  </a>

  {* ── Infos ── *}
  <div class="ms-cart-line__body">

    <a class="ms-cart-line__name" href="{$product.url}" data-id_customization="{$product.id_customization|intval}">{$product.name}</a>

    {if $product.attributes}
      <ul class="ms-cart-line__attrs">
        {foreach from=$product.attributes key="attribute" item="value"}
          <li><span class="ms-cart-line__attr-label">{$attribute}:</span> <span>{$value}</span></li>
        {/foreach}
      </ul>
    {/if}

    <div class="ms-cart-line__unit-price {if $product.has_discount}has-discount{/if}">
      {if $product.has_discount}
        <span class="ms-cart-line__regular regular-price">{$product.regular_price}</span>
        {if $product.discount_type === 'percentage'}
          <span class="ms-cart-line__discount discount discount-percentage">-{$product.discount_percentage_absolute}</span>
        {else}
          <span class="ms-cart-line__discount discount discount-amount">-{$product.discount_to_display}</span>
        {/if}
      {/if}
      <span class="ms-cart-line__price price">{$product.price}</span>
      {if $product.unit_price_full}
        <span class="ms-cart-line__unit unit-price-cart">{$product.unit_price_full}</span>
      {/if}
      {hook h='displayProductPriceBlock' product=$product type="unit_price"}
    </div>

    {if is_array($product.customizations) && $product.customizations|count}
      {block name='cart_detailed_product_line_customization'}
        <div class="ms-cart-line__custom">
          {foreach from=$product.customizations item="customization"}
            <a href="#" data-toggle="modal" data-target="#product-customizations-modal-{$customization.id_customization}">{l s='Personnalisation produit' d='Shop.Theme.Catalog'}</a>
            <div class="modal fade customization-modal js-customization-modal" id="product-customizations-modal-{$customization.id_customization}" tabindex="-1" role="dialog" aria-hidden="true">
              <div class="modal-dialog" role="document">
                <div class="modal-content">
                  <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="{l s='Fermer' d='Shop.Theme.Global'}">
                      <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title">{l s='Personnalisation produit' d='Shop.Theme.Catalog'}</h4>
                  </div>
                  <div class="modal-body">
                    {foreach from=$customization.fields item="field"}
                      <div class="product-customization-line row">
                        <div class="col-sm-3 col-xs-4 label">{$field.label}</div>
                        <div class="col-sm-9 col-xs-8 value">
                          {if $field.type == 'text'}
                            {if (int)$field.id_module}{$field.text nofilter}{else}{$field.text}{/if}
                          {elseif $field.type == 'image'}
                            <img src="{$field.image.small.url}" loading="lazy">
                          {/if}
                        </div>
                      </div>
                    {/foreach}
                  </div>
                </div>
              </div>
            </div>
          {/foreach}
        </div>
      {/block}
    {/if}

  </div>

  {* ── Quantité ── *}
  <div class="ms-cart-line__qty">
    {if !empty($product.is_gift)}
      <span class="ms-cart-line__gift-qty">{$product.quantity}</span>
    {else}
      <input
        class="ms-cart-line__qty-input js-cart-line-product-quantity"
        data-down-url="{$product.down_quantity_url}"
        data-up-url="{$product.up_quantity_url}"
        data-update-url="{$product.update_quantity_url}"
        data-product-id="{$product.id_product}"
        type="number"
        inputmode="numeric"
        pattern="[0-9]*"
        min="1"
        value="{$product.quantity}"
        name="product-quantity-spin"
        aria-label="{l s='Quantité de %productName%' sprintf=['%productName%' => $product.name] d='Shop.Theme.Checkout'}"
      />
    {/if}
  </div>

  {* ── Total ligne ── *}
  <div class="ms-cart-line__total">
    {if !empty($product.is_gift)}
      <span class="ms-cart-line__gift">{l s='Cadeau' d='Shop.Theme.Checkout'}</span>
    {else}
      {$product.total}
    {/if}
  </div>

  {* ── Supprimer ── *}
  <div class="ms-cart-line__actions">
    <a
      class="ms-cart-line__remove remove-from-cart"
      rel="nofollow"
      href="{$product.remove_from_cart_url}"
      data-link-action="delete-from-cart"
      data-id-product="{$product.id_product|escape:'javascript'}"
      data-id-product-attribute="{$product.id_product_attribute|escape:'javascript'}"
      data-id-customization="{$product.id_customization|default|escape:'javascript'}"
      aria-label="{l s='Supprimer du panier' d='Shop.Theme.Actions'}"
    >
      {if empty($product.is_gift)}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <polyline points="3 6 5 6 21 6"/>
          <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
          <path d="M10 11v6M14 11v6"/>
          <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
        </svg>
      {/if}
    </a>

    {block name='hook_cart_extra_product_actions'}
      {hook h='displayCartExtraProductActions' product=$product}
    {/block}
  </div>

</div>
