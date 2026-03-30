{block name='product_miniature_item'}
<article class="ms-product-card js-product{if !empty($productClasses)} {$productClasses}{/if}" data-id-product="{$product.id_product}" data-id-product-attribute="{$product.id_product_attribute}">

  {* ── Image + overlay ── *}
  <a href="{$product.url}" class="ms-product-card__media">
    {if $product.cover}
      <picture>
        {if !empty($product.cover.bySize.home_default.sources.avif)}<source srcset="{$product.cover.bySize.home_default.sources.avif}" type="image/avif">{/if}
        {if !empty($product.cover.bySize.home_default.sources.webp)}<source srcset="{$product.cover.bySize.home_default.sources.webp}" type="image/webp">{/if}
        <img src="{$product.cover.bySize.home_default.url}" alt="{$product.name|escape:'html':'UTF-8'}" loading="lazy" width="{$product.cover.bySize.home_default.width}" height="{$product.cover.bySize.home_default.height}">
      </picture>
    {else}
      <img src="{$urls.no_picture_image.bySize.home_default.url}" alt="{$product.name|escape:'html':'UTF-8'}" loading="lazy">
    {/if}

    <div class="ms-product-card__overlay">
      <span class="ms-product-card__overlay-btn">{l s='VOIR LA FICHE PRODUIT' d='Shop.Theme.Actions'}</span>
    </div>

    {if $product.has_discount && $product.discount_type === 'percentage'}
      <span class="ms-product-card__badge">{$product.discount_percentage}</span>
    {/if}
  </a>

  {* ── Infos ── *}
  <div class="ms-product-card__body">

    <h2 class="ms-product-card__name">
      <a href="{$product.url}">{$product.name|truncate:50:'...'}</a>
    </h2>

    {if $product.show_price}
      <div class="ms-product-card__prices">
        <span class="ms-product-card__price">{$product.price}</span>
        {if $product.has_discount}
          <span class="ms-product-card__price--old">{$product.regular_price}</span>
        {/if}
      </div>
    {/if}

    <div class="ms-product-card__availability ms-product-card__availability--{$product.availability}">
      {$product.availability_message}
    </div>

    <a href="{$product.url}" class="ms-product-card__add">{l s='AJOUTER' d='Shop.Theme.Actions'}</a>

  </div>

</article>
{/block}
