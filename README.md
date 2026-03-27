# Megaservice Theme

Un thème PrestaShop moderne et modulaire construit avec SASS, Webpack et une architecture propre.

## 🎯 Caractéristiques

- **Architecture ITCSS** - Organisation scalable et maintenable
- **Système de variables centralisé** - Couleurs, espacements, typographie
- **Mixins réutilisables** - Media queries, utilitaires flexbox, transitions
- **Responsive design** - Breakpoints prédéfinis (xs, sm, md, lg, xl, xxl)
- **Build optimisé** - Webpack avec extraction CSS et minification
- **Support Babel** - ES6+ transpilé pour la compatibilité

## 📁 Structure du projet

```
megaservice-theme/
├── megaservice/
│   ├── assets/
│   │   ├── css/          # CSS compilé (généré)
│   │   ├── dist/         # Build output (généré)
│   │   ├── js/
│   │   │   ├── app.js    # JavaScript principal
│   │   │   └── theme.js
│   │   └── scss/
│   │       ├── app.scss  # Point d'entrée SCSS
│   │       ├── base/     # Reset, typographie
│   │       ├── components/ # Boutons, formulaires
│   │       ├── layout/   # Header, footer, layout
│   │       ├── pages/    # Styles spécifiques aux pages
│   │       └── settings/ # Variables, mixins
│   ├── config/
│   │   └── theme.yml     # Configuration du thème
│   └── templates/        # Templates PrestaShop (.tpl)
├── webpack.config.js     # Configuration Webpack
├── package.json          # Dépendances npm
└── README.md             # Ce fichier
```

## 🚀 Installation

### Prérequis
- Node.js >= 14
- npm >= 6

### Setup

```bash
# Installer les dépendances
npm install

# Démarrer le développement (watch mode)
npm run dev

# Build de production
npm run build
```

## 📝 Variables SCSS

Les variables sont centralisées dans [megaservice/assets/scss/settings/_variables.scss](megaservice/assets/scss/settings/_variables.scss).

### Couleurs
```scss
$color-primary: #0059ff;
$color-secondary: #ff5a5f;
$color-text: #222;
$color-bg: #f5f5f5;
```

### Breakpoints
```scss
$breakpoint-sm: 576px;
$breakpoint-md: 768px;
$breakpoint-lg: 992px;
$breakpoint-xl: 1200px;
```

### Espacement
```scss
$spacing-xs: 0.25rem;
$spacing-sm: 0.5rem;
$spacing-md: 1rem;
$spacing-lg: 1.5rem;
$spacing-xl: 2rem;
```

## 🎨 Utiliser les Mixins

### Media Queries
```scss
@include media-md() {
  // Styles pour mobile (max-width: 768px)
}

@include media-up-lg() {
  // Styles pour desktop (min-width: 992px)
}
```

### Utilitaires
```scss
// Flexbox centr\u00e9
@include flex-center();

// Flexbox entre
@include flex-between();

// Transition fluide
@include transition(color, opacity);

// Focus ring pour l'accessibilité
@include focus-ring();
```

## 💻 Composants disponibles

### Boutons

```html
<button class="btn btn-primary">Bouton primaire</button>
<button class="btn btn-secondary">Bouton secondaire</button>
<button class="btn btn-outline">Bouton outline</button>
<button class="btn btn-ghost">Bouton ghost</button>

<!-- Tailles -->
<button class="btn btn-sm">Petit</button>
<button class="btn btn-lg">Grand</button>

<!-- Full-width -->
<button class="btn btn-block">Pleine largeur</button>
```

### Formulaires

```html
<form>
  <div class="form-group">
    <label for="email">Email</label>
    <input type="email" id="email" required>
    <div class="form-help">Nous ne partagerons jamais votre email</div>
  </div>

  <div class="form-group">
    <label for="message">Message</label>
    <textarea id="message" required></textarea>
  </div>

  <button type="submit" class="btn btn-primary">Envoyer</button>
</form>
```

## 🔧 Configuration Webpack

Le fichier [webpack.config.js](webpack.config.js) est préconfiguré pour :
- Compiler SCSS en CSS
- Extraire le CSS dans des fichiers `.css` séparés
- Transpiler JavaScript ES6+ avec Babel
- Générer les assets dans `megaservice/assets/dist/`

## 📚 Conventions de codage

### SCSS
- Utiliser les variables pour les valeurs répétées
- Préférer les mixins aux valeurs hardcodées
- Suivre l'architecture ITCSS (Settings → Base → Layout → Components → Pages)
- Organiser le code par fonctionnalité

### JavaScript
- Utiliser le strict mode
- Initialiser les composants dans des fonctions nommées
- Ajouter des commentaires JSDoc

## 🔗 Ressources

- [Sass Documentation](https://sass-lang.com/documentation)
- [Webpack Documentation](https://webpack.js.org/)
- [ITCSS Architecture](https://itcss.io/)

## 📄 License

MIT
