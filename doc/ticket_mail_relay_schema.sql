SET NAMES utf8mb4;

ALTER TABLE `docente_telegram_relay`
ADD COLUMN `canale_apertura` VARCHAR(20) NOT NULL DEFAULT 'telegram' AFTER `ticket_code`,
ADD COLUMN `email_riferimento` VARCHAR(255) NULL AFTER `canale_apertura`;
