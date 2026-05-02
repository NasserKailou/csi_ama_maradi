-- ═══════════════════════════════════════════════════════════════════════════
-- SCRIPT DE GÉNÉRATION DE DONNÉES DE TEST – Base directaid (v2 corrigée)
-- Période : 01/01/2026 → aujourd'hui
-- ⚠ Exécuter d'abord le script de réinitialisation
-- ═══════════════════════════════════════════════════════════════════════════

SET FOREIGN_KEY_CHECKS = 0;
SET @date_debut = '2026-01-01';
SET @date_fin   = CURDATE();

-- ───────────────────────────────────────────────────────────────────────────
-- 1. PATIENTS
-- ───────────────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS gen_patients;
DELIMITER $$
CREATE PROCEDURE gen_patients()
BEGIN
    DECLARE i INT DEFAULT 1;
    DECLARE v_nom VARCHAR(200);
    DECLARE v_sexe ENUM('M','F');
    DECLARE v_age TINYINT UNSIGNED;
    DECLARE v_prov VARCHAR(150);
    DECLARE v_orph TINYINT(1);
    DECLARE v_tel VARCHAR(20);
    DECLARE v_date DATETIME;

    WHILE i <= 150 DO
        SET v_sexe = IF(RAND() < 0.55, 'F', 'M');
        SET v_nom = ELT(FLOOR(1 + RAND()*20),
            'Issaka Ali','Moussa Sanda','Fatouma Souley','Aïcha Ibrahim','Hassane Adamou',
            'Zeinabou Garba','Abdoul Razak','Mariama Hassan','Salamatou Oumarou','Boubacar Idi',
            'Hadjara Yacouba','Rachida Maman','Souleymane Issa','Amadou Tahirou','Halima Sidi',
            'Nafissa Maïga','Ibrahim Saley','Ramatou Daouda','Karim Abdou','Djamila Harouna');
        SET v_age = FLOOR(1 + RAND()*75);
        SET v_prov = ELT(FLOOR(1 + RAND()*8),
            'Maradi','Maradawa','Tibiri','Madarounfa','Aguié',
            'Dakoro','Guidan-Roumdji','Mayahi');

        IF RAND() < 0.15 THEN
            SET v_orph = 1; SET v_sexe = 'M'; SET v_prov = 'Maradi';
            SET v_age  = FLOOR(3 + RAND()*15);
        ELSE
            SET v_orph = 0;
        END IF;

        SET v_tel = CONCAT(IF(RAND()<0.5,'9','8'), LPAD(FLOOR(RAND()*10000000), 7, '0'));
        SET v_date = TIMESTAMP(
            DATE_ADD(@date_debut, INTERVAL FLOOR(RAND() * DATEDIFF(@date_fin, @date_debut)) DAY),
            SEC_TO_TIME(FLOOR(28800 + RAND()*36000))
        );

        INSERT IGNORE INTO patients (telephone, nom, sexe, age, provenance, est_orphelin, whendone, whodone, lastUpdate)
        VALUES (v_tel, v_nom, v_sexe, v_age, v_prov, v_orph, v_date, 5, v_date);

        SET i = i + 1;
    END WHILE;
END$$
DELIMITER ;
CALL gen_patients();
DROP PROCEDURE gen_patients;

-- ───────────────────────────────────────────────────────────────────────────
-- 2. APPROVISIONNEMENTS PHARMACIE
--    + on AUGMENTE le stock_actuel pour pouvoir absorber 4 mois de ventes
-- ───────────────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS gen_approvisionnements;
DELIMITER $$
CREATE PROCEDURE gen_approvisionnements()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_pid INT UNSIGNED;
    DECLARE v_qte INT UNSIGNED;
    DECLARE cur CURSOR FOR
        SELECT id FROM produits_pharmacie WHERE isDeleted = 0 AND id NOT IN (15,16,17);
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN cur;
    boucle: LOOP
        FETCH cur INTO v_pid;
        IF done THEN LEAVE boucle; END IF;

        -- Approvisionnement initial massif pour absorber les ventes
        SET v_qte = FLOOR(500 + RAND()*1000);
        INSERT INTO approvisionnements_pharmacie (produit_id, quantite, date_appro, commentaire, whendone, whodone, lastUpdate)
        VALUES (v_pid, v_qte, '2026-01-02', 'Stock initial année',
                '2026-01-02 09:00:00', 1, '2026-01-02 09:00:00');
        UPDATE produits_pharmacie SET stock_actuel = stock_actuel + v_qte WHERE id = v_pid;

        -- Approvisionnements mensuels aléatoires
        INSERT INTO approvisionnements_pharmacie (produit_id, quantite, date_appro, commentaire, whendone, whodone, lastUpdate)
        SELECT v_pid,
               FLOOR(50 + RAND()*200),
               d.date_appro,
               'Approvisionnement mensuel',
               TIMESTAMP(d.date_appro, '09:30:00'),
               1,
               TIMESTAMP(d.date_appro, '09:30:00')
        FROM (
            SELECT '2026-02-05' AS date_appro UNION ALL
            SELECT '2026-03-05' UNION ALL
            SELECT '2026-04-05'
        ) d
        WHERE d.date_appro <= @date_fin AND RAND() < 0.7;
    END LOOP;
    CLOSE cur;
END$$
DELIMITER ;
CALL gen_approvisionnements();
DROP PROCEDURE gen_approvisionnements;

-- ───────────────────────────────────────────────────────────────────────────
-- 3. REÇUS + LIGNES
-- ───────────────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS gen_recus;
DELIMITER $$
CREATE PROCEDURE gen_recus()
BEGIN
    DECLARE v_jour DATE DEFAULT @date_debut;
    DECLARE v_nb_jour INT;
    DECLARE i INT;
    DECLARE v_recu_id INT UNSIGNED;
    DECLARE v_num INT UNSIGNED DEFAULT 1;
    DECLARE v_patient_id INT UNSIGNED;
    DECLARE v_type VARCHAR(20);
    DECLARE v_type_pat VARCHAR(20);
    DECLARE v_montant INT UNSIGNED;
    DECLARE v_encaisse INT UNSIGNED;
    DECLARE v_user INT UNSIGNED;
    DECLARE v_dt DATETIME;
    DECLARE v_acte_id INT UNSIGNED;
    DECLARE v_acte_lib VARCHAR(200);
    DECLARE v_acte_tarif INT UNSIGNED;
    DECLARE v_acte_gratuit TINYINT(1);
    DECLARE v_avec_carnet TINYINT(1);
    DECLARE v_orph TINYINT(1);
    DECLARE j INT;
    DECLARE v_nb_lignes INT;
    DECLARE v_examen_id INT UNSIGNED;
    DECLARE v_examen_lib VARCHAR(200);
    DECLARE v_examen_cout INT UNSIGNED;
    DECLARE v_examen_pct DECIMAL(5,2);
    DECLARE v_prod_id INT UNSIGNED;
    DECLARE v_prod_nom VARCHAR(200);
    DECLARE v_prod_forme VARCHAR(50);
    DECLARE v_prod_prix INT UNSIGNED;
    DECLARE v_prod_stock INT UNSIGNED;
    DECLARE v_qte INT UNSIGNED;

    WHILE v_jour <= @date_fin DO
        IF DAYOFWEEK(v_jour) <> 1 THEN
            SET v_nb_jour = FLOOR(12 + RAND()*14);
            SET i = 0;

            WHILE i < v_nb_jour DO
                SELECT id, est_orphelin INTO v_patient_id, v_orph
                FROM patients
                WHERE DATE(whendone) <= v_jour
                ORDER BY RAND() LIMIT 1;

                IF v_patient_id IS NULL THEN
                    SET i = v_nb_jour;
                ELSE
                    SET v_type = ELT(
                        CASE
                            WHEN RAND() < 0.60 THEN 1
                            WHEN RAND() < 0.50 THEN 2
                            ELSE 3
                        END,
                        'consultation','examen','pharmacie');
                    SET v_user = ELT(FLOOR(1 + RAND()*3), 3, 4, 5);
                    SET v_dt = TIMESTAMP(v_jour, SEC_TO_TIME(FLOOR(28800 + RAND()*32400)));

                    -- ───────── CONSULTATION ─────────
                    IF v_type = 'consultation' THEN
                        SELECT id, libelle, tarif, est_gratuit
                          INTO v_acte_id, v_acte_lib, v_acte_tarif, v_acte_gratuit
                        FROM actes_medicaux
                        WHERE isDeleted = 0 AND id BETWEEN 1 AND 9
                        ORDER BY RAND() LIMIT 1;

                        SET v_avec_carnet = IF(RAND() < 0.30 AND v_acte_gratuit = 0, 1, 0);

                        IF v_orph = 1 THEN
                            SET v_type_pat = 'orphelin';
                            SET v_montant  = v_acte_tarif;
                            SET v_encaisse = 0;
                            SET v_avec_carnet = 0;
                        ELSEIF v_acte_gratuit = 1 THEN
                            SET v_type_pat = 'acte_gratuit';
                            SET v_montant  = v_acte_tarif;
                            SET v_encaisse = 0;
                        ELSE
                            SET v_type_pat = 'normal';
                            SET v_montant  = v_acte_tarif + IF(v_avec_carnet=1, 100, 0);
                            SET v_encaisse = v_montant;
                        END IF;

                        INSERT INTO recus (numero_recu, patient_id, type_recu, type_patient,
                                           montant_total, montant_encaisse, whendone, whodone, lastUpdate)
                        VALUES (v_num, v_patient_id, 'consultation', v_type_pat,
                                v_montant, v_encaisse, v_dt, v_user, v_dt);
                        SET v_recu_id = LAST_INSERT_ID();

                        INSERT INTO lignes_consultation (recu_id, acte_id, libelle, tarif, est_gratuit,
                                                         avec_carnet, tarif_carnet, whendone, whodone, lastUpdate)
                        VALUES (v_recu_id, v_acte_id, v_acte_lib, v_acte_tarif,
                                IF(v_orph=1 OR v_acte_gratuit=1,1,0),
                                v_avec_carnet, IF(v_avec_carnet=1,100,0),
                                v_dt, v_user, v_dt);

                    -- ───────── EXAMEN ─────────
                    ELSEIF v_type = 'examen' THEN
                        SET v_type_pat = IF(v_orph = 1, 'orphelin', 'normal');
                        SET v_nb_lignes = FLOOR(1 + RAND()*4);
                        SET v_montant = 0;

                        INSERT INTO recus (numero_recu, patient_id, type_recu, type_patient,
                                           montant_total, montant_encaisse, whendone, whodone, lastUpdate)
                        VALUES (v_num, v_patient_id, 'examen', v_type_pat, 0, 0, v_dt, v_user, v_dt);
                        SET v_recu_id = LAST_INSERT_ID();

                        SET j = 0;
                        WHILE j < v_nb_lignes DO
                            SELECT id, libelle, cout_total, pourcentage_labo
                              INTO v_examen_id, v_examen_lib, v_examen_cout, v_examen_pct
                            FROM examens
                            WHERE isDeleted = 0
                            ORDER BY RAND() LIMIT 1;

                            INSERT INTO lignes_examen (recu_id, examen_id, libelle, cout_total,
                                                       pourcentage_labo, montant_labo,
                                                       whendone, whodone, lastUpdate)
                            VALUES (v_recu_id, v_examen_id, v_examen_lib, v_examen_cout,
                                    v_examen_pct, ROUND(v_examen_cout * v_examen_pct / 100),
                                    v_dt, v_user, v_dt);

                            SET v_montant = v_montant + v_examen_cout;
                            SET j = j + 1;
                        END WHILE;

                        UPDATE recus
                        SET montant_total = v_montant,
                            montant_encaisse = IF(v_orph=1, 0, v_montant)
                        WHERE id = v_recu_id;

                    -- ───────── PHARMACIE (CORRIGÉE) ─────────
                    ELSE
                        SET v_type_pat = IF(v_orph = 1, 'orphelin', 'normal');
                        SET v_nb_lignes = FLOOR(1 + RAND()*4);
                        SET v_montant = 0;

                        INSERT INTO recus (numero_recu, patient_id, type_recu, type_patient,
                                           montant_total, montant_encaisse, whendone, whodone, lastUpdate)
                        VALUES (v_num, v_patient_id, 'pharmacie', v_type_pat, 0, 0, v_dt, v_user, v_dt);
                        SET v_recu_id = LAST_INSERT_ID();

                        SET j = 0;
                        WHILE j < v_nb_lignes DO
                            SET v_qte = FLOOR(1 + RAND()*5);   -- 1..5

                            -- On ne sélectionne QUE des produits qui ont assez de stock
                            SET v_prod_id = NULL;
                            SELECT id, nom, forme, prix_unitaire, stock_actuel
                              INTO v_prod_id, v_prod_nom, v_prod_forme, v_prod_prix, v_prod_stock
                            FROM produits_pharmacie
                            WHERE isDeleted = 0
                              AND id NOT IN (15,16)
                              AND stock_actuel >= v_qte
                            ORDER BY RAND() LIMIT 1;

                            -- Si vraiment aucun produit dispo (improbable), on sort
                            IF v_prod_id IS NULL THEN
                                SET j = v_nb_lignes;
                            ELSE
                                INSERT INTO lignes_pharmacie (recu_id, produit_id, nom, forme, quantite,
                                                              prix_unitaire, total_ligne,
                                                              whendone, whodone, lastUpdate)
                                VALUES (v_recu_id, v_prod_id, v_prod_nom, v_prod_forme, v_qte,
                                        v_prod_prix, v_prod_prix * v_qte,
                                        v_dt, v_user, v_dt);

                                -- Décrément SÉCURISÉ (cast SIGNED pour éviter l'underflow UNSIGNED)
                                UPDATE produits_pharmacie
                                SET stock_actuel = CAST(stock_actuel AS SIGNED) - CAST(v_qte AS SIGNED)
                                WHERE id = v_prod_id AND stock_actuel >= v_qte;

                                SET v_montant = v_montant + v_prod_prix * v_qte;
                                SET j = j + 1;
                            END IF;
                        END WHILE;

                        UPDATE recus
                        SET montant_total = v_montant,
                            montant_encaisse = IF(v_orph=1, 0, v_montant)
                        WHERE id = v_recu_id;
                    END IF;

                    SET v_num = v_num + 1;
                    SET i = i + 1;
                END IF;
            END WHILE;
        END IF;

        SET v_jour = DATE_ADD(v_jour, INTERVAL 1 DAY);
    END WHILE;
END$$
DELIMITER ;
CALL gen_recus();
DROP PROCEDURE gen_recus;

-- ───────────────────────────────────────────────────────────────────────────
-- 4. MODIFICATIONS DE REÇUS (~3%)
-- ───────────────────────────────────────────────────────────────────────────
INSERT INTO modifications_recus (recu_id, user_id, type_recu, motif, detail_avant, detail_apres, whendone)
SELECT r.id, r.whodone, r.type_recu,
       ELT(FLOOR(1 + RAND()*4),
           'Erreur de saisie (montant)','Quantité incorrecte',
           'Erreur de saisie (patient)','Erreur produit/examen sélectionné'),
       JSON_OBJECT('montant_total', r.montant_total, 'montant_encaisse', r.montant_encaisse),
       JSON_OBJECT('montant_total', r.montant_total, 'montant_encaisse', r.montant_encaisse),
       DATE_ADD(r.whendone, INTERVAL FLOOR(10 + RAND()*120) MINUTE)
FROM recus r
WHERE RAND() < 0.03;

SET FOREIGN_KEY_CHECKS = 1;

-- ═══════════════════════════════════════════════════════════════════════════
-- VÉRIFICATIONS
-- ═══════════════════════════════════════════════════════════════════════════
SELECT 'patients' AS t, COUNT(*) nb FROM patients
UNION ALL SELECT 'recus', COUNT(*) FROM recus
UNION ALL SELECT 'lignes_consultation', COUNT(*) FROM lignes_consultation
UNION ALL SELECT 'lignes_examen', COUNT(*) FROM lignes_examen
UNION ALL SELECT 'lignes_pharmacie', COUNT(*) FROM lignes_pharmacie
UNION ALL SELECT 'approvisionnements_pharmacie', COUNT(*) FROM approvisionnements_pharmacie
UNION ALL SELECT 'modifications_recus', COUNT(*) FROM modifications_recus;

SELECT DATE_FORMAT(whendone,'%Y-%m') AS mois, type_recu,
       COUNT(*) nb_recus, SUM(montant_total) total_facture, SUM(montant_encaisse) total_encaisse
FROM recus GROUP BY mois, type_recu ORDER BY mois, type_recu;
