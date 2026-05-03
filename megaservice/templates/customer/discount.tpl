{extends file='customer/page.tpl'}

{block name='page_title'}{l s='Mes bons de réduction' d='Shop.Theme.Customeraccount'}{/block}

{block name='page_content'}
  {if $cart_rules}
    <table class="ms-account-table">
      <thead>
        <tr>
          <th>{l s='Code' d='Shop.Theme.Checkout'}</th>
          <th>{l s='Nom' d='Shop.Theme.Checkout'}</th>
          <th>{l s='Quantité' d='Shop.Theme.Checkout'}</th>
          <th>{l s='Valeur' d='Shop.Theme.Checkout'}</th>
          <th>{l s='Minimum' d='Shop.Theme.Checkout'}</th>
          <th>{l s='Cumulable' d='Shop.Theme.Checkout'}</th>
          <th>{l s='Expiration' d='Shop.Theme.Checkout'}</th>
        </tr>
      </thead>
      <tbody>
        {foreach from=$cart_rules item=cart_rule}
          <tr>
            <th scope="row"><strong>{$cart_rule.code}</strong></th>
            <td>{$cart_rule.name}</td>
            <td>{$cart_rule.quantity_for_user}</td>
            <td>{$cart_rule.value}</td>
            <td>{$cart_rule.voucher_minimal}</td>
            <td>{$cart_rule.voucher_cumulable}</td>
            <td>{$cart_rule.voucher_date}</td>
          </tr>
        {/foreach}
      </tbody>
    </table>

    <div class="ms-account-list">
      {foreach from=$cart_rules item=cart_rule}
        <article class="ms-account-list__item">
          <div class="ms-account-list__row"><strong>{$cart_rule.code}</strong><span>{$cart_rule.value}</span></div>
          <div class="ms-account-list__row"><span>{l s='Nom' d='Shop.Theme.Checkout'}</span><span>{$cart_rule.name}</span></div>
          <div class="ms-account-list__row"><span>{l s='Quantité' d='Shop.Theme.Checkout'}</span><span>{$cart_rule.quantity_for_user}</span></div>
          <div class="ms-account-list__row"><span>{l s='Minimum' d='Shop.Theme.Checkout'}</span><span>{$cart_rule.voucher_minimal}</span></div>
          <div class="ms-account-list__row"><span>{l s='Cumulable' d='Shop.Theme.Checkout'}</span><span>{$cart_rule.voucher_cumulable}</span></div>
          <div class="ms-account-list__row"><span>{l s='Expiration' d='Shop.Theme.Checkout'}</span><span>{$cart_rule.voucher_date}</span></div>
        </article>
      {/foreach}
    </div>
  {else}
    <div class="ms-account-empty">{l s='Vous n\'avez pas de bons de réduction.' d='Shop.Theme.Customeraccount'}</div>
  {/if}
{/block}
