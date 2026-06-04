-- ═══════════════════════════════════════════════════════════════════
-- Migration : Distinction Carnet de Soins / Carnet de Santé
-- Date     : 2026-05-20
-- Idempotent : peut être rejouée sans erreur
-- ═══════════════════════════════════════════════════════════════════

-- ── 0. Créer la table mouvements_carnets si elle n'existe pas encore ─
CREATE TABLE IF NOT EXISTS `mouvements_carnets` (
  `id`           INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `type_mvt`     ENUM('initialisation','sortie','correction') NOT NULL DEFAULT 'sortie',
  `type_carnet`  ENUM('soins','sante') NOT NULL DEFAULT 'soins',
  `quantite`     INT(11) NOT NULL DEFAULT 0 COMMENT 'Négatif pour sorties',
  `stock_avant`  INT(11) NOT NULL DEFAULT 0,
  `stock_apres`  INT(11) NOT NULL DEFAULT 0,
  `recu_id`      INT(10) UNSIGNED DEFAULT NULL COMMENT 'Reçu consultation lié',
  `commentaire`  TEXT DEFAULT NULL,
  `whendone`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `whodone`      INT(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_mvt_carnet_date` (`whendone`),
  KEY `idx_mvt_carnet_recu` (`recu_id`),
  KEY `idx_mvt_carnet_type` (`type_carnet`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 1. Ajouter colonne type_carnet si absente (table créée par migration 002 sans cette colonne) ─
DROP PROCEDURE IF EXISTS `_patch_add_type_carnet`;
DELIMITER //
CREATE PROCEDURE `_patch_add_type_carnet`()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'mouvements_carnets'
      AND COLUMN_NAME  = 'type_carnet'
  ) THEN
    ALTER TABLE `mouvements_carnets`
      ADD COLUMN `type_carnet` ENUM('soins','sante') NOT NULL DEFAULT 'soins'
      AFTER `type_mvt`;
  END IF;
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'mouvements_carnets'
      AND INDEX_NAME   = 'idx_mvt_carnet_type'
  ) THEN
    ALTER TABLE `mouvements_carnets`
      ADD KEY `idx_mvt_carnet_type` (`type_carnet`);
  END IF;
END //
DELIMITER ;
CALL `_patch_add_type_carnet`();
DROP PROCEDURE IF EXISTS `_patch_add_type_carnet`;

-- ── 2. Migration des données existantes ────────────────────────────
-- Tous les mouvements liés à un reçu acte_gratuit deviennent 'sante'
UPDATE `mouvements_carnets` mc
INNER JOIN `recus` r ON r.id = mc.recu_id
SET mc.type_carnet = 'sante'
WHERE r.type_patient = 'acte_gratuit';

-- Les autres restent 'soins' (par défaut)

-- ── 3. Initialisation des nouvelles clés config_systeme ────────────
-- Récupérer l'ancien stock global pour le ventiler
SET @ancien_stock := (SELECT CAST(valeur AS UNSIGNED) FROM config_systeme WHERE cle='stock_carnets' LIMIT 1);
SET @ancien_seuil := (SELECT CAST(valeur AS UNSIGNED) FROM config_systeme WHERE cle='seuil_alerte_carnets' LIMIT 1);

-- Insérer les nouvelles clés (l'ancien stock global est attribué aux carnets de soins par défaut)
INSERT INTO `config_systeme` (`cle`, `valeur`, `whodone`) VALUES
  ('stock_carnets_soins',         IFNULL(@ancien_stock, 0),  1),
  ('seuil_alerte_carnets_soins',  IFNULL(@ancien_seuil, 10), 1),
  ('stock_carnets_sante',         0,                          1),
  ('seuil_alerte_carnets_sante',  IFNULL(@ancien_seuil, 10), 1)
ON DUPLICATE KEY UPDATE valeur = valeur;

-- ── 4. (Optionnel) Marquer l'ancienne clé comme dépréciée ──────────
-- On la conserve pour compatibilité descendante mais on n'écrira plus dessus
-- UPDATE config_systeme SET cle = 'stock_carnets_DEPRECATED' WHERE cle = 'stock_carnets';
