{extends file='page.tpl'}

{block name='page_title'}{l s='Mot de passe oublié ?' d='Shop.Theme.Customeraccount'}{/block}
{block name='page_header_container'}{/block}

{block name='page_content_container'}
  <section id="content" class="ms-auth">
    <div class="ms-auth__card">

      <h1 class="ms-auth__title">{l s='Email envoyé' d='Shop.Theme.Customeraccount'}</h1>

      <ul class="ps-alert-success">
        {foreach $successes as $success}
          <li class="item">
            <i><svg viewBox="0 0 24 24"><path fill="#fff" d="M21,7L9,19L3.5,13.5L4.91,12.09L9,16.17L19.59,5.59L21,7Z"/></svg></i>
            <p>{$success}</p>
          </li>
        {/foreach}
      </ul>

      <a href="{$urls.pages.authentication}" class="ms-auth__back">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        <span>{l s='Retour à la connexion' d='Shop.Theme.Actions'}</span>
      </a>

    </div>
  </section>
{/block}
