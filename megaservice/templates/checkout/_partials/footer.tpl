{* Modal réutilisée par le checkout (login transformations, etc.) *}
<div class="modal fade js-checkout-modal" id="modal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <button type="button" class="close" data-dismiss="modal" aria-label="{l s='Fermer' d='Shop.Theme.Global'}">
        <span aria-hidden="true">&times;</span>
      </button>
      <div class="js-modal-content"></div>
    </div>
  </div>
</div>

<footer class="ms-checkout-footer">
  {if $tos_cms != false}
    <span class="js-terms">{$tos_cms nofilter}</span>
  {/if}
  {block name='copyright_link'}
    <span>© {'Y'|date} Megaservice — {l s='Tous droits réservés' d='Shop.Theme.Global'}</span>
  {/block}
</footer>
