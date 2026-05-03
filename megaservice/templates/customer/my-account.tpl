{extends file='customer/page.tpl'}

{block name='page_title'}{l s='Mon compte' d='Shop.Theme.Customeraccount'}{/block}

{block name='page_content'}

  <div class="ms-account-tiles">

    <a class="ms-account-tile" id="identity-link" href="{$urls.pages.identity}">
      <span class="ms-account-tile__icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      </span>
      <span class="ms-account-tile__label">{l s='Mes informations' d='Shop.Theme.Customeraccount'}</span>
    </a>

    {if $customer.addresses|count}
      <a class="ms-account-tile" id="addresses-link" href="{$urls.pages.addresses}">
        <span class="ms-account-tile__icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        </span>
        <span class="ms-account-tile__label">{l s='Mes adresses' d='Shop.Theme.Customeraccount'}</span>
      </a>
    {else}
      <a class="ms-account-tile" id="address-link" href="{$urls.pages.address}">
        <span class="ms-account-tile__icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </span>
        <span class="ms-account-tile__label">{l s='Ajouter une adresse' d='Shop.Theme.Customeraccount'}</span>
      </a>
    {/if}

    {if !$configuration.is_catalog}
      <a class="ms-account-tile" id="history-link" href="{$urls.pages.history}">
        <span class="ms-account-tile__icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </span>
        <span class="ms-account-tile__label">{l s='Mes commandes' d='Shop.Theme.Customeraccount'}</span>
      </a>
    {/if}

    {if !$configuration.is_catalog}
      <a class="ms-account-tile" id="order-slips-link" href="{$urls.pages.order_slip}">
        <span class="ms-account-tile__icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </span>
        <span class="ms-account-tile__label">{l s='Avoirs' d='Shop.Theme.Customeraccount'}</span>
      </a>
    {/if}

    {if $configuration.voucher_enabled && !$configuration.is_catalog}
      <a class="ms-account-tile" id="discounts-link" href="{$urls.pages.discount}">
        <span class="ms-account-tile__icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
        </span>
        <span class="ms-account-tile__label">{l s='Mes bons de réduction' d='Shop.Theme.Customeraccount'}</span>
      </a>
    {/if}

    {if $configuration.return_enabled && !$configuration.is_catalog}
      <a class="ms-account-tile" id="returns-link" href="{$urls.pages.order_follow}">
        <span class="ms-account-tile__icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>
        </span>
        <span class="ms-account-tile__label">{l s='Mes retours' d='Shop.Theme.Customeraccount'}</span>
      </a>
    {/if}

    {block name='display_customer_account'}
      {hook h='displayCustomerAccount'}
    {/block}

  </div>

{/block}
