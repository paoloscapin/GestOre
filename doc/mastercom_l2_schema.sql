CREATE TABLE IF NOT EXISTS `mastercom_l2_classi_mbapp` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `mbapp_classe_id` INT(11) NULL,
  `mbapp_classe_nome` VARCHAR(100) NOT NULL,
  `nome_gruppo` VARCHAR(100) NOT NULL,
  `livello` VARCHAR(50) NULL,
  `gruppo` VARCHAR(20) NULL,
  `attivo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mastercom_l2_mbapp_classe_nome` (`mbapp_classe_nome`),
  KEY `idx_mastercom_l2_mbapp_classe_id` (`mbapp_classe_id`),
  KEY `idx_mastercom_l2_attivo` (`attivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `mastercom_l2_gruppo_studenti` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_l2_classe_mbapp` INT(11) NOT NULL,
  `mastercom_id_studente` INT(11) NOT NULL,
  `id_studente_gestore` INT(11) NULL,
  `data_inizio` DATE NOT NULL,
  `data_fine` DATE NULL,
  `attivo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mastercom_l2_studente_gruppo` (`id_l2_classe_mbapp`, `mastercom_id_studente`, `data_inizio`),
  KEY `idx_mastercom_l2_gruppo_studente` (`mastercom_id_studente`),
  KEY `idx_mastercom_l2_gruppo_studente_gestore` (`id_studente_gestore`),
  KEY `idx_mastercom_l2_gruppo_date` (`data_inizio`, `data_fine`),
  KEY `idx_mastercom_l2_gruppo_attivo` (`attivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `mastercom_l2_studente_ore` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_l2_classe_mbapp` INT(11) NOT NULL,
  `mastercom_id_studente` INT(11) NOT NULL,
  `giorno_settimana` TINYINT(1) NOT NULL,
  `ora_inizio` TIME NOT NULL,
  `attivo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mastercom_l2_studente_ora` (`id_l2_classe_mbapp`, `mastercom_id_studente`, `giorno_settimana`, `ora_inizio`),
  KEY `idx_mastercom_l2_studente_ore_studente` (`mastercom_id_studente`),
  KEY `idx_mastercom_l2_studente_ore_slot` (`id_l2_classe_mbapp`, `giorno_settimana`, `ora_inizio`),
  KEY `idx_mastercom_l2_studente_ore_attivo` (`attivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `mastercom_l2_appelli` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_l2_classe_mbapp` INT(11) NOT NULL,
  `data_giorno` DATE NOT NULL,
  `ora_inizio` TIME NOT NULL,
  `ora_fine` TIME NULL,
  `aula` VARCHAR(100) NULL,
  `id_docente_gestore` INT(11) NULL,
  `created_by_user_id` INT(11) NULL,
  `note` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mastercom_l2_appello_slot` (`id_l2_classe_mbapp`, `data_giorno`, `ora_inizio`),
  KEY `idx_mastercom_l2_appelli_data` (`data_giorno`),
  KEY `idx_mastercom_l2_appelli_docente` (`id_docente_gestore`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `mastercom_l2_appello_studenti` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_appello` INT(11) NOT NULL,
  `mastercom_id_studente` INT(11) NOT NULL,
  `ora_inizio` TIME NULL,
  `id_studente_gestore` INT(11) NULL,
  `stato` VARCHAR(30) NOT NULL DEFAULT 'PRESENTE',
  `note` TEXT NULL,
  `mastercom_action_state` VARCHAR(30) NULL,
  `mastercom_action_at` DATETIME NULL,
  `mastercom_action_error` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mastercom_l2_appello_studente_ora` (`id_appello`, `mastercom_id_studente`, `ora_inizio`),
  KEY `idx_mastercom_l2_appello_studente` (`mastercom_id_studente`),
  KEY `idx_mastercom_l2_appello_studente_gestore` (`id_studente_gestore`),
  KEY `idx_mastercom_l2_appello_stato` (`stato`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
