-- ============================================================
-- Seeder – Données initiales + Comptes de test
-- Système CSI AMA Maradi v1.0
-- ============================================================
USE `csi_ama`;

-- ─────────────────────────────────────────────────────────────
-- Configuration système par défaut
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO `config_systeme` (`cle`, `valeur`, `whodone`) VALUES
('nom_centre',    'Centre de Santé Intégré AMA Maradi',          0),
('adresse',       'B.P. XXX, Maradi – République du Niger',       0),
('telephone',     '+227 20 XX XX XX',                             0),
('logo_filename', 'logo_csi.png',                                 0),
('pied_de_page',  'Merci de votre visite. Votre santé est notre priorité.', 0);

-- ─────────────────────────────────────────────────────────────
-- Actes médicaux configurés
-- est_gratuit=1 → CPN, Nourrissons, Planning Familial, Accouchement
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO `actes_medicaux` (`libelle`, `tarif`, `est_gratuit`, `whodone`) VALUES
('Consultation Générale',           300, 0, 0),
('Consultation Prénatale (CPN)',     300, 1, 0),
('Consultation Nourrissons',        300, 1, 0),
('Accouchement',                    300, 1, 0),
('Planning Familial',               300, 1, 0),
('Consultation Pédiatrique',        300, 0, 0),
('Consultation d\'urgence',         300, 0, 0),
('Visite Domicile',                 500, 0, 0),
('Renouvellement Ordonnance',       200, 0, 0);

-- ─────────────────────────────────────────────────────────────
-- Types de carnets
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO `types_carnets` (`libelle`, `tarif`, `est_gratuit`, `whodone`) VALUES
('Carnet de Soins',   100, 0, 0),
('Carnet de Santé',     0, 1, 0);

-- ─────────────────────────────────────────────────────────────
-- Examens laboratoire (pourcentage laborantin 30%)
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO `examens` (`libelle`, `cout_total`, `pourcentage_labo`, `whodone`) VALUES
('Numération Formule Sanguine (NFS)',    2500, 30.00, 0),
('Glycémie à jeun',                     1500, 30.00, 0),
('Test de Dépistage Paludisme (TDR)',    1000, 30.00, 0),
('Bilan Hépatique complet',             4000, 30.00, 0),
('ECBU (Examen Cytobactériologique)',   2000, 30.00, 0),
('Créatininémie',                       1500, 30.00, 0),
('Transaminases ALAT/ASAT',            3000, 30.00, 0),
('Groupage Sanguin + Rhésus',          1500, 30.00, 0),
('Test de grossesse (β-HCG)',           1000, 30.00, 0),
('VIH (Screening ELISA)',               1500, 30.00, 0),
('Goutte épaisse (Plasmodium)',         1000, 30.00, 0),
('Urée sanguine',                       1500, 30.00, 0),
('Protéinurie de Bence-Jones',         2000, 30.00, 0),
('Sérologie Hépatite B (HBs Ag)',      2500, 30.00, 0),
('Ionogramme (Na, K, Cl)',             3500, 30.00, 0);

-- ─────────────────────────────────────────────────────────────
-- Produits pharmacie – stock démonstration
-- Inclut 1 produit périmé et 1 en rupture pour tester les alertes
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO `produits_pharmacie`
    (`nom`, `forme`, `prix_unitaire`, `stock_initial`, `stock_actuel`, `seuil_alerte`, `date_peremption`, `whodone`)
VALUES
('Paracétamol 500mg',           'comprimé',  25,   500, 480, 50, '2027-12-31', 0),
('Amoxicilline 500mg',          'gélule',    75,   200, 190, 30, '2026-12-31', 0),
('Ibuprofène 400mg',            'comprimé',  50,   300, 295, 30, '2027-06-30', 0),
('Artésunate 200mg',            'comprimé',  350,  100,  95, 20, '2026-09-30', 0),
('Sulfate de Zinc 20mg',        'comprimé',  30,   400, 385, 40, '2027-03-31', 0),
('Métronidazole 250mg',         'comprimé',  40,   200, 196, 25, '2026-08-31', 0),
('Sirop Toux Enfant',           'sirop',    500,    50,  45, 10, '2026-06-30', 0),
('SRO (Sels Réhydratation)',    'solution', 150,   100,  98, 20, '2027-01-31', 0),
('Vitamine C 1g',               'comprimé',  20,   300, 298, 30, '2027-12-31', 0),
('Fer + Acide Folique',         'comprimé',  15,   400, 394, 50, '2027-06-30', 0),
('Albendazole 400mg',           'comprimé',  60,   150, 140, 20, '2026-10-31', 0),
('Ciprofloxacine 500mg',        'comprimé', 120,   100,  90, 15, '2026-12-31', 0),
('Cétrizine 10mg',              'comprimé',  35,   200, 195, 20, '2027-09-30', 0),
('Quinine 300mg',               'comprimé', 180,    80,  75, 15, '2026-11-30', 0),
('Produit périmé (test)',        'comprimé',  50,    10,   5, 10, '2024-01-01', 0),
('Produit en rupture (test)',    'sirop',    200,     0,   0, 10, '2027-12-31', 0),
('Stock faible (test)',         'ampoule',  450,    15,   8, 10, '2026-07-31', 0);
