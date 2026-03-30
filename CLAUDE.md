# CLAUDE.md — Megaservice Theme

Ce fichier est lu automatiquement par Claude à chaque conversation.
Ne jamais modifier les assets ci-dessous sans accord explicite.

---

## Git workflow

### Règle absolue
**Un commit par composant/modification terminé, AVANT le deploy, toujours.**

### Granularité des commits
- Chaque composant, template, fichier SCSS ou JS modifié → commit dédié avec description explicite
- Exemples : `feat: footer — template HTML`, `feat: footer — scss`, `fix: carousel — hauteur mobile`
- Ça permet un `git checkout <hash> -- fichier` chirurgical sans toucher au reste

### Branches
- Petite modif / fix CSS → commit direct sur `main`
- Grosse feature (nouvelle page, nouveau composant complexe) → branche `feat/nom-feature`, merge sur `main` une fois validé visuellement

### En cas de régression
- Revenir à un fichier spécifique : `git checkout <hash> -- chemin/du/fichier`
- Revenir à un état complet : `git revert <hash>`

---

## SVG Icons — Header (NE PAS MODIFIER)

Ces SVGs sont fournis par le client. Toujours utiliser exactement ces codes.

### Recherche (30×30, stroke white)
```html
<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 30 30" fill="none" aria-hidden="true">
  <path d="M26.25 26.25L20.8125 20.8125M23.75 13.75C23.75 19.2728 19.2728 23.75 13.75 23.75C8.22715 23.75 3.75 19.2728 3.75 13.75C3.75 8.22715 8.22715 3.75 13.75 3.75C19.2728 3.75 23.75 8.22715 23.75 13.75Z" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
```

### Panier (30×30, fill white)
```html
<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 30 30" fill="none" aria-hidden="true">
  <path d="M27.2775 9.28875C27.0438 8.95125 26.66 8.75 26.25 8.75H9.16625L7.72375 5.2875C7.33375 4.3525 6.42875 3.75 5.41625 3.75H2.5V6.25H5.41625L11.3462 20.4813C11.54 20.9463 11.995 21.25 12.5 21.25H22.5C23.0212 21.25 23.4875 20.9262 23.6712 20.44L27.4212 10.44C27.565 10.055 27.5113 9.625 27.2775 9.28875ZM21.6337 18.75H13.3337L10.2087 11.25H24.4462L21.6337 18.75Z" fill="white"/>
  <path d="M13.125 26.25C14.1605 26.25 15 25.4105 15 24.375C15 23.3395 14.1605 22.5 13.125 22.5C12.0895 22.5 11.25 23.3395 11.25 24.375C11.25 25.4105 12.0895 26.25 13.125 26.25Z" fill="white"/>
  <path d="M21.875 26.25C22.9105 26.25 23.75 25.4105 23.75 24.375C23.75 23.3395 22.9105 22.5 21.875 22.5C20.8395 22.5 20 23.3395 20 24.375C20 25.4105 20.8395 26.25 21.875 26.25Z" fill="white"/>
</svg>
```

### Compte (30×30, fill white)
```html
<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 30 30" fill="none" aria-hidden="true">
  <path d="M15 2.5C11.5538 2.5 8.75 5.30375 8.75 8.75C8.75 12.1962 11.5538 15 15 15C18.4462 15 21.25 12.1962 21.25 8.75C21.25 5.30375 18.4462 2.5 15 2.5ZM15 12.5C12.9325 12.5 11.25 10.8175 11.25 8.75C11.25 6.6825 12.9325 5 15 5C17.0675 5 18.75 6.6825 18.75 8.75C18.75 10.8175 17.0675 12.5 15 12.5ZM26.25 26.25V25C26.25 20.1763 22.3237 16.25 17.5 16.25H12.5C7.675 16.25 3.75 20.1763 3.75 25V26.25H6.25V25C6.25 21.5537 9.05375 18.75 12.5 18.75H17.5C20.9463 18.75 23.75 21.5537 23.75 25V26.25H26.25Z" fill="white"/>
</svg>
```

### Service client — header desktop (24×24, fill white)
```html
<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
  <path fill-rule="evenodd" clip-rule="evenodd" d="M15.9514 13.4159C15.3994 13.4159 14.9464 12.9689 14.9464 12.4159C14.9464 11.8629 15.3894 11.4159 15.9424 11.4159H15.9514C16.5034 11.4159 16.9514 11.8629 16.9514 12.4159C16.9514 12.9689 16.5034 13.4159 15.9514 13.4159ZM11.9424 13.4159C11.3894 13.4159 10.9374 12.9689 10.9374 12.4159C10.9374 11.8629 11.3814 11.4159 11.9334 11.4159H11.9424C12.4944 11.4159 12.9424 11.8629 12.9424 12.4159C12.9424 12.9689 12.4944 13.4159 11.9424 13.4159ZM7.93337 13.4159C7.38137 13.4159 6.92937 12.9689 6.92937 12.4159C6.92937 11.8629 7.37137 11.4159 7.92437 11.4159H7.93337C8.48537 11.4159 8.93337 11.8629 8.93337 12.4159C8.93337 12.9689 8.48537 13.4159 7.93337 13.4159ZM19.4274 4.57593C17.4464 2.59493 14.8094 1.50293 12.0034 1.50293C9.19737 1.50293 6.56037 2.59493 4.57937 4.57593C1.47737 7.67893 0.631368 12.4409 2.45637 16.3749C2.48937 16.5369 2.40037 17.1959 2.33637 17.6779C2.09437 19.4839 1.97037 20.8099 2.58337 21.4239C3.19537 22.0369 4.52037 21.9129 6.32637 21.6699C6.80837 21.6059 7.47537 21.5199 7.57937 21.5309C8.98537 22.1819 10.4884 22.4969 11.9804 22.4969C14.7184 22.4969 17.4204 21.4349 19.4274 19.4269C23.5204 15.3319 23.5214 8.66993 19.4274 4.57593Z" fill="white"/>
</svg>
```

---

## Design system — valeurs figées

| Token | Valeur |
|---|---|
| `$color-ktm` | `#FE6604` |
| `$color-black` | `#0A0A0A` |
| Font heading | Blender Pro |
| Font main | Trade Gothic |

### Carousel hero
- Titre : Blender Pro 900, 64px desktop / 32px mobile, uppercase, white
- Sous-titre : Trade Gothic 700, 22px desktop / 17px mobile, white, text-align center
- Bouton CTA : height 50px, background $color-ktm, uppercase, bold
- Dots : 31×6px, border-radius 3px — inactif: rgba(white, 0.7) — actif: $color-ktm
- Hauteur : 580px desktop, 420px tablet, 425px mobile
- Mobile : pas de dots, gap 60px entre texte et bouton

### UI Kit — règle globale
- **Tous les boutons** : font-family Blender Pro, font-weight 900, text-transform uppercase

### Header desktop
- Height : 72px
- Background : $color-black
- Actions droite : Recherche / Panier / Compte — icône + label texte sous l'icône
- Service client : icône SVG fournie + numéro 01 34 86 49 26

### Sidebar menu
- Déclenché par `.ms-header__burger` → `#ms-sidebar`
- Logo MEGA + logos KTM/Husqvarna/GasGas dans le header du sidebar
- Liens administrables via back-office PrestaShop (ps_mainmenu)
- Footer : service client + bouton "MON COMPTE" outlined black

---

## Fichiers clés

| Rôle | Fichier |
|---|---|
| Template header | `megaservice/templates/_partials/header.tpl` |
| Template carousel | `megaservice/modules/ps_imageslider/views/templates/hook/slider.tpl` |
| Template searchbar | `megaservice/modules/ps_searchbar/ps_searchbar.tpl` |
| SCSS header | `megaservice/assets/scss/layout/_header.scss` |
| SCSS carousel | `megaservice/assets/scss/components/_carousel.scss` |
| SCSS menu sidebar | `megaservice/assets/scss/layout/_menu.scss` |
| JS carousel | `megaservice/assets/js/carousel.js` |
| JS menu | `megaservice/assets/js/menu.js` |
| Build output | `megaservice/assets/dist/` |
| Deploy | `bash deploy.sh` (full) ou `bash deploy-css.sh` (CSS only) |

## Build & Deploy — règle absolue

**TOUJOURS dans cet ordre :**
1. `npm run build`
2. `git add` + `git commit`
3. `bash deploy.sh`

**Ne jamais déployer sans commit préalable.** Si le build ou le commit échoue, ne pas déployer.
Ne jamais proposer un deploy si les changements ne sont pas commités.
