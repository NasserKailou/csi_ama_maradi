-- =====================================================================
-- MIGRATION : Gestion des règlements DirectAid AMA pour les orphelins
-- Date : 2026-05-02 (version corrigée — types alignés sur le schéma réel)
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. Ajout des colonnes de statut de règlement sur la table recus
-- ---------------------------------------------------------------------
ALTER TABLE `recus`
    ADD COLUMN `statut_reglement` ENUM('regle','en_instance') NOT NULL DEFAULT 'regle' AFTER `type_patient`,
    ADD COLUMN `date_reglement`   DATETIME NULL DEFAULT NULL AFTER `statut_reglement`,
    ADD COLUMN `reglement_id`     INT(10) UNSIGNED NULL DEFAULT NULL AFTER `date_reglement`,
    ADD INDEX `idx_recus_statut_reglement` (`statut_reglement`),
    ADD INDEX `idx_recus_reglement_id` (`reglement_id`);

-- ---------------------------------------------------------------------
-- 2. Mise à jour des données existantes
-- ---------------------------------------------------------------------
UPDATE `recus`
SET `statut_reglement` = 'en_instance', `date_reglement` = NULL
WHERE `type_patient` = 'orphelin' AND `isDeleted` = 0;

UPDATE `recus`
SET `statut_reglement` = 'regle', `date_reglement` = `whendone`
WHERE `type_patient` IN ('normal','acte_gratuit') AND `isDeleted` = 0;

-- ---------------------------------------------------------------------
-- 3. Table principale des règlements (types alignés sur utilisateurs.id)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reglements_orphelins` (
    `id`                  INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `numero_reglement`    VARCHAR(50) NOT NULL,
    `date_reglement`      DATE NOT NULL,
    `montant_total`       INT(10) UNSIGNED NOT NULL DEFAULT 0,
    `nb_recus`            INT(10) UNSIGNED NOT NULL DEFAULT 0,
    `mode_paiement`       ENUM('especes','cheque','virement','mobile_money') NOT NULL DEFAULT 'especes',
    `reference_paiement`  VARCHAR(100) NULL,
    `observations`        TEXT NULL,
    `whendone`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `whodone`             INT(10) UNSIGNED NOT NULL DEFAULT 0,
    `isDeleted`           TINYINT(1) NOT NULL DEFAULT 0,
    `lastUpdate`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_numero_reglement` (`numero_reglement`),
    KEY `idx_date_reglement` (`date_reglement`),
    KEY `idx_regle_par` (`whodone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. Clé étrangère reliant les reçus au règlement
-- ---------------------------------------------------------------------
ALTER TABLE `recus`
    ADD CONSTRAINT `fk_recu_reglement`
    FOREIGN KEY (`reglement_id`) REFERENCES `reglements_orphelins` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 5. Vérification
-- ---------------------------------------------------------------------
SELECT 
    type_patient,
    statut_reglement,
    COUNT(*) AS nb,
    COALESCE(SUM(montant_total),0) AS total
FROM recus 
WHERE isDeleted = 0
GROUP BY type_patient, statut_reglement
ORDER BY type_patient, statut_reglement;
