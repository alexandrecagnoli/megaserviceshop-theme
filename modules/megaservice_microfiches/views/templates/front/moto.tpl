{**
 * PR6 — PLP moto : grille des microfiches d'une moto + filtre catégorie.
 * Maquette : PLP_CAT_SPAREPARTS. Données injectées par
 * controllers/front/moto.php (ms_moto, ms_microfiches, ms_categories).
 *}
{extends file='layouts/layout-full-width.tpl'}

{block name='content'}
  <section class="ms-plp">

    <h1 class="ms-plp__title">
      {l s='Pièces détachées compatibles avec' d='Modules.Megaservicemicrofiches.Shop'}
      <span class="ms-plp__title-model">{$ms_moto.marque} {$ms_moto.core_name}</span>
    </h1>

    {* Bannière modèle courant. Les actions "changer de modèle / garage" relèvent
       du sélecteur everyparts — laissées inertes côté microfiches en V1. *}
    <div class="ms-plp__model-banner">
      {if $ms_moto.picture}
        <img class="ms-plp__model-thumb" src="{$ms_moto.picture}" alt="{$ms_moto.nom_fr|escape:'html'}" loading="lazy">
      {/if}
      <div class="ms-plp__model-info">
        <span class="ms-plp__model-label">{l s='Catalogue filtré sur' d='Modules.Megaservicemicrofiches.Shop'}</span>
        <strong class="ms-plp__model-name">{$ms_moto.nom_fr|escape:'html'}</strong>
      </div>
    </div>

    <div class="ms-plp__body">

      {* ---------------- Sidebar filtre catégorie ---------------- *}
      <aside class="ms-plp__sidebar">
        <div class="ms-plp__filter">
          <div class="ms-plp__filter-head">
            <span class="ms-plp__filter-title">{l s='Catégorie' d='Modules.Megaservicemicrofiches.Shop'}</span>
            <button type="button" class="ms-plp__filter-clear js-ms-plp-clear">{l s='Effacer' d='Modules.Megaservicemicrofiches.Shop'}</button>
          </div>
          <ul class="ms-plp__filter-list">
            {foreach from=$ms_categories item=cat}
              <li class="ms-plp__filter-item">
                <label class="ms-plp__filter-label">
                  <span class="ms-plp__filter-text">{$cat.label|escape:'html'}</span>
                  <input type="checkbox" class="ms-plp__filter-cb js-ms-plp-cb"
                         value="{$cat.id_categorie}" checked>
                  <span class="ms-plp__filter-count">{$cat.count}</span>
                </label>
              </li>
            {/foreach}
          </ul>
          <button type="button" class="ms-plp__filter-reset js-ms-plp-reset">{l s='Réinitialiser' d='Modules.Megaservicemicrofiches.Shop'}</button>
        </div>
      </aside>

      {* ---------------- Grille microfiches ---------------- *}
      <div class="ms-plp__main">
        <div class="ms-plp__toolbar">
          <span class="ms-plp__count js-ms-plp-count">
            {l s='Affichage de %d résultats' sprintf=[$ms_total] d='Modules.Megaservicemicrofiches.Shop'}
          </span>
        </div>

        {if $ms_total > 0}
          <ul class="ms-plp__grid js-ms-plp-grid">
            {foreach from=$ms_microfiches item=mf}
              <li class="ms-plp__card-wrap" data-categorie="{$mf.id_categorie}">
                <a class="ms-plp__card" href="{$mf.url}">
                  <span class="ms-plp__badge">{l s='Compatible' d='Modules.Megaservicemicrofiches.Shop'}</span>
                  <span class="ms-plp__card-img">
                    <img src="{$mf.thumb}" alt="{$mf.display_name|escape:'html'}" loading="lazy"
                         onerror="this.closest('.ms-plp__card-img').classList.add('is-broken');this.remove();">
                  </span>
                  <span class="ms-plp__card-body">
                    <span class="ms-plp__card-name">{$mf.display_name|escape:'html'}</span>
                    <span class="ms-plp__card-brand">{$ms_moto.marque}</span>
                    <span class="ms-plp__card-count">{l s='%d pièces' sprintf=[$mf.nb_pieces] d='Modules.Megaservicemicrofiches.Shop'}</span>
                  </span>
                </a>
              </li>
            {/foreach}
          </ul>

          {* État vide après filtrage client (révélé en JS quand tout est décoché). *}
          <p class="ms-plp__empty-filtered js-ms-plp-empty" hidden>
            {l s='Aucune microfiche pour les catégories sélectionnées.' d='Modules.Megaservicemicrofiches.Shop'}
          </p>
        {else}
          <p class="ms-plp__empty">
            {l s='Aucune microfiche disponible pour ce modèle pour le moment.' d='Modules.Megaservicemicrofiches.Shop'}
          </p>
        {/if}
      </div>

    </div>
  </section>
{/block}
