{extends file='page.tpl'}

{block name='page_title'}{l s='Réinitialiser le mot de passe' d='Shop.Theme.Customeraccount'}{/block}

{block name='page_content_container'}
  <section id="content" class="ms-auth">
    <div class="ms-auth__card">

      <h1 class="ms-auth__title">{l s='Nouveau mot de passe' d='Shop.Theme.Customeraccount'}</h1>

      <form action="{$urls.pages.password}" method="post">

        <ul class="ps-alert-error">
          {foreach $errors as $error}
            <li class="item"><i><svg viewBox="0 0 24 24"><path fill="#fff" d="M11,15H13V17H11V15M11,7H13V13H11V7M12,2C6.47,2 2,6.5 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20Z"/></svg></i><p>{$error}</p></li>
          {/foreach}
        </ul>

        <p class="ms-auth__intro">
          {l s='Adresse email : %email%' d='Shop.Theme.Customeraccount' sprintf=['%email%' => $customer_email|stripslashes]}
        </p>

        <div class="form-group">
          <label class="form-control-label" for="passwd">{l s='Nouveau mot de passe' d='Shop.Forms.Labels'}</label>
          <input
            id="passwd"
            class="form-control"
            type="password"
            data-validate="isPasswd"
            name="passwd"
            value=""
            {if isset($configuration.password_policy.minimum_length)}data-minlength="{$configuration.password_policy.minimum_length}"{/if}
            {if isset($configuration.password_policy.maximum_length)}data-maxlength="{$configuration.password_policy.maximum_length}"{/if}
            {if isset($configuration.password_policy.minimum_score)}data-minscore="{$configuration.password_policy.minimum_score}"{/if}
          >
        </div>

        <div class="form-group">
          <label class="form-control-label" for="confirmation">{l s='Confirmation' d='Shop.Forms.Labels'}</label>
          <input id="confirmation" class="form-control" type="password" data-validate="isPasswd" name="confirmation" value="">
        </div>

        <input type="hidden" name="token" id="token" value="{$customer_token}">
        <input type="hidden" name="id_customer" id="id_customer" value="{$id_customer}">
        <input type="hidden" name="reset_token" id="reset_token" value="{$reset_token}">

        <button class="btn btn-primary" type="submit" name="submit">
          {l s='Modifier le mot de passe' d='Shop.Theme.Actions'}
        </button>

      </form>

      <a href="{$urls.pages.authentication}" class="ms-auth__back">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        <span>{l s='Retour à la connexion' d='Shop.Theme.Actions'}</span>
      </a>

    </div>
  </section>
{/block}
