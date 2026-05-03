{extends file='page.tpl'}

{block name='page_title'}{l s='Créer un compte' d='Shop.Theme.Customeraccount'}{/block}
{block name='page_header_container'}{/block}

{block name='page_content_container'}
  <section id="content" class="ms-auth">
    <div class="ms-auth__card" style="max-width:640px;">

      <h1 class="ms-auth__title">{l s='Créer un compte' d='Shop.Theme.Customeraccount'}</h1>

      {block name='register_form_container'}
        {$hook_create_account_top nofilter}
        <section class="register-form">
          {render file='customer/_partials/customer-form.tpl' ui=$register_form}
        </section>

        <p class="ms-auth__alt">
          {l s='Déjà un compte ?' d='Shop.Theme.Customeraccount'}
          <a href="{$urls.pages.authentication}">{l s='Se connecter' d='Shop.Theme.Customeraccount'}</a>
        </p>
      {/block}

    </div>
  </section>
{/block}
