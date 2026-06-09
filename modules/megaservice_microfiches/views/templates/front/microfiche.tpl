{**
 * PR7 — PDP microfiche : vue éclatée + hotspots cliquables + liste pièces.
 * Maquette : PDP_microfiche. Données : controllers/front/microfiche.php.
 *}
{extends file='layouts/layout-full-width.tpl'}

{block name='content'}
  <section class="ms-pdp">

    <h1 class="ms-pdp__title">{$ms_microfiche.display_name|escape:'html'}</h1>

    {* Bannière modèle courant (inerte — garage = everyparts). *}
    <div class="ms-pdp__model-banner">
      {if $ms_moto.picture}
        <img class="ms-pdp__model-thumb" src="{$ms_moto.picture}" alt="{$ms_moto.nom_fr|escape:'html'}" loading="lazy">
      {/if}
      <div class="ms-pdp__model-info">
        <span class="ms-pdp__model-label">{l s='Catalogue filtré sur' d='Modules.Megaservicemicrofiches.Shop'}</span>
        <strong class="ms-pdp__model-name">{$ms_moto.nom_fr|escape:'html'}</strong>
      </div>
      <a class="ms-pdp__model-back" href="{$ms_moto.url}">{l s='Retour au modèle' d='Modules.Megaservicemicrofiches.Shop'}</a>
    </div>

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
                    <form class="ms-pdp__part-form" method="get" action="{$ms_cart_url}">
                      <input type="hidden" name="controller" value="cart">
                      <input type="hidden" name="add" value="1">
                      <input type="hidden" name="id_product" value="{$h.id_product}">
                      <input type="hidden" name="token" value="{$ms_cart_token}">
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
        <ul class="ms-pdp__related-grid">
          {foreach from=$ms_related item=r}
            <li class="ms-pdp__related-card-wrap">
              <a class="ms-pdp__related-card" href="{$r.url}">
                <span class="ms-pdp__related-img">
                  <img src="{$r.thumb}" alt="{$r.display_name|escape:'html'}" loading="lazy"
                       onerror="this.closest('.ms-pdp__related-img').classList.add('is-broken');this.remove();">
                </span>
                <span class="ms-pdp__related-name">{$r.display_name|escape:'html'}</span>
                <span class="ms-pdp__related-count">{l s='%d pièces' sprintf=[$r.nb_pieces] d='Modules.Megaservicemicrofiches.Shop'}</span>
              </a>
            </li>
          {/foreach}
        </ul>
      </div>
    {/if}

  </section>
{/block}
