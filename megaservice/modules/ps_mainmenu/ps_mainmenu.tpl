{**
 * ps_mainmenu override — Megaservice Theme
 * Renders sidebar nav with BEM classes
 *}
{function name="ms_menu" nodes=[] depth=0}
  {if $nodes|count}
    <ul class="{if $depth === 0}ms-menu__list{else}ms-menu__submenu{/if}" data-depth="{$depth}">
      {foreach from=$nodes item=node}
        <li class="{if $depth === 0}ms-menu__item{else}ms-menu__subitem{/if}{if $node.current} is-current{/if}">
          <a href="{$node.url}" class="{if $depth === 0}ms-menu__link{else}ms-menu__sublink{/if}"{if $node.children|count} data-toggle="true"{/if}{if $node.open_in_new_window} target="_blank"{/if}>
            {$node.label}
            {if $node.children|count}
              <svg class="ms-menu__chevron" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="6 9 12 15 18 9"/>
              </svg>
            {/if}
          </a>
          {if $node.children|count}
            <div class="ms-menu__submenu-wrap">
              {ms_menu nodes=$node.children depth=$node.depth}
            </div>
          {/if}
        </li>
      {/foreach}
    </ul>
  {/if}
{/function}

{ms_menu nodes=$menu.children}
