{**
 * PR6 — PLP moto : grille des microfiches d'une moto + filtre catégorie.
 * Maquette : PLP_CAT_SPAREPARTS. Réutilise le chrome de la page catégorie du
 * thème (.ms-catalog-hero / .ms-catalog-context / .ms-catalog-layout /
 * .ms-product-card / .facet) pour un rendu natif. Données : front/moto.php.
 *}
{extends file='layouts/layout-full-width.tpl'}

{block name='left_column'}{/block}

{block name='content'}

  {* ── Hero noir + bannière modèle (réutilise le chrome catégorie) ── *}
  <div class="ms-catalog-hero ms-catalog-hero--with-context">
    <div class="ms-catalog-hero__inner">
      <h1 class="ms-catalog-hero__title">
        {l s='Pièces détachées compatibles avec' d='Modules.Megaservicemicrofiches.Shop'}
        {$ms_moto.marque} {$ms_moto.core_name}
      </h1>
    </div>

    <div class="ms-catalog-context">
      {if $ms_moto.picture}
        <img src="{$ms_moto.picture}" alt="{$ms_moto.nom_fr|escape:'html'}" class="ms-catalog-context__moto-img" loading="lazy">
      {/if}
      <div class="ms-catalog-context__inner">
        <div class="ms-catalog-context__text">
          <span class="ms-catalog-context__label">{l s='Catalogue filtré sur' d='Modules.Megaservicemicrofiches.Shop'}</span>
          <strong class="ms-catalog-context__moto-name">{$ms_moto.nom_fr|escape:'html'}</strong>
        </div>
        <div class="ms-catalog-context__actions">
          <a href="#" class="ms-catalog-context__link js-model-trigger">{l s='Changer de modèle' d='Modules.Megaservicemicrofiches.Shop'}</a>
        </div>
      </div>
    </div>
  </div>

  <div class="ms-catalog-layout">

    {* ── Colonne principale : toolbar + grille ── *}
    <div class="ms-catalog__main">
      <section id="products">
        {if $ms_total > 0}

          <div class="ms-catalog-topbar">
            <p class="ms-catalog-topbar__count js-ms-plp-count">
              {l s='Affichage de %d résultats' sprintf=[$ms_total] d='Modules.Megaservicemicrofiches.Shop'}
            </p>
            <div class="ms-catalog-topbar__right">
              <button type="button" class="ms-catalog-topbar__filter-btn js-search-toggler">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                  <path d="M2 4h12M4 8h8M6 12h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                {l s='Filtrer' d='Modules.Megaservicemicrofiches.Shop'}
              </button>
            </div>
          </div>

          <div class="products js-ms-plp-grid">
            {foreach from=$ms_microfiches item=mf}
              <article class="ms-product-card js-product ms-plp-card" data-categorie="{$mf.id_categorie}">
                <a href="{$mf.url}" class="ms-product-card__media">
                  <img src="{$mf.thumb}" alt="{$mf.display_name|escape:'html'}" loading="lazy"
                       onerror="this.closest('.ms-product-card__media').classList.add('is-broken');this.remove();">
                  <div class="ms-product-card__overlay">
                    <span class="ms-product-card__overlay-btn">{l s='VOIR LA MICROFICHE' d='Modules.Megaservicemicrofiches.Shop'}</span>
                  </div>
                  <span class="ms-product-card__compat" aria-hidden="true">
                    {l s='Compatible' d='Modules.Megaservicemicrofiches.Shop'}
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none">
                      <path d="M5 12L10 17L20 7" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </span>
                </a>
                <div class="ms-product-card__body">
                  <h2 class="ms-product-card__name">
                    <a href="{$mf.url}">{$mf.display_name|escape:'html'}</a>
                  </h2>
                  <div class="ms-plp-card__meta">
                    <span class="ms-plp-card__brand">{$ms_moto.marque}</span>
                    <span class="ms-plp-card__count">{l s='%d pièces' sprintf=[$mf.nb_pieces] d='Modules.Megaservicemicrofiches.Shop'}</span>
                  </div>
                </div>
              </article>
            {/foreach}
          </div>

          <p class="ms-plp-empty js-ms-plp-empty" hidden>
            {l s='Aucune microfiche pour les catégories sélectionnées.' d='Modules.Megaservicemicrofiches.Shop'}
          </p>

        {else}
          <p class="ms-plp-empty">
            {l s='Aucune microfiche disponible pour ce modèle pour le moment.' d='Modules.Megaservicemicrofiches.Shop'}
          </p>
        {/if}
      </section>
    </div>

    {* ── Sidebar filtre catégorie (réutilise .ms-catalog__sidebar / .facet) ── *}
    <div class="ms-filters-overlay js-filters-overlay" hidden aria-hidden="true"></div>

    <aside class="ms-catalog__sidebar js-filters-sheet">
      <header class="ms-catalog__sidebar-head">
        <h2 class="ms-catalog__sidebar-title">{l s='Filtres' d='Modules.Megaservicemicrofiches.Shop'}</h2>
        <button type="button" class="ms-catalog__sidebar-close js-filters-close" aria-label="{l s='Fermer' d='Modules.Megaservicemicrofiches.Shop'}">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </header>

      <div id="js-search-filters-wrapper">
        <div id="search_filters">
          <section class="facet clearfix">
            <p class="h6 facet-title">
              {l s='Catégorie' d='Modules.Megaservicemicrofiches.Shop'}
              <button type="button" class="ms-plp-clear js-ms-plp-clear">{l s='Effacer' d='Modules.Megaservicemicrofiches.Shop'}</button>
            </p>
            <ul>
              {foreach from=$ms_categories item=cat}
                <li>
                  <label class="facet-label">
                    <span class="custom-checkbox">
                      <input type="checkbox" class="js-ms-plp-cb" value="{$cat.id_categorie}" checked>
                      <span><i class="material-icons rtl-no-flip checkbox-checked">&#xE5CA;</i></span>
                    </span>
                    <span class="search-link">
                      {$cat.label|escape:'html'}
                      <span class="magnitude">({$cat.count})</span>
                    </span>
                  </label>
                </li>
              {/foreach}
            </ul>
          </section>
        </div>
      </div>

      <footer class="ms-catalog__sidebar-foot">
        <button type="button" class="ms-catalog__sidebar-apply js-filters-apply">{l s='Appliquer les filtres' d='Modules.Megaservicemicrofiches.Shop'}</button>
        <button type="button" class="ms-catalog__sidebar-reset js-ms-plp-reset">{l s='Réinitialiser' d='Modules.Megaservicemicrofiches.Shop'}</button>
      </footer>
    </aside>

  </div>
{/block}
