{* Sidebar nav espace client — page_name de PS sert pour l'état actif *}
{assign var='ms_pn' value=$page.page_name|default:''}

<aside class="ms-account__nav" aria-label="{l s='Navigation espace client' d='Shop.Theme.Customeraccount'}">
  <ul>

    <li>
      <a class="ms-account__nav-link{if $ms_pn === 'my-account'} is-active{/if}" href="{$urls.pages.my_account}">
        <span class="ms-account__nav-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
          </svg>
        </span>
        <span>{l s='Tableau de bord' d='Shop.Theme.Customeraccount'}</span>
      </a>
    </li>

    <li>
      <a class="ms-account__nav-link{if $ms_pn === 'identity'} is-active{/if}" href="{$urls.pages.identity}">
        <span class="ms-account__nav-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
          </svg>
        </span>
        <span>{l s='Mes informations' d='Shop.Theme.Customeraccount'}</span>
      </a>
    </li>

    <li>
      <a class="ms-account__nav-link{if $ms_pn === 'addresses' || $ms_pn === 'address'} is-active{/if}" href="{$urls.pages.addresses}">
        <span class="ms-account__nav-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
          </svg>
        </span>
        <span>{l s='Mes adresses' d='Shop.Theme.Customeraccount'}</span>
      </a>
    </li>

    {if !$configuration.is_catalog}
      <li>
        <a class="ms-account__nav-link{if $ms_pn === 'history' || $ms_pn === 'order-detail'} is-active{/if}" href="{$urls.pages.history}">
          <span class="ms-account__nav-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
            </svg>
          </span>
          <span>{l s='Mes commandes' d='Shop.Theme.Customeraccount'}</span>
        </a>
      </li>
    {/if}

    {if !$configuration.is_catalog}
      <li>
        <a class="ms-account__nav-link{if $ms_pn === 'order-slip'} is-active{/if}" href="{$urls.pages.order_slip}">
          <span class="ms-account__nav-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
            </svg>
          </span>
          <span>{l s='Avoirs' d='Shop.Theme.Customeraccount'}</span>
        </a>
      </li>
    {/if}

    {if $configuration.voucher_enabled && !$configuration.is_catalog}
      <li>
        <a class="ms-account__nav-link{if $ms_pn === 'discount'} is-active{/if}" href="{$urls.pages.discount}">
          <span class="ms-account__nav-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>
            </svg>
          </span>
          <span>{l s='Mes bons de réduction' d='Shop.Theme.Customeraccount'}</span>
        </a>
      </li>
    {/if}

    {if $configuration.return_enabled && !$configuration.is_catalog}
      <li>
        <a class="ms-account__nav-link{if $ms_pn === 'order-follow' || $ms_pn === 'order-return'} is-active{/if}" href="{$urls.pages.order_follow}">
          <span class="ms-account__nav-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/>
            </svg>
          </span>
          <span>{l s='Mes retours' d='Shop.Theme.Customeraccount'}</span>
        </a>
      </li>
    {/if}

    <li>
      <a class="ms-account__nav-link ms-account__nav-link--logout" href="{$urls.actions.logout}">
        <span class="ms-account__nav-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
          </svg>
        </span>
        <span>{l s='Déconnexion' d='Shop.Theme.Actions'}</span>
      </a>
    </li>

  </ul>
</aside>
