{**
 * Bloc « cette référence est remplacée » — fiche produit front.
 * Matrice SPEC §5.2, cas fourni par MsReplacementFrontBlock.
 *
 * La fiche du produit remplacé reste VISIBLE et INDEXABLE (pas de 301) : le
 * client doit pouvoir confirmer qu'il a trouvé la bonne ancienne référence.
 * Elle n'est simplement plus achetable — le JS neutralise l'ajout au panier.
 *}
<div class="ms-repl-front ms-repl-front--{$ms_repl.case|lower}" data-case="{$ms_repl.case}">

  {* ── En-tête : le message change selon le cas ── *}
  <div class="ms-repl-front__head">
    <span class="ms-repl-front__icon" aria-hidden="true">&#8635;</span>
    <div>
      <p class="ms-repl-front__title">
        {if $ms_repl.case == 'E'}
          {l s='Référence remplacée' d='Modules.Megaservicereplacement.Shop'}
        {elseif $ms_repl.is_set}
          {l s='Cette pièce est remplacée par un ensemble de %d références' sprintf=[$ms_repl.total_count] d='Modules.Megaservicereplacement.Shop'}
        {else}
          {l s='Cette pièce est remplacée' d='Modules.Megaservicereplacement.Shop'}
        {/if}
      </p>
      <p class="ms-repl-front__sub">
        {l s='Référence %s — remplacée par le constructeur.' sprintf=[$ms_repl.reference] d='Modules.Megaservicereplacement.Shop'}
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
    <ul class="ms-repl-front__list{if $ms_repl.is_large_set} ms-repl-front__list--collapsed js-ms-repl-list{/if}">
      {foreach from=$ms_repl.targets item=t name=tg}
        <li class="ms-repl-front__item{if !$t.available} is-unavailable{/if}{if $ms_repl.is_large_set && $smarty.foreach.tg.index >= 8} ms-repl-front__item--extra{/if}">

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

    {if $ms_repl.is_large_set}
      <button type="button" class="ms-repl-front__more js-ms-repl-more"
              data-more="{l s='Voir les %d références' sprintf=[$ms_repl.total_count] d='Modules.Megaservicereplacement.Shop'}"
              data-less="{l s='Réduire la liste' d='Modules.Megaservicereplacement.Shop'}">
        {l s='Voir les %d références' sprintf=[$ms_repl.total_count] d='Modules.Megaservicereplacement.Shop'}
      </button>
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
    {if $ms_repl.case == 'D'}
      <p class="ms-repl-front__msg ms-repl-front__msg--warning">
        {l s='La référence de remplacement est actuellement indisponible. Contactez-nous pour connaître le délai.' d='Modules.Megaservicereplacement.Shop'}
      </p>
      <a href="{url entity='contact'}" class="btn btn-primary ms-repl-front__cta">
        {l s='Nous contacter' d='Modules.Megaservicereplacement.Shop'}
      </a>
    {/if}

  {/if}
</div>
