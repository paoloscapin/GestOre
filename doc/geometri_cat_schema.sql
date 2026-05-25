-- Schema per gestione esami CAT / Collegio Geometri
-- Modulo autonomo rispetto ai corsi didattica/corsi.php.

CREATE TABLE IF NOT EXISTS `geometri_esami` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `codice` VARCHAR(40) NOT NULL,
  `titolo` VARCHAR(200) NOT NULL,
  `descrizione` TEXT NULL,
  `anno_corso` TINYINT NOT NULL COMMENT '3, 4 o 5',
  `ordine` INT NOT NULL DEFAULT 0,
  `attivo` TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_geometri_esami_codice` (`codice`),
  KEY `idx_geometri_esami_anno` (`anno_corso`),
  KEY `idx_geometri_esami_attivo` (`attivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `geometri_sessioni` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_esame` INT NOT NULL,
  `id_anno_scolastico` INT NOT NULL,
  `data` DATETIME NOT NULL,
  `note` TEXT NULL,
  `stato` VARCHAR(30) NOT NULL DEFAULT 'bozza' COMMENT 'bozza, programmata, chiusa',
  `creato_il` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `aggiornato_il` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_geometri_sessioni_esame` (`id_esame`),
  KEY `idx_geometri_sessioni_anno` (`id_anno_scolastico`),
  KEY `idx_geometri_sessioni_data` (`data`),
  CONSTRAINT `fk_geometri_sessioni_esame`
    FOREIGN KEY (`id_esame`) REFERENCES `geometri_esami` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_geometri_sessioni_anno`
    FOREIGN KEY (`id_anno_scolastico`) REFERENCES `anno_scolastico` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `geometri_sessioni_classi` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_sessione` INT NOT NULL,
  `id_classe` INT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_geometri_sessione_classe` (`id_sessione`, `id_classe`),
  KEY `idx_geometri_sessioni_classi_classe` (`id_classe`),
  CONSTRAINT `fk_geometri_sessioni_classi_sessione`
    FOREIGN KEY (`id_sessione`) REFERENCES `geometri_sessioni` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_geometri_sessioni_classi_classe`
    FOREIGN KEY (`id_classe`) REFERENCES `classi` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `geometri_sessioni_docenti` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_sessione` INT NOT NULL,
  `id_docente` INT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_geometri_sessione_docente` (`id_sessione`, `id_docente`),
  KEY `idx_geometri_sessioni_docenti_docente` (`id_docente`),
  CONSTRAINT `fk_geometri_sessioni_docenti_sessione`
    FOREIGN KEY (`id_sessione`) REFERENCES `geometri_sessioni` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_geometri_sessioni_docenti_docente`
    FOREIGN KEY (`id_docente`) REFERENCES `docente` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `geometri_sessioni_esterni` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_sessione` INT NOT NULL,
  `id_utente` INT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_geometri_sessione_esterno` (`id_sessione`, `id_utente`),
  KEY `idx_geometri_sessioni_esterni_utente` (`id_utente`),
  CONSTRAINT `fk_geometri_sessioni_esterni_sessione`
    FOREIGN KEY (`id_sessione`) REFERENCES `geometri_sessioni` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_geometri_sessioni_esterni_utente`
    FOREIGN KEY (`id_utente`) REFERENCES `utente` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `geometri_sessioni_studenti` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_sessione` INT NOT NULL,
  `id_studente` INT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_geometri_sessione_studente_extra` (`id_sessione`, `id_studente`),
  KEY `idx_geometri_sessioni_studenti_studente` (`id_studente`),
  CONSTRAINT `fk_geometri_sessioni_studenti_sessione`
    FOREIGN KEY (`id_sessione`) REFERENCES `geometri_sessioni` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_geometri_sessioni_studenti_studente`
    FOREIGN KEY (`id_studente`) REFERENCES `studente` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `geometri_studenti_ciclo` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_studente` INT NOT NULL,
  `id_anno_ingresso` INT NULL,
  `stato` VARCHAR(30) NOT NULL DEFAULT 'attivo' COMMENT 'attivo, ritirato, trasferito, concluso',
  `data_stato` DATE NULL,
  `note` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_geometri_studente_ciclo` (`id_studente`),
  KEY `idx_geometri_studenti_ciclo_stato` (`stato`),
  KEY `idx_geometri_studenti_ciclo_anno` (`id_anno_ingresso`),
  CONSTRAINT `fk_geometri_studenti_ciclo_studente`
    FOREIGN KEY (`id_studente`) REFERENCES `studente` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_geometri_studenti_ciclo_anno`
    FOREIGN KEY (`id_anno_ingresso`) REFERENCES `anno_scolastico` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `geometri_esiti` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_sessione` INT NOT NULL,
  `id_studente` INT NOT NULL,
  `presente` TINYINT NOT NULL DEFAULT 1,
  `esito` VARCHAR(30) NOT NULL DEFAULT 'da_valutare' COMMENT 'da_valutare, superato, non_superato, assente, ritirato',
  `voto` DECIMAL(5,2) NULL,
  `note` TEXT NULL,
  `registrato_da_ruolo` VARCHAR(30) NULL,
  `registrato_da_id` INT NULL,
  `registrato_il` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_geometri_esito_sessione_studente` (`id_sessione`, `id_studente`),
  KEY `idx_geometri_esiti_studente` (`id_studente`),
  KEY `idx_geometri_esiti_esito` (`esito`),
  CONSTRAINT `fk_geometri_esiti_sessione`
    FOREIGN KEY (`id_sessione`) REFERENCES `geometri_sessioni` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_geometri_esiti_studente`
    FOREIGN KEY (`id_studente`) REFERENCES `studente` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
