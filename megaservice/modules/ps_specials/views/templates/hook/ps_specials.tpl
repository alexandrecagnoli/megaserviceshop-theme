{* Habillage et règle « aucun produit sans photo » : _partials/home-products-section.tpl *}
{capture name='ms_title'}{l s='En Promo' d='Modules.Specials.Shop'}{/capture}
{capture name='ms_more'}{l s='Toutes les promos' d='Modules.Specials.Shop'}{/capture}

{include file='_partials/home-products-section.tpl'
         products=$products
         title=$smarty.capture.ms_title
         more_url=$allSpecialProductsLink
         more_label=$smarty.capture.ms_more}
