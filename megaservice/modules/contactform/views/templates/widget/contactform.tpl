{*
 * Override Megaservice Theme — contactform module.
 * Mêmes name fields que le template d'origine (id_contact, from, id_order,
 * fileUpload, message, url, token, submitMessage) pour préserver le handler PS.
 *}
<section class="ms-contact-form login-form">
  <form action="{$urls.pages.contact|escape:'htmlall':'UTF-8'}" method="post" {if $contact.allow_file_upload}enctype="multipart/form-data"{/if}>

    <header class="ms-contact-form__head">
      <h2 class="ms-contact-form__title">{l s='Envoyer un message' d='Modules.Contactform.Shop'}</h2>
      <p class="ms-contact-form__intro">{l s='Renseignez le formulaire ci-dessous et nous reviendrons vers vous dans les meilleurs délais.' d='Modules.Contactform.Shop'}</p>
    </header>

    {if $notifications}
      <div class="ms-contact-form__notif {if $notifications.nw_error}ms-contact-form__notif--error{else}ms-contact-form__notif--success{/if}">
        <ul>
          {foreach $notifications.messages as $notif}
            <li>{$notif|escape:'htmlall':'UTF-8'}</li>
          {/foreach}
        </ul>
      </div>
    {/if}

    {if !$notifications || $notifications.nw_error}

      <div class="ms-contact-form__fields form-fields">

        <div class="form-group">
          <label for="ms-contact-subject" class="form-control-label">{l s='Objet' d='Modules.Contactform.Shop'}</label>
          <select id="ms-contact-subject" name="id_contact" class="form-control">
            {foreach from=$contact.contacts item=contact_elt}
              <option value="{$contact_elt.id_contact|escape:'htmlall':'UTF-8'}">{$contact_elt.name}</option>
            {/foreach}
          </select>
        </div>

        <div class="form-group">
          <label for="ms-contact-email" class="form-control-label">{l s='Adresse e-mail' d='Modules.Contactform.Shop'}</label>
          <input id="ms-contact-email" type="email" name="from" class="form-control" value="{$contact.email|escape:'htmlall':'UTF-8'}" required>
        </div>

        {if $contact.orders}
          <div class="form-group">
            <label for="ms-contact-order" class="form-control-label">{l s='Référence de commande' d='Modules.Contactform.Shop'}</label>
            <select id="ms-contact-order" name="id_order" class="form-control">
              <option value="">{l s='Sélectionner une référence' d='Modules.Contactform.Shop'}</option>
              {foreach from=$contact.orders item=order}
                <option value="{$order.id_order|escape:'htmlall':'UTF-8'}">{$order.reference|escape:'htmlall':'UTF-8'}</option>
              {/foreach}
            </select>
          </div>
        {/if}

        {if $contact.allow_file_upload}
          <div class="form-group">
            <label for="ms-contact-file" class="form-control-label">{l s='Pièce jointe' d='Modules.Contactform.Shop'}</label>
            <input id="ms-contact-file" type="file" name="fileUpload" class="form-control">
          </div>
        {/if}

        <div class="form-group">
          <label for="ms-contact-message" class="form-control-label">{l s='Message' d='Modules.Contactform.Shop'}</label>
          <textarea id="ms-contact-message" name="message" class="form-control" rows="6" required>{if $contact.message}{$contact.message|escape:'htmlall':'UTF-8'}{/if}</textarea>
        </div>

        {hook h='displayGDPRConsent' id_module=$id_module}

      </div>

      <footer class="ms-contact-form__foot">
        {* Honeypot anti-bot — caché *}
        <input type="text" name="url" value="" tabindex="-1" autocomplete="off" style="position:absolute; left:-9999px;">
        <input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}">
        <button type="submit" name="submitMessage" class="ms-contact-form__submit">
          <span>{l s='Envoyer le message' d='Modules.Contactform.Shop'}</span>
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="14" viewBox="0 0 24 16" fill="none" aria-hidden="true">
            <path d="M1 8H23M23 8L16 1M23 8L16 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
      </footer>

    {/if}

  </form>
</section>
