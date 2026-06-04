# Module `megaservice_microfiches` — État du projet

> **Document vivant** mis à jour à chaque fin de session.
> Le brief initial est dans [`BRIEF.md`](BRIEF.md) — il n'a **pas** été modifié,
> mais certaines décisions actées en cours de dev s'en sont éloignées. Toutes
> ces déviations sont tracées ci-dessous + dans [`../../TECH_DEBT.md`](../../TECH_DEBT.md).

**Branche de travail** : `feat/microfiches-skeleton` (jamais mergée sur `main` —
décision actée : on attend que toute la V1 marche).

**Dernier commit** : voir `git log --oneline feat/microfiches-skeleton ^main | head -20`

---

## 1. État BDD preprod (au dernier point de validation)

| Table | Rows | Notes |
|---|---|---|
| `ps_ms_moto` | **1830** | 3 marques (KTM 1218 / HQV 429 / GASGAS 183) |
| `ps_ms_microfiche_categorie` | **25** | Toutes en `code = TODO_xxx` jusqu'à renommage manuel |
| `ps_ms_microfiche` | **45** | Toutes liées à `serial_constructeur = F0403X7` (GASGAS EC 300 2024, `id_moto = 2587`) |
| `ps_ms_microfiche_hotspot` | **1463** | `id_product` = NULL pour les 1463 (catalogue spareparts pas peuplé) |

**Distribution `ms_moto.type`** : 2.3% en `Autres` après patch du dictionnaire taxonomie.

---

## 2. PRs — état d'avancement

### ✅ Livrées et validées sur preprod

| PR | Quoi | Commits clés |
|---|---|---|
| **PR1** | Skeleton module + 4 tables + 4 ObjectModels + hooks registerés | `8adff50` → `241f7c2` |
| **PR2** | Importer motos (3 marques) + taxonomie 8 types + page BO upload + patch dictionnaire | `8465879` → `2c64822` |
| **PR3** | Importer microfiches + auto-création catégories + page BO upload + fixes (LIMIT, UNIQUE KEY hotspots) | `c1c6a37` → `b1ab36e` |
| **PR4** | Tabs BO sous Catalogue + 3 AdminControllers (Motos, Microfiches, Catégories) | `591d033` → `f191a06` |
| *(fixes)* | Collation alignée sur PrestaShop | `da5c843` |

**Tests unitaires CLI** : 142 passants (motos 88, microfiches 54) dans `tests/cli/`.

### 🔴 Bloquées (dépendances externes)

| PR | Quoi | Débloquée par |
|---|---|---|
| **PR6** | Front PDP moto (page d'une moto avec ses microfiches) | Réception maquette `PLP_CAT_SPAREPARTS.png` côté MSS |
| **PR7** | Front PDP microfiche (vue éclatée + hotspots cliquables) | Réception maquette `PDP_microfiche.png` côté MSS |

### 🟡 Non commencées (priorisables)

| PR | Quoi | Effort estimé | Bloqueur |
|---|---|---|---|
| **PR-Visuels** | Cron download images motos (URL base × marque → `img/ms_moto/<id>/main.png`) | ~3h | URLs base à demander à MSS |
| **PR5** | Éditeur visuel hotspots BO (cercles overlayés sur l'image, read-only V1) | ~4 commits | Aucun |
| **PR8** | Cron rematching `hotspot.id_product` ↔ `ps_product.reference` + hook `actionProductAdd` | ~3 commits | Aucun techniquement, mais inutile tant que `ps_product` n'a pas les pièces OEM |
| **PR9** | Tests d'intégration (DB réelle) | ~5 commits | Aucun |

---

## 3. Décisions architecturales actées (différences vs brief)

| # | Décision | Pourquoi | Brief initial |
|---|---|---|---|
| 1 | Module renommé `megaservice_microfiches` | Cohérence avec autres modules MSS | `mssmicrofiches` |
| 2 | Brief gardé en doc, pas suivi à la lettre sur l'ordre des PRs | Adaptation pragmatique | Suivait un ordre strict |
| 3 | Upload CSV via BO direct (au lieu de SCP/SSH) | UX admin sans accès shell | "Importer via BO ou CLI" |
| 4 | Tabs BO sous `Catalogue` (pas top-level) | PS 8 n'autorise pas les top-level customs | Brief ne le précisait pas |
| 5 | `UNIQUE KEY hotspot` étendue avec `position_x`, `position_y` | 14.5% des hotspots écrasés sans ça (mesure réelle F0403X7) | `(microfiche, article_ref, sequence)` |
| 6 | Collation `utf8mb4_general_ci` (alignée sur PS) | Sinon JOIN avec `ps_product` plante (1267) | Brief disait `utf8mb4_unicode_ci` |
| 7 | Édition motos : `type` + `active` + **upload photo cycle + photo moteur** | Source de vérité CSV pour le reste, mais visuels partiels uploadés manuellement | Brief V2 prévoyait édition complète |
| 8 | Visuels microfiches : hotlink V1 (pas de download) | Décision MSS | "Stockage URL en V1, download V2" |
| 9 | Visuels motos : download local prévu (PR-Visuels) | Décision MSS | "Juste nom de fichier en V1" |
| 10 | Patch dictionnaire taxonomie : 17% → 2.3% Autres | Patterns manquants au brief (`TC`/`FC` HQV, `EC` GASGAS, `RC<digits>` KTM, etc.) | Dictionnaire incomplet §4.2 |

Détail complet de chaque déviation dans `TECH_DEBT.md`.

---

## 4. Actions en attente côté MSS (client)

Liste à remonter avant prochaine session de dev :

1. **URLs de base par marque** pour télécharger les visuels motos (PR-Visuels) :
   - KTM, HQV, GASGAS — format type `https://media.<marque>.com/.../{filename}`
2. **Confirmation hotlink microfiches** (sparepartsfinder.gasgas.com, gasgasdealer.net, équivalents KTM/HQV) — OK légalement ?
3. **Maquettes Figma** : `PLP_CAT_SPAREPARTS.png` + `PDP_microfiche.png` (bloquent PR6/PR7 front)
4. **Stratégie catalogue spareparts** : par quel flux les 1128+ références OEM par moto vont-elles arriver dans `ps_product.reference` ? (CSV PS natif ? Module externe ? API ?)
5. ~~**Champs `picture_cycle` + `picture_moteur` sur `ms_moto`** : prévus au schéma mais le CSV motos n'a qu'une seule colonne `picture`. Source de ces vues partielles ?~~ → **Résolu** : upload manuel par moto via fiche d'édition BO (cf. décision #7). Stockage dans `img/ms_moto/<id>/{cycle,moteur}.<ext>`.

6. **Mapping serials motos ↔ microfiches** : le CSV motos utilise des codes SAP/ERP fournisseur avec suffixes `_CFMOTO` ou `_FTI` (modèles fabriqués par CFMOTO / via le programme FTI). Le CSV microfiches utilise les codes officiels catalogue pièces. **Le numéro lui-même diffère**, pas juste le suffixe (ex. `2887X1_FTI` → `F2803X1` pour la Norden 901 2024). Mesure preprod : **15 motos sur 1830 concernées** (9 CFMOTO + 6 FTI), toutes en 2022-2025. À demander à MSS : table de correspondance `serial_csv_motos` → `serial_csv_microfiches` pour ces 15. Solution V1 : `UPDATE` SQL ponctuel sur `ms_moto.serial_constructeur` (pas de refactor schéma vu la volumétrie).

---

## 5. SQL utiles (à exécuter en phpMyAdmin)

### Migrations en attente sur preprod

```sql
-- Migration 002 : aligner collation (à appliquer une fois)
-- Source : modules/megaservice_microfiches/sql/migrations/002_align_collation_with_prestashop.sql
ALTER TABLE `ps_ms_moto`                  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `ps_ms_microfiche_categorie`  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `ps_ms_microfiche`            CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `ps_ms_microfiche_hotspot`    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
```

### Renommage des 25 catégories TODO_xxx en français

```sql
UPDATE ps_ms_microfiche_categorie SET nom_fr = CASE code
    WHEN 'TODO_moteur_30' THEN 'Carter moteur'
    WHEN 'TODO_moteur_32' THEN 'Embrayage'
    WHEN 'TODO_moteur_33' THEN 'Boîte de vitesses'
    WHEN 'TODO_moteur_34' THEN 'Sélecteur de vitesses'
    WHEN 'TODO_moteur_35' THEN 'Refroidissement'
    WHEN 'TODO_moteur_37' THEN 'Commande d''échappement'
    WHEN 'TODO_moteur_38' THEN 'Lubrification'
    WHEN 'TODO_moteur_39' THEN 'Allumage'
    WHEN 'TODO_moteur_41' THEN 'Boîtier d''injection'
    WHEN 'TODO_cycle_1'   THEN 'Fourche avant'
    WHEN 'TODO_cycle_2'   THEN 'Guidon'
    WHEN 'TODO_cycle_3'   THEN 'Cadre'
    WHEN 'TODO_cycle_4'   THEN 'Suspension arrière'
    WHEN 'TODO_cycle_5'   THEN 'Échappement'
    WHEN 'TODO_cycle_6'   THEN 'Filtre à air'
    WHEN 'TODO_cycle_7'   THEN 'Réservoir / Selle'
    WHEN 'TODO_cycle_8'   THEN 'Carénage'
    WHEN 'TODO_cycle_9'   THEN 'Roue avant'
    WHEN 'TODO_cycle_10'  THEN 'Roue arrière'
    WHEN 'TODO_cycle_11'  THEN 'Faisceau électrique'
    WHEN 'TODO_cycle_13'  THEN 'Freinage'
    WHEN 'TODO_cycle_14'  THEN 'Éclairage'
    WHEN 'TODO_cycle_15'  THEN 'Filtre à charbon actif'
    WHEN 'TODO_cycle_20'  THEN 'Pack accessoires'
    WHEN 'TODO_cycle_90'  THEN 'Cales de réglage'
END
WHERE code LIKE 'TODO_%';

UPDATE ps_ms_microfiche_categorie
SET code = SUBSTRING(code, 6)
WHERE code LIKE 'TODO_%';
```

### Mesures et diagnostics

```sql
-- Distribution motos par marque + nb 'Autres'
SELECT marque, COUNT(*) AS total,
       SUM(CASE WHEN type = 'Autres' THEN 1 ELSE 0 END) AS autres
FROM ps_ms_moto GROUP BY marque;

-- Liste motos en Autres pour correction manuelle (BO)
SELECT marque, modelnumber, core_name FROM ps_ms_moto
WHERE type = 'Autres' ORDER BY marque, core_name;

-- Compteur taux de matching produit (devient utile dès catalogue spareparts importé)
SELECT COUNT(DISTINCT h.article_ref) AS refs_uniques,
       COUNT(DISTINCT p.id_product)  AS produits_qui_matchent
FROM ps_ms_microfiche_hotspot h
LEFT JOIN ps_product p ON p.reference = h.article_ref AND p.active = 1;
```

---

## 6. Flux de travail (CI/CD)

- **Git** : `feat/microfiches-skeleton`, jamais mergée sur main
- **Push** : `git push origin feat/microfiches-skeleton`
- **Deploy preprod** : GitHub Actions → "Build & Deploy" → Run workflow sur la branche
  - Le workflow rsync `megaservice/`, `override/`, `modules/` puis vide `var/cache/*`
- **Activation Tabs BO après deploy** : nécessite **désinstall + réinstall** du module en BO
  (les données BDD sont préservées — `uninstall()` ne droppe pas les tables)

---

## 7. Comment reprendre le projet

1. Clone si nouveau poste : `git clone <repo> && cd megaservice-theme && git checkout feat/microfiches-skeleton`
2. Lire ce fichier (STATUS.md) en entier pour comprendre l'état
3. Lire les commits récents : `git log --oneline -20`
4. Lire `TECH_DEBT.md` pour les déviations vs brief
5. Vérifier l'état preprod avec les SQL du §5
6. Prioriser une PR de la liste §2 "Non commencées"
7. Suivre la même méthodo qui a marché jusqu'ici :
   - 1 commit par composant (granularité fine)
   - Tests CLI hors Presta pour les méthodes pures
   - Validation par étapes sur preprod (push → deploy → smoke test → fix → push)
   - Documenter chaque déviation vs brief dans TECH_DEBT

---

## 8. Fichiers clés du module

```
modules/megaservice_microfiches/
├── megaservice_microfiches.php           # Classe principale (install, uninstall, Tabs, getContent)
├── config.xml                            # Métadonnées module
├── classes/
│   ├── MsMoto.php                        # ObjectModel + constantes MARQUES, TYPES
│   ├── MsMicrofiche.php
│   ├── MsMicroficheCategorie.php
│   ├── MsMicroficheHotspot.php
│   └── importers/
│       ├── CsvReader.php                 # Utilitaire pur : encodage + delim + headers
│       ├── MotosTaxonomy.php             # Dictionnaire type + dérivations core_name/cyl
│       ├── MotosImporter.php             # Orchestration import motos
│       ├── MotosImportReport.php         # Rapport
│       ├── MicrofichesTaxonomy.php       # Mapping engine→moteur / frame→cycle
│       ├── MicrofichesImporter.php       # Orchestration import microfiches
│       └── MicrofichesImportReport.php
├── controllers/admin/
│   ├── AdminMsMotosController.php        # Listing 1830 motos + édition type/active
│   ├── AdminMsMicrofichesController.php  # Listing 45 microfiches read-only + JOIN
│   └── AdminMsCategoriesController.php   # Listing 25 catégories + édition nom_fr
├── sql/
│   ├── install.sql                       # CREATE TABLE des 4 tables (idempotent)
│   ├── uninstall.sql                     # DROP TABLE (NON appelé automatiquement)
│   └── migrations/
│       ├── 001_hotspot_unique_with_position.sql  # Fix UNIQUE KEY hotspots
│       └── 002_align_collation_with_prestashop.sql  # Fix collation
├── samples/                              # Échantillons CSV versionnés (~30 lignes/marque)
│   ├── sample_KTM_MOTORCYCLES.csv
│   ├── sample_HQV_MOTORCYCLES.csv
│   ├── sample_GASGAS_MOTORCYCLES.csv
│   ├── sample_F0403X7.csv
│   └── README.md                         # Procédure régénération samples
└── tests/cli/                            # Tests unitaires PHP CLI (hors Presta)
    ├── dump_csv.php
    ├── dump_taxonomy.php
    ├── test_taxonomy_units.php           # 101 cas
    ├── test_motos_importer_units.php     # 36 cas
    ├── test_microfiches_taxonomy.php     # 16 cas
    └── test_microfiches_importer_units.php  # 38 cas
```

CSV complets (gitignorés) : `data/imports/` à la racine du repo.
