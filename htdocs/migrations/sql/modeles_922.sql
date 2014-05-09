/**
 * modeles_922.sql
 * 
 * Petite migration pour les modèles de documents
 */

-- Un bugfix perso de Dolibarr.
ALTER TABLE IF EXISTS `llx_societe` ADD COLUMN IF NOT EXISTS (`datea` DATETIME DEFAULT NULL);

-- Les modèles de documents à charger en BDD.
INSERT INTO `llx_document_model` (`nom`, `type`) VALUES ('codecouleurs', 'propal'), ('codecouleurs', 'invoice');
