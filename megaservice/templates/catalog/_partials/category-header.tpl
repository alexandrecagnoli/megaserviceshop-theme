<div id="js-product-list-header" class="ms-catalog-hero{if isset($ms_show_moto_context) && $ms_show_moto_context} ms-catalog-hero--with-context{/if}">
  <div class="ms-catalog-hero__inner">
    <h1 class="ms-catalog-hero__title">
      {$category.name}{if isset($ms_moto_filter) && $ms_moto_filter}<span class="ms-catalog-hero__title-suffix"> {l s='compatibles avec' d='Shop.Theme.Catalog'} <span>{$ms_moto_filter.label|escape:'html'}</span></span>{/if}
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
        <strong class="ms-catalog-context__moto-name">{if isset($ms_moto_filter) && $ms_moto_filter}{$ms_moto_filter.label|escape:'html'}{/if}</strong>
      </div>

      <div class="ms-catalog-context__actions">
        <a href="#" class="ms-catalog-context__link js-model-trigger">{l s='Changer de modèle' d='Shop.Theme.Catalog'}</a>
        {if isset($ms_moto_filter) && $ms_moto_filter}<a href="{$ms_moto_filter.clear_url|escape:'html'}" class="ms-catalog-context__link">{l s='Retirer le filtre' d='Shop.Theme.Catalog'}</a>{/if}
      </div>

    </div>
  </div>
  {/if}

</div>
