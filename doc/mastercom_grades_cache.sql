CREATE TABLE IF NOT EXISTS `mastercom_voti_materie` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `mastercom_id_classe` INT NOT NULL,
  `mastercom_id_materia` INT NOT NULL,
  `materia` VARCHAR(255) NOT NULL,
  `last_sync_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mastercom_voti_materie_classe_materia` (`mastercom_id_classe`, `mastercom_id_materia`),
  KEY `idx_mastercom_voti_materie_classe` (`mastercom_id_classe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mastercom_voti_medie` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `mastercom_id_classe` INT NOT NULL,
  `mastercom_id_materia` INT NOT NULL,
  `mastercom_id_studente` INT NOT NULL,
  `range_start` DATE NOT NULL,
  `range_end` DATE NOT NULL,
  `scritto` VARCHAR(20) NULL,
  `orale` VARCHAR(20) NULL,
  `pratico` VARCHAR(20) NULL,
  `totale` VARCHAR(20) NULL,
  `last_sync_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mastercom_voti_medie` (`mastercom_id_classe`, `mastercom_id_materia`, `mastercom_id_studente`, `range_start`, `range_end`),
  KEY `idx_mastercom_voti_medie_classe_range` (`mastercom_id_classe`, `range_start`, `range_end`),
  KEY `idx_mastercom_voti_medie_studente` (`mastercom_id_studente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mastercom_voti_dettaglio` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_voto` INT NOT NULL,
  `mastercom_id_classe` INT NOT NULL,
  `mastercom_id_materia` INT NOT NULL,
  `mastercom_id_studente` INT NOT NULL,
  `mastercom_id_professore` INT NULL,
  `data_ts` INT NOT NULL,
  `data_giorno` DATE NOT NULL,
  `tipo` INT NOT NULL DEFAULT 0,
  `tipo_aggiuntivo` VARCHAR(100) NULL,
  `voto` VARCHAR(20) NULL,
  `note` TEXT NULL,
  `id_obiettivo` INT NULL,
  `raw_json` LONGTEXT NULL,
  `last_sync_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mastercom_voti_dettaglio_id_voto` (`id_voto`),
  KEY `idx_mastercom_voti_dettaglio_classe_materia_data` (`mastercom_id_classe`, `mastercom_id_materia`, `data_giorno`),
  KEY `idx_mastercom_voti_dettaglio_studente` (`mastercom_id_studente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mastercom_voti_sync_log` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `started_at` DATETIME NOT NULL,
  `finished_at` DATETIME NULL,
  `mastercom_id_classe` INT NULL,
  `mastercom_id_materia` INT NULL,
  `range_start` DATE NOT NULL,
  `range_end` DATE NOT NULL,
  `stato` VARCHAR(30) NOT NULL DEFAULT 'RUNNING',
  `message` TEXT NULL,
  `stats_json` LONGTEXT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mastercom_voti_sync_log_started` (`started_at`),
  KEY `idx_mastercom_voti_sync_log_scope` (`mastercom_id_classe`, `mastercom_id_materia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
