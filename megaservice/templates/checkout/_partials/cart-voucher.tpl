{if $cart.vouchers.allowed}
  {block name='cart_voucher'}
    <div class="ms-cart-voucher block-promo">
      <div class="cart-voucher js-cart-voucher">

        {if $cart.vouchers.added}
          {block name='cart_voucher_list'}
            <ul class="ms-cart-voucher__list promo-name">
              {foreach from=$cart.vouchers.added item=voucher}
                <li class="ms-cart-voucher__item cart-summary-line">
                  <span class="ms-cart-voucher__name">{$voucher.name}</span>
                  <div class="ms-cart-voucher__right">
                    <span class="ms-cart-voucher__amount">{$voucher.reduction_formatted}</span>
                    {if isset($voucher.code) && $voucher.code !== ''}
                      <a class="ms-cart-voucher__remove" href="{$voucher.delete_url}" data-link-action="remove-voucher" aria-label="{l s='Retirer ce code' d='Shop.Theme.Actions'}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                      </a>
                    {/if}
                  </div>
                </li>
              {/foreach}
            </ul>
          {/block}
        {/if}

        <p class="ms-cart-voucher__toggle promo-code-button display-promo{if $cart.discounts|count > 0} with-discounts{/if}">
          <a class="collapse-button" href="#promo-code">{l s='Vous avez un code promo ?' d='Shop.Theme.Checkout'}</a>
        </p>

        <div id="promo-code" class="ms-cart-voucher__form-wrap collapse{if $cart.discounts|count > 0} in{/if}">
          <div class="promo-code">
            {block name='cart_voucher_form'}
              <form class="ms-cart-voucher__form" action="{$urls.pages.cart}" data-link-action="add-voucher" method="post">
                <input type="hidden" name="token" value="{$static_token}">
                <input type="hidden" name="addDiscount" value="1">
                <input class="ms-cart-voucher__input promo-input" type="text" name="discount_name" placeholder="{l s='Code promo' d='Shop.Theme.Checkout'}">
                <button type="submit" class="ms-cart-voucher__submit"><span>{l s='Appliquer' d='Shop.Theme.Actions'}</span></button>
              </form>
            {/block}

            {block name='cart_voucher_notifications'}
              <div class="ms-cart-voucher__error alert alert-danger js-error" role="alert" hidden>
                <span class="js-error-text"></span>
              </div>
            {/block}

            <a class="ms-cart-voucher__cancel collapse-button promo-code-button cancel-promo" role="button" data-toggle="collapse" data-target="#promo-code" aria-expanded="true" aria-controls="promo-code">
              {l s='Fermer' d='Shop.Theme.Checkout'}
            </a>
          </div>
        </div>

        {if $cart.discounts|count > 0}
          <p class="ms-cart-voucher__highlight block-promo promo-highlighted">
            {l s='Profitez de nos offres exclusives :' d='Shop.Theme.Actions'}
          </p>
          <ul class="ms-cart-voucher__discounts js-discount promo-discounts">
            {foreach from=$cart.discounts item=discount}
              <li class="cart-summary-line">
                <span class="label">
                  <span class="code">{$discount.code}</span> - {$discount.name}
                </span>
              </li>
            {/foreach}
          </ul>
        {/if}

      </div>
    </div>
  {/block}
{/if}
