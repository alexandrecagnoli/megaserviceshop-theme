<div id="js-product-list-header" class="ms-catalog-hero ms-catalog-hero--full{if isset($ms_show_moto_context) && $ms_show_moto_context} ms-catalog-hero--with-context{/if}">
  <div class="ms-catalog-hero__inner">
    <h1 class="ms-catalog-hero__title">{$category.name}{if isset($ms_h1_suffix)}{$ms_h1_suffix}{/if}{if isset($ms_moto_filter) && $ms_moto_filter}<span class="ms-catalog-hero__title-suffix"> {l s='compatibles avec' d='Shop.Theme.Catalog'} <span>{$ms_moto_filter.label|escape:'html'}</span></span>{/if}</h1>

    {if isset($ms_show_subcat_nav) && $ms_show_subcat_nav}
      {include file='catalog/_partials/subcat-nav.tpl'}
    {/if}
  </div>

  {* Bandeau moto « Catalogue filtré sur <moto> » — même partial que le hero normal.
     Indispensable ici : sans le marqueur .ms-catalog-context, le JS de purge ne
     nettoyait pas l'URL ?moto= sur la racine plein-largeur → le filtre revenait au
     changement de page (cf. moto-context.tpl et moto-garage.js). *}
  {include file='catalog/_partials/moto-context.tpl'}
</div>
