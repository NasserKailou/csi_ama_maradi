USE `directaid`;

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_SAFE_UPDATES = 0;

DELETE FROM `modifications_recus`;

DELETE FROM `lignes_consultation`;
DELETE FROM `lignes_examen`;
DELETE FROM `lignes_pharmacie`;

DELETE FROM `inventaire_physique`;
DELETE FROM `approvisionnements_pharmacie`;

DELETE FROM `recus`;
DELETE FROM `reglements_orphelins`;

DELETE FROM `patients`;

DELETE FROM `produits_pharmacie`;
DELETE FROM `examens`;
DELETE FROM `actes_medicaux`;
DELETE FROM `types_carnets`;

ALTER TABLE `modifications_recus` AUTO_INCREMENT = 1;

ALTER TABLE `lignes_consultation` AUTO_INCREMENT = 1;
ALTER TABLE `lignes_examen` AUTO_INCREMENT = 1;
ALTER TABLE `lignes_pharmacie` AUTO_INCREMENT = 1;

ALTER TABLE `inventaire_physique` AUTO_INCREMENT = 1;
ALTER TABLE `approvisionnements_pharmacie` AUTO_INCREMENT = 1;

ALTER TABLE `recus` AUTO_INCREMENT = 1;
ALTER TABLE `reglements_orphelins` AUTO_INCREMENT = 1;

ALTER TABLE `patients` AUTO_INCREMENT = 1;

ALTER TABLE `produits_pharmacie` AUTO_INCREMENT = 1;
ALTER TABLE `examens` AUTO_INCREMENT = 1;
ALTER TABLE `actes_medicaux` AUTO_INCREMENT = 1;
ALTER TABLE `types_carnets` AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;
