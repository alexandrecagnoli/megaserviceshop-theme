{*
 * Onglet "Relations Powerparts" sur la page produit BO.
 * 4 panneaux : mandatory / excluded / recommended / spare.
 * Chaque panneau a une autocomplete + une liste réordonnable des produits liés.
 * Le panneau "spare" expose en plus un champ qty par row.
 *
 * Initial state injecté en JSON, JS prend le relais pour add/remove/reorder/save.
 *}
<div class="form-wrapper ms-relations" data-ms-id-product="{$ms_id_product|intval}" data-ms-ajax-url="{$ms_ajax_url|escape:'htmlall'}">
  <h3>{l s='Relations Powerparts' mod='megaservice_relations'}</h3>

  <p class="text-muted small">
    {l s='Glissez-déposez pour réordonner. Les modifications sont enregistrées automatiquement.' mod='megaservice_relations'}
  </p>

  <div class="ms-relations__panels">

    {* Pièces obligatoires *}
    <div class="ms-relations__panel" data-type="mandatory">
      <h4>{l s='Pièces obligatoires' mod='megaservice_relations'} <span class="ms-relations__count badge">0</span></h4>
      <div class="ms-relations__search-wrap">
        <input type="text" class="ms-relations__search form-control" placeholder="{l s='Rechercher un produit (nom ou référence)…' mod='megaservice_relations'}" autocomplete="off">
        <ul class="ms-relations__results"></ul>
      </div>
      <ul class="ms-relations__items"></ul>
    </div>

    {* Pièces exclues *}
    <div class="ms-relations__panel" data-type="excluded">
      <h4>{l s='Pièces exclues (incompatibilités)' mod='megaservice_relations'} <span class="ms-relations__count badge">0</span></h4>
      <div class="ms-relations__search-wrap">
        <input type="text" class="ms-relations__search form-control" placeholder="{l s='Rechercher un produit (nom ou référence)…' mod='megaservice_relations'}" autocomplete="off">
        <ul class="ms-relations__results"></ul>
      </div>
      <ul class="ms-relations__items"></ul>
    </div>

    {* Pièces recommandées *}
    <div class="ms-relations__panel" data-type="recommended">
      <h4>{l s='Pièces recommandées' mod='megaservice_relations'} <span class="ms-relations__count badge">0</span></h4>
      <div class="ms-relations__search-wrap">
        <input type="text" class="ms-relations__search form-control" placeholder="{l s='Rechercher un produit (nom ou référence)…' mod='megaservice_relations'}" autocomplete="off">
        <ul class="ms-relations__results"></ul>
      </div>
      <ul class="ms-relations__items"></ul>
    </div>

    {* Pièces de rechange (avec qty) *}
    <div class="ms-relations__panel ms-relations__panel--with-qty" data-type="spare">
      <h4>{l s='Pièces de rechange' mod='megaservice_relations'} <span class="ms-relations__count badge">0</span></h4>
      <div class="ms-relations__search-wrap">
        <input type="text" class="ms-relations__search form-control" placeholder="{l s='Rechercher un produit (nom ou référence)…' mod='megaservice_relations'}" autocomplete="off">
        <ul class="ms-relations__results"></ul>
      </div>
      <ul class="ms-relations__items"></ul>
    </div>

  </div>

  {* État initial pour le JS *}
  <script type="application/json" class="ms-relations__data">{$ms_relations_json nofilter}</script>
</div>
