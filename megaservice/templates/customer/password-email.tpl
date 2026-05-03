{extends file='page.tpl'}

{block name='page_title'}{l s='Mot de passe oublié ?' d='Shop.Theme.Customeraccount'}{/block}

{block name='page_content_container'}
  <section id="content" class="ms-auth">
    <div class="ms-auth__card">

      <h1 class="ms-auth__title">{l s='Mot de passe oublié' d='Shop.Theme.Customeraccount'}</h1>

      <p class="ms-auth__intro">{l s='Saisissez l\'adresse e-mail utilisée pour votre compte. Vous recevrez un lien temporaire pour réinitialiser votre mot de passe.' d='Shop.Theme.Customeraccount'}</p>

      <form action="{$urls.pages.password}" class="forgotten-password" method="post">

        <ul class="ps-alert-error">
          {foreach $errors as $error}
            <li class="item"><i><svg viewBox="0 0 24 24"><path fill="#fff" d="M11,15H13V17H11V15M11,7H13V13H11V7M12,2C6.47,2 2,6.5 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20Z"/></svg></i><p>{$error}</p></li>
          {/foreach}
        </ul>

        <div class="form-group">
          <label class="form-control-label required" for="email">{l s='Adresse e-mail' d='Shop.Forms.Labels'}</label>
          <input type="email" name="email" id="email" value="{if isset($smarty.post.email)}{$smarty.post.email|stripslashes}{/if}" class="form-control" required>
        </div>

        <button id="send-reset-link" class="form-control-submit btn btn-primary" name="submit" type="submit">
          {l s='Envoyer le lien' d='Shop.Theme.Actions'}
        </button>

      </form>

      <a id="back-to-login" href="{$urls.pages.authentication}" class="ms-auth__back">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        <span>{l s='Retour à la connexion' d='Shop.Theme.Actions'}</span>
      </a>

    </div>
  </section>
{/block}
