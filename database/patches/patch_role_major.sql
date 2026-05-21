-- ============================================================
-- Migration : ajout de la valeur 'major' dans l'enum role
-- Table     : utilisateurs
-- Date      : 2026-05-16
-- ============================================================

ALTER TABLE `utilisateurs`
    MODIFY COLUMN `role` ENUM('admin','comptable','percepteur','major')
        NOT NULL DEFAULT 'percepteur';
