{* ── Sélecteur de moto — Modal desktop / Bottomsheet mobile ── *}

{* Overlay *}
<div class="ms-model-overlay js-model-overlay" hidden aria-hidden="true"></div>

{* Modal (desktop) / Bottomsheet (mobile) *}
<div class="ms-model-modal js-model-modal" hidden role="dialog" aria-modal="true">

  {* ── Col gauche : image + texte (desktop uniquement) ── *}
  <div class="ms-model-modal__visual">
    <div class="ms-model-modal__visual-content">
      <h2 class="ms-model-modal__title">{l s='Recherche de pièces compatibles' d='Shop.Theme.Global'}</h2>
      <p class="ms-model-modal__desc">{l s='Recherchez des pièces détachées pour partie cycle, partie moteur et accessoires powerparts compatible avec votre moto KTM, Husqvarna et GasGas parmi plus de 40 000 références.' d='Shop.Theme.Global'}</p>
    </div>
  </div>

  {* ── Col droite : panel onglets + formulaires ── *}
  <div class="ms-model-modal__panel">

    {* Tabs *}
    <div class="ms-model-modal__tabs">
      <button type="button" class="ms-model-modal__tab is-active" data-tab="model">
        <svg width="36" height="25" viewBox="0 0 44 30" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <path d="M6.85791 13.992C8.08425 13.992 9.23155 14.3331 10.2129 14.9262C9.65792 14.0542 8.85325 13.3551 7.88603 12.9356L1.99282 10.3797C1.54523 10.1856 1.18562 9.82941 0.98442 9.38103L-4.42528e-07 7.18714C6.0623 5.76846 8.60376 6.94625 8.60376 6.94625C8.60376 6.94625 9.39799 7.99019 11.9658 9.43554C14.5337 10.881 17.3929 10.0512 17.3929 10.0512C17.3929 10.0512 20.0667 6.62498 21.5908 6.49114C23.115 6.3573 24.3024 6.54472 27.4791 6.97292C30.656 7.40124 30.9022 8.93184 30.9022 8.93184C30.9022 8.93184 31.1264 8.91896 31.4757 8.89762C31.4757 8.89762 30.5871 7.04065 30.212 6.76845C29.8001 6.46957 29.1378 6.32285 28.9193 6.29188C28.5058 6.2354 28.2159 5.85081 28.2716 5.4327C28.3275 5.01459 28.7081 4.72197 29.1214 4.77787C29.2398 4.79399 30.3006 4.95231 31.0929 5.52722C31.9941 6.18124 33.1082 8.79231 33.1082 8.79231C34.0075 8.73061 34.9264 8.65998 35.3151 8.60558C36.247 8.4751 36.41 7.5113 35.2648 6.86529C34.5195 6.44486 33.7859 6.02768 33.1826 5.68345C32.3728 5.22138 32.0596 4.2011 32.4687 3.35664L33.0119 2.23511C33.0119 2.23511 38.6882 4.97689 41.0068 8.65917C43.0072 11.8362 41.9735 13.5101 41.9735 13.5101L39.8367 13.1499L38.6647 14.1057C41.7017 14.6812 44 17.3757 44 20.6134C44 24.2704 41.068 27.2351 37.451 27.2351C33.834 27.2351 30.902 24.2705 30.902 20.6134C30.902 20.5536 30.9029 20.494 30.9044 20.4346L29.8189 21.3199L30.6742 24.4248L12.8914 23.1922C11.8969 25.5681 9.56982 27.2351 6.85768 27.2351C3.24083 27.2351 0.308679 24.2705 0.308679 20.6134C0.308679 16.9563 3.24072 13.9917 6.85768 13.9917L6.85791 13.992ZM37.4512 24.762C39.7135 24.762 41.5541 22.9011 41.5541 20.6136C41.5541 18.3262 39.7136 16.4653 37.4512 16.4653C37.1489 16.4653 36.8544 16.4995 36.5704 16.5626L37.0117 17.553C37.1553 17.5322 37.3019 17.5208 37.4511 17.5208C39.1405 17.5208 40.51 18.9055 40.51 20.6136C40.51 22.3218 39.1405 23.7065 37.4511 23.7065C35.7617 23.7065 34.3922 22.3218 34.3922 20.6136C34.3922 19.6095 34.8658 18.7175 35.5993 18.1524L35.1626 17.1723C34.069 17.9181 33.3484 19.1823 33.3484 20.6136C33.3484 22.901 35.1889 24.762 37.4512 24.762ZM6.85791 24.762C8.1642 24.762 9.32894 24.1409 10.0808 23.1761L6.85791 23.1578C5.46834 23.1578 4.3418 22.0188 4.3418 20.6138C4.3418 19.2088 5.46834 18.0697 6.85791 18.0697H10.0951C9.34374 17.0944 8.17246 16.4655 6.85791 16.4655C4.59565 16.4655 2.75505 18.3264 2.75505 20.6139C2.75505 22.9014 4.59553 24.7623 6.85791 24.7623V24.762Z" fill="currentColor"/>
        </svg>
        <span>{l s='Recherche' d='Shop.Theme.Global'}<br><strong>{l s='par modèle' d='Shop.Theme.Global'}</strong></span>
      </button>
      <button type="button" class="ms-model-modal__tab" data-tab="vin">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="26" viewBox="0 0 25 23" fill="none" aria-hidden="true">
          <path d="M2.5 2.5H7.5V0H2.5C1.12125 0 0 1.12125 0 2.5V7.5H2.5V2.5ZM2.5 22.5H7.5V20H2.5V15H0V20C0 21.3788 1.12125 22.5 2.5 22.5ZM22.5 0H17.5V2.5H22.5V7.5H25V2.5C25 1.12125 23.8788 0 22.5 0ZM22.5 20H17.5V22.5H22.5C23.8788 22.5 25 21.3788 25 20V15H22.5V20Z" fill="currentColor"/>
        </svg>
        <span>{l s='Recherche' d='Shop.Theme.Global'}<br><strong>{l s='par VIN' d='Shop.Theme.Global'}</strong></span>
      </button>
    </div>

    <div class="ms-model-modal__forms-wrap">

    {* Form : recherche par modèle *}
    <form class="ms-model-modal__form is-active" data-form="model" action="#" method="get">

      <div class="ms-model-modal__fields js-model-fields">
        <div class="ms-model-modal__field">
          <select class="ms-model-modal__select js-model-select" name="marque">
            <option value="" disabled selected>{l s='Marque' d='Shop.Theme.Global'}</option>
            <option value="ktm">KTM</option>
            <option value="husqvarna">Husqvarna</option>
            <option value="gasgas">GasGas</option>
          </select>
          <svg class="ms-model-modal__chevron" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M6 9L12 15L18 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <div class="ms-model-modal__field">
          <select class="ms-model-modal__select js-model-select" name="annee" disabled>
            <option value="" disabled selected>{l s='Année' d='Shop.Theme.Global'}</option>
          </select>
          <svg class="ms-model-modal__chevron" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M6 9L12 15L18 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <div class="ms-model-modal__field">
          <select class="ms-model-modal__select js-model-select" name="gamme" disabled>
            <option value="" disabled selected>{l s='Gamme' d='Shop.Theme.Global'}</option>
          </select>
          <svg class="ms-model-modal__chevron" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M6 9L12 15L18 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <div class="ms-model-modal__field">
          <select class="ms-model-modal__select js-model-select" name="modele" disabled>
            <option value="" disabled selected>{l s='Modèle' d='Shop.Theme.Global'}</option>
          </select>
          <svg class="ms-model-modal__chevron" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M6 9L12 15L18 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
      </div>

      <button type="submit" class="ms-model-modal__btn-primary js-model-submit" disabled>
        {l s='Afficher les pièces compatibles' d='Shop.Theme.Global'}
        <svg class="ms-model-modal__btn-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M21 21L16.514 16.506M19 10.5C19 15.194 15.194 19 10.5 19C5.806 19 2 15.194 2 10.5C2 5.806 5.806 2 10.5 2C15.194 2 19 5.806 19 10.5Z" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
      </button>
      <button type="button" class="ms-model-modal__btn-secondary js-model-reset">
        {l s='Réinitialiser' d='Shop.Theme.Global'}
      </button>

    </form>

    {* Form : recherche par VIN *}
    <form class="ms-model-modal__form" data-form="vin" action="#" method="get">

      <div class="ms-model-modal__vin-field js-vin-fields">
        <input type="text" class="ms-model-modal__vin-input js-vin-input" name="vin" placeholder="{l s='Saisissez votre VIN' d='Shop.Theme.Global'}">
        <button type="button" class="ms-model-modal__vin-search js-vin-search" aria-label="{l s='Rechercher' d='Shop.Theme.Actions'}">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M21 21L16.514 16.506M19 10.5C19 15.194 15.194 19 10.5 19C5.806 19 2 15.194 2 10.5C2 5.806 5.806 2 10.5 2C15.194 2 19 5.806 19 10.5Z" stroke="#1E1E1E" stroke-width="2.5" stroke-linecap="round"/>
          </svg>
        </button>
      </div>

      {* Bloc résultat — visible après recherche VIN réussie *}
      <div class="ms-model-modal__result js-vin-result" hidden>
        <p class="ms-model-modal__result-count">{l s='1 véhicule correspond à votre recherche' d='Shop.Theme.Global'}</p>
        <div class="ms-model-modal__result-card">
          <div class="ms-model-modal__result-card-left">
            <div class="ms-model-modal__result-icon">
              <svg width="32" height="22" viewBox="0 0 44 30" fill="none" aria-hidden="true">
                <path d="M6.85791 13.992C8.08425 13.992 9.23155 14.3331 10.2129 14.9262C9.65792 14.0542 8.85325 13.3551 7.88603 12.9356L1.99282 10.3797C1.54523 10.1856 1.18562 9.82941 0.98442 9.38103L-4.42528e-07 7.18714C6.0623 5.76846 8.60376 6.94625 8.60376 6.94625C8.60376 6.94625 9.39799 7.99019 11.9658 9.43554C14.5337 10.881 17.3929 10.0512 17.3929 10.0512C17.3929 10.0512 20.0667 6.62498 21.5908 6.49114C23.115 6.3573 24.3024 6.54472 27.4791 6.97292C30.656 7.40124 30.9022 8.93184 30.9022 8.93184C30.9022 8.93184 31.1264 8.91896 31.4757 8.89762C31.4757 8.89762 30.5871 7.04065 30.212 6.76845C29.8001 6.46957 29.1378 6.32285 28.9193 6.29188C28.5058 6.2354 28.2159 5.85081 28.2716 5.4327C28.3275 5.01459 28.7081 4.72197 29.1214 4.77787C29.2398 4.79399 30.3006 4.95231 31.0929 5.52722C31.9941 6.18124 33.1082 8.79231 33.1082 8.79231C34.0075 8.73061 34.9264 8.65998 35.3151 8.60558C36.247 8.4751 36.41 7.5113 35.2648 6.86529C34.5195 6.44486 33.7859 6.02768 33.1826 5.68345C32.3728 5.22138 32.0596 4.2011 32.4687 3.35664L33.0119 2.23511C33.0119 2.23511 38.6882 4.97689 41.0068 8.65917C43.0072 11.8362 41.9735 13.5101 41.9735 13.5101L39.8367 13.1499L38.6647 14.1057C41.7017 14.6812 44 17.3757 44 20.6134C44 24.2704 41.068 27.2351 37.451 27.2351C33.834 27.2351 30.902 24.2705 30.902 20.6134C30.902 20.5536 30.9029 20.494 30.9044 20.4346L29.8189 21.3199L30.6742 24.4248L12.8914 23.1922C11.8969 25.5681 9.56982 27.2351 6.85768 27.2351C3.24083 27.2351 0.308679 24.2705 0.308679 20.6134C0.308679 16.9563 3.24072 13.9917 6.85768 13.9917L6.85791 13.992Z" fill="#FE6604"/>
              </svg>
              <svg class="ms-model-modal__result-check" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="12" r="12" fill="#22C55E"/>
                <path d="M7 12L10.5 15.5L17 9" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            <div class="ms-model-modal__result-info">
              <strong class="js-vin-result-name"></strong>
              <span class="js-vin-result-vin"></span>
            </div>
          </div>
          <button type="button" class="ms-model-modal__result-remove js-vin-result-remove" aria-label="{l s='Supprimer' d='Shop.Theme.Actions'}">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </button>
        </div>
      </div>

      <button type="submit" class="ms-model-modal__btn-primary js-vin-submit" disabled>{l s='Afficher les pièces compatibles' d='Shop.Theme.Global'}</button>
      <button type="button" class="ms-model-modal__btn-secondary js-vin-reset">{l s='Réinitialiser' d='Shop.Theme.Global'}</button>

    </form>

    </div>{* fin forms-wrap *}

  </div>
</div>

{* ── Barre fixe mobile ── *}
<div class="ms-model-bar js-model-trigger-mobile" role="button" tabindex="0">
  {* État vide *}
  <div class="ms-model-bar__inner ms-model-bar__inner--empty">
    <div class="ms-model-bar__icon-wrap">
      <img src="{$urls.theme_assets}img/ico-bike.svg" alt="" width="46" height="46" aria-hidden="true">
      <span class="ms-model-bar__plus" aria-hidden="true">+</span>
    </div>
    <div class="ms-model-bar__text">
      <strong>{l s='Ajoutez votre moto' d='Shop.Theme.Global'}</strong>
      <span>{l s='pour afficher les pièces compatibles' d='Shop.Theme.Global'}</span>
    </div>
  </div>
  {* État rempli — visible quand .has-model *}
  <div class="ms-model-bar__inner ms-model-bar__inner--filled">
    <div class="ms-model-bar__icon-wrap">
      <img src="{$urls.theme_assets}img/ico-bike.svg" alt="" width="46" height="46" aria-hidden="true">
      <span class="ms-model-bar__check" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" width="10" height="8" viewBox="0 0 10 8" fill="none">
          <path d="M1 4.5L3.5 7L9 1.5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </span>
    </div>
    <div class="ms-model-bar__text">
      <span>{l s='Catalogue filtré sur' d='Shop.Theme.Global'}</span>
      <strong class="js-model-current-name"></strong>
    </div>
  </div>
</div>
