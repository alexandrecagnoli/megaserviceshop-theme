{block name='header'}
  <header id="header" class="header">
    <div class="header-top">
      <div class="page-container">

        <div class="header-left">
          <a id="_desktop_logo" class="header-logo" href="{$urls.pages.index}">
            {if isset($shop.logo)}
              <img src="{$shop.logo}" alt="{$shop.name}" loading="lazy">
            {/if}
          </a>
        </div>

        <div class="header-right">
          {hook h='displayTop'}
          {hook h='displayNav1'}
          {hook h='displayNav2'}
        </div>

      </div>
    </div>
  </header>
{/block}
