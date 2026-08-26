-- ============================================================================
-- Migration 005 : rebaser la UNIQUE KEY hotspots sur la position CONSTRUCTEUR
--                 (position_x_original / position_y_original) au lieu de la
--                 position VIVANTE (position_x / position_y).
--
-- ----------------------------------------------------------------------------
-- POURQUOI (bug corrige)
-- ----------------------------------------------------------------------------
-- La migration 001 avait etendu `uk_hotspot_naturel` avec position_x/position_y
-- VIVANTES. La migration 003 a ensuite ajoute le flag `manually_edited` cense
-- proteger les positions deplacees a la main par l'admin (editeur drag/drop BO)
-- contre l'ecrasement au reimport du CSV constructeur, via le
-- IF(manually_edited = 1, ...) du ON DUPLICATE KEY UPDATE de
-- MicrofichesImporter::upsertHotspot.
--
-- Ces deux decisions se CONTREDISENT : le ON DUPLICATE ne se declenche que si
-- la cle UNIQUE entrante percute une ligne existante. Or des qu'un admin drague
-- un hotspot, le save BO modifie position_x/position_y -- donc modifie la cle
-- elle-meme. Au reimport, le CSV porte la position constructeur d'origine :
-- l'INSERT ne percute plus la ligne deplacee -> INSERT frais -> DOUBLON.
-- La branche TRUE du IF(manually_edited=1,...) n'est jamais atteinte : la
-- protection 003 ne protege rien des qu'on a bouge le cercle.
--   (Reproduit en preprod 2026-06-09 : un drag + reimport = 2 hotspots pour la
--    meme piece.)
--
-- ----------------------------------------------------------------------------
-- LE FIX
-- ----------------------------------------------------------------------------
-- position_x_original / position_y_original tracent la derniere position CSV
-- connue et ne bougent PAS lors d'un drag manuel (le save BO ne touche que les
-- positions vivantes). En basant la cle naturelle dessus, le reimport percute
-- toujours la bonne ligne meme apres un deplacement -> ON DUPLICATE se
-- declenche -> IF(manually_edited=1,...) protege enfin la position vivante.
-- L'intention de la decision #5 est preservee : une meme piece a plusieurs
-- endroits du CSV = des *_original distincts = des cles distinctes.
--
-- ----------------------------------------------------------------------------
-- APPLICATION (phpMyAdmin : Plesk > Bases > megaserviceshop > SQL)
-- Remplacer "ps_" par le prefixe reel si different. Executer les 3 blocs dans
-- l'ordre. Migration PONCTUELLE (pas idempotente sur le bloc de dedup).
-- ============================================================================

-- ---------------------------------------------------------------------------
-- Bloc 1 : filet de securite -- backfill des *_original encore NULL.
-- (Normalement deja fait par la migration 003, mais une *_original NULL
--  casserait la dedup ET la nouvelle cle UNIQUE.)
-- ---------------------------------------------------------------------------
UPDATE `ps_ms_microfiche_hotspot`
SET `position_x_original` = `position_x`,
    `position_y_original` = `position_y`
WHERE `position_x_original` IS NULL
   OR `position_y_original` IS NULL;

-- ---------------------------------------------------------------------------
-- Bloc 2 : dedup des doublons deja crees par le bug.
-- Pour chaque groupe partageant la future cle naturelle
-- (id_microfiche, article_ref, sequence_number, position_x_original,
--  position_y_original), on conserve UNE ligne et on supprime les autres.
-- Priorite de conservation : manually_edited = 1 d'abord (le travail manuel de
-- l'admin prime sur le fantome constructeur), puis le plus petit id_hotspot.
-- ---------------------------------------------------------------------------
DELETE `h` FROM `ps_ms_microfiche_hotspot` `h`
JOIN (
    SELECT
        `g`.`id_microfiche`,
        `g`.`article_ref`,
        `g`.`sequence_number`,
        `g`.`position_x_original`,
        `g`.`position_y_original`,
        (
            SELECT `k`.`id_hotspot`
            FROM `ps_ms_microfiche_hotspot` `k`
            WHERE `k`.`id_microfiche`       =   `g`.`id_microfiche`
              AND `k`.`article_ref`         =   `g`.`article_ref`
              AND `k`.`sequence_number`     =   `g`.`sequence_number`
              AND `k`.`position_x_original` <=> `g`.`position_x_original`
              AND `k`.`position_y_original` <=> `g`.`position_y_original`
            ORDER BY `k`.`manually_edited` DESC, `k`.`id_hotspot` ASC
            LIMIT 1
        ) AS `keeper`
    FROM `ps_ms_microfiche_hotspot` `g`
    GROUP BY
        `g`.`id_microfiche`,
        `g`.`article_ref`,
        `g`.`sequence_number`,
        `g`.`position_x_original`,
        `g`.`position_y_original`
    HAVING COUNT(*) > 1
) `d`
  ON  `h`.`id_microfiche`       =   `d`.`id_microfiche`
  AND `h`.`article_ref`         =   `d`.`article_ref`
  AND `h`.`sequence_number`     =   `d`.`sequence_number`
  AND `h`.`position_x_original` <=> `d`.`position_x_original`
  AND `h`.`position_y_original` <=> `d`.`position_y_original`
WHERE `h`.`id_hotspot` <> `d`.`keeper`;

-- ---------------------------------------------------------------------------
-- Bloc 3 : swap de la cle naturelle vers les positions CONSTRUCTEUR.
-- (DROP KEY/ADD KEY n'impacte pas le FK fk_hotspot_microfiche ON DELETE CASCADE.)
-- ---------------------------------------------------------------------------
ALTER TABLE `ps_ms_microfiche_hotspot`
  DROP KEY `uk_hotspot_naturel`,
  ADD UNIQUE KEY `uk_hotspot_naturel`
    (`id_microfiche`, `article_ref`, `sequence_number`, `position_x_original`, `position_y_original`);

-- ---------------------------------------------------------------------------
-- Verification (doit renvoyer 0) : plus aucun groupe en doublon sur la nouvelle
-- cle naturelle.
-- ---------------------------------------------------------------------------
SELECT COUNT(*) AS groupes_en_doublon
FROM (
    SELECT 1
    FROM `ps_ms_microfiche_hotspot`
    GROUP BY `id_microfiche`, `article_ref`, `sequence_number`,
             `position_x_original`, `position_y_original`
    HAVING COUNT(*) > 1
) `t`;
