USE `directaid`;

START TRANSACTION;

SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- 1. ACTES MEDICAUX
-- =========================================================

INSERT INTO `actes_medicaux`
(`id`, `libelle`, `tarif`, `est_gratuit`, `whendone`, `whodone`, `isDeleted`)
VALUES
(1, 'Consultation Générale', 300, 0, '2026-01-01 08:00:00', 1, 0),
(2, 'Consultation Prénatale (CPN)', 0, 1, '2026-01-01 08:00:00', 1, 0),
(3, 'Consultation Nourrissons', 0, 1, '2026-01-01 08:00:00', 1, 0),
(4, 'Accouchement', 0, 1, '2026-01-01 08:00:00', 1, 0),
(5, 'Planning Familial', 300, 1, '2026-01-01 08:00:00', 1, 0),
(6, 'Consultation Pédiatrique', 300, 0, '2026-01-01 08:00:00', 1, 0),
(7, 'Consultation d''urgence', 300, 0, '2026-01-01 08:00:00', 1, 0),
(8, 'Visite Domicile', 500, 0, '2026-01-01 08:00:00', 1, 0),
(9, 'Renouvellement Ordonnance', 200, 0, '2026-01-01 08:00:00', 1, 0);

-- =========================================================
-- 2. TYPES DE CARNETS
-- =========================================================

INSERT INTO `types_carnets`
(`id`, `libelle`, `tarif`, `est_gratuit`, `whendone`, `whodone`, `isDeleted`)
VALUES
(1, 'Carnet de Soins', 100, 0, '2026-01-01 08:00:00', 1, 0),
(2, 'Carnet de Santé', 100, 0, '2026-01-01 08:00:00', 1, 0);

-- =========================================================
-- 3. EXAMENS DE LABORATOIRE
-- pourcentage_labo = 0.00 pour tous
-- montant_labo est généré automatiquement dans examens
-- =========================================================

INSERT INTO `examens`
(`id`, `libelle`, `cout_total`, `pourcentage_labo`, `whendone`, `whodone`, `isDeleted`)
VALUES
(1, 'Antigénémie HBS', 1200, 0.00, '2026-01-01 08:00:00', 1, 0),
(2, 'Albuminurie', 700, 0.00, '2026-01-01 08:00:00', 1, 0),
(3, 'Test Syphilis BW', 700, 0.00, '2026-01-01 08:00:00', 1, 0),
(4, 'Créatininémie', 1500, 0.00, '2026-01-01 08:00:00', 1, 0),
(5, 'Culot urinaire', 900, 0.00, '2026-01-01 08:00:00', 1, 0),
(6, 'Dosage hémoglobine', 900, 0.00, '2026-01-01 08:00:00', 1, 0),
(7, 'Glucosurie', 800, 0.00, '2026-01-01 08:00:00', 1, 0),
(8, 'Glycémie', 1100, 0.00, '2026-01-01 08:00:00', 1, 0),
(9, 'Goutte Epaisse', 400, 0.00, '2026-01-01 08:00:00', 1, 0),
(10, 'Groupe Sanguin/Rhésus', 1300, 0.00, '2026-01-01 08:00:00', 1, 0),
(11, 'Test rapide Hépatite Virale C (VHC)', 700, 0.00, '2026-01-01 08:00:00', 1, 0),
(12, 'NFS', 1700, 0.00, '2026-01-01 08:00:00', 1, 0),
(13, 'Protéinurie', 2100, 0.00, '2026-01-01 08:00:00', 1, 0),
(14, 'Selles KOPA', 500, 0.00, '2026-01-01 08:00:00', 1, 0),
(15, 'Test de Grossesse', 1100, 0.00, '2026-01-01 08:00:00', 1, 0),
(16, 'Test d''Emmel', 700, 0.00, '2026-01-01 08:00:00', 1, 0),
(17, 'Azotémie', 1500, 0.00, '2026-01-01 08:00:00', 1, 0),
(18, 'Widal', 1100, 0.00, '2026-01-01 08:00:00', 1, 0);

-- =========================================================
-- 4. PRODUITS PHARMACIE
-- =========================================================

INSERT INTO `produits_pharmacie`
(`id`, `nom`, `forme`, `prix_unitaire`, `stock_initial`, `stock_actuel`, `seuil_alerte`, `date_peremption`, `whendone`, `whodone`, `isDeleted`)
VALUES
(1, 'Amoxi sp 125 mg', 'sirop', 650, 80, 80, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(2, 'Amoxi sp 250 mg', 'sirop', 1000, 80, 80, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(3, 'Amoxi gel 500 mg', 'gélule', 500, 150, 150, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(4, 'Amoxi 1g', 'comprimé', 200, 200, 200, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(5, 'Analgin inj', 'ampoule', 200, 100, 100, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(6, 'Buthyl cp', 'comprimé', 700, 100, 100, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(7, 'Buthyl inj', 'ampoule', 250, 100, 100, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(8, 'B complexe', 'comprimé', 250, 150, 150, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(9, 'Caha presson', 'autre', 1750, 50, 50, 5, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(10, 'Clox gel', 'gélule', 600, 100, 100, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(11, 'Cipro 500 mg', 'comprimé', 850, 100, 100, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(12, 'Cotri cp', 'comprimé', 150, 150, 150, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(13, 'Dexa inj', 'ampoule', 100, 150, 150, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(14, 'Diazepan', 'comprimé', 500, 80, 80, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(15, 'Genta inj', 'ampoule', 200, 120, 120, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(16, 'Gant sterile', 'autre', 100, 300, 300, 20, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(17, 'Gant en vrac', 'autre', 100, 300, 300, 20, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(18, 'Hydroxyd dl', 'autre', 100, 100, 100, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(19, 'Ibuprofene up', 'comprimé', 150, 200, 200, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(20, 'Metro cp', 'comprimé', 100, 200, 200, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(21, 'Metro sp', 'sirop', 800, 80, 80, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(22, 'Fil à suture', 'autre', 1000, 50, 50, 5, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(23, 'Para sp', 'sirop', 750, 80, 80, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(24, 'Para cp', 'comprimé', 100, 250, 250, 20, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(25, 'Promethozine cp', 'comprimé', 100, 150, 150, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(26, 'Cimetidine inj', 'ampoule', 250, 100, 100, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(27, 'Pommade Tetral 1', 'pommade', 200, 100, 100, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(28, 'Vogalene inj', 'ampoule', 500, 100, 100, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(29, 'Serum cilycose', 'solution', 1000, 80, 80, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(30, 'Serum salé', 'solution', 1000, 80, 80, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(31, 'Serum Ringer', 'solution', 1000, 80, 80, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(32, 'Perfuseur', 'autre', 250, 150, 150, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(33, 'Cathéter', 'autre', 500, 120, 120, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(34, 'Seringue 5 cc', 'autre', 100, 300, 300, 20, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(35, 'Seringue 10 cc', 'autre', 150, 300, 300, 20, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(36, 'Carnet de santé', 'autre', 100, 200, 200, 20, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(37, 'Carnet soins', 'autre', 100, 200, 200, 20, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(38, 'Epicranienne', 'autre', 250, 120, 120, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(39, 'Eau distille', 'solution', 100, 150, 150, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(40, 'Metro soluté', 'solution', 1000, 80, 80, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(41, 'Para soluté', 'solution', 1250, 80, 80, 10, '2027-12-31', '2026-01-01 08:00:00', 1, 0),
(42, 'Consultation', 'autre', 300, 0, 0, 0, NULL, '2026-01-01 08:00:00', 1, 0),
(43, 'Mise en observation', 'autre', 1000, 0, 0, 0, NULL, '2026-01-01 08:00:00', 1, 0);

-- =========================================================
-- 5. APPROVISIONNEMENTS PHARMACIE
-- =========================================================

INSERT INTO `approvisionnements_pharmacie`
(`id`, `produit_id`, `quantite`, `date_appro`, `commentaire`, `whendone`, `whodone`, `isDeleted`)
VALUES
(1, 1, 30, '2026-01-03', 'Stock test janvier', '2026-01-03 08:30:00', 1, 0),
(2, 4, 50, '2026-01-03', 'Stock test janvier', '2026-01-03 08:30:00', 1, 0),
(3, 24, 100, '2026-01-03', 'Stock test janvier', '2026-01-03 08:30:00', 1, 0),
(4, 11, 20, '2026-02-02', 'Réapprovisionnement février', '2026-02-02 09:00:00', 1, 0),
(5, 19, 50, '2026-02-02', 'Réapprovisionnement février', '2026-02-02 09:00:00', 1, 0),
(6, 30, 30, '2026-02-02', 'Réapprovisionnement février', '2026-02-02 09:00:00', 1, 0),
(7, 15, 30, '2026-03-04', 'Réapprovisionnement mars', '2026-03-04 09:15:00', 1, 0),
(8, 31, 30, '2026-03-04', 'Réapprovisionnement mars', '2026-03-04 09:15:00', 1, 0),
(9, 35, 100, '2026-03-04', 'Réapprovisionnement mars', '2026-03-04 09:15:00', 1, 0),
(10, 29, 30, '2026-04-06', 'Réapprovisionnement avril', '2026-04-06 09:10:00', 1, 0),
(11, 40, 30, '2026-04-06', 'Réapprovisionnement avril', '2026-04-06 09:10:00', 1, 0),
(12, 41, 30, '2026-04-06', 'Réapprovisionnement avril', '2026-04-06 09:10:00', 1, 0);

-- =========================================================
-- 6. PATIENTS
-- =========================================================

INSERT INTO `patients`
(`id`, `telephone`, `nom`, `sexe`, `age`, `provenance`, `est_orphelin`, `whendone`, `whodone`, `isDeleted`)
VALUES
(1, '90000001', 'Amina Abdou', 'F', 28, 'Maradi', 0, '2026-01-05 08:55:00', 1, 0),
(2, '90000002', 'Moussa Issoufou', 'M', 34, 'Tibiri', 0, '2026-01-06 09:10:00', 1, 0),
(3, '90000003', 'Issaka Ali', 'M', 9, 'Maradi', 1, '2026-01-07 09:20:00', 1, 0),
(4, '90000004', 'Fatouma Sani', 'F', 22, 'Madarounfa', 0, '2026-01-10 10:00:00', 1, 0),
(5, '90000005', 'Halima Mamane', 'F', 31, 'Maradi', 0, '2026-02-03 08:40:00', 1, 0),
(6, '90000006', 'Souleymane Garba', 'M', 7, 'Maradi', 1, '2026-02-04 09:05:00', 1, 0),
(7, '90000007', 'Rabiou Danladi', 'M', 45, 'Dakoro', 0, '2026-02-05 09:25:00', 1, 0),
(8, '90000008', 'Zeinabou Lawali', 'F', 19, 'Guidan Roumdji', 0, '2026-03-02 08:30:00', 1, 0),
(9, '90000009', 'Nafissa Oumarou', 'F', 26, 'Maradi', 0, '2026-03-03 08:50:00', 1, 0),
(10, '90000010', 'Abdoul Karim', 'M', 11, 'Maradi', 1, '2026-03-04 09:15:00', 1, 0),
(11, '90000011', 'Bachir Saley', 'M', 52, 'Aguié', 0, '2026-04-03 09:00:00', 1, 0),
(12, '90000012', 'Sani Abdoulaye', 'M', 6, 'Maradi', 1, '2026-04-04 09:30:00', 1, 0);

-- =========================================================
-- 7. REGLEMENTS ORPHELINS
-- insérés avant les reçus car recus.reglement_id référence cette table
-- =========================================================

INSERT INTO `reglements_orphelins`
(`id`, `numero_reglement`, `date_reglement`, `montant_total`, `nb_recus`, `mode_paiement`, `reference_paiement`, `observations`, `whendone`, `whodone`, `isDeleted`)
VALUES
(1, 'RGL-20260131-001', '2026-01-31', 4000, 3, 'especes', 'REG-JAN-001', 'Règlement orphelins janvier 2026', '2026-01-31 16:00:00', 1, 0),
(2, 'RGL-20260228-001', '2026-02-28', 2200, 2, 'especes', 'REG-FEV-001', 'Règlement orphelins février 2026', '2026-02-28 16:00:00', 1, 0),
(3, 'RGL-20260331-001', '2026-03-31', 5300, 3, 'especes', 'REG-MAR-001', 'Règlement orphelins mars 2026', '2026-03-31 16:00:00', 1, 0),
(4, 'RGL-20260430-001', '2026-04-30', 6250, 3, 'especes', 'REG-AVR-001', 'Règlement orphelins avril 2026', '2026-04-30 16:00:00', 1, 0);

-- =========================================================
-- 8. RECUS
-- =========================================================

INSERT INTO `recus`
(`id`, `numero_recu`, `patient_id`, `type_recu`, `type_patient`, `statut_reglement`, `date_reglement`, `reglement_id`, `montant_total`, `montant_encaisse`, `whendone`, `whodone`, `isDeleted`)
VALUES
-- Janvier 2026
(1, 1, 1, 'consultation', 'normal', 'regle', '2026-01-05 09:00:00', NULL, 400, 400, '2026-01-05 09:00:00', 3, 0),
(2, 2, 2, 'consultation', 'acte_gratuit', 'regle', '2026-01-06 09:20:00', NULL, 0, 0, '2026-01-06 09:20:00', 3, 0),
(3, 3, 3, 'consultation', 'orphelin', 'regle', '2026-01-31 16:00:00', 1, 300, 300, '2026-01-07 09:30:00', 3, 0),
(4, 4, 1, 'examen', 'normal', 'regle', '2026-01-08 10:10:00', NULL, 2200, 2200, '2026-01-08 10:10:00', 3, 0),
(5, 5, 3, 'examen', 'orphelin', 'regle', '2026-01-31 16:00:00', 1, 2100, 2100, '2026-01-09 10:40:00', 3, 0),
(6, 6, 4, 'pharmacie', 'normal', 'regle', '2026-01-10 11:00:00', NULL, 1000, 1000, '2026-01-10 11:00:00', 3, 0),
(7, 7, 3, 'pharmacie', 'orphelin', 'regle', '2026-01-31 16:00:00', 1, 1600, 1600, '2026-01-12 11:30:00', 3, 0),

-- Février 2026
(8, 8, 5, 'consultation', 'normal', 'regle', '2026-02-03 09:00:00', NULL, 300, 300, '2026-02-03 09:00:00', 3, 0),
(9, 9, 6, 'consultation', 'orphelin', 'regle', '2026-02-28 16:00:00', 2, 300, 300, '2026-02-04 09:15:00', 3, 0),
(10, 10, 5, 'examen', 'normal', 'regle', '2026-02-05 09:40:00', NULL, 2800, 2800, '2026-02-05 09:40:00', 3, 0),
(11, 11, 7, 'examen', 'normal', 'regle', '2026-02-06 10:00:00', NULL, 1100, 1100, '2026-02-06 10:00:00', 3, 0),
(12, 12, 5, 'pharmacie', 'normal', 'regle', '2026-02-07 10:20:00', NULL, 1350, 1350, '2026-02-07 10:20:00', 3, 0),
(13, 13, 6, 'pharmacie', 'orphelin', 'regle', '2026-02-28 16:00:00', 2, 1900, 1900, '2026-02-08 10:45:00', 3, 0),

-- Mars 2026
(14, 14, 8, 'consultation', 'normal', 'regle', '2026-03-02 09:00:00', NULL, 300, 300, '2026-03-02 09:00:00', 3, 0),
(15, 15, 9, 'consultation', 'acte_gratuit', 'regle', '2026-03-03 09:10:00', NULL, 0, 0, '2026-03-03 09:10:00', 3, 0),
(16, 16, 10, 'consultation', 'orphelin', 'regle', '2026-03-31 16:00:00', 3, 400, 400, '2026-03-04 09:40:00', 3, 0),
(17, 17, 8, 'examen', 'normal', 'regle', '2026-03-05 10:00:00', NULL, 2600, 2600, '2026-03-05 10:00:00', 3, 0),
(18, 18, 10, 'examen', 'orphelin', 'regle', '2026-03-31 16:00:00', 3, 3200, 3200, '2026-03-06 10:30:00', 3, 0),
(19, 19, 9, 'pharmacie', 'normal', 'regle', '2026-03-07 11:00:00', NULL, 1700, 1700, '2026-03-07 11:00:00', 3, 0),
(20, 20, 8, 'pharmacie', 'normal', 'regle', '2026-03-08 11:20:00', NULL, 1300, 1300, '2026-03-08 11:20:00', 3, 0),
(21, 21, 10, 'pharmacie', 'orphelin', 'regle', '2026-03-31 16:00:00', 3, 1700, 1700, '2026-03-09 11:40:00', 3, 0),

-- Avril 2026
(22, 22, 11, 'consultation', 'normal', 'regle', '2026-04-03 09:00:00', NULL, 600, 600, '2026-04-03 09:00:00', 3, 0),
(23, 23, 12, 'consultation', 'orphelin', 'regle', '2026-04-30 16:00:00', 4, 300, 300, '2026-04-04 09:20:00', 3, 0),
(24, 24, 11, 'examen', 'normal', 'regle', '2026-04-05 09:45:00', NULL, 4300, 4300, '2026-04-05 09:45:00', 3, 0),
(25, 25, 2, 'examen', 'normal', 'regle', '2026-04-06 10:00:00', NULL, 1200, 1200, '2026-04-06 10:00:00', 3, 0),
(26, 26, 12, 'examen', 'orphelin', 'regle', '2026-04-30 16:00:00', 4, 3200, 3200, '2026-04-07 10:30:00', 3, 0),
(27, 27, 11, 'pharmacie', 'normal', 'regle', '2026-04-08 11:00:00', NULL, 1800, 1800, '2026-04-08 11:00:00', 3, 0),
(28, 28, 2, 'pharmacie', 'normal', 'regle', '2026-04-09 11:20:00', NULL, 900, 900, '2026-04-09 11:20:00', 3, 0),
(29, 29, 12, 'pharmacie', 'orphelin', 'regle', '2026-04-30 16:00:00', 4, 2750, 2750, '2026-04-10 11:40:00', 3, 0);

-- =========================================================
-- 9. LIGNES CONSULTATION
-- =========================================================

INSERT INTO `lignes_consultation`
(`id`, `recu_id`, `acte_id`, `libelle`, `tarif`, `est_gratuit`, `avec_carnet`, `tarif_carnet`, `whendone`, `whodone`, `isDeleted`)
VALUES
(1, 1, 1, 'Consultation Générale', 300, 0, 1, 100, '2026-01-05 09:00:00', 3, 0),
(2, 2, 2, 'Consultation Prénatale (CPN)', 0, 1, 0, 0, '2026-01-06 09:20:00', 3, 0),
(3, 3, 1, 'Consultation Générale', 300, 0, 0, 0, '2026-01-07 09:30:00', 3, 0),
(4, 8, 6, 'Consultation Pédiatrique', 300, 0, 0, 0, '2026-02-03 09:00:00', 3, 0),
(5, 9, 1, 'Consultation Générale', 300, 0, 0, 0, '2026-02-04 09:15:00', 3, 0),
(6, 14, 7, 'Consultation d''urgence', 300, 0, 0, 0, '2026-03-02 09:00:00', 3, 0),
(7, 15, 4, 'Accouchement', 0, 1, 0, 0, '2026-03-03 09:10:00', 3, 0),
(8, 16, 1, 'Consultation Générale', 300, 0, 1, 100, '2026-03-04 09:40:00', 3, 0),
(9, 22, 8, 'Visite Domicile', 500, 0, 1, 100, '2026-04-03 09:00:00', 3, 0),
(10, 23, 1, 'Consultation Générale', 300, 0, 0, 0, '2026-04-04 09:20:00', 3, 0);

-- =========================================================
-- 10. LIGNES EXAMEN
-- montant_labo = 0 car pourcentage_labo = 0
-- =========================================================

INSERT INTO `lignes_examen`
(`id`, `recu_id`, `examen_id`, `libelle`, `cout_total`, `pourcentage_labo`, `montant_labo`, `whendone`, `whodone`, `isDeleted`)
VALUES
(1, 4, 8, 'Glycémie', 1100, 0.00, 0, '2026-01-08 10:10:00', 3, 0),
(2, 4, 18, 'Widal', 1100, 0.00, 0, '2026-01-08 10:10:00', 3, 0),
(3, 5, 12, 'NFS', 1700, 0.00, 0, '2026-01-09 10:40:00', 3, 0),
(4, 5, 9, 'Goutte Epaisse', 400, 0.00, 0, '2026-01-09 10:40:00', 3, 0),

(5, 10, 4, 'Créatininémie', 1500, 0.00, 0, '2026-02-05 09:40:00', 3, 0),
(6, 10, 10, 'Groupe Sanguin/Rhésus', 1300, 0.00, 0, '2026-02-05 09:40:00', 3, 0),
(7, 11, 15, 'Test de Grossesse', 1100, 0.00, 0, '2026-02-06 10:00:00', 3, 0),

(8, 17, 1, 'Antigénémie HBS', 1200, 0.00, 0, '2026-03-05 10:00:00', 3, 0),
(9, 17, 2, 'Albuminurie', 700, 0.00, 0, '2026-03-05 10:00:00', 3, 0),
(10, 17, 3, 'Test Syphilis BW', 700, 0.00, 0, '2026-03-05 10:00:00', 3, 0),
(11, 18, 13, 'Protéinurie', 2100, 0.00, 0, '2026-03-06 10:30:00', 3, 0),
(12, 18, 18, 'Widal', 1100, 0.00, 0, '2026-03-06 10:30:00', 3, 0),

(13, 24, 12, 'NFS', 1700, 0.00, 0, '2026-04-05 09:45:00', 3, 0),
(14, 24, 8, 'Glycémie', 1100, 0.00, 0, '2026-04-05 09:45:00', 3, 0),
(15, 24, 17, 'Azotémie', 1500, 0.00, 0, '2026-04-05 09:45:00', 3, 0),
(16, 25, 11, 'Test rapide Hépatite Virale C (VHC)', 700, 0.00, 0, '2026-04-06 10:00:00', 3, 0),
(17, 25, 14, 'Selles KOPA', 500, 0.00, 0, '2026-04-06 10:00:00', 3, 0),
(18, 26, 4, 'Créatininémie', 1500, 0.00, 0, '2026-04-07 10:30:00', 3, 0),
(19, 26, 6, 'Dosage hémoglobine', 900, 0.00, 0, '2026-04-07 10:30:00', 3, 0),
(20, 26, 7, 'Glucosurie', 800, 0.00, 0, '2026-04-07 10:30:00', 3, 0);

-- =========================================================
-- 11. LIGNES PHARMACIE
-- =========================================================

INSERT INTO `lignes_pharmacie`
(`id`, `recu_id`, `produit_id`, `nom`, `forme`, `quantite`, `prix_unitaire`, `total_ligne`, `whendone`, `whodone`, `isDeleted`)
VALUES
-- Janvier
(1, 6, 24, 'Para cp', 'comprimé', 2, 100, 200, '2026-01-10 11:00:00', 3, 0),
(2, 6, 4, 'Amoxi 1g', 'comprimé', 3, 200, 600, '2026-01-10 11:00:00', 3, 0),
(3, 6, 20, 'Metro cp', 'comprimé', 2, 100, 200, '2026-01-10 11:00:00', 3, 0),
(4, 7, 11, 'Cipro 500 mg', 'comprimé', 1, 850, 850, '2026-01-12 11:30:00', 3, 0),
(5, 7, 23, 'Para sp', 'sirop', 1, 750, 750, '2026-01-12 11:30:00', 3, 0),

-- Février
(6, 12, 19, 'Ibuprofene up', 'comprimé', 5, 150, 750, '2026-02-07 10:20:00', 3, 0),
(7, 12, 12, 'Cotri cp', 'comprimé', 4, 150, 600, '2026-02-07 10:20:00', 3, 0),
(8, 13, 1, 'Amoxi sp 125 mg', 'sirop', 1, 650, 650, '2026-02-08 10:45:00', 3, 0),
(9, 13, 30, 'Serum salé', 'solution', 1, 1000, 1000, '2026-02-08 10:45:00', 3, 0),
(10, 13, 32, 'Perfuseur', 'autre', 1, 250, 250, '2026-02-08 10:45:00', 3, 0),

-- Mars
(11, 19, 3, 'Amoxi gel 500 mg', 'gélule', 2, 500, 1000, '2026-03-07 11:00:00', 3, 0),
(12, 19, 15, 'Genta inj', 'ampoule', 2, 200, 400, '2026-03-07 11:00:00', 3, 0),
(13, 19, 35, 'Seringue 10 cc', 'autre', 2, 150, 300, '2026-03-07 11:00:00', 3, 0),
(14, 20, 21, 'Metro sp', 'sirop', 1, 800, 800, '2026-03-08 11:20:00', 3, 0),
(15, 20, 24, 'Para cp', 'comprimé', 5, 100, 500, '2026-03-08 11:20:00', 3, 0),
(16, 21, 31, 'Serum Ringer', 'solution', 1, 1000, 1000, '2026-03-09 11:40:00', 3, 0),
(17, 21, 33, 'Cathéter', 'autre', 1, 500, 500, '2026-03-09 11:40:00', 3, 0),
(18, 21, 16, 'Gant sterile', 'autre', 2, 100, 200, '2026-03-09 11:40:00', 3, 0),

-- Avril
(19, 27, 13, 'Dexa inj', 'ampoule', 3, 100, 300, '2026-04-08 11:00:00', 3, 0),
(20, 27, 28, 'Vogalene inj', 'ampoule', 1, 500, 500, '2026-04-08 11:00:00', 3, 0),
(21, 27, 29, 'Serum cilycose', 'solution', 1, 1000, 1000, '2026-04-08 11:00:00', 3, 0),
(22, 28, 26, 'Cimetidine inj', 'ampoule', 2, 250, 500, '2026-04-09 11:20:00', 3, 0),
(23, 28, 27, 'Pommade Tetral 1', 'pommade', 1, 200, 200, '2026-04-09 11:20:00', 3, 0),
(24, 28, 39, 'Eau distille', 'solution', 2, 100, 200, '2026-04-09 11:20:00', 3, 0),
(25, 29, 41, 'Para soluté', 'solution', 1, 1250, 1250, '2026-04-10 11:40:00', 3, 0),
(26, 29, 40, 'Metro soluté', 'solution', 1, 1000, 1000, '2026-04-10 11:40:00', 3, 0),
(27, 29, 32, 'Perfuseur', 'autre', 2, 250, 500, '2026-04-10 11:40:00', 3, 0);

-- =========================================================
-- 12. MODIFICATIONS DE RECUS - données de test
-- =========================================================

INSERT INTO `modifications_recus`
(`id`, `recu_id`, `user_id`, `type_recu`, `motif`, `detail_avant`, `detail_apres`, `whendone`)
VALUES
(1, 6, 3, 'pharmacie', 'Correction quantité produit test',
 '{"montant_total":800,"lignes":[{"produit_id":24,"quantite":1}]}',
 '{"montant_total":1000,"lignes":[{"produit_id":24,"quantite":2}]}',
 '2026-01-10 11:15:00'),
(2, 22, 3, 'consultation', 'Ajout carnet de soins',
 '{"montant_total":500,"avec_carnet":0}',
 '{"montant_total":600,"avec_carnet":1}',
 '2026-04-03 09:20:00');

-- =========================================================
-- 13. MISE A JOUR DES STOCKS ACTUELS
-- stock_actuel = stock_initial + approvisionnements - ventes
-- =========================================================

UPDATE `produits_pharmacie` p
LEFT JOIN (
    SELECT `produit_id`, SUM(`quantite`) AS qte_appro
    FROM `approvisionnements_pharmacie`
    WHERE `isDeleted` = 0
    GROUP BY `produit_id`
) a ON a.`produit_id` = p.`id`
LEFT JOIN (
    SELECT `produit_id`, SUM(`quantite`) AS qte_vendue
    FROM `lignes_pharmacie`
    WHERE `isDeleted` = 0
    GROUP BY `produit_id`
) v ON v.`produit_id` = p.`id`
SET p.`stock_actuel` = GREATEST(
    p.`stock_initial` + COALESCE(a.`qte_appro`, 0) - COALESCE(v.`qte_vendue`, 0),
    0
);

-- =========================================================
-- 14. INVENTAIRE PHYSIQUE TEST
-- ecart est généré automatiquement
-- =========================================================

INSERT INTO `inventaire_physique`
(`id`, `produit_id`, `stock_physique`, `stock_theorique`, `commentaire`, `whendone`, `whodone`, `isDeleted`)
VALUES
(1, 24, 343, 343, 'Inventaire test après ventes janvier-mars', '2026-04-30 17:00:00', 1, 0),
(2, 11, 119, 119, 'Inventaire test Cipro 500 mg', '2026-04-30 17:05:00', 1, 0),
(3, 32, 147, 147, 'Inventaire test perfuseurs', '2026-04-30 17:10:00', 1, 0);

-- =========================================================
-- 15. AUTO_INCREMENT
-- =========================================================

ALTER TABLE `actes_medicaux` AUTO_INCREMENT = 10;
ALTER TABLE `types_carnets` AUTO_INCREMENT = 3;
ALTER TABLE `examens` AUTO_INCREMENT = 19;
ALTER TABLE `produits_pharmacie` AUTO_INCREMENT = 44;
ALTER TABLE `approvisionnements_pharmacie` AUTO_INCREMENT = 13;
ALTER TABLE `patients` AUTO_INCREMENT = 13;
ALTER TABLE `reglements_orphelins` AUTO_INCREMENT = 5;
ALTER TABLE `recus` AUTO_INCREMENT = 30;
ALTER TABLE `lignes_consultation` AUTO_INCREMENT = 11;
ALTER TABLE `lignes_examen` AUTO_INCREMENT = 21;
ALTER TABLE `lignes_pharmacie` AUTO_INCREMENT = 28;
ALTER TABLE `modifications_recus` AUTO_INCREMENT = 3;
ALTER TABLE `inventaire_physique` AUTO_INCREMENT = 4;

SET FOREIGN_KEY_CHECKS = 1;

COMMIT;
