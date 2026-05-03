{extends file=$layout}

{block name='header'}
  {include file='checkout/_partials/header.tpl'}
{/block}

{block name='content'}
  <section id="content" class="ms-checkout">

    <h1 class="ms-checkout__title">{l s='Validation de la commande' d='Shop.Theme.Checkout'}</h1>

    <div class="ms-checkout__layout">

      <div class="ms-checkout__main cart-grid-body">
        {block name='checkout_process'}
          {render file='checkout/checkout-process.tpl' ui=$checkout_process}
        {/block}
      </div>

      <aside class="ms-checkout__aside cart-grid-right">
        {block name='cart_summary'}
          {include file='checkout/_partials/cart-summary.tpl' cart=$cart}
        {/block}
        {hook h='displayReassurance'}
      </aside>

    </div>

  </section>
{/block}

{block name='footer'}
  {include file='checkout/_partials/footer.tpl'}
{/block}
