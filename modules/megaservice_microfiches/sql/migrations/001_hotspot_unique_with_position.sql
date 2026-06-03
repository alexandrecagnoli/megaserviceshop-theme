-- ============================================================================
-- Migration 001 : élargir UNIQUE KEY hotspots avec position_x + position_y
--
-- Contexte : la spec initiale du brief §4.3 disait que la clé naturelle d'un
-- hotspot était (id_microfiche, article_ref, sequence_number). Mesure réelle
-- sur le 1er CSV testé (F0403X7.csv, GASGAS EC 300 2024) : 14.5% des hotspots
-- partagent cette clé mais avec des positions visuelles différentes (ex. une
-- vis numérotée "10" qui apparaît 9 fois sur le schéma à 9 endroits cliquables).
--
-- Sans ce fix : 213 hotspots sur 1466 sont silencieusement écrasés au INSERT
-- ON DUPLICATE KEY UPDATE → le client ne peut cliquer qu'à 1 endroit pour la
-- même référence sur une même microfiche. UX dégradée.
--
-- Application :
--   1. Ouvrir phpMyAdmin (Plesk > Bases de données > megaserviceshop > SQL)
--   2. Copier-coller le bloc ALTER ci-dessous APRÈS avoir remplacé "ps_" par
--      le préfixe réel de la base si différent (vérifier dans config/parameters.php).
--   3. Exécuter.
--   4. Retourner dans le BO du module et réimporter le(s) CSV microfiches déjà
--      importé(s) — les 213 hotspots manquants seront ajoutés (idempotent :
--      les 1250 existants conservent leur id_hotspot).
--
-- Note : ON DELETE CASCADE sur fk_hotspot_microfiche reste intact (n'est pas
-- impacté par un DROP KEY/ADD KEY sur la UNIQUE).
-- ============================================================================

ALTER TABLE `ps_ms_microfiche_hotspot`
  DROP KEY `uk_hotspot_naturel`,
  ADD UNIQUE KEY `uk_hotspot_naturel`
    (`id_microfiche`, `article_ref`, `sequence_number`, `position_x`, `position_y`);
