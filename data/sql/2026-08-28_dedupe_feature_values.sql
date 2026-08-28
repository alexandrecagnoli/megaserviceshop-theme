-- ============================================================================
-- Deduplication des valeurs de caracteristiques produit
-- A executer UNE FOIS, en phpMyAdmin, APRES un dump de la base.
--
-- Contexte : constate le 2026-08-28 sur la fiche produit — la caracteristique
-- « Pratique / Cross » sortait jusqu'a 20 fois sur un meme produit. Cause :
-- l'import affecte les caracteristiques par leur libelle et non par leur
-- id_feature_value ; PrestaShop cree alors une nouvelle valeur personnalisee
-- a chaque passage. On se retrouve avec N valeurs distinctes portant le meme
-- texte, toutes affectees au meme produit, et le template sort un <tr> par
-- affectation.
--
-- Ce script ne supprime AUCUNE information : pour chaque couple
-- (caracteristique, libelle) il conserve la plus ancienne valeur (id le plus
-- petit), y repointe les affectations produit, puis supprime les doublons
-- devenus orphelins.
--
-- ATTENTION : ne corrige pas la cause. Sans correction de l'import, les
-- doublons reviendront au prochain passage.
-- ============================================================================


-- ── 0. Etat des lieux (a lancer seul, avant tout) ───────────────────────────
-- Nombre de produits touches et volume d'affectations en trop.
SELECT
    COUNT(*)                                   AS couples_produit_caracteristique_en_doublon,
    SUM(occurrences)                           AS affectations_totales,
    SUM(occurrences) - COUNT(*)                AS affectations_a_supprimer,
    MAX(occurrences)                           AS pire_cas
FROM (
    SELECT fp.id_product, fp.id_feature, COUNT(*) AS occurrences
    FROM ps_feature_product fp
    GROUP BY fp.id_product, fp.id_feature
    HAVING COUNT(*) > 1
) AS d;


-- ── 1. Table de correspondance : la valeur canonique de chaque libelle ──────
-- Table reelle et non TEMPORARY : phpMyAdmin ouvre une connexion par
-- soumission, une table temporaire ne survivrait pas d'une requete a l'autre.
DROP TABLE IF EXISTS ms_fv_canon;

CREATE TABLE ms_fv_canon (
    id_feature       INT UNSIGNED NOT NULL,
    valeur           TEXT NOT NULL,
    id_canon         INT UNSIGNED NOT NULL,
    KEY idx_lookup (id_feature, valeur(255))
) ENGINE=InnoDB;

INSERT INTO ms_fv_canon (id_feature, valeur, id_canon)
SELECT fv.id_feature, fvl.value, MIN(fv.id_feature_value)
FROM ps_feature_value fv
JOIN ps_feature_value_lang fvl
      ON fvl.id_feature_value = fv.id_feature_value
     AND fvl.id_lang = 1
GROUP BY fv.id_feature, fvl.value
HAVING COUNT(*) > 1;


-- ── 2. Repointer les affectations vers la valeur canonique ──────────────────
-- UPDATE IGNORE : la cle primaire de ps_feature_product interdit deux fois la
-- meme ligne. La premiere affectation d'un produit bascule sur la canonique,
-- les suivantes echouent silencieusement et restent en place — l'etape 3 les
-- ramasse. C'est ce qui garantit qu'aucun produit ne perd sa caracteristique.
UPDATE IGNORE ps_feature_product fp
JOIN ps_feature_value fv       ON fv.id_feature_value = fp.id_feature_value
JOIN ps_feature_value_lang fvl ON fvl.id_feature_value = fv.id_feature_value
                              AND fvl.id_lang = 1
JOIN ms_fv_canon c             ON c.id_feature = fv.id_feature
                              AND c.valeur = fvl.value
SET fp.id_feature_value = c.id_canon
WHERE fp.id_feature_value <> c.id_canon;


-- ── 3. Supprimer les affectations restees sur une valeur non canonique ──────
DELETE fp FROM ps_feature_product fp
JOIN ps_feature_value fv       ON fv.id_feature_value = fp.id_feature_value
JOIN ps_feature_value_lang fvl ON fvl.id_feature_value = fv.id_feature_value
                              AND fvl.id_lang = 1
JOIN ms_fv_canon c             ON c.id_feature = fv.id_feature
                              AND c.valeur = fvl.value
WHERE fp.id_feature_value <> c.id_canon;


-- ── 4. Supprimer les valeurs en doublon devenues orphelines ─────────────────
-- Uniquement celles du jeu de doublons, et uniquement si plus rien ne les
-- reference : on ne touche pas aux valeurs predefinies utilisees ailleurs.
DELETE fv FROM ps_feature_value fv
JOIN ps_feature_value_lang fvl ON fvl.id_feature_value = fv.id_feature_value
                              AND fvl.id_lang = 1
JOIN ms_fv_canon c             ON c.id_feature = fv.id_feature
                              AND c.valeur = fvl.value
LEFT JOIN ps_feature_product fp ON fp.id_feature_value = fv.id_feature_value
WHERE fv.id_feature_value <> c.id_canon
  AND fp.id_feature_value IS NULL;

DELETE fvl FROM ps_feature_value_lang fvl
LEFT JOIN ps_feature_value fv ON fv.id_feature_value = fvl.id_feature_value
WHERE fv.id_feature_value IS NULL;


-- ── 5. Verification : doit renvoyer 0 ligne ────────────────────────────────
SELECT fp.id_product, fp.id_feature, COUNT(*) AS occurrences
FROM ps_feature_product fp
GROUP BY fp.id_product, fp.id_feature
HAVING COUNT(*) > 1
LIMIT 20;


-- ── 6. Menage ───────────────────────────────────────────────────────────────
DROP TABLE IF EXISTS ms_fv_canon;
