-- ============================================================
-- Seeder – Données initiales et comptes de test
-- ============================================================
USE csi_ama;

-- ─────────────────────────────────────────────────────────────
-- Comptes utilisateurs de test
-- Mots de passe hachés BCRYPT (cost=12)
-- admin123    → $2y$12$... (généré à l'installation)
-- Pour régénérer : php -r "echo password_hash('admin123', PASSWORD_BCRYPT, ['cost'=>12]);"
-- ─────────────────────────────────────────────────────────────

-- Le hash sera inséré par le script PHP d'installation
-- ci-dessous les valeurs en clair pour documentation :
-- admin     / Admin@CSI2026
-- comptable / Compta@CSI2026
-- percepteur1 / Percep1@CSI2026
-- percepteur2 / Percep2@CSI2026

-- ─────────────────────────────────────────────────────────────
-- Config système (valeurs par défaut)
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO config_systeme (cle, valeur, whodone) VALUES
('nom_centre',      'Centre de Santé Intégré AMA Maradi', 0),
('adresse',         'B.P. XXX, Maradi – Niger', 0),
('telephone',       '+227 XX XX XX XX', 0),
('logo_filename',   '', 0),
('pied_de_page',    'Merci de votre visite. Votre santé est notre priorité.', 0);

-- ─────────────────────────────────────────────────────────────
-- Actes médicaux pré-configurés obligatoires
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO actes_medicaux (libelle, tarif, est_gratuit, whodone) VALUES
('Consultation Générale',           300,  0, 0),
('Consultation Prénatale (CPN)',     300,  1, 0),
('Consultation Nourrissons',         300,  1, 0),
('Accouchement',                    300,  1, 0),
('Planning Familial',               300,  1, 0),
('Consultation Pédiatrique',        300,  0, 0),
('Consultation d\'urgence',         300,  0, 0);

-- ─────────────────────────────────────────────────────────────
-- Types de carnets
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO types_carnets (libelle, tarif, est_gratuit, whodone) VALUES
('Carnet de Soins',   100, 0, 0),
('Carnet de Santé',   0,   1, 0);

-- ─────────────────────────────────────────────────────────────
-- Examens de référence
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO examens (libelle, cout_total, pourcentage_labo, whodone) VALUES
('Numération Formule Sanguine (NFS)',   2500, 30.00, 0),
('Glycémie',                            1500, 30.00, 0),
('Test de Dépistage Paludisme (TDR)',   1000, 30.00, 0),
('Bilan Hépatique',                     4000, 30.00, 0),
('ECBU',                                2000, 30.00, 0),
('Créatininémie',                       1500, 30.00, 0),
('Transaminases ALAT/ASAT',            3000, 30.00, 0),
('Groupage Sanguin',                   1500, 30.00, 0),
('Test de grossesse',                  1000, 30.00, 0),
('VIH (Screening)',                    1500, 30.00, 0);

-- ─────────────────────────────────────────────────────────────
-- Produits pharmacie de démonstration
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO produits_pharmacie (nom, forme, prix_unitaire, stock_initial, stock_actuel, seuil_alerte, date_peremption, whodone) VALUES
('Paracétamol 500mg',         'comprimé',  25,   500, 480, 50, '2027-12-31', 0),
('Amoxicilline 500mg',        'gélule',    75,   200, 190, 30, '2026-12-31', 0),
('Ibuprofène 400mg',          'comprimé',  50,   300, 295, 30, '2027-06-30', 0),
('Artémisinine 80mg',         'comprimé',  200,  100,  98, 20, '2026-09-30', 0),
('Sulfate de Zinc 20mg',      'comprimé',  30,   400, 385, 40, '2027-03-31', 0),
('Métronidazole 250mg',       'comprimé',  40,   200, 196, 25, '2026-08-31', 0),
('Sirop Toux Enfant',         'sirop',     500,   50,  45, 10, '2026-06-30', 0),
('Sérum de Réhydratation ORS','solution',  150,  100,  98, 20, '2027-01-31', 0),
('Vitamine C 1g',             'comprimé',  20,   300, 298, 30, '2027-12-31', 0),
('Fer + Acide Folique',       'comprimé',  15,   400, 394, 50, '2027-06-30', 0),
('Produit périmé (test)',     'comprimé',  50,    10,   5, 10, '2024-01-01', 0),
('Produit en rupture (test)', 'sirop',     200,    0,   0, 10, '2027-12-31', 0);
