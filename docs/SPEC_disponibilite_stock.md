# Règle d'affichage de la disponibilité — données PrestaShop uniquement

**Statut** : Draft — bloqué sur 1 question ouverte + 1 point de localisation, voir §5 et §6.
**Date** : 04/09/2026

---

## 1. Les 3 données en jeu

| Donnée | Origine | Alimentation |
|---|---|---|
| `quantity` | stock magasin | **PrestaShop natif**, alimenté par **G8** |
| `StockAvailable` | stock constructeur | CSV constructeur, déjà géré par le script de mise à jour existant |
| `hqETADate` | date de réassort constructeur | CSV constructeur, déjà géré par le script de mise à jour existant |

Rien d'autre n'entre dans ce calcul. Pas de statut, pas de code constructeur.

## 2. Principe

Le produit reste **toujours commandable** tant qu'il y a du stock quelque part (magasin OU constructeur). Le stock à 0 en magasin ne bloque jamais l'achat si le constructeur peut fournir — seul le délai annoncé change.

## 3. Les affichages cibles

### 1. « En stock »
- Condition : `quantity > 0`
- Variante **« Derniers articles en stock »** : `quantity > 0` ET `quantity <= seuil` (seuil à définir, ex. 3)

### 2. « En stock constructeur »
- Condition : `quantity = 0` ET `StockAvailable > 0`
- Affiche le délai si `hqETADate` est renseignée (ex. « disponible sous X jours »)
- Commandable normalement.

### 3. « Épuisé »
- Condition : `quantity = 0` ET `StockAvailable = 0`
- Si `hqETADate` est présente malgré tout : afficher la date à titre indicatif (« épuisé — réassort prévu le [date] »)
- Si `hqETADate` est vide : « épuisé » sans date, sans promesse.
- **À trancher** : commandable quand même (précommande) ou blocage total tant que rien n'est en stock nulle part ? — voir §5.

## 4. Colonnes PrestaShop à poser selon le cas

| Cas | `out_of_stock` | `available_date` | Libellé affiché |
|---|---|---|---|
| `quantity > 0` | — | — | En stock (natif PS) |
| `quantity = 0`, `StockAvailable > 0` | 1 (autoriser) | `hqETADate` | En stock constructeur |
| `quantity = 0`, `StockAvailable = 0` | 0 (refuser) | `hqETADate` si présente, vide sinon | Épuisé (+ date si dispo) |

`out_of_stock` et `available_date` sont les colonnes natives PrestaShop (`ps_stock_available.out_of_stock`, `ps_product[_shop].available_date`) — pas de nouvelle table à créer côté affichage, uniquement à piloter ces colonnes natives depuis `StockAvailable` et `hqETADate`.

## 5. Ce que cette règle ne touche jamais

`quantity` elle-même — alimentée exclusivement par G8, jamais réécrite par le script constructeur.

## 6. Question ouverte à trancher avant de coder

**Cas « Épuisé avec date connue »** : le produit doit-il rester commandable (précommande sur la date annoncée) ou bloqué jusqu'à ce que `StockAvailable` repasse au-dessus de 0 ?

## 7. Points à lever avant implémentation (constats du 04/09/2026)

- **`StockAvailable` (constructeur) et `hqETADate` sont absents de ce repo** — vérifié par recherche exhaustive sur `modules/`, `override/`, `megaservice/`, `docs/`. Le seul `StockAvailable` présent est la classe **native** PrestaShop (stock magasin, via `StockAvailable::getQuantityAvailableByProduct()` dans `megaservice_replacement` et `megaservice_microfiches`) — à ne pas confondre avec le champ constructeur du brief, qui porte le même nom.
- Le « script de mise à jour existant » qui alimente ces deux champs constructeur n'a pas été localisé : ni module custom, ni override, ni référence dans `TECH_DEBT.md`. Soit il vit hors de ce repo (ERP, script serveur non versionné), soit il reste à écrire.
- Avant de coder la règle d'affichage, il faut savoir **où lire** `StockAvailable` (constructeur) et `hqETADate` : nom de table/colonne, ou mécanisme d'accès si porté par un module tiers.
