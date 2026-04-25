<div id="js-product-list-header" class="ms-catalog-hero">
  <div class="ms-catalog-hero__inner">
    <h1 class="ms-catalog-hero__title">
      {$category.name}<span class="ms-catalog-hero__title-suffix"> {l s='compatibles avec' d='Shop.Theme.Catalog'} <span class="js-model-current-name"></span></span>
    </h1>
  </div>

  {* Bandeau moto — uniquement sur les pages de la branche Accessoires Powerparts (cf. CategoryController) *}
  {if isset($ms_show_moto_context) && $ms_show_moto_context}
  <div class="ms-catalog-context">
    <img src="{$urls.theme_assets}img/moto-context.png" alt="" class="ms-catalog-context__moto-img">
    <div class="ms-catalog-context__inner">

      <span class="ms-catalog-context__brand" aria-hidden="true">
        <img src="{$urls.theme_assets}img/brands/ktm-square.png" alt="">
      </span>

      <div class="ms-catalog-context__text">
        <span class="ms-catalog-context__label">{l s='Catalogue filtré sur' d='Shop.Theme.Catalog'}</span>
        <strong class="ms-catalog-context__moto-name js-model-current-name"></strong>
      </div>

      <div class="ms-catalog-context__actions">
        <a href="#" class="ms-catalog-context__link js-model-trigger">{l s='Changer de modèle' d='Shop.Theme.Catalog'}</a>
        <a href="#" class="ms-catalog-context__link">{l s='Ajouter à mon garage' d='Shop.Theme.Catalog'}</a>
      </div>

    </div>
  </div>
  {/if}

</div>
