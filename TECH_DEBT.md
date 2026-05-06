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
