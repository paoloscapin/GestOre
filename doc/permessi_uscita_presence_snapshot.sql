ALTER TABLE `permessi_uscita`
ADD COLUMN `mastercom_presence_stato` VARCHAR(30) NULL AFTER `mastercom_sync_last_error`,
ADD COLUMN `mastercom_presence_label` VARCHAR(100) NULL AFTER `mastercom_presence_stato`,
ADD COLUMN `mastercom_presence_detail` TEXT NULL AFTER `mastercom_presence_label`,
ADD COLUMN `mastercom_presence_at` DATETIME NULL AFTER `mastercom_presence_detail`;
