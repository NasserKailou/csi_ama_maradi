-- ═══════════════════════════════════════════════════════════════════════════
-- SCRIPT DE NETTOYAGE – Base directaid (version corrigée)
-- ───────────────────────────────────────────────────────────────────────────
-- Réinitialise les données transactionnelles et remet les compteurs à zéro.
-- 
-- ✅ PRÉSERVE : utilisateurs, actes_medicaux, examens, produits_pharmacie,
--               types_carnets, config_systeme
-- 
-- 🗑 SUPPRIME : recus, lignes_consultation, lignes_examen, lignes_pharmacie,
--               modifications_recus, approvisionnements_pharmacie,
--               inventaire_physique, patients
-- 
-- ⚠ FAIRE UNE SAUVEGARDE COMPLÈTE AVANT EXÉCUTION !
-- ═══════════════════════════════════════════════════════════════════════════

-- Désactivation des contraintes FK pour toute la session
SET FOREIGN_KEY_CHECKS = 0;

-- ───────────────────────────────────────────────────────────────────────────
-- 1. Vidage des tables transactionnelles via DELETE
--    (DELETE fonctionne malgré les FK quand FOREIGN_KEY_CHECKS=0)
-- ───────────────────────────────────────────────────────────────────────────
DELETE FROM `lignes_consultation`;
DELETE FROM `lignes_examen`;
DELETE FROM `lignes_pharmacie`;
DELETE FROM `modifications_recus`;
DELETE FROM `recus`;
DELETE FROM `approvisionnements_pharmacie`;
DELETE FROM `inventaire_physique`;
DELETE FROM `patients`;

-- ───────────────────────────────────────────────────────────────────────────
-- 2. Réinitialisation explicite des compteurs AUTO_INCREMENT
-- ───────────────────────────────────────────────────────────────────────────
ALTER TABLE `recus`                          AUTO_INCREMENT = 1;
ALTER TABLE `lignes_consultation`            AUTO_INCREMENT = 1;
ALTER TABLE `lignes_examen`                  AUTO_INCREMENT = 1;
ALTER TABLE `lignes_pharmacie`               AUTO_INCREMENT = 1;
ALTER TABLE `modifications_recus`            AUTO_INCREMENT = 1;
ALTER TABLE `approvisionnements_pharmacie`   AUTO_INCREMENT = 1;
ALTER TABLE `inventaire_physique`            AUTO_INCREMENT = 1;
ALTER TABLE `patients`                       AUTO_INCREMENT = 1;

-- ───────────────────────────────────────────────────────────────────────────
-- 3. Remise à niveau du stock pharmacie : stock_actuel = stock_initial
-- ───────────────────────────────────────────────────────────────────────────
UPDATE `produits_pharmacie`
SET `stock_actuel` = `stock_initial`
WHERE `isDeleted` = 0;

-- ───────────────────────────────────────────────────────────────────────────
-- 4. Réactivation des contraintes
-- ───────────────────────────────────────────────────────────────────────────
SET FOREIGN_KEY_CHECKS = 1;

-- ═══════════════════════════════════════════════════════════════════════════
-- VÉRIFICATIONS POST-EXÉCUTION
-- ═══════════════════════════════════════════════════════════════════════════
SELECT 'recus'                       AS table_name, COUNT(*) AS nb FROM recus
UNION ALL SELECT 'lignes_consultation',          COUNT(*) FROM lignes_consultation
UNION ALL SELECT 'lignes_examen',                COUNT(*) FROM lignes_examen
UNION ALL SELECT 'lignes_pharmacie',             COUNT(*) FROM lignes_pharmacie
UNION ALL SELECT 'modifications_recus',          COUNT(*) FROM modifications_recus
UNION ALL SELECT 'approvisionnements_pharmacie', COUNT(*) FROM approvisionnements_pharmacie
UNION ALL SELECT 'inventaire_physique',          COUNT(*) FROM inventaire_physique
UNION ALL SELECT 'patients',                     COUNT(*) FROM patients
UNION ALL SELECT '--- préservées ---',           NULL
UNION ALL SELECT 'utilisateurs',                 COUNT(*) FROM utilisateurs    WHERE isDeleted=0
UNION ALL SELECT 'actes_medicaux',               COUNT(*) FROM actes_medicaux  WHERE isDeleted=0
UNION ALL SELECT 'examens',                      COUNT(*) FROM examens         WHERE isDeleted=0
UNION ALL SELECT 'produits_pharmacie',           COUNT(*) FROM produits_pharmacie WHERE isDeleted=0
UNION ALL SELECT 'types_carnets',                COUNT(*) FROM types_carnets   WHERE isDeleted=0
UNION ALL SELECT 'config_systeme',               COUNT(*) FROM config_systeme  WHERE isDeleted=0;
