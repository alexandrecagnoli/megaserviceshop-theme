<section class="ms-mosaic">
  {foreach from=$ms_mosaic item=tile}
    <a href="{$tile.url}" class="ms-mosaic__item ms-mosaic__item--large">
      <div class="ms-mosaic__bg" style="background-image:url('{$urls.theme_assets}img/mosaique/optimized/{$tile.image}')"></div>
      <div class="ms-mosaic__overlay"></div>
      <span class="ms-mosaic__label">{$tile.label}</span>
    </a>
  {/foreach}
</section>
