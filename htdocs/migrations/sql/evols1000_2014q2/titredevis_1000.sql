/*
 * titredevis_1000.sql
 * 
 * Migration pour l'ajout d'un titre dans les devis
 */

ALTER TABLE `llx_propal` ADD COLUMN `titre` VARCHAR(128) DEFAULT NULL;
