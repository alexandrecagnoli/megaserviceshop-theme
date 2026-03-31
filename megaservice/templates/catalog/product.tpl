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
  <div class="ms-container">
  <div class="ms-product__top">

    {* Colonne gauche — images *}
    <div class="ms-product__gallery">
      {block name='product_cover_thumbnails'}
        {include file='catalog/_partials/product-cover-thumbnails.tpl'}
      {/block}
    </div>

    {* Colonne droite — infos *}
    <div class="ms-product__info">

      <div class="ms-product__identity">
        <h1 class="ms-product__name">{$product.name}</h1>

        {if isset($product.reference_to_display) && $product.reference_to_display neq ''}
          <p class="ms-product__reference">{l s='Référence' d='Shop.Theme.Catalog'} : {$product.reference_to_display}</p>
        {/if}

        {if $product.description_short}
          <div class="ms-product__desc">{$product.description_short nofilter}</div>
        {/if}

        <div class="ms-product__availability ms-product__availability--{$product.availability}">
          {if $product.availability == 'available'}
            {l s='En stock - Livraison sous 48h' d='Shop.Theme.Catalog'}
          {elseif $product.availability == 'last_remaining_items'}
            {l s='Derniers articles en stock' d='Shop.Theme.Catalog'}
          {else}
            {$product.availability_message}
          {/if}
        </div>
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
                <span class="ms-product__accordion-icon ms-product__accordion-icon--plus" aria-hidden="true">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M12 5V19" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M5 12H19" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </span>
                <span class="ms-product__accordion-icon ms-product__accordion-icon--minus" aria-hidden="true" hidden>
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M19 11H5C4.73478 11 4.48043 11.1054 4.29289 11.2929C4.10536 11.4804 4 11.7348 4 12C4 12.2652 4.10536 12.5196 4.29289 12.7071C4.48043 12.8946 4.73478 13 5 13H19C19.2652 13 19.5196 12.8946 19.7071 12.7071C19.8946 12.5196 20 12.2652 20 12C20 11.7348 19.8946 11.4804 19.7071 11.2929C19.5196 11.1054 19.2652 11 19 11Z" fill="black"/>
                  </svg>
                </span>
              </button>
              <div class="ms-product__accordion-body" hidden>
                <div class="ms-product__delivery-rows">
                  <div class="ms-product__delivery-row">
                    <div class="ms-product__delivery-row-left">
                      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                        <path d="M2.25 6.75H15.75M2.25 6.75V14.25C2.25 14.6642 2.58579 15 3 15H15C15.4142 15 15.75 14.6642 15.75 14.25V6.75M2.25 6.75L3.75 3H14.25L15.75 6.75M7.5 15V10.5H10.5V15" stroke="#484848" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                      </svg>
                      <span>{l s='Retrait en magasin' d='Shop.Theme.Catalog'}</span>
                    </div>
                    <span class="ms-product__delivery-row-time">{l s='24H' d='Shop.Theme.Catalog'}</span>
                  </div>
                  <div class="ms-product__delivery-row">
                    <div class="ms-product__delivery-row-left">
                      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                        <path d="M1.5 3.75H12V11.25H1.5V3.75Z" stroke="#484848" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M12 6H14.625L16.5 8.625V11.25H12V6Z" stroke="#484848" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M4.125 13.875C4.95343 13.875 5.625 13.2034 5.625 12.375C5.625 11.5466 4.95343 10.875 4.125 10.875C3.29657 10.875 2.625 11.5466 2.625 12.375C2.625 13.2034 3.29657 13.875 4.125 13.875Z" stroke="#484848" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M13.875 13.875C14.7034 13.875 15.375 13.2034 15.375 12.375C15.375 11.5466 14.7034 10.875 13.875 10.875C13.0466 10.875 12.375 11.5466 12.375 12.375C12.375 13.2034 13.0466 13.875 13.875 13.875Z" stroke="#484848" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                      </svg>
                      <span>{l s='Livraison à domicile' d='Shop.Theme.Catalog'}</span>
                    </div>
                    <span class="ms-product__delivery-row-time">{l s='3 À 5 JOURS OUVRÉS' d='Shop.Theme.Catalog'}</span>
                  </div>
                  <div class="ms-product__delivery-row">
                    <div class="ms-product__delivery-row-left">
                      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                        <path d="M1.5 3.75H12V11.25H1.5V3.75Z" stroke="#484848" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M12 6H14.625L16.5 8.625V11.25H12V6Z" stroke="#484848" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M4.125 13.875C4.95343 13.875 5.625 13.2034 5.625 12.375C5.625 11.5466 4.95343 10.875 4.125 10.875C3.29657 10.875 2.625 11.5466 2.625 12.375C2.625 13.2034 3.29657 13.875 4.125 13.875Z" stroke="#484848" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M13.875 13.875C14.7034 13.875 15.375 13.2034 15.375 12.375C15.375 11.5466 14.7034 10.875 13.875 10.875C13.0466 10.875 12.375 11.5466 12.375 12.375C12.375 13.2034 13.0466 13.875 13.875 13.875Z" stroke="#484848" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M7.5 5.25L9 6.75L11.25 4.5" stroke="#484848" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                      </svg>
                      <span>{l s='Livraison en point relais' d='Shop.Theme.Catalog'}</span>
                    </div>
                    <span class="ms-product__delivery-row-time">{l s='3 À 5 JOURS OUVRÉS' d='Shop.Theme.Catalog'}</span>
                  </div>
                </div>
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
  </div>

  {* ── Onglets ── *}
  {block name='product_tabs'}
    <div class="ms-container">
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
          {if $product.features}
            <div class="ms-product__features">
              <table class="ms-product__feature-group is-active">
                {foreach from=$product.features item=f}
                  <tr>
                    <td class="ms-product__feature-name">{$f.name}</td>
                    <td class="ms-product__feature-value">{$f.value|escape:'htmlall'|nl2br nofilter}</td>
                  </tr>
                {/foreach}
              </table>
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
    </div>
  {/block}

  {* ── Accessoires Powerparts + Produits associés ── *}
  {* TODO: filtrer powerparts vs produits associés quand les données seront prêtes *}
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
      <section class="ms-products-section">
        <div class="ms-container">
          <div class="ms-products-section__header">
            <h2 class="ms-products-section__title">{l s='Produits qui pourraient vous plaire' d='Shop.Theme.Catalog'}</h2>
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

  {block name='product_footer'}{/block}

  {block name='product_images_modal'}
    {include file='catalog/_partials/product-images-modal.tpl'}
  {/block}

</div>
{/block}
