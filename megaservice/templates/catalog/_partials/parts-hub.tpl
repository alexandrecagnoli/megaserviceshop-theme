{**
 * Hub d'orientation des pièces détachées d'origine — remplace la liste produits.
 *
 * Cette page remontait 40 897 références sur 3 409 pages, dont les premières
 * étaient des manuels d'utilisateur sans photo. Sans moto, aucune liste n'a de
 * sens ici : la compatibilité des pièces d'origine passe par les vues éclatées,
 * pas par la montabilité.
 *
 * Deux chemins vers la même fin, et ils convergent tous deux sur le hub moto
 * existant, qui ne change pas :
 *   - le sélecteur (parts-search.tpl), au-dessus, pour qui connaît sa moto ;
 *   - la navigation par gamme, ici, pour qui la cherche.
 *
 * Les millésimes sont portés SUR la carte modèle : une pièce dépend de l'année,
 * le choix doit rester explicite — mais sans page intermédiaire.
 *
 * Variables : ms_hub_gammes, ms_hub_gamme, ms_hub_models (cf. CategoryController).
 *}

<section class="ms-parts-hub">

  {* ── Gammes ─────────────────────────────────────────────── *}
  {if $ms_hub_gammes}
    <div class="ms-parts-hub__block">
      <h2 class="ms-parts-hub__title">{l s='Vous ne connaissez pas votre référence ?' d='Shop.Theme.Catalog'}</h2>
      <p class="ms-parts-hub__lead">
        {l s='Partez de votre type de moto. Chaque gamme mène à ses modèles, et chaque modèle à ses vues éclatées.' d='Shop.Theme.Catalog'}
      </p>

      <nav class="ms-parts-hub__gammes" aria-label="{l s='Gammes de motos' d='Shop.Theme.Catalog'}">
        {foreach from=$ms_hub_gammes item='gamme'}
          <a href="{$gamme.url|escape:'html':'UTF-8'}"
             class="ms-parts-hub__gamme{if $ms_hub_gamme === $gamme.type} is-active{/if}"
             {if $ms_hub_gamme === $gamme.type}aria-current="true"{/if}>
            {if $gamme.picture}
              <img src="{$gamme.picture|escape:'html':'UTF-8'}" alt="" class="ms-parts-hub__gamme-bg" loading="lazy">
            {/if}
            <span class="ms-parts-hub__gamme-label">{$gamme.label}</span>
            <span class="ms-parts-hub__gamme-count">
              {l s='%count% modèles' sprintf=['%count%' => $gamme.nb] d='Shop.Theme.Catalog'}
            </span>
          </a>
        {/foreach}
      </nav>
    </div>
  {/if}

  {* ── Modèles ────────────────────────────────────────────── *}
  {if $ms_hub_models}
    <div class="ms-parts-hub__block ms-parts-hub__block--alt">
      <h2 class="ms-parts-hub__title">
        {if $ms_hub_gamme}
          {l s='Modèles' d='Shop.Theme.Catalog'} &mdash; {$ms_hub_gamme}
        {else}
          {l s='Les modèles les plus récents' d='Shop.Theme.Catalog'}
        {/if}
      </h2>
      <p class="ms-parts-hub__lead">
        {l s='Choisissez le millésime : les pièces en dépendent.' d='Shop.Theme.Catalog'}
      </p>

      <ul class="ms-parts-hub__models">
        {foreach from=$ms_hub_models item='model'}
          <li class="ms-parts-hub__model">
            <div class="ms-parts-hub__model-media">
              {if $model.picture}
                <img src="{$model.picture|escape:'html':'UTF-8'}" alt="" loading="lazy">
              {else}
                <span class="ms-parts-hub__model-noimg">{$model.marque|escape:'html':'UTF-8'}</span>
              {/if}
            </div>
            <div class="ms-parts-hub__model-body">
              <p class="ms-parts-hub__model-brand">{$model.marque|escape:'html':'UTF-8'}</p>
              <p class="ms-parts-hub__model-name">{$model.name|escape:'html':'UTF-8'}</p>

              <ul class="ms-parts-hub__years">
                {foreach from=$model.years item='year'}
                  <li>
                    <a href="{$year.url|escape:'html':'UTF-8'}"
                       aria-label="{l s='%model% %year%' sprintf=['%model%' => $model.name, '%year%' => $year.annee] d='Shop.Theme.Catalog'}">{$year.annee}</a>
                  </li>
                {/foreach}
                {if $model.years_more > 0}
                  {* Les millésimes plus anciens restent atteignables par le
                     sélecteur ci-dessus : on ne les cache pas derrière un bouton
                     qui demanderait du JS pour une poignée de liens. Le nombre
                     est calculé par le contrôleur (HUB_YEARS_SHOWN), pour ne pas
                     recopier le seuil ici. *}
                  <li class="ms-parts-hub__years-more">
                    {l s='+ %count% millésimes' sprintf=['%count%' => $model.years_more] d='Shop.Theme.Catalog'}
                  </li>
                {/if}
              </ul>
            </div>
          </li>
        {/foreach}
      </ul>
    </div>
  {/if}

  {* ── Les deux familles ──────────────────────────────────── *}
  <div class="ms-parts-hub__block">
    <h2 class="ms-parts-hub__title">{l s='Ce que couvrent les pièces d\'origine' d='Shop.Theme.Catalog'}</h2>
    <p class="ms-parts-hub__lead">
      {l s='Toutes nos références sont certifiées par le constructeur et repérées sur les vues éclatées officielles.' d='Shop.Theme.Catalog'}
    </p>

    <div class="ms-parts-hub__families">
      <div class="ms-parts-hub__family">
        <h3>{l s='Partie cycle' d='Shop.Theme.Catalog'}</h3>
        <p>{l s='Châssis, fourches et amortisseurs, freinage, roues, guidon et commandes, carénages et plastiques.' d='Shop.Theme.Catalog'}</p>
      </div>
      <div class="ms-parts-hub__family">
        <h3>{l s='Partie moteur' d='Shop.Theme.Catalog'}</h3>
        <p>{l s='Haut et bas moteur, embrayage, boîte de vitesses, allumage et injection, refroidissement, échappement.' d='Shop.Theme.Catalog'}</p>
      </div>
    </div>
  </div>

</section>
