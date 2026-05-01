-- database/patches/patch_orphelin_flag.sql
-- Date : 2026-04-25
-- Objet : Ajout colonne est_orphelin sur patients

ALTER TABLE patients
    ADD COLUMN IF NOT EXISTS est_orphelin TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = orphelin DirectAid AMA'
    AFTER provenance;

CREATE INDEX IF NOT EXISTS idx_patients_orphelin ON patients(est_orphelin);
