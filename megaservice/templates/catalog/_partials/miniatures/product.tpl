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

    {* Badge "Compatible" — uniquement sur les catégories Accessoires Powerparts (cf. CategoryController) *}
    {if isset($ms_show_moto_context) && $ms_show_moto_context}
    <span class="ms-product-card__compat" aria-hidden="true">
      {l s='Compatible' d='Shop.Theme.Catalog'}
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none">
        <path d="M5 12L10 17L20 7" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </span>
    {/if}
  </a>

  {* ── Infos ── *}
  <div class="ms-product-card__body">

    <h2 class="ms-product-card__name">
      <a href="{$product.url}">{$product.name}</a>
    </h2>

    {* show_price masque le prix quand PS refuse la commande (rupture + comportement
       "deny"). $product.price reste calcule independamment de ce flag : on l'affiche
       toujours, l'indisponibilite est deja signalee par le badge ci-dessous. *}
    {if $product.price}
      <div class="ms-product-card__prices">
        <span class="ms-product-card__price">{$product.price}</span>
        {if $product.has_discount}
          <span class="ms-product-card__price--old">{$product.regular_price}</span>
        {/if}
      </div>
    {/if}

    {* État de disponibilité à 4 valeurs — cf. docs/SPEC_disponibilite_stock.md.
       $product.availability (natif PS) ne distingue que available / last_remaining_items /
       unavailable : 'unavailable' recouvre aussi bien "rupture magasin mais commandable
       via le stock constructeur" (add_to_cart_url présent, out_of_stock=1) que "épuisé
       partout" (add_to_cart_url absent). On les sépare nous-mêmes. *}
    {if $product.availability == 'available'}
      {assign var='msAvailability' value='available'}
    {elseif $product.availability == 'last_remaining_items'}
      {assign var='msAvailability' value='last_remaining_items'}
    {elseif $product.add_to_cart_url}
      {assign var='msAvailability' value='backorder'}
    {else}
      {assign var='msAvailability' value='out_of_stock'}
    {/if}

    <div class="ms-product-card__availability ms-product-card__availability--{$msAvailability}">
      {if $msAvailability == 'available'}
        {l s='Disponible' d='Shop.Theme.Catalog'}
      {elseif $msAvailability == 'last_remaining_items'}
        {l s='Derniers articles en stock' d='Shop.Theme.Catalog'}
      {elseif $msAvailability == 'backorder'}
        {l s='En stock constructeur' d='Shop.Theme.Catalog'}
        {if $product.available_date && $product.available_date != '0000-00-00'}
          — {l s='réassort le' d='Shop.Theme.Catalog'} {$product.available_date|date_format:"%d/%m/%Y"}
        {/if}
      {else}
        {l s='Épuisé' d='Shop.Theme.Catalog'}
        {if $product.available_date && $product.available_date != '0000-00-00'}
          — {l s='réassort prévu le' d='Shop.Theme.Catalog'} {$product.available_date|date_format:"%d/%m/%Y"}
        {/if}
      {/if}
    </div>

    {if $product.add_to_cart_url}
      <form action="{$product.add_to_cart_url}" method="post" class="ms-product-card__add-form">
        <input type="hidden" name="token" value="{$static_token}">
        <input type="hidden" name="id_product" value="{$product.id_product}">
        <input type="hidden" name="qty" value="1">
        <button type="submit" class="ms-product-card__add">{l s='AJOUTER' d='Shop.Theme.Actions'}</button>
      </form>
    {else}
      <a href="{$product.url}" class="ms-product-card__add">{l s='AJOUTER' d='Shop.Theme.Actions'}</a>
    {/if}

  </div>

</article>
{/block}
