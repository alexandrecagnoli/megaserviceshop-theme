{* Habillage et règle « aucun produit sans photo » : _partials/home-products-section.tpl *}
{capture name='ms_title'}{l s='Les Produits' d='Modules.Featuredproducts.Shop'}{/capture}

{include file='_partials/home-products-section.tpl'
         products=$products
         title=$smarty.capture.ms_title}
