{**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}

{**
 * Info boxes (REC-39) : un message = un paragraphe, plus de liste à puces.
 * Les noms de blocs PrestaShop sont conservés pour qu'un enfant puisse
 * toujours les surcharger. L'icône et les couleurs viennent de
 * assets/scss/components/_alerts.scss.
 *}
{if isset($notifications)}
<aside id="notifications">
  <div class="notifications-container container">
    {if $notifications.error}
      {block name='notifications_error'}
        <article class="alert alert-danger ms-alert ms-alert--error" role="alert" data-alert="danger">
          <div class="ms-alert__body">
            {foreach $notifications.error as $notif}
              <p>{$notif nofilter}</p>
            {/foreach}
          </div>
        </article>
      {/block}
    {/if}

    {if $notifications.warning}
      {block name='notifications_warning'}
        <article class="alert alert-warning ms-alert ms-alert--warning" role="alert" data-alert="warning">
          <div class="ms-alert__body">
            {foreach $notifications.warning as $notif}
              <p>{$notif nofilter}</p>
            {/foreach}
          </div>
        </article>
      {/block}
    {/if}

    {if $notifications.success}
      {block name='notifications_success'}
        <article class="alert alert-success ms-alert ms-alert--success" role="status" data-alert="success">
          <div class="ms-alert__body">
            {foreach $notifications.success as $notif}
              <p>{$notif nofilter}</p>
            {/foreach}
          </div>
        </article>
      {/block}
    {/if}

    {if $notifications.info}
      {block name='notifications_info'}
        <article class="alert alert-info ms-alert ms-alert--info" role="status" data-alert="info">
          <div class="ms-alert__body">
            {foreach $notifications.info as $notif}
              <p>{$notif nofilter}</p>
            {/foreach}
          </div>
        </article>
      {/block}
    {/if}
  </div>
</aside>
{/if}
