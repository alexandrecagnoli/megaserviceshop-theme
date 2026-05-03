{extends file='customer/page.tpl'}

{block name='page_title'}{l s='Mes adresses' d='Shop.Theme.Customeraccount'}{/block}

{block name='page_content'}
  {if $customer.addresses}
    <div class="ms-address-grid">
      {foreach $customer.addresses as $address}
        {block name='customer_address'}
          {include file='customer/_partials/block-address.tpl' address=$address}
        {/block}
      {/foreach}
    </div>
  {else}
    <div class="ms-account-empty">
      {l s='Aucune adresse enregistrée.' d='Shop.Theme.Customeraccount'}
      <a href="{$urls.pages.address}">{l s='Ajouter une adresse' d='Shop.Theme.Actions'}</a>
    </div>
  {/if}

  <a class="ms-account__add-address" href="{$urls.pages.address}" data-link-action="add-address">
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
    </svg>
    <span>{l s='Ajouter une adresse' d='Shop.Theme.Actions'}</span>
  </a>
{/block}
