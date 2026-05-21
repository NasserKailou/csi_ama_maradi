-- ═══════════════════════════════════════════════════════════════════
-- Migration : Distinction Carnet de Soins / Carnet de Santé
-- Date     : 2026-05-20
-- ═══════════════════════════════════════════════════════════════════
START TRANSACTION;

-- ── 1. Ajouter colonne type_carnet dans mouvements_carnets ─────────
ALTER TABLE `mouvements_carnets`
  ADD COLUMN `type_carnet` ENUM('soins','sante') NOT NULL DEFAULT 'soins'
  AFTER `type_mvt`,
  ADD KEY `idx_mvt_carnet_type` (`type_carnet`);

-- ── 2. Migration des données existantes ────────────────────────────
-- Tous les mouvements liés à un reçu acte_gratuit deviennent 'sante'
UPDATE `mouvements_carnets` mc
INNER JOIN `recus` r ON r.id = mc.recu_id
SET mc.type_carnet = 'sante'
WHERE r.type_patient = 'acte_gratuit';

-- Les autres restent 'soins' (par défaut)

-- ── 3. Initialisation des nouvelles clés config_systeme ────────────
-- Récupérer l'ancien stock global pour le ventiler
SET @ancien_stock := (SELECT CAST(valeur AS UNSIGNED) FROM config_systeme WHERE cle='stock_carnets' LIMIT 1);
SET @ancien_seuil := (SELECT CAST(valeur AS UNSIGNED) FROM config_systeme WHERE cle='seuil_alerte_carnets' LIMIT 1);

-- Calculer ce qui a été consommé par type depuis les mouvements
SET @sorties_sante := (SELECT COALESCE(ABS(SUM(quantite)),0) FROM mouvements_carnets WHERE type_mvt='sortie' AND type_carnet='sante');
SET @sorties_soins := (SELECT COALESCE(ABS(SUM(quantite)),0) FROM mouvements_carnets WHERE type_mvt='sortie' AND type_carnet='soins');

-- Insérer les nouvelles clés (l'ancien stock global est attribué aux carnets de soins par défaut)
INSERT INTO `config_systeme` (`cle`, `valeur`, `whodone`) VALUES
  ('stock_carnets_soins',         IFNULL(@ancien_stock, 0),  1),
  ('seuil_alerte_carnets_soins',  IFNULL(@ancien_seuil, 10), 1),
  ('stock_carnets_sante',         0,                          1),
  ('seuil_alerte_carnets_sante',  IFNULL(@ancien_seuil, 10), 1)
ON DUPLICATE KEY UPDATE valeur = valeur;

-- ── 4. (Optionnel) Marquer l'ancienne clé comme dépréciée ──────────
-- On la conserve pour compatibilité descendante mais on n'écrira plus dessus
-- UPDATE config_systeme SET cle = 'stock_carnets_DEPRECATED' WHERE cle = 'stock_carnets';

COMMIT;
