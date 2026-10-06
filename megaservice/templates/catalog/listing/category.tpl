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
{extends file='catalog/listing/product-list.tpl'}

{block name='product_list_header'}
  {if $ms_is_full_width}
    {include file='catalog/_partials/category-header-full.tpl' listing=$listing category=$category}
  {else}
    {include file='catalog/_partials/category-header.tpl' listing=$listing category=$category}
  {/if}

  {* Grand sélecteur moto — même section que la home, entre le hero et le
     catalogue. Uniquement dans les branches « pièces » ET sans filtre actif
     (cf. CategoryController::showMotoFinder) : naviguer sans moto y revient à
     parcourir des dizaines de milliers de références indifférenciées.
     Dès qu'une moto est choisie, le bandeau de contexte prend le relais et
     cette section disparaît — les deux ne coexistent jamais. *}
  {if isset($ms_show_moto_finder) && $ms_show_moto_finder}
    {include file='_partials/parts-search.tpl'}
  {/if}
{/block}

{block name='product_list_footer'}
  {include file='catalog/_partials/category-footer.tpl' listing=$listing category=$category}
{/block}

{* Sur « Pièces détachées d'origine », la liste produits cède la place à un hub
   d'orientation : elle remontait 40 897 références sur 3 409 pages, à commencer
   par des manuels d'utilisateur sans photo. Sans moto, la compatibilité n'y est
   pas connue — et pour ces pièces elle passe de toute façon par les vues
   éclatées, pas par la montabilité (cf. CategoryController::showPartsHub).

   On court-circuite `content` plutôt que les blocs de liste : ni la colonne de
   facettes, ni le tri, ni la pagination n'ont de sens sur une page sans
   produits. Partout ailleurs, le listing reste strictement celui du parent. *}
{block name='content'}
  {if isset($ms_show_parts_hub) && $ms_show_parts_hub}

    {block name='parts_hub_header'}
      {if $ms_is_full_width}
        {include file='catalog/_partials/category-header-full.tpl' listing=$listing category=$category}
      {else}
        {include file='catalog/_partials/category-header.tpl' listing=$listing category=$category}
      {/if}
    {/block}

    {* Le sélecteur reste la voie rapide pour qui connaît sa moto. *}
    {include file='_partials/parts-search.tpl'}

    {include file='catalog/_partials/parts-hub.tpl'}

  {else}
    {$smarty.block.parent}
  {/if}
{/block}
