{**
 * ps_searchbar override — Megaservice Theme
 * Renders as icon + label in header
 *}
<div id="search_widget" class="ms-search-widget" data-search-controller-url="{$search_controller_url}">
  <button type="button" class="ms-search-widget__toggle" aria-label="{l s='Rechercher' d='Shop.Theme.Actions'}" aria-expanded="false" aria-controls="ms-search-form">
    <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 30 30" fill="none" aria-hidden="true">
      <path d="M26.25 26.25L20.8125 20.8125M23.75 13.75C23.75 19.2728 19.2728 23.75 13.75 23.75C8.22715 23.75 3.75 19.2728 3.75 13.75C3.75 8.22715 8.22715 3.75 13.75 3.75C19.2728 3.75 23.75 8.22715 23.75 13.75Z" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <span>{l s='Recherche' d='Shop.Theme.Actions'}</span>
  </button>
  <form id="ms-search-form" class="ms-search-widget__form" method="get" action="{$search_controller_url}" role="search" hidden>
    <input type="hidden" name="controller" value="search">
    <input type="text" name="s" value="{$query|escape:'html':'UTF-8'}" placeholder="{l s='Rechercher...' d='Shop.Theme.Actions'}" aria-label="{l s='Rechercher' d='Shop.Theme.Actions'}" autofocus>
    <button type="submit" aria-label="{l s='Valider' d='Shop.Theme.Actions'}">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
    </button>
  </form>
</div>
