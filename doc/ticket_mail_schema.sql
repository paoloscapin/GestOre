SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `ticket_mail_import_log` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `message_uid` VARCHAR(190) NOT NULL,
  `message_id` VARCHAR(255) NULL,
  `from_email` VARCHAR(255) NULL,
  `to_addresses` TEXT NULL,
  `subject` VARCHAR(255) NULL,
  `idDocente` INT NULL,
  `idRelay` INT NULL,
  `ticket_code` VARCHAR(64) NULL,
  `esito` VARCHAR(32) NOT NULL,
  `nota` VARCHAR(255) NULL,
  `imported_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticket_mail_import_uid` (`message_uid`),
  KEY `idx_ticket_mail_import_message_id` (`message_id`),
  KEY `idx_ticket_mail_import_docente` (`idDocente`),
  KEY `idx_ticket_mail_import_relay` (`idRelay`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
