{extends file=$layout}

{block name='head' append}
  <meta property="og:type" content="product">
  {if $product.cover}
    <meta property="og:image" content="{$product.cover.large.url}">
  {/if}
  {if $product.show_price}
    <meta property="product:pretax_price:amount" content="{$product.price_tax_exc}">
    <meta property="product:pretax_price:currency" content="{$currency.iso_code}">
    <meta property="product:price:amount" content="{$product.price_amount}">
    <meta property="product:price:currency" content="{$currency.iso_code}">
  {/if}
{/block}

{block name='head_microdata_special'}
  {include file='_partials/microdata/product-jsonld.tpl'}
{/block}

{block name='content'}
<div class="ms-product js-product-container" id="main">
  <meta content="{$product.url}">

  {* ── Zone principale : images + infos ── *}
  <div class="ms-product__top">

    {* Colonne gauche — images *}
    <div class="ms-product__gallery">
      {block name='product_cover_thumbnails'}
        {include file='catalog/_partials/product-cover-thumbnails.tpl'}
      {/block}
    </div>

    {* Colonne droite — infos *}
    <div class="ms-product__info">

      <h1 class="ms-product__name">{$product.name}</h1>

      {if isset($product.reference_to_display) && $product.reference_to_display neq ''}
        <p class="ms-product__reference">{l s='Référence' d='Shop.Theme.Catalog'} : <span>{$product.reference_to_display}</span></p>
      {/if}

      {if $product.description_short}
        <div class="ms-product__desc">{$product.description_short nofilter}</div>
      {/if}

      <div class="ms-product__availability ms-product__availability--{$product.availability}">
        {if $product.availability == 'available'}
          {l s='Disponible' d='Shop.Theme.Catalog'}
        {elseif $product.availability == 'last_remaining_items'}
          {l s='Derniers articles en stock' d='Shop.Theme.Catalog'}
        {else}
          {$product.availability_message}
        {/if}
      </div>

      <div class="product-actions js-product-actions">
        {block name='product_buy'}
          <form action="{$urls.pages.cart}" method="post" id="add-to-cart-or-refresh">
            <input type="hidden" name="token" value="{$static_token}">
            <input type="hidden" name="id_product" value="{$product.id}" id="product_page_product_id">
            <input type="hidden" name="id_customization" value="{$product.id_customization}" id="product_customization_id" class="js-product-customization-id">

            {block name='product_variants'}
              {include file='catalog/_partials/product-variants.tpl'}
            {/block}

            {block name='product_prices'}
              {include file='catalog/_partials/product-prices.tpl'}
            {/block}

            <div class="ms-product__accordion js-accordion">
              <button type="button" class="ms-product__accordion-trigger js-accordion-trigger" aria-expanded="false">
                {l s='Informations de livraison' d='Shop.Theme.Catalog'}
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                  <path d="M4 6L8 10L12 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
              <div class="ms-product__accordion-body" hidden>
                {hook h='displayProductDeliveryTime' product=$product}
                <p>{l s="Livraison offerte dès 150€ d'achat en France métropolitaine." d='Shop.Theme.Catalog'}</p>
              </div>
            </div>

            {block name='product_add_to_cart'}
              {include file='catalog/_partials/product-add-to-cart.tpl'}
            {/block}

            {block name='product_refresh'}{/block}
          </form>
        {/block}
      </div>

    </div>
  </div>

  {* ── Onglets ── *}
  {block name='product_tabs'}
    <div class="ms-product__tabs-section">

      <nav class="ms-product__tabs-nav">
        {if $product.description}
          <button class="ms-product__tab-btn js-product-tab-btn" data-tab="description">{l s='Description' d='Shop.Theme.Catalog'}</button>
        {/if}
        <button class="ms-product__tab-btn js-product-tab-btn is-active" data-tab="features">{l s='Fiche technique' d='Shop.Theme.Catalog'}</button>
        {foreach from=$product.extra_tabs item=extra_tab}
          <button class="ms-product__tab-btn js-product-tab-btn" data-tab="{$extra_tab.id_tab|escape:'htmlall'}">{$extra_tab.title}</button>
        {/foreach}
      </nav>

      <div class="ms-product__tabs-content">

        {if $product.description}
          <div class="ms-product__tab-panel js-product-tab-panel" data-panel="description">
            <div class="ms-product__description">{$product.description nofilter}</div>
          </div>
        {/if}

        <div class="ms-product__tab-panel js-product-tab-panel is-active" data-panel="features">
          {if $product.grouped_features}
            <div class="ms-product__features">
              <nav class="ms-product__features-nav">
                {foreach from=$product.grouped_features item=feature key=k}
                  <button class="ms-product__feature-group-btn js-feature-group-btn{if $k@first} is-active{/if}" data-group="{$k}">{$feature.name}</button>
                {/foreach}
              </nav>
              <div class="ms-product__features-table">
                {foreach from=$product.grouped_features item=feature key=k}
                  <table class="ms-product__feature-group js-feature-group{if $k@first} is-active{/if}" data-group="{$k}">
                    {foreach from=$feature.features item=f}
                      <tr>
                        <td class="ms-product__feature-name">{$f.name}</td>
                        <td class="ms-product__feature-value">{$f.value|escape:'htmlall'|nl2br nofilter}</td>
                      </tr>
                    {/foreach}
                  </table>
                {/foreach}
              </div>
            </div>
          {else}
            {block name='product_details'}
              {include file='catalog/_partials/product-details.tpl'}
            {/block}
          {/if}
        </div>

        {foreach from=$product.extra_tabs item=extra_tab}
          <div class="ms-product__tab-panel js-product-tab-panel" data-panel="{$extra_tab.id_tab|escape:'htmlall'}">
            {$extra_tab.content nofilter}
          </div>
        {/foreach}

      </div>
    </div>
  {/block}

  {* ── Accessoires ── *}
  {block name='product_accessories'}
    {if $accessories}
      <section class="ms-products-section">
        <div class="ms-container">
          <div class="ms-products-section__header">
            <h2 class="ms-products-section__title">{l s='Accessoires Powerparts associés' d='Shop.Theme.Catalog'}</h2>
          </div>
          <div class="products">
            {foreach from=$accessories item="product_accessory" key="position"}
              {include file='catalog/_partials/miniatures/product.tpl' product=$product_accessory position=$position}
            {/foreach}
          </div>
        </div>
      </section>
    {/if}
  {/block}

  {block name='product_footer'}
    {hook h='displayFooterProduct' product=$product category=$category}
  {/block}

  {block name='product_images_modal'}
    {include file='catalog/_partials/product-images-modal.tpl'}
  {/block}

</div>
{/block}
