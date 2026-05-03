{extends file='page.tpl'}

{block name='notifications'}{/block}

{* On supprime le header.page-header par défaut — le titre est rendu dans le layout custom *}
{block name='page_header_container'}{/block}

{block name='page_content_container'}
  <section id="content" class="ms-account">

    {block name='page_title_block'}
      <h1 class="ms-account__title">{block name='page_title'}{/block}</h1>
    {/block}

    {block name='page_content_top'}
      {block name='customer_notifications'}
        {include file='_partials/notifications.tpl'}
      {/block}
    {/block}

    <div class="ms-account__layout">

      {* ── Sidebar nav ── *}
      {include file='customer/_partials/account-nav.tpl'}

      {* ── Main ── *}
      <div class="ms-account__main">
        {block name='page_content'}{/block}
      </div>

    </div>

  </section>
{/block}

{block name='page_footer'}{/block}
