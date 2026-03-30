{block name='footer'}
<footer class="ms-footer">

  {* ── Bannière Pro ── *}
  <div class="ms-footer__pro-banner">
    <div class="ms-footer__container">
      <span class="ms-footer__pro-text">{l s='Vous êtes un professionnel de la moto ?' d='Shop.Theme.Global'}</span>
      <a href="#" class="ms-footer__pro-btn">{l s='DÉCOUVREZ LES AVANTAGES EXCLUSIFS' d='Shop.Theme.Global'}</a>
    </div>
  </div>

  {* ── Corps principal ── *}
  <div class="ms-footer__main">
    <div class="ms-footer__container">
      <div class="ms-footer__grid">

        {* Colonne 1 — Logo + adresse + marques *}
        <div class="ms-footer__brand">
          <a href="{$urls.pages.index}" aria-label="{$shop.name}">
            <img src="{$urls.theme_assets}img/LOGO_MEGASERVICE_DARK.png" alt="{$shop.name}" class="ms-footer__logo" loading="lazy">
          </a>
          <p class="ms-footer__address">12 avenue du bigeon bleu<br>78490 Méré - France</p>
          <img src="{$urls.theme_assets}img/brands/KTM_HUSQVARNA_GASGAS_LOGO.png" alt="KTM · Husqvarna · GasGas Authorized Dealer" class="ms-footer__brands-img" loading="lazy">
        </div>

        {* Colonne 2 — La Société *}
        <div class="ms-footer__nav">
          <h3 class="ms-footer__title">{l s='LA SOCIÉTÉ' d='Shop.Theme.Global'}</h3>
          <ul class="ms-footer__links">
            <li><a href="#">{l s='A propos' d='Shop.Theme.Global'}</a></li>
            <li><a href="#">{l s='Livraison et retours' d='Shop.Theme.Global'}</a></li>
            <li><a href="#">{l s='Blog' d='Shop.Theme.Global'}</a></li>
            <li><a href="#">{l s='FAQ' d='Shop.Theme.Global'}</a></li>
            <li><a href="{$urls.pages.contact}">{l s='Contact' d='Shop.Theme.Global'}</a></li>
          </ul>
        </div>

        {* Colonne 3 — Catalogue *}
        <div class="ms-footer__nav">
          <h3 class="ms-footer__title">{l s='CATALOGUE' d='Shop.Theme.Global'}</h3>
          <ul class="ms-footer__links">
            <li><a href="#">{l s='Motos neuves' d='Shop.Theme.Global'}</a></li>
            <li><a href="#">{l s='Motos d\'occasion' d='Shop.Theme.Global'}</a></li>
            <li><a href="#">{l s='Equipements pilotes' d='Shop.Theme.Global'}</a></li>
            <li><a href="#">{l s='Pièces d\'origines' d='Shop.Theme.Global'}</a></li>
            <li><a href="#">{l s='Accessoires powerparts' d='Shop.Theme.Global'}</a></li>
            <li><a href="#">{l s='Sportswear' d='Shop.Theme.Global'}</a></li>
          </ul>
        </div>

        {* Colonne 4 — Newsletter *}
        <div class="ms-footer__newsletter">
          <h3 class="ms-footer__title">{l s='NEWSLETTER' d='Shop.Theme.Global'}</h3>
          <p class="ms-footer__newsletter-desc">{l s='-10% sur votre première commande en vous inscrivant à la newsletter Mega Service' d='Shop.Theme.Global'}</p>
          <form class="ms-footer__newsletter-form" action="{$urls.pages.index}" method="post">
            <input type="email" name="email" class="ms-footer__newsletter-input" placeholder="email@address.com" aria-label="{l s='Votre adresse email' d='Shop.Theme.Global'}">
            <button type="submit" class="ms-footer__newsletter-btn">{l s='JE M\'INSCRIS' d='Shop.Theme.Global'}</button>
            <label class="ms-footer__newsletter-gdpr">
              <input type="checkbox" name="gdpr" required>
              <span>{l s='Je consens au traitement de mes données personnelles par Mega Service Shop.' d='Shop.Theme.Global'} <a href="#">{l s='Voir la politique de données personnelles' d='Shop.Theme.Global'}</a></span>
            </label>
          </form>
        </div>

      </div>
    </div>
  </div>

  {* ── Barre légale ── *}
  <div class="ms-footer__bottom">
    <div class="ms-footer__container">
      <ul class="ms-footer__legal">
        <li><a href="#">{l s='Conditions générales de vente' d='Shop.Theme.Global'}</a></li>
        <li><a href="#">{l s='Mentions légales' d='Shop.Theme.Global'}</a></li>
        <li><a href="#">{l s='Politique de confidentialité' d='Shop.Theme.Global'}</a></li>
        <li><a href="#">{l s='Vie privée et cookies' d='Shop.Theme.Global'}</a></li>
        <li><a href="{$urls.pages.contact}">{l s='Contact' d='Shop.Theme.Global'}</a></li>
      </ul>
    </div>
  </div>

</footer>
{/block}
