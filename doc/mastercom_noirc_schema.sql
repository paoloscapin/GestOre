SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `mastercom_noirc_docenti_assegnazioni` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_docente` INT NOT NULL,
  `giorno_settimana` TINYINT NOT NULL,
  `ora` VARCHAR(5) NOT NULL,
  `data_inizio` DATE NOT NULL,
  `data_fine` DATE NOT NULL,
  `aula` VARCHAR(50) NULL,
  `gruppo_label` VARCHAR(20) NOT NULL DEFAULT 'A',
  `classi_incluse` VARCHAR(255) NULL,
  `capienza_massima` INT NULL,
  `note` VARCHAR(255) NULL,
  `attivo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mastercom_noirc_docenti_assegnazioni_docente` (`id_docente`),
  KEY `idx_mastercom_noirc_docenti_assegnazioni_slot` (`giorno_settimana`, `ora`),
  KEY `idx_mastercom_noirc_docenti_assegnazioni_periodo` (`data_inizio`, `data_fine`),
  CONSTRAINT `fk_mastercom_noirc_docenti_assegnazioni_docente`
    FOREIGN KEY (`id_docente`) REFERENCES `docente` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mastercom_noirc_aula_classi` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `giorno_settimana` TINYINT NOT NULL,
  `ora` VARCHAR(5) NOT NULL,
  `classe_label` VARCHAR(30) NOT NULL,
  `aula` VARCHAR(50) NOT NULL,
  `data_inizio` DATE NOT NULL,
  `data_fine` DATE NOT NULL,
  `note` VARCHAR(255) NULL,
  `attivo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mastercom_noirc_aula_classi_slot` (`giorno_settimana`, `ora`),
  KEY `idx_mastercom_noirc_aula_classi_classe` (`classe_label`),
  KEY `idx_mastercom_noirc_aula_classi_periodo` (`data_inizio`, `data_fine`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mastercom_noirc_appelli` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `data_giorno` DATE NOT NULL,
  `giorno_settimana` TINYINT NOT NULL,
  `ora` VARCHAR(5) NOT NULL,
  `id_assegnazione` INT NULL,
  `aula` VARCHAR(50) NULL,
  `created_by_user_id` INT NULL,
  `note` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mastercom_noirc_appelli_slot` (`data_giorno`, `ora`),
  KEY `idx_mastercom_noirc_appelli_aula` (`aula`),
  KEY `idx_mastercom_noirc_appelli_assegnazione` (`id_assegnazione`),
  CONSTRAINT `fk_mastercom_noirc_appelli_assegnazione`
    FOREIGN KEY (`id_assegnazione`) REFERENCES `mastercom_noirc_docenti_assegnazioni` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mastercom_noirc_appello_studenti` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_appello` INT NOT NULL,
  `id_studente_gestore` INT NULL,
  `mastercom_id_studente` INT NULL,
  `stato` VARCHAR(30) NOT NULL DEFAULT 'PRESENTE',
  `note` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mastercom_noirc_appello_studenti` (`id_appello`, `mastercom_id_studente`),
  KEY `idx_mastercom_noirc_appello_studenti_studente_gestore` (`id_studente_gestore`),
  CONSTRAINT `fk_mastercom_noirc_appello_studenti_appello`
    FOREIGN KEY (`id_appello`) REFERENCES `mastercom_noirc_appelli` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mastercom_noirc_appello_studenti_studente_gestore`
    FOREIGN KEY (`id_studente_gestore`) REFERENCES `studente` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
