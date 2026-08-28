# Cadrage SEO — Référencement des pages par moto

**Projet :** Mega Service Shop — PrestaShop 8.2
**Date :** 2026-07-29
**Portée :** deux mécanismes de compatibilité **distincts** (OEM microfiches / Powerparts montabilité), traités séparément mais sous une **doctrine SEO commune**.

---

## Principe directeur commun

Chaque modèle de moto devient une **porte d'entrée SEO** — longue traîne à fort intérêt commercial (« pièces / accessoires pour KTM 250 EXC-F 2024 »).

> Le levier SEO n'est **pas** la profondeur du chemin d'URL — c'est **title + meta + canonical + maillage interne + sitemap**.

On garde des **URL courtes et robustes**, et on investit l'effort sur ces cinq leviers.

### Règle d'or de routing (déjà actée — ne pas re-litiger)
Schéma **id-based partout**. PrestaShop route sur l'`id`, donc le slug est **purement cosmétique** et un slug obsolète charge quand même la bonne page.
On n'introduit **jamais** de segments d'URL **mutables** (type, année, libellé de catégorie) dans le chemin : ils cassent au moindre renommage et imposent des cascades de 301.

---

# VOLET 1 — Microfiches OEM (compatibilité native, déjà routée)

## Contexte
Le module `megaservice_microfiches` expose déjà des URL propres, id-based, **déjà indexables** :

| URL | Page |
|---|---|
| `/motos/{id}-{slug}` | Hub moto |
| `/motos/{id}-{slug}?partie=cycle` | PLP scopée `cycle` \| `moteur` |
| `/microfiches/{id}-{slug}` | PDP microfiche |

L'OEM est **masqué du catalogue général** (`visibility='none'`) par conception : il ne surface **que** via les microfiches. Le SEO OEM par moto est donc **structurellement déjà en place** — il ne manque que l'**optimisation on-page**.

## Décisions de structure d'URL (reco = statu quo)
- **Garder l'id-based.** Ne **pas** passer `partie` en segment d'URL (`/partie-cycle/`) : détourner le dispatcher natif du contrôleur = chantier fragile pour un gain SEO nul. `partie` reste un **paramètre**.
- Ne **pas** créer d'arborescence profonde (`/motos/enduro/2024/…`) : niveaux parents mutables + pages intermédiaires à maintenir + risque de 404.
- **Seul ajustement retenu — enrichir le slug** sans toucher à la structure :
  - de `834-2024-ktm-250-exc-f` → vers un slug portant **marque + modèle + année + type** : `834-ktm-250-exc-f-2024-enduro`.
  - Mots-clés dans l'URL, **zéro risque** puisque le routing est sur l'id.
  - **Audit fait (2026-07-29) :** le slug est aujourd'hui construit **ad-hoc dans ~7 endroits** et **incohérent** — `Tools::str2url($moto->nom_fr)` dans `moto.php` / `microfiche.php` (ex. `1290-super-duke-r-2014`), mais `str2url($core_name)` dans `selectordata.php`. **Reco :** introduire un **helper central** `MsMoto::slug()` (marque + core_name + année + type) et remplacer **tous** les call sites. Zéro risque (routing id-based).

## Travail on-page à livrer (le vrai gain)
- **`<title>` dédié par page :**
  - hub moto : « Pièces d'origine — {Marque} {Modèle} {Année} »
  - PLP partie : « Pièces d'origine partie {cycle\|moteur} — {Marque} {Modèle} {Année} »
  - PDP microfiche : « {Nom FR microfiche} — {Marque} {Modèle} {Année} »
- **meta description** générée par moto / partie.
- **canonical propre :** sur la PLP partie, canonical vers la vue partie « nue » (sans tri/pagination). Le hub moto est sa propre canonical.
- **balisage :** un `H1` explicite reprenant Marque / Modèle / Année.
- **maillage :** hub moto → PLP cycle/moteur → PDP microfiche (déjà en place) + lien depuis chaque **fiche produit OEM** vers **sa microfiche** (point d'entrée déjà décidé, à câbler).
- **sitemap :** une entrée hub par moto + les PLP parties + les PDP microfiches.

---

# VOLET 2 — Powerparts (montabilité, via paramètre `?moto=`)

## Prérequis en place
Le module de **montabilité est déployé et opérationnel** — le filtrage compatibilité fonctionne déjà (facettes conservées via le hook `actionFacetedSearchFilters` de `ps_facetedsearch`).
Ce volet est **purement une surcouche SEO** par-dessus l'existant : il ne touche **pas** au filtrage, il rend **indexable** ce qui n'était accessible que par cookie. **Aucune dépendance de développement bloquante.**

## Problème
Le filtre « moto en garage » sur le catalogue Powerparts (catégorie **41**) est stocké en **cookie** → un robot n'a pas le cookie, voit le catalogue **non filtré**, et **aucune page « accessoires pour moto X » n'existe** pour Google. Même URL = contenu différent selon le visiteur (mauvais pour cache, partage, SEO).

## Principe
L'**URL devient la source de vérité** du filtre, le **cookie reste en secours** (confort de navigation). Résolution de la moto active :
1. moto dans l'**URL** (paramètre) → prioritaire, crawlable, partageable
2. sinon **cookie** (garage) → confort

## Forme d'URL retenue — paramètre (pas segment propre)
```
/41-accessoires-powerparts?moto=2587-ktm-1290-super-duke-r-2014
```
- `2587` = `id_moto` (résolution fiable, insensible au renommage de slug) — slug cosmétique.
- **Justification :** faire accepter un segment supplémentaire au `CategoryController` (cœur PrestaShop) impose de détourner le **dispatcher natif** — fragile, cassable à chaque montée de version — pour un gain SEO **marginal** dès lors que le paramètre porte canonical + maillage propres. Google indexe parfaitement les URL à paramètre. **Segment propre = reporté.**

## Architecture (par étape, chacune déployable/testable indépendamment)

### Étape 1 — Moto dans l'URL, source de vérité
- **Lecture :** partout où on lit `context->cookie->ms_moto` (override `CategoryController`, hook `actionFacetedSearchFilters`), lire **d'abord** `Tools::getValue('moto')` (extraire l'`id_moto` de tête), sinon le cookie.
- **Écriture :** quand une moto est résolue depuis l'URL, **réarmer le cookie** (garage cohérent).
- **Helper central** `motoFilteredCategoryUrl($idCategory, $idMoto)` → produit l'URL catégorie + `?moto=id-slug`.

### Étape 2 — Canonical + meta (le cœur SEO)
Dans l'override `CategoryController::initContent()`, quand le filtre moto est actif :
- forcer le **canonical** vers l'URL « moto seule » (sans facette `q=`)
- **`<title>`** : « Accessoires Powerparts pour {Marque} {Modèle} {Année} »
- **meta description** dédiée
- **`H1`** reprenant la moto

### Étape 3 — Maillage interne
Sur le hub moto (`moto-hub.tpl`), le lien « Accessoires Powerparts » pointe aujourd'hui vers la catégorie 41 **générique** → le faire pointer vers l'**URL filtrée** de cette moto (`?moto=…`).
**C'est ce lien qui connecte le SEO OEM (volet 1) et le SEO Powerparts (volet 2)** sur la même page moto.

### Étape 4 — Sitemap
Générer, pour chaque moto active, l'URL Powerparts filtrée. Exposer via un **contrôleur sitemap dédié** du module **ou** en alimentant le module sitemap existant s'il expose un hook (**question ouverte D5**).
**Segmenter** le sitemap (index multi-fichiers) : ~1800 motos, surveiller la limite de **50 000 URL/fichier**.

### Étape 5 — Contrôle d'indexation (anti-duplication)
- **Indexable :** catégorie racine Powerparts × moto, **facette vide**.
- **`noindex,follow` :** dès qu'une facette `q=` s'ajoute à la moto (évite l'explosion combinatoire) ; canonical de ces pages → vue « moto seule ».
- **Critique pour le budget de crawl.**

## Arbitrages à acter
| # | Décision | Statut |
|---|---|---|
| D1 | Forme d'URL : **paramètre** | ✅ acté |
| D2 | Source de vérité : **URL** prioritaire, cookie secours | ✅ acté |
| D3 | Granularité : démarrer **moto × catégorie racine 41 uniquement** (pas une page par sous-catégorie × moto — risque de dizaines de milliers d'URL ; à n'ouvrir que si les premières pages performent) | ✅ acté |
| D4 | Combinaisons moto + facette : **noindex + canonical** vers moto seule | ✅ acté |
| D5 | Sitemap : **gsitemap** installé (module PS par défaut) — ne couvre **pas** les routes `/motos/…` ni les URL `?moto=`. **Reco actée : sitemap dédié au module** (index multi-fichiers, façon ukooparts), gsitemap conservé pour le catalogue standard. Découvrabilité via robots.txt + Search Console. | ✅ acté (sitemap dédié) |
| — | Meta/title **éditorialisables en BO** : **NON** en v1 (génération auto suffit) ; éditorialisation des motos à fort trafic en **v2** | ✅ acté |

---

# Séquençage global

1. **Volet 1 (microfiches OEM)** — livrable, **indépendant** : slug enrichi + title/meta/canonical/H1 + sitemap. **Aucune dépendance.**
2. **Volet 2 (Powerparts)** — livrable **en parallèle** du volet 1 (la montabilité étant déjà en place). Ordre interne : étape 1-2 (socle SEO) → 3 (maillage) → 4 (sitemap) → 5 (hygiène noindex).

**Point de convergence** — le lien « Accessoires Powerparts » du hub moto pointant vers l'URL `?moto=` filtrée — est **activable immédiatement**, les deux bouts existant déjà.

---

# Hors périmètre v1 (les deux volets)
- Segment d'URL 100 % réécrit sans « ? » (reporté, chantier routing).
- Pages SEO éditorialisées (contenu rédactionnel par moto).
- Filtrage moto hors sous-arbre Powerparts.
- Recherche VIN.

# Risques
- **Duplication de contenu** (combinaisons facette + moto) → mitigé par `noindex` + canonical.
- **Volumétrie sitemap** (~1800 motos) → segmenter.
- **Cohérence cookie ↔ URL** → l'URL gagne toujours, éviter le filtre fantôme.
- **Budget de crawl** → ne pas laisser explorer les combinaisons de facettes.
