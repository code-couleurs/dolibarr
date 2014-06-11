/*
 * evall.sql
 * 
 * Récapitulatif de toutes les migrations en lien avec les évolutions du 
 * Dolibarr de Code Couleurs faites en Juin-Juillet 2014.
 */

-- Un titre pour le devis (#1000)
ALTER TABLE `llx_propal` ADD COLUMN `titre` VARCHAR(128) DEFAULT NULL;

-- Des mentions spécifiques pour le client (#1001)
--ALTER TABLE `llx_propal` ADD COLUMN `mentions_client` VARCHAR(65536) DEFAULT NULL;
