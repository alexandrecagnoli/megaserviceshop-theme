-- ============================================================================
-- Schéma initial du module megaservice_microfiches.
-- 4 tables : ms_moto > ms_microfiche > ms_microfiche_hotspot
--           ms_microfiche_categorie (référentiel filtré côté front)
-- PREFIX_ est remplacé par _DB_PREFIX_ au moment de l'install (cf. installSql()).
-- ============================================================================

CREATE TABLE IF NOT EXISTS `PREFIX_ms_moto` (
  `id_moto`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `modelnumber`         VARCHAR(64) NOT NULL,
  `serial_constructeur` VARCHAR(32) NULL,
  `marque`              ENUM('KTM','HQV','GASGAS') NOT NULL,
  `annee`               SMALLINT UNSIGNED NOT NULL,
  `category_fr`         VARCHAR(255) NOT NULL,
  `core_name`           VARCHAR(255) NOT NULL,
  `type`                ENUM('Motocross','Enduro','Naked','Adventure','Supermoto','Electrique','Trial','Autres') NOT NULL DEFAULT 'Autres',
  `cylindree`           SMALLINT UNSIGNED NULL,
  `is_electric`         TINYINT(1) NOT NULL DEFAULT 0,
  `nom_fr`              VARCHAR(255) NOT NULL,
  `description_fr`      TEXT NULL,
  `picture_main`        VARCHAR(255) NULL,
  `picture_cycle`       VARCHAR(255) NULL,
  `picture_moteur`      VARCHAR(255) NULL,
  `active`              TINYINT(1) NOT NULL DEFAULT 1,
  `date_add`            DATETIME NOT NULL,
  `date_upd`            DATETIME NOT NULL,
  PRIMARY KEY (`id_moto`),
  UNIQUE KEY `uk_modelnumber` (`modelnumber`),
  KEY `idx_serial` (`serial_constructeur`),
  KEY `idx_marque_annee` (`marque`, `annee`),
  KEY `idx_type` (`type`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_ms_microfiche_categorie` (
  `id_categorie`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `partie`              ENUM('cycle','moteur') NOT NULL,
  `numero_constructeur` SMALLINT UNSIGNED NOT NULL,
  `code`                VARCHAR(64) NOT NULL,
  `nom_fr`              VARCHAR(128) NULL,
  `ordre_affichage`     SMALLINT NOT NULL DEFAULT 0,
  `active`              TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_categorie`),
  UNIQUE KEY `uk_partie_numero` (`partie`, `numero_constructeur`),
  KEY `idx_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_ms_microfiche` (
  `id_microfiche`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_moto`          INT UNSIGNED NOT NULL,
  `id_categorie`     INT UNSIGNED NOT NULL,
  `nom_constructeur` VARCHAR(255) NOT NULL,
  `nom_fr`           VARCHAR(255) NULL,
  `image_full_url`   VARCHAR(512) NOT NULL,
  `image_thumb_url`  VARCHAR(512) NULL,
  `image_local`      VARCHAR(255) NULL,
  `image_width`      SMALLINT UNSIGNED NULL,
  `image_height`     SMALLINT UNSIGNED NULL,
  `active`           TINYINT(1) NOT NULL DEFAULT 1,
  `date_add`         DATETIME NOT NULL,
  `date_upd`         DATETIME NOT NULL,
  PRIMARY KEY (`id_microfiche`),
  UNIQUE KEY `uk_microfiche_naturelle` (`id_moto`, `id_categorie`, `nom_constructeur`),
  KEY `idx_id_moto` (`id_moto`),
  KEY `idx_id_categorie` (`id_categorie`),
  KEY `idx_active` (`active`),
  CONSTRAINT `fk_microfiche_moto`      FOREIGN KEY (`id_moto`)      REFERENCES `PREFIX_ms_moto`(`id_moto`)                          ON DELETE CASCADE,
  CONSTRAINT `fk_microfiche_categorie` FOREIGN KEY (`id_categorie`) REFERENCES `PREFIX_ms_microfiche_categorie`(`id_categorie`)     ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Note : la UNIQUE KEY inclut position_x ET position_y car une meme piece
-- (ex. vis M6 #10) peut apparaitre plusieurs fois sur la vue eclatee a des
-- endroits visuels differents -- chaque occurrence est un vrai hotspot.
-- La spec initiale du brief 4.3 sous-specifiait la cle naturelle, on a
-- mesure une perte de 14,5% des hotspots sur le 1er CSV teste (cf. TECH_DEBT).
CREATE TABLE IF NOT EXISTS `PREFIX_ms_microfiche_hotspot` (
  `id_hotspot`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_microfiche`   INT UNSIGNED NOT NULL,
  `id_product`      INT UNSIGNED NULL,
  `article_ref`     VARCHAR(64) NOT NULL,
  `article_label`   VARCHAR(255) NULL,
  `sequence_number` SMALLINT UNSIGNED NOT NULL,
  `position_x`      SMALLINT UNSIGNED NOT NULL,
  `position_y`      SMALLINT UNSIGNED NOT NULL,
  `qty_recommended` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_hotspot`),
  UNIQUE KEY `uk_hotspot_naturel` (`id_microfiche`, `article_ref`, `sequence_number`, `position_x`, `position_y`),
  KEY `idx_id_microfiche` (`id_microfiche`),
  KEY `idx_id_product` (`id_product`),
  KEY `idx_article_ref` (`article_ref`),
  CONSTRAINT `fk_hotspot_microfiche` FOREIGN KEY (`id_microfiche`) REFERENCES `PREFIX_ms_microfiche`(`id_microfiche`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
