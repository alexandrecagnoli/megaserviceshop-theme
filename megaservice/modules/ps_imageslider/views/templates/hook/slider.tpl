{**
 * Hero Carousel — ps_imageslider override
 * Megaservice Theme
 *}
{if $homeslider.slides}
<section class="ms-carousel" data-carousel aria-label="Carousel principal">

  <div class="ms-carousel__track" data-carousel-track>
    {foreach from=$homeslider.slides item=slide name=slides_loop}
    <div class="ms-carousel__slide{if $smarty.foreach.slides_loop.first} is-active{/if}"
         data-carousel-slide
         role="group"
         aria-roledescription="slide"
         aria-label="{$smarty.foreach.slides_loop.index+1} sur {$smarty.foreach.slides_loop.total}">

      <div class="ms-carousel__media">
        <img class="ms-carousel__img"
             src="{$slide.image_url}"
             alt="{$slide.legend|escape}"
             {if $smarty.foreach.slides_loop.first}loading="eager"{else}loading="lazy"{/if}>
      </div>

      <div class="ms-carousel__overlay" aria-hidden="true"></div>

      <div class="ms-carousel__content">
        {if $slide.title}
        <h2 class="ms-carousel__title">{$slide.title|escape:'html':'UTF-8'}</h2>
        {/if}
        {if $slide.description}
        <div class="ms-carousel__subtitle">{$slide.description nofilter}</div>
        {/if}
        {if $slide.url && $slide.legend}
        <a href="{$slide.url}" class="ms-carousel__cta">
          {$slide.legend|escape:'html':'UTF-8'}
        </a>
        {/if}
      </div>

    </div>
    {/foreach}
  </div>

  {if count($homeslider.slides) > 1}
  <nav class="ms-carousel__dots" aria-label="Navigation carousel" data-carousel-dots>
    {foreach from=$homeslider.slides item=slide name=dots_loop}
    <button class="ms-carousel__dot{if $smarty.foreach.dots_loop.first} is-active{/if}"
            data-carousel-dot="{$smarty.foreach.dots_loop.index}"
            aria-label="Slide {$smarty.foreach.dots_loop.index+1}"
            aria-current="{if $smarty.foreach.dots_loop.first}true{else}false{/if}"
            type="button"></button>
    {/foreach}
  </nav>
  {/if}

</section>
{/if}
