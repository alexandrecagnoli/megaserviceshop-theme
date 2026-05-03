{extends file='customer/page.tpl'}

{block name='page_title'}{l s='Mes retours' d='Shop.Theme.Customeraccount'}{/block}

{block name='page_content'}
  {if $ordersReturn && count($ordersReturn)}
    <table class="ms-account-table">
      <thead>
        <tr>
          <th>{l s='Commande' d='Shop.Theme.Customeraccount'}</th>
          <th>{l s='Retour' d='Shop.Theme.Customeraccount'}</th>
          <th>{l s='Statut colis' d='Shop.Theme.Customeraccount'}</th>
          <th>{l s='Date' d='Shop.Theme.Customeraccount'}</th>
          <th>{l s='Formulaire' d='Shop.Theme.Customeraccount'}</th>
        </tr>
      </thead>
      <tbody>
        {foreach from=$ordersReturn item=return}
          <tr>
            <td><a class="ms-account-table__link" href="{$return.details_url}">{$return.reference}</a></td>
            <td><a class="ms-account-table__link" href="{$return.return_url}">{$return.return_number}</a></td>
            <td>{$return.state_name}</td>
            <td>{$return.return_date}</td>
            <td>
              {if $return.print_url}
                <a class="ms-account-table__link" href="{$return.print_url}">{l s='Imprimer' d='Shop.Theme.Actions'}</a>
              {else}-{/if}
            </td>
          </tr>
        {/foreach}
      </tbody>
    </table>

    <div class="ms-account-list">
      {foreach from=$ordersReturn item=return}
        <article class="ms-account-list__item">
          <div class="ms-account-list__row"><a class="ms-account-table__link" href="{$return.details_url}"><strong>{$return.reference}</strong></a><span>{$return.return_date}</span></div>
          <div class="ms-account-list__row"><span>{l s='Retour' d='Shop.Theme.Customeraccount'}</span><a class="ms-account-table__link" href="{$return.return_url}">{$return.return_number}</a></div>
          <div class="ms-account-list__row"><span>{l s='Statut' d='Shop.Theme.Customeraccount'}</span><span>{$return.state_name}</span></div>
          {if $return.print_url}<div class="ms-account-list__row"><a class="ms-account-table__link" href="{$return.print_url}">{l s='Imprimer le formulaire' d='Shop.Theme.Actions'}</a></div>{/if}
        </article>
      {/foreach}
    </div>
  {else}
    <div class="ms-account-empty">{l s='Vous n\'avez aucune autorisation de retour.' d='Shop.Theme.Customeraccount'}</div>
  {/if}
{/block}
