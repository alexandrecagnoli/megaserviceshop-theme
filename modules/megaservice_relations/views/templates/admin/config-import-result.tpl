{*
 * Rapport post-import.
 *}
<div class="panel">
  <h3>
    <i class="icon-list"></i> {l s='Résultat de l\'import' mod='megaservice_relations'}
  </h3>

  <div class="alert {if $ms_import_stats.errors|count > 0}alert-warning{else}alert-success{/if}">
    <strong>
      {if $ms_import_stats.errors|count > 0}
        {l s='Import terminé avec erreurs' mod='megaservice_relations'}
      {else}
        {l s='Import réussi' mod='megaservice_relations'}
      {/if}
    </strong>
  </div>

  <table class="table">
    <tbody>
      <tr>
        <th style="width:40%;">{l s='Relations importées (nouvelles)' mod='megaservice_relations'}</th>
        <td><strong style="color:#28a745;">{$ms_import_stats.imported|intval}</strong></td>
      </tr>
      <tr>
        <th>{l s='Relations mises à jour (existaient déjà)' mod='megaservice_relations'}</th>
        <td><span style="color:#6c757d;">{$ms_import_stats.skipped|intval}</span></td>
      </tr>
      {if $ms_import_stats.deleted > 0}
        <tr>
          <th>{l s='Relations supprimées avant import' mod='megaservice_relations'}</th>
          <td><span style="color:#dc3545;">{$ms_import_stats.deleted|intval}</span></td>
        </tr>
      {/if}
      <tr>
        <th>{l s='Erreurs' mod='megaservice_relations'}</th>
        <td>
          {if $ms_import_stats.errors|count > 0}
            <strong style="color:#dc3545;">{$ms_import_stats.errors|count}</strong>
          {else}
            <span style="color:#28a745;">0</span>
          {/if}
        </td>
      </tr>
    </tbody>
  </table>

  {if $ms_import_stats.errors|count > 0}
    <h4>{l s='Détail des erreurs' mod='megaservice_relations'}</h4>
    <table class="table table-bordered" style="font-size:13px;">
      <thead>
        <tr>
          <th style="width:80px;">{l s='Ligne' mod='megaservice_relations'}</th>
          <th>{l s='Message' mod='megaservice_relations'}</th>
        </tr>
      </thead>
      <tbody>
        {foreach from=$ms_import_stats.errors item="err"}
          <tr>
            <td><strong>#{$err.line|intval}</strong></td>
            <td>{$err.msg|escape:'htmlall':'UTF-8'}</td>
          </tr>
        {/foreach}
      </tbody>
    </table>
  {/if}
</div>
