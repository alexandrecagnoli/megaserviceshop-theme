{extends file='customer/page.tpl'}

{block name='page_title'}{l s='Historique des commandes' d='Shop.Theme.Customeraccount'}{/block}

{block name='page_content'}
  {if $orders}

    {* Desktop : table *}
    <table class="ms-account-table">
      <thead>
        <tr>
          <th>{l s='Référence' d='Shop.Theme.Checkout'}</th>
          <th>{l s='Date' d='Shop.Theme.Checkout'}</th>
          <th>{l s='Total' d='Shop.Theme.Checkout'}</th>
          <th>{l s='Paiement' d='Shop.Theme.Checkout'}</th>
          <th>{l s='Statut' d='Shop.Theme.Checkout'}</th>
          <th>{l s='Facture' d='Shop.Theme.Checkout'}</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        {foreach from=$orders item=order}
          <tr>
            <th scope="row">{$order.details.reference}</th>
            <td>{$order.details.order_date}</td>
            <td>{$order.totals.total.value}</td>
            <td>{$order.details.payment}</td>
            <td>
              <span class="ms-status-pill {$order.history.current.contrast}" style="background-color:{$order.history.current.color}">
                {$order.history.current.ostate_name}
              </span>
            </td>
            <td>
              {if $order.details.invoice_url}
                <a class="ms-account-table__icon-btn" href="{$order.details.invoice_url}" aria-label="{l s='Télécharger la facture' d='Shop.Theme.Actions'}">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                </a>
              {else}-{/if}
            </td>
            <td>
              <div class="ms-account-table__actions">
                <a class="ms-account-table__link view-order-details-link" href="{$order.details.details_url}" data-link-action="view-order-details">{l s='Détails' d='Shop.Theme.Customeraccount'}</a>
                {if $order.details.reorder_url}
                  <a class="ms-account-table__link reorder-link" href="{$order.details.reorder_url}">{l s='Recommander' d='Shop.Theme.Actions'}</a>
                {/if}
              </div>
            </td>
          </tr>
        {/foreach}
      </tbody>
    </table>

    {* Mobile : liste *}
    <div class="ms-account-list">
      {foreach from=$orders item=order}
        <article class="ms-account-list__item">
          <div class="ms-account-list__row">
            <a class="ms-account-table__link" href="{$order.details.details_url}"><strong>{$order.details.reference}</strong></a>
            <span class="ms-status-pill" style="background-color:{$order.history.current.color}">{$order.history.current.ostate_name}</span>
          </div>
          <div class="ms-account-list__row"><span>{l s='Date' d='Shop.Theme.Global'}</span><span>{$order.details.order_date}</span></div>
          <div class="ms-account-list__row"><span>{l s='Total' d='Shop.Theme.Checkout'}</span><strong>{$order.totals.total.value}</strong></div>
          <div class="ms-account-list__row" style="margin-top:8px;">
            <a class="ms-account-table__link" href="{$order.details.details_url}" data-link-action="view-order-details">{l s='Voir les détails' d='Shop.Theme.Customeraccount'}</a>
            {if $order.details.reorder_url}<a class="ms-account-table__link" href="{$order.details.reorder_url}">{l s='Recommander' d='Shop.Theme.Actions'}</a>{/if}
          </div>
        </article>
      {/foreach}
    </div>

  {else}
    <div class="ms-account-empty">{l s='Vous n\'avez pas encore passé de commande.' d='Shop.Theme.Customeraccount'}</div>
  {/if}
{/block}
