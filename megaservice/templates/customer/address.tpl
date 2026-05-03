{extends file='customer/page.tpl'}

{block name='page_title'}
  {if $editing}{l s='Modifier mon adresse' d='Shop.Theme.Customeraccount'}{else}{l s='Nouvelle adresse' d='Shop.Theme.Customeraccount'}{/if}
{/block}

{block name='page_content'}
  <div class="ms-account-card">
    <div class="ms-account-card__body address-form">
      {render template="customer/_partials/address-form.tpl" ui=$address_form}
    </div>
  </div>
{/block}
