-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='TRADITIONAL,ALLOW_INVALID_DATES';

-- -----------------------------------------------------
-- Schema GestOre
-- -----------------------------------------------------
-- gestionale

-- -----------------------------------------------------
-- Table `docente`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `docente` ;

CREATE TABLE IF NOT EXISTS `docente` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `codice_istituto` VARCHAR(45) NULL,
  `cognome` VARCHAR(45) NULL,
  `nome` VARCHAR(45) NULL,
  `email` VARCHAR(45) NULL,
  `username` VARCHAR(45) NULL,
  `matricola` VARCHAR(45) NULL,
  `attivo` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  INDEX `cognome_nome_INDEX` (`cognome` ASC, `nome` ASC),
  INDEX `attivo_INDEX` (`attivo` ASC),
  UNIQUE INDEX `username_UNIQUE` (`username` ASC),
  INDEX `codice_istituto_index` (`codice_istituto` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `materia`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `materia` ;

CREATE TABLE IF NOT EXISTS `materia` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(45) NULL,
  `descrizione` TEXT NULL,
  `codice` VARCHAR(45) NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `nome_UNIQUE` (`nome` ASC),
  UNIQUE INDEX `codice_UNIQUE` (`codice` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `anno_scolastico`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `anno_scolastico` ;

CREATE TABLE IF NOT EXISTS `anno_scolastico` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `anno` VARCHAR(20) NULL,
  PRIMARY KEY (`id`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `profilo_docente`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `profilo_docente` ;

CREATE TABLE IF NOT EXISTS `profilo_docente` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `classe_di_concorso` VARCHAR(45) NULL,
  `tipo_di_contratto` VARCHAR(45) NULL,
  `giorni_di_servizio` INT NULL,
  `ore_di_cattedra` DOUBLE NULL,
  `ore_eccedenti` DOUBLE NULL,
  `note` VARCHAR(200) NULL,
  `ore_dovute_70_con_studenti` INT NULL DEFAULT 0,
  `ore_dovute_70_funzionali` INT NULL DEFAULT 0,
  `ore_dovute_40` INT NULL DEFAULT 0,
  `ore_dovute_totale` INT NULL DEFAULT 0 COMMENT 'somma di\nore_dovute_70_con_studenti +\nore_dovute_70_funzionali +\nore_dovute_40',
  `ore_dovute_supplenze` INT NULL DEFAULT 0,
  `ore_dovute_aggiornamento` INT NULL DEFAULT 0,
  `ore_dovute_totale_con_studenti` INT NULL DEFAULT 0,
  `ore_dovute_totale_funzionali` INT NULL DEFAULT 0,
  `docente_id` INT NOT NULL,
  `anno_scolastico_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `tipo_di_contratto` (`tipo_di_contratto` ASC),
  INDEX `fk_profilo_docente_docente1_idx` (`docente_id` ASC),
  INDEX `fk_profilo_docente_anno_scolastico1_idx` (`anno_scolastico_id` ASC),
  CONSTRAINT `fk_profilo_docente_docente1`
    FOREIGN KEY (`docente_id`)
    REFERENCES `docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_profilo_docente_anno_scolastico1`
    FOREIGN KEY (`anno_scolastico_id`)
    REFERENCES `anno_scolastico` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `anno_scolastico_corrente`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `anno_scolastico_corrente` ;

CREATE TABLE IF NOT EXISTS `anno_scolastico_corrente` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `anno` VARCHAR(20) NULL,
  `anno_scorso_id` INT NULL,
  `anno_scolastico_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_anno_scolastico_corrente_anno_scolastico1_idx` (`anno_scolastico_id` ASC),
  CONSTRAINT `fk_anno_scolastico_corrente_anno_scolastico1`
    FOREIGN KEY (`anno_scolastico_id`)
    REFERENCES `anno_scolastico` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `corso_di_recupero`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `corso_di_recupero` ;

CREATE TABLE IF NOT EXISTS `corso_di_recupero` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `in_itinere` TINYINT NULL DEFAULT 0,
  `codice` VARCHAR(45) NULL,
  `aula` VARCHAR(45) NULL,
  `numero_ore` INT NULL,
  `ore_pagamento_extra` INT NULL DEFAULT 0,
  `ore_recuperate` INT NULL DEFAULT 0,
  `materia_id` INT NOT NULL,
  `anno_scolastico_id` INT NOT NULL,
  `docente_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_corso_di_recupero_materia1_idx` (`materia_id` ASC),
  INDEX `fk_corso_di_recupero_anno_scolastico1_idx` (`anno_scolastico_id` ASC),
  INDEX `fk_corso_di_recupero_docente1_idx` (`docente_id` ASC),
  INDEX `in_itinere_INDEX` (`in_itinere` ASC),
  CONSTRAINT `fk_corso_di_recupero_materia1`
    FOREIGN KEY (`materia_id`)
    REFERENCES `materia` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_corso_di_recupero_anno_scolastico1`
    FOREIGN KEY (`anno_scolastico_id`)
    REFERENCES `anno_scolastico` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_corso_di_recupero_docente1`
    FOREIGN KEY (`docente_id`)
    REFERENCES `docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `lezione_corso_di_recupero`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `lezione_corso_di_recupero` ;

CREATE TABLE IF NOT EXISTS `lezione_corso_di_recupero` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `data` DATE NULL,
  `inizia_alle` TIME NULL,
  `numero_ore` DOUBLE NULL,
  `orario` VARCHAR(20) NULL,
  `firmato` TINYINT NULL DEFAULT 0,
  `argomento` VARCHAR(255) NULL,
  `note` VARCHAR(4000) NULL,
  `corso_di_recupero_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_lezione_corso_di_recupero_corso_di_recupero1_idx` (`corso_di_recupero_id` ASC),
  INDEX `data_index` (`data` ASC),
  INDEX `ora_index` (`inizia_alle` ASC),
  INDEX `firmato_index` (`firmato` ASC),
  CONSTRAINT `fk_lezione_corso_di_recupero_corso_di_recupero1`
    FOREIGN KEY (`corso_di_recupero_id`)
    REFERENCES `corso_di_recupero` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `classe`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `classe` ;

CREATE TABLE IF NOT EXISTS `classe` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(45) NULL,
  `attiva` TINYINT NULL DEFAULT 1,
  PRIMARY KEY (`id`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `profilo_docente_has_classe`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `profilo_docente_has_classe` ;

CREATE TABLE IF NOT EXISTS `profilo_docente_has_classe` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `profilo_docente_id` INT NOT NULL,
  `classe_id` INT NOT NULL,
  INDEX `fk_profilo_docente_has_classe_profilo_docente1_idx` (`profilo_docente_id` ASC),
  INDEX `fk_profilo_docente_has_classe_classe1_idx` (`classe_id` ASC),
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_profilo_docente_has_classe_profilo_docente1`
    FOREIGN KEY (`profilo_docente_id`)
    REFERENCES `profilo_docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_profilo_docente_has_classe_classe1`
    FOREIGN KEY (`classe_id`)
    REFERENCES `classe` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `studente_per_corso_di_recupero`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `studente_per_corso_di_recupero` ;

CREATE TABLE IF NOT EXISTS `studente_per_corso_di_recupero` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `cognome` VARCHAR(45) NULL,
  `nome` VARCHAR(45) NULL,
  `commento` VARCHAR(200) NULL,
  `classe` VARCHAR(10) NULL,
  `voto_settembre` INT NULL,
  `data_voto_settembre` DATE NULL,
  `docente_voto_settembre_id` INT NULL,
  `voto_novembre` INT NULL,
  `data_voto_novembre` DATE NULL,
  `docente_voto_novembre_id` INT NULL,
  `passato` TINYINT NULL,
  `serve_voto` TINYINT NULL,
  `corso_di_recupero_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `cognome_nome` (`cognome` ASC, `nome` ASC),
  INDEX `classe` (`classe` ASC),
  INDEX `fk_studente_per_corso_di_recupero_corso_di_recupero1_idx` (`corso_di_recupero_id` ASC),
  CONSTRAINT `fk_studente_per_corso_di_recupero_corso_di_recupero1`
    FOREIGN KEY (`corso_di_recupero_id`)
    REFERENCES `corso_di_recupero` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `studente_partecipa_lezione_corso_di_recupero`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `studente_partecipa_lezione_corso_di_recupero` ;

CREATE TABLE IF NOT EXISTS `studente_partecipa_lezione_corso_di_recupero` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `ha_partecipato` TINYINT NULL DEFAULT 0,
  `lezione_corso_di_recupero_id` INT NOT NULL,
  `studente_per_corso_di_recupero_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_studente_partecipa_lezione_corso_di_recupero_lezione_cor_idx` (`lezione_corso_di_recupero_id` ASC),
  INDEX `fk_studente_partecipa_lezione_corso_di_recupero_studente_pe_idx` (`studente_per_corso_di_recupero_id` ASC),
  CONSTRAINT `fk_studente_partecipa_lezione_corso_di_recupero_lezione_corso1`
    FOREIGN KEY (`lezione_corso_di_recupero_id`)
    REFERENCES `lezione_corso_di_recupero` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_studente_partecipa_lezione_corso_di_recupero_studente_per_1`
    FOREIGN KEY (`studente_per_corso_di_recupero_id`)
    REFERENCES `studente_per_corso_di_recupero` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `utente`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `utente` ;

CREATE TABLE IF NOT EXISTS `utente` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(45) NULL,
  `cognome` VARCHAR(45) NULL,
  `nome` VARCHAR(45) NULL,
  `ruolo` VARCHAR(45) NULL,
  `password` VARCHAR(200) NULL,
  `email` VARCHAR(200) NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `username_index` (`username` ASC),
  INDEX `cognome_nome_index` (`cognome` ASC, `nome` ASC),
  INDEX `email_index` (`email` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `aula`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `aula` ;

CREATE TABLE IF NOT EXISTS `aula` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `codice` VARCHAR(45) NULL,
  `nome_diurno` VARCHAR(45) NULL,
  `nome_serale_eda` VARCHAR(45) NULL,
  `piano` VARCHAR(45) NULL,
  PRIMARY KEY (`id`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `ora_insegnamento`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `ora_insegnamento` ;

CREATE TABLE IF NOT EXISTS `ora_insegnamento` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(45) NULL,
  `tipo` VARCHAR(45) NULL COMMENT '\'da trasformare in enum, tipo diurno, serale etc\'',
  `orario` VARCHAR(45) NULL COMMENT '\'ad esempio 9:50 - 10:40\'',
  `note` VARCHAR(2000) NULL,
  PRIMARY KEY (`id`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `tipo_sostituzione`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `tipo_sostituzione` ;

CREATE TABLE IF NOT EXISTS `tipo_sostituzione` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `codice` VARCHAR(45) NULL,
  `colore` VARCHAR(45) NULL,
  PRIMARY KEY (`id`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `sostituzione`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `sostituzione` ;

CREATE TABLE IF NOT EXISTS `sostituzione` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `data` DATE NULL,
  `effettuata` TINYINT NULL DEFAULT 0,
  `anno_scolastico_id` INT NOT NULL,
  `docente_incaricato_id` INT NOT NULL,
  `docente_assente_id` INT NOT NULL,
  `classe_id` INT NOT NULL,
  `aula_id` INT NOT NULL,
  `ora_insegnamento_id` INT NOT NULL,
  `tipo_sostituzione_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_sostituzione_anno_scolastico1_idx` (`anno_scolastico_id` ASC),
  INDEX `fk_sostituzione_docente1_idx` (`docente_incaricato_id` ASC),
  INDEX `fk_sostituzione_docente2_idx` (`docente_assente_id` ASC),
  INDEX `fk_sostituzione_classe1_idx` (`classe_id` ASC),
  INDEX `fk_sostituzione_aula1_idx` (`aula_id` ASC),
  INDEX `fk_sostituzione_ora_insegnamento1_idx` (`ora_insegnamento_id` ASC),
  INDEX `fk_sostituzione_tipo_sostituzione1_idx` (`tipo_sostituzione_id` ASC),
  CONSTRAINT `fk_sostituzione_anno_scolastico1`
    FOREIGN KEY (`anno_scolastico_id`)
    REFERENCES `anno_scolastico` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_sostituzione_docente1`
    FOREIGN KEY (`docente_incaricato_id`)
    REFERENCES `docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_sostituzione_docente2`
    FOREIGN KEY (`docente_assente_id`)
    REFERENCES `docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_sostituzione_classe1`
    FOREIGN KEY (`classe_id`)
    REFERENCES `classe` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_sostituzione_aula1`
    FOREIGN KEY (`aula_id`)
    REFERENCES `aula` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_sostituzione_ora_insegnamento1`
    FOREIGN KEY (`ora_insegnamento_id`)
    REFERENCES `ora_insegnamento` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_sostituzione_tipo_sostituzione1`
    FOREIGN KEY (`tipo_sostituzione_id`)
    REFERENCES `tipo_sostituzione` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `viaggio`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `viaggio` ;

CREATE TABLE IF NOT EXISTS `viaggio` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `protocollo` VARCHAR(45) NULL,
  `destinazione` VARCHAR(45) NULL,
  `tipo_viaggio` VARCHAR(45) NULL,
  `data_nomina` DATE NULL,
  `data_partenza` DATE NULL,
  `data_rientro` DATE NULL,
  `ora_partenza` TIME NULL,
  `ora_rientro` TIME NULL,
  `classe` VARCHAR(45) NULL,
  `note` VARCHAR(200) NULL,
  `totale_rimborso_spese` DECIMAL(10,2) NULL,
  `ore_richieste` DOUBLE NULL DEFAULT 0,
  `richiesta_fuis` TINYINT NULL,
  `stato` VARCHAR(45) NULL DEFAULT 'assegnato',
  `rimborsato` TINYINT NULL DEFAULT 0,
  `chiuso` TINYINT NULL DEFAULT 0,
  `anno_scolastico_id` INT NOT NULL,
  `docente_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_viaggio_anno_scolastico1_idx` (`anno_scolastico_id` ASC),
  INDEX `fk_viaggio_docente1_idx` (`docente_id` ASC),
  INDEX `chiuso_index` (`chiuso` ASC),
  INDEX `data_index` (`data_partenza` ASC),
  CONSTRAINT `fk_viaggio_anno_scolastico1`
    FOREIGN KEY (`anno_scolastico_id`)
    REFERENCES `anno_scolastico` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_viaggio_docente1`
    FOREIGN KEY (`docente_id`)
    REFERENCES `docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `spesa_viaggio`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `spesa_viaggio` ;

CREATE TABLE IF NOT EXISTS `spesa_viaggio` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `importo` DECIMAL(10,2) NULL,
  `data` DATE NULL,
  `tipo` VARCHAR(45) NULL COMMENT 'pasti, trasporti, biglietti vari, altre spese',
  `note` VARCHAR(255) NULL,
  `validato` TINYINT NULL,
  `viaggio_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_spesa_viaggio_viaggio1_idx` (`viaggio_id` ASC),
  CONSTRAINT `fk_spesa_viaggio_viaggio1`
    FOREIGN KEY (`viaggio_id`)
    REFERENCES `viaggio` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `sostituzione_disponibilita`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `sostituzione_disponibilita` ;

CREATE TABLE IF NOT EXISTS `sostituzione_disponibilita` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tipo` VARCHAR(45) NULL,
  `note` VARCHAR(45) NULL,
  `giorno_settimana` INT NULL,
  `data` DATE NOT NULL,
  `ora_insegnamento_id` INT NOT NULL,
  `anno_scolastico_id` INT NOT NULL,
  `docente_id` INT NOT NULL,
  PRIMARY KEY (`id`, `data`),
  INDEX `fk_sostituzione_disponibilita_ora_insegnamento1_idx` (`ora_insegnamento_id` ASC),
  INDEX `fk_sostituzione_disponibilita_anno_scolastico1_idx` (`anno_scolastico_id` ASC),
  INDEX `fk_sostituzione_disponibilita_docente1_idx` (`docente_id` ASC),
  CONSTRAINT `fk_sostituzione_disponibilita_ora_insegnamento1`
    FOREIGN KEY (`ora_insegnamento_id`)
    REFERENCES `ora_insegnamento` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_sostituzione_disponibilita_anno_scolastico1`
    FOREIGN KEY (`anno_scolastico_id`)
    REFERENCES `anno_scolastico` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_sostituzione_disponibilita_docente1`
    FOREIGN KEY (`docente_id`)
    REFERENCES `docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `sostituzione_situazione_docente`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `sostituzione_situazione_docente` ;

CREATE TABLE IF NOT EXISTS `sostituzione_situazione_docente` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `giorno_settimana` INT NULL,
  `ora_insegnamento_id` INT NOT NULL,
  `ore_da_fare` INT NULL,
  `ore_fatte` INT NULL,
  `docente_id` INT NOT NULL,
  `anno_scolastico_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_sostituzione_situazione_docente_ora_insegnamento1_idx` (`ora_insegnamento_id` ASC),
  INDEX `fk_sostituzione_situazione_docente_docente1_idx` (`docente_id` ASC),
  INDEX `fk_sostituzione_situazione_docente_anno_scolastico1_idx` (`anno_scolastico_id` ASC),
  CONSTRAINT `fk_sostituzione_situazione_docente_ora_insegnamento1`
    FOREIGN KEY (`ora_insegnamento_id`)
    REFERENCES `ora_insegnamento` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_sostituzione_situazione_docente_docente1`
    FOREIGN KEY (`docente_id`)
    REFERENCES `docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_sostituzione_situazione_docente_anno_scolastico1`
    FOREIGN KEY (`anno_scolastico_id`)
    REFERENCES `anno_scolastico` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `ore_dovute`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `ore_dovute` ;

CREATE TABLE IF NOT EXISTS `ore_dovute` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `ore_80_collegi_docenti` DOUBLE NULL DEFAULT 0,
  `ore_80_udienze_generali` DOUBLE NULL DEFAULT 0,
  `ore_80_dipartimenti` DOUBLE NULL DEFAULT 0,
  `ore_80_aggiornamento_facoltativo` DOUBLE NULL DEFAULT 0,
  `ore_80_consigli_di_classe` DOUBLE NULL DEFAULT 0,
  `ore_40_sostituzioni_di_ufficio` DOUBLE NULL DEFAULT 0,
  `ore_40_con_studenti` DOUBLE NULL DEFAULT 0 COMMENT '(da 60 minuti)',
  `ore_40_aggiornamento` DOUBLE NULL DEFAULT 0 COMMENT '(da 60 minuti)',
  `ore_70_funzionali` DOUBLE NULL DEFAULT 0 COMMENT '(da 60 minuti)',
  `ore_70_con_studenti` DOUBLE NULL DEFAULT 0 COMMENT '(da 50 minuti)',
  `ore_80_totale` DOUBLE NULL DEFAULT 0,
  `ore_40_totale` DOUBLE NULL DEFAULT 0,
  `ore_70_totale` DOUBLE NULL DEFAULT 0,
  `note` VARCHAR(2000) NULL,
  `docente_id` INT NOT NULL,
  `anno_scolastico_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_ore_dovute_docente1_idx` (`docente_id` ASC),
  INDEX `fk_ore_dovute_anno_scolastico1_idx` (`anno_scolastico_id` ASC),
  UNIQUE INDEX `docente_anno_unico` (`docente_id` ASC, `anno_scolastico_id` ASC),
  CONSTRAINT `fk_ore_dovute_docente1`
    FOREIGN KEY (`docente_id`)
    REFERENCES `docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_ore_dovute_anno_scolastico1`
    FOREIGN KEY (`anno_scolastico_id`)
    REFERENCES `anno_scolastico` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `ore_previste_tipo_attivita`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `ore_previste_tipo_attivita` ;

CREATE TABLE IF NOT EXISTS `ore_previste_tipo_attivita` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `categoria` VARCHAR(45) NULL COMMENT 'ad esempio 80, funzionali, con studenti',
  `nome` VARCHAR(200) NULL COMMENT 'cdc, dipartimenti, sportello, olimpiadi...',
  `ore` DOUBLE NULL COMMENT 'se 0, deve essere inserito, altrimenti vale quanto scritto',
  `ore_max` DOUBLE NULL COMMENT 'numero massimo di ore che si possono inserire',
  `valido` TINYINT NULL COMMENT 'indica se questo anno questo tipo di arttivita puo essere scelto',
  `inserito_da_docente` TINYINT NULL COMMENT 'indica se il docente lo puo inserire o viene assegnato',
  `previsto_da_docente` TINYINT NULL,
  `da_rendicontare` TINYINT NULL,
  PRIMARY KEY (`id`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `ore_previste_attivita`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `ore_previste_attivita` ;

CREATE TABLE IF NOT EXISTS `ore_previste_attivita` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `dettaglio` VARCHAR(200) NULL COMMENT 'parte specifica del nome, ad esempio per commissioni il nome della commissione',
  `ore` DOUBLE NULL COMMENT 'numero di ore previste per questa attivita',
  `ultima_modifica` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ore_previste_tipo_attivita_id` INT NOT NULL,
  `docente_id` INT NOT NULL,
  `anno_scolastico_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_ore_previste_attivita_ore_previste_tipo_attivita1_idx` (`ore_previste_tipo_attivita_id` ASC),
  INDEX `fk_ore_previste_attivita_docente1_idx` (`docente_id` ASC),
  INDEX `fk_ore_previste_attivita_anno_scolastico1_idx` (`anno_scolastico_id` ASC),
  CONSTRAINT `fk_ore_previste_attivita_ore_previste_tipo_attivita1`
    FOREIGN KEY (`ore_previste_tipo_attivita_id`)
    REFERENCES `ore_previste_tipo_attivita` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_ore_previste_attivita_docente1`
    FOREIGN KEY (`docente_id`)
    REFERENCES `docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_ore_previste_attivita_anno_scolastico1`
    FOREIGN KEY (`anno_scolastico_id`)
    REFERENCES `anno_scolastico` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `ore_previste`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `ore_previste` ;

CREATE TABLE IF NOT EXISTS `ore_previste` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `ore_80_collegi_docenti` DOUBLE NULL DEFAULT 0,
  `ore_80_udienze_generali` DOUBLE NULL DEFAULT 0,
  `ore_80_dipartimenti` DOUBLE NULL DEFAULT 0,
  `ore_80_aggiornamento_facoltativo` DOUBLE NULL DEFAULT 0,
  `ore_80_consigli_di_classe` DOUBLE NULL DEFAULT 0,
  `ore_40_sostituzioni_di_ufficio` DOUBLE NULL DEFAULT 0,
  `ore_40_con_studenti` DOUBLE NULL DEFAULT 0 COMMENT '(da 60 minuti)',
  `ore_40_aggiornamento` DOUBLE NULL DEFAULT 0 COMMENT '(da 60 minuti)',
  `ore_70_funzionali` DOUBLE NULL DEFAULT 0 COMMENT '(da 60 minuti)',
  `ore_70_con_studenti` DOUBLE NULL DEFAULT 0 COMMENT '(da 50 minuti)',
  `ore_80_totale` DOUBLE NULL DEFAULT 0,
  `ore_40_totale` DOUBLE NULL DEFAULT 0,
  `ore_70_totale` DOUBLE NULL DEFAULT 0,
  `note` VARCHAR(2000) NULL,
  `ultimo_controllo` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `chiuso` TINYINT NULL DEFAULT 0,
  `docente_id` INT NOT NULL,
  `anno_scolastico_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_ore_dovute_docente1_idx` (`docente_id` ASC),
  INDEX `fk_ore_dovute_anno_scolastico1_idx` (`anno_scolastico_id` ASC),
  CONSTRAINT `fk_ore_dovute_docente10`
    FOREIGN KEY (`docente_id`)
    REFERENCES `docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_ore_dovute_anno_scolastico10`
    FOREIGN KEY (`anno_scolastico_id`)
    REFERENCES `anno_scolastico` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `ore_fatte`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `ore_fatte` ;

CREATE TABLE IF NOT EXISTS `ore_fatte` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `ore_80_collegi_docenti` DOUBLE NULL DEFAULT 0,
  `ore_80_udienze_generali` DOUBLE NULL DEFAULT 0,
  `ore_80_dipartimenti` DOUBLE NULL DEFAULT 0,
  `ore_80_aggiornamento_facoltativo` DOUBLE NULL DEFAULT 0,
  `ore_80_consigli_di_classe` DOUBLE NULL DEFAULT 0,
  `ore_40_sostituzioni_di_ufficio` DOUBLE NULL DEFAULT 0,
  `ore_40_con_studenti` DOUBLE NULL DEFAULT 0 COMMENT '(da 60 minuti)',
  `ore_40_aggiornamento` DOUBLE NULL DEFAULT 0 COMMENT '(da 60 minuti)',
  `ore_70_funzionali` DOUBLE NULL DEFAULT 0 COMMENT '(da 60 minuti)',
  `ore_70_con_studenti` DOUBLE NULL DEFAULT 0 COMMENT '(da 50 minuti)',
  `ore_80_totale` DOUBLE NULL DEFAULT 0,
  `ore_40_totale` DOUBLE NULL DEFAULT 0,
  `ore_70_totale` DOUBLE NULL DEFAULT 0,
  `note` VARCHAR(2000) NULL,
  `ultimo_controllo` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `chiuso` TINYINT NULL DEFAULT 0,
  `docente_id` INT NOT NULL,
  `anno_scolastico_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_ore_dovute_docente1_idx` (`docente_id` ASC),
  INDEX `fk_ore_dovute_anno_scolastico1_idx` (`anno_scolastico_id` ASC),
  CONSTRAINT `fk_ore_dovute_docente11`
    FOREIGN KEY (`docente_id`)
    REFERENCES `docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_ore_dovute_anno_scolastico11`
    FOREIGN KEY (`anno_scolastico_id`)
    REFERENCES `anno_scolastico` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `ore_fatte_attivita`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `ore_fatte_attivita` ;

CREATE TABLE IF NOT EXISTS `ore_fatte_attivita` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `data` DATE NULL,
  `ora_inizio` TIME NULL,
  `ore` DOUBLE NULL,
  `dettaglio` VARCHAR(200) NULL,
  `contestata` TINYINT NULL,
  `ultima_modifica` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ore_previste_tipo_attivita_id` INT NOT NULL,
  `anno_scolastico_id` INT NOT NULL,
  `docente_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_ore_fatte_attivita_ore_previste_tipo_attivita1_idx` (`ore_previste_tipo_attivita_id` ASC),
  INDEX `fk_ore_fatte_attivita_anno_scolastico1_idx` (`anno_scolastico_id` ASC),
  INDEX `fk_ore_fatte_attivita_docente1_idx` (`docente_id` ASC),
  CONSTRAINT `fk_ore_fatte_attivita_ore_previste_tipo_attivita1`
    FOREIGN KEY (`ore_previste_tipo_attivita_id`)
    REFERENCES `ore_previste_tipo_attivita` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_ore_fatte_attivita_anno_scolastico1`
    FOREIGN KEY (`anno_scolastico_id`)
    REFERENCES `anno_scolastico` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_ore_fatte_attivita_docente1`
    FOREIGN KEY (`docente_id`)
    REFERENCES `docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `registro_attivita`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `registro_attivita` ;

CREATE TABLE IF NOT EXISTS `registro_attivita` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `descrizione` VARCHAR(200) NULL,
  `studenti` MEDIUMTEXT NULL,
  `ore_fatte_attivita_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_registro_attivita_ore_fatte_attivita1_idx` (`ore_fatte_attivita_id` ASC),
  CONSTRAINT `fk_registro_attivita_ore_fatte_attivita1`
    FOREIGN KEY (`ore_fatte_attivita_id`)
    REFERENCES `ore_fatte_attivita` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `config`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `config` ;

CREATE TABLE IF NOT EXISTS `config` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `voti_recupero_settembre_aperto` TINYINT NULL,
  `voti_recupero_novembre_aperto` TINYINT NULL,
  `ore_previsioni_aperto` TINYINT NULL,
  `ore_fatte_aperto` TINYINT NULL,
  `bonus_adesione_aperto` TINYINT NULL,
  `bonus_rendiconto_aperto` TINYINT NULL,
  `bonus_budget` FLOAT NULL,
  `fuis_budget` FLOAT NULL,
  `fuis_ore_con_studenti` FLOAT NULL,
  `fuis_ore_funzionali` FLOAT NULL,
  `fuis_diaria_viaggi` FLOAT NULL,
  `fuis_assegnato` FLOAT NULL,
  `ultimo_controllo_sportelli` DATE NULL DEFAULT '2020-01-01',
  `debug` TINYINT NULL DEFAULT 0,
  PRIMARY KEY (`id`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `rendiconto_attivita`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `rendiconto_attivita` ;

CREATE TABLE IF NOT EXISTS `rendiconto_attivita` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `rendiconto` MEDIUMTEXT NULL,
  `rendicontato` TINYINT NULL,
  `ore_previste_attivita_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_rendiconto_attivita_ore_previste_attivita1_idx` (`ore_previste_attivita_id` ASC),
  CONSTRAINT `fk_rendiconto_attivita_ore_previste_attivita1`
    FOREIGN KEY (`ore_previste_attivita_id`)
    REFERENCES `ore_previste_attivita` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `bonus_area`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `bonus_area` ;

CREATE TABLE IF NOT EXISTS `bonus_area` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `valido` TINYINT NULL,
  `codice` VARCHAR(10) NULL,
  `descrizione` VARCHAR(45) NULL,
  `valore_massimo` INT NULL,
  `peso_percentuale` INT NULL,
  PRIMARY KEY (`id`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `bonus_indicatore`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `bonus_indicatore` ;

CREATE TABLE IF NOT EXISTS `bonus_indicatore` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `valido` TINYINT NULL,
  `codice` VARCHAR(45) NULL,
  `descrizione` VARCHAR(200) NULL,
  `valore_massimo` INT NULL,
  `bonus_area_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_bonus_indicatore_bonus_area1_idx` (`bonus_area_id` ASC),
  CONSTRAINT `fk_bonus_indicatore_bonus_area1`
    FOREIGN KEY (`bonus_area_id`)
    REFERENCES `bonus_area` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `bonus`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `bonus` ;

CREATE TABLE IF NOT EXISTS `bonus` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `valido` TINYINT NULL,
  `codice` VARCHAR(45) NULL,
  `descrittori` VARCHAR(2000) NULL,
  `evidenze` VARCHAR(2000) NULL,
  `valore_previsto` INT NULL,
  `bonus_indicatore_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_bonus_bonus_indicatore1_idx` (`bonus_indicatore_id` ASC),
  CONSTRAINT `fk_bonus_bonus_indicatore1`
    FOREIGN KEY (`bonus_indicatore_id`)
    REFERENCES `bonus_indicatore` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `bonus_docente`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `bonus_docente` ;

CREATE TABLE IF NOT EXISTS `bonus_docente` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `rendiconto_evidenze` VARCHAR(2000) NULL,
  `approvato` TINYINT NULL,
  `ultima_modifica` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ultimo_controllo` TIMESTAMP NULL DEFAULT 0,
  `docente_id` INT NOT NULL,
  `anno_scolastico_id` INT NOT NULL,
  `bonus_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_bonus_docente_docente1_idx` (`docente_id` ASC),
  INDEX `fk_bonus_docente_anno_scolastico1_idx` (`anno_scolastico_id` ASC),
  INDEX `fk_bonus_docente_bonus1_idx` (`bonus_id` ASC),
  CONSTRAINT `fk_bonus_docente_docente1`
    FOREIGN KEY (`docente_id`)
    REFERENCES `docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_bonus_docente_anno_scolastico1`
    FOREIGN KEY (`anno_scolastico_id`)
    REFERENCES `anno_scolastico` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_bonus_docente_bonus1`
    FOREIGN KEY (`bonus_id`)
    REFERENCES `bonus` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `viaggio_ore_recuperate`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `viaggio_ore_recuperate` ;

CREATE TABLE IF NOT EXISTS `viaggio_ore_recuperate` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `ore` DOUBLE NULL,
  `viaggio_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_viaggio_ore_recuperate_viaggio1_idx` (`viaggio_id` ASC),
  CONSTRAINT `fk_viaggio_ore_recuperate_viaggio1`
    FOREIGN KEY (`viaggio_id`)
    REFERENCES `viaggio` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `fuis_viaggio_diaria`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `fuis_viaggio_diaria` ;

CREATE TABLE IF NOT EXISTS `fuis_viaggio_diaria` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `importo` FLOAT NULL,
  `liquidato` TINYINT NULL,
  `data_richiesta_liquidazione` DATE NULL,
  `viaggio_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_fuis_viaggio_diaria_viaggio1_idx` (`viaggio_id` ASC),
  CONSTRAINT `fk_fuis_viaggio_diaria_viaggio1`
    FOREIGN KEY (`viaggio_id`)
    REFERENCES `viaggio` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `fuis_assegnato_tipo`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `fuis_assegnato_tipo` ;

CREATE TABLE IF NOT EXISTS `fuis_assegnato_tipo` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(200) NULL,
  `codice_citrix` VARCHAR(45) NULL,
  `attivo` TINYINT NULL,
  PRIMARY KEY (`id`),
  INDEX `attivo_index` (`attivo` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `fuis_assegnato`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `fuis_assegnato` ;

CREATE TABLE IF NOT EXISTS `fuis_assegnato` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `importo` FLOAT NULL,
  `fuis_assegnato_tipo_id` INT NOT NULL,
  `docente_id` INT NOT NULL,
  `anno_scolastico_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_fuis_assegnato_fuis_assegnato_tipo1_idx` (`fuis_assegnato_tipo_id` ASC),
  INDEX `fk_fuis_assegnato_docente1_idx` (`docente_id` ASC),
  INDEX `fk_fuis_assegnato_anno_scolastico1_idx` (`anno_scolastico_id` ASC),
  CONSTRAINT `fk_fuis_assegnato_fuis_assegnato_tipo1`
    FOREIGN KEY (`fuis_assegnato_tipo_id`)
    REFERENCES `fuis_assegnato_tipo` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_fuis_assegnato_docente1`
    FOREIGN KEY (`docente_id`)
    REFERENCES `docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_fuis_assegnato_anno_scolastico1`
    FOREIGN KEY (`anno_scolastico_id`)
    REFERENCES `anno_scolastico` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `fuis_docente`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `fuis_docente` ;

CREATE TABLE IF NOT EXISTS `fuis_docente` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `viaggi` FLOAT NULL,
  `assegnato` FLOAT NULL,
  `funzionale` FLOAT NULL,
  `con_studenti` FLOAT NULL,
  `totale` FLOAT NULL,
  `clil_funzionale` FLOAT NULL,
  `clil_con_studenti` FLOAT NULL,
  `clil_funzionale_ore` DOUBLE NULL,
  `clil_funzionale_proposto` FLOAT NULL,
  `clil_funzionale_approvato` FLOAT NULL,
  `clil_con_studenti_ore` DOUBLE NULL,
  `clil_con_studenti_proposto` FLOAT NULL,
  `clil_con_studenti_approvato` FLOAT NULL,
  `funzionale_ore` DOUBLE NULL,
  `funzionale_proposto` FLOAT NULL,
  `funzionale_approvato` FLOAT NULL,
  `con_studenti_ore` DOUBLE NULL,
  `con_studenti_proposto` FLOAT NULL,
  `con_studenti_approvato` FLOAT NULL,
  `sostituzioni_ore` DOUBLE NULL,
  `sostituzioni_proposto` FLOAT NULL,
  `sostituzioni_approvato` FLOAT NULL,
  `totale_proposto` FLOAT NULL,
  `totale_approvato` FLOAT NULL,
  `clil_totale_proposto` FLOAT NULL,
  `clil_totale_approvato` FLOAT NULL,
  `totale_da_pagare` FLOAT NULL,
  `confermato` TINYINT NULL,
  `commento` VARCHAR(2000) NULL,
  `ultimo_controllo` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `docente_id` INT NOT NULL,
  `anno_scolastico_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_fuis_docente_docente1_idx` (`docente_id` ASC),
  INDEX `fk_fuis_docente_anno_scolastico1_idx` (`anno_scolastico_id` ASC),
  UNIQUE INDEX `unique_fuis_docente_anno` (`docente_id` ASC, `anno_scolastico_id` ASC),
  CONSTRAINT `fk_fuis_docente_docente1`
    FOREIGN KEY (`docente_id`)
    REFERENCES `docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_fuis_docente_anno_scolastico1`
    FOREIGN KEY (`anno_scolastico_id`)
    REFERENCES `anno_scolastico` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `ore_fatte_attivita_clil`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `ore_fatte_attivita_clil` ;

CREATE TABLE IF NOT EXISTS `ore_fatte_attivita_clil` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `data` DATE NULL,
  `ora_inizio` TIME NULL,
  `ore` DOUBLE NULL,
  `dettaglio` VARCHAR(200) NULL,
  `con_studenti` TINYINT NULL,
  `contestata` TINYINT NULL,
  `ultima_modifica` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `docente_id` INT NOT NULL,
  `anno_scolastico_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_ore_fatte_attivita_clil_docente1_idx` (`docente_id` ASC),
  INDEX `fk_ore_fatte_attivita_clil_anno_scolastico1_idx` (`anno_scolastico_id` ASC),
  CONSTRAINT `fk_ore_fatte_attivita_clil_docente1`
    FOREIGN KEY (`docente_id`)
    REFERENCES `docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_ore_fatte_attivita_clil_anno_scolastico1`
    FOREIGN KEY (`anno_scolastico_id`)
    REFERENCES `anno_scolastico` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `registro_attivita_clil`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `registro_attivita_clil` ;

CREATE TABLE IF NOT EXISTS `registro_attivita_clil` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `descrizione` VARCHAR(200) NULL,
  `studenti` MEDIUMTEXT NULL,
  `ore_fatte_attivita_clil_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_registro_attivita_clil_ore_fatte_attivita_clil1_idx` (`ore_fatte_attivita_clil_id` ASC),
  CONSTRAINT `fk_registro_attivita_clil_ore_fatte_attivita_clil1`
    FOREIGN KEY (`ore_fatte_attivita_clil_id`)
    REFERENCES `ore_fatte_attivita_clil` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `ore_fatte_attivita_commento`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `ore_fatte_attivita_commento` ;

CREATE TABLE IF NOT EXISTS `ore_fatte_attivita_commento` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `commento` VARCHAR(400) NULL,
  `ore_originali` DOUBLE NULL DEFAULT 0,
  `ore_concesse` INT NULL,
  `chiuso` TINYINT NULL,
  `ore_fatte_attivita_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_ore_fatte_attivita_commento_ore_fatte_attivita1_idx` (`ore_fatte_attivita_id` ASC),
  CONSTRAINT `fk_ore_fatte_attivita_commento_ore_fatte_attivita1`
    FOREIGN KEY (`ore_fatte_attivita_id`)
    REFERENCES `ore_fatte_attivita` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `ore_fatte_attivita_clil_commento`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `ore_fatte_attivita_clil_commento` ;

CREATE TABLE IF NOT EXISTS `ore_fatte_attivita_clil_commento` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `commento` VARCHAR(400) NULL,
  `ore_originali` DOUBLE NULL DEFAULT 0,
  `ore_concesse` INT NULL,
  `chiuso` TINYINT NULL,
  `ore_fatte_attivita_clil_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_ore_fatte_attivita_clil_commento_ore_fatte_attivita_clil_idx` (`ore_fatte_attivita_clil_id` ASC),
  CONSTRAINT `fk_ore_fatte_attivita_clil_commento_ore_fatte_attivita_clil1`
    FOREIGN KEY (`ore_fatte_attivita_clil_id`)
    REFERENCES `ore_fatte_attivita_clil` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `sostituzione_docente`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `sostituzione_docente` ;

CREATE TABLE IF NOT EXISTS `sostituzione_docente` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `data` DATE NOT NULL,
  `ora` INT NULL,
  `numero_ore` DOUBLE NULL,
  `anno_scolastico_id` INT NOT NULL,
  `docente_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_sostituzione_docente_anno_scolastico1_idx` (`anno_scolastico_id` ASC),
  INDEX `fk_sostituzione_docente_docente1_idx` (`docente_id` ASC),
  INDEX `data` (`data` ASC),
  CONSTRAINT `fk_sostituzione_docente_anno_scolastico1`
    FOREIGN KEY (`anno_scolastico_id`)
    REFERENCES `anno_scolastico` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_sostituzione_docente_docente1`
    FOREIGN KEY (`docente_id`)
    REFERENCES `docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `immagine`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `immagine` ;

CREATE TABLE IF NOT EXISTS `immagine` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(45) NOT NULL,
  `src` MEDIUMBLOB NULL,
  PRIMARY KEY (`id`),
  INDEX `nome_immagine` (`nome` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `gruppo`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `gruppo` ;

CREATE TABLE IF NOT EXISTS `gruppo` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(200) NULL,
  `dipartimento` TINYINT NULL,
  `clil` TINYINT NULL DEFAULT 0,
  `commento` TEXT NULL,
  `max_ore` DOUBLE NOT NULL DEFAULT 10,
  `anno_scolastico_id` INT NOT NULL,
  `responsabile_docente_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_gruppo_anno_scolastico1_idx` (`anno_scolastico_id` ASC),
  INDEX `fk_gruppo_docente1_idx` (`responsabile_docente_id` ASC),
  INDEX `dipartimento_index` (`dipartimento` ASC),
  INDEX `dipartimento_nome` (`nome` ASC),
  INDEX `clil_index` (`clil` ASC),
  CONSTRAINT `fk_gruppo_anno_scolastico1`
    FOREIGN KEY (`anno_scolastico_id`)
    REFERENCES `anno_scolastico` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_gruppo_docente1`
    FOREIGN KEY (`responsabile_docente_id`)
    REFERENCES `docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `gruppo_partecipante`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `gruppo_partecipante` ;

CREATE TABLE IF NOT EXISTS `gruppo_partecipante` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `gruppo_id` INT NOT NULL,
  `docente_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_gruppo_partecipante_gruppo1_idx` (`gruppo_id` ASC),
  INDEX `fk_gruppo_partecipante_docente1_idx` (`docente_id` ASC),
  CONSTRAINT `fk_gruppo_partecipante_gruppo1`
    FOREIGN KEY (`gruppo_id`)
    REFERENCES `gruppo` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_gruppo_partecipante_docente1`
    FOREIGN KEY (`docente_id`)
    REFERENCES `docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `gruppo_incontro`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `gruppo_incontro` ;

CREATE TABLE IF NOT EXISTS `gruppo_incontro` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `data` DATE NULL,
  `ora` TIME NULL,
  `ordine_del_giorno` TEXT NULL,
  `verbale` TEXT NULL,
  `durata` DOUBLE NULL,
  `effettuato` TINYINT NULL,
  `gruppo_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_gruppo_incontro_gruppo1_idx` (`gruppo_id` ASC),
  INDEX `data_index` (`data` ASC),
  CONSTRAINT `fk_gruppo_incontro_gruppo1`
    FOREIGN KEY (`gruppo_id`)
    REFERENCES `gruppo` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `gruppo_incontro_partecipazione`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `gruppo_incontro_partecipazione` ;

CREATE TABLE IF NOT EXISTS `gruppo_incontro_partecipazione` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `ore` DOUBLE NULL,
  `ha_partecipato` TINYINT NULL DEFAULT 0,
  `gruppo_incontro_id` INT NOT NULL,
  `docente_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_gruppo_incontro_partecipante_gruppo_incontro1_idx` (`gruppo_incontro_id` ASC),
  INDEX `fk_gruppo_incontro_partecipante_docente1_idx` (`docente_id` ASC),
  INDEX `index_ha_partecipato` (`ha_partecipato` ASC),
  CONSTRAINT `fk_gruppo_incontro_partecipante_gruppo_incontro1`
    FOREIGN KEY (`gruppo_incontro_id`)
    REFERENCES `gruppo_incontro` (`id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_gruppo_incontro_partecipante_docente1`
    FOREIGN KEY (`docente_id`)
    REFERENCES `docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `collegio_docenti`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `collegio_docenti` ;

CREATE TABLE IF NOT EXISTS `collegio_docenti` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `data` DATE NULL,
  `ora` TIME NULL,
  `effettuato` TINYINT NULL,
  `ordine_del_giorno` TEXT NULL,
  `durata` INT NULL,
  `verbale` TEXT NULL,
  `approvato` TINYINT NULL,
  `anno_scolastico_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_collegio_docenti_anno_scolastico1_idx` (`anno_scolastico_id` ASC),
  INDEX `index_data` (`data` ASC),
  CONSTRAINT `fk_collegio_docenti_anno_scolastico1`
    FOREIGN KEY (`anno_scolastico_id`)
    REFERENCES `anno_scolastico` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `collegio_docenti_partecipazione`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `collegio_docenti_partecipazione` ;

CREATE TABLE IF NOT EXISTS `collegio_docenti_partecipazione` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tempo_firma` TIMESTAMP NULL,
  `ha_partecipato` TINYINT NULL,
  `collegio_docenti_id` INT NOT NULL,
  `docente_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_collegio_docenti_partecipazione_collegio_docenti1_idx` (`collegio_docenti_id` ASC),
  INDEX `fk_collegio_docenti_partecipazione_docente1_idx` (`docente_id` ASC),
  CONSTRAINT `fk_collegio_docenti_partecipazione_collegio_docenti1`
    FOREIGN KEY (`collegio_docenti_id`)
    REFERENCES `collegio_docenti` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_collegio_docenti_partecipazione_docente1`
    FOREIGN KEY (`docente_id`)
    REFERENCES `docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `udienze_generali`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `udienze_generali` ;

CREATE TABLE IF NOT EXISTS `udienze_generali` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `data` DATE NULL,
  `durata` DOUBLE NULL,
  `anno_scolastico_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_udienze_generali_anno_scolastico1_idx` (`anno_scolastico_id` ASC),
  CONSTRAINT `fk_udienze_generali_anno_scolastico1`
    FOREIGN KEY (`anno_scolastico_id`)
    REFERENCES `anno_scolastico` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `udienze_generali_partecipazione`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `udienze_generali_partecipazione` ;

CREATE TABLE IF NOT EXISTS `udienze_generali_partecipazione` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `ha_partecipato` TINYINT NULL,
  `ore` DOUBLE NULL,
  `udienze_generali_id` INT NOT NULL,
  `docente_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_udienze_generali_partecipazione_udienze_generali1_idx` (`udienze_generali_id` ASC),
  INDEX `fk_udienze_generali_partecipazione_docente1_idx` (`docente_id` ASC),
  CONSTRAINT `fk_udienze_generali_partecipazione_udienze_generali1`
    FOREIGN KEY (`udienze_generali_id`)
    REFERENCES `udienze_generali` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_udienze_generali_partecipazione_docente1`
    FOREIGN KEY (`docente_id`)
    REFERENCES `docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `ore_previste_attivita_commento`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `ore_previste_attivita_commento` ;

CREATE TABLE IF NOT EXISTS `ore_previste_attivita_commento` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `commento` VARCHAR(400) NULL,
  `ore_originali` DOUBLE NULL,
  `ore_previste_attivita_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_ore_previste_attivita_commento_ore_previste_attivita1_idx` (`ore_previste_attivita_id` ASC),
  CONSTRAINT `fk_ore_previste_attivita_commento_ore_previste_attivita1`
    FOREIGN KEY (`ore_previste_attivita_id`)
    REFERENCES `ore_previste_attivita` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `sportello`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `sportello` ;

CREATE TABLE IF NOT EXISTS `sportello` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `data` DATE NULL,
  `ora` VARCHAR(45) NULL,
  `numero_ore` DOUBLE NULL,
  `argomento` VARCHAR(2000) NULL,
  `luogo` VARCHAR(45) NULL,
  `classe` VARCHAR(45) NULL,
  `categoria` VARCHAR(200) NULL DEFAULT 'sportello didattico',
  `max_iscrizioni` INT NULL,
  `firmato` TINYINT NULL DEFAULT 0,
  `cancellato` TINYINT NULL DEFAULT 0,
  `online` TINYINT NULL DEFAULT 0,
  `note` TEXT NULL,
  `anno_scolastico_id` INT NOT NULL,
  `materia_id` INT NOT NULL,
  `docente_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_sportello_anno_scolastico1_idx` (`anno_scolastico_id` ASC),
  INDEX `fk_sportello_materia1_idx` (`materia_id` ASC),
  INDEX `fk_sportello_docente1_idx` (`docente_id` ASC),
  INDEX `date_INDEX` (`data` ASC),
  INDEX `firmato_INDEX` (`firmato` ASC),
  INDEX `cancellato_INDEX` (`cancellato` ASC),
  INDEX `categoria_INDEX` (`categoria` ASC),
  CONSTRAINT `fk_sportello_anno_scolastico1`
    FOREIGN KEY (`anno_scolastico_id`)
    REFERENCES `anno_scolastico` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_sportello_materia1`
    FOREIGN KEY (`materia_id`)
    REFERENCES `materia` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_sportello_docente1`
    FOREIGN KEY (`docente_id`)
    REFERENCES `docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `studente`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `studente` ;

CREATE TABLE IF NOT EXISTS `studente` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `cognome` VARCHAR(45) NULL,
  `nome` VARCHAR(45) NULL,
  `email` VARCHAR(200) NULL,
  `username` VARCHAR(45) NULL,
  `classe` VARCHAR(45) NULL,
  `anno` VARCHAR(45) NULL,
  PRIMARY KEY (`id`),
  INDEX `email_INDEX` (`email` ASC),
  INDEX `cognome_nome_INDEX` (`cognome` ASC, `nome` ASC),
  INDEX `username_INDEX` (`username` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `sportello_studente`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `sportello_studente` ;

CREATE TABLE IF NOT EXISTS `sportello_studente` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `iscritto` TINYINT NULL DEFAULT 1,
  `presente` TINYINT NULL,
  `argomento` VARCHAR(200) NULL,
  `note` TEXT NULL,
  `sportello_id` INT NOT NULL,
  `studente_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_sportello_studente_sportello1_idx` (`sportello_id` ASC),
  INDEX `fk_sportello_studente_studente1_idx` (`studente_id` ASC),
  INDEX `iscritto_INDEX` (`iscritto` ASC),
  CONSTRAINT `fk_sportello_studente_sportello1`
    FOREIGN KEY (`sportello_id`)
    REFERENCES `sportello` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_sportello_studente_studente1`
    FOREIGN KEY (`studente_id`)
    REFERENCES `studente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `bonus_assegnato`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `bonus_assegnato` ;

CREATE TABLE IF NOT EXISTS `bonus_assegnato` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `importo` DOUBLE NULL,
  `commento` VARCHAR(400) NULL,
  `docente_id` INT NOT NULL,
  `anno_scolastico_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_bonus_assegnato_docente1_idx` (`docente_id` ASC),
  INDEX `fk_bonus_assegnato_anno_scolastico1_idx` (`anno_scolastico_id` ASC),
  CONSTRAINT `fk_bonus_assegnato_docente1`
    FOREIGN KEY (`docente_id`)
    REFERENCES `docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_bonus_assegnato_anno_scolastico1`
    FOREIGN KEY (`anno_scolastico_id`)
    REFERENCES `anno_scolastico` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `importo`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `importo` ;

CREATE TABLE IF NOT EXISTS `importo` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `fuis` DOUBLE NULL,
  `bonus` DOUBLE NULL,
  `diaria` DOUBLE NULL,
  `fuis_clil` DOUBLE NULL,
  `importo_ore_con_studenti` DOUBLE NULL,
  `importo_ore_funzionali` DOUBLE NULL,
  `importo_ore_corsi_di_recupero` DOUBLE NULL,
  `importo_diaria_con_pernottamento` DOUBLE NULL,
  `importo_diaria_senza_pernottamento` DOUBLE NULL,
  `anno_scolastico_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_importo_anno_scolastico1_idx` (`anno_scolastico_id` ASC),
  CONSTRAINT `fk_importo_anno_scolastico1`
    FOREIGN KEY (`anno_scolastico_id`)
    REFERENCES `anno_scolastico` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `viaggio_diaria_prevista`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `viaggio_diaria_prevista` ;

CREATE TABLE IF NOT EXISTS `viaggio_diaria_prevista` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `descrizione` VARCHAR(200) NULL,
  `ore` DOUBLE NULL DEFAULT 0,
  `giorni_senza_pernottamento` INT NULL DEFAULT 0,
  `giorni_con_pernottamento` INT NULL DEFAULT 0,
  `ultima_modifica` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `anno_scolastico_id` INT NOT NULL,
  `docente_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_viaggio_diaria_prevista_anno_scolastico1_idx` (`anno_scolastico_id` ASC),
  INDEX `fk_viaggio_diaria_prevista_docente1_idx` (`docente_id` ASC),
  CONSTRAINT `fk_viaggio_diaria_prevista_anno_scolastico1`
    FOREIGN KEY (`anno_scolastico_id`)
    REFERENCES `anno_scolastico` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_viaggio_diaria_prevista_docente1`
    FOREIGN KEY (`docente_id`)
    REFERENCES `docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `viaggio_diaria_prevista_commento`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `viaggio_diaria_prevista_commento` ;

CREATE TABLE IF NOT EXISTS `viaggio_diaria_prevista_commento` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `commento` VARCHAR(400) NULL,
  `ore_originali` DOUBLE NULL DEFAULT 0,
  `giorni_senza_pernottamento_originali` INT NULL DEFAULT 0,
  `giorni_con_pernottamento_originali` INT NULL DEFAULT 0,
  `viaggio_diaria_prevista_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_viaggio_diaria_prevista_commento_viaggio_diaria_prevista_idx` (`viaggio_diaria_prevista_id` ASC),
  CONSTRAINT `fk_viaggio_diaria_prevista_commento_viaggio_diaria_prevista1`
    FOREIGN KEY (`viaggio_diaria_prevista_id`)
    REFERENCES `viaggio_diaria_prevista` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `viaggio_diaria_fatta`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `viaggio_diaria_fatta` ;

CREATE TABLE IF NOT EXISTS `viaggio_diaria_fatta` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `data_partenza` DATE NULL,
  `descrizione` VARCHAR(200) NULL,
  `ore` DOUBLE NULL DEFAULT 0,
  `giorni_senza_pernottamento` INT NULL DEFAULT 0,
  `giorni_con_pernottamento` INT NULL DEFAULT 0,
  `ultima_modifica` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `viaggio_diaria_prevista_id` INT NULL,
  `viaggio_id` INT NULL,
  `anno_scolastico_id` INT NOT NULL,
  `docente_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_viaggio_diaria_fatta_anno_scolastico1_idx` (`anno_scolastico_id` ASC),
  INDEX `fk_viaggio_diaria_fatta_docente1_idx` (`docente_id` ASC),
  CONSTRAINT `fk_viaggio_diaria_fatta_anno_scolastico1`
    FOREIGN KEY (`anno_scolastico_id`)
    REFERENCES `anno_scolastico` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_viaggio_diaria_fatta_docente1`
    FOREIGN KEY (`docente_id`)
    REFERENCES `docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `viaggio_diaria_fatta_commento`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `viaggio_diaria_fatta_commento` ;

CREATE TABLE IF NOT EXISTS `viaggio_diaria_fatta_commento` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `commento` VARCHAR(400) NULL,
  `ore_originali` DOUBLE NULL DEFAULT 0,
  `giorni_senza_pernottamento_originali` INT NULL DEFAULT 0,
  `giorni_con_pernottamento_originali` INT NULL DEFAULT 0,
  `viaggio_diaria_fatta_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_viaggio_diaria_fatta_commento_viaggio_diaria_fatta1_idx` (`viaggio_diaria_fatta_id` ASC),
  CONSTRAINT `fk_viaggio_diaria_fatta_commento_viaggio_diaria_fatta1`
    FOREIGN KEY (`viaggio_diaria_fatta_id`)
    REFERENCES `viaggio_diaria_fatta` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `sportello_categoria`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `sportello_categoria` ;

CREATE TABLE IF NOT EXISTS `sportello_categoria` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(200) NULL,
  `conteggio_ore_automatico` TINYINT NULL DEFAULT 1,
  `previsione_ore_automatico` TINYINT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  INDEX `nome_INDEX` (`nome` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `indirizzo`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `indirizzo` ;

CREATE TABLE IF NOT EXISTS `indirizzo` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(200) NULL,
  `nome_breve` VARCHAR(20) NULL,
  `biennio` TINYINT NULL DEFAULT 1,
  `triennio` TINYINT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  INDEX `biennio_INDEX` (`biennio` ASC),
  INDEX `triennio_INDEX` (`triennio` ASC),
  INDEX `nome_breve_INDEX` (`nome_breve` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `piano_di_lavoro`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `piano_di_lavoro` ;

CREATE TABLE IF NOT EXISTS `piano_di_lavoro` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `classe` INT NOT NULL DEFAULT 1,
  `sezione` VARCHAR(10) NOT NULL DEFAULT 'A',
  `stato` VARCHAR(45) NULL DEFAULT 'draft',
  `competenze` TEXT NULL,
  `note_aggiuntive` TEXT NULL,
  `template` TINYINT NULL DEFAULT 0,
  `creazione` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `ultima_modifica` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `indirizzo_id` INT NOT NULL,
  `materia_id` INT NOT NULL,
  `anno_scolastico_id` INT NOT NULL,
  `docente_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_piano_di_lavoro_indirizzo1_idx` (`indirizzo_id` ASC),
  INDEX `fk_piano_di_lavoro_materia1_idx` (`materia_id` ASC),
  INDEX `fk_piano_di_lavoro_anno_scolastico1_idx` (`anno_scolastico_id` ASC),
  INDEX `fk_piano_di_lavoro_docente1_idx` (`docente_id` ASC),
  INDEX `stato_INDEX` (`stato` ASC),
  INDEX `classe_INDEX` (`classe` ASC),
  INDEX `sezione_INDEX` (`sezione` ASC),
  INDEX `template_INDEX` (`template` ASC),
  CONSTRAINT `fk_piano_di_lavoro_indirizzo1`
    FOREIGN KEY (`indirizzo_id`)
    REFERENCES `indirizzo` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_piano_di_lavoro_materia1`
    FOREIGN KEY (`materia_id`)
    REFERENCES `materia` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_piano_di_lavoro_anno_scolastico1`
    FOREIGN KEY (`anno_scolastico_id`)
    REFERENCES `anno_scolastico` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_piano_di_lavoro_docente1`
    FOREIGN KEY (`docente_id`)
    REFERENCES `docente` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `piano_di_lavoro_contenuto`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `piano_di_lavoro_contenuto` ;

CREATE TABLE IF NOT EXISTS `piano_di_lavoro_contenuto` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `titolo` VARCHAR(200) NULL,
  `testo` TEXT NULL,
  `posizione` INT NULL,
  `piano_di_lavoro_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_piano_di_lavoro_contenuto_piano_di_lavoro1_idx` (`piano_di_lavoro_id` ASC),
  INDEX `posizione_INDEX` (`posizione` ASC),
  CONSTRAINT `fk_piano_di_lavoro_contenuto_piano_di_lavoro1`
    FOREIGN KEY (`piano_di_lavoro_id`)
    REFERENCES `piano_di_lavoro` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `piano_di_lavoro_metodologia`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `piano_di_lavoro_metodologia` ;

CREATE TABLE IF NOT EXISTS `piano_di_lavoro_metodologia` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(200) NULL,
  `descrizione` TEXT NULL,
  `attivo` TINYINT NULL DEFAULT 1,
  PRIMARY KEY (`id`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `piano_di_lavoro_usa_metodologia`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `piano_di_lavoro_usa_metodologia` ;

CREATE TABLE IF NOT EXISTS `piano_di_lavoro_usa_metodologia` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `piano_di_lavoro_id` INT NOT NULL,
  `piano_di_lavoro_metodologia_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_piano_di_lavoro_usa_metodologia_piano_di_lavoro1_idx` (`piano_di_lavoro_id` ASC),
  INDEX `fk_piano_di_lavoro_usa_metodologia_piano_di_lavoro_metodolo_idx` (`piano_di_lavoro_metodologia_id` ASC),
  CONSTRAINT `fk_piano_di_lavoro_usa_metodologia_piano_di_lavoro1`
    FOREIGN KEY (`piano_di_lavoro_id`)
    REFERENCES `piano_di_lavoro` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_piano_di_lavoro_usa_metodologia_piano_di_lavoro_metodologia1`
    FOREIGN KEY (`piano_di_lavoro_metodologia_id`)
    REFERENCES `piano_di_lavoro_metodologia` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `piano_di_lavoro_tic`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `piano_di_lavoro_tic` ;

CREATE TABLE IF NOT EXISTS `piano_di_lavoro_tic` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(200) NULL,
  `descrizione` TEXT NULL,
  `attivo` TINYINT NULL DEFAULT 1,
  PRIMARY KEY (`id`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `piano_di_lavoro_usa_tic`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `piano_di_lavoro_usa_tic` ;

CREATE TABLE IF NOT EXISTS `piano_di_lavoro_usa_tic` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `piano_di_lavoro_id` INT NOT NULL,
  `piano_di_lavoro_tic_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_piano_di_lavoro_usa_tic_piano_di_lavoro1_idx` (`piano_di_lavoro_id` ASC),
  INDEX `fk_piano_di_lavoro_usa_tic_piano_di_lavoro_tic1_idx` (`piano_di_lavoro_tic_id` ASC),
  CONSTRAINT `fk_piano_di_lavoro_usa_tic_piano_di_lavoro1`
    FOREIGN KEY (`piano_di_lavoro_id`)
    REFERENCES `piano_di_lavoro` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_piano_di_lavoro_usa_tic_piano_di_lavoro_tic1`
    FOREIGN KEY (`piano_di_lavoro_tic_id`)
    REFERENCES `piano_di_lavoro_tic` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `piano_di_lavoro_materiale`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `piano_di_lavoro_materiale` ;

CREATE TABLE IF NOT EXISTS `piano_di_lavoro_materiale` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(200) NULL,
  `descrizione` TEXT NULL,
  `attivo` TINYINT NULL DEFAULT 1,
  PRIMARY KEY (`id`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `piano_di_lavoro_usa_materiale`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `piano_di_lavoro_usa_materiale` ;

CREATE TABLE IF NOT EXISTS `piano_di_lavoro_usa_materiale` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `piano_di_lavoro_id` INT NOT NULL,
  `piano_di_lavoro_materiale_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_piano_di_lavoro_usa_materiale_piano_di_lavoro1_idx` (`piano_di_lavoro_id` ASC),
  INDEX `fk_piano_di_lavoro_usa_materiale_piano_di_lavoro_materiale1_idx` (`piano_di_lavoro_materiale_id` ASC),
  CONSTRAINT `fk_piano_di_lavoro_usa_materiale_piano_di_lavoro1`
    FOREIGN KEY (`piano_di_lavoro_id`)
    REFERENCES `piano_di_lavoro` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_piano_di_lavoro_usa_materiale_piano_di_lavoro_materiale1`
    FOREIGN KEY (`piano_di_lavoro_materiale_id`)
    REFERENCES `piano_di_lavoro_materiale` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
