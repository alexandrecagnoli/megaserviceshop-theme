-- ============================================================================
-- Migration 006 : table fille serial -> moto (ps_ms_moto_serial)
--
-- Contexte : l'import motos deduplique par `modelnumber` et ne garde qu'un
-- serial par modele (500 serials ecartes sur KTM : 1 471 serials pour 971
-- modeles). Or le fichier de montabilite designe les motos par serial EXACT.
-- Sur 64 modeles, le serial conserve porte moins de references que ses freres
-- (3 168 references perdues au total ; ex. $M-350EXCFSD : 14 contre 650).
--
-- Cette migration cree la table et la remplit avec le serial deja present dans
-- ms_moto : le comportement est donc IDENTIQUE a l'existant tant que les
-- serials freres n'ont pas ete rattaches. Ces derniers viennent d'un reimport
-- du CSV motos (scripts/cli/import_motos.php), qui alimente desormais la table.
--
-- ORDRE : appliquer cette migration AVANT de deployer le code qui joint sur
-- ps_ms_moto_serial, sinon le front de montabilite renvoie une erreur SQL.
--
-- Application : phpMyAdmin (Plesk > Bases > megaserviceshop > SQL), prefixe ps_.
-- Idempotente (CREATE IF NOT EXISTS + INSERT IGNORE).
-- ============================================================================

CREATE TABLE IF NOT EXISTS `ps_ms_moto_serial` (
  `serial`   VARCHAR(32) NOT NULL,
  `id_moto`  INT UNSIGNED NOT NULL,
  `marque`   ENUM('KTM','HQV','GASGAS') NOT NULL,
  `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`serial`),
  KEY `idx_id_moto` (`id_moto`),
  CONSTRAINT `fk_moto_serial_moto` FOREIGN KEY (`id_moto`) REFERENCES `ps_ms_moto`(`id_moto`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `ps_ms_moto_serial` (`serial`, `id_moto`, `marque`, `date_add`)
SELECT `serial_constructeur`, `id_moto`, `marque`, NOW()
FROM `ps_ms_moto`
WHERE `serial_constructeur` IS NOT NULL AND `serial_constructeur` <> '';
