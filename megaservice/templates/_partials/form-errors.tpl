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
{* Info box (REC-39) : même rendu que notifications.tpl. Sans `inline`, c'est une
   erreur de FORMULAIRE (ex. « Échec d'authentification ») ; avec `inline`, une erreur
   de CHAMP, sous le champ, en version compacte. Plus de liste à puces. *}
{if $errors|count}
  <div class="help-block{if !empty($inline)} help-block--inline{/if}">
    {block name='form_errors'}
      <div class="alert alert-danger ms-alert ms-alert--error{if !empty($inline)} ms-alert--inline{/if}" role="alert">
        <div class="ms-alert__body">
          {foreach $errors as $error}
            <p>{$error|nl2br nofilter}</p>
          {/foreach}
        </div>
      </div>
    {/block}
  </div>
{/if}
