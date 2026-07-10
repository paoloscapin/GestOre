CREATE TABLE IF NOT EXISTS `iscrizioni_prime_pratiche` (
  `id` int NOT NULL AUTO_INCREMENT,
  `anno_scolastico` varchar(9) NOT NULL,
  `codice_domanda` varchar(30) DEFAULT NULL,
  `codice_sidi` varchar(30) DEFAULT NULL,
  `codice_giada` varchar(30) DEFAULT NULL,
  `codice_fiscale` varchar(16) NOT NULL,
  `cognome` varchar(100) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `sesso` char(1) DEFAULT NULL,
  `data_nascita` date DEFAULT NULL,
  `unita_scolastica` varchar(255) DEFAULT NULL,
  `corso_studi` varchar(255) DEFAULT NULL,
  `anno_corso` tinyint DEFAULT NULL,
  `mensa` varchar(50) DEFAULT NULL,
  `religione` varchar(50) DEFAULT NULL,
  `scelta_alternativa_religione` varchar(255) DEFAULT NULL,
  `richiesta_trasporto` varchar(50) DEFAULT NULL,
  `scelta_formativa` varchar(255) DEFAULT NULL,
  `certificazione_online` varchar(255) DEFAULT NULL,
  `email_studente` varchar(255) DEFAULT NULL,
  `telefono_studente` varchar(50) DEFAULT NULL,
  `email_genitore_1` varchar(255) DEFAULT NULL,
  `email_genitore_2` varchar(255) DEFAULT NULL,
  `telefono_genitore_1` varchar(50) DEFAULT NULL,
  `telefono_genitore_2` varchar(50) DEFAULT NULL,
  `responsabile_1_tipo` varchar(50) DEFAULT NULL,
  `responsabile_1_cognome` varchar(100) DEFAULT NULL,
  `responsabile_1_nome` varchar(100) DEFAULT NULL,
  `responsabile_1_codice_fiscale` varchar(16) DEFAULT NULL,
  `responsabile_2_tipo` varchar(50) DEFAULT NULL,
  `responsabile_2_cognome` varchar(100) DEFAULT NULL,
  `responsabile_2_nome` varchar(100) DEFAULT NULL,
  `responsabile_2_codice_fiscale` varchar(16) DEFAULT NULL,
  `token_hash` char(64) DEFAULT NULL,
  `token_last4` char(4) DEFAULT NULL,
  `token_created_at` datetime DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `stato` enum('importata','bozza','inviata','verificata','da_integrare','annullata') NOT NULL DEFAULT 'importata',
  `dati_confermati_json` mediumtext DEFAULT NULL,
  `raw_prime_json` mediumtext DEFAULT NULL,
  `raw_dsa_json` mediumtext DEFAULT NULL,
  `consiglio_orientativo` text DEFAULT NULL,
  `raw_anagrafica_json` mediumtext DEFAULT NULL,
  `note_interne` text DEFAULT NULL,
  `imported_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_iscrizioni_prime_anno_cf` (`anno_scolastico`, `codice_fiscale`),
  KEY `idx_iscrizioni_prime_codice_domanda` (`codice_domanda`),
  KEY `idx_iscrizioni_prime_token_hash` (`token_hash`),
  KEY `idx_iscrizioni_prime_stato` (`stato`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `iscrizioni_prime_documenti` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pratica_id` int NOT NULL,
  `tipo_documento` varchar(50) NOT NULL,
  `stato` enum('mancante','caricato','estratto','verificato','da_sostituire') NOT NULL DEFAULT 'mancante',
  `file_path` varchar(500) DEFAULT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `uploaded_at` datetime DEFAULT NULL,
  `extracted_json` mediumtext DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `note` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_iscrizioni_doc_tipo` (`pratica_id`, `tipo_documento`),
  KEY `idx_iscrizioni_doc_stato` (`stato`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tipi documento previsti:
-- pagella
-- diploma
-- certificazione_competenze
-- invalsi
-- documento_identita_studente
-- codice_fiscale_studente
-- documento_identita_genitore_1
-- codice_fiscale_genitore_1
-- documento_identita_genitore_2
-- codice_fiscale_genitore_2
-- attestazione_erogazione_liberale
-- altro

CREATE TABLE IF NOT EXISTS `iscrizioni_prime_voti` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pratica_id` int NOT NULL,
  `documento_id` int DEFAULT NULL,
  `materia` varchar(120) NOT NULL,
  `voto` decimal(4,2) DEFAULT NULL,
  `giudizio` varchar(255) DEFAULT NULL,
  `fonte` varchar(50) NOT NULL DEFAULT 'pagella',
  `verificato` tinyint NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_iscrizioni_voti_pratica` (`pratica_id`),
  KEY `idx_iscrizioni_voti_materia` (`materia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `iscrizioni_prime_import_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `created_by` varchar(255) DEFAULT NULL,
  `prime_filename` varchar(255) DEFAULT NULL,
  `dsa_filename` varchar(255) DEFAULT NULL,
  `anagrafica_filename` varchar(255) DEFAULT NULL,
  `righe_prime` int NOT NULL DEFAULT 0,
  `righe_dsa` int NOT NULL DEFAULT 0,
  `righe_anagrafica` int NOT NULL DEFAULT 0,
  `inserite` int NOT NULL DEFAULT 0,
  `aggiornate` int NOT NULL DEFAULT 0,
  `contatti_aggiornati` int NOT NULL DEFAULT 0,
  `contatti_ignorati` int NOT NULL DEFAULT 0,
  `errori_json` mediumtext DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
