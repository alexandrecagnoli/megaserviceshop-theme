{if $products}
<section class="ms-products-section">
  <div class="ms-container">
    <div class="ms-products-section__header">
      <h2 class="ms-products-section__title">{l s='Les Produits' d='Modules.Featuredproducts.Shop'}</h2>
    </div>
    {include file='catalog/_partials/productlist.tpl' products=$products}
  </div>
</section>
{/if}
