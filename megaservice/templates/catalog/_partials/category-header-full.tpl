<div id="js-product-list-header" class="ms-catalog-hero ms-catalog-hero--full">
  <div class="ms-catalog-hero__inner">
    <h1 class="ms-catalog-hero__title">{$category.name}</h1>

    {if isset($subcategories) && $subcategories|@count > 0}
      <nav class="ms-subcat-nav" aria-label="{l s='Sous-catégories' d='Shop.Theme.Catalog'}">
        {foreach from=$subcategories item="subcat"}
          {assign var='_subcat_img' value=''}
          {if !empty($subcat.image.medium.url)}{assign var='_subcat_img' value=$subcat.image.medium.url}
          {elseif !empty($subcat.image.large.url)}{assign var='_subcat_img' value=$subcat.image.large.url}
          {elseif !empty($subcat.image.small.url)}{assign var='_subcat_img' value=$subcat.image.small.url}
          {/if}
          <a href="{$subcat.url}"
             class="ms-subcat-nav__item"
             aria-label="{$subcat.name|escape:'html':'UTF-8'}"
             {if $_subcat_img}style="background-image:url('{$_subcat_img}');"{/if}>
            <span class="ms-subcat-nav__label">{$subcat.name}</span>
          </a>
        {/foreach}
      </nav>
    {/if}
  </div>
</div>
