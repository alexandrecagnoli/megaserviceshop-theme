{**
 * Phase 1 — Page "hub moto" (/motos/{id}-{slug}, sans partie).
 * Maquette composite : hero + bannière modèle, 3 accès (Cycle / Moteur /
 * Powerparts), bloc "Dernières microfiches". Données : front/moto.php::initHub.
 * Phase 2 (intégration ukooparts) : bloc "Accessoires Powerparts" + cible de
 * la carte Powerparts + "Récemment consultés".
 *}
{extends file='layouts/layout-full-width.tpl'}

{block name='left_column'}{/block}

{block name='content'}

  {* ── Hero noir + bannière modèle (chrome catégorie réutilisé) ── *}
  <div class="ms-catalog-hero ms-catalog-hero--with-context">
    <div class="ms-catalog-hero__inner">
      <h1 class="ms-catalog-hero__title">{l s='Pièces d\'origine' d='Modules.Megaservicemicrofiches.Shop'} — {$ms_moto.marque} {$ms_moto.core_name} {$ms_moto.annee}</h1>
    </div>

    <div class="ms-catalog-context">
      <img src="{$urls.theme_assets}img/moto-context.png" alt="{$ms_moto.nom_fr|escape:'html'}" class="ms-catalog-context__moto-img" loading="lazy">
      <div class="ms-catalog-context__inner">
        <div class="ms-catalog-context__text">
          <span class="ms-catalog-context__label">{l s='Catalogue filtré sur' d='Modules.Megaservicemicrofiches.Shop'}</span>
          {* Rendu serveur : ANNÉE + MODÈLE (pas de js-model-current-name, cf. moto.tpl). *}
          <strong class="ms-catalog-context__moto-name">{$ms_moto.annee} {$ms_moto.core_name|escape:'html'}</strong>
        </div>
        <div class="ms-catalog-context__actions">
          <a href="#" class="ms-catalog-context__link js-model-trigger">{l s='Changer de modèle' d='Modules.Megaservicemicrofiches.Shop'}</a>
          <a href="#" class="ms-catalog-context__link">{l s='Ajouter à mon garage' d='Modules.Megaservicemicrofiches.Shop'}</a>
        </div>
      </div>
    </div>
  </div>

  <div class="ms-hub">

    {* ── 3 accès : Cycle / Moteur / Powerparts ── *}
    <div class="ms-hub__access">
      <a href="{$ms_cycle_url}" class="ms-hub__card ms-hub__card--cycle">
        <span class="ms-hub__card-label">{l s='Partie cycle' d='Modules.Megaservicemicrofiches.Shop'}</span>
      </a>
      <a href="{$ms_moteur_url}" class="ms-hub__card ms-hub__card--moteur">
        <span class="ms-hub__card-label">{l s='Partie moteur' d='Modules.Megaservicemicrofiches.Shop'}</span>
      </a>
      <a href="{$ms_powerparts_url}" class="ms-hub__card ms-hub__card--powerparts">
        <span class="ms-hub__card-label">{l s='Powerparts' d='Modules.Megaservicemicrofiches.Shop'}</span>
      </a>
    </div>

    {* ── Dernières microfiches ── *}
    {if $ms_latest}
      <section class="ms-hub__section">
        <header class="ms-hub__section-head">
          <h2 class="ms-hub__section-title">{l s='Dernières microfiches' d='Modules.Megaservicemicrofiches.Shop'}</h2>
          <a href="{$ms_latest_more}" class="ms-hub__section-more">{l s='Voir plus' d='Modules.Megaservicemicrofiches.Shop'}</a>
        </header>
        <div class="ms-hub__grid">
          {foreach from=$ms_latest item=mf}
            <article class="ms-product-card js-product ms-plp-card">
              <a href="{$mf.url}" class="ms-product-card__media">
                {* Miniature générée (taille normalisée) → cards homogènes. Pour
                   l'image entière (tailles variables) : $mf.image_full_url. *}
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
                <h3 class="ms-product-card__name"><a href="{$mf.url}">{$mf.display_name|escape:'html'}</a></h3>
                <div class="ms-plp-card__meta">
                  <span class="ms-plp-card__brand">{$ms_moto.marque}</span>
                  <span class="ms-plp-card__count">{l s='%d pièces' sprintf=[$mf.nb_pieces] d='Modules.Megaservicemicrofiches.Shop'}</span>
                </div>
              </div>
            </article>
          {/foreach}
        </div>
      </section>
    {/if}

    {* ── Accessoires Powerparts ──
       Produits Powerparts FILTRÉS sur la compatibilité de cette moto (module
       montabilité). Miniature produit NATIVE du thème → cartes prix + "Ajouter"
       panier identiques au reste du site. Badge « Compatible » actif via
       ms_show_moto_context (tous les produits ici sont compatibles). *}
    {if $ms_powerparts}
      <section class="ms-hub__section">
        <header class="ms-hub__section-head">
          <h2 class="ms-hub__section-title">{l s='Accessoires Powerparts' d='Modules.Megaservicemicrofiches.Shop'}</h2>
          <a href="{$ms_powerparts_url}" class="ms-hub__section-more">{l s='Voir plus' d='Modules.Megaservicemicrofiches.Shop'}</a>
        </header>
        <div class="ms-hub__grid">
          {foreach from=$ms_powerparts item=product}
            {include file='catalog/_partials/miniatures/product.tpl' product=$product}
          {/foreach}
        </div>
      </section>
    {/if}

    {* ── Phase 2 : "Récemment consultés" (bloc cookie natif) ── *}

  </div>
{/block}
