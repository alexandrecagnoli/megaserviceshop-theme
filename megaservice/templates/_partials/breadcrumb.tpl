<nav data-depth="{$breadcrumb.count}" class="ms-breadcrumb">
  <div class="ms-container">
  <ol class="ms-breadcrumb__list">
    {block name='breadcrumb'}
      {foreach from=$breadcrumb.links item=path name=breadcrumb}
        {block name='breadcrumb_item'}
          <li class="ms-breadcrumb__item">
            {if not $smarty.foreach.breadcrumb.last}
              <a href="{$path.url}" class="ms-breadcrumb__link">{$path.title}</a>
              <svg class="ms-breadcrumb__sep" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                <path d="M5.25 10.5L8.75 7L5.25 3.5" stroke="black" stroke-opacity="0.8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            {else}
              <span class="ms-breadcrumb__current">{$path.title}</span>
            {/if}
          </li>
        {/block}
      {/foreach}
    {/block}
  </ol>
  </div>
</nav>
