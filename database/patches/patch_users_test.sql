-- =============================================================================
-- PATCH SQL – Comptes utilisateurs de test
-- Système CSI AMA Maradi
-- =============================================================================
-- Exécuter ce script dans phpMyAdmin (base : csi_ama)
-- OU via la ligne de commande MySQL :
--   mysql -u root csi_ama < patch_users_test.sql
--
-- Ce script supprime les éventuels comptes existants et les recrée
-- avec des mots de passe hachés (BCRYPT cost=12).
--
-- ┌─────────────────┬──────────────────────┬──────────────┐
-- │ Login           │ Mot de passe         │ Rôle         │
-- ├─────────────────┼──────────────────────┼──────────────┤
-- │ admin           │ Admin@CSI2026        │ admin        │
-- │ comptable       │ Compta@CSI2026       │ comptable    │
-- │ percepteur1     │ Percep1@CSI2026      │ percepteur   │
-- │ percepteur2     │ Percep2@CSI2026      │ percepteur   │
-- └─────────────────┴──────────────────────┴──────────────┘
-- =============================================================================

USE `csi_ama`;

-- Désactiver temporairement les FK pour éviter les erreurs d'intégrité
SET FOREIGN_KEY_CHECKS = 0;

-- Supprimer les anciens comptes de test (si existants)
DELETE FROM `utilisateurs`
WHERE `login` IN ('admin', 'comptable', 'percepteur1', 'percepteur2');

-- Réinitialiser l'auto-increment (optionnel, si base vide)
-- ALTER TABLE `utilisateurs` AUTO_INCREMENT = 1;

-- Insérer les comptes de test avec hashes BCRYPT (cost=12)
-- Note : Les hashes $2b$ (Python bcrypt) sont compatibles avec PHP password_verify()
--        car PHP reconnaît $2b$ et $2y$ comme équivalents.

INSERT INTO `utilisateurs`
    (`nom`, `prenom`, `login`, `password`, `role`, `est_actif`, `whodone`, `isDeleted`)
VALUES
    -- Admin / Admin@CSI2026
    ('Admin', 'CSI', 'admin',
     '$2b$12$ZFL8oJS1PcyaLRMinoz/Be0wZjHso46Am/HNHTYX4kkrw4Z/3oR9.',
     'admin', 1, 1, 0),

    -- Comptable / Compta@CSI2026
    ('Comptable', 'CSI', 'comptable',
     '$2b$12$evFhfalrJbYhLak4.vpkzO9/FavYsnUw1axvxvPZ1bAXWxw8xKxfC',
     'comptable', 1, 1, 0),

    -- Percepteur 1 / Percep1@CSI2026
    ('Percepteur', 'Un', 'percepteur1',
     '$2b$12$QsbqT1bajUMQsaVZkv60HOV9vBQQ.dv4sllUTk5zdPepORv6iyerm',
     'percepteur', 1, 1, 0),

    -- Percepteur 2 / Percep2@CSI2026
    ('Percepteur', 'Deux', 'percepteur2',
     '$2b$12$VG5tf3CFIoF4S/a7XxthYe28DBfjcAmdp7nqEPLeOmnyL3WVlcsmi',
     'percepteur', 1, 1, 0);

-- Réactiver les FK
SET FOREIGN_KEY_CHECKS = 1;

-- Vérification : afficher les comptes créés
SELECT `id`, `nom`, `prenom`, `login`, `role`, `est_actif`,
       SUBSTRING(`password`, 1, 20) AS `hash_debut`
FROM `utilisateurs`
WHERE `login` IN ('admin', 'comptable', 'percepteur1', 'percepteur2')
ORDER BY `id`;

-- =============================================================================
-- FIN DU PATCH
-- =============================================================================
