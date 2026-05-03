{extends file=$layout}

{block name='content'}
  <section id="main" class="ms-cart">

    <h1 class="ms-cart__title">{l s='Mon Panier' d='Shop.Theme.Checkout'}</h1>

    <div class="ms-cart__layout">

      {* ── Colonne gauche : produits ── *}
      <div class="ms-cart__main">

        <div class="ms-cart__list cart-container">
          {block name='cart_overview'}
            {include file='checkout/_partials/cart-detailed.tpl' cart=$cart}
          {/block}
        </div>

        {block name='continue_shopping'}
          <a class="ms-cart__continue" href="{$urls.pages.index}">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
            </svg>
            <span>{l s='Continuer mes achats' d='Shop.Theme.Actions'}</span>
          </a>
        {/block}

        {block name='hook_shopping_cart_footer'}
          {hook h='displayShoppingCartFooter'}
        {/block}

      </div>

      {* ── Colonne droite : récap ── *}
      <aside class="ms-cart__aside">

        {block name='cart_summary'}
          <div class="ms-cart__summary">

            <h2 class="ms-cart__summary-title">{l s='Récapitulatif' d='Shop.Theme.Checkout'}</h2>

            {block name='hook_shopping_cart'}
              {hook h='displayShoppingCart'}
            {/block}

            {block name='cart_totals'}
              {include file='checkout/_partials/cart-detailed-totals.tpl' cart=$cart}
            {/block}

            {block name='cart_actions'}
              {include file='checkout/_partials/cart-detailed-actions.tpl' cart=$cart}
            {/block}

          </div>
        {/block}

        {block name='hook_reassurance'}
          {hook h='displayReassurance'}
        {/block}

      </aside>

    </div>

    {hook h='displayCrossSellingShoppingCart'}

  </section>
{/block}
