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
        {* Overlay du bottom sheet — mobile uniquement *}
        <div class="ms-filters-overlay js-filters-overlay" hidden aria-hidden="true"></div>

        <aside class="ms-catalog__sidebar js-filters-sheet">

          {* Header sheet — mobile uniquement *}
          <header class="ms-catalog__sidebar-head">
            <h2 class="ms-catalog__sidebar-title">{l s='Filtres' d='Shop.Theme.Catalog'}</h2>
            <button type="button" class="ms-catalog__sidebar-close js-filters-close" aria-label="{l s='Fermer les filtres' d='Shop.Theme.Actions'}">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
              </svg>
            </button>
          </header>

          {* Bloc parent catégorie — uniquement sur les pages de la branche Accessoires Powerparts (cf. CategoryController) *}
          {if isset($ms_show_moto_context) && $ms_show_moto_context}
          <div class="ms-catalog__cat-card">
            <img src="{$urls.theme_assets}img/akrapovic-exhaust.png" alt="" class="ms-catalog__cat-card-bg" loading="lazy">
            <span class="ms-catalog__cat-card-label">{l s='Accessoires powerparts' d='Shop.Theme.Catalog'}</span>
          </div>
          {/if}

          <div id="js-search-filters-wrapper">
            {$sidebar_content nofilter}
          </div>

          {* Footer sheet — mobile uniquement *}
          <footer class="ms-catalog__sidebar-foot">
            <button type="button" class="ms-catalog__sidebar-apply js-filters-apply">{l s='Appliquer les filtres' d='Shop.Theme.Actions'}</button>
            <button type="button" class="ms-catalog__sidebar-reset js-filters-reset">{l s='Réinitialiser' d='Shop.Theme.Actions'}</button>
          </footer>

        </aside>
      {/if}
    {/if}

  </div>

  {block name='product_list_footer'}{/block}

  {hook h="displayFooterCategory"}

{/block}
