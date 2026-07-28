{**
 * Bloc « Motos compatibles » — bas de fiche produit.
 * Données : Megaservice_mountability::hookDisplayFooterProduct.
 * Masqué si aucune moto résolue (géré côté hook).
 *}
<section class="ms-mount ms-container">
  <h2 class="ms-mount__title">
    {l s='Motos compatibles' d='Modules.Megaservicemountability.Shop'}
    <span class="ms-mount__count">{count($ms_mount_motos)}</span>
  </h2>

  <ul class="ms-mount__list{if count($ms_mount_motos) > $ms_mount_threshold} ms-mount__list--collapsed js-ms-mount-list{/if}">
    {foreach from=$ms_mount_motos item=moto name=motos}
      <li class="ms-mount__item{if $smarty.foreach.motos.index >= $ms_mount_threshold} ms-mount__item--extra{/if}">
        <a href="{$moto.url}" class="ms-mount__link">
          <span class="ms-mount__brand">{$moto.marque|escape:'html'}</span>
          <span class="ms-mount__name">{$moto.label|escape:'html'}</span>
        </a>
      </li>
    {/foreach}
  </ul>

  {if count($ms_mount_motos) > $ms_mount_threshold}
    <button type="button" class="ms-mount__more js-ms-mount-more"
            data-more="{l s='Voir les %d motos' sprintf=[count($ms_mount_motos)] d='Modules.Megaservicemountability.Shop'}"
            data-less="{l s='Réduire' d='Modules.Megaservicemountability.Shop'}">
      {l s='Voir les %d motos' sprintf=[count($ms_mount_motos)] d='Modules.Megaservicemountability.Shop'}
    </button>
  {/if}
</section>
