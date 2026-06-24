# Dette technique — Megaservice Theme

Liste vivante des hacks, raccourcis, et optimisations à reprendre quand le projet aura le temps. Ajouter une entrée à chaque fois qu'on choisit pragma sur clean.

**Format** : titre, contexte, impact, fix proposé, statut.

---

## 🟡 Module microfiches — collation tables ms_* alignée sur PrestaShop a posteriori

**Fichier** : [modules/megaservice_microfiches/sql/install.sql](modules/megaservice_microfiches/sql/install.sql) + [migration 002](modules/megaservice_microfiches/sql/migrations/002_align_collation_with_prestashop.sql)

**Contexte** : l'install.sql initial utilisait `utf8mb4_unicode_ci` (choix "moderne", trié plus correctement). PrestaShop 8 utilise `utf8mb4_general_ci` pour toutes ses tables natives. MySQL refuse de comparer 2 chaînes avec collations différentes → tout JOIN entre nos `ms_*` et les `ps_*` natives planterait avec `#1267 Illegal mix of collations`.

**Détecté** quand on a voulu mesurer combien de nos `article_ref` matchent des `ps_product.reference` (préparation PR8 rematching).

**Fix appliqué** :
1. `install.sql` : 4 occurrences `utf8mb4_unicode_ci` → `utf8mb4_general_ci`
2. `sql/migrations/002_align_collation_with_prestashop.sql` : 4 `ALTER TABLE` à exécuter manuellement en phpMyAdmin sur les bases déjà installées (préprod)
3. Aucun changement code PHP : les jointures avec `ps_*` deviendront possibles sans `COLLATE` forcé après migration

**Statut** : 🟢 corrigé en code et schéma. Migration manuelle requise sur préprod.

---

## 🟡 Module microfiches — UNIQUE KEY hotspot sous-spécifiée dans le brief (corrigée a posteriori)

**Fichier** : [modules/megaservice_microfiches/sql/install.sql](modules/megaservice_microfiches/sql/install.sql) + [migration 001](modules/megaservice_microfiches/sql/migrations/001_hotspot_unique_with_position.sql)

**Contexte** : le brief §4.3 spécifiait la clé fonctionnelle d'un hotspot comme `(id_microfiche, article_ref, sequence_number)`. Mesure réelle sur le 1er CSV testé (F0403X7.csv = GASGAS EC 300 2024, 1466 hotspots) :
- 1250 clés uniques selon la spec brief
- 1463 clés uniques en incluant `position_x` + `position_y`
- **→ 213 hotspots (14.5%) silencieusement écrasés** par le INSERT ON DUPLICATE KEY UPDATE

**Cause racine** : une même pièce numérotée (ex. vis M6 "10") peut apparaître plusieurs fois sur une vue éclatée à des endroits visuels différents (jusqu'à 9 fois pour les vis répétitives du moteur). Chaque occurrence est un vrai hotspot cliquable distinct.

**Fix appliqué** (commit qui suit la mesure) :
1. `install.sql` : `UNIQUE KEY uk_hotspot_naturel` étendue à 5 colonnes (ajout position_x, position_y)
2. `sql/migrations/001_hotspot_unique_with_position.sql` : script `ALTER TABLE` pour les installs déjà en place (préprod, prod future). À exécuter manuellement via phpMyAdmin.
3. Aucun changement code PHP : `MicrofichesImporter::upsertHotspot()` n'est pas impacté (le SQL ON DUPLICATE KEY UPDATE marche identiquement avec la nouvelle clé).

**Impact résiduel** : la doc du brief §4.3 reste à corriger côté MSS — la spec officielle devrait inclure la position dans la clé naturelle.

**Statut** : 🟢 corrigé en code et schéma. Migration manuelle requise sur les bases déjà installées (préprod en attente d'exécution au moment de cet ajout).

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

---

## 🔴 Import CSV Powerparts — accepter les refs constructeur au lieu d'`id_product`

**Fichiers** :
- [modules/megaservice_relations/classes/CsvImporter.php](modules/megaservice_relations/classes/CsvImporter.php) (`REQUIRED_COLS`)
- [modules/megaservice_relations/views/templates/admin/config-import.tpl](modules/megaservice_relations/views/templates/admin/config-import.tpl)
- [samples/test-relations-import-29.csv](samples/test-relations-import-29.csv)

**Contexte** : Le CSV exige `id_product_source` / `id_product_target` en integers PS internes. Or les fichiers constructeur (KTM Powerparts notamment) ne contiennent QUE des **références** (SKU `60100978000` etc.) — jamais d'`id_product` PS qui sont des données internes au shop. Sans support des refs, impossible de balancer un CSV constructeur tel quel : il faut un script intermédiaire de mapping `ref → id` à maintenir.

**Impact** :
- Bloquant pour intégrer la data fournisseur réelle (KTM, Husqvarna, GasGas)
- Glue script à maintenir = source d'erreurs (mapping périmé après chaque nouveau produit ajouté)
- Les ids varient entre dev/preprod/prod, les refs sont stables

**Fix proposé** :
1. Renommer les colonnes requises en `ref_source`, `relation_type`, `ref_target` (+ optionnels `position`, `recommended_qty`)
2. **Une seule** requête en début d'import : `SELECT id_product, reference FROM ps_product WHERE reference IN (...)` pour récupérer toutes les refs du CSV en batch
3. Construire la map `[ref => id_product]`, traduire chaque ligne, remonter en erreur les refs absentes (avec n° de ligne + ref invalide)
4. Le reste de la logique (modes append / replace_for_listed / full_replace, dedup) reste identique

**Perf** : meilleures qu'aujourd'hui — 1 SELECT batch au lieu de N `existsProduct()` actuels (un par ligne). Sur un import de 10k lignes : 1 requête vs 10k.

**Effort** : ~1h.

**Statut** : 🔴 max important — bloquant pour l'usage prod. À implémenter avant le premier vrai import constructeur.

---

## 🟡 Import CSV — pas de feedback progressif (progress bar) ni de chunking

**Fichier** : [modules/megaservice_relations/classes/CsvImporter.php](modules/megaservice_relations/classes/CsvImporter.php)

**Contexte** : L'import est synchrone — un POST `/admin?configure=megaservice_relations` traite tout le fichier ligne par ligne en PHP, puis renvoie la page de résultats à la fin. Pas de progress bar, pas de chunking, pas de streaming.

**Impact** :
- Sur un gros fichier (>5k lignes), le navigateur peut hit le `max_execution_time` PHP (30s par défaut) ou un timeout proxy → import partiel et state incohérent (mode `full_replace` particulièrement risqué : la table est vidée puis l'insert plante en cours)
- UX : l'admin attend devant un loader sans savoir si ça avance ou si c'est freezé
- Pas de retry/reprise possible

**Fix proposé** :
- **Court terme** : afficher un loader explicite sur le submit, augmenter `max_execution_time` localement via `set_time_limit(0)` au début de l'import
- **Moyen terme** : chunker l'import en transactions de 500-1000 lignes, exposer un endpoint AJAX `?step=N` que la page poll, afficher une vraie progress bar en frontend (X / Y lignes traitées)
- **Long terme** : queue async (Symfony Messenger ou job PrestaShop) — l'admin upload, reçoit un job id, peut quitter la page, voit le statut au retour

**Effort** : 30 min court terme / 3h moyen terme / 1 jour long terme.

**Statut** : 🟡 à reprendre si on rencontre un timeout sur un vrai import constructeur.

---

## 🟠 Footer — liens hardcodés `href="#"` au lieu de routes dynamiques

**Fichier** : [megaservice/templates/_partials/footer.tpl](megaservice/templates/_partials/footer.tpl)

**Contexte** : Le footer contient 13 liens `href="#"` non câblés à de vraies destinations PS. Tant qu'on n'a pas créé les pages CMS / catégories / politiques côté back-office, on pointe vers `#` pour l'intégration visuelle.

**Liens concernés** :

*Colonne "La Société"* :
- A propos (ligne 35)
- Livraison et retours (ligne 36)
- Blog (ligne 37)
- FAQ (ligne 38)

*Colonne "Catalogue"* :
- Motos neuves (ligne 47)
- Motos d'occasion (ligne 48)
- Equipements pilotes (ligne 49) → catégorie 15 existante (`{$urls.pages.category15}`)
- Pièces d'origines (ligne 50)
- Accessoires powerparts (ligne 51) → catégorie 41 existante
- Sportswear (ligne 52)

*Newsletter (politique GDPR)* :
- "Voir la politique de données personnelles" (ligne 65)

*Barre légale* :
- Conditions générales de vente (ligne 78)
- Mentions légales (ligne 79)
- Politique de confidentialité (ligne 80)
- Vie privée et cookies (ligne 81)

**Impact** :
- Cliquer sur un lien fait défiler en haut de page (pas d'erreur, mais comportement inattendu)
- SEO : Google indexe les liens `#` comme un trou
- Accessibilité : nuit aux lecteurs d'écran
- Légal : les CGV / Mentions légales / Politique de confidentialité sont **obligatoires** côté FR pour un site marchand. À fixer avant la mise en prod.

**Fix proposé** :
1. **Pages catégorie** : remplacer par `{$urls.pages.categoryN}` ou construire l'URL via `{url entity='category' id=N}` quand les ID sont stables (ex: 15 et 41 connus). Idéalement créer une variable Smarty mappée dans un controller pour les autres.
2. **Pages CMS** (CGV, Mentions, RGPD, Cookies, A propos, FAQ, Livraison, Blog) : les créer dans le BO Presta (Design → Pages) puis utiliser `{$urls.pages.cmsN}` ou le widget `ps_linklist` (déjà standard PS) pour rendre la liste dynamiquement.
3. **Newsletter GDPR link** : pointer vers la page CMS "Politique de confidentialité" une fois créée.
4. **À long terme** : remplacer les colonnes hardcodées du footer par le module `ps_linklist` (config dans le BO, plusieurs blocs de liens, multilingue gratuit).

**Effort** : 30 min si toutes les pages CMS existent, ~2h si faut les créer (+ rédaction).

**Statut** : 🟠 important — bloquant pour la mise en prod (légalement et UX). À traiter en priorité avant le go live.

---

## 🟠 Newsletter — formulaire factice non câblé à un service mailing

**Fichier** : [megaservice/templates/_partials/footer.tpl](megaservice/templates/_partials/footer.tpl#L60-L67)

**Contexte** : Le formulaire newsletter du footer (`<form action="{$urls.pages.index}" method="post">`) est purement visuel. Le submit POST vers la home (qui ne traite rien), donc l'inscription n'est **enregistrée nulle part**. Le checkbox GDPR existe mais n'a pas d'effet.

**Impact** :
- Les emails saisis par les visiteurs sont **perdus** (le POST sur l'index n'enregistre rien)
- Promesse contractuelle non tenue : on annonce "-10% sur la 1ère commande" sans mécanisme pour délivrer ni le code promo ni le mail de bienvenue
- Pas de RGPD opt-in tracé (aucune trace consentement) → risque légal si quelqu'un saisit son mail
- Faux positif : un visiteur inscrit pense être abonné mais n'aura jamais de news

**Fix proposé** :
1. **Choisir un service mailing** : Brevo (ex-Sendinblue, fr, gratuit jusqu'à 300 envois/jour), Mailchimp, Mailjet, ConvertKit, etc.
2. **Câbler via API** :
   - **Option A — module PS dédié** : la plupart des services ont un module officiel (`brevo`, `mailchimp` etc.) qui s'occupe de tout (form, double opt-in, sync contacts)
   - **Option B — controller custom** : créer un endpoint front module qui reçoit le POST, valide email + GDPR, fait l'API call vers le service, gère retours d'erreurs / messages de succès
3. **Workflow attendu** :
   - Submit → enregistre dans la liste mailing (status: pending)
   - Email de double opt-in envoyé (RGPD)
   - Au confirm → status: confirmed + email avec code promo `BIENVENUE10`
   - Stockage du consentement (date/IP/version CGV) en BDD pour traçabilité légale

**Effort** : ~2h avec module officiel du service choisi / ~1 jour pour solution custom.

**Statut** : 🟠 important — non bloquant techniquement (le form ne plante pas) mais bloquant fonctionnellement (l'engagement marketing n'est pas tenu) + risque RGPD. À fixer dès qu'un service mailing est choisi.

---

## 🟢 Module microfiches — upload BO des CSV motos (résolu)

**Fichier** : [modules/megaservice_microfiches/megaservice_microfiches.php](modules/megaservice_microfiches/megaservice_microfiches.php) (`getContent()`, `handleUploadAndImport()`)

**Contexte historique** : la 1ʳᵉ version de `getContent()` se contentait de scanner `<PS root>/data/imports/` pour les CSV déposés en SCP/SSH. UX dégradée pour l'admin sans accès shell.

**Résolution** : ajout d'un panneau d'upload (form `multipart/form-data`) qui :
1. Valide l'extension `.csv`
2. Déduit la marque depuis le nom de fichier (via `MotosImporter::deduceMarque`)
3. Déplace le fichier dans `data/imports/<MARQUE>_MOTORCYCLES.csv` (nom canonique)
4. Lance l'import immédiatement et affiche le rapport
5. Affiche les limites PHP (`upload_max_filesize`, `post_max_size`) + mapping clair des codes d'erreur (1=trop volumineux, 3=interrompu, etc.)

**Limites résiduelles** :
- Sur Plesk/hébergement mutualisé, `upload_max_filesize` peut être <32 Mo → l'upload KTM échouera. Il faut alors augmenter via `.htaccess` ou demander à l'hébergeur. L'erreur est explicite.
- Pas de versioning des CSV (un nouvel upload écrase le précédent). Bonus optionnel pour V2 : renommer en `.YYYYMMDD-HHMM.csv` avant écrasement.

**Statut** : 🟢 résolu.

---

## 🟡 Module microfiches — bruit HTML dans les CSV constructeur

**Fichier** : [modules/megaservice_microfiches/classes/importers/MotosImporter.php](modules/megaservice_microfiches/classes/importers/MotosImporter.php) (`buildRow()`)

**Contexte** : Les CSV `KTM_MOTORCYCLES.csv`, `HQV_MOTORCYCLES.csv` et `GASGAS_MOTORCYCLES.csv` fournis par le constructeur contiennent en début de fichier des fragments HTML (lignes `<td style="..."`, `<tr ...>`, `</tr>`) au lieu de vrais rows CSV. Origine probable : copié-collé depuis un export web. L'importer filtre via `^\$M-` sur `MODELNUMBER` et compte ces lignes en `skipped` dans le rapport.

**Impact** :
- Aucun bug fonctionnel : les rows polluées sont silencieusement skippées
- Le compteur "Skippées" du rapport BO peut dérouter l'admin (élevé) — à expliquer dans la doc
- Si la pollution change de forme (ex. autre balise HTML), notre filtre `^\$M-` pourrait laisser passer des rows invalides

**Fix proposé** :
1. Court terme : OK comme tel, le filtre est robuste
2. Idéal : remonter le bug à la source (équipe constructeur / KTM Powerparts) pour qu'ils livrent des CSV propres
3. Alternative : pré-processer le CSV avant import (script awk de nettoyage) — mais alourdit le pipeline pour pas grand-chose

**Statut** : 🟡 monitoring — vérifier que le compteur "skipped" reste cohérent dans le temps.

---

## 🟡 Module microfiches — strip d'accents par table explicite (workaround libiconv BSD macOS)

**Fichier** : [modules/megaservice_microfiches/classes/importers/CsvReader.php](modules/megaservice_microfiches/classes/importers/CsvReader.php) (`stripAccents()`)

**Contexte** : `CsvReader::normalizeHeader()` doit retirer les accents pour canoniser les noms de colonnes (`année` / `annee` / `ANNEE` → `annee`). La méthode standard `iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s)` fonctionne avec GNU libiconv (Linux serveur) mais **strippe silencieusement** les caractères accentués sans les convertir avec la libiconv BSD livrée par macOS — résultat : `année` devient `anne` puis `ann_` après notre regex.

Solution adoptée : table de remplacement PHP explicite (`strtr` sur ~50 caractères latins courants) — déterministe et portable, mais ne couvre que les accents latins (pas le cyrillique, grec, etc., on s'en fout pour des CSV moto FR/EN/DE/ES/IT).

**Impact** :
- Code un peu plus verbeux que `iconv TRANSLIT`
- Couvre les besoins actuels à 100% (validé sur les 3 CSV)
- Ne couvre pas un futur CSV cyrillique / asiatique → pas un sujet sur ce projet

**Fix proposé** : aucun, c'est la bonne solution pour la portabilité dev macOS / prod Linux.

**Statut** : 🟢 résolu, documenté pour mémoire — ne pas "améliorer" en remettant iconv.

---

## 🟡 Module microfiches — modèles KTM 125 LC2/Sting d'avant 2000 tombent en taxonomie 'Autres'

**Fichier** : [modules/megaservice_microfiches/classes/importers/MotosTaxonomy.php](modules/megaservice_microfiches/classes/importers/MotosTaxonomy.php) (`RULES`)

**Contexte** : Le dictionnaire taxonomie du brief §4.2 est conçu pour les modèles modernes (post-2010 grosso modo). Sur les samples on a observé 5 motos KTM des années 1997-1998 qui tombent en `Autres` :
- `125 LC2 100/WEISS`, `125 STING`, `125 LC2 80`, `125 STING/100`, `125 LC2 11KW MIL`

Conforme au brief qui prévoit explicitement de loguer ces motos pour correction manuelle BO (cf. compteur `autresFound` du `MotosImportReport`).

**Impact** :
- Sur l'import complet (~1830 motos), proportion d'`Autres` à mesurer
- Si > 10% : enrichir le dictionnaire (ajouter règles `LC2`, `Sting` → `Enduro` ou `Naked` selon le modèle)
- Sinon : laisser tel quel, corriger via BO (édition manuelle du `type`)

**Fix proposé** : après le 1er import complet, mesurer la proportion et décider. Si patch dictionnaire, ajouter les règles avant `Motocross (SX|MC)` pour ne pas voler des matchs légitimes.

**Statut** : 🟡 à mesurer après import complet sur preprod.

---

## 🔴→🟢 Module microfiches — UNIQUE KEY hotspots basée sur la position vivante défaisait la protection `manually_edited`

**Fichiers** :
- [modules/megaservice_microfiches/sql/migrations/005_hotspot_unique_on_original_position.sql](modules/megaservice_microfiches/sql/migrations/005_hotspot_unique_on_original_position.sql) (fix)
- [modules/megaservice_microfiches/sql/install.sql](modules/megaservice_microfiches/sql/install.sql) (clé alignée)
- [modules/megaservice_microfiches/classes/importers/MicrofichesImporter.php](modules/megaservice_microfiches/classes/importers/MicrofichesImporter.php) (`upsertHotspot`, doc)

**Contexte** : deux décisions livrées séparément se contredisaient.
- Migration 001 / décision archi #5 : `uk_hotspot_naturel` étendue avec `position_x`/`position_y` **vivantes** (pour autoriser une même pièce à plusieurs endroits de la vue éclatée).
- Migration 003 : flag `manually_edited` + `IF(manually_edited=1, ...)` dans le `ON DUPLICATE KEY UPDATE` de `upsertHotspot`, censé protéger les positions déplacées au drag/drop BO contre l'écrasement au réimport CSV.

Or le `ON DUPLICATE` ne se déclenche que si la clé UNIQUE entrante percute une ligne existante. Le save drag/drop BO modifie `position_x`/`position_y` — donc **modifie la clé elle-même**. Au réimport, le CSV porte la position constructeur d'origine : l'INSERT ne percute plus la ligne déplacée → INSERT frais → **doublon** (un hotspot fantôme constructeur réapparaît à côté du déplacé). La branche `TRUE` du `IF` n'était jamais atteinte : la protection 003 était du code mort dès qu'on bougeait un cercle.

**Reproduit en preprod (2026-06-09)** : drag d'un hotspot puis réimport → 2 hotspots pour la même pièce.

**Fix** : rebaser la clé naturelle sur `position_x_original`/`position_y_original` (position constructeur, stable au drag). Le réimport percute alors toujours la bonne ligne → `ON DUPLICATE` → `IF(manually_edited=1,...)` protège enfin la position vivante. L'intention de #5 est préservée (une même pièce à N endroits du CSV = N originaux distincts = N clés distinctes). Migration 005 nettoie aussi les doublons déjà créés (conserve la ligne `manually_edited=1` en priorité) avant le swap de clé.

**Impact** :
- Migration 005 **ponctuelle** (pas idempotente sur le bloc dedup) — à jouer une seule fois sur preprod.
- Edge case non couvert : si un CSV constructeur **repositionne** réellement une pièce (original change), l'ancienne ligne devient orpheline plutôt que d'être updatée — comportement identique à l'ancienne clé, hors scope ici.

**Statut** : 🟢 corrigé en code, ⏳ migration 005 à appliquer sur preprod + retest #5 de bout en bout.

---

## 🔵 Motos — 3e visuel « Accessoires Powerparts » (à implémenter)

**Fichiers concernés (à terme)** : `classes/MsMoto.php`, `sql/install.sql` (+ migration), `controllers/admin/AdminMsMotosController.php` (form upload + listing previews).

**Contexte** : aujourd'hui la moto a 3 champs image (`picture_main`, `picture_cycle`, `picture_moteur`), mais **`picture_main` = `picture_cycle`** (même vue : la moto entière). Le client veut **3 visuels DISTINCTS** par moto :
1. **Cycle** (= la moto entière, aujourd'hui `picture_main` issu du CSV / à terme PR-Visuels)
2. **Moteur** (upload manuel actuel)
3. **Accessoires Powerparts** (NOUVEAU — visuel des accessoires Powerparts pour la moto)

**À faire** :
- Ajouter un champ `picture_powerparts` (ou repurposer `picture_cycle` qui fait doublon avec `picture_main`) → décider du modèle à l'implémentation.
- Câbler l'upload BO + les previews listing pour ce 3e visuel.
- Clarifier au passage la redondance `picture_main`/`picture_cycle`.

**Statut** : 🔵 à implémenter — pas urgent, consigné le 2026-06-24.

---

## 🔵 Visuels motos — import en masse (à implémenter)

**Fichier concerné (à terme)** : `controllers/admin/AdminMsMotosController.php` (ou page `getContent` dédiée).

**Contexte** : les visuels moto (`cycle`, `moteur`, et bientôt `powerparts`) s'uploadent **un par un** depuis la fiche moto BO. Sur **1 817 motos**, c'est ingérable à la main.

**À faire** : un **import en masse** des visuels (sur le modèle du ZIP microfiches déjà en place) :
- Upload d'un ZIP d'images, nommées par convention (ex. `<serial>_cycle.png`, `<serial>_moteur.png`, `<serial>_powerparts.png`), rangées dans `img/ms_moto/<id_moto>/`.
- Pivot par `serial_constructeur` (ou `modelnumber`) pour rattacher chaque image à sa moto.
- Rapport d'import (matchées / orphelines / ignorées), comme l'upload microfiches.

**Statut** : 🔵 à implémenter — dépend aussi de PR-Visuels (URLs MSS) pour le visuel principal. Consigné le 2026-06-24.

---

## 🔵 Listings BO — lien « voir la page publique » (à implémenter)

**Fichiers concernés (à terme)** : `controllers/admin/AdminMsMotosController.php`, `controllers/admin/AdminMsMicrofichesController.php`.

**Contexte** : depuis les listings BO, pas de moyen rapide d'ouvrir la **vue publique** (front) d'une moto ou d'une microfiche pour vérifier le rendu.

**À faire** : ajouter dans chaque listing une **colonne / action « Voir sur le site »** :
- Motos → lien vers la PLP : `getModuleLink('megaservice_microfiches', 'moto', ['id_moto' => …])`
- Microfiches → lien vers la PDP : `getModuleLink('megaservice_microfiches', 'microfiche', ['id_microfiche' => …])`
- Ouverture dans un nouvel onglet.

Quick win : les front controllers + `getModuleLink` existent déjà, il ne reste qu'à poser le lien dans les fields_list.

**Statut** : 🔵 à implémenter — facile, consigné le 2026-06-24.
