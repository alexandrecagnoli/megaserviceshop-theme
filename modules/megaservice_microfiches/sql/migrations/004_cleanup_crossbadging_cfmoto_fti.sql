-- ============================================================================
-- Migration 004 : suppression des motos cross-badging CFMOTO / FTI
--
-- Decision client MSS du 2026-06-05 : ces 15 motos (suffixes _CFMOTO et
-- _FTI sur serial_constructeur) sont des cross-badges du partenariat
-- CFMOTO pour des marches hors europe. Elles ne concernent PAS le marche
-- MSS et doivent etre supprimees. L'equivalent europeen existe deja en
-- base sous serial = 'F' + base_sans_suffixe (ex: 7487V2_CFMOTO ->
-- F7487V2 deja present).
--
-- Code MotosImporter::buildRow() filtre maintenant ces serials a l'import
-- (constante BLACKLISTED_SERIAL_SUFFIXES) pour empecher la recreation au
-- prochain reimport CSV motos.
--
-- Cleanup en BDD :
-- ============================================================================

-- Verification avant suppression : combien de motos concernees ?
-- (devrait renvoyer 15 d'apres notre mesure du 2026-06-04)
SELECT marque, COUNT(*) AS nb
FROM `ps_ms_moto`
WHERE serial_constructeur LIKE '%\_CFMOTO' ESCAPE '\\'
   OR serial_constructeur LIKE '%\_FTI'    ESCAPE '\\'
GROUP BY marque;

-- Verification : combien de microfiches seraient supprimees en cascade ?
-- (FK ON DELETE CASCADE sur ms_microfiche.id_moto + ms_microfiche_hotspot)
SELECT COUNT(*) AS nb_microfiches_perdues
FROM `ps_ms_microfiche` mf
JOIN `ps_ms_moto` m ON m.id_moto = mf.id_moto
WHERE m.serial_constructeur LIKE '%\_CFMOTO' ESCAPE '\\'
   OR m.serial_constructeur LIKE '%\_FTI'    ESCAPE '\\';

-- Suppression effective. Les hotspots et microfiches associes sont
-- supprimes automatiquement par ON DELETE CASCADE.
DELETE FROM `ps_ms_moto`
WHERE serial_constructeur LIKE '%\_CFMOTO' ESCAPE '\\'
   OR serial_constructeur LIKE '%\_FTI'    ESCAPE '\\';

-- Verification post-suppression : devrait renvoyer 0
SELECT COUNT(*) AS reste
FROM `ps_ms_moto`
WHERE serial_constructeur LIKE '%\_CFMOTO' ESCAPE '\\'
   OR serial_constructeur LIKE '%\_FTI'    ESCAPE '\\';
