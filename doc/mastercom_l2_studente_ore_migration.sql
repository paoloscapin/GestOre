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
