<div id="js-product-list-header" class="ms-catalog-hero ms-catalog-hero--full">
  <div class="ms-catalog-hero__inner">
    <h1 class="ms-catalog-hero__title">{$category.name}</h1>

    {if isset($subcategories) && $subcategories|@count > 0}
      <nav class="ms-subcat-nav" aria-label="{l s='Sous-catégories' d='Shop.Theme.Catalog'}">
        {foreach from=$subcategories item="subcat"}
          {* Fallback chain — PS expose des paths variables selon les types d'image générés *}
          {assign var='_subcat_img' value=''}
          {if !empty($subcat.image.large.url)}{assign var='_subcat_img' value=$subcat.image.large.url}
          {elseif !empty($subcat.image.medium.url)}{assign var='_subcat_img' value=$subcat.image.medium.url}
          {elseif !empty($subcat.image.small.url)}{assign var='_subcat_img' value=$subcat.image.small.url}
          {elseif !empty($subcat.image.url)}{assign var='_subcat_img' value=$subcat.image.url}
          {elseif !empty($subcat.image_url)}{assign var='_subcat_img' value=$subcat.image_url}
          {elseif !empty($subcat.id_category) && !empty($subcat.id_image)}
            {* Dernier recours : path direct vers l'image originale uploadée *}
            {assign var='_subcat_img' value="/img/c/`$subcat.id_image`.jpg"}
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
