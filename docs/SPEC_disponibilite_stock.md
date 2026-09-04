# Règle d'affichage de la disponibilité et du délai de livraison

**Statut** : à jour au 04/09/2026 — cette version **remplace** la précédente. 1 point reste en attente d'arbitrage COPROJ (§5, cas 4).
**Historique** : la version initiale (colonnes à identifier) est dans l'historique git de ce fichier si besoin d'audit.

---

## 1. Ce qui est fait et vérifié — ne pas retoucher

Le cron du plugin **Advance Importing Pro** (⚠️ distinct de `ba_importer`, cf. [TECH_DEBT.md](../TECH_DEBT.md) — deux plugins d'import différents, ne pas confondre) écrit déjà, sur les produits **et** les déclinaisons, par référence :

| Colonne CSV constructeur | Colonne PrestaShop native |
|---|---|
| `SalesPrice` | prix HT |
| `hqETADate` | `available_date` |
| `StockAvailable` | `available_for_order` (1 si `StockAvailable > 0`, sinon 0) |

Réglage boutique posé : **`PS_ORDER_OUT_OF_STOCK = 1`** (autorise les commandes hors stock magasin par défaut). C'est ce réglage + `available_for_order` qui pilotent la commandabilité — **`out_of_stock` n'est plus écrit**, il reste à sa valeur par défaut (2) et hérite du réglage boutique.

### Vérifié en base sur l'import KTM (47 916 produits)

- 2 815 produits avec `available_date` renseignée → tous `available_for_order = 1`
- 1 236 produits sans stock constructeur / sans date → `available_for_order = 0`
- `quantity` (stock magasin) non touchée par cet import — confirmé à 0 partout sauf 1 produit, cohérent avec l'absence d'intégration G8 à ce jour.

---

## 2. Le modèle simplifié — 3 champs PrestaShop, 2 sources

| Source | Écrit | Jamais touché ici |
|---|---|---|
| G8 (stock magasin) | `quantity` | — (pas encore intégré) |
| CSV constructeur (cron Advance Importing Pro) | `available_for_order`, `available_date`, prix | `quantity`, nom, description, catégories, images |

---

## 3. Les 4 cas cibles

### 1. `quantity` (magasin) > 0
Commandable, peu importe le reste. **Prioritaire sur tout.**
**Pas encore actif** : dépend de l'intégration G8. Point d'extension à prévoir dans le code, mais **aucune logique active à coder maintenant** — `quantity` vaut 0 partout tant que G8 n'est pas branché.

### 2. `quantity = 0`, `StockAvailable` (constructeur) `> 0`
`available_for_order = 1`, `available_date = hqETADate`. Commandable, délai affiché via `available_date`.
**Déjà correctement géré par le cron du plugin existant.**

### 3. `quantity = 0`, `StockAvailable = 0`, pas de `hqETADate`
`available_for_order = 0`. Non commandable, aucune promesse de délai.
**Déjà correctement géré par le cron du plugin existant.**

### 4. `quantity = 0`, `StockAvailable = 0`, MAIS `hqETADate` renseignée
Comportement actuel (par défaut du mapping) : `available_for_order = 0` → **non commandable**, mais la date peut être présente en base.

**À trancher au prochain COPROJ client** (déjà tracé) : ce produit doit-il rester non commandable comme aujourd'hui, ou passer commandable en précommande sur cette date ?

**Ne rien coder de plus sur ce cas avant l'arbitrage** — le comportement actuel (bloqué) est un choix par défaut sûr, pas un manque à corriger dans l'urgence.

---

## 4. Hors scope de cette règle

- **Statut constructeur** (`ArticleStatus` 20/70/80 → actif/visibilité) — chantier séparé, pas encore développé. Ne pas mélanger à la disponibilité : la disponibilité dit *peut-on l'acheter*, le statut dit *le produit existe-t-il encore au catalogue constructeur*. Indépendants dans le code.
- **Libellés client** (« Texte affiché lorsqu'en stock » / « Texte affiché lorsque la commande en attente est autorisée ») — réglage thème/boutique à vérifier séparément, hors de ce brief.

## 5. Prochaine étape

Import identique en cours sur HQV et GASGAS (même mapping plugin). Même vérification à faire une fois terminé (répartition `available_for_order` vs `available_date`, `quantity` intacte).

## 6. Ne pas faire

- Ne pas écrire `out_of_stock`.
- Ne pas coder la branche « stock magasin G8 » comme logique active.
- Ne pas décider seul du cas 4 — arbitrage client en attente.

---

## 7. État de l'implémentation thème (04/09/2026)

Le code déployé (commit `0e10fdb`, card + fiche produit) est **déjà aligné** avec cette version — aucune modification requise. Il ne lit ni `out_of_stock` ni `available_for_order` directement : il s'appuie sur **`$product.add_to_cart_url`**, que PrestaShop calcule nativement en combinant `available_for_order`, le stock et `PS_ORDER_OUT_OF_STOCK`. Correspondance avec les 4 cas :

| Cas du brief | État thème (`msAvailability`) | Comportement actuel |
|---|---|---|
| 1 — stock magasin | `available` (natif PS, rien codé côté thème) | correct, inactif tant que G8 n'est pas branché |
| 2 — stock constructeur, commandable | `backorder` | « En stock constructeur — réassort le [date] » |
| 3 — épuisé, pas de date | `out_of_stock` | « Épuisé » |
| 4 — épuisé, date connue, non commandable | `out_of_stock` | « Épuisé — réassort prévu le [date] » (informatif, ne rend pas commandable) |

Voir `megaservice/templates/catalog/_partials/miniatures/product.tpl` et `megaservice/templates/catalog/product.tpl`.

---

## 8. Bug ouvert (04/09/2026) — bouton et texte ignorent `available_for_order`

**Constat client** : produit `quantity=0`, `available_for_order=1` en base → bouton grisé, texte « Épuisé » quand même. Rapporté comme bloquant.

**Investigation thème (faite)** : ni `override/controllers/front/ProductController.php`, ni `override/controllers/front/CategoryController.php`, ni aucun module custom, ni le JS (`product.js`) ne recalculent ou n'interfèrent avec `$product.add_to_cart_url`. Le template le lit tel quel, des deux côtés (card et fiche produit). **La piste « override thème » est écartée.**

Conséquence directe : mon `msAvailability` (commit `0e10fdb`) tombe sur `out_of_stock` exactement quand `add_to_cart_url` est vide — le symptôme observé est cohérent avec un `add_to_cart_url` natif incorrect pour ce produit, pas avec un bug dans le branchement du template.

**Non vérifiable depuis cet environnement** : je n'ai ni accès SSH/BO à la preprod, ni dump plus récent que le 25/08 (antérieur à l'import KTM et au passage de `PS_ORDER_OUT_OF_STOCK` à 1). Impossible de confirmer la cause exacte sans accès à l'état actuel.

### Checklist à exécuter côté préprod pour isoler la cause

1. **Confirmer la config actuelle** : `SELECT value FROM ps_configuration WHERE name = 'PS_ORDER_OUT_OF_STOCK'` doit renvoyer `1`.
2. **Sur LE produit testé précisément**, en base :
   ```sql
   SELECT sa.out_of_stock, sa.quantity, p.available_for_order, p.available_date
   FROM ps_stock_available sa
   JOIN ps_product p ON p.id_product = sa.id_product
   WHERE sa.id_product = <ID_DU_PRODUIT_B> AND sa.id_product_attribute = 0;
   ```
   Si `out_of_stock` vaut `0` explicitement (et non `2`) sur ce produit → résidu d'un import antérieur à la bascule stratégie (avant l'arrêt d'écriture sur `out_of_stock`), à corriger en masse plutôt qu'unitairement.
3. **Vider le cache** (`var/cache`) après tout changement de config — un changement de `PS_ORDER_OUT_OF_STOCK` peut rester invisible si un cache de configuration persistant (Memcached/Redis/OPcache selon l'hébergement) n'a pas été purgé.
4. Si les deux points ci-dessus sont conformes et que le bouton reste grisé quand même : le calcul natif PS du bouton dépend d'autre chose que je n'ai pas identifié sans le code du core — remonter le résultat de la requête ci-dessus pour investigation plus poussée.

**Ne pas coder de correctif thème sur ce ticket tant que la cause réelle n'est pas isolée** — bypasser `add_to_cart_url` pour calculer nous-mêmes la commandabilité contournerait une logique de sécurité native de PrestaShop, risque à ne pas prendre sans certitude sur la cause.

---

## 9. Cause identifiée (04/09/2026) — `show_price = 0`, pas un problème de code

Accès SSH diagnostic obtenu, script `scripts/cli/debug_product_availability.php` exécuté en conditions réelles sur la préprod (produit 94479, référence `0000070405800`).

**Résultat mesuré, sans ambiguïté** :

```
ps_product.show_price sur tout le catalogue (47 916 produits) :
  show_price = 0  →  47 915 produits
  show_price = 1  →      1 produit  (id_product 96704, date_upd = 04/09 16:43 — édité pendant l'investigation, pas par l'import)

Parmi les produits available_for_order = 1 (censés vendables) :
  show_price = 0  →  46 679 produits
  show_price = 1  →      1 produit
```

`show_price` n'est **pas** qu'un calcul dynamique du Presenter — c'est une **colonne persistante** de `ps_product`/`ps_product_shop` (back-office : onglet Prix de la fiche produit, case « Afficher le prix »). Native PrestaShop, quand elle est à 0 : **prix ET bouton d'achat masqués**, indépendamment de `available_for_order`, `out_of_stock` ou du stock. C'est la cause du bug §8, et elle explique le comportement observé sur la quasi-totalité du catalogue, pas un cas isolé.

**Non confirmé techniquement** : le lien exact dans le code du Presenter (`ProductAssembler` + `ProductListingPresenter`) n'a pas pu être vérifié — l'appel plante en CLI sur un contexte de devise/panier incomplet (`ComputingPrecision::getPrecision()`, `ProductSearchContext.php:72-73`), non résolu malgré une tentative de fix du bootstrap. Le diagnostic s'appuie donc sur la mesure en base + la sémantique documentée du champ back-office, pas sur une trace directe du calcul interne.

**Origine du `0` — à trancher, pas déductible du code** :
1. Le cron Advance Importing Pro écrit aussi `show_price` (à 0), en plus des colonnes déjà documentées (§1) — non mentionné dans le brief initial.
2. `show_price = 0` est la valeur par défaut de tout produit créé par import, et seule une édition manuelle en BO le passe à 1 (cohérent avec le seul produit à `1`, modifié aujourd'hui).

**Pas de correctif appliqué.** Passer `show_price = 1` en masse sur ~47 000 produits est une action à fort impact (rend le prix et l'achat visibles sur tout le catalogue d'un coup) — à valider avant exécution, pas une décision à prendre seul depuis ce diagnostic.
