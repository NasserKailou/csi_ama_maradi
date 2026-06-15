-- ============================================================
-- Migration 002 – Améliorations CSI AMA Maradi
-- Date : 2026-05-16
-- Auteur : AK
-- ============================================================
-- Exécuter après db_final_directaid.sql
-- Compatible MariaDB 10.4+ / MySQL 8.0+
-- ============================================================

-- 1. ── Stock Carnets ─────────────────────────────────────────────────────────
-- Utilise config_systeme pour stocker :
--   stock_carnets            (int)  nombre actuel de carnets
--   seuil_alerte_carnets     (int)  seuil d'alerte
-- Ces clés sont insérées via INSERT IGNORE pour ne pas écraser si déjà présentes.
INSERT IGNORE INTO `config_systeme` (`cle`, `valeur`, `whendone`, `whodone`) VALUES
  ('stock_carnets',        '0',  NOW(), 1),
  ('seuil_alerte_carnets', '10', NOW(), 1);

-- 2. ── Mouvements stock pharmacie (historique entrées/sorties) ───────────────
CREATE TABLE IF NOT EXISTS `mouvements_stock_pharmacie` (
  `id`           INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `produit_id`   INT(10) UNSIGNED NOT NULL,
  `type_mvt`     ENUM('entree','sortie','correction') NOT NULL DEFAULT 'entree'
                 COMMENT 'entree=appro, sortie=vente recu, correction=ajustement inventaire',
  `quantite`     INT(11) NOT NULL COMMENT 'Positif ou négatif selon type',
  `stock_avant`  INT(11) NOT NULL DEFAULT 0,
  `stock_apres`  INT(11) NOT NULL DEFAULT 0,
  `recu_id`      INT(10) UNSIGNED DEFAULT NULL COMMENT 'Reçu pharmacie lié (si sortie)',
  `commentaire`  TEXT DEFAULT NULL,
  `whendone`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `whodone`      INT(10) UNSIGNED NOT NULL DEFAULT 0,
  `isDeleted`    TINYINT(1) NOT NULL DEFAULT 0,
  `lastUpdate`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mvt_produit`  (`produit_id`),
  KEY `idx_mvt_date`     (`whendone`),
  KEY `idx_mvt_recu`     (`recu_id`),
  KEY `idx_mvt_type`     (`type_mvt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajout de la clé étrangère uniquement si elle n'existe pas
-- (utilisation de procédure pour compatibilité MariaDB)
DROP PROCEDURE IF EXISTS `add_fk_mvt_produit`;
DELIMITER //
CREATE PROCEDURE `add_fk_mvt_produit`()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_NAME='fk_mvt_produit'
      AND TABLE_NAME='mouvements_stock_pharmacie'
      AND TABLE_SCHEMA=DATABASE()
  ) THEN
    ALTER TABLE `mouvements_stock_pharmacie`
      ADD CONSTRAINT `fk_mvt_produit`
      FOREIGN KEY (`produit_id`) REFERENCES `produits_pharmacie`(`id`);
  END IF;
END //
DELIMITER ;
CALL `add_fk_mvt_produit`();
DROP PROCEDURE IF EXISTS `add_fk_mvt_produit`;

-- 3. ── Annulations reçus ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `annulations_recus` (
  `id`           INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `recu_id`      INT(10) UNSIGNED NOT NULL,
  `type_recu`    ENUM('consultation','examen','pharmacie') NOT NULL,
  `motif`        VARCHAR(500) NOT NULL DEFAULT '',
  `montant_annule` INT(10) UNSIGNED NOT NULL DEFAULT 0,
  `whendone`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `whodone`      INT(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_annul_recu`  (`recu_id`),
  KEY `idx_annul_date`  (`whendone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FK annulations -> recus
DROP PROCEDURE IF EXISTS `add_fk_annul_recu`;
DELIMITER //
CREATE PROCEDURE `add_fk_annul_recu`()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_NAME='fk_annul_recu'
      AND TABLE_NAME='annulations_recus'
      AND TABLE_SCHEMA=DATABASE()
  ) THEN
    ALTER TABLE `annulations_recus`
      ADD CONSTRAINT `fk_annul_recu`
      FOREIGN KEY (`recu_id`) REFERENCES `recus`(`id`);
  END IF;
END //
DELIMITER ;
CALL `add_fk_annul_recu`();
DROP PROCEDURE IF EXISTS `add_fk_annul_recu`;

-- 4. ── Mouvements carnets ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `mouvements_carnets` (
  `id`           INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `type_mvt`     ENUM('initialisation','sortie','correction') NOT NULL DEFAULT 'sortie',
  `quantite`     INT(11) NOT NULL COMMENT 'Négatif pour sorties',
  `stock_avant`  INT(11) NOT NULL DEFAULT 0,
  `stock_apres`  INT(11) NOT NULL DEFAULT 0,
  `recu_id`      INT(10) UNSIGNED DEFAULT NULL COMMENT 'Reçu consultation lié',
  `commentaire`  TEXT DEFAULT NULL,
  `whendone`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `whodone`      INT(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_mvt_carnet_date` (`whendone`),
  KEY `idx_mvt_carnet_recu` (`recu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. ── Stock Fiches Actes Gratuits ──────────────────────────────────────────
-- Utilise config_systeme pour stocker :
--   stock_fiches_ag          (int)  nombre actuel de fiches actes gratuits
--   seuil_alerte_fiches_ag   (int)  seuil d'alerte
INSERT IGNORE INTO `config_systeme` (`cle`, `valeur`, `whendone`, `whodone`) VALUES
  ('stock_fiches_ag',        '0',  NOW(), 1),
  ('seuil_alerte_fiches_ag', '10', NOW(), 1);

-- 6. ── Mouvements fiches actes gratuits ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `mouvements_fiches_ag` (
  `id`           INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `type_mvt`     ENUM('initialisation','sortie','correction') NOT NULL DEFAULT 'sortie',
  `quantite`     INT(11) NOT NULL COMMENT 'Positif pour ajouts, négatif pour sorties',
  `stock_avant`  INT(11) NOT NULL DEFAULT 0,
  `stock_apres`  INT(11) NOT NULL DEFAULT 0,
  `recu_id`      INT(10) UNSIGNED DEFAULT NULL COMMENT 'Reçu consultation lié (si sortie)',
  `commentaire`  TEXT DEFAULT NULL,
  `whendone`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `whodone`      INT(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_mvt_fiche_date` (`whendone`),
  KEY `idx_mvt_fiche_recu` (`recu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- FIN MIGRATION 002
-- ============================================================
