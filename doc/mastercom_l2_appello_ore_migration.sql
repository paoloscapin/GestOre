ALTER TABLE `mastercom_l2_appello_studenti`
ADD COLUMN `ora_inizio` TIME NULL AFTER `mastercom_id_studente`;

ALTER TABLE `mastercom_l2_appello_studenti`
DROP KEY `uk_mastercom_l2_appello_studente`;

ALTER TABLE `mastercom_l2_appello_studenti`
ADD UNIQUE KEY `uk_mastercom_l2_appello_studente_ora` (`id_appello`, `mastercom_id_studente`, `ora_inizio`);
