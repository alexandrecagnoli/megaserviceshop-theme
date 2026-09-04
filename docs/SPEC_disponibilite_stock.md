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
