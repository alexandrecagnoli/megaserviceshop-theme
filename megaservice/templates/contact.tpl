{extends file='page.tpl'}

{block name='page_header_container'}{/block}

{block name='page_content_container'}
  <section id="content" class="ms-contact">

    <h1 class="ms-contact__title">{l s='Nous contacter' d='Shop.Theme.Customeraccount'}</h1>
    <p class="ms-contact__intro">{l s='Une question, un conseil, un suivi de commande ? Notre équipe vous répond du lundi au samedi.' d='Shop.Theme.Customeraccount'}</p>

    <div class="ms-contact__layout">

      {* ── Colonne gauche : formulaire ── *}
      <div class="ms-contact__main">
        {block name='page_content'}
          {hook h='displayContactContent'}
        {/block}
      </div>

      {* ── Colonne droite : infos contact ── *}
      <aside class="ms-contact__aside">

        <div class="ms-contact-info">
          <h2 class="ms-contact-info__title">{l s='Service client' d='Shop.Theme.Global'}</h2>

          <a href="tel:0134864926" class="ms-contact-info__row ms-contact-info__row--link">
            <span class="ms-contact-info__icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
              </svg>
            </span>
            <span class="ms-contact-info__text">
              <small>{l s='Appelez-nous' d='Shop.Theme.Global'}</small>
              <strong>01 34 86 49 26</strong>
            </span>
          </a>

          <div class="ms-contact-info__row">
            <span class="ms-contact-info__icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
              </svg>
            </span>
            <span class="ms-contact-info__text">
              <small>{l s='Horaires' d='Shop.Theme.Global'}</small>
              <strong>{l s='Lun. — Sam.  9h — 18h' d='Shop.Theme.Global'}</strong>
            </span>
          </div>

          {if isset($shop.email) && $shop.email}
            <a href="mailto:{$shop.email}" class="ms-contact-info__row ms-contact-info__row--link">
              <span class="ms-contact-info__icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                </svg>
              </span>
              <span class="ms-contact-info__text">
                <small>{l s='Email' d='Shop.Theme.Global'}</small>
                <strong>{$shop.email}</strong>
              </span>
            </a>
          {/if}
        </div>

        {if $layout === 'layouts/layout-left-column.tpl'}
          {block name="left_column"}<div class="ms-contact__hooks">{hook h='displayContactLeftColumn'}</div>{/block}
        {elseif $layout === 'layouts/layout-right-column.tpl'}
          {block name="right_column"}<div class="ms-contact__hooks">{hook h='displayContactRightColumn'}</div>{/block}
        {/if}

      </aside>

    </div>

  </section>
{/block}
