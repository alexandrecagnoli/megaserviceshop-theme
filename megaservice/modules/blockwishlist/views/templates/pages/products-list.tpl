{*
 * Override Megaservice Theme
 * Vue détail d'une liste — wrappe dans customer/page.tpl pour avoir notre sidebar nav.
 *}
{extends file='customer/page.tpl'}

{block name='page_title'}
  {if isset($wishlistName)}{$wishlistName}{else}{l s='Liste d\'envies' d='Modules.Blockwishlist.Shop'}{/if}
{/block}

{block name='page_content'}
  <div
    class="wishlist-products-container"
    data-url="{$url}"
    data-list-id="{$id}"
    data-default-sort="{l s='Last added' d='Modules.Blockwishlist.Shop'}"
    data-add-to-cart="{l s='Add to cart' d='Shop.Theme.Actions'}"
    data-share="{if $isGuest}true{else}false{/if}"
    data-customize-text="{l s='Customize' d='Modules.Blockwishlist.Shop'}"
    data-quantity-text="{l s='Quantity' d='Shop.Theme.Catalog'}"
    data-title="{$wishlistName}"
    data-no-products-message="{l s='No products found' d='Modules.Blockwishlist.Shop'}"
    data-filter="{l s='Sort by:' d='Shop.Theme.Global'}"
  >
  </div>

  {include file="module:blockwishlist/views/templates/components/pagination.tpl"}
{/block}
