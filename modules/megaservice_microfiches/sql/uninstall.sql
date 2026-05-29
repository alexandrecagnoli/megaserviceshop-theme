-- ============================================================================
-- ATTENTION : ce fichier N'EST PAS appelé automatiquement par uninstall().
-- Pour réellement droper les tables (perte de données), appeler manuellement
-- Megaservice_microfiches::dropTables().
-- Ordre des DROP : inverse des FK pour respecter les contraintes.
-- ============================================================================

DROP TABLE IF EXISTS `PREFIX_ms_microfiche_hotspot`;
DROP TABLE IF EXISTS `PREFIX_ms_microfiche`;
DROP TABLE IF EXISTS `PREFIX_ms_microfiche_categorie`;
DROP TABLE IF EXISTS `PREFIX_ms_moto`;
