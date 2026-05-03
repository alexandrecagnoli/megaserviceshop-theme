{extends file='customer/page.tpl'}

{block name='page_title'}{l s='Détail du retour' d='Shop.Theme.Customeraccount'}{/block}

{block name='page_content'}
  {block name='order_return_infos'}
    <div id="order-return-infos" class="ms-account-card">
      <div class="ms-account-card__head">
        <h2 class="ms-account-card__title">{l s='%number% du %date%' d='Shop.Theme.Customeraccount' sprintf=['%number%' => $return.return_number, '%date%' => $return.return_date]}</h2>
      </div>
      <div class="ms-account-card__body">
        <p>{l s='Votre demande de retour a bien été enregistrée.' d='Shop.Theme.Customeraccount'}</p>
        <p>{l s='Votre colis doit nous être retourné sous %number% jours après réception de votre commande.' d='Shop.Theme.Customeraccount' sprintf=['%number%' => $configuration.number_of_days_for_return]}</p>
        <p>
          {l
            s='Le statut actuel de votre retour est : [1] %status% [/1]'
            d='Shop.Theme.Customeraccount'
            sprintf=['[1]' => '<strong>', '[/1]' => '</strong>', '%status%' => $return.state_name]
          }
        </p>

        <h3 style="margin:24px 0 12px; font-family: var(--font-heading,Blender Pro), sans-serif; text-transform:uppercase; font-size:14px;">{l s='Articles à retourner' d='Shop.Theme.Customeraccount'}</h3>

        <table class="ms-account-table" style="border:1px solid #CCCCCC; border-radius:4px; overflow:hidden;">
          <thead><tr><th>{l s='Produit' d='Shop.Theme.Catalog'}</th><th>{l s='Quantité' d='Shop.Theme.Checkout'}</th></tr></thead>
          <tbody>
            {foreach from=$products item=product}
              <tr>
                <td>
                  <strong>{$product.product_name}</strong>
                  {if $product.product_reference}<br><small>{l s='Référence' d='Shop.Theme.Catalog'} : {$product.product_reference}</small>{/if}
                  {if $product.customizations}
                    {foreach from=$product.customizations item="customization"}
                      <div class="customization">
                        <a href="#" data-toggle="modal" data-target="#product-customizations-modal-{$customization.id_customization}">{l s='Personnalisation' d='Shop.Theme.Catalog'}</a>
                      </div>
                      <div class="modal fade customization-modal" id="product-customizations-modal-{$customization.id_customization}" tabindex="-1" role="dialog" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                          <div class="modal-content">
                            <div class="modal-header">
                              <button type="button" class="close" data-dismiss="modal" aria-label="{l s='Fermer' d='Shop.Theme.Global'}"><span aria-hidden="true">&times;</span></button>
                              <h4 class="modal-title">{l s='Personnalisation' d='Shop.Theme.Catalog'}</h4>
                            </div>
                            <div class="modal-body">
                              {foreach from=$customization.fields item="field"}
                                <div class="product-customization-line row">
                                  <div class="col-sm-3 col-xs-4 label">{$field.label}</div>
                                  <div class="col-sm-9 col-xs-8 value">
                                    {if $field.type == 'text'}{if (int)$field.id_module}{$field.text nofilter}{else}{$field.text}{/if}
                                    {elseif $field.type == 'image'}<img src="{$field.image.small.url}" loading="lazy">{/if}
                                  </div>
                                </div>
                              {/foreach}
                            </div>
                          </div>
                        </div>
                      </div>
                    {/foreach}
                  {/if}
                </td>
                <td>{$product.product_quantity}</td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      </div>
    </div>
  {/block}

  {if $return.state == 2}
    <section class="ms-account-card" style="margin-top:16px;">
      <div class="ms-account-card__head"><h2 class="ms-account-card__title">{l s='Rappel' d='Shop.Theme.Customeraccount'}</h2></div>
      <div class="ms-account-card__body">
        <p>{l s='Tous les produits doivent être retournés dans leur emballage et leur état d\'origine.' d='Shop.Theme.Customeraccount'}</p>
        <p>{l s='Merci d\'imprimer le [1]formulaire de retour[/1] et de l\'inclure dans votre colis.' d='Shop.Theme.Customeraccount' sprintf=['[1]' => '<a class="ms-account-table__link" href="'|cat:$return.print_url|cat:'">', '[/1]' => '</a>']}</p>
        <p>{l s='Vérifiez l\'adresse de retour sur le [1]formulaire[/1].' d='Shop.Theme.Customeraccount' sprintf=['[1]' => '<a class="ms-account-table__link" href="'|cat:$return.print_url|cat:'">', '[/1]' => '</a>']}</p>
        <p>{l s='Lorsque nous recevrons votre colis, nous vous enverrons un email puis traiterons votre remboursement.' d='Shop.Theme.Customeraccount'} <a class="ms-account-table__link" href="{$urls.pages.contact}">{l s='Contactez-nous pour toute question.' d='Shop.Theme.Customeraccount'}</a></p>
        <p>{l s='Si les conditions de retour ci-dessus ne sont pas respectées, nous nous réservons le droit de refuser votre colis et/ou le remboursement.' d='Shop.Theme.Customeraccount'}</p>
      </div>
    </section>
  {/if}
{/block}
