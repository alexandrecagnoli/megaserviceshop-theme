-- ============================================================================
-- Réparation de l'arbre des catégories — APPLIQUÉE sur la préprod le 23/09/2026.
-- Conservé comme trace et comme mode d'emploi si le cas se reproduit.
--
-- SYMPTÔME : sur les catégories Powerparts, la facette « Catégories » ne réagissait
-- pas au clic. Les autres facettes (Marque, Disponibilité…) fonctionnaient.
--
-- CAUSE : l'arbre imbriqué de ps_category était corrompu sur SES DEUX dimensions.
--   1. nleft / nright   : 73 catégories sur 222 hors de l'intervalle de leur parent
--   2. level_depth      : 157 sur 222 incohérentes — presque tout à plat sur depth=3
--
-- ENCHAÎNEMENT : ps_facetedsearch borne la liste des catégories proposées avec
-- `level_depth <= PS_LAYERED_FILTER_CATEGORY_DEPTH + level_depth(parent)`
-- (cf. Block::addCategoriesBlockFilters). PS_LAYERED_FILTER_CATEGORY_DEPTH vaut 1,
-- donc seuls les enfants DIRECTS devraient être listés. level_depth étant faux,
-- la borne ne filtrait plus rien : la facette proposait 63 catégories prises dans
-- tout l'arbre (Fourche, Carburateur, Produits WP…). Or le filtre ne sait appliquer
-- qu'un enfant direct de la catégorie courante — tout le reste était écarté
-- SILENCIEUSEMENT côté serveur. Réponse HTTP 200, JSON valide, listing inchangé :
-- d'où l'impression que le clic ne faisait rien.
--
-- Ce n'était donc ni le JS du thème, ni le template, ni le module.
--
-- ATTENTION — effet visible : le comptage des catégories change, parce qu'il était
-- calculé sur un sous-arbre tronqué. Accessoires Powerparts passe de 6 076 à 36 791
-- produits. Le nouveau chiffre découle des associations produit-catégorie réelles ;
-- s'il paraît trop large, c'est LE RATTACHEMENT DES PRODUITS qu'il faut revoir,
-- pas l'arbre.
--
-- SAUVEGARDE : ps_category_bak_20260923_ntree (222 lignes, état avant réparation).
-- ============================================================================

-- ── 1. Diagnostic — à lancer AVANT toute réparation ─────────────────────────
SELECT
  (SELECT COUNT(*) FROM ps_category)                                        AS categories,
  (SELECT COUNT(*) FROM ps_category c JOIN ps_category p ON p.id_category=c.id_parent
    WHERE c.nleft < p.nleft OR c.nright > p.nright)                         AS nleft_nright_ko,
  (SELECT COUNT(*) FROM ps_category c JOIN ps_category p ON p.id_category=c.id_parent
    WHERE c.level_depth <> p.level_depth + 1
      AND c.id_category <> c.id_parent)                                     AS level_depth_ko;

-- ── 2. Sauvegarde ───────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `ps_category_bak_20260923_ntree`;
CREATE TABLE `ps_category_bak_20260923_ntree` LIKE `ps_category`;
INSERT INTO `ps_category_bak_20260923_ntree` SELECT * FROM `ps_category`;

-- ── 3. nleft / nright ───────────────────────────────────────────────────────
-- PAS de SQL : passer par l'API PrestaShop, qui reconstruit depuis les id_parent.
--   php scripts/cli/<script>.php  avec  Category::regenerateEntireNtree();

-- ── 4. level_depth ──────────────────────────────────────────────────────────
-- regenerateEntireNtree() ne recalcule QUE nleft/nright. level_depth doit être
-- aligné séparément. Requête idempotente, à rejouer jusqu'à 0 ligne affectée
-- (2 passes ont suffi le 23/09 : 157 -> 79 -> 0).
UPDATE `ps_category` c
  JOIN `ps_category` p ON p.`id_category` = c.`id_parent`
   SET c.`level_depth` = p.`level_depth` + 1
 WHERE c.`level_depth` <> p.`level_depth` + 1
   AND c.`id_category` <> c.`id_parent`;

-- ── 5. Vider le cache de blocs des facettes ─────────────────────────────────
-- Indispensable : sans ça la facette reste servie depuis le cache et ne reflète
-- pas la réparation. Se reconstruit tout seul à la première visite.
TRUNCATE `ps_layered_filter_block`;

-- Puis vider le cache PrestaShop :
--   find var/cache -mindepth 1 -delete

-- ── 6. Contrôle APRÈS — les deux compteurs doivent valoir 0 ─────────────────
SELECT
  (SELECT COUNT(*) FROM ps_category c JOIN ps_category p ON p.id_category=c.id_parent
    WHERE c.nleft < p.nleft OR c.nright > p.nright)                         AS nleft_nright_ko,
  (SELECT COUNT(*) FROM ps_category c JOIN ps_category p ON p.id_category=c.id_parent
    WHERE c.level_depth <> p.level_depth + 1
      AND c.id_category <> c.id_parent)                                     AS level_depth_ko;

-- ============================================================================
-- SUITE — 23/09/2026 : la facette PRIX ne s'affichait pas non plus.
--
-- SYMPTÔME : `price` était configurée comme facette sur les 3 catégories
-- Powerparts (41, 332, 369) mais n'apparaissait sur AUCUNE page.
--
-- CAUSE : l'index de prix du module était vide à 99,99 %.
--     produits actifs                  47 916
--     produits dans l'index                 1
--     lignes ps_layered_price_index       409  (pour 228 produits)
-- Sans cet index, le module ne peut calculer aucune borne, donc il n'affiche
-- pas la facette du tout. Le drapeau PS_LAYERED_INDEXED valait pourtant 1 :
-- rien ne signalait que l'indexation n'avait jamais abouti. Bug silencieux.
--
-- CORRECTIF : reconstruction via ps_facetedsearch::fullPricesIndexProcess(),
-- bouclée sur son curseur. ~12 minutes pour 47 916 produits.
--     APRÈS : 95 804 lignes, 47 902 produits indexés
-- Les 14 produits restants portent visibility='search' (invisibles en
-- catalogue) : exclus à juste titre par l'indexeur, ce n'est pas un échec.
--
-- Vérifié après coup : la facette Prix s'affiche (slider 0 à 14 581 €) et
-- filtre — 19 918 produits entre 0 et 50 €, 8 927 entre 100 et 500 €.
--
-- ATTENTION : la boucle sur le curseur ne se termine pas d'elle-même sur les
-- derniers lots. Prévoir un garde-fou sur le nombre de passes, ou s'arrêter
-- quand le compteur de produits indexés cesse de progresser.
--
-- RESTE À COMPRENDRE : pourquoi l'indexation n'a jamais tourné. Sans réponse,
-- l'index se redégradera au prochain import catalogue. Le module expose une
-- URL de cron (action=indexPrices&full=1) — vérifier qu'elle est planifiée.
-- ============================================================================

-- Contrôle de l'index de prix — doit être proche du nombre de produits actifs
SELECT
  (SELECT COUNT(*) FROM ps_product_shop WHERE id_shop=1 AND active=1)        AS produits_actifs,
  (SELECT COUNT(DISTINCT id_product) FROM ps_layered_price_index)            AS produits_indexes,
  (SELECT COUNT(*) FROM ps_layered_price_index)                              AS lignes_index;

-- Après toute réindexation, vider le cache de blocs des facettes :
-- TRUNCATE `ps_layered_filter_block`;
