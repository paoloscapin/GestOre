ALTER TABLE `permessi_uscita`
ADD COLUMN `mastercom_sync_stato` VARCHAR(30) NULL AFTER `note_segreteria`,
ADD COLUMN `mastercom_sync_at` DATETIME NULL AFTER `mastercom_sync_stato`,
ADD COLUMN `mastercom_sync_attempts` INT NOT NULL DEFAULT 0 AFTER `mastercom_sync_at`,
ADD COLUMN `mastercom_sync_last_note` TEXT NULL AFTER `mastercom_sync_attempts`,
ADD COLUMN `mastercom_sync_last_error` TEXT NULL AFTER `mastercom_sync_last_note`,
ADD COLUMN `mastercom_presence_stato` VARCHAR(30) NULL AFTER `mastercom_sync_last_error`,
ADD COLUMN `mastercom_presence_label` VARCHAR(100) NULL AFTER `mastercom_presence_stato`,
ADD COLUMN `mastercom_presence_detail` TEXT NULL AFTER `mastercom_presence_label`,
ADD COLUMN `mastercom_presence_at` DATETIME NULL AFTER `mastercom_presence_detail`;

CREATE INDEX `idx_permessi_uscita_mastercom_sync`
ON `permessi_uscita` (`data`, `stato`, `mastercom_sync_stato`);
