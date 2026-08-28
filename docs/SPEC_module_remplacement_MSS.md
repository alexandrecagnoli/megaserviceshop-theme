# MSS — Module produits remplacés / remplaçants (`msreplacement`)

**Projet** : Refonte e-commerce Mega Service Shop — PrestaShop 8.2
**Version** : 0.1 — 22 juillet 2026 — Alfred
**Statut** : Draft, en attente arbitrages COPROJ#13

---

## 1. Contexte et objectif

Les catalogues OEM Pierer (KTM / Husqvarna / GASGAS) font l'objet de remplacements permanents de références : une pièce sortie du catalogue est remplacée soit par une nouvelle référence unique, soit par un ensemble de références (kit éclaté en composants). Les clients arrivent très majoritairement avec l'**ancienne référence** (gravée sur la pièce, lue dans une ancienne microfiche ou une facture) : le site doit reconnaître cette référence, informer du remplacement et router vers la ou les références actives, sans friction.

Le module couvre : l'import du fichier de remplacement constructeur, le stockage de la relation, la résolution des chaînes de remplacement, et l'affichage/parcours d'achat sur la fiche produit remplacée.

Hors périmètre : la gestion du cycle de vie produit lui-même (portée par l'import OEM automatisé), les compatibilités produit↔moto (EveryParts), les microfiches (`mssmicrofiches`).

## 2. Source de données

### 2.1 Fichier constructeur

Un fichier CSV par organisation de vente : `replacement_articles_<orga>.csv` (0140 = KTM ; équivalents 0150 / 0910 / 1100 attendus pour les autres marques — **à confirmer auprès de Jérôme**).

Format : séparateur `;`, valeurs entre guillemets doubles, encodage UTF-8, fins de ligne CRLF.

| Colonne | Type | Contenu |
|---|---|---|
| `ArticleNumber` | string | Référence remplacée (clé constructeur) |
| `ArticleNumberReplace` | string | Référence remplaçante |
| `ConversionType` | enum | `Replace` (1:1) ou `Set` (1:N) |
| `SalesOrga` | string | Organisation de vente (0140, 0150…) |
| `RelationType` | enum | `1:1` ou `1:N` (redondant avec ConversionType, conservé pour contrôle) |
| `UnitQuantity` | decimal | Quantité de la réf remplaçante dans le set (1.000 pour les 1:1) |

### 2.2 Volumétrie constatée (fichier 0140 du 22/07/2026)

- 7 059 lignes, 6 573 références remplacées uniques, 5 356 références remplaçantes uniques.
- 6 231 relations `Replace` 1:1 ; 828 lignes `Set` 1:N (un article remplacé est alors présent sur N lignes, une par composant — jusqu'à 10 composants constatés, quantités de 1 à 36).
- 96 références remplaçantes sont elles-mêmes remplacées → chaînes A→B→C à résoudre.
- 1 104 références remplacées absentes de la pricelist courante (références anciennes sorties du catalogue).
- Statuts pricelist des remplaçants : seuls ~48 % sont en statut 20 (actif) ; ~21 % sont eux-mêmes en statut 80.

### 2.3 Invariants et contrôles à l'import

- Le couple (`ArticleNumber`, `ArticleNumberReplace`) est unique par SalesOrga → clé de dédoublonnage.
- Incohérence `ConversionType` / `RelationType` → ligne rejetée + log.
- `UnitQuantity` non entière ou ≤ 0 → ligne rejetée + log (arrondi entier attendu ; format constructeur `1.000`).
- Auto-référence (`ArticleNumber` = `ArticleNumberReplace`) → rejet + log.

## 3. Modèle de données

Une table custom, alignée sur le principe projet : **la référence constructeur est la clé, jamais l'ID PrestaShop**. La résolution réf → `id_product` se fait à l'exécution (affichage, ajout panier), jamais au stockage.

### 3.1 Table `ms_replacement`

| Champ | Type | Rôle |
|---|---|---|
| `id_replacement` | INT PK AI | Technique |
| `sales_orga` | VARCHAR(8) | 0140 / 0150 / 0910 / 1100 |
| `ref_replaced` | VARCHAR(32), index | Référence remplacée |
| `ref_replacement` | VARCHAR(32), index | Référence remplaçante (directe, telle que dans le fichier) |
| `conversion_type` | ENUM('replace','set') | Type de relation |
| `quantity` | INT | Quantité dans le set (1 pour les 1:1) |
| `ref_final` | VARCHAR(32), nullable | Référence finale après résolution transitive |
| `chain_depth` | TINYINT | Profondeur de chaîne (1 = direct) |
| `chain_status` | ENUM('ok','loop','dead_end') | Résultat de la résolution |
| `date_add` / `date_upd` | DATETIME | Audit |

Index unique : (`sales_orga`, `ref_replaced`, `ref_replacement`).

On conserve **à la fois** la relation brute (`ref_replacement`, audit et retraçabilité fichier) et la relation résolue (`ref_final`, ce que le front consomme).

### 3.2 Résolution transitive (au moment de l'import)

Pour chaque relation, on suit la chaîne `ref_replacement` → sa propre ligne de remplacement éventuelle, jusqu'à :

- une référence qui n'est plus remplacée → `ref_final` = cette réf, `chain_status = ok` ;
- une profondeur > 10 ou un cycle détecté (réf déjà vue dans la chaîne) → `ref_final` = dernière réf saine, `chain_status = loop`, alerte log ;
- cas particulier : si un maillon de chaîne est de type `Set`, la résolution s'arrête au maillon précédent (`chain_status = ok`, la cible est le set) — on ne compose pas des sets de sets.

La résolution est précalculée à l'import : zéro coût en front, comportement déterministe, chaînes auditables.

## 4. Import

### 4.1 Mécanisme

Import dédié porté par le module (contrôleur admin + CLI), **hors plugin Advance Importing Pro** : il ne s'agit pas de créer des produits mais d'alimenter une table relationnelle — même logique de séparation que `mssmicrofiches`.

Déroulé : upload ou dépôt fichier → parsing + contrôles §2.3 → upsert en `ms_replacement` (delta par clé unique) → suppression des relations absentes du nouveau fichier pour la SalesOrga concernée (le fichier est photographique, pas incrémental — **à confirmer avec Jérôme** : si le fichier est cumulatif côté KTM, on passera en upsert pur sans purge) → passe de résolution transitive → rapport (lignes ok / rejetées / chaînes / relations supprimées).

### 4.2 Cadence

Manuel dans un premier temps (BO module, comme les autres imports de la surcouche). L'automatisation FTP+cron pourra suivre le même rail que l'import OEM quotidien une fois celui-ci en place — non bloquant pour la v1.

### 4.3 Multi-marques

Un import par fichier/SalesOrga, les relations coexistent dans la table. Le front ne filtre pas par SalesOrga : la réf constructeur est globalement unique dans l'écosystème Pierer.

## 5. Règles de gestion

### 5.1 Source de vérité

**Le fichier replacement est la source de vérité de la relation de remplacement.** Le statut pricelist (`ArticleStatus`) ne l'est pas : le statut 70 est corrélé au remplacement mais pas biunivoque (des remplacés sont en 80, voire en 20). Aucune règle ne doit être dérivée du seul statut.

### 5.2 Matrice des cas front

| Cas | Situation | Comportement fiche produit remplacé |
|---|---|---|
| A | Remplaçant 1:1 actif et publié | Bloc « Cette pièce est remplacée par [réf] » avec nom, prix, dispo, CTA vers la fiche + ajout panier direct |
| B | Set 1:N, tous composants actifs | Bloc set : liste des N composants (réf, nom, quantité, prix unitaire, dispo, sous-total), total du set, CTA « Ajouter le set au panier » |
| C | Set 1:N, composants partiellement dispos | Idem B ; composants indisponibles affichés grisés ; CTA ajoute les lignes disponibles + message « X articles sur N ajoutés — Y indisponibles » |
| D | Remplaçant lui-même indisponible (statut 80, sans successeur) | **À trancher COPROJ** — proposition Alfred : « Référence remplacée par [réf], également indisponible — nous contacter » avec CTA contact |
| E | Remplaçant absent du catalogue Presta | Message générique « Référence remplacée — nous contacter » + alerte back-office (écart de données à traiter) |
| F | Chaîne `loop` / `dead_end` | Idem E + alerte log import |

Dans tous les cas, la fiche du produit remplacé reste **visible et indexable, mais non achetable** (bouton panier de la fiche désactivé, remplacé par le bloc de remplacement). Pas de redirection 301 : le client doit pouvoir confirmer qu'il a trouvé la bonne (ancienne) référence — pattern standard pièces OEM, et conservation du SEO sur les anciennes refs.

### 5.3 Produit remplacé absent du catalogue

Les ~1 100 refs remplacées sans fiche produit ne donnent rien en v1 (pas de fiche = pas de bloc). Évolution possible : intercepter la recherche interne sur ces refs pour proposer directement le remplaçant (« La réf X que vous cherchez est remplacée par Y »). À chiffrer séparément, forte valeur parcours.

## 6. Front — fiche produit

### 6.1 Intégration

Hook `displayProductAdditionalInfo` (ou emplacement équivalent validé avec l'intégration front) sur la fiche produit. Lookup par référence produit dans `ms_replacement` (`ref_replaced` = référence de la fiche) ; si match, injection du template selon la matrice §5.2 et désactivation de l'achat de la fiche courante.

Les données affichées pour les remplaçants (nom, prix, dispo, image) sont lues **en temps réel sur les fiches produit cibles** — donc toujours alignées sur la dernière sync G8, aucun stockage de prix/stock dans le module.

### 6.2 Ajout multi-lignes (cas Set)

Endpoint ajax du module : reçoit `ref_replaced`, relit la composition du set **côté serveur** (jamais confiance au payload client sur les quantités), résout chaque réf composant → `id_product` (+ `id_product_attribute` le cas échéant), effectue N `Cart::updateQty` avec les quantités du fichier, retourne le détail ajouté/échoué pour le message front (cas C).

Le panier résultant contient N lignes produit standard : tunnel, stock, et **descente de commande vers G8 inchangés** — chaque ligne porte une vraie référence constructeur commandable.

## 7. Interactions avec l'existant

- **ShopG8** : aucun impact, aucun objet nouveau à synchroniser. C'est le motif central de l'architecture retenue : pas de packs natifs PrestaShop, qui créeraient des produits à réf inventée, invisibles de G8 (prix/stock non synchronisés, lignes de commande non rapprochables en magasin).
- **Import OEM automatisé** : indépendant. L'import OEM gère création/prix/dispo/état de vie ; le module remplacement se superpose par la référence. Ordre d'import indifférent (une relation pointant vers une réf pas encore créée tombe en cas E jusqu'à création).
- **`mssmicrofiches`** : les microfiches référencent des refs OEM pouvant être remplacées ; le clic microfiche → fiche produit remplacée affiche naturellement le bloc. Aucun développement côté microfiches en v1. Évolution possible : badge « remplacée » dans la liste des pièces de la microfiche.
- **EveryParts** : hors périmètre, aucune interaction.

## 8. Back-office

- Écran d'import (upload fichier, choix SalesOrga auto-détecté, rapport d'exécution).
- Liste des relations avec recherche par réf, filtres type/statut de chaîne, indicateurs (relations totales, sets, chaînes, cas E détectés).
- Export CSV des anomalies (rejets, loops, remplaçants absents du catalogue) pour transmission à Jérôme/KTM.

## 9. Points ouverts

| # | Point | Owner | Échéance |
|---|---|---|---|
| 1 | Cas D : formulation et comportement quand le remplaçant est lui-même indisponible | COPROJ (Jérôme) | COPROJ#13 |
| 2 | Fichiers replacement 0150 / 0910 / 1100 : disponibilité et canal de récupération | Jérôme | ASAP |
| 3 | Nature du fichier : photographique (purge des relations disparues) ou cumulatif | Jérôme / KTM | Avant dev import |
| 4 | Fréquence de mise à jour du fichier côté KTM (conditionne l'intérêt du cron) | Jérôme | Avant v1 |
| 5 | Légende officielle des codes `ArticleStatus` (confirmation 70 = remplacé, sémantique 40/50/60) | Jérôme / doc dealer | Info |
| 6 | Interception de la recherche interne sur refs remplacées sans fiche (§5.3) | Alfred | Chiffrage v2 |
