{extends 'customer/page.tpl'}

{block name='page_title'}{l s='Mes informations personnelles' d='Shop.Theme.Customeraccount'}{/block}

{block name='page_content'}
  <div class="ms-account-card">
    <div class="ms-account-card__body">
      {render file='customer/_partials/customer-form.tpl' ui=$customer_form}
    </div>
  </div>
{/block}
