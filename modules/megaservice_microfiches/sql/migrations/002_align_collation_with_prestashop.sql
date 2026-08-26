-- ============================================================================
-- Migration 002 : aligner la collation de nos 4 tables sur celle de PrestaShop
--
-- Contexte : install.sql initial utilisait `utf8mb4_unicode_ci` (choix "moderne"),
-- mais PrestaShop 8 utilise `utf8mb4_general_ci` pour toutes ses tables natives
-- (notamment ps_product.reference). MySQL refuse de comparer 2 chaînes avec
-- collations différentes :
--   #1267 - Illegal mix of collations (utf8mb4_general_ci,IMPLICIT)
--           and (utf8mb4_unicode_ci,IMPLICIT) for operation '='
--
-- Impact : tout JOIN entre nos tables ms_* et les tables ps_* natives plante.
-- Notamment le futur cron de rematching (PR8) qui fera :
--   SELECT id_product FROM ps_product WHERE reference = h.article_ref
--
-- Application :
--   1. phpMyAdmin (Plesk > Bases > megaserviceshop > SQL)
--   2. Coller le bloc ci-dessous (adapter le préfixe ps_ si différent)
--   3. Exécuter (les 4 ALTER TABLE prennent quelques secondes sur 45+25+1463+1830 rows)
-- ============================================================================

ALTER TABLE `ps_ms_moto`
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

ALTER TABLE `ps_ms_microfiche_categorie`
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

ALTER TABLE `ps_ms_microfiche`
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

ALTER TABLE `ps_ms_microfiche_hotspot`
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
