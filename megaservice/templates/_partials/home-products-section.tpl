{**
 * Bloc produits de la page d'accueil — Nouveautés, Promos, Produits.
 *
 * RÈGLE : aucun produit sans photo sur la page d'accueil.
 *
 * Les trois blocs affichaient ce que leur module renvoyait, photo ou non :
 * l'accueil montrait le placeholder `fr-default-home_default.jpg` sur 5 des
 * 9 cartes. C'est la première page vue, et une fiche sans visuel n'y a pas sa
 * place — ailleurs (catégorie, recherche) le produit reste visible, il ne faut
 * pas le retirer du catalogue.
 *
 * Détection : une couverture réelle porte `id_image`. L'image « aucune photo »
 * fabriquée par PrestaShop (ImageRetriever::getNoPictureImage) n'a que
 * `bySize`, `small`, `medium`, `large` et `legend` — jamais `id_image`. Tester
 * `$product.cover` seul ne suffit donc pas : il est toujours vrai.
 *
 * Conséquence assumée : un bloc peut afficher moins de produits que le nombre
 * configuré côté module, et disparaître entièrement si aucun n'a de photo.
 *
 * Paramètres : products, title, more_url (facultatif), more_label (facultatif).
 *}

{assign var='ms_with_image' value=[]}
{foreach from=$products item='ms_product'}
  {if !empty($ms_product.cover.id_image)}
    {append var='ms_with_image' value=$ms_product}
  {/if}
{/foreach}

{if $ms_with_image}
<section class="ms-products-section">
  <div class="ms-container">
    <div class="ms-products-section__header">
      <h2 class="ms-products-section__title">{$title}</h2>
      {if !empty($more_url)}
        <a href="{$more_url}" class="ms-products-section__see-all">{$more_label}</a>
      {/if}
    </div>
    {include file='catalog/_partials/productlist.tpl' products=$ms_with_image}
  </div>
</section>
{/if}
