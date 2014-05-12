/**
* modeles_922.sql
*
* Petite migration pour les modèles de documents
*/

-- Les modèles de documents à charger en BDD.
INSERT INTO `llx_document_model` (`nom`, `type`) VALUES ('codecouleurs', 'propal'), ('codecouleurs', 'invoice');
