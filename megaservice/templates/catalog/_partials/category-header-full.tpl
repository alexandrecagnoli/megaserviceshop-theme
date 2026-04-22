<div id="js-product-list-header" class="ms-catalog-hero ms-catalog-hero--full">
  <div class="ms-catalog-hero__inner">
    <h1 class="ms-catalog-hero__title">{$category.name}</h1>

    {if isset($subcategories) && $subcategories|@count > 0}
      <nav class="ms-subcat-nav" aria-label="{l s='Sous-catégories' d='Shop.Theme.Catalog'}">
        {foreach from=$subcategories item="subcat"}
          <a href="{$subcat.url}" class="ms-subcat-nav__item">
            {if !empty($subcat.image.medium.url)}
              <img
                src="{$subcat.image.medium.url}"
                alt="{$subcat.name|escape:'html':'UTF-8'}"
                loading="lazy"
              >
            {elseif !empty($subcat.image.large.url)}
              <img
                src="{$subcat.image.large.url}"
                alt="{$subcat.name|escape:'html':'UTF-8'}"
                loading="lazy"
              >
            {/if}
            <span class="ms-subcat-nav__label">{$subcat.name}</span>
          </a>
        {/foreach}
      </nav>
    {/if}
  </div>
</div>
