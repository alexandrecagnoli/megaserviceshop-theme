{**
 * Cocon sémantique « Type de pièce » — sous la liste, racine PowerParts (533).
 *
 * La variable ms_type_piece_cocon est assignée par CategoryController
 * (override/controllers/front/CategoryController.php :: typePieceCocon()) et
 * n'est non vide QUE sur la racine 533, qui est en layout plein-largeur sans
 * facettes. Chaque lien pointe la vue filtrée par la facette « Type de pièce »
 * correspondante (param q de ps_facetedsearch).
 *}
{if isset($ms_type_piece_cocon) && $ms_type_piece_cocon|@count}
  <nav class="ms-cocon" aria-label="{l s='Explorer par type de pièce' d='Shop.Theme.Catalog'}">
    <h2 class="ms-cocon__title">{l s='Explorer par type de pièce' d='Shop.Theme.Catalog'}</h2>
    <ul class="ms-cocon__list">
      {foreach from=$ms_type_piece_cocon item=ms_tp}
        <li class="ms-cocon__item">
          <a class="ms-cocon__link" href="{$ms_tp.url}">
            <span class="ms-cocon__label">{$ms_tp.name|escape:'html':'UTF-8'}</span>
            <span class="ms-cocon__count">{$ms_tp.nb}</span>
          </a>
        </li>
      {/foreach}
    </ul>
  </nav>
{/if}
