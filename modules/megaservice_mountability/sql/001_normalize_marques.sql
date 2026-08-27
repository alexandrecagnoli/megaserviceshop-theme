-- ============================================================================
-- Nettoyage de `ms_mountability.marque` — à exécuter UNE FOIS en phpMyAdmin.
--
-- Contexte : l'importer acceptait n'importe quelle chaîne comme marque. Deux
-- conséquences constatées en prod le 2026-08-27 :
--
--   1. Les fichiers Husqvarna se chargeaient sous « HUSQVARNA » alors que le
--      référentiel ms_moto dit « HQV ». Le rapport d'import affichait donc
--      « 0 motos allumées (0%) » sur un import parfaitement réussi, et la purge
--      de rechargement (DELETE WHERE marque = <formulaire>) ne percutait rien.
--
--   2. Des fichiers au mauvais format (3e colonne = nom de catégorie) ont
--      chargé ~24 900 lignes sous des pseudo-marques : « PIÈCES DÉTACHÉES »,
--      « ACCESSOIRES POWE », « ÉQUIPEMENT PILOT », « SUSPENSIONS WP »,
--      « LIFESTYLE », « MAIN CATEGORY », « MARQUE ». Les deux premières sont
--      tronquées à 16 caractères par `marque VARCHAR(16)` (MySQL non strict).
--      Signature du désalignement : lignes == serials distincts, donc chaque
--      `id_moto_constructeur` est unique — ce sont des références produit.
--
-- Le front n'a jamais été affecté : il joint sur `serial_constructeur` et
-- n'utilise pas `marque`. Ce nettoyage sert le rapport d'import et la purge.
--
-- L'importer refuse désormais toute marque hors référentiel (cf.
-- MountabilityImporter::normalizeMarque), donc ce cas ne peut plus se reproduire.
--
-- Remplacer `ps_` par le préfixe réel si différent.
-- ============================================================================

-- ── 1. Contrôle AVANT : ce qui va être supprimé / modifié ───────────────────
SELECT `marque`, COUNT(*) AS lignes, COUNT(DISTINCT `id_moto_constructeur`) AS serials
FROM `ps_ms_mountability`
GROUP BY `marque`
ORDER BY lignes DESC;

-- ── 2. Purge des lignes issues de fichiers au mauvais format ────────────────
-- Ne touche QUE ce qui n'est pas une marque du référentiel.
DELETE FROM `ps_ms_mountability`
WHERE `marque` NOT IN ('KTM', 'HQV', 'GASGAS', 'HUSQVARNA');

-- ── 3. Alignement Husqvarna sur le référentiel ms_moto ──────────────────────
UPDATE `ps_ms_mountability` SET `marque` = 'HQV' WHERE `marque` = 'HUSQVARNA';

-- ── 4. Contrôle APRÈS : seules les 3 marques doivent subsister ──────────────
SELECT `marque`, COUNT(*) AS lignes, COUNT(DISTINCT `id_moto_constructeur`) AS serials
FROM `ps_ms_mountability`
GROUP BY `marque`
ORDER BY lignes DESC;

-- ── 5. Contrôle de non-régression : le nombre de motos allumées ─────────────
-- Doit rester à sa valeur d'avant nettoyage (1771 au 2026-08-27) : les lignes
-- supprimées ne résolvaient aucune moto. Toute baisse signale une purge trop
-- large — restaurer et rouvrir le sujet.
SELECT COUNT(DISTINCT mo.`id_moto`) AS motos_allumees
FROM `ps_ms_mountability` c
JOIN `ps_ms_moto` mo ON mo.`serial_constructeur` = c.`id_moto_constructeur`;
