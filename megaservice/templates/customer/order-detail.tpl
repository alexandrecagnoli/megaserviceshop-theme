{extends file='customer/page.tpl'}

{block name='page_title'}{l s='Détail de la commande' d='Shop.Theme.Customeraccount'}{/block}

{block name='page_content'}
  <div class="ms-order-detail">

    {* En-tête commande *}
    {block name='order_infos'}
      <div id="order-infos" class="ms-account-card">
        <div class="ms-account-card__head">
          <h2 class="ms-account-card__title">
            {l s='Commande %reference% — %date%' d='Shop.Theme.Customeraccount' sprintf=['%reference%' => $order.details.reference, '%date%' => $order.details.order_date]}
          </h2>
          {if $order.details.reorder_url}
            <a href="{$order.details.reorder_url}" class="ms-account-table__link">{l s='Recommander' d='Shop.Theme.Actions'}</a>
          {/if}
        </div>
        <div class="ms-account-card__body">
          <ul style="margin:0; padding:0; list-style:none; display:flex; flex-direction:column; gap:8px;">
            {if $order.carrier.name}<li><strong>{l s='Transporteur' d='Shop.Theme.Checkout'} :</strong> {$order.carrier.name}</li>{/if}
            <li><strong>{l s='Mode de paiement' d='Shop.Theme.Checkout'} :</strong> {$order.details.payment}</li>
            {if $order.details.invoice_url}
              <li><a class="ms-account-table__link" href="{$order.details.invoice_url}">{l s='Télécharger la facture (PDF)' d='Shop.Theme.Customeraccount'}</a></li>
            {/if}
            {if $order.details.recyclable}<li>{l s='Vous avez accepté l\'emballage recyclé pour cette commande.' d='Shop.Theme.Customeraccount'}</li>{/if}
            {if $order.details.gift_message}
              <li>{l s='Vous avez choisi l\'emballage cadeau.' d='Shop.Theme.Customeraccount'}</li>
              <li><strong>{l s='Message' d='Shop.Theme.Customeraccount'} :</strong> {$order.details.gift_message nofilter}</li>
            {/if}
          </ul>
        </div>
      </div>
    {/block}

    {* Statut historique *}
    {block name='order_history'}
      <section id="order-history" class="ms-account-card">
        <div class="ms-account-card__head"><h2 class="ms-account-card__title">{l s='Suivi du statut' d='Shop.Theme.Customeraccount'}</h2></div>
        <div class="ms-account-card__body" style="padding:0;">
          <table class="ms-account-table">
            <thead><tr><th>{l s='Date' d='Shop.Theme.Global'}</th><th>{l s='Statut' d='Shop.Theme.Global'}</th></tr></thead>
            <tbody>
              {foreach from=$order.history item=state}
                <tr>
                  <td>{$state.history_date}</td>
                  <td><span class="ms-status-pill" style="background-color:{$state.color}">{$state.ostate_name}</span></td>
                </tr>
              {/foreach}
            </tbody>
          </table>
          <div class="ms-account-list" style="padding:16px;">
            {foreach from=$order.history item=state}
              <div class="ms-account-list__item">
                <div class="ms-account-list__row"><span>{$state.history_date}</span><span class="ms-status-pill" style="background-color:{$state.color}">{$state.ostate_name}</span></div>
              </div>
            {/foreach}
          </div>
        </div>
      </section>
    {/block}

    {if $order.follow_up}
      <div class="ms-account-card">
        <div class="ms-account-card__body">
          <p style="margin:0 0 8px;">{l s='Cliquez sur le lien suivant pour suivre la livraison de votre commande' d='Shop.Theme.Customeraccount'}</p>
          <a class="ms-account-table__link" href="{$order.follow_up}">{$order.follow_up}</a>
        </div>
      </div>
    {/if}

    {* Adresses *}
    {block name='addresses'}
      <div class="ms-order-detail__addresses">
        {if $order.addresses.delivery}
          <article id="delivery-address" class="ms-account-card">
            <div class="ms-account-card__head"><h2 class="ms-account-card__title">{l s='Adresse de livraison %alias%' d='Shop.Theme.Checkout' sprintf=['%alias%' => $order.addresses.delivery.alias]}</h2></div>
            <div class="ms-account-card__body"><address style="margin:0; font-style:normal; line-height:1.5;">{$order.addresses.delivery.formatted nofilter}</address></div>
          </article>
        {/if}
        <article id="invoice-address" class="ms-account-card">
          <div class="ms-account-card__head"><h2 class="ms-account-card__title">{l s='Adresse de facturation %alias%' d='Shop.Theme.Checkout' sprintf=['%alias%' => $order.addresses.invoice.alias]}</h2></div>
          <div class="ms-account-card__body"><address style="margin:0; font-style:normal; line-height:1.5;">{$order.addresses.invoice.formatted nofilter}</address></div>
        </article>
      </div>
    {/block}

    {$HOOK_DISPLAYORDERDETAIL nofilter}

    {block name='order_detail'}
      {if $order.details.is_returnable && !$orderIsVirtual}
        {include file='customer/_partials/order-detail-return.tpl'}
      {else}
        {include file='customer/_partials/order-detail-no-return.tpl'}
      {/if}
    {/block}

    {block name='order_carriers'}
      {if $order.shipping}
        <div class="ms-account-card">
          <div class="ms-account-card__head"><h2 class="ms-account-card__title">{l s='Livraison' d='Shop.Theme.Checkout'}</h2></div>
          <div class="ms-account-card__body" style="padding:0;">
            <table class="ms-account-table">
              <thead>
                <tr>
                  <th>{l s='Date' d='Shop.Theme.Global'}</th>
                  <th>{l s='Transporteur' d='Shop.Theme.Checkout'}</th>
                  <th>{l s='Poids' d='Shop.Theme.Checkout'}</th>
                  <th>{l s='Coût' d='Shop.Theme.Checkout'}</th>
                  <th>{l s='Suivi' d='Shop.Theme.Checkout'}</th>
                </tr>
              </thead>
              <tbody>
                {foreach from=$order.shipping item=line}
                  <tr><td>{$line.shipping_date}</td><td>{$line.carrier_name}</td><td>{$line.shipping_weight}</td><td>{$line.shipping_cost}</td><td>{$line.tracking nofilter}</td></tr>
                {/foreach}
              </tbody>
            </table>
            <div class="ms-account-list" style="padding:16px;">
              {foreach from=$order.shipping item=line}
                <div class="ms-account-list__item">
                  <div class="ms-account-list__row"><span>{l s='Date' d='Shop.Theme.Global'}</span><span>{$line.shipping_date}</span></div>
                  <div class="ms-account-list__row"><span>{l s='Transporteur' d='Shop.Theme.Checkout'}</span><span>{$line.carrier_name}</span></div>
                  <div class="ms-account-list__row"><span>{l s='Poids' d='Shop.Theme.Checkout'}</span><span>{$line.shipping_weight}</span></div>
                  <div class="ms-account-list__row"><span>{l s='Coût' d='Shop.Theme.Checkout'}</span><span>{$line.shipping_cost}</span></div>
                  <div class="ms-account-list__row"><span>{l s='Suivi' d='Shop.Theme.Checkout'}</span><span>{$line.tracking nofilter}</span></div>
                </div>
              {/foreach}
            </div>
          </div>
        </div>
      {/if}
    {/block}

    {block name='order_messages'}
      <div class="ms-order-detail__messages">
        {include file='customer/_partials/order-messages.tpl'}
      </div>
    {/block}

  </div>
{/block}
