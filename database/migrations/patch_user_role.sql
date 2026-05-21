ALTER TABLE `utilisateurs`
    MODIFY COLUMN `role` ENUM('admin','comptable','percepteur','major')
        NOT NULL DEFAULT 'percepteur';
