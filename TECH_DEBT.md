# Dette technique — Megaservice Theme

Liste vivante des hacks, raccourcis, et optimisations à reprendre quand le projet aura le temps. Ajouter une entrée à chaque fois qu'on choisit pragma sur clean.

**Format** : titre, contexte, impact, fix proposé, statut.

---

## 🟠 Module Powerparts — panneau BO injecté en JS au lieu d'un onglet Symfony natif

**Fichier** : [modules/megaservice_relations/views/js/admin-product-relations.js](modules/megaservice_relations/views/js/admin-product-relations.js)

**Contexte** : Le panneau "Relations Powerparts" est rendu via une injection JS depuis le hook `displayBackOfficeHeader`. Le JS détecte la page produit via URL (`/products-v2/<id>/edit`), fetch les données via AJAX, et injecte le HTML dans le DOM sous le bloc "Produits associés".

Pourquoi : les hooks "Step" (`displayAdminProductsMainStepLeftColumnBottom` etc.) ne dispatchent pas vers les modules legacy dans la page produit moderne PS 8 — `LegacyHookSubscriber` a un comportement inconsistant. On a tenté d'injecter un fake onglet dans la nav Vue mais c'était un hack fragile.

**Impact** : Fonctionne, mais :
- Le panneau n'est pas un vrai onglet natif (UX inférieure : pas dans la nav, faut scroller)
- Dépend du DOM de la page produit (selectors `#related-product` etc.) — peut casser à la prochaine montée de version PS
- Le module ajoute une requête AJAX au load de chaque page produit BO (pas que Powerparts) pour le check `getState`

**Fix proposé** : Symfony Form Type Extension (option A) — la VRAIE manière PS 8 :
1. `config/services.yml` enregistrant l'extension comme service
2. `MegaserviceProductFormTypeExtension extends AbstractTypeExtension` qui ajoute une section au form Product
3. `ProductFormDataProvider` qui charge nos données dans le form
4. Twig template qui rend le panneau dans la nav d'onglets native (Vue gère la réactivité)
5. Wire form submission

**Effort** : ~2h de session active (1h best case, 3h si on tombe sur des bugs PS 8). Investissement qui paie sur le long terme : zéro hack, zéro fragilité aux montées de version PS, pattern réutilisable pour de futurs modules.

**Statut** : 🟠 noté — à faire quand on aura un créneau dédié.

---

## 🟡 Fake data fallback dans ProductController override

**Fichier** : [override/controllers/front/ProductController.php](override/controllers/front/ProductController.php)

**Contexte** : Quand un produit Powerparts n'a aucune relation définie en BDD, le controller retombe sur de la fake data (12 produits aléatoires du shop) pour garder du visuel pendant l'intégration.

**Impact** : Pendant qu'on saisit les vraies relations en BO, certaines pages produit affichent des relations bidon. Pas grave en dev, mais à retirer en prod.

**Fix proposé** : Supprimer les méthodes `fetchFakeProductIds()`, `presentProductsByIds()`, `buildSpareRows()` et le fallback dans `assignPowerpartsTabs()`. Une fois retiré, les tabs vides afficheront simplement "Aucune pièce..." comme prévu.

**Statut** : 🟡 à retirer quand toutes les relations Powerparts seront en BDD.

---

## 🟢 EventEmitter défensif sur `window.prestashop`

**Fichier** : [megaservice/assets/js/app.js](megaservice/assets/js/app.js#L13)

**Contexte** : On initialise un EventEmitter custom sur `window.prestashop` uniquement si PS ne l'a pas déjà fait. Workaround pour des cas où le système d'events PS n'est pas disponible (ex : les filtres ps_facetedsearch qui en dépendent).

**Impact** : Aucun bug actuel, mais une nouvelle version de PS pourrait gérer ça nativement et notre shim deviendrait redondant.

**Fix proposé** : à terme, vérifier si on peut le retirer (test : enlever le shim, voir si les events PS fonctionnent toujours).

**Statut** : 🟢 monitoring — à recontrôler à chaque montée de version PS.

---

## 🟡 SQL inline avec interpolation au lieu de prepared statements

**Fichiers** : plusieurs (overrides + module)

**Contexte** : Toutes les requêtes SQL custom utilisent l'API legacy `Db::getInstance()->getValue()` / `executeS()` avec interpolation directe via `(int)` et `pSQL()`. PS supporte aussi un fluent builder `DbQuery` plus moderne.

**Impact** : Sécurité OK (les casts/escapes sont là), mais lisibilité moindre et plus exposé aux fautes de frappe (ex : oublier un `pSQL()` introduit une SQL injection).

**Fix proposé** : migrer vers `DbQuery` builder ou Doctrine DBAL pour les requêtes custom non-triviales.

**Statut** : 🟡 lift-and-shift quand on touchera ces fichiers.

---

## 🟡 `isProductInPowerpartsSubtree` dupliqué + `$POWERPARTS_ROOT_IDS` en dur

**Fichiers** :
- [override/controllers/front/ProductController.php](override/controllers/front/ProductController.php)
- [modules/megaservice_relations/megaservice_relations.php](modules/megaservice_relations/megaservice_relations.php)

**Contexte** : La logique "ce produit appartient-il à la sous-arborescence Powerparts ?" est dupliquée dans deux fichiers, avec une constante `$POWERPARTS_ROOT_IDS = [41]` également dupliquée. Si on ajoute une nouvelle catégorie racine demain, faut modifier deux endroits.

**Impact** : DRY violation, risque d'incohérence (une racine ajoutée d'un côté mais pas de l'autre = comportement différent front vs BO).

**Fix proposé** :
- Centraliser la logique dans `MsProductRelationService::isInPowerpartsSubtree($idProduct)`
- Le ProductController override appelle ce service via `loadRelationService()` (déjà en place)
- Exposer `$POWERPARTS_ROOT_IDS` via `Configuration::get('MS_POWERPARTS_ROOT_IDS')` configurable depuis le BO

**Effort** : 30 min.

**Statut** : 🟡 cleanup mécanique, à faire quand on touchera l'un des deux fichiers.

---

## 🟠 Wishlist delete bypasse le modal Delete du module blockwishlist

**Fichier** : [megaservice/assets/js/wishlist.js](megaservice/assets/js/wishlist.js)

**Contexte** : Sur la page wishlist view, le bouton poubelle de chaque item devrait normalement émettre un event `showDeleteWishlist` capté par un composant Vue `Delete` qui ouvre une modal de confirmation. Mais ce composant n'est pas monté sur cette page (l'override template `products-list.tpl` n'inclut pas la modal).

On contourne avec un handler JS qui fait du direct-AJAX vers `deleteProductFromWishList` (sans confirmation modale).

**Impact** :
- Si le module blockwishlist se met à jour et change l'API endpoint ou le format de params, on doit réadapter
- Pas de UX de confirmation avant suppression (clic = produit perdu si erreur)
- Extraction de `id_product` par regex sur l'URL — fragile (cf. entrée séparée ci-dessous)

**Fix proposé** :
- Override du template `products-list.tpl` pour inclure le composant Delete du module
- OU monter notre propre modal de confirmation custom
- OU au minimum ajouter un `confirm()` natif avant l'AJAX

**Statut** : 🟠 important si le module se met à jour ou si tu veux la confirmation UX.

---

## 🟠 Module admin AJAX endpoint via front controller + cookie auth

**Fichier** : [modules/megaservice_relations/controllers/front/admin.php](modules/megaservice_relations/controllers/front/admin.php)

**Contexte** : Pour les actions AJAX du panneau BO Powerparts (search products, save relations, getState), on utilise un **front module controller** avec auth via `Cookie('psAdmin')->id_employee`. C'est le pattern que utilise le module `an_sidebarcart` aussi.

Cleaner : un vrai Admin Module Controller PS avec sécurité Symfony native (annotations `@AdminSecurity`, services container, etc.).

**Impact** :
- L'auth via cookie psAdmin est valable mais moins robuste que la sécurité Symfony native (pas de CSRF token sur la requête, pas de vérification fine des permissions employee)
- L'URL `/index.php?fc=module&module=...&controller=admin` pollue les routes front
- Lié à la migration option A (Symfony Form Type Extension)

**Fix proposé** : créer `modules/megaservice_relations/controllers/admin/AdminMegaserviceRelationsController.php` avec routes Symfony, security annotations, Twig templates.

**Effort** : ~1h, à faire en même temps que la migration A.

**Statut** : 🟠 important pour la sécurité long terme — à coupler avec la migration option A.

---

## 🟢 Pattern CSS override systematique pour modules à bundle Vue/React

**Fichiers** : [megaservice/assets/scss/components/_sidebar-cart.scss](megaservice/assets/scss/components/_sidebar-cart.scss), [megaservice/assets/scss/components/_wishlist.scss](megaservice/assets/scss/components/_wishlist.scss)

**Contexte** : Les modules PS modernes (an_sidebarcart, blockwishlist) compilent leurs composants Vue/React via vue-style-loader et **injectent leur CSS dans le `<head>` à runtime** après le chargement du theme.css. La cascade CSS s'applique propriété par propriété, donc même avec une spécificité supérieure, si on n'override pas EXPLICITEMENT chaque propriété déclarée par le bundle, leurs valeurs passent.

→ Notre SCSS pour ces modules contient beaucoup de redéclarations explicites de `flex-direction`, `width`, `max-width`, `position`, `margin`, etc.

**Impact** :
- Pas vraiment fixable côté thème (c'est inhérent au bundling Vue)
- Mais documenté dans `~/.claude/projects/.../memory/feedback_ps_module_vue_bundles.md` pour pas se faire piéger à nouveau
- À chaque nouveau module à styler avec un bundle, faut dump leurs règles CSS et override propriété par propriété

**Statut** : 🟢 monitoring — pattern à reconnaître quand on intègre un nouveau module.

---

## 🟡 Extraction `id_product` par regex sur l'URL produit

**Fichier** : [megaservice/assets/js/wishlist.js](megaservice/assets/js/wishlist.js)

**Contexte** : Dans le handler delete, on récupère l'`id_product` du produit à supprimer en regexant l'URL du link `/(\d+)-[^\/]+\.html`. C'était la solution rapide quand on a découvert que Vue avait viré le `data-list-id` original.

**Impact** :
- Dépend du format d'URL PS standard (`<id>-<slug>.html`)
- Si tu actives un autre format d'URL ou un module qui réécrit, ça casse silencieusement (delete ne marche plus, sans message d'erreur clair)

**Fix proposé** : ajouter un attribut `data-id-product` sur l'élément `.cart-product-line` côté template du module wishlist (override). Le JS lit l'attribut directement.

**Effort** : 15 min.

**Statut** : 🟡 à fixer la prochaine fois qu'on touche le wishlist.

---

## 🟢 `find -delete || true` dans deploy.yml clear cache

**Fichier** : [.github/workflows/deploy.yml](.github/workflows/deploy.yml)

**Contexte** : Le step "Clear PrestaShop cache" utilise `find ... -delete || true` pour vider `var/cache/`. Le `|| true` empêche le step d'échouer en cas de race condition avec PHP-FPM qui écrit pendant qu'on supprime.

**Impact** : `|| true` avale aussi les VRAIES erreurs (permissions cassées, chemin manquant, SSH down). Si un jour le cache n'est plus vidé, on ne le verra pas dans le CI.

**Fix proposé** : remplacer par un script qui ignore SEULEMENT les erreurs "Directory not empty" / EBUSY :
```bash
find ... -delete 2>&1 | grep -v 'Directory not empty' || true
```
ou utiliser un endpoint PHP qui clear via `Tools::clearCache()` (plus robuste).

**Statut** : 🟢 monitoring — pas grave en l'état mais à reprendre si on a un jour un bug de cache non vidé.
