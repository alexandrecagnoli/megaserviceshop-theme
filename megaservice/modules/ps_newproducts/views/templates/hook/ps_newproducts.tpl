{* Habillage et règle « aucun produit sans photo » : _partials/home-products-section.tpl *}
{capture name='ms_title'}{l s='Les Nouveautés' d='Modules.Newproducts.Shop'}{/capture}
{capture name='ms_more'}{l s='Toutes les nouveautés' d='Modules.Newproducts.Shop'}{/capture}

{include file='_partials/home-products-section.tpl'
         products=$products
         title=$smarty.capture.ms_title
         more_url=$allNewProductsLink
         more_label=$smarty.capture.ms_more}
