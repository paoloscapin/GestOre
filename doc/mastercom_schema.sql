SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `mastercom_studenti` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_studente_gestore` INT NULL,
  `mastercom_id_studente` INT NOT NULL,
  `mastercom_id_classe_corrente` INT NULL,
  `registro_numero` INT NULL,
  `cognome` VARCHAR(100) NULL,
  `nome` VARCHAR(100) NULL,
  `codice_fiscale` VARCHAR(32) NULL,
  `data_nascita_ts` INT NULL,
  `data_nascita` DATE NULL,
  `sesso` VARCHAR(10) NULL,
  `email1` VARCHAR(255) NULL,
  `email2` VARCHAR(255) NULL,
  `foto` VARCHAR(255) NULL,
  `classe_numero` INT NULL,
  `sezione` VARCHAR(20) NULL,
  `codice_indirizzo` VARCHAR(50) NULL,
  `descrizione_indirizzo` VARCHAR(255) NULL,
  `tipo_indirizzo` INT NULL,
  `ordinamento` INT NULL,
  `esonero_religione` TINYINT(1) NULL,
  `descrizione_materia_integrativa` VARCHAR(255) NULL,
  `esonero_ed_fisica` TINYINT(1) NULL,
  `servizio_mensa` TINYINT(1) NULL,
  `necessita_sostegno` TINYINT(1) NULL,
  `esito` VARCHAR(100) NULL,
  `esito_corrente_calcolato` VARCHAR(100) NULL,
  `data_inizio_partecipazione_ts` INT NULL,
  `data_fine_partecipazione_ts` INT NULL,
  `attivo_mastercom` TINYINT(1) NOT NULL DEFAULT 1,
  `last_sync_at` DATETIME NULL,
  `last_seen_at` DATETIME NULL,
  `raw_json` LONGTEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mastercom_studenti_mastercom_id` (`mastercom_id_studente`),
  KEY `idx_mastercom_studenti_gestore` (`id_studente_gestore`),
  CONSTRAINT `fk_mastercom_studenti_studente`
    FOREIGN KEY (`id_studente_gestore`) REFERENCES `studente` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mastercom_genitori` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_genitore_gestore` INT NULL,
  `mastercom_id_parente` INT NOT NULL,
  `cognome` VARCHAR(100) NULL,
  `nome` VARCHAR(100) NULL,
  `codice_fiscale` VARCHAR(32) NULL,
  `email` VARCHAR(255) NULL,
  `telefono` VARCHAR(100) NULL,
  `cellulare` VARCHAR(100) NULL,
  `indirizzo` VARCHAR(255) NULL,
  `cap` VARCHAR(20) NULL,
  `citta` VARCHAR(100) NULL,
  `provincia` VARCHAR(20) NULL,
  `comune_nascita` VARCHAR(100) NULL,
  `data_nascita_ts` INT NULL,
  `data_nascita` DATE NULL,
  `attivo_mastercom` TINYINT(1) NOT NULL DEFAULT 1,
  `last_sync_at` DATETIME NULL,
  `last_seen_at` DATETIME NULL,
  `raw_json` LONGTEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mastercom_genitori_mastercom_id` (`mastercom_id_parente`),
  KEY `idx_mastercom_genitori_gestore` (`id_genitore_gestore`),
  CONSTRAINT `fk_mastercom_genitori_genitori`
    FOREIGN KEY (`id_genitore_gestore`) REFERENCES `genitori` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mastercom_docenti` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_docente_gestore` INT NULL,
  `mastercom_id_user` INT NOT NULL,
  `nome_visualizzato` VARCHAR(255) NULL,
  `tipo_utente` VARCHAR(50) NULL,
  `attivo_mastercom` TINYINT(1) NOT NULL DEFAULT 1,
  `last_sync_at` DATETIME NULL,
  `last_seen_at` DATETIME NULL,
  `raw_json` LONGTEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mastercom_docenti_mastercom_id` (`mastercom_id_user`),
  KEY `idx_mastercom_docenti_gestore` (`id_docente_gestore`),
  CONSTRAINT `fk_mastercom_docenti_docente`
    FOREIGN KEY (`id_docente_gestore`) REFERENCES `docente` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mastercom_classi` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_classe_gestore` INT NULL,
  `mastercom_id_classe` INT NOT NULL,
  `nome` VARCHAR(255) NULL,
  `classe_numero` INT NULL,
  `sezione` VARCHAR(20) NULL,
  `codice_indirizzo` VARCHAR(50) NULL,
  `descrizione_indirizzo` VARCHAR(255) NULL,
  `anno_scolastico` VARCHAR(20) NULL,
  `attiva_mastercom` TINYINT(1) NOT NULL DEFAULT 1,
  `last_sync_at` DATETIME NULL,
  `last_seen_at` DATETIME NULL,
  `raw_json` LONGTEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mastercom_classi_mastercom_id` (`mastercom_id_classe`),
  KEY `idx_mastercom_classi_gestore` (`id_classe_gestore`),
  CONSTRAINT `fk_mastercom_classi_classi`
    FOREIGN KEY (`id_classe_gestore`) REFERENCES `classi` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mastercom_studenti_classi` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `mastercom_id_studente` INT NOT NULL,
  `mastercom_id_classe` INT NOT NULL,
  `anno_scolastico` VARCHAR(20) NULL,
  `classe_numero` INT NULL,
  `sezione` VARCHAR(20) NULL,
  `codice_indirizzo` VARCHAR(50) NULL,
  `descrizione_indirizzo` VARCHAR(255) NULL,
  `esito` VARCHAR(100) NULL,
  `data_inizio_partecipazione_ts` INT NULL,
  `data_fine_partecipazione_ts` INT NULL,
  `last_sync_at` DATETIME NULL,
  `raw_json` LONGTEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mastercom_studenti_classi` (`mastercom_id_studente`, `mastercom_id_classe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mastercom_genitori_studenti` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `mastercom_id_parente` INT NOT NULL,
  `mastercom_id_studente` INT NOT NULL,
  `id_genitore_gestore` INT NULL,
  `id_studente_gestore` INT NULL,
  `relazione` VARCHAR(50) NULL,
  `source_mastercom` VARCHAR(50) NULL DEFAULT 'mastercom',
  `last_sync_at` DATETIME NULL,
  `raw_json` LONGTEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mastercom_genitori_studenti` (`mastercom_id_parente`, `mastercom_id_studente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
