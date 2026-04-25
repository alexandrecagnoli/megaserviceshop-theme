<div id="js-product-list-header" class="ms-catalog-hero">
  <div class="ms-catalog-hero__inner">
    <h1 class="ms-catalog-hero__title">
      {$category.name}<span class="ms-catalog-hero__title-suffix"> {l s='compatibles avec' d='Shop.Theme.Catalog'} <span class="js-model-current-name"></span></span>
    </h1>
  </div>

  {* État rempli — visible uniquement quand body.has-moto-selected *}
  <div class="ms-catalog-context">
    <div class="ms-catalog-context__inner">

      <div class="ms-catalog-context__moto">
        <img src="{$urls.theme_assets}img/640733_MY26_KTM 990 DUKE R_ACTION_ACTION_2026 Street and Adventure models_KTM 990 DUKE R_ACTION 1.jpg" alt="" class="ms-catalog-context__moto-img">
        <span class="ms-catalog-context__brand" aria-hidden="true">
          <img src="{$urls.theme_assets}img/brands/KTM_HUSQVARNA_GASGAS_LOGO.png" alt="">
        </span>
      </div>

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

</div>
