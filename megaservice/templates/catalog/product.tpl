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
                        <path d="M14.361 2.22825C14.0918 1.779 13.599 1.5 13.0755 1.5H4.9245C4.401 1.5 3.90825 1.779 3.639 2.22825L1.60725 5.61375C1.53675 5.73075 1.5 5.8635 1.5 6C1.5 6.7545 1.7895 7.4355 2.25 7.9635V14.25C2.25 15.0773 2.92275 15.75 3.75 15.75H6.75H11.25H14.25C15.0773 15.75 15.75 15.0773 15.75 14.25V7.9635C16.2105 7.4355 16.5 6.7545 16.5 6C16.5 5.8635 16.4633 5.73075 16.3928 5.61375L14.361 2.22825ZM14.988 6.18825C14.895 6.92625 14.2635 7.5 13.5 7.5C12.6727 7.5 12 6.82725 12 6C12 5.949 11.9813 5.904 11.9708 5.856L11.9857 5.853L11.415 3H13.0755L14.988 6.18825ZM8.11425 3H9.885L10.4948 6.04875C10.4685 6.85275 9.81 7.5 9 7.5C8.19 7.5 7.5315 6.85275 7.50525 6.04875L8.11425 3ZM4.9245 3H6.585L6.015 5.853L6.03 5.856C6.01875 5.904 6 5.949 6 6C6 6.82725 5.32725 7.5 4.5 7.5C3.7365 7.5 3.105 6.92625 3.012 6.18825L4.9245 3ZM7.5 14.25V12H10.5V14.25H7.5ZM12 14.25V12C12 11.1727 11.3273 10.5 10.5 10.5H7.5C6.67275 10.5 6 11.1727 6 12V14.25H3.75V8.8935C3.99075 8.95575 4.239 9 4.5 9C5.39475 9 6.20025 8.60625 6.75 7.98225C7.29975 8.60625 8.10525 9 9 9C9.89475 9 10.7003 8.60625 11.25 7.98225C11.7997 8.60625 12.6053 9 13.5 9C13.761 9 14.0092 8.95575 14.25 8.8935V14.25H12Z" fill="#484848"/>
                      </svg>
                      <span>{l s='Retrait en magasin' d='Shop.Theme.Catalog'}</span>
                    </div>
                    <span class="ms-product__delivery-row-time">{l s='24H' d='Shop.Theme.Catalog'}</span>
                  </div>
                  <div class="ms-product__delivery-row">
                    <div class="ms-product__delivery-row-left">
                      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                        <path d="M17.25 9.375V13.125C17.25 13.3239 17.171 13.5147 17.0303 13.6553C16.8897 13.796 16.6989 13.875 16.5 13.875H15.75C15.75 14.4717 15.5129 15.044 15.091 15.466C14.669 15.8879 14.0967 16.125 13.5 16.125C12.9033 16.125 12.331 15.8879 11.909 15.466C11.4871 15.044 11.25 14.4717 11.25 13.875H6.75C6.75 14.4717 6.51295 15.044 6.09099 15.466C5.66903 15.8879 5.09674 16.125 4.5 16.125C3.90326 16.125 3.33097 15.8879 2.90901 15.466C2.48705 15.044 2.25 14.4717 2.25 13.875H1.5C1.30109 13.875 1.11032 13.796 0.969669 13.6553C0.829017 13.5147 0.75 13.3239 0.75 13.125V4.125C0.75 3.52826 0.987053 2.95597 1.40901 2.53401C1.83097 2.11205 2.40326 1.875 3 1.875H9.75C10.3467 1.875 10.919 2.11205 11.341 2.53401C11.7629 2.95597 12 3.52826 12 4.125V5.625H13.5C13.8493 5.625 14.1938 5.70633 14.5062 5.86254C14.8187 6.01875 15.0904 6.24556 15.3 6.525L17.1 8.925C17.1219 8.9576 17.1396 8.99289 17.1525 9.03L17.1975 9.1125C17.2306 9.19614 17.2484 9.28506 17.25 9.375V9.375Z" fill="#484848"/>
                        <path d="M5.25 13.875C5.25 13.7267 5.20601 13.5817 5.1236 13.4583C5.04119 13.335 4.92406 13.2389 4.78701 13.1821C4.64997 13.1253 4.49917 13.1105 4.35368 13.1394C4.2082 13.1683 4.07456 13.2398 3.96967 13.3447C3.86478 13.4496 3.79335 13.5832 3.76441 13.7287C3.73547 13.8742 3.75032 14.025 3.80709 14.162C3.86386 14.2991 3.95999 14.4162 4.08332 14.4986C4.20666 14.581 4.35166 14.625 4.5 14.625C4.69891 14.625 4.88968 14.546 5.03033 14.4053C5.17098 14.2647 5.25 14.0739 5.25 13.875Z" fill="#484848"/>
                        <path d="M14.25 13.875C14.25 13.7267 14.206 13.5817 14.1236 13.4583C14.0412 13.335 13.9241 13.2389 13.787 13.1821C13.65 13.1253 13.4992 13.1105 13.3537 13.1394C13.2082 13.1683 13.0746 13.2398 12.9697 13.3447C12.8648 13.4496 12.7933 13.5832 12.7644 13.7287C12.7355 13.8742 12.7503 14.025 12.8071 14.162C12.8639 14.2991 12.96 14.4162 13.0833 14.4986C13.2067 14.581 13.3517 14.625 13.5 14.625C13.6989 14.625 13.8897 14.546 14.0303 14.4053C14.171 14.2647 14.25 14.0739 14.25 13.875Z" fill="#484848"/>
                        <path d="M15.75 10.125H12V12.21C12.4426 11.8144 13.0236 11.6098 13.6165 11.6407C14.2093 11.6716 14.7659 11.9355 15.165 12.375H15.75V10.125Z" fill="#484848"/>
                      </svg>
                      <span>{l s='Livraison à domicile' d='Shop.Theme.Catalog'}</span>
                    </div>
                    <span class="ms-product__delivery-row-time">{l s='3 À 5 JOURS OUVRÉS' d='Shop.Theme.Catalog'}</span>
                  </div>
                  <div class="ms-product__delivery-row">
                    <div class="ms-product__delivery-row-left">
                      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                        <path d="M17.25 9.375V13.125C17.25 13.3239 17.171 13.5147 17.0303 13.6553C16.8897 13.796 16.6989 13.875 16.5 13.875H15.75C15.75 14.4717 15.5129 15.044 15.091 15.466C14.669 15.8879 14.0967 16.125 13.5 16.125C12.9033 16.125 12.331 15.8879 11.909 15.466C11.4871 15.044 11.25 14.4717 11.25 13.875H6.75C6.75 14.4717 6.51295 15.044 6.09099 15.466C5.66903 15.8879 5.09674 16.125 4.5 16.125C3.90326 16.125 3.33097 15.8879 2.90901 15.466C2.48705 15.044 2.25 14.4717 2.25 13.875H1.5C1.30109 13.875 1.11032 13.796 0.969669 13.6553C0.829017 13.5147 0.75 13.3239 0.75 13.125V4.125C0.75 3.52826 0.987053 2.95597 1.40901 2.53401C1.83097 2.11205 2.40326 1.875 3 1.875H9.75C10.3467 1.875 10.919 2.11205 11.341 2.53401C11.7629 2.95597 12 3.52826 12 4.125V5.625H13.5C13.8493 5.625 14.1938 5.70633 14.5062 5.86254C14.8187 6.01875 15.0904 6.24556 15.3 6.525L17.1 8.925C17.1219 8.9576 17.1396 8.99289 17.1525 9.03L17.1975 9.1125C17.2306 9.19614 17.2484 9.28506 17.25 9.375V9.375Z" fill="#484848"/>
                        <path d="M5.25 13.875C5.25 13.7267 5.20601 13.5817 5.1236 13.4583C5.04119 13.335 4.92406 13.2389 4.78701 13.1821C4.64997 13.1253 4.49917 13.1105 4.35368 13.1394C4.2082 13.1683 4.07456 13.2398 3.96967 13.3447C3.86478 13.4496 3.79335 13.5832 3.76441 13.7287C3.73547 13.8742 3.75032 14.025 3.80709 14.162C3.86386 14.2991 3.95999 14.4162 4.08332 14.4986C4.20666 14.581 4.35166 14.625 4.5 14.625C4.69891 14.625 4.88968 14.546 5.03033 14.4053C5.17098 14.2647 5.25 14.0739 5.25 13.875Z" fill="#484848"/>
                        <path d="M14.25 13.875C14.25 13.7267 14.206 13.5817 14.1236 13.4583C14.0412 13.335 13.9241 13.2389 13.787 13.1821C13.65 13.1253 13.4992 13.1105 13.3537 13.1394C13.2082 13.1683 13.0746 13.2398 12.9697 13.3447C12.8648 13.4496 12.7933 13.5832 12.7644 13.7287C12.7355 13.8742 12.7503 14.025 12.8071 14.162C12.8639 14.2991 12.96 14.4162 13.0833 14.4986C13.2067 14.581 13.3517 14.625 13.5 14.625C13.6989 14.625 13.8897 14.546 14.0303 14.4053C14.171 14.2647 14.25 14.0739 14.25 13.875Z" fill="#484848"/>
                        <path d="M15.75 10.125H12V12.21C12.4426 11.8144 13.0236 11.6098 13.6165 11.6407C14.2093 11.6716 14.7659 11.9355 15.165 12.375H15.75V10.125Z" fill="#484848"/>
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

  {* ── Produits associés ── *}
  {block name='product_footer'}
    {capture name='footer_hook'}{hook h='displayFooterProduct' product=$product category=$category}{/capture}
    {if $smarty.capture.footer_hook|trim neq ''}
      <section class="ms-products-section">
        <div class="ms-container">
          <div class="ms-products-section__header">
            <h2 class="ms-products-section__title">{l s='Produits qui pourraient vous plaire' d='Shop.Theme.Catalog'}</h2>
          </div>
          <div class="products">
            {$smarty.capture.footer_hook nofilter}
          </div>
        </div>
      </section>
    {/if}
  {/block}

  {block name='product_images_modal'}
    {include file='catalog/_partials/product-images-modal.tpl'}
  {/block}

</div>
{/block}
