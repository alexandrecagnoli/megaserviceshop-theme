# Brief Claude Code — Module PrestaShop `mssmicrofiches`

> **Pour Claude Code** : ce brief est ta spec. Lis-le intégralement avant d'écrire la première ligne. Si un point te semble ambigu, demande avant de coder, ne devine pas.

---

## 1. Contexte projet (en 1 paragraphe)

MSS (Mega Service Shop) est un dealer KTM / Husqvarna / GASGAS. Refonte e-commerce en cours sur **PrestaShop 8.2**. Le site doit permettre à un client d'identifier visuellement les pièces détachées d'origine (OEM) de sa moto via des **vues éclatées** (schémas techniques constructeur), de cliquer sur les pièces représentées, et de les commander. Le système existant (CMS custom legacy hors Presta) fonctionne mais est porté **from scratch** en module Presta dans cette refonte. Le pipeline d'ingestion existant (dépôt CSV sur FTP → script d'ingestion) est **maintenu**, on adapte juste sa couche d'écriture pour cibler les nouvelles tables.

**Important — ce que ce module N'EST PAS** :
- Pas un plugin marketplace, pas une surcouche de catégories Presta, pas un système à produits variables. Module custom from scratch dans `modules/mssmicrofiches/`.
- Pas un système de hotspots sur photo de moto (cycle/moteur) menant à des microfiches. **Cette couche intermédiaire est supprimée par rapport au legacy.** Navigation directe via filtres de catégories constructeur.

---

## 2. Modèle conceptuel

Trois entités principales **côté custom** + le produit Presta natif :

```
ms_moto (1830 motos, dédupliquées sur MODELNUMBER)
   └── ms_microfiche (vues éclatées, ~50 par moto, image + métadonnées)
          └── ms_microfiche_hotspot (points cliquables, ~30 par microfiche, xy + ref OEM + qty)
                 └── ps_product (NATIF Presta : la pièce OEM avec SKU = ref constructeur)

ms_microfiche_categorie (référentiel, ~30 entrées)
   ↑ FK depuis ms_microfiche (sert de filtre côté front)
```

**Volumétrie cible** (à supporter sans optimisation prématurée mais à garder en tête) :
- ~1 830 motos
- ~50 000 microfiches (≈ 30 motos × dédoublonnage cross-année + millésimes)
- ~1 500 000 hotspots
- ~40 000+ produits OEM (existent côté `ps_product`, hors scope du module)

---

## 3. Modèle de données — DDL exact

Toutes les tables avec préfixe `ps_` (le préfixe Presta par défaut). Adapter à `_DB_PREFIX_` dans le code.

```sql
-- ============================================================================
-- TABLE : ms_moto
-- 1 ligne = 1 modèle/année moto (clé naturelle = MODELNUMBER)
-- ============================================================================
CREATE TABLE `ps_ms_moto` (
  `id_moto`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `modelnumber`     VARCHAR(64) NOT NULL,              -- ex "$M-125DUKE2026"
  `marque`          ENUM('KTM','HQV','GASGAS') NOT NULL,
  `annee`           SMALLINT UNSIGNED NOT NULL,
  `category_fr`     VARCHAR(255) NOT NULL,             -- ex "125 Duke 2026"
  `core_name`       VARCHAR(255) NOT NULL,             -- ex "125 Duke" (dérivé)
  `type`            ENUM('Motocross','Enduro','Naked','Adventure','Supermoto','Electrique','Trial','Autres') NOT NULL DEFAULT 'Autres',
  `cylindree`       SMALLINT UNSIGNED NULL,            -- ex 125
  `is_electric`     TINYINT(1) NOT NULL DEFAULT 0,
  `nom_fr`          VARCHAR(255) NOT NULL,             -- ex "2026 KTM 125 DUKE"
  `description_fr` TEXT NULL,
  `picture_main`    VARCHAR(255) NULL,                 -- nom de fichier
  `picture_cycle`   VARCHAR(255) NULL,                 -- illustration sans hotspots
  `picture_moteur`  VARCHAR(255) NULL,                 -- illustration sans hotspots
  `active`          TINYINT(1) NOT NULL DEFAULT 1,
  `date_add`        DATETIME NOT NULL,
  `date_upd`        DATETIME NOT NULL,
  PRIMARY KEY (`id_moto`),
  UNIQUE KEY `uk_modelnumber` (`modelnumber`),
  KEY `idx_marque_annee` (`marque`, `annee`),
  KEY `idx_type` (`type`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE : ms_microfiche_categorie
-- Référentiel des catégories constructeur pour filtrer les microfiches
-- Mapping libellés FR à saisir en BO ("engine 30" → "Bas-moteur" etc.)
-- ============================================================================
CREATE TABLE `ps_ms_microfiche_categorie` (
  `id_categorie`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `partie`              ENUM('cycle','moteur') NOT NULL,    -- vient de vue_eclatee_type
  `numero_constructeur` SMALLINT UNSIGNED NOT NULL,         -- vient de vue_eclatee_number
  `code`                VARCHAR(64) NOT NULL,               -- ex "brakes"
  `nom_fr`              VARCHAR(128) NOT NULL,              -- ex "Freinage"
  `ordre_affichage`     SMALLINT NOT NULL DEFAULT 0,
  `active`              TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_categorie`),
  UNIQUE KEY `uk_partie_numero` (`partie`, `numero_constructeur`),
  KEY `idx_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE : ms_microfiche
-- 1 microfiche = 1 vue éclatée d'une partie d'une moto
-- ============================================================================
CREATE TABLE `ps_ms_microfiche` (
  `id_microfiche`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_moto`         INT UNSIGNED NOT NULL,
  `id_categorie`    INT UNSIGNED NOT NULL,
  `nom_constructeur` VARCHAR(255) NOT NULL,            -- ex "FRONT BRAKE CALIPER"
  `nom_fr`          VARCHAR(255) NULL,                 -- nullable, traduction à venir
  `image_full_url`  VARCHAR(512) NOT NULL,             -- URL source constructeur
  `image_thumb_url` VARCHAR(512) NULL,
  `image_local`     VARCHAR(255) NULL,                 -- chemin local après téléchargement (option V2)
  `image_width`     SMALLINT UNSIGNED NULL,
  `image_height`    SMALLINT UNSIGNED NULL,
  `active`          TINYINT(1) NOT NULL DEFAULT 1,
  `date_add`        DATETIME NOT NULL,
  `date_upd`        DATETIME NOT NULL,
  PRIMARY KEY (`id_microfiche`),
  KEY `idx_id_moto` (`id_moto`),
  KEY `idx_id_categorie` (`id_categorie`),
  KEY `idx_active` (`active`),
  CONSTRAINT `fk_microfiche_moto`      FOREIGN KEY (`id_moto`)      REFERENCES `ps_ms_moto`(`id_moto`)                ON DELETE CASCADE,
  CONSTRAINT `fk_microfiche_categorie` FOREIGN KEY (`id_categorie`) REFERENCES `ps_ms_microfiche_categorie`(`id_categorie`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE : ms_microfiche_hotspot
-- 1 hotspot = 1 point cliquable sur une microfiche → 1 produit OEM
-- ============================================================================
CREATE TABLE `ps_ms_microfiche_hotspot` (
  `id_hotspot`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_microfiche`     INT UNSIGNED NOT NULL,
  `id_product`        INT UNSIGNED NULL,                 -- FK ps_product (NULL si non rattaché)
  `article_ref`       VARCHAR(64) NOT NULL,              -- ref constructeur (clé de matching)
  `article_label`     VARCHAR(255) NULL,                 -- ex "Engine case cmpl." (depuis CSV)
  `sequence_number`   SMALLINT UNSIGNED NOT NULL,        -- ordre d'affichage sur le schéma
  `position_x`        SMALLINT UNSIGNED NOT NULL,        -- coordonnée en pixels (image_width réf)
  `position_y`        SMALLINT UNSIGNED NOT NULL,
  `qty_recommended`   TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_hotspot`),
  KEY `idx_id_microfiche` (`id_microfiche`),
  KEY `idx_id_product` (`id_product`),
  KEY `idx_article_ref` (`article_ref`),
  CONSTRAINT `fk_hotspot_microfiche` FOREIGN KEY (`id_microfiche`) REFERENCES `ps_ms_microfiche`(`id_microfiche`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Notes critiques sur le modèle :**

1. **`id_product` est nullable** : un hotspot peut référencer une ref OEM dont la fiche produit n'existe pas encore dans `ps_product`. C'est le cas "produit manquant" — à traiter en BO. Conserver `article_ref` (la ref constructeur) permet de re-tenter le matching plus tard.

2. **Le matching `article_ref` → `id_product` se fait sur `ps_product.reference`**. Ne pas confondre avec `ps_product.id_product` (auto-increment Presta).

3. **`image_full_url` est l'URL constructeur** (ex `https://www.gasgasdealer.net/SPF/Images/maps/23A4403010.png`). V1 : on affiche depuis cette URL directement. V2 (optionnelle) : téléchargement local dans `image_local` pour autonomie. **À implémenter en V1 : juste stocker l'URL, pas de téléchargement.**

4. **Position xy en pixels absolus**, référencés à `image_width` × `image_height` de la microfiche. Côté front, faire un calcul de ratio pour adapter à la taille rendue.

---

## 4. Sources de données — Format d'import

### 4.1 Fichiers CSV motos (3 fichiers, un par marque)

Fichiers source : `KTM_MOTORCYCLES.csv`, `HQV_MOTORCYCLES.csv`, `GASGAS_MOTORCYCLES.csv`

**Encodings différents** :
- KTM : `ISO-8859-1` (Latin-1)
- HQV : `UTF-8`
- GASGAS : `UTF-8`

**Séparateur** : `;` (point-virgule)

**Variation du nom de colonne année selon la marque** :
- KTM : `année` (avec accent)
- HQV : `annee` (sans accent)
- GASGAS : `ANNEE` (capitales)

→ Le mapper doit normaliser ces 3 variantes en un seul champ `annee`.

**Volumétrie brute** : KTM 5938 lignes / HQV 1173 / GASGAS 355 = 7466 lignes total.
**Volumétrie utile après dédoublonnage MODELNUMBER** : ~1830 motos.

**Stratégie de dédoublonnage** : on garde la **1ère occurrence** de chaque `MODELNUMBER`. Les "doublons" du CSV portent des variantes de coloris/photo qu'on ignore en V1 (décision actée).

**Colonnes utiles (sur les 30 disponibles)** :

| Colonne CSV | Champ cible | Notes |
|---|---|---|
| `MODELNUMBER` | `ms_moto.modelnumber` | Clé naturelle, UNIQUE |
| `année` / `annee` / `ANNEE` | `ms_moto.annee` | Normaliser le nom |
| `model name (FR)` | `ms_moto.nom_fr` | |
| `text (FR)` | `ms_moto.description_fr` | Texte long |
| `Category FR` | `ms_moto.category_fr` | ex "125 Duke 2026" |
| `picture` | `ms_moto.picture_main` | Juste le nom du fichier |

**Marque** : déduite du fichier source (KTM_MOTORCYCLES.csv → 'KTM').

**Dérivations à appliquer après import** :

- `core_name` : on retire le motif d'année final de `category_fr`. Regex : `s/\s*\d{4}\s*$//`. Ex: "125 Duke 2026" → "125 Duke".
- `cylindree` : extraction du premier nombre du `core_name`. Ex: "125 Duke" → 125. Ex: "Svartpilen 401" → 401. Ex: "Norden 901 Expedition" → 901. Si aucun nombre, NULL.
- `type` : dictionnaire de mapping appliqué sur le `core_name` (voir section 4.2 ci-dessous).
- `is_electric` : true si `type = 'Electrique'`, false sinon.

### 4.2 Dictionnaire de mapping `type` (depuis `core_name`)

À appliquer dans cet ordre — premier match gagne. **Match insensible à la casse.**

```php
// Ordre IMPORTANT : du plus spécifique au plus général

// ÉLECTRIQUE en premier (avant que SX-E matche SX)
preg_match('/\b(SX-E|MC-E|EE|Freeride\s*E)\b/i', $core)         => 'Electrique'

// TRIAL (GASGAS uniquement)
preg_match('/\b(TXT|TXT\s+GP|TXT\s+Racing)\b/i', $core)          => 'Trial'

// ADVENTURE / TRAVEL
preg_match('/\b(Adventure|Norden)\b/i', $core)                    => 'Adventure'

// SUPERMOTO (SMC / SMR / SM / FS / Supermoto)
preg_match('/\b(SMC|SMR|SM\s|FS\s|Supermoto)\b/i', $core)        => 'Supermoto'

// NAKED / ROADSTER (Duke, RC, Svartpilen, Vitpilen)
preg_match('/\b(Duke|RC\s+\d|Svartpilen|Vitpilen)\b/i', $core)   => 'Naked'

// ENDURO routier (Enduro R / 701 Enduro)
preg_match('/\b(Enduro\s+R|701\s+Enduro)\b/i', $core)            => 'Enduro'

// ENDURO compétition (EXC, EX, XC, TE, TX, FE, FX)
preg_match('/\b(EXC|EX|XC|TE|TX|FE|FX)\b/i', $core)              => 'Enduro'

// MOTOCROSS (SX, MC) — laissé EN DERNIER car SX matche aussi des sous-variantes
preg_match('/\b(SX|MC)\b/i', $core)                              => 'Motocross'

// Fallback
default                                                           => 'Autres'
```

Loguer en BO toutes les motos tombées en `'Autres'` pour correction manuelle (interface BO).

### 4.3 Fichiers CSV microfiches (un par moto)

Fichier exemple : `F0403X7.csv` (microfiches de la GASGAS EC 300 2024).

**Encoding** : UTF-8. **Séparateur** : `;`.

**1 ligne = 1 hotspot** (pas 1 microfiche). Les microfiches sont implicites : on les déduit en groupant sur `(vue_eclatee_type, vue_eclatee_number, vue_eclatee)`.

**Colonnes** :

| Colonne CSV | Cible | Notes |
|---|---|---|
| `category_model_1..7` | (ignoré) | Hiérarchie constructeur déjà cartographiée |
| `model_annee` | (recoupement) | Année moto, doit matcher `ms_moto.annee` |
| `model_pays` | (ignoré) | Toujours "EU" |
| `model` | (recoupement) | = nom de fichier sans extension (`F0403X7`). Sert à retrouver la moto |
| `vue_eclatee_type` | `ms_microfiche_categorie.partie` | "engine" → 'moteur', "frame" → 'cycle' |
| `vue_eclatee_number` | `ms_microfiche_categorie.numero_constructeur` | smallint |
| `vue_eclatee` | `ms_microfiche.nom_constructeur` | ex "FRONT BRAKE CALIPER" |
| `vue_eclatee_image_preview` | `ms_microfiche.image_thumb_url` | URL |
| `vue_eclatee_image` | `ms_microfiche.image_full_url` | URL |
| `vue_eclatee_image_width` | `ms_microfiche.image_width` | smallint |
| `vue_eclatee_image_height` | `ms_microfiche.image_height` | smallint |
| `article_id` | `ms_microfiche_hotspot.article_ref` | Référence constructeur |
| `sequence_number` | `ms_microfiche_hotspot.sequence_number` | |
| `position_left` | `ms_microfiche_hotspot.position_x` | px |
| `position_bottom` | `ms_microfiche_hotspot.position_y` | px |
| `article` | `ms_microfiche_hotspot.article_label` | Libellé construct |
| `quantity` | `ms_microfiche_hotspot.qty_recommended` | |

**Pivot du nom de fichier vers la moto** :
- Le `model` du CSV est une référence interne constructeur (ex "F0403X7")
- **Cette ref doit être stockée sur `ms_moto`** comme champ supplémentaire pour permettre le pivot. À ajouter au DDL :

```sql
ALTER TABLE `ps_ms_moto` 
  ADD COLUMN `serial_constructeur` VARCHAR(32) NULL AFTER `modelnumber`,
  ADD KEY `idx_serial` (`serial_constructeur`);
```

Cette ref est récupérée depuis le CSV motos via la colonne `article number` (ex pour GASGAS EC 300 2024 = `F0403X7`). À mapper lors de l'import motos.

⚠️ **Le `article number` du CSV motos n'est pas unique** (il y a plusieurs lignes par MODELNUMBER). En dédoublonnage MODELNUMBER, prendre l'`article number` de la 1ère occurrence.

**Idempotence d'import microfiche** :
- Si on réimporte le même CSV microfiche : ne pas dupliquer la microfiche ni ses hotspots
- Clé fonctionnelle d'une microfiche : `(id_moto, vue_eclatee_type, vue_eclatee_number, vue_eclatee)`
- Clé fonctionnelle d'un hotspot : `(id_microfiche, article_ref, sequence_number)`
- Stratégie : `INSERT ... ON DUPLICATE KEY UPDATE` ou `DELETE + INSERT` par scope (microfiche). À discuter, mais l'idempotence est non-négociable.

### 4.4 Matching produit (hotspot → ps_product)

Au moment de l'ingestion d'un hotspot :

```sql
SELECT id_product 
FROM ps_product 
WHERE reference = :article_ref 
  AND active = 1 
LIMIT 1;
```

Si trouvé → `id_product` rempli. Sinon → `id_product` reste NULL.

Un cron de rematching tourne périodiquement pour rattacher les hotspots orphelins quand de nouveaux produits sont ajoutés au catalogue.

---

## 5. Structure du module — Arborescence fichiers

```
modules/mssmicrofiches/
├── mssmicrofiches.php              # classe principale du module (install/uninstall/hooks)
├── config.xml                      # métadonnées module
├── logo.png
├── readme.md
├── classes/
│   ├── MsMoto.php                  # ObjectModel
│   ├── MsMicrofiche.php            # ObjectModel
│   ├── MsMicroficheCategorie.php   # ObjectModel
│   ├── MsMicroficheHotspot.php     # ObjectModel
│   └── importers/
│       ├── MotosImporter.php       # ingestion CSV motos (3 marques)
│       ├── MicrofichesImporter.php # ingestion CSV microfiches (1 par moto)
│       └── ProductMatcher.php      # rematching hotspots orphelins
├── controllers/
│   ├── admin/
│   │   ├── AdminMsMotosController.php           # listing + édition motos
│   │   ├── AdminMsMicrofichesController.php     # listing + édition microfiches
│   │   ├── AdminMsCategoriesController.php      # référentiel catégories (mapping FR)
│   │   └── AdminMsImportController.php          # interface import CSV
│   └── front/
│       ├── moto.php                 # PDP moto (filtres + liste microfiches)
│       └── microfiche.php           # PDP microfiche (image + hotspots)
├── views/
│   ├── templates/
│   │   ├── admin/
│   │   │   ├── motos/list.tpl
│   │   │   ├── microfiches/edit.tpl       # éditeur hotspots BO
│   │   │   └── categories/list.tpl
│   │   └── front/
│   │       ├── moto.tpl
│   │       └── microfiche.tpl
│   ├── css/
│   │   ├── front.css
│   │   └── admin.css
│   └── js/
│       ├── front-microfiche.js      # interactions hotspots front
│       └── admin-hotspot-editor.js  # éditeur visuel hotspots BO
├── sql/
│   ├── install.sql
│   └── uninstall.sql
└── translations/
    └── fr.php
```

---

## 6. Périmètre fonctionnel — V1 (à coder)

### 6.1 Back-office

**A. Listing motos** (`AdminMsMotosController`)
- Tableau Presta natif avec filtres : marque, année, type, search par modelnumber/nom
- Colonnes : ID, marque, année, nom, type, nb microfiches, actif, actions (Voir / Désactiver)
- Pagination 50/page

**B. Fiche moto** (lecture seule en V1, édition V2)
- Affichage des données importées
- Liste des microfiches rattachées, groupées par catégorie

**C. Listing microfiches** (`AdminMsMicrofichesController`)
- Filtres : moto, partie cycle/moteur, catégorie, search par nom
- Colonnes : ID, moto, partie, catégorie, nom_constructeur, nom_fr, nb hotspots, actif

**D. Éditeur hotspots** (`AdminMsMicrofichesController::viewAction`)
- Affichage image microfiche + overlay des hotspots actuels (en SVG positionné absolument)
- Lecture seule en V1 (édition manuelle V2)
- Panneau latéral droit avec liste des hotspots et liens vers leurs `ps_product` rattachés
- Mise en évidence des hotspots **non rattachés** (`id_product IS NULL`) avec badge "produit manquant"

**E. Référentiel catégories** (`AdminMsCategoriesController`)
- CRUD complet (création/édition manuelle)
- Champs : partie, numéro constructeur, code, nom_fr, ordre_affichage
- C'est l'écran utilisé pour le mapping FR (atelier métier)
- **Auto-création** : lors de l'import microfiche, si une catégorie `(partie, numero)` n'existe pas, la créer automatiquement avec `nom_fr = NULL` et `code = "TODO_<partie>_<numero>"`. Elle apparaîtra dans le listing en attente de saisie FR.

**F. Interface import** (`AdminMsImportController`)
- Onglet 1 : Import motos (CSV unique ou les 3 marques d'un coup)
- Onglet 2 : Import microfiches (upload d'un CSV par moto)
- Stats post-import : lignes traitées, motos créées / mises à jour, microfiches créées, hotspots créés, produits manquants
- Logs exportables

### 6.2 Front-office

**A. Page moto** (`controllers/front/moto.php`)

URL : `/motos/{marque}/{annee}/{slug-moto}`

Contenu :
- Header avec photo principale moto, marque, année, type
- 2 colonnes principales :
  - Gauche : filtres de catégories microfiches (partie cycle/moteur + catégories actives)
  - Droite : grille des microfiches (thumbnail + nom_fr ou nom_constructeur, lien vers PDP microfiche)
- Filtres réactifs (JS, pas de rechargement)

**B. Page microfiche** (`controllers/front/microfiche.php`)

URL : `/microfiches/{id_microfiche}-{slug-microfiche}`

Contenu (conforme à la maquette `PDP_microfiche.png` fournie) :
- Bandeau "Catalogue filtré sur [moto]" en haut
- Bandeau de confirmation compatibilité
- Image éclatée à gauche (~60% largeur) avec overlay SVG des hotspots numérotés
- Panneau droit (~40%) : liste des produits avec ref OEM, prix (depuis ps_product/G8), bouton "Ajouter au panier" (qty pré-remplie depuis qty_recommended)
- Lien "Télécharger la microfiche" (placeholder en V1, fonctionnalité V2)
- Sous la microfiche : autres microfiches compatibles avec la moto sélectionnée
- Carrousel "Produits qui pourraient vous plaire"

### 6.3 Interactions hotspots (`front-microfiche.js`)
- Au hover sur un hotspot → highlight la ligne produit correspondante dans le panneau droit
- Au click sur un hotspot → scroll vers la ligne produit + highlight
- Au hover sur une ligne produit → highlight le hotspot correspondant
- Ajout au panier AJAX (hook standard Presta `actionCartUpdate`)
- Calcul du ratio image_width/height vs taille rendue pour positionner les hotspots SVG

### 6.4 Périmètre EXCLU de V1 (à anticiper en archi mais pas à coder)

- Édition manuelle des hotspots côté BO (drag/drop, placement à la souris)
- Action "Dupliquer une moto" depuis le BO
- Action "Reprendre les parties d'une autre moto" (templating)
- Génération PDF de la microfiche
- Téléchargement des images en local (V1 = consommation directe URL constructeur)
- Sync temps réel G8 sur les prix des hotspots (V1 = prix `ps_product` natif)

---

## 7. Hooks PrestaShop

Hooks à enregistrer dans le module :

- `displayHeader` : injection CSS/JS front
- `displayBackOfficeHeader` : injection CSS/JS BO
- `actionProductAdd` / `actionProductUpdate` : déclenche le rematching des hotspots orphelins quand un nouveau produit est créé (job léger, par batch de 100)
- `actionAdminControllerSetMedia` : assets BO

---

## 8. Conventions de code

- PSR-12 strict
- PHP 8.1+ (Presta 8.2)
- Type hints partout, return types partout
- Pas de SQL en dur dans les controllers → tout passe par les ObjectModel ou un Repository dédié
- Logs via `PrestaShopLogger::addLog()`
- Idempotence sur tous les imports (test : réimporter 2x le même CSV doit donner le même état final)
- Pas de `var_dump`/`dd`/`echo` debug laissé dans le code
- Commentaires en français (équipe MSS francophone)

---

## 9. Stratégie de tests

Pas de framework de tests imposé par Presta, mais :

- **Tests unitaires** sur les dérivations (`core_name`, `cylindree`, `type` via dictionnaire) — PHPUnit ou tests inline dans une commande CLI
- **Tests d'intégration** sur les imports : un jeu de 10-20 motos KTM/HQV/GG + 2-3 CSV microfiches dans un répertoire `tests/fixtures/`, scénarios :
  - Import vierge → vérifier les comptes attendus
  - Réimport du même fichier → idem comptes, pas de doublons
  - Import avec produit manquant → hotspot créé avec `id_product IS NULL`
  - Création auto de catégorie inconnue → catégorie créée avec `nom_fr IS NULL`

---

## 10. Ordre de réalisation suggéré

1. **Squelette module** : `mssmicrofiches.php`, `config.xml`, install/uninstall avec création des 4 tables
2. **ObjectModels** des 4 entités
3. **Importer motos** + dictionnaire taxonomie + dérivations → import des 3 CSV en console
4. **Importer microfiches** (1 CSV de test : F0403X7) + matching produit
5. **BO listings** motos / microfiches / catégories
6. **BO éditeur hotspots** (lecture seule)
7. **Front moto** (PDP avec filtres)
8. **Front microfiche** (PDP avec hotspots interactifs)
9. **Cron de rematching** des hotspots orphelins
10. **Tests d'intégration** et fixtures

---

## 11. Fichiers de référence à demander avant de coder

Avant la première ligne, demander à l'utilisateur ces fichiers s'ils ne sont pas fournis :

- [ ] `KTM_MOTORCYCLES.csv` (Latin-1, ~6000 lignes, 30 colonnes)
- [ ] `HQV_MOTORCYCLES.csv` (UTF-8, ~1200 lignes)
- [ ] `GASGAS_MOTORCYCLES.csv` (UTF-8, ~360 lignes)
- [ ] Au moins un CSV microfiche de test (ex `F0403X7.csv` — GASGAS EC 300 2024)
- [ ] Maquettes : `PDP_microfiche.png` (page microfiche), `PLP_CAT_SPAREPARTS.png` (page moto)
- [ ] Accès BDD Presta de préprod pour valider les écritures

---

## 12. Questions à clarifier avec MSS avant ou pendant le dev

Ces points sont en cours d'arbitrage métier (atelier microfiches prévu). Si bloqué pendant le dev, **ne pas inventer, demander** :

1. Mapping FR des ~30 catégories microfiches (engine 30, frame 13, etc.) — à l'atelier microfiches métier
2. Workflow de validation pour les motos auto-classées en `type='Autres'` (interface à prévoir mais pas urgente)
3. Sort des hotspots orphelins (`id_product IS NULL`) côté front : on les affiche désactivés ? on les masque ? on affiche "nous consulter" ?
4. Format final des URLs front (slugs FR ou anglais, structure exacte)
5. Articulation avec EveryParts pour le bandeau "Compatible avec [moto]" : à brancher dans un 2e temps

---

## 13. Volumétrie & performance — points de vigilance

- 1.5M hotspots : indexer correctement, jamais de full table scan
- Image éclatée chargée depuis URL constructeur (V1) : prévoir un timeout court (5s) et un fallback "image non disponible"
- Cache des listings BO motos : 1830 lignes, pagination 50/page, doit rester rapide même sans cache
- Page moto front : ~50 microfiches à afficher en grille, prévoir lazy loading des thumbs (loading="lazy" natif)
- Page microfiche front : ~30 hotspots, ~30 lookups `ps_product` → faire un seul `JOIN` ou un `IN(...)`, pas N+1 queries

---

## 14. Ce que ce brief NE résout PAS

- L'éditeur visuel de hotspots côté BO (placement à la souris) — c'est un chantier UX dédié pour la V2
- L'intégration G8 temps réel sur les prix des hotspots — autre module, autre chantier
- La migration des données depuis l'ancien CMS — script dédié à écrire séparément (volet 2 du chantier microfiches)
- L'articulation avec EveryParts — module tiers, point d'intégration à définir

---

## 15. Critère de "fini" pour la V1

Le module est considéré V1 prête pour recette quand :

- ✅ Les 3 CSV motos s'importent sans erreur, 1830 motos en base, taxonomie correctement appliquée
- ✅ Un CSV microfiche (F0403X7) s'importe, microfiches + hotspots créés, catégories auto-créées
- ✅ Les BO listings (motos / microfiches / catégories) sont navigables et filtrables
- ✅ Le référentiel catégories est éditable en BO (saisie des libellés FR)
- ✅ La PDP moto affiche les filtres + grille microfiches conformes à la maquette PLP
- ✅ La PDP microfiche affiche l'image + hotspots cliquables + panneau produits + ajout panier
- ✅ Réimport idempotent (test : 2x le même CSV → même état BDD)
- ✅ Un produit créé après l'import déclenche le rematching de ses hotspots orphelins
- ✅ Tests d'intégration passent

---

**Fin du brief.**
