{**
 * Bloc « cette référence est remplacée » — fiche produit front.
 * Matrice SPEC §5.2, cas fourni par MsReplacementFrontBlock.
 *
 * La fiche du produit remplacé reste VISIBLE et INDEXABLE (pas de 301) : le
 * client doit pouvoir confirmer qu'il a trouvé la bonne ancienne référence.
 * Elle n'est simplement plus achetable — le JS neutralise l'ajout au panier.
 *}
{* Ajout groupé : 1:N dont au moins un composant est cochable. Ce bloc est rendu
   DANS le <form> d'achat de la fiche : aucun champ ci-dessous n'a de `name` et le
   bouton est type="button", sinon ils partiraient avec l'ajout du produit principal. *}
{assign var='msReplGroup' value=false}
{if $ms_repl.is_set}
  {foreach from=$ms_repl.targets item=tg}{if $tg.selectable}{assign var='msReplGroup' value=true}{/if}{/foreach}
{/if}
<div class="ms-repl-front ms-repl-front--{$ms_repl.case|lower}" data-case="{$ms_repl.case}"{if $ms_repl.replaced_orderable} data-orderable="1"{/if}
     {if $msReplGroup} data-group-add data-cart-url="{$ms_repl.cart_url|escape:'html':'UTF-8'}" data-token="{$ms_repl.token|escape:'html':'UTF-8'}" data-currency="{$ms_repl.currency|escape:'html':'UTF-8'}"{/if}>

  {* ── En-tête : le message change selon le cas ── *}
  <div class="ms-repl-front__head">
    <span class="ms-repl-front__icon" aria-hidden="true">&#8635;</span>
    <div>
      <p class="ms-repl-front__title">
        {if $ms_repl.case == 'E'}
          {l s='Référence remplacée' d='Modules.Megaservicereplacement.Shop'}
        {elseif $ms_repl.is_set}
          {* Règle client du 17/09 : « remplacé » est réservé au 1:1. Un 1:N —
             vrai remplacement éclaté ou simple kit — se COMPOSE de N références. *}
          {l s='Cet article se compose de %d références' sprintf=[$ms_repl.total_count] d='Modules.Megaservicereplacement.Shop'}
        {else}
          {l s='Cette pièce est remplacée' d='Modules.Megaservicereplacement.Shop'}
        {/if}
      </p>
      <p class="ms-repl-front__sub">
        {if $ms_repl.is_set && $ms_repl.case != 'E'}
          {if $ms_repl.replaced_orderable}
            {l s='Référence %s — vous pouvez aussi commander les composants séparément.' sprintf=[$ms_repl.reference] d='Modules.Megaservicereplacement.Shop'}
          {else}
            {l s='Référence %s — à commander composant par composant.' sprintf=[$ms_repl.reference] d='Modules.Megaservicereplacement.Shop'}
          {/if}
        {else}
          {l s='Référence %s — remplacée par le constructeur.' sprintf=[$ms_repl.reference] d='Modules.Megaservicereplacement.Shop'}
        {/if}
      </p>
    </div>
  </div>

  {* ── Cas E / F : rien d'exploitable au catalogue ── *}
  {if $ms_repl.case == 'E'}
    <p class="ms-repl-front__msg">
      {l s='La référence de remplacement n\'est pas encore disponible sur notre site. Contactez-nous, nous la commandons pour vous.' d='Modules.Megaservicereplacement.Shop'}
    </p>
    <a href="{url entity='contact'}" class="btn btn-primary ms-repl-front__cta">
      {l s='Nous contacter' d='Modules.Megaservicereplacement.Shop'}
    </a>

  {else}

    {* ── Liste des références de remplacement ── *}
    {* Gros ensemble : la liste défile dans le bloc (hauteur bornée) au lieu de
       s'allonger, et tout reste visible — y compris les cases cochées. *}
    <ul class="ms-repl-front__list{if $ms_repl.is_large_set} ms-repl-front__list--scroll{/if}">
      {foreach from=$ms_repl.targets item=t name=tg}
        <li class="ms-repl-front__item{if !$t.available} is-unavailable{/if}"
            {if $msReplGroup && $t.selectable} data-id-product="{$t.product.id_product|intval}" data-price="{$t.product.price_raw}" data-id-attr="{if $t.product.variant_mode == 'single'}{$t.product.id_product_attribute|intval}{else}0{/if}"{if $t.product.variant_mode == 'multiple'} data-variant="multiple"{/if}{/if}>

          {if $msReplGroup}
            {if $t.selectable}
              {* Plusieurs déclinaisons : case inactive et décochée tant que le client
                 n'a pas choisi la sienne — on ne devine pas son choix. *}
              <input type="checkbox" class="ms-repl-front__check js-ms-repl-check"{if $t.product.variant_mode == 'multiple'} disabled{else} checked{/if}
                     aria-label="{l s='Ajouter %s au panier' sprintf=[$t.ref|escape:'html'] d='Modules.Megaservicereplacement.Shop'}">
            {else}
              <span class="ms-repl-front__check ms-repl-front__check--none" aria-hidden="true"></span>
            {/if}
          {/if}

          {if $t.product}
            {if $t.product.image}
              <a href="{$t.product.url}" class="ms-repl-front__thumb">
                <img src="{$t.product.image}" alt="{$t.product.name|escape:'html'}" loading="lazy">
              </a>
            {/if}
            <div class="ms-repl-front__info">
              <a href="{$t.product.url}" class="ms-repl-front__name">{$t.product.name|escape:'html'}</a>
              <span class="ms-repl-front__ref">{l s='Réf' d='Modules.Megaservicereplacement.Shop'} : {$t.ref|escape:'html'}</span>
              {if $t.quantity > 1}
                <span class="ms-repl-front__qty">{l s='Quantité nécessaire : ×%d' sprintf=[$t.quantity] d='Modules.Megaservicereplacement.Shop'}</span>
              {/if}
            </div>
            <div class="ms-repl-front__buy">
              <span class="ms-repl-front__price">{$t.product.price}</span>
              {if $t.available}
                <span class="ms-repl-front__stock is-available">{l s='Disponible' d='Modules.Megaservicereplacement.Shop'}</span>
              {else}
                <span class="ms-repl-front__stock is-unavailable">{l s='Indisponible' d='Modules.Megaservicereplacement.Shop'}</span>
              {/if}
              {if $msReplGroup && $t.selectable}
                {if $t.product.variant_mode == 'multiple'}
                  {* Déclinaisons : à choisir ici. Celles qui ne sont pas commandables
                     restent listées mais grisées. Une seule déclinaison → aucun
                     sélecteur, elle est ajoutée telle quelle. *}
                  <div class="ms-repl-front__qty-wrap ms-repl-front__variant-wrap">
                    <select class="ms-repl-front__qty-select ms-repl-front__variant-select js-ms-repl-variant"
                            aria-label="{l s='Déclinaison' d='Modules.Megaservicereplacement.Shop'}">
                      <option value="">{l s='Choisir' d='Modules.Megaservicereplacement.Shop'}</option>
                      {foreach from=$t.product.combinations item=c}
                        <option value="{$c.id_product_attribute|intval}" data-price="{$c.price_raw}"{if !$c.orderable} disabled{/if}>{$c.label|escape:'html'}{if !$c.orderable} — {l s='indisponible' d='Modules.Megaservicereplacement.Shop'}{/if}</option>
                      {/foreach}
                    </select>
                    <svg class="ms-repl-front__qty-chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                      <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </div>
                {/if}
                {* Même sélecteur que le panier latéral (an_sidebarcart). 1..20, ou
                   jusqu'à la quantité nécessaire si elle est plus grande (jusqu'à 36 vus). *}
                {assign var='msReplQty' value=1}
                {if $t.quantity > 1}{assign var='msReplQty' value=$t.quantity|intval}{/if}
                {assign var='msReplQtyMax' value=20}
                {if $msReplQty > 20}{assign var='msReplQtyMax' value=$msReplQty}{/if}
                <div class="ms-repl-front__qty-wrap">
                  <select class="ms-repl-front__qty-select js-ms-repl-qty"
                          aria-label="{l s='Quantité' d='Modules.Megaservicereplacement.Shop'}">
                    {assign var='msReplQtyLoop' value=$msReplQtyMax+1}
                    {section name=q loop=$msReplQtyLoop start=1}
                      <option value="{$smarty.section.q.index}"{if $smarty.section.q.index == $msReplQty} selected{/if}>{$smarty.section.q.index}</option>
                    {/section}
                  </select>
                  <svg class="ms-repl-front__qty-chevron" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                    <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </div>
              {/if}
            </div>
          {else}
            {* Composant absent du catalogue au sein d'un ensemble *}
            <div class="ms-repl-front__info">
              <span class="ms-repl-front__ref">{l s='Réf' d='Modules.Megaservicereplacement.Shop'} : {$t.ref|escape:'html'}</span>
              <span class="ms-repl-front__stock is-unavailable">{l s='Non référencée' d='Modules.Megaservicereplacement.Shop'}</span>
            </div>
          {/if}

        </li>
      {/foreach}
    </ul>


    {* ── Ajout groupé des composants cochés ── *}
    {if $msReplGroup}
      <div class="ms-repl-front__group">
        <label class="ms-repl-front__all">
          <input type="checkbox" class="ms-repl-front__check js-ms-repl-all" checked>
          {l s='Tout sélectionner' d='Modules.Megaservicereplacement.Shop'}
        </label>
        <span class="ms-repl-front__total js-ms-repl-total"></span>
        <button type="button" class="ms-repl-front__add js-ms-repl-add"
                data-label="{l s='Ajouter la sélection' d='Modules.Megaservicereplacement.Shop'}">
          {l s='Ajouter la sélection' d='Modules.Megaservicereplacement.Shop'}
        </button>
      </div>
      <p class="ms-repl-front__feedback js-ms-repl-feedback" role="status" aria-live="polite" hidden></p>
    {/if}

    {* ── Message de disponibilité partielle (cas C) ── *}
    {if $ms_repl.case == 'C'}
      <p class="ms-repl-front__msg ms-repl-front__msg--warning">
        {l s='%d référence(s) sur %d sont actuellement disponibles.' sprintf=[$ms_repl.available_count, $ms_repl.total_count] d='Modules.Megaservicereplacement.Shop'}
      </p>
    {/if}

    {* ── Cas D : présent au catalogue mais rien de commandable ──
         Formulation de repli : le comportement définitif est en attente
         d'arbitrage COPROJ (et concerne ~26 % des remplaçants). *}
    {if $ms_repl.case == 'D' && !$ms_repl.replaced_orderable}
      <p class="ms-repl-front__msg ms-repl-front__msg--warning">
        {l s='La référence de remplacement est actuellement indisponible. Contactez-nous pour connaître le délai.' d='Modules.Megaservicereplacement.Shop'}
      </p>
      <a href="{url entity='contact'}" class="btn btn-primary ms-repl-front__cta">
        {l s='Nous contacter' d='Modules.Megaservicereplacement.Shop'}
      </a>
    {/if}

  {/if}
</div>
