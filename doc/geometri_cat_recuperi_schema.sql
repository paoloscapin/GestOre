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
