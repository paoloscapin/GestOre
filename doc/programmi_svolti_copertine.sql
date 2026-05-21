CREATE TABLE IF NOT EXISTS `programmi_svolti_copertine` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_programma_svolto` INT NOT NULL,
  `id_anno_scolastico` INT NOT NULL,
  `stato` VARCHAR(30) NOT NULL DEFAULT 'RICHIESTA',
  `fascicolo_codice` VARCHAR(30) NULL,
  `fascicolo_numero` INT NULL,
  `fascicolo_anno` INT NULL,
  `file_name` VARCHAR(255) NULL,
  `drive_file_id` VARCHAR(255) NULL,
  `drive_web_view_link` TEXT NULL,
  `requested_by_user_id` INT NULL,
  `requested_at` DATETIME NOT NULL,
  `generated_by_user_id` INT NULL,
  `generated_at` DATETIME NULL,
  `printed_by_user_id` INT NULL,
  `printed_at` DATETIME NULL,
  `error_message` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_programmi_svolti_copertine_programma` (`id_programma_svolto`),
  UNIQUE KEY `uk_programmi_svolti_copertine_codice` (`fascicolo_codice`),
  KEY `idx_programmi_svolti_copertine_stato` (`stato`),
  KEY `idx_programmi_svolti_copertine_anno` (`id_anno_scolastico`),
  KEY `idx_programmi_svolti_copertine_progressivo` (`fascicolo_anno`, `fascicolo_numero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `programmi_svolti_copertine`
  ADD COLUMN IF NOT EXISTS `printed_by_user_id` INT NULL AFTER `generated_at`,
  ADD COLUMN IF NOT EXISTS `printed_at` DATETIME NULL AFTER `printed_by_user_id`;
