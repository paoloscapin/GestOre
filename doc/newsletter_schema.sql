SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `gestore_news_item` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `titolo` VARCHAR(255) NOT NULL,
  `contenuto` TEXT NOT NULL,
  `audience` VARCHAR(20) NOT NULL DEFAULT 'tutti',
  `channels` VARCHAR(50) NOT NULL DEFAULT 'mail,telegram',
  `data_novita` DATE NOT NULL,
  `created_by_user_id` INT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gestore_news_item_data` (`data_novita`),
  KEY `idx_gestore_news_item_audience` (`audience`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `gestore_newsletter` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `titolo` VARCHAR(255) NOT NULL,
  `intro_text` TEXT NULL,
  `body_text` LONGTEXT NOT NULL,
  `period_start` DATE NULL,
  `period_end` DATE NULL,
  `channels` VARCHAR(50) NOT NULL DEFAULT 'mail,telegram',
  `audience` VARCHAR(20) NOT NULL DEFAULT 'tutti',
  `telegram_sent_count` INT NOT NULL DEFAULT 0,
  `mail_sent_count` INT NOT NULL DEFAULT 0,
  `stato` VARCHAR(20) NOT NULL DEFAULT 'INVIATA',
  `created_by_user_id` INT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_gestore_newsletter_created` (`created_at`),
  KEY `idx_gestore_newsletter_sent` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
