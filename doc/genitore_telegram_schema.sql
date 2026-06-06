SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `genitore_telegram` (
  `idGenitore` INT NOT NULL,
  `telegram_chat_id` VARCHAR(50) NULL,
  `attivo` TINYINT(1) NOT NULL DEFAULT 1,
  `consenso_notifiche` TINYINT(1) NOT NULL DEFAULT 1,
  `ultimo_errore` TEXT NULL,
  `ultimo_errore_data` DATETIME NULL,
  PRIMARY KEY (`idGenitore`),
  KEY `idx_genitore_telegram_chat` (`telegram_chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `genitore_telegram_token` (
  `idToken` INT NOT NULL AUTO_INCREMENT,
  `idGenitore` INT NOT NULL,
  `token` VARCHAR(128) NOT NULL,
  `dataCreazione` DATETIME NOT NULL,
  `dataScadenza` DATETIME NULL,
  `usato` TINYINT(1) NOT NULL DEFAULT 0,
  `dataUso` DATETIME NULL,
  PRIMARY KEY (`idToken`),
  UNIQUE KEY `uq_genitore_telegram_token` (`token`),
  KEY `idx_genitore_telegram_token_genitore` (`idGenitore`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
