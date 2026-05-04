{*
 * Bloc tabs Powerparts — affiché uniquement pour les produits dans la
 * sous-arborescence Accessoires Powerparts (cf. ProductController override).
 * Données injectées via Smarty :
 *   - $ms_mandatory_products    (pièces nécessaires au montage)
 *   - $ms_excluded_products     (incompatibilités)
 *   - $ms_recommended_products  (suggestions)
 *   - $ms_spare_products        (pièces de rechange — list view)
 *
 * Onglet par défaut : "Pièces recommandées".
 *}
<section class="ms-related-tabs">
  <div class="ms-container">

    {* ── Nav onglets ───────────────────────── *}
    <nav class="ms-related-tabs__nav" role="tablist">
      <button class="ms-related-tabs__btn js-related-tab-btn" type="button" role="tab" data-tab="mandatory">
        {l s='Pièces obligatoires' d='Shop.Theme.Catalog'} <span class="ms-related-tabs__count">({$ms_mandatory_products|count})</span>
      </button>
      <button class="ms-related-tabs__btn js-related-tab-btn" type="button" role="tab" data-tab="excluded">
        {l s='Pièces exclues' d='Shop.Theme.Catalog'} <span class="ms-related-tabs__count">({$ms_excluded_products|count})</span>
      </button>
      <button class="ms-related-tabs__btn js-related-tab-btn is-active" type="button" role="tab" data-tab="recommended">
        {l s='Pièces recommandées' d='Shop.Theme.Catalog'}
      </button>
      <button class="ms-related-tabs__btn js-related-tab-btn" type="button" role="tab" data-tab="spare">
        {l s='Pièces de rechange' d='Shop.Theme.Catalog'}
      </button>
    </nav>

    {* ── Panels ──────────────────────────── *}
    <div class="ms-related-tabs__panels">

      {* ── PIÈCES OBLIGATOIRES ── *}
      <div class="ms-related-tabs__panel js-related-tab-panel" data-panel="mandatory">
        <div class="ms-related-tabs__alert" role="alert">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="10" stroke="#FE6604" stroke-width="2"/>
            <line x1="12" y1="7" x2="12" y2="13" stroke="#FE6604" stroke-width="2" stroke-linecap="round"/>
            <circle cx="12" cy="17" r="1" fill="#FE6604"/>
          </svg>
          <p>{l s='Ces produits sont nécessaires pour le montage de votre article sur votre véhicule. Vérifiez que vous les avez en votre possession ou ajoutez les à votre panier !' d='Shop.Theme.Catalog'}</p>
        </div>
        {if $ms_mandatory_products|count > 0}
          <div class="ms-related-tabs__grid">
            {foreach from=$ms_mandatory_products item="product"}
              {include file='catalog/_partials/miniatures/product.tpl' product=$product productClasses="ms-product-card--related ms-product-card--compatible"}
            {/foreach}
          </div>
        {else}
          <p class="ms-related-tabs__empty">{l s='Aucune pièce obligatoire' d='Shop.Theme.Catalog'}</p>
        {/if}
      </div>

      {* ── PIÈCES EXCLUES ── *}
      <div class="ms-related-tabs__panel js-related-tab-panel" data-panel="excluded">
        <div class="ms-related-tabs__alert" role="alert">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="10" stroke="#FE6604" stroke-width="2"/>
            <line x1="12" y1="7" x2="12" y2="13" stroke="#FE6604" stroke-width="2" stroke-linecap="round"/>
            <circle cx="12" cy="17" r="1" fill="#FE6604"/>
          </svg>
          <p>{l s='Attention : Cet article n\'est pas compatible avec les produits suivants :' d='Shop.Theme.Catalog'}</p>
        </div>
        {if $ms_excluded_products|count > 0}
          <div class="ms-related-tabs__grid">
            {foreach from=$ms_excluded_products item="product"}
              {include file='catalog/_partials/miniatures/product.tpl' product=$product productClasses="ms-product-card--related ms-product-card--excluded"}
            {/foreach}
          </div>
        {else}
          <p class="ms-related-tabs__empty">{l s='Aucune incompatibilité connue' d='Shop.Theme.Catalog'}</p>
        {/if}
      </div>

      {* ── PIÈCES RECOMMANDÉES ── *}
      <div class="ms-related-tabs__panel js-related-tab-panel is-active" data-panel="recommended">
        {if $ms_recommended_products|count > 0}
          <div class="ms-related-tabs__grid">
            {foreach from=$ms_recommended_products item="product"}
              {include file='catalog/_partials/miniatures/product.tpl' product=$product productClasses="ms-product-card--related"}
            {/foreach}
          </div>
        {else}
          <p class="ms-related-tabs__empty">{l s='Aucune recommandation' d='Shop.Theme.Catalog'}</p>
        {/if}
      </div>

      {* ── PIÈCES DE RECHANGE (list view) ── *}
      <div class="ms-related-tabs__panel js-related-tab-panel" data-panel="spare">
        {if $ms_spare_products|count > 0}
          <ul class="ms-spare-list">
            {foreach from=$ms_spare_products item="part"}
              <li class="ms-spare-list__row">
                <span class="ms-spare-list__name">{$part.name|escape:'html':'UTF-8'}</span>
                <span class="ms-spare-list__ref">{l s='Référence' d='Shop.Theme.Catalog'} : {$part.reference|escape:'html':'UTF-8'}</span>
                <span class="ms-spare-list__avail ms-spare-list__avail--{$part.availability|escape:'html':'UTF-8'}">
                  {if $part.availability == 'available'}{l s='Disponible' d='Shop.Theme.Catalog'}{else}{l s='Indisponible' d='Shop.Theme.Catalog'}{/if}
                </span>
                <span class="ms-spare-list__price">{$part.price|escape:'html':'UTF-8'}</span>
                <span class="ms-spare-list__qty-label">{l s='Quantité recommandée' d='Shop.Theme.Catalog'}</span>
                <div class="ms-spare-list__qty-wrap">
                  <select class="ms-spare-list__qty" aria-label="{l s='Quantité' d='Shop.Theme.Actions'}">
                    {section name=qty loop=10 start=1}
                      <option value="{$smarty.section.qty.index}"{if $smarty.section.qty.index == $part.recommended_qty} selected{/if}>{$smarty.section.qty.index}</option>
                    {/section}
                  </select>
                  <svg class="ms-spare-list__qty-chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                    <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </div>
                <form action="{$part.add_to_cart_url|escape:'html':'UTF-8'}" method="post" class="ms-spare-list__add-form">
                  <input type="hidden" name="token" value="{$static_token}">
                  <input type="hidden" name="id_product" value="{$part.id_product|intval}">
                  <input type="hidden" name="qty" value="{$part.recommended_qty|intval}">
                  <button type="submit" class="ms-spare-list__add">
                    {l s='AJOUTER AU PANIER' d='Shop.Theme.Actions'}
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                      <path d="M15.75 3H3.75H3H1.5V4.5H3H3.225L5.6865 11.2687C6.00825 12.1545 6.85875 12.75 7.80075 12.75H14.25V11.25H7.80075C7.4865 11.25 7.203 11.0513 7.09575 10.7565L6.72975 9.75H13.6845C14.352 9.75 14.9445 9.3015 15.1268 8.66175L16.4715 3.95625C16.536 3.72975 16.4902 3.48675 16.3492 3.2985C16.2067 3.11025 15.9847 3 15.75 3ZM13.6845 8.25H6.1845L4.821 4.5H14.7562L13.6845 8.25Z" fill="white"/>
                    </svg>
                  </button>
                </form>
              </li>
            {/foreach}
          </ul>
        {else}
          <p class="ms-related-tabs__empty">{l s='Aucune pièce de rechange' d='Shop.Theme.Catalog'}</p>
        {/if}
      </div>

    </div>

  </div>
</section>
