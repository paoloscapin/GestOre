SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `mastercom_tag_stampe` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `anno_scolastico_id` INT NULL,
  `data_inizio` DATE NULL,
  `data_fine` DATE NULL,
  `classi_label` VARCHAR(255) NULL,
  `docente_label` VARCHAR(255) NULL,
  `tag_label` TEXT NULL,
  `source_filename` VARCHAR(255) NULL,
  `source_hash` CHAR(40) NULL,
  `created_by` INT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mastercom_tag_stampe_created_at` (`created_at`),
  KEY `idx_mastercom_tag_stampe_anno` (`anno_scolastico_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mastercom_tag_righe` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `stampa_id` INT NOT NULL,
  `data_ora` DATETIME NULL,
  `tag` VARCHAR(120) NULL,
  `docente` VARCHAR(255) NULL,
  `materia` VARCHAR(255) NULL,
  `classe` VARCHAR(255) NULL,
  `argomento` TEXT NULL,
  `modulo` TEXT NULL,
  `row_hash` CHAR(40) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mastercom_tag_righe_stampa_hash` (`stampa_id`, `row_hash`),
  KEY `idx_mastercom_tag_righe_stampa` (`stampa_id`),
  KEY `idx_mastercom_tag_righe_data` (`data_ora`),
  KEY `idx_mastercom_tag_righe_tag` (`tag`),
  CONSTRAINT `fk_mastercom_tag_righe_stampa`
    FOREIGN KEY (`stampa_id`) REFERENCES `mastercom_tag_stampe` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
