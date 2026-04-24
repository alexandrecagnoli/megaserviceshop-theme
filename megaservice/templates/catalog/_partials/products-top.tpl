<div id="js-product-list-top" class="ms-catalog-topbar">
  <p class="ms-catalog-topbar__count">
    {l s='Affichage de %from%-%to% sur %total% résultats' d='Shop.Theme.Catalog' sprintf=[
      '%from%' => $listing.pagination.items_shown_from,
      '%to%'   => $listing.pagination.items_shown_to,
      '%total%' => $listing.pagination.total_items
    ]}
  </p>

  <div class="ms-catalog-topbar__right">
    {block name='sort_by'}
      {include file='catalog/_partials/sort-orders.tpl' sort_orders=$listing.sort_orders}
    {/block}

    {if !empty($listing.rendered_facets)}
      <button class="ms-catalog-topbar__filter-btn js-search-toggler" id="search_filter_toggler">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
          <path d="M2 4h12M4 8h8M6 12h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        {l s='Filtrer' d='Shop.Theme.Actions'}
      </button>
    {/if}
  </div>
</div>
