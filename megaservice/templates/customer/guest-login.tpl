{extends file='page.tpl'}

{block name='page_title'}{l s='Suivi de commande invité' d='Shop.Theme.Customeraccount'}{/block}

{block name='page_content_container'}
  <section id="content" class="ms-auth">
    <div class="ms-auth__card">

      <h1 class="ms-auth__title">{l s='Suivi de commande' d='Shop.Theme.Customeraccount'}</h1>

      <p class="ms-auth__intro">{l s='Pour suivre votre commande, renseignez les informations ci-dessous.' d='Shop.Theme.Customeraccount'}</p>

      <form id="guestOrderTrackingForm" action="{$urls.pages.guest_tracking}" method="get">

        <input type="hidden" name="controller" value="guest-tracking">

        <div class="form-group">
          <label class="form-control-label required" for="order_reference">{l s='Référence de commande' d='Shop.Forms.Labels'}</label>
          <input
            id="order_reference"
            class="form-control"
            name="order_reference"
            type="text"
            size="8"
            value="{if isset($smarty.request.order_reference)}{$smarty.request.order_reference}{/if}"
          >
          <small class="form-control-comment" style="color:#7E7E7E; font-size:12px; margin-top:4px; display:block;">
            {l s='Par exemple : QIIXJXNUI ou QIIXJXNUI#1' d='Shop.Theme.Customeraccount'}
          </small>
        </div>

        <div class="form-group">
          <label class="form-control-label required" for="email">{l s='Email' d='Shop.Forms.Labels'}</label>
          <input
            id="email"
            class="form-control"
            name="email"
            type="email"
            value="{if isset($smarty.request.email)}{$smarty.request.email}{/if}"
          >
        </div>

        <button class="btn btn-primary" type="submit">{l s='Envoyer' d='Shop.Theme.Actions'}</button>

      </form>

    </div>
  </section>
{/block}
