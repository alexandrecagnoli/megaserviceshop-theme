{**
 * Bandeau « Catalogue filtré sur <moto> » — partagé par les deux variantes de
 * hero (category-header.tpl ET category-header-full.tpl). Une seule copie : la
 * racine PowerParts (533) est en hero plein-largeur, les familles en hero
 * normal, et les deux doivent afficher le même bandeau quand une moto est active.
 *
 * Sans ce bandeau sur la variante plein-largeur, la racine filtrée n'affichait
 * ni contexte ni bouton « Retirer », et surtout le marqueur .ms-catalog-context
 * manquait : le JS de purge (moto-garage.js) ne nettoyait alors pas l'URL ?moto=,
 * que la pagination ré-injectait → le filtre « revenait » au changement de page.
 *
 * Rendu seulement quand ms_show_moto_context est vrai (cf. CategoryController).
 *}
{if isset($ms_show_moto_context) && $ms_show_moto_context}
  <div class="ms-catalog-context">
    {* La photo de la moto filtrée, détourée si elle existe (cf. MsMoto). L'image
       générique reste la réserve pour les modèles sans photo. *}
    <img src="{if !empty($ms_moto_filter.picture_url)}{$ms_moto_filter.picture_url|escape:'html':'UTF-8'}{else}{$urls.theme_assets}img/moto-context.png{/if}"
         alt="" class="ms-catalog-context__moto-img">
    <div class="ms-catalog-context__inner">

      <span class="ms-catalog-context__brand" aria-hidden="true">
        <img src="{$urls.theme_assets}img/brands/ktm-square.png" alt="">
      </span>

      <div class="ms-catalog-context__text">
        <span class="ms-catalog-context__label">{l s='Catalogue filtré sur' d='Shop.Theme.Catalog'}</span>
        <strong class="ms-catalog-context__moto-name">{if isset($ms_moto_filter) && $ms_moto_filter}{$ms_moto_filter.label|escape:'html'}{/if}</strong>
      </div>

      <div class="ms-catalog-context__actions">
        <a href="#" class="ms-catalog-context__link js-model-trigger">{l s='Changer de modèle' d='Shop.Theme.Catalog'}</a>
        {* js-model-clear : purge localStorage ET le cookie serveur (cf. model-selector.js).
           Le href reste le repli sans JS. *}
        {if isset($ms_moto_filter) && $ms_moto_filter}<a href="{$ms_moto_filter.clear_url|escape:'html'}" class="ms-catalog-context__link js-model-clear">{l s='Retirer le filtre' d='Shop.Theme.Catalog'}</a>{/if}
      </div>

    </div>
  </div>
{/if}
