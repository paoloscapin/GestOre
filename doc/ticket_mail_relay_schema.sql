SET NAMES utf8mb4;

ALTER TABLE `docente_telegram_relay`
ADD COLUMN `canale_apertura` VARCHAR(20) NOT NULL DEFAULT 'telegram' AFTER `ticket_code`,
ADD COLUMN `tipo_utente` VARCHAR(20) NOT NULL DEFAULT 'docente' AFTER `canale_apertura`,
ADD COLUMN `email_riferimento` VARCHAR(255) NULL AFTER `tipo_utente`,
ADD COLUMN `idStudente` INT NULL AFTER `idDocente`,
ADD COLUMN `idGenitore` INT NULL AFTER `idStudente`,
ADD COLUMN `utente_nome` VARCHAR(100) NULL AFTER `idGenitore`,
ADD COLUMN `utente_cognome` VARCHAR(100) NULL AFTER `utente_nome`,
ADD COLUMN `utente_email` VARCHAR(255) NULL AFTER `utente_cognome`;
