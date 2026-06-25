{**
 * PR7 — PDP microfiche : vue éclatée + hotspots cliquables + liste pièces.
 * Maquette : PDP_microfiche. Données : controllers/front/microfiche.php.
 *}
{extends file='layouts/layout-full-width.tpl'}

{block name='content'}

  {* Hero noir + bannière modèle : MÊME chrome natif que la PLP. .ms-catalog-context
     vit DANS le hero (sinon son image moto en position:absolute déborde sur le
     titre). Affiché nativement quand un modèle est choisi (body.has-moto-selected). *}
  <div class="ms-catalog-hero ms-catalog-hero--with-context">
    <div class="ms-catalog-hero__inner">
      <h1 class="ms-catalog-hero__title">{$ms_microfiche.display_name|escape:'html'}</h1>
    </div>

    <div class="ms-catalog-context">
      {* Image générique (comme le bloc natif) — vraies photos via PR-Visuels. *}
      <img src="{$urls.theme_assets}img/moto-context.png" alt="{$ms_moto.nom_fr|escape:'html'}" class="ms-catalog-context__moto-img" loading="lazy">
      <div class="ms-catalog-context__inner">
        <div class="ms-catalog-context__text">
          <span class="ms-catalog-context__label">{l s='Catalogue filtré sur' d='Modules.Megaservicemicrofiches.Shop'}</span>
          <strong class="ms-catalog-context__moto-name js-model-current-name">{$ms_moto.nom_fr|escape:'html'}</strong>
        </div>
        <div class="ms-catalog-context__actions">
          <a href="{$ms_moto.url}" class="ms-catalog-context__link">{l s='Retour au modèle' d='Modules.Megaservicemicrofiches.Shop'}</a>
          <a href="#" class="ms-catalog-context__link js-model-trigger">{l s='Changer de modèle' d='Modules.Megaservicemicrofiches.Shop'}</a>
        </div>
      </div>
    </div>
  </div>

  <section class="ms-pdp">

    <div class="ms-pdp__compat">
      {l s='Compatible avec' d='Modules.Megaservicemicrofiches.Shop'} <strong>{$ms_moto.nom_fr|escape:'html'}</strong>
    </div>

    <div class="ms-pdp__body">

      {* ---------------- Vue éclatée + hotspots overlay ---------------- *}
      <div class="ms-pdp__viewer">
        <div class="ms-pdp__image-wrap">
          <img class="ms-pdp__image" src="{$ms_microfiche.image_url}" alt="{$ms_microfiche.display_name|escape:'html'}"
               onerror="this.closest('.ms-pdp__image-wrap').classList.add('is-broken');this.style.display='none';">
          {foreach from=$ms_hotspots item=h}
            <button type="button" class="ms-pdp__dot{if $h.has_product} is-linked{/if}"
                    style="left:{$h.pos_left|string_format:'%.3f'}%;bottom:{$h.pos_bottom|string_format:'%.3f'}%;"
                    data-hotspot="{$h.id_hotspot}" aria-label="{$h.article_label|escape:'html'}">
              {$h.sequence_number}
            </button>
          {/foreach}
        </div>
        {if $ms_microfiche.image_url}
          <a class="ms-pdp__download" href="{$ms_microfiche.image_url}" target="_blank" rel="noopener" download>
            {l s='Télécharger la microfiche' d='Modules.Megaservicemicrofiches.Shop'}
          </a>
        {/if}
      </div>

      {* ---------------- Liste des pièces ---------------- *}
      <div class="ms-pdp__parts">
        {if $ms_hotspots}
          <ul class="ms-pdp__parts-list">
            {foreach from=$ms_hotspots item=h}
              <li class="ms-pdp__part{if $h.has_product} is-linked{/if}" id="ms-part-{$h.id_hotspot}" data-hotspot="{$h.id_hotspot}">
                <span class="ms-pdp__part-seq">{$h.sequence_number}</span>
                <div class="ms-pdp__part-main">
                  <span class="ms-pdp__part-name">{$h.article_label|escape:'html'}</span>
                  <span class="ms-pdp__part-ref">{l s='Réf' d='Modules.Megaservicemicrofiches.Shop'} : {$h.article_ref|escape:'html'}</span>
                  {if $h.has_product}
                    <span class="ms-pdp__part-stock {if $h.available}is-available{else}is-unavailable{/if}">
                      {if $h.available}{l s='Disponible' d='Modules.Megaservicemicrofiches.Shop'}{else}{l s='Sur commande' d='Modules.Megaservicemicrofiches.Shop'}{/if}
                    </span>
                  {else}
                    <span class="ms-pdp__part-stock is-soon">{l s='Bientôt disponible' d='Modules.Megaservicemicrofiches.Shop'}</span>
                  {/if}
                </div>

                {if $h.has_product}
                  <div class="ms-pdp__part-buy">
                    <span class="ms-pdp__part-price">{$h.price}</span>
                    {* Form AJAX : intercepté par app.js (.js-ajax-add-to-cart) →
                       ajout sans rechargement + ouverture du panneau panier latéral. *}
                    <form class="ms-pdp__part-form js-ajax-add-to-cart" method="post" action="{$h.add_to_cart_url}">
                      <input type="hidden" name="token" value="{$ms_cart_token}">
                      <input type="hidden" name="id_product" value="{$h.id_product}">
                      <input type="hidden" name="id_product_attribute" value="0">
                      <label class="ms-pdp__part-qty">
                        <span class="ms-pdp__part-qty-label">{l s='Qté recommandée' d='Modules.Megaservicemicrofiches.Shop'}</span>
                        <select name="qty">
                          {section name=q start=1 loop=11}
                            <option value="{$smarty.section.q.index}"{if $smarty.section.q.index == $h.qty_recommended} selected{/if}>{$smarty.section.q.index}</option>
                          {/section}
                        </select>
                      </label>
                      <button type="submit" class="ms-pdp__part-add">{l s='Ajouter au panier' d='Modules.Megaservicemicrofiches.Shop'}</button>
                    </form>
                  </div>
                {else}
                  <div class="ms-pdp__part-buy ms-pdp__part-buy--soon">
                    <span class="ms-pdp__part-qty-reco">{l s='Qté recommandée' d='Modules.Megaservicemicrofiches.Shop'} : ×{$h.qty_recommended}</span>
                  </div>
                {/if}
              </li>
            {/foreach}
          </ul>
        {else}
          <p class="ms-pdp__empty">{l s='Aucune pièce référencée sur cette vue.' d='Modules.Megaservicemicrofiches.Shop'}</p>
        {/if}
      </div>

    </div>

    {* ---------------- Microfiches liées (même moto) ---------------- *}
    {if $ms_related}
      <div class="ms-pdp__related">
        <h2 class="ms-pdp__related-title">
          {l s='Pièces d\'origine compatibles avec' d='Modules.Megaservicemicrofiches.Shop'} {$ms_moto.marque} {$ms_moto.core_name}
        </h2>
        <div class="ms-pdp__related-grid">
          {foreach from=$ms_related item=r}
            <article class="ms-product-card js-product ms-plp-card">
              <a href="{$r.url}" class="ms-product-card__media">
                <img src="{$r.thumb}" alt="{$r.display_name|escape:'html'}" loading="lazy"
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
                <h2 class="ms-product-card__name"><a href="{$r.url}">{$r.display_name|escape:'html'}</a></h2>
                <div class="ms-plp-card__meta">
                  <span class="ms-plp-card__count">{l s='%d pièces' sprintf=[$r.nb_pieces] d='Modules.Megaservicemicrofiches.Shop'}</span>
                </div>
              </div>
            </article>
          {/foreach}
        </div>
      </div>
    {/if}

  </section>
{/block}
