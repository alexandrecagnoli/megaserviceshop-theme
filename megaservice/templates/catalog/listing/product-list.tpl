{**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
{extends file=$layout}

{* ms_is_full_width est assigné par CategoryController (override/controllers/front/CategoryController.php) *}
{if !isset($ms_is_full_width)}{assign var='ms_is_full_width' value=false}{/if}

{block name='head_microdata_special'}
  {include file='_partials/microdata/product-list-jsonld.tpl' listing=$listing}
{/block}

{* Vide le left_column PS — les filtres sont rendus dans ms-catalog__sidebar *}
{block name='left_column'}{/block}

{block name='content'}

  {block name='product_list_header'}{/block}

  <div class="ms-catalog-layout{if $ms_is_full_width} ms-catalog-layout--full{/if}">

    <div class="ms-catalog__main">

      {hook h="displayHeaderCategory"}

      <section id="products">
        {if $listing.products|count}

          {block name='product_list_top'}
            {include file='catalog/_partials/products-top.tpl' listing=$listing}
          {/block}

          {block name='product_list'}
            {include file='catalog/_partials/products.tpl' listing=$listing productClass=""}
          {/block}

          {block name='product_list_bottom'}
            {include file='catalog/_partials/products-bottom.tpl' listing=$listing}
          {/block}

        {else}
          <div id="js-product-list-top"></div>

          <div id="js-product-list">
            {capture assign="errorContent"}
              <h4>{l s='No products available yet' d='Shop.Theme.Catalog'}</h4>
              <p>{l s='Stay tuned! More products will be shown here as they are added.' d='Shop.Theme.Catalog'}</p>
            {/capture}
            {include file='errors/not-found.tpl' errorContent=$errorContent}
          </div>

          <div id="js-product-list-bottom"></div>
        {/if}
      </section>

    </div>

    {* Sidebar filtres uniquement pour les layouts avec colonne *}
    {if !$ms_is_full_width}
      {capture assign='sidebar_content'}{hook h="displayLeftColumn"}{/capture}
      {if $sidebar_content|trim}
        <aside class="ms-catalog__sidebar">

          <a href="javascript:history.back()" class="ms-catalog__back">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M19 12H5M5 12L12 19M5 12L12 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>{l s='Retour' d='Shop.Theme.Actions'}</span>
          </a>

          {* Bloc parent catégorie — visible uniquement quand body.has-moto-selected *}
          <div class="ms-catalog__cat-card">
            <img src="{$urls.theme_assets}img/parts-search-bg.jpg" alt="" class="ms-catalog__cat-card-bg" loading="lazy">
            <span class="ms-catalog__cat-card-label">{l s='Accessoires powerparts' d='Shop.Theme.Catalog'}</span>
          </div>

          <div id="js-search-filters-wrapper">
            {$sidebar_content nofilter}
          </div>
        </aside>
      {/if}
    {/if}

  </div>

  {block name='product_list_footer'}{/block}

  {hook h="displayFooterCategory"}

{/block}
