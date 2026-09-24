<div id="js-product-list-header" class="ms-catalog-hero ms-catalog-hero--full">
  <div class="ms-catalog-hero__inner">
    <h1 class="ms-catalog-hero__title">{$category.name}</h1>

    {if isset($subcategories) && $subcategories|@count > 0}
      {* Défilement horizontal : à la souris sans molette latérale, les cartes hors
         cadre sont inatteignables. Les flèches sont posées par subcat-nav.js
         uniquement si le contenu déborde et si le pointeur est fin (cf. SCSS). *}
      <div class="ms-subcat-scroller js-subcat-scroller">
      <nav class="ms-subcat-nav js-subcat-track" aria-label="{l s='Sous-catégories' d='Shop.Theme.Catalog'}">
        {foreach from=$subcategories item="subcat"}
          {* Fallback chain — PS expose des paths variables selon les types d'image générés.
             Cas observé : id_image vide en BDD malgré upload OK → on construit l'URL depuis id_category *}
          {assign var='_subcat_img' value=''}
          {if !empty($subcat.image.large.url)}{assign var='_subcat_img' value=$subcat.image.large.url}
          {elseif !empty($subcat.image.medium.url)}{assign var='_subcat_img' value=$subcat.image.medium.url}
          {elseif !empty($subcat.image.small.url)}{assign var='_subcat_img' value=$subcat.image.small.url}
          {elseif !empty($subcat.id_category)}
            {* Dernier recours : path direct PS construit depuis id_category
               (small_default est le seul type souvent généré pour les categories) *}
            {assign var='_subcat_img' value="/img/c/`$subcat.id_category`-small_default.jpg"}
          {/if}
          <a href="{$subcat.url}"
             class="ms-subcat-nav__item"
             aria-label="{$subcat.name|escape:'html':'UTF-8'}"
             {if $_subcat_img}style="background-image:url('{$_subcat_img}');"{/if}>
            <span class="ms-subcat-nav__label">{$subcat.name}</span>
          </a>
        {/foreach}
      </nav>

        <button type="button"
                class="ms-subcat-scroller__arrow ms-subcat-scroller__arrow--prev js-subcat-prev"
                aria-label="{l s='Voir les catégories précédentes' d='Shop.Theme.Catalog'}" hidden>
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M15 5L8 12L15 19" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>

        <button type="button"
                class="ms-subcat-scroller__arrow ms-subcat-scroller__arrow--next js-subcat-next"
                aria-label="{l s='Voir les catégories suivantes' d='Shop.Theme.Catalog'}" hidden>
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M9 5L16 12L9 19" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
      </div>
    {/if}
  </div>
</div>
