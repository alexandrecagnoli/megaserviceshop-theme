{extends file='page.tpl'}

{block name='page_title'}{l s='Connexion à votre compte' d='Shop.Theme.Customeraccount'}{/block}

{block name='page_content_container'}
  <section id="content" class="ms-auth">
    <div class="ms-auth__card">

      <h1 class="ms-auth__title">{l s='Connexion' d='Shop.Theme.Customeraccount'}</h1>

      {block name='login_form_container'}
        <section class="login-form">
          {render file='customer/_partials/login-form.tpl' ui=$login_form}
        </section>

        {block name='display_after_login_form'}
          {hook h='displayCustomerLoginFormAfter'}
        {/block}

        <p class="ms-auth__alt">
          {l s='Pas encore de compte ?' d='Shop.Theme.Customeraccount'}
          <a href="{$urls.pages.register}" data-link-action="display-register-form">{l s='Créer un compte' d='Shop.Theme.Customeraccount'}</a>
        </p>
      {/block}

    </div>
  </section>
{/block}
