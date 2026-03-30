<div class="ms-product__gallery-main js-gallery-main">
  {if $product.default_image}
    <picture>
      {if !empty($product.default_image.bySize.large_default.sources.avif)}<source srcset="{$product.default_image.bySize.large_default.sources.avif}" type="image/avif">{/if}
      {if !empty($product.default_image.bySize.large_default.sources.webp)}<source srcset="{$product.default_image.bySize.large_default.sources.webp}" type="image/webp">{/if}
      <img
        class="ms-product__gallery-img js-qv-product-cover"
        src="{$product.default_image.bySize.large_default.url}"
        {if !empty($product.default_image.legend)}
          alt="{$product.default_image.legend}"
          title="{$product.default_image.legend}"
        {else}
          alt="{$product.name}"
        {/if}
        loading="lazy"
        width="{$product.default_image.bySize.large_default.width}"
        height="{$product.default_image.bySize.large_default.height}"
      >
    </picture>
  {else}
    <picture>
      {if !empty($urls.no_picture_image.bySize.large_default.sources.avif)}<source srcset="{$urls.no_picture_image.bySize.large_default.sources.avif}" type="image/avif">{/if}
      {if !empty($urls.no_picture_image.bySize.large_default.sources.webp)}<source srcset="{$urls.no_picture_image.bySize.large_default.sources.webp}" type="image/webp">{/if}
      <img
        class="ms-product__gallery-img"
        src="{$urls.no_picture_image.bySize.large_default.url}"
        loading="lazy"
        width="{$urls.no_picture_image.bySize.large_default.width}"
        height="{$urls.no_picture_image.bySize.large_default.height}"
      >
    </picture>
  {/if}
</div>

{if $product.images|count > 1}
  <div class="ms-product__gallery-thumbs js-gallery-thumbs">
    {foreach from=$product.images item=image}
      <button
        type="button"
        class="ms-product__gallery-thumb js-gallery-thumb{if $image.id_image == $product.default_image.id_image} is-active{/if}"
        data-image-large-src="{$image.bySize.large_default.url}"
        {if !empty($image.bySize.large_default.sources)}data-image-large-sources="{$image.bySize.large_default.sources|@json_encode}"{/if}
        aria-label="{if !empty($image.legend)}{$image.legend}{else}{$product.name}{/if}"
      >
        <picture>
          {if !empty($image.bySize.small_default.sources.avif)}<source srcset="{$image.bySize.small_default.sources.avif}" type="image/avif">{/if}
          {if !empty($image.bySize.small_default.sources.webp)}<source srcset="{$image.bySize.small_default.sources.webp}" type="image/webp">{/if}
          <img
            src="{$image.bySize.small_default.url}"
            alt="{if !empty($image.legend)}{$image.legend}{else}{$product.name}{/if}"
            loading="lazy"
            width="{$image.bySize.small_default.width}"
            height="{$image.bySize.small_default.height}"
          >
        </picture>
      </button>
    {/foreach}
  </div>
{/if}
