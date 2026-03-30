{if $products}
<section class="ms-products-section">
  <div class="ms-container">
    <div class="ms-products-section__header">
      <h2 class="ms-products-section__title">{l s='Les Nouveautés' d='Modules.Newproducts.Shop'}</h2>
      <a href="{$allNewProductsLink}" class="ms-products-section__see-all">{l s='Toutes les nouveautés' d='Modules.Newproducts.Shop'}</a>
    </div>
    {include file='catalog/_partials/productlist.tpl' products=$products}
  </div>
</section>
{/if}
