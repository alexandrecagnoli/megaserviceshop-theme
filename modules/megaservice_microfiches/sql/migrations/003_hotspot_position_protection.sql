-- ============================================================================
-- Migration 003 : protection des positions hotspots contre l ecrasement
--                 par un reimport CSV constructeur
--
-- Contexte : on prepare l editeur drag/drop visuel des positions hotspots
-- (depuis l ecran d inspection visuelle d une microfiche). L admin va
-- pouvoir deplacer les cercles a la souris ; pour que ces modifications
-- ne soient pas balayees au prochain import du CSV constructeur, on ajoute :
--
--   - position_x_original / position_y_original : copie figee des positions
--     du dernier CSV constructeur connu. Sert au revert d une edition.
--   - manually_edited : flag boolean (1 = position modifiee a la main par
--     l admin -> l importer respecte position_x/y et qty_recommended).
--
-- L importer MicrofichesImporter::upsertHotspot a ete adapte pour utiliser
-- IF(manually_edited = 1, ...) sur les SET du ON DUPLICATE KEY UPDATE.
--
-- Application :
--   1. Ouvrir phpMyAdmin (Plesk > Bases > megaserviceshop > SQL)
--   2. Executer le bloc ci-dessous (adapter le prefixe ps_ si different)
--   3. Le UPDATE final initialise position_x_original / position_y_original
--      avec les valeurs courantes pour les hotspots deja en base
-- ============================================================================

ALTER TABLE `ps_ms_microfiche_hotspot`
  ADD COLUMN `position_x_original` SMALLINT UNSIGNED NULL AFTER `position_y`,
  ADD COLUMN `position_y_original` SMALLINT UNSIGNED NULL AFTER `position_x_original`,
  ADD COLUMN `manually_edited`     TINYINT(1) NOT NULL DEFAULT 0 AFTER `qty_recommended`,
  ADD INDEX `idx_manually_edited` (`manually_edited`);

-- Initialisation des valeurs originales pour les hotspots deja en BDD :
-- on les considere comme "etat constructeur" courant.
UPDATE `ps_ms_microfiche_hotspot`
SET `position_x_original` = `position_x`,
    `position_y_original` = `position_y`
WHERE `position_x_original` IS NULL OR `position_y_original` IS NULL;
