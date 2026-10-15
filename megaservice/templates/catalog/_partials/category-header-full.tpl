<div id="js-product-list-header" class="ms-catalog-hero ms-catalog-hero--full">
  <div class="ms-catalog-hero__inner">
    <h1 class="ms-catalog-hero__title">{$category.name}</h1>

    {if isset($ms_show_subcat_nav) && $ms_show_subcat_nav}
      {include file='catalog/_partials/subcat-nav.tpl'}
    {/if}
  </div>
</div>
