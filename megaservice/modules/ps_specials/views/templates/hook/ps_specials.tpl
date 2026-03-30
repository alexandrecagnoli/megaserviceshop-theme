{if $products}
<section class="ms-products-section">
  <div class="ms-container">
    <div class="ms-products-section__header">
      <h2 class="ms-products-section__title">{l s='En Promo' d='Modules.Specials.Shop'}</h2>
      <a href="{$allSpecialProductsLink}" class="ms-products-section__see-all">{l s='Toutes les promos' d='Modules.Specials.Shop'}</a>
    </div>
    {include file='catalog/_partials/productlist.tpl' products=$products}
  </div>
</section>
{/if}
