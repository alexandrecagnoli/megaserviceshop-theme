{extends file='customer/page.tpl'}

{block name='page_title'}{l s='Mes avoirs' d='Shop.Theme.Customeraccount'}{/block}

{block name='page_content'}
  {if $credit_slips}
    <table class="ms-account-table">
      <thead>
        <tr>
          <th>{l s='Commande' d='Shop.Theme.Customeraccount'}</th>
          <th>{l s='N° avoir' d='Shop.Theme.Customeraccount'}</th>
          <th>{l s='Date' d='Shop.Theme.Customeraccount'}</th>
          <th>{l s='Voir' d='Shop.Theme.Customeraccount'}</th>
        </tr>
      </thead>
      <tbody>
        {foreach from=$credit_slips item=slip}
          <tr>
            <td><a class="ms-account-table__link" href="{$slip.order_url_details}" data-link-action="view-order-details">{$slip.order_reference}</a></td>
            <td>{$slip.credit_slip_number}</td>
            <td>{$slip.credit_slip_date}</td>
            <td>
              <a class="ms-account-table__icon-btn" href="{$slip.url}" aria-label="{l s='Voir l\'avoir' d='Shop.Theme.Customeraccount'}">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              </a>
            </td>
          </tr>
        {/foreach}
      </tbody>
    </table>

    <div class="ms-account-list">
      {foreach from=$credit_slips item=slip}
        <article class="ms-account-list__item">
          <div class="ms-account-list__row"><a class="ms-account-table__link" href="{$slip.order_url_details}" data-link-action="view-order-details"><strong>{$slip.order_reference}</strong></a><span>{$slip.credit_slip_date}</span></div>
          <div class="ms-account-list__row"><span>{l s='N° avoir' d='Shop.Theme.Customeraccount'}</span><span>{$slip.credit_slip_number}</span></div>
          <div class="ms-account-list__row"><a class="ms-account-table__link" href="{$slip.url}">{l s='Télécharger' d='Shop.Theme.Customeraccount'}</a></div>
        </article>
      {/foreach}
    </div>
  {else}
    <div class="ms-account-empty">{l s='Vous n\'avez reçu aucun avoir.' d='Shop.Theme.Customeraccount'}</div>
  {/if}
{/block}
