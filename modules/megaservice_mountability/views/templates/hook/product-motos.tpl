{**
 * Bloc « Compatible avec » — motos compatibles groupées par modèle.
 * Une ligne = un modèle (marque + core_name + type) ; années cliquables → hub.
 * Recherche client-side (js-ms-mount-search) + zébrage recalculé au filtrage.
 * Masqué si aucune moto résolue (géré côté hook).
 *}
<section class="ms-mount">
  <h2 class="ms-mount__title">{l s='Compatible avec' mod='megaservice_mountability'}</h2>

  <div class="ms-mount__search">
    <div class="ms-mount__search-field">
      <input type="text"
             class="ms-mount__input js-ms-mount-search"
             placeholder="{l s='Rechercher votre moto dans la liste…' mod='megaservice_mountability'}"
             aria-label="{l s='Rechercher une moto compatible' mod='megaservice_mountability'}"
             autocomplete="off">
      <span class="ms-mount__search-icon" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M10 18C11.846 18 13.543 17.365 14.897 16.312L19.293 20.708L20.707 19.294L16.311 14.898C17.365 13.543 18 11.846 18 10C18 5.589 14.411 2 10 2C5.589 2 2 5.589 2 10C2 14.411 5.589 18 10 18ZM10 4C13.309 4 16 6.691 16 10C16 13.309 13.309 16 10 16C6.691 16 4 13.309 4 10C4 6.691 6.691 4 10 4Z" fill="#484848"/></svg>
      </span>
    </div>
  </div>

  <div class="ms-mount__table js-ms-mount-list">
    {foreach from=$ms_mount_groups item=g}
      <div class="ms-mount__row" data-search="{$g.search|escape:'htmlall':'UTF-8'}">
        <span class="ms-mount__model">{$g.model|escape:'htmlall':'UTF-8'}</span>
        <span class="ms-mount__type">{$g.type|escape:'htmlall':'UTF-8'}</span>
        <span class="ms-mount__years">{foreach from=$g.years item=y name=yrs}<a class="ms-mount__year" href="{$y.url|escape:'htmlall':'UTF-8'}">{$y.annee}</a>{if !$smarty.foreach.yrs.last}, {/if}{/foreach}</span>
      </div>
    {/foreach}
    <p class="ms-mount__empty js-ms-mount-empty" hidden>{l s='Aucune moto ne correspond à votre recherche.' mod='megaservice_mountability'}</p>
  </div>
</section>
