-- ============================================================
-- Système de Gestion CSI – AMA Maradi
-- Migration 001 – Schéma complet v1.0
-- Convention BDD : whendone, whodone, isDeleted, lastUpdate
-- INTERDICTION DELETE – Utiliser isDeleted = 1
-- ============================================================

CREATE DATABASE IF NOT EXISTS `csi_ama` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `csi_ama`;

-- ─────────────────────────────────────────────────────────────
-- TABLE : utilisateurs
-- Profils : admin | comptable | percepteur
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `utilisateurs` (
    `id`          INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    `nom`         VARCHAR(100)     NOT NULL,
    `prenom`      VARCHAR(100)     NOT NULL,
    `login`       VARCHAR(80)      NOT NULL UNIQUE,
    `password`    VARCHAR(255)     NOT NULL,
    `role`        ENUM('admin','comptable','percepteur') NOT NULL DEFAULT 'percepteur',
    `est_actif`   TINYINT(1)       NOT NULL DEFAULT 1,
    -- Traçabilité obligatoire
    `whendone`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `whodone`     INT UNSIGNED     NOT NULL DEFAULT 0,
    `isDeleted`   TINYINT(1)       NOT NULL DEFAULT 0,
    `lastUpdate`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_login` (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : config_systeme
-- Paramètres de l'application (entête reçus, logo, etc.)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `config_systeme` (
    `id`          INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    `cle`         VARCHAR(100)     NOT NULL UNIQUE,
    `valeur`      TEXT,
    `whendone`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `whodone`     INT UNSIGNED     NOT NULL DEFAULT 0,
    `isDeleted`   TINYINT(1)       NOT NULL DEFAULT 0,
    `lastUpdate`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : patients
-- Déduplication par numéro de téléphone
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `patients` (
    `id`          INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    `telephone`   VARCHAR(20)      NOT NULL UNIQUE,
    `nom`         VARCHAR(200)     NOT NULL,
    `sexe`        ENUM('M','F')    NOT NULL DEFAULT 'M',
    `age`         TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `provenance`  VARCHAR(150)     DEFAULT NULL,
    `whendone`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `whodone`     INT UNSIGNED     NOT NULL DEFAULT 0,
    `isDeleted`   TINYINT(1)       NOT NULL DEFAULT 0,
    `lastUpdate`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_telephone` (`telephone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : actes_medicaux
-- Catalogue des actes (consultation, CPN, accouchement...)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `actes_medicaux` (
    `id`          INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    `libelle`     VARCHAR(200)     NOT NULL,
    `tarif`       INT UNSIGNED     NOT NULL DEFAULT 300,
    `est_gratuit` TINYINT(1)       NOT NULL DEFAULT 0,
    `whendone`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `whodone`     INT UNSIGNED     NOT NULL DEFAULT 0,
    `isDeleted`   TINYINT(1)       NOT NULL DEFAULT 0,
    `lastUpdate`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : types_carnets
-- Carnets de soins & carnets de santé (CPN)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `types_carnets` (
    `id`          INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    `libelle`     VARCHAR(100)     NOT NULL,
    `tarif`       INT UNSIGNED     NOT NULL DEFAULT 0,
    `est_gratuit` TINYINT(1)       NOT NULL DEFAULT 0,
    `whendone`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `whodone`     INT UNSIGNED     NOT NULL DEFAULT 0,
    `isDeleted`   TINYINT(1)       NOT NULL DEFAULT 0,
    `lastUpdate`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : examens
-- Catalogue des examens laborantin avec % de commission
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `examens` (
    `id`                INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    `libelle`           VARCHAR(200)    NOT NULL,
    `cout_total`        INT UNSIGNED    NOT NULL DEFAULT 0,
    `pourcentage_labo`  DECIMAL(5,2)    NOT NULL DEFAULT 30.00,
    `montant_labo`      INT UNSIGNED    GENERATED ALWAYS AS (ROUND(cout_total * pourcentage_labo / 100)) STORED,
    `whendone`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `whodone`           INT UNSIGNED    NOT NULL DEFAULT 0,
    `isDeleted`         TINYINT(1)      NOT NULL DEFAULT 0,
    `lastUpdate`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : produits_pharmacie
-- Stock pharmaceutique avec seuil d'alerte et péremption
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `produits_pharmacie` (
    `id`               INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    `nom`              VARCHAR(200)   NOT NULL,
    `forme`            ENUM('comprimé','sirop','ampoule','gélule','suppositoire','pommade','solution','autre') NOT NULL DEFAULT 'comprimé',
    `prix_unitaire`    INT UNSIGNED   NOT NULL DEFAULT 0,
    `stock_initial`    INT UNSIGNED   NOT NULL DEFAULT 0,
    `stock_actuel`     INT UNSIGNED   NOT NULL DEFAULT 0,
    `seuil_alerte`     INT UNSIGNED   NOT NULL DEFAULT 10,
    `date_peremption`  DATE           DEFAULT NULL,
    `whendone`         DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `whodone`          INT UNSIGNED   NOT NULL DEFAULT 0,
    `isDeleted`        TINYINT(1)     NOT NULL DEFAULT 0,
    `lastUpdate`       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_stock`       (`stock_actuel`),
    INDEX `idx_peremption`  (`date_peremption`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : approvisionnements_pharmacie
-- Historique des réapprovisionnements
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `approvisionnements_pharmacie` (
    `id`           INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    `produit_id`   INT UNSIGNED   NOT NULL,
    `quantite`     INT UNSIGNED   NOT NULL,
    `date_appro`   DATE           NOT NULL,
    `commentaire`  TEXT           DEFAULT NULL,
    `whendone`     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `whodone`      INT UNSIGNED   NOT NULL DEFAULT 0,
    `isDeleted`    TINYINT(1)     NOT NULL DEFAULT 0,
    `lastUpdate`   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_appro_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits_pharmacie`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : recus
-- Table maîtresse de toutes les transactions
-- Numérotation séquentielle globale (toutes transactions)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `recus` (
    `id`               INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    `numero_recu`      INT UNSIGNED   NOT NULL UNIQUE,
    `patient_id`       INT UNSIGNED   NOT NULL,
    `type_recu`        ENUM('consultation','examen','pharmacie') NOT NULL,
    `type_patient`     ENUM('normal','orphelin','acte_gratuit')  NOT NULL DEFAULT 'normal',
    `montant_total`    INT UNSIGNED   NOT NULL DEFAULT 0,
    `montant_encaisse` INT UNSIGNED   NOT NULL DEFAULT 0,
    `whendone`         DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `whodone`          INT UNSIGNED   NOT NULL DEFAULT 0,
    `isDeleted`        TINYINT(1)     NOT NULL DEFAULT 0,
    `lastUpdate`       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_recu_patient`   FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`),
    INDEX `idx_date`       (`whendone`),
    INDEX `idx_percepteur` (`whodone`),
    INDEX `idx_type_recu`  (`type_recu`),
    INDEX `idx_patient`    (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : lignes_consultation
-- Détail d'un reçu de consultation (acte + carnet)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `lignes_consultation` (
    `id`           INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    `recu_id`      INT UNSIGNED   NOT NULL,
    `acte_id`      INT UNSIGNED   NOT NULL,
    `libelle`      VARCHAR(200)   NOT NULL,
    `tarif`        INT UNSIGNED   NOT NULL DEFAULT 300,
    `est_gratuit`  TINYINT(1)     NOT NULL DEFAULT 0,
    `avec_carnet`  TINYINT(1)     NOT NULL DEFAULT 0,
    `tarif_carnet` INT UNSIGNED   NOT NULL DEFAULT 0,
    `whendone`     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `whodone`      INT UNSIGNED   NOT NULL DEFAULT 0,
    `isDeleted`    TINYINT(1)     NOT NULL DEFAULT 0,
    `lastUpdate`   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_lc_recu` FOREIGN KEY (`recu_id`) REFERENCES `recus`(`id`),
    CONSTRAINT `fk_lc_acte` FOREIGN KEY (`acte_id`) REFERENCES `actes_medicaux`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : lignes_examen
-- Détail d'un reçu d'examens avec part laborantin
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `lignes_examen` (
    `id`              INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    `recu_id`         INT UNSIGNED   NOT NULL,
    `examen_id`       INT UNSIGNED   NOT NULL,
    `libelle`         VARCHAR(200)   NOT NULL,
    `cout_total`      INT UNSIGNED   NOT NULL,
    `pourcentage_labo` DECIMAL(5,2)  NOT NULL DEFAULT 30.00,
    `montant_labo`    INT UNSIGNED   NOT NULL DEFAULT 0,
    `whendone`        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `whodone`         INT UNSIGNED   NOT NULL DEFAULT 0,
    `isDeleted`       TINYINT(1)     NOT NULL DEFAULT 0,
    `lastUpdate`      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_le_recu`   FOREIGN KEY (`recu_id`)   REFERENCES `recus`(`id`),
    CONSTRAINT `fk_le_examen` FOREIGN KEY (`examen_id`) REFERENCES `examens`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : lignes_pharmacie
-- Détail d'un reçu pharmaceutique (produits délivrés)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `lignes_pharmacie` (
    `id`            INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    `recu_id`       INT UNSIGNED   NOT NULL,
    `produit_id`    INT UNSIGNED   NOT NULL,
    `nom`           VARCHAR(200)   NOT NULL,
    `forme`         VARCHAR(50)    NOT NULL,
    `quantite`      INT UNSIGNED   NOT NULL,
    `prix_unitaire` INT UNSIGNED   NOT NULL,
    `total_ligne`   INT UNSIGNED   NOT NULL,
    `whendone`      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `whodone`       INT UNSIGNED   NOT NULL DEFAULT 0,
    `isDeleted`     TINYINT(1)     NOT NULL DEFAULT 0,
    `lastUpdate`    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_lp_recu`    FOREIGN KEY (`recu_id`)    REFERENCES `recus`(`id`),
    CONSTRAINT `fk_lp_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits_pharmacie`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE : inventaire_physique
-- Rapprochement stock physique vs stock théorique
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `inventaire_physique` (
    `id`              INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    `produit_id`      INT UNSIGNED   NOT NULL,
    `stock_physique`  INT UNSIGNED   NOT NULL DEFAULT 0,
    `stock_theorique` INT UNSIGNED   NOT NULL DEFAULT 0,
    `ecart`           INT            GENERATED ALWAYS AS (stock_physique - stock_theorique) STORED,
    `commentaire`     TEXT           DEFAULT NULL,
    `whendone`        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `whodone`         INT UNSIGNED   NOT NULL DEFAULT 0,
    `isDeleted`       TINYINT(1)     NOT NULL DEFAULT 0,
    `lastUpdate`      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_inv_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits_pharmacie`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
