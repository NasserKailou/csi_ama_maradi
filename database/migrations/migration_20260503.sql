-- 1) Autoriser NULL sur acte_id (consultation et observation n'ont pas d'acte associé)
ALTER TABLE lignes_consultation MODIFY acte_id INT NULL;

-- 2) Ajouter une colonne type_ligne pour tracer la nature de chaque ligne
ALTER TABLE lignes_consultation 
ADD COLUMN type_ligne ENUM('consultation','observation','carnet','redevance','acte_gratuit','autre') 
NOT NULL DEFAULT 'consultation' AFTER acte_id;

-- 3) Index pour les requêtes de situation par période
ALTER TABLE lignes_consultation ADD INDEX idx_type_ligne (type_ligne);