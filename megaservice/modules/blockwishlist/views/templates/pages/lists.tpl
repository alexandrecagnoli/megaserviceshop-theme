{*
 * Override Megaservice Theme
 * On utilise customer/page.tpl pour bénéficier de notre layout sidebar nav + main.
 * Le template original override page_content_container (toute la zone) — nous on
 * passe par page_content (block enfant) pour rester dans .ms-account__main.
 * Le footer-links chevron_left/home n'est pas re-rendu : la sidebar nav remplit ce rôle.
 *}
{extends file='customer/page.tpl'}

{block name='page_title'}
  {l s='Mes listes d\'envies' d='Modules.Blockwishlist.Shop'}
{/block}

{block name='page_content'}
  <div
    class="wishlist-container"
    data-url="{$url}"
    data-title="{$wishlistsTitlePage}"
    data-empty-text="{l s='No wishlist found.' d='Modules.Blockwishlist.Shop'}"
    data-rename-text="{l s='Rename' d='Modules.Blockwishlist.Shop'}"
    data-share-text="{l s='Share' d='Modules.Blockwishlist.Shop'}"
    data-add-text="{$newWishlistCTA}"
  ></div>

  {include file="module:blockwishlist/views/templates/components/modals/share.tpl" url=$shareUrl}
  {include file="module:blockwishlist/views/templates/components/modals/rename.tpl" url=$renameUrl}
{/block}
