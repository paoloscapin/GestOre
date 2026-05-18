ALTER TABLE `mastercom_studenti`
  ADD COLUMN `sesso` VARCHAR(10) NULL AFTER `data_nascita`;

CREATE TABLE IF NOT EXISTS `mastercom_gruppi` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(150) NOT NULL,
  `descrizione` TEXT NULL,
  `ambito` VARCHAR(30) NOT NULL DEFAULT 'eventi_messaggi',
  `attivo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mastercom_gruppi_nome` (`nome`),
  KEY `idx_mastercom_gruppi_attivo` (`attivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mastercom_gruppi_studenti` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_gruppo` INT NOT NULL,
  `mastercom_id_studente` INT NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mastercom_gruppo_studente` (`id_gruppo`, `mastercom_id_studente`),
  KEY `idx_mastercom_gruppi_studenti_studente` (`mastercom_id_studente`),
  CONSTRAINT `fk_mastercom_gruppi_studenti_gruppo`
    FOREIGN KEY (`id_gruppo`) REFERENCES `mastercom_gruppi` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
