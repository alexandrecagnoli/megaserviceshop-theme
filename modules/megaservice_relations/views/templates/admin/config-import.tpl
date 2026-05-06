{*
 * Page config du module — formulaire d'import CSV des relations Powerparts.
 *}
<div class="panel">
  <h3><i class="icon-cloud-upload"></i> {l s='Import CSV des relations' mod='megaservice_relations'}</h3>

  <form action="{$ms_form_action|escape:'htmlall':'UTF-8'}" method="post" enctype="multipart/form-data" class="defaultForm form-horizontal">

    <div class="form-group">
      <label class="control-label col-lg-3" for="csv_file">{l s='Fichier CSV' mod='megaservice_relations'}</label>
      <div class="col-lg-9">
        <input type="file" name="csv_file" id="csv_file" accept=".csv,text/csv" required>
        <p class="help-block">{l s='Encodage UTF-8 recommandé. Délimiteur , ou ; (auto-détecté).' mod='megaservice_relations'}</p>
      </div>
    </div>

    <div class="form-group">
      <label class="control-label col-lg-3">{l s='Mode d\'import' mod='megaservice_relations'}</label>
      <div class="col-lg-9">
        <div class="radio">
          <label>
            <input type="radio" name="import_mode" value="append" checked>
            <strong>{l s='Append' mod='megaservice_relations'}</strong> — {l s='Ajoute les lignes du CSV. Une relation déjà existante (même source + type + cible) est mise à jour.' mod='megaservice_relations'}
          </label>
        </div>
        <div class="radio">
          <label>
            <input type="radio" name="import_mode" value="replace_for_listed">
            <strong>{l s='Replace pour produits listés' mod='megaservice_relations'}</strong> — {l s='Pour chaque (id_product_source, relation_type) du CSV, vide les relations existantes puis insère les nouvelles. Idéal pour resync depuis un export.' mod='megaservice_relations'}
          </label>
        </div>
        <div class="radio">
          <label>
            <input type="radio" name="import_mode" value="full_replace">
            <strong>{l s='Full replace' mod='megaservice_relations'}</strong> — {l s='Vide TOUTE la table avant import. À utiliser pour la migration initiale.' mod='megaservice_relations'}
          </label>
        </div>
      </div>
    </div>

    <div class="panel-footer">
      <button type="submit" name="submitImportRelations" class="btn btn-primary">
        <i class="process-icon-cogs"></i> {l s='Lancer l\'import' mod='megaservice_relations'}
      </button>
    </div>

  </form>
</div>

<div class="panel">
  <h3><i class="icon-info-circle"></i> {l s='Format du CSV attendu' mod='megaservice_relations'}</h3>

  <p>
    {l s='Le fichier doit contenir un header avec les colonnes suivantes :' mod='megaservice_relations'}
  </p>

  <ul>
    <li><code>id_product_source</code> <span class="text-danger">{l s='(requis)' mod='megaservice_relations'}</span> — {l s='ID PrestaShop du produit qui possède la relation' mod='megaservice_relations'}</li>
    <li><code>relation_type</code> <span class="text-danger">{l s='(requis)' mod='megaservice_relations'}</span> — {l s='Une des valeurs : mandatory, excluded, recommended, spare' mod='megaservice_relations'}</li>
    <li><code>id_product_target</code> <span class="text-danger">{l s='(requis)' mod='megaservice_relations'}</span> — {l s='ID PrestaShop du produit lié' mod='megaservice_relations'}</li>
    <li><code>position</code> <span class="text-muted">{l s='(optionnel)' mod='megaservice_relations'}</span> — {l s='Position d\'affichage. Si vide, position auto-incrémentée.' mod='megaservice_relations'}</li>
    <li><code>recommended_qty</code> <span class="text-muted">{l s='(optionnel)' mod='megaservice_relations'}</span> — {l s='Quantité recommandée (utilisé pour les pièces de rechange). Défaut : 1.' mod='megaservice_relations'}</li>
  </ul>

  <p>{l s='Exemple :' mod='megaservice_relations'}</p>

  <pre style="background:#f5f5f5;padding:12px;border:1px solid #ddd;font-size:12px;">id_product_source,relation_type,id_product_target,position,recommended_qty
29,mandatory,30,1,1
29,mandatory,37,2,1
29,excluded,40,1,
29,recommended,30,1,
29,recommended,73,2,
29,spare,37,1,4
29,spare,73,2,2</pre>
</div>
