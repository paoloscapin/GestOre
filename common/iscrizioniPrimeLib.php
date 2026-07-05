<?php

defined('GESTORE_BOOTSTRAP') || define('GESTORE_BOOTSTRAP', true);

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/scuoleIstitutiLib.php';
require_once __DIR__ . '/studentiAttributiRiservatiLib.php';

function iscrizioniPrimeEnsureSchema(): void
{
    scuoleIstitutiEnsureTable();

    dbExec("
        CREATE TABLE IF NOT EXISTS iscrizioni_prime_pratiche (
          id int NOT NULL AUTO_INCREMENT,
          anno_scolastico varchar(9) NOT NULL,
          codice_domanda varchar(30) DEFAULT NULL,
          codice_sidi varchar(30) DEFAULT NULL,
          codice_giada varchar(30) DEFAULT NULL,
          codice_fiscale varchar(16) NOT NULL,
          tipo_iscrizione varchar(20) NOT NULL DEFAULT 'prime',
          studente_interno tinyint NOT NULL DEFAULT 0,
          cognome varchar(100) NOT NULL,
          nome varchar(100) NOT NULL,
          sesso char(1) DEFAULT NULL,
          data_nascita date DEFAULT NULL,
          nazione_nascita varchar(100) DEFAULT NULL,
          provincia_nascita varchar(100) DEFAULT NULL,
          comune_nascita varchar(100) DEFAULT NULL,
          luogo_nascita varchar(150) DEFAULT NULL,
          cittadinanza varchar(100) DEFAULT NULL,
          nazione_residenza varchar(100) DEFAULT NULL,
          provincia_residenza varchar(100) DEFAULT NULL,
          sigla_provincia_residenza varchar(5) DEFAULT NULL,
          comune_residenza varchar(100) DEFAULT NULL,
          frazione_residenza varchar(100) DEFAULT NULL,
          cap_residenza varchar(10) DEFAULT NULL,
          indirizzo_residenza varchar(255) DEFAULT NULL,
          telefono_residenza varchar(100) DEFAULT NULL,
          scuola_provenienza varchar(255) DEFAULT NULL,
          anno_esame_licenza varchar(20) DEFAULT NULL,
          esito_esame_licenza varchar(100) DEFAULT NULL,
          voto_esame_licenza varchar(20) DEFAULT NULL,
          sezione_richiesta varchar(20) DEFAULT NULL,
          lingua_straniera_1 varchar(100) DEFAULT NULL,
          lingua_straniera_2 varchar(100) DEFAULT NULL,
          lingua_straniera_3 varchar(100) DEFAULT NULL,
          trattamento_immagini varchar(50) DEFAULT NULL,
          esami_integrativi_da_verificare tinyint NOT NULL DEFAULT 0,
          nulla_osta_richiesto tinyint NOT NULL DEFAULT 0,
          nulla_osta_data date DEFAULT NULL,
          carenze_formative_dichiarate enum('','no','si') NOT NULL DEFAULT '',
          carenze_formative_materie text DEFAULT NULL,
          carenze_formative_altro varchar(255) DEFAULT NULL,
          unita_scolastica varchar(255) DEFAULT NULL,
          corso_studi varchar(255) DEFAULT NULL,
          id_indirizzo_gestore int DEFAULT NULL,
          note_genitori_iscrizione text DEFAULT NULL,
          curvatura_design varchar(20) NOT NULL DEFAULT '',
          anno_corso tinyint DEFAULT NULL,
          mensa varchar(50) DEFAULT NULL,
          religione varchar(50) DEFAULT NULL,
          scelta_alternativa_religione varchar(255) DEFAULT NULL,
          richiesta_trasporto varchar(50) DEFAULT NULL,
          scelta_formativa varchar(255) DEFAULT NULL,
          certificazione_online varchar(255) DEFAULT NULL,
          email_studente varchar(255) DEFAULT NULL,
          telefono_studente varchar(50) DEFAULT NULL,
          email_genitore_1 varchar(255) DEFAULT NULL,
          email_genitore_2 varchar(255) DEFAULT NULL,
          telefono_genitore_1 varchar(50) DEFAULT NULL,
          telefono_genitore_2 varchar(50) DEFAULT NULL,
          responsabile_1_tipo varchar(50) DEFAULT NULL,
          responsabile_1_cognome varchar(100) DEFAULT NULL,
          responsabile_1_nome varchar(100) DEFAULT NULL,
          responsabile_1_codice_fiscale varchar(16) DEFAULT NULL,
          responsabile_2_tipo varchar(50) DEFAULT NULL,
          responsabile_2_cognome varchar(100) DEFAULT NULL,
          responsabile_2_nome varchar(100) DEFAULT NULL,
          responsabile_2_codice_fiscale varchar(16) DEFAULT NULL,
          token_hash char(64) DEFAULT NULL,
          token_last4 char(4) DEFAULT NULL,
          token_created_at datetime DEFAULT NULL,
          token_expires_at datetime DEFAULT NULL,
          stato enum('importata','bozza','inviata','verifica_iniziale_ok','verificata','da_integrare','annullata') NOT NULL DEFAULT 'importata',
          dati_confermati_json mediumtext DEFAULT NULL,
          novita_segreteria_at datetime DEFAULT NULL,
          novita_segreteria_messaggio varchar(255) DEFAULT NULL,
          tablet_scelto tinyint NOT NULL DEFAULT 0,
          tablet_stato varchar(30) NOT NULL DEFAULT '',
          tablet_gruppo varchar(50) DEFAULT NULL,
          tablet_posizione int DEFAULT NULL,
          tablet_acquistato tinyint NOT NULL DEFAULT 0,
          tablet_acquistato_at date DEFAULT NULL,
          tablet_proprio tinyint NOT NULL DEFAULT 0,
          tablet_ripescato_da_pratica_id int DEFAULT NULL,
          tablet_note text DEFAULT NULL,
          tablet_rinuncia_allegato_path text DEFAULT NULL,
          tablet_rinuncia_allegato_original_name text DEFAULT NULL,
          tablet_rinuncia_allegato_size int DEFAULT NULL,
          raw_prime_json mediumtext DEFAULT NULL,
          raw_dsa_json mediumtext DEFAULT NULL,
          raw_licenza_media_json mediumtext DEFAULT NULL,
          raw_anagrafica_json mediumtext DEFAULT NULL,
          raw_dati_aggiuntivi_json mediumtext DEFAULT NULL,
          note_interne text DEFAULT NULL,
          imported_at datetime NOT NULL,
          updated_at datetime NOT NULL,
          PRIMARY KEY (id),
          UNIQUE KEY idx_iscrizioni_prime_anno_tipo_cf (anno_scolastico, tipo_iscrizione, codice_fiscale),
          KEY idx_iscrizioni_prime_codice_domanda (codice_domanda),
          KEY idx_iscrizioni_prime_token_hash (token_hash),
          KEY idx_iscrizioni_prime_stato (stato)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    dbExec("
        CREATE TABLE IF NOT EXISTS iscrizioni_prime_documenti (
          id int NOT NULL AUTO_INCREMENT,
          pratica_id int NOT NULL,
          tipo_documento varchar(50) NOT NULL,
          stato enum('mancante','caricato','consegna_cartacea','estratto','verificato','da_sostituire') NOT NULL DEFAULT 'mancante',
          file_path varchar(500) DEFAULT NULL,
          original_name varchar(255) DEFAULT NULL,
          mime_type varchar(100) DEFAULT NULL,
          file_size int DEFAULT NULL,
          storage_type enum('LOCAL','DRIVE') NOT NULL DEFAULT 'LOCAL',
          drive_file_id varchar(255) DEFAULT NULL,
          drive_web_view_link varchar(500) DEFAULT NULL,
          drive_folder_id varchar(255) DEFAULT NULL,
          uploaded_at datetime DEFAULT NULL,
          extracted_json mediumtext DEFAULT NULL,
          verified_at datetime DEFAULT NULL,
          note text DEFAULT NULL,
          PRIMARY KEY (id),
          UNIQUE KEY idx_iscrizioni_doc_tipo (pratica_id, tipo_documento),
          KEY idx_iscrizioni_doc_stato (stato)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    dbExec("
        CREATE TABLE IF NOT EXISTS iscrizioni_prime_voti (
          id int NOT NULL AUTO_INCREMENT,
          pratica_id int NOT NULL,
          documento_id int DEFAULT NULL,
          materia varchar(120) NOT NULL,
          voto decimal(4,2) DEFAULT NULL,
          giudizio varchar(255) DEFAULT NULL,
          fonte varchar(50) NOT NULL DEFAULT 'pagella',
          verificato tinyint NOT NULL DEFAULT 0,
          created_at datetime NOT NULL,
          updated_at datetime NOT NULL,
          PRIMARY KEY (id),
          KEY idx_iscrizioni_voti_pratica (pratica_id),
          KEY idx_iscrizioni_voti_materia (materia)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    dbExec("
        CREATE TABLE IF NOT EXISTS iscrizioni_prime_cambio_scuola (
          id int NOT NULL AUTO_INCREMENT,
          pratica_id int NOT NULL,
          tipo_iscrizione varchar(20) NOT NULL DEFAULT 'prime',
          richiesta_data date DEFAULT NULL,
          canale varchar(30) NOT NULL DEFAULT 'mail',
          id_istituto_destinazione int DEFAULT NULL,
          scuola_destinazione varchar(255) DEFAULT NULL,
          indirizzo_destinazione varchar(255) DEFAULT NULL,
          colloquio_stato varchar(30) NOT NULL DEFAULT 'da_valutare',
          nulla_osta_stato varchar(30) NOT NULL DEFAULT 'da_richiedere',
          documenti_stato varchar(30) NOT NULL DEFAULT 'da_verificare',
          pratica_stato varchar(30) NOT NULL DEFAULT 'aperta',
          stato_pratica_precedente varchar(30) DEFAULT NULL,
          note text DEFAULT NULL,
          allegato_path varchar(500) DEFAULT NULL,
          allegato_original_name varchar(255) DEFAULT NULL,
          allegato_size int DEFAULT NULL,
          created_by varchar(255) DEFAULT NULL,
          created_at datetime NOT NULL,
          updated_at datetime NOT NULL,
          PRIMARY KEY (id),
          UNIQUE KEY idx_iscrizioni_cambio_pratica (pratica_id),
          KEY idx_iscrizioni_cambio_tipo (tipo_iscrizione),
          KEY idx_iscrizioni_cambio_stato (pratica_stato)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    dbExec("
        CREATE TABLE IF NOT EXISTS iscrizioni_prime_cambio_scuola_eventi (
          id int NOT NULL AUTO_INCREMENT,
          cambio_scuola_id int DEFAULT NULL,
          pratica_id int NOT NULL,
          tipo_iscrizione varchar(20) NOT NULL DEFAULT 'prime',
          richiesta_data date DEFAULT NULL,
          canale varchar(30) NOT NULL DEFAULT 'mail',
          id_istituto_destinazione int DEFAULT NULL,
          scuola_destinazione varchar(255) DEFAULT NULL,
          indirizzo_destinazione varchar(255) DEFAULT NULL,
          colloquio_stato varchar(30) NOT NULL DEFAULT 'da_valutare',
          nulla_osta_stato varchar(30) NOT NULL DEFAULT 'da_richiedere',
          documenti_stato varchar(30) NOT NULL DEFAULT 'da_verificare',
          pratica_stato varchar(30) NOT NULL DEFAULT 'aperta',
          note text DEFAULT NULL,
          allegato_path varchar(500) DEFAULT NULL,
          allegato_original_name varchar(255) DEFAULT NULL,
          allegato_size int DEFAULT NULL,
          created_by varchar(255) DEFAULT NULL,
          created_at datetime NOT NULL,
          PRIMARY KEY (id),
          KEY idx_iscrizioni_cambio_eventi_pratica (pratica_id),
          KEY idx_iscrizioni_cambio_eventi_cambio (cambio_scuola_id),
          KEY idx_iscrizioni_cambio_eventi_tipo (tipo_iscrizione)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    dbExec("
        CREATE TABLE IF NOT EXISTS iscrizioni_prime_eventi (
          id int NOT NULL AUTO_INCREMENT,
          pratica_id int NOT NULL,
          tipo_iscrizione varchar(20) NOT NULL DEFAULT 'prime',
          tipo_evento varchar(60) NOT NULL,
          titolo varchar(255) NOT NULL,
          stato_precedente varchar(30) DEFAULT NULL,
          stato_nuovo varchar(30) DEFAULT NULL,
          oggetto varchar(255) DEFAULT NULL,
          messaggio mediumtext DEFAULT NULL,
          dettagli_json mediumtext DEFAULT NULL,
          created_by varchar(255) DEFAULT NULL,
          created_at datetime NOT NULL,
          PRIMARY KEY (id),
          KEY idx_iscrizioni_eventi_pratica (pratica_id),
          KEY idx_iscrizioni_eventi_tipo (tipo_iscrizione),
          KEY idx_iscrizioni_eventi_evento (tipo_evento)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    dbExec("
        CREATE TABLE IF NOT EXISTS iscrizioni_prime_import_log (
          id int NOT NULL AUTO_INCREMENT,
          created_at datetime NOT NULL,
          created_by varchar(255) DEFAULT NULL,
          prime_filename varchar(255) DEFAULT NULL,
          dsa_filename varchar(255) DEFAULT NULL,
          anagrafica_filename varchar(255) DEFAULT NULL,
          licenza_media_filename varchar(255) DEFAULT NULL,
          dsa_school_filename varchar(255) DEFAULT NULL,
          righe_prime int NOT NULL DEFAULT 0,
          righe_dsa int NOT NULL DEFAULT 0,
          righe_anagrafica int NOT NULL DEFAULT 0,
          righe_licenza_media int NOT NULL DEFAULT 0,
          righe_dsa_school int NOT NULL DEFAULT 0,
          attributi_school_aggiornati int NOT NULL DEFAULT 0,
          attributi_school_non_agganciati int NOT NULL DEFAULT 0,
          inserite int NOT NULL DEFAULT 0,
          aggiornate int NOT NULL DEFAULT 0,
          contatti_aggiornati int NOT NULL DEFAULT 0,
          contatti_ignorati int NOT NULL DEFAULT 0,
          tipo_iscrizione varchar(20) NOT NULL DEFAULT 'prime',
          errori_json mediumtext DEFAULT NULL,
          PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    dbExec("
        CREATE TABLE IF NOT EXISTS iscrizioni_prime_mail_log (
          id int NOT NULL AUTO_INCREMENT,
          pratica_id int NOT NULL,
          recipient_email varchar(255) NOT NULL,
          account_email varchar(255) DEFAULT NULL,
          token_last4 char(4) DEFAULT NULL,
          stato enum('inviata','errore','bounce') NOT NULL,
          test_mode tinyint NOT NULL DEFAULT 0,
          transport varchar(50) DEFAULT NULL,
          gmail_message_id varchar(190) DEFAULT NULL,
          errore text DEFAULT NULL,
          bounce_type varchar(40) DEFAULT NULL,
          bounce_reason text DEFAULT NULL,
          bounce_message_id varchar(190) DEFAULT NULL,
          bounce_snippet text DEFAULT NULL,
          checked_at datetime DEFAULT NULL,
          bounced_at datetime DEFAULT NULL,
          sent_at datetime DEFAULT NULL,
          created_at datetime NOT NULL,
          PRIMARY KEY (id),
          KEY idx_iscrizioni_mail_pratica (pratica_id),
          KEY idx_iscrizioni_mail_recipient (recipient_email),
          KEY idx_iscrizioni_mail_account_day (account_email, sent_at),
          KEY idx_iscrizioni_mail_stato (stato)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    dbExec("
        CREATE TABLE IF NOT EXISTS iscrizioni_prime_custom_mail_log (
          id int NOT NULL AUTO_INCREMENT,
          pratica_id int NOT NULL,
          tipo_iscrizione varchar(20) NOT NULL DEFAULT 'prime',
          communication_key char(64) NOT NULL,
          recipient_email varchar(255) NOT NULL,
          account_email varchar(255) DEFAULT NULL,
          subject varchar(255) DEFAULT NULL,
          stato enum('inviata','errore','bounce') NOT NULL,
          test_mode tinyint NOT NULL DEFAULT 0,
          errore text DEFAULT NULL,
          sent_at datetime DEFAULT NULL,
          created_at datetime NOT NULL,
          PRIMARY KEY (id),
          UNIQUE KEY idx_iscrizioni_custom_mail_once (communication_key, pratica_id, recipient_email, test_mode),
          KEY idx_iscrizioni_custom_mail_pratica (pratica_id),
          KEY idx_iscrizioni_custom_mail_account_day (account_email, sent_at),
          KEY idx_iscrizioni_custom_mail_tipo (tipo_iscrizione)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    dbExec("
        CREATE TABLE IF NOT EXISTS iscrizioni_prime_mail_templates (
          id int NOT NULL AUTO_INCREMENT,
          tipo_iscrizione varchar(20) NOT NULL DEFAULT 'prime',
          subject varchar(255) DEFAULT NULL,
          body_html mediumtext DEFAULT NULL,
          updated_by varchar(255) DEFAULT NULL,
          updated_at datetime NOT NULL,
          PRIMARY KEY (id),
          UNIQUE KEY idx_iscrizioni_mail_template_tipo (tipo_iscrizione)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    dbExec("
        CREATE TABLE IF NOT EXISTS iscrizioni_prime_mail_bounce_unmatched (
          id int NOT NULL AUTO_INCREMENT,
          account_email varchar(255) NOT NULL,
          gmail_message_id varchar(190) NOT NULL,
          bounce_type varchar(40) DEFAULT NULL,
          bounce_reason text DEFAULT NULL,
          subject varchar(255) DEFAULT NULL,
          snippet text DEFAULT NULL,
          checked_at datetime NOT NULL,
          created_at datetime NOT NULL,
          PRIMARY KEY (id),
          UNIQUE KEY idx_iscrizioni_bounce_unmatched_msg (account_email, gmail_message_id),
          KEY idx_iscrizioni_bounce_unmatched_checked (checked_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    dbExec("
        CREATE TABLE IF NOT EXISTS iscrizioni_prime_mail_attachments (
          id int NOT NULL AUTO_INCREMENT,
          tipo_iscrizione varchar(20) NOT NULL DEFAULT 'prime',
          file_path varchar(500) NOT NULL,
          original_name varchar(255) NOT NULL,
          file_size int NOT NULL DEFAULT 0,
          uploaded_by varchar(255) DEFAULT NULL,
          uploaded_at datetime NOT NULL,
          PRIMARY KEY (id),
          KEY idx_iscrizioni_mail_attach_tipo (tipo_iscrizione)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    dbExec("
        CREATE TABLE IF NOT EXISTS iscrizioni_prime_config (
          config_key varchar(100) NOT NULL,
          config_value text DEFAULT NULL,
          updated_by varchar(255) DEFAULT NULL,
          updated_at datetime NOT NULL,
          PRIMARY KEY (config_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    dbExec("
        CREATE TABLE IF NOT EXISTS iscrizioni_contatti_variazioni (
          id int NOT NULL AUTO_INCREMENT,
          pratica_id int NOT NULL,
          tipo_iscrizione varchar(20) NOT NULL DEFAULT 'prime',
          campo varchar(80) NOT NULL,
          etichetta varchar(160) NOT NULL,
          valore_precedente varchar(255) DEFAULT NULL,
          valore_nuovo varchar(255) DEFAULT NULL,
          stato enum('da_lavorare','lavorata') NOT NULL DEFAULT 'da_lavorare',
          created_at datetime NOT NULL,
          processed_at datetime DEFAULT NULL,
          processed_by varchar(255) DEFAULT NULL,
          PRIMARY KEY (id),
          KEY idx_iscrizioni_contatti_pratica (pratica_id),
          KEY idx_iscrizioni_contatti_tipo_stato (tipo_iscrizione, stato),
          KEY idx_iscrizioni_contatti_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'responsabile_1_tipo', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN responsabile_1_tipo varchar(50) DEFAULT NULL AFTER telefono_genitore_2");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'tipo_iscrizione', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN tipo_iscrizione varchar(20) NOT NULL DEFAULT 'prime' AFTER codice_fiscale");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'studente_interno', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN studente_interno tinyint NOT NULL DEFAULT 0 AFTER tipo_iscrizione");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'email_studente', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN email_studente varchar(255) DEFAULT NULL AFTER certificazione_online");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'telefono_studente', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN telefono_studente varchar(50) DEFAULT NULL AFTER email_studente");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'nazione_nascita', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN nazione_nascita varchar(100) DEFAULT NULL AFTER data_nascita");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'provincia_nascita', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN provincia_nascita varchar(100) DEFAULT NULL AFTER nazione_nascita");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'comune_nascita', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN comune_nascita varchar(100) DEFAULT NULL AFTER provincia_nascita");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'luogo_nascita', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN luogo_nascita varchar(150) DEFAULT NULL AFTER comune_nascita");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'cittadinanza', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN cittadinanza varchar(100) DEFAULT NULL AFTER luogo_nascita");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'nazione_residenza', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN nazione_residenza varchar(100) DEFAULT NULL AFTER cittadinanza");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'provincia_residenza', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN provincia_residenza varchar(100) DEFAULT NULL AFTER nazione_residenza");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'sigla_provincia_residenza', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN sigla_provincia_residenza varchar(5) DEFAULT NULL AFTER provincia_residenza");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'comune_residenza', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN comune_residenza varchar(100) DEFAULT NULL AFTER sigla_provincia_residenza");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'frazione_residenza', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN frazione_residenza varchar(100) DEFAULT NULL AFTER comune_residenza");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'cap_residenza', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN cap_residenza varchar(10) DEFAULT NULL AFTER frazione_residenza");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'indirizzo_residenza', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN indirizzo_residenza varchar(255) DEFAULT NULL AFTER cap_residenza");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'telefono_residenza', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN telefono_residenza varchar(100) DEFAULT NULL AFTER indirizzo_residenza");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'scuola_provenienza', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN scuola_provenienza varchar(255) DEFAULT NULL AFTER telefono_residenza");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'anno_esame_licenza', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN anno_esame_licenza varchar(20) DEFAULT NULL AFTER scuola_provenienza");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'esito_esame_licenza', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN esito_esame_licenza varchar(100) DEFAULT NULL AFTER anno_esame_licenza");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'voto_esame_licenza', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN voto_esame_licenza varchar(20) DEFAULT NULL AFTER esito_esame_licenza");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'sezione_richiesta', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN sezione_richiesta varchar(20) DEFAULT NULL AFTER voto_esame_licenza");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'lingua_straniera_1', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN lingua_straniera_1 varchar(100) DEFAULT NULL AFTER sezione_richiesta");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'lingua_straniera_2', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN lingua_straniera_2 varchar(100) DEFAULT NULL AFTER lingua_straniera_1");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'lingua_straniera_3', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN lingua_straniera_3 varchar(100) DEFAULT NULL AFTER lingua_straniera_2");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'trattamento_immagini', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN trattamento_immagini varchar(50) DEFAULT NULL AFTER lingua_straniera_3");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'esami_integrativi_da_verificare', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN esami_integrativi_da_verificare tinyint NOT NULL DEFAULT 0 AFTER trattamento_immagini");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'nulla_osta_richiesto', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN nulla_osta_richiesto tinyint NOT NULL DEFAULT 0 AFTER esami_integrativi_da_verificare");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'nulla_osta_data', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN nulla_osta_data date DEFAULT NULL AFTER nulla_osta_richiesto");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'carenze_formative_dichiarate', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN carenze_formative_dichiarate enum('','no','si') NOT NULL DEFAULT '' AFTER nulla_osta_data");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'carenze_formative_materie', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN carenze_formative_materie text DEFAULT NULL AFTER carenze_formative_dichiarate");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'carenze_formative_altro', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN carenze_formative_altro varchar(255) DEFAULT NULL AFTER carenze_formative_materie");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'responsabile_1_cognome', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN responsabile_1_cognome varchar(100) DEFAULT NULL AFTER responsabile_1_tipo");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'responsabile_1_nome', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN responsabile_1_nome varchar(100) DEFAULT NULL AFTER responsabile_1_cognome");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'responsabile_1_codice_fiscale', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN responsabile_1_codice_fiscale varchar(16) DEFAULT NULL AFTER responsabile_1_nome");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'responsabile_2_tipo', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN responsabile_2_tipo varchar(50) DEFAULT NULL AFTER responsabile_1_codice_fiscale");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'responsabile_2_cognome', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN responsabile_2_cognome varchar(100) DEFAULT NULL AFTER responsabile_2_tipo");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'responsabile_2_nome', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN responsabile_2_nome varchar(100) DEFAULT NULL AFTER responsabile_2_cognome");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'responsabile_2_codice_fiscale', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN responsabile_2_codice_fiscale varchar(16) DEFAULT NULL AFTER responsabile_2_nome");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'raw_anagrafica_json', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN raw_anagrafica_json mediumtext DEFAULT NULL AFTER raw_dsa_json");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'raw_licenza_media_json', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN raw_licenza_media_json mediumtext DEFAULT NULL AFTER raw_dsa_json");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'raw_dati_aggiuntivi_json', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN raw_dati_aggiuntivi_json mediumtext DEFAULT NULL AFTER raw_anagrafica_json");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'novita_segreteria_at', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN novita_segreteria_at datetime DEFAULT NULL AFTER dati_confermati_json");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'novita_segreteria_messaggio', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN novita_segreteria_messaggio varchar(255) DEFAULT NULL AFTER novita_segreteria_at");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'tablet_scelto', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN tablet_scelto tinyint NOT NULL DEFAULT 0 AFTER novita_segreteria_messaggio");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'tablet_stato', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN tablet_stato varchar(30) NOT NULL DEFAULT '' AFTER tablet_scelto");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'tablet_gruppo', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN tablet_gruppo varchar(50) DEFAULT NULL AFTER tablet_stato");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'tablet_posizione', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN tablet_posizione int DEFAULT NULL AFTER tablet_gruppo");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'tablet_acquistato', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN tablet_acquistato tinyint NOT NULL DEFAULT 0 AFTER tablet_posizione");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'tablet_acquistato_at', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN tablet_acquistato_at date DEFAULT NULL AFTER tablet_acquistato");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'tablet_proprio', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN tablet_proprio tinyint NOT NULL DEFAULT 0 AFTER tablet_acquistato_at");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'tablet_ripescato_da_pratica_id', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN tablet_ripescato_da_pratica_id int DEFAULT NULL AFTER tablet_proprio");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'tablet_note', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN tablet_note text DEFAULT NULL AFTER tablet_ripescato_da_pratica_id");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'tablet_rinuncia_allegato_path', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN tablet_rinuncia_allegato_path text DEFAULT NULL AFTER tablet_note");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'tablet_rinuncia_allegato_original_name', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN tablet_rinuncia_allegato_original_name text DEFAULT NULL AFTER tablet_rinuncia_allegato_path");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'tablet_rinuncia_allegato_size', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN tablet_rinuncia_allegato_size int DEFAULT NULL AFTER tablet_rinuncia_allegato_original_name");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'id_indirizzo_gestore', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN id_indirizzo_gestore int DEFAULT NULL AFTER corso_studi");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'note_genitori_iscrizione', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN note_genitori_iscrizione text DEFAULT NULL AFTER id_indirizzo_gestore");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'curvatura_design', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN curvatura_design varchar(20) NOT NULL DEFAULT '' AFTER note_genitori_iscrizione");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'terza_media_pagella', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN terza_media_pagella decimal(4,2) DEFAULT NULL AFTER id_indirizzo_gestore");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'terza_voto_matematica', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN terza_voto_matematica decimal(4,2) DEFAULT NULL AFTER terza_media_pagella");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'terza_voto_italiano', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN terza_voto_italiano decimal(4,2) DEFAULT NULL AFTER terza_voto_matematica");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'terza_voto_capacita_relazionale', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN terza_voto_capacita_relazionale decimal(4,2) DEFAULT NULL AFTER terza_voto_italiano");
    iscrizioniPrimeNormalizeGestoreAddressIds();
    iscrizioniPrimeNormalizeTerzeDigitalScienceAddress();
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_import_log', 'anagrafica_filename', "ALTER TABLE iscrizioni_prime_import_log ADD COLUMN anagrafica_filename varchar(255) DEFAULT NULL AFTER dsa_filename");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_import_log', 'righe_anagrafica', "ALTER TABLE iscrizioni_prime_import_log ADD COLUMN righe_anagrafica int NOT NULL DEFAULT 0 AFTER righe_dsa");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_import_log', 'licenza_media_filename', "ALTER TABLE iscrizioni_prime_import_log ADD COLUMN licenza_media_filename varchar(255) DEFAULT NULL AFTER anagrafica_filename");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_import_log', 'righe_licenza_media', "ALTER TABLE iscrizioni_prime_import_log ADD COLUMN righe_licenza_media int NOT NULL DEFAULT 0 AFTER righe_anagrafica");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_import_log', 'dati_aggiuntivi_filename', "ALTER TABLE iscrizioni_prime_import_log ADD COLUMN dati_aggiuntivi_filename varchar(255) DEFAULT NULL AFTER licenza_media_filename");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_import_log', 'righe_dati_aggiuntivi', "ALTER TABLE iscrizioni_prime_import_log ADD COLUMN righe_dati_aggiuntivi int NOT NULL DEFAULT 0 AFTER righe_licenza_media");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_import_log', 'dsa_school_filename', "ALTER TABLE iscrizioni_prime_import_log ADD COLUMN dsa_school_filename varchar(255) DEFAULT NULL AFTER licenza_media_filename");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_import_log', 'righe_dsa_school', "ALTER TABLE iscrizioni_prime_import_log ADD COLUMN righe_dsa_school int NOT NULL DEFAULT 0 AFTER righe_licenza_media");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_import_log', 'attributi_school_aggiornati', "ALTER TABLE iscrizioni_prime_import_log ADD COLUMN attributi_school_aggiornati int NOT NULL DEFAULT 0 AFTER righe_dsa_school");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_import_log', 'attributi_school_non_agganciati', "ALTER TABLE iscrizioni_prime_import_log ADD COLUMN attributi_school_non_agganciati int NOT NULL DEFAULT 0 AFTER attributi_school_aggiornati");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_import_log', 'contatti_aggiornati', "ALTER TABLE iscrizioni_prime_import_log ADD COLUMN contatti_aggiornati int NOT NULL DEFAULT 0 AFTER aggiornate");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_import_log', 'contatti_ignorati', "ALTER TABLE iscrizioni_prime_import_log ADD COLUMN contatti_ignorati int NOT NULL DEFAULT 0 AFTER contatti_aggiornati");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_import_log', 'tipo_iscrizione', "ALTER TABLE iscrizioni_prime_import_log ADD COLUMN tipo_iscrizione varchar(20) NOT NULL DEFAULT 'prime' AFTER contatti_ignorati");
    iscrizioniPrimeEnsureColumn('iscrizioni_contatti_variazioni', 'processed_by', "ALTER TABLE iscrizioni_contatti_variazioni ADD COLUMN processed_by varchar(255) DEFAULT NULL AFTER processed_at");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_mail_log', 'test_mode', "ALTER TABLE iscrizioni_prime_mail_log ADD COLUMN test_mode tinyint NOT NULL DEFAULT 0 AFTER stato");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_mail_log', 'transport', "ALTER TABLE iscrizioni_prime_mail_log ADD COLUMN transport varchar(50) DEFAULT NULL AFTER test_mode");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_mail_log', 'gmail_message_id', "ALTER TABLE iscrizioni_prime_mail_log ADD COLUMN gmail_message_id varchar(190) DEFAULT NULL AFTER transport");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_mail_log', 'bounce_type', "ALTER TABLE iscrizioni_prime_mail_log ADD COLUMN bounce_type varchar(40) DEFAULT NULL AFTER errore");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_mail_log', 'bounce_reason', "ALTER TABLE iscrizioni_prime_mail_log ADD COLUMN bounce_reason text DEFAULT NULL AFTER bounce_type");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_mail_log', 'bounce_message_id', "ALTER TABLE iscrizioni_prime_mail_log ADD COLUMN bounce_message_id varchar(190) DEFAULT NULL AFTER bounce_reason");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_mail_log', 'bounce_snippet', "ALTER TABLE iscrizioni_prime_mail_log ADD COLUMN bounce_snippet text DEFAULT NULL AFTER bounce_message_id");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_mail_log', 'checked_at', "ALTER TABLE iscrizioni_prime_mail_log ADD COLUMN checked_at datetime DEFAULT NULL AFTER bounce_snippet");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_mail_log', 'bounced_at', "ALTER TABLE iscrizioni_prime_mail_log ADD COLUMN bounced_at datetime DEFAULT NULL AFTER checked_at");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_documenti', 'storage_type', "ALTER TABLE iscrizioni_prime_documenti ADD COLUMN storage_type enum('LOCAL','DRIVE') NOT NULL DEFAULT 'LOCAL' AFTER file_size");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_documenti', 'drive_file_id', "ALTER TABLE iscrizioni_prime_documenti ADD COLUMN drive_file_id varchar(255) DEFAULT NULL AFTER storage_type");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_documenti', 'drive_web_view_link', "ALTER TABLE iscrizioni_prime_documenti ADD COLUMN drive_web_view_link varchar(500) DEFAULT NULL AFTER drive_file_id");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_documenti', 'drive_folder_id', "ALTER TABLE iscrizioni_prime_documenti ADD COLUMN drive_folder_id varchar(255) DEFAULT NULL AFTER drive_web_view_link");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_cambio_scuola', 'tipo_iscrizione', "ALTER TABLE iscrizioni_prime_cambio_scuola ADD COLUMN tipo_iscrizione varchar(20) NOT NULL DEFAULT 'prime' AFTER pratica_id");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_cambio_scuola', 'richiesta_data', "ALTER TABLE iscrizioni_prime_cambio_scuola ADD COLUMN richiesta_data date DEFAULT NULL AFTER tipo_iscrizione");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_cambio_scuola', 'canale', "ALTER TABLE iscrizioni_prime_cambio_scuola ADD COLUMN canale varchar(30) NOT NULL DEFAULT 'mail' AFTER richiesta_data");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_cambio_scuola', 'id_istituto_destinazione', "ALTER TABLE iscrizioni_prime_cambio_scuola ADD COLUMN id_istituto_destinazione int DEFAULT NULL AFTER canale");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_cambio_scuola', 'scuola_destinazione', "ALTER TABLE iscrizioni_prime_cambio_scuola ADD COLUMN scuola_destinazione varchar(255) DEFAULT NULL AFTER canale");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_cambio_scuola', 'indirizzo_destinazione', "ALTER TABLE iscrizioni_prime_cambio_scuola ADD COLUMN indirizzo_destinazione varchar(255) DEFAULT NULL AFTER scuola_destinazione");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_cambio_scuola', 'colloquio_stato', "ALTER TABLE iscrizioni_prime_cambio_scuola ADD COLUMN colloquio_stato varchar(30) NOT NULL DEFAULT 'da_valutare' AFTER canale");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_cambio_scuola', 'nulla_osta_stato', "ALTER TABLE iscrizioni_prime_cambio_scuola ADD COLUMN nulla_osta_stato varchar(30) NOT NULL DEFAULT 'da_richiedere' AFTER colloquio_stato");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_cambio_scuola', 'documenti_stato', "ALTER TABLE iscrizioni_prime_cambio_scuola ADD COLUMN documenti_stato varchar(30) NOT NULL DEFAULT 'da_verificare' AFTER nulla_osta_stato");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_cambio_scuola', 'pratica_stato', "ALTER TABLE iscrizioni_prime_cambio_scuola ADD COLUMN pratica_stato varchar(30) NOT NULL DEFAULT 'aperta' AFTER documenti_stato");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_cambio_scuola', 'note', "ALTER TABLE iscrizioni_prime_cambio_scuola ADD COLUMN note text DEFAULT NULL AFTER pratica_stato");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_cambio_scuola', 'allegato_path', "ALTER TABLE iscrizioni_prime_cambio_scuola ADD COLUMN allegato_path varchar(500) DEFAULT NULL AFTER note");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_cambio_scuola', 'allegato_original_name', "ALTER TABLE iscrizioni_prime_cambio_scuola ADD COLUMN allegato_original_name varchar(255) DEFAULT NULL AFTER allegato_path");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_cambio_scuola', 'allegato_size', "ALTER TABLE iscrizioni_prime_cambio_scuola ADD COLUMN allegato_size int DEFAULT NULL AFTER allegato_original_name");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_cambio_scuola', 'created_by', "ALTER TABLE iscrizioni_prime_cambio_scuola ADD COLUMN created_by varchar(255) DEFAULT NULL AFTER allegato_size");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_cambio_scuola_eventi', 'cambio_scuola_id', "ALTER TABLE iscrizioni_prime_cambio_scuola_eventi ADD COLUMN cambio_scuola_id int DEFAULT NULL AFTER id");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_cambio_scuola_eventi', 'tipo_iscrizione', "ALTER TABLE iscrizioni_prime_cambio_scuola_eventi ADD COLUMN tipo_iscrizione varchar(20) NOT NULL DEFAULT 'prime' AFTER pratica_id");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_cambio_scuola_eventi', 'id_istituto_destinazione', "ALTER TABLE iscrizioni_prime_cambio_scuola_eventi ADD COLUMN id_istituto_destinazione int DEFAULT NULL AFTER canale");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_cambio_scuola_eventi', 'scuola_destinazione', "ALTER TABLE iscrizioni_prime_cambio_scuola_eventi ADD COLUMN scuola_destinazione varchar(255) DEFAULT NULL AFTER canale");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_cambio_scuola_eventi', 'indirizzo_destinazione', "ALTER TABLE iscrizioni_prime_cambio_scuola_eventi ADD COLUMN indirizzo_destinazione varchar(255) DEFAULT NULL AFTER scuola_destinazione");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_cambio_scuola_eventi', 'stato_pratica_precedente', "ALTER TABLE iscrizioni_prime_cambio_scuola_eventi ADD COLUMN stato_pratica_precedente varchar(30) DEFAULT NULL AFTER pratica_stato");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_cambio_scuola_eventi', 'allegato_path', "ALTER TABLE iscrizioni_prime_cambio_scuola_eventi ADD COLUMN allegato_path varchar(500) DEFAULT NULL AFTER note");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_cambio_scuola_eventi', 'allegato_original_name', "ALTER TABLE iscrizioni_prime_cambio_scuola_eventi ADD COLUMN allegato_original_name varchar(255) DEFAULT NULL AFTER allegato_path");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_cambio_scuola_eventi', 'allegato_size', "ALTER TABLE iscrizioni_prime_cambio_scuola_eventi ADD COLUMN allegato_size int DEFAULT NULL AFTER allegato_original_name");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_eventi', 'stato_precedente', "ALTER TABLE iscrizioni_prime_eventi ADD COLUMN stato_precedente varchar(30) DEFAULT NULL AFTER titolo");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_eventi', 'stato_nuovo', "ALTER TABLE iscrizioni_prime_eventi ADD COLUMN stato_nuovo varchar(30) DEFAULT NULL AFTER stato_precedente");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_eventi', 'oggetto', "ALTER TABLE iscrizioni_prime_eventi ADD COLUMN oggetto varchar(255) DEFAULT NULL AFTER stato_nuovo");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_eventi', 'messaggio', "ALTER TABLE iscrizioni_prime_eventi ADD COLUMN messaggio mediumtext DEFAULT NULL AFTER oggetto");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_eventi', 'dettagli_json', "ALTER TABLE iscrizioni_prime_eventi ADD COLUMN dettagli_json mediumtext DEFAULT NULL AFTER messaggio");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_eventi', 'created_by', "ALTER TABLE iscrizioni_prime_eventi ADD COLUMN created_by varchar(255) DEFAULT NULL AFTER dettagli_json");
    iscrizioniPrimeEnsureDocumentStatusEnum();
    iscrizioniPrimeEnsurePracticeStatusEnum();
    iscrizioniPrimeEnsureMailLogStatusEnum();
}

function iscrizioniPrimeEnsureColumn(string $table, string $column, string $alterSql): void
{
    $exists = dbGetValue("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = " . dbQ($table) . "
          AND COLUMN_NAME = " . dbQ($column) . "
    ");

    if (intval($exists) === 0) {
        dbExec($alterSql);
    }
}

function iscrizioniPrimeNormalizeTextForAddress(string $value): string
{
    $value = strtoupper(trim($value));
    $value = str_replace(['À','Á','È','É','Ì','Í','Ò','Ó','Ù','Ú'], ['A','A','E','E','I','I','O','O','U','U'], $value);
    $value = preg_replace('/[^A-Z0-9]+/u', ' ', $value) ?? $value;
    return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
}

function iscrizioniPrimeGestoreAddressOptions(): array
{
    return dbGetAll("SELECT id, nome FROM indirizzo WHERE id BETWEEN 1 AND 10 ORDER BY nome ASC") ?: [];
}

function iscrizioniPrimeGestoreAddressIdFromText(string $value): int
{
    $norm = iscrizioniPrimeNormalizeTextForAddress($value);
    if ($norm === '') {
        return 0;
    }
    $aliases = [
        'ENERGIA' => ['MECCANICA', 'ENERGIA'],
        'MECCANICA ENERGIA' => ['MECCANICA', 'ENERGIA'],
        'MECCANICA E MECCATRONICA ENERGIA' => ['MECCANICA', 'ENERGIA'],
        'CHIMICA E MATERIALI' => ['CHIMICA', 'MATERIALI'],
        'CHIMICA DEI MATERIALI' => ['CHIMICA', 'MATERIALI'],
        'AUTOMAZIONE' => ['AUTOMAZIONE'],
        'AUA' => ['AUTOMAZIONE'],
        'ELETTRONICA ELETTROTECNICA' => ['ELETTROTECNICA'],
        'ELETTROTECNICA' => ['ELETTROTECNICA'],
        'ELA' => ['ELETTROTECNICA'],
        'INFORMATICA' => ['INFORMATICA'],
        'TELECOMUNICAZIONI' => ['TELECOMUNICAZIONI'],
        'BIOTECNOLOGIE SANITARIE' => ['BIOTECNOLOGIE', 'SANITARIE'],
        'BIOTECNOLOGIE AMBIENTALI' => ['BIOTECNOLOGIE', 'AMBIENTALI'],
        'GRAFICA E COMUNICAZIONE' => ['GRAFICA', 'COMUNICAZIONE'],
        'DIGITAL SCIENCE' => ['DIGITAL', 'SCIENCE'],
    ];
    $needleParts = $aliases[$norm] ?? [$norm];
    foreach (iscrizioniPrimeGestoreAddressOptions() as $row) {
        $id = intval($row['id'] ?? 0);
        $nameNorm = iscrizioniPrimeNormalizeTextForAddress((string)($row['nome'] ?? ''));
        if ($id <= 0 || $nameNorm === '') {
            continue;
        }
        if ($nameNorm === $norm || strpos($nameNorm, $norm) !== false || strpos($norm, $nameNorm) !== false) {
            return $id;
        }
        $ok = true;
        foreach ($needleParts as $part) {
            $partNorm = iscrizioniPrimeNormalizeTextForAddress((string)$part);
            if ($partNorm === '' || strpos($nameNorm, $partNorm) === false) {
                $ok = false;
                break;
            }
        }
        if ($ok) {
            return $id;
        }
    }
    return 0;
}

function iscrizioniPrimeGestoreAddressIdFromPractice(array $practice): int
{
    $existing = intval($practice['id_indirizzo_gestore'] ?? 0);
    if ($existing > 0) {
        return $existing;
    }
    foreach (['corso_studi', 'scelta_formativa', 'corso', 'indirizzo'] as $field) {
        $id = iscrizioniPrimeGestoreAddressIdFromText((string)($practice[$field] ?? ''));
        if ($id > 0) {
            return $id;
        }
    }
    return 0;
}

function iscrizioniPrimeNormalizeGestoreAddressIds(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $rows = dbGetAll("
        SELECT id, corso_studi, scelta_formativa
        FROM iscrizioni_prime_pratiche
        WHERE tipo_iscrizione = 'terze'
          AND COALESCE(id_indirizzo_gestore, 0) = 0
    ") ?: [];
    foreach ($rows as $row) {
        $idIndirizzo = iscrizioniPrimeGestoreAddressIdFromPractice($row);
        if ($idIndirizzo <= 0) {
            continue;
        }
        dbExec("
            UPDATE iscrizioni_prime_pratiche
            SET id_indirizzo_gestore = " . dbI($idIndirizzo) . "
            WHERE id = " . dbI($row['id'] ?? 0) . "
            LIMIT 1
        ");
    }
}

function iscrizioniPrimeNormalizeTerzeDigitalScienceAddress(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $digitalScienceAddressId = iscrizioniPrimeGestoreAddressIdFromText('DIGITAL SCIENCE');
    if ($digitalScienceAddressId <= 0) {
        return;
    }

    $rows = dbGetAll("
        SELECT id, codice_fiscale, corso_studi, id_indirizzo_gestore
        FROM iscrizioni_prime_pratiche
        WHERE tipo_iscrizione = 'terze'
          AND (stato IS NULL OR stato <> 'annullata')
    ") ?: [];
    foreach ($rows as $row) {
        if (!iscrizioniPrimeIsPromotedFromSecondaDigitalScience((string)($row['codice_fiscale'] ?? ''))) {
            continue;
        }
        if (intval($row['id_indirizzo_gestore'] ?? 0) === $digitalScienceAddressId
            && strtoupper(trim((string)($row['corso_studi'] ?? ''))) === 'DIGITAL SCIENCE') {
            continue;
        }
        dbExec("
            UPDATE iscrizioni_prime_pratiche
            SET id_indirizzo_gestore = " . dbI($digitalScienceAddressId) . ",
                corso_studi = 'DIGITAL SCIENCE',
                scelta_formativa = 'DIGITAL SCIENCE',
                updated_at = NOW()
            WHERE id = " . dbI($row['id'] ?? 0) . "
            LIMIT 1
        ");
    }
}

function iscrizioniPrimeEnsureDocumentStatusEnum(): void
{
    $columnType = (string)dbGetValue("
        SELECT COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'iscrizioni_prime_documenti'
          AND COLUMN_NAME = 'stato'
        LIMIT 1
    ");

    if (strpos($columnType, 'consegna_cartacea') === false) {
        dbExec("ALTER TABLE iscrizioni_prime_documenti MODIFY COLUMN stato enum('mancante','caricato','consegna_cartacea','estratto','verificato','da_sostituire') NOT NULL DEFAULT 'mancante'");
    }
}

function iscrizioniPrimeEnsurePracticeStatusEnum(): void
{
    $columnType = (string)dbGetValue("
        SELECT COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'iscrizioni_prime_pratiche'
          AND COLUMN_NAME = 'stato'
        LIMIT 1
    ");

    if (strpos($columnType, 'verifica_iniziale_ok') === false) {
        dbExec("ALTER TABLE iscrizioni_prime_pratiche MODIFY COLUMN stato enum('importata','bozza','inviata','verifica_iniziale_ok','verificata','da_integrare','annullata') NOT NULL DEFAULT 'importata'");
    }
}

function iscrizioniPrimeEnsureMailLogStatusEnum(): void
{
    $columnType = (string)dbGetValue("
        SELECT COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'iscrizioni_prime_mail_log'
          AND COLUMN_NAME = 'stato'
        LIMIT 1
    ");

    if (strpos($columnType, 'bounce') === false) {
        dbExec("ALTER TABLE iscrizioni_prime_mail_log MODIFY COLUMN stato enum('inviata','errore','bounce') NOT NULL");
    }
}

function iscrizioniPrimeIsReceivedBySecretaryState(string $stato): bool
{
    return in_array($stato, ['inviata', 'verifica_iniziale_ok'], true);
}

function iscrizioniPrimeNormalizeTipoIscrizione($tipo): string
{
    $tipo = strtolower(trim((string)$tipo));
    return $tipo === 'terze' ? 'terze' : 'prime';
}

function iscrizioniPrimeTipoIscrizioneFromPratica(array $pratica): string
{
    return iscrizioniPrimeNormalizeTipoIscrizione($pratica['tipo_iscrizione'] ?? 'prime');
}

function iscrizioniPrimeTabletStatusLabel(string $status): string
{
    $labels = [
        '' => 'Non indicato',
        'richiesto' => 'Richiesto',
        'confermato' => 'Confermato',
        'escluso' => 'Escluso',
        'rinuncia' => 'Rinuncia',
    ];
    return $labels[$status] ?? $status;
}

function iscrizioniPrimeTabletGroupLabel(string $group): string
{
    $labels = [
        'ipad' => 'Classi tablet',
        'digital_science' => 'Digital Science',
    ];
    return $labels[$group] ?? $group;
}

function iscrizioniPrimeTabletRecordEvent(int $praticaId, string $title, array $details = []): void
{
    iscrizioniPrimeRecordEvent($praticaId, 'tablet', $title, [
        'dettagli' => $details,
    ]);
}

function iscrizioniPrimeTabletSetPurchase(int $praticaId, bool $purchased, string $note = ''): array
{
    iscrizioniPrimeEnsureSchema();
    $pratica = dbGetFirst("SELECT * FROM iscrizioni_prime_pratiche WHERE id = " . dbI($praticaId) . " LIMIT 1");
    if (!$pratica) {
        return ['ok' => false, 'message' => 'Pratica non trovata.'];
    }
    $dateSql = $purchased ? 'CURDATE()' : 'NULL';
    dbExec("
        UPDATE iscrizioni_prime_pratiche
        SET tablet_acquistato = " . dbI($purchased ? 1 : 0) . ",
            tablet_acquistato_at = " . $dateSql . ",
            tablet_proprio = 0,
            tablet_note = " . dbQ($note !== '' ? $note : ($pratica['tablet_note'] ?? null)) . ",
            updated_at = NOW()
        WHERE id = " . dbI($praticaId) . "
    ");
    iscrizioniPrimeTabletRecordEvent($praticaId, $purchased ? 'Tablet acquistato' : 'Tablet segnato non acquistato', [
        'note' => $note,
    ]);
    return ['ok' => true, 'message' => $purchased ? 'Acquisto tablet registrato.' : 'Acquisto tablet rimosso.'];
}

function iscrizioniPrimeTabletSetOwnDevice(int $praticaId, bool $ownDevice, string $note = ''): array
{
    iscrizioniPrimeEnsureSchema();
    $pratica = dbGetFirst("SELECT * FROM iscrizioni_prime_pratiche WHERE id = " . dbI($praticaId) . " LIMIT 1");
    if (!$pratica) {
        return ['ok' => false, 'message' => 'Pratica non trovata.'];
    }
    $existingNote = trim((string)($pratica['tablet_note'] ?? ''));
    if ($ownDevice && trim($note) === '') {
        $note = 'Tablet gia di proprieta/acquistato autonomamente dalla famiglia: il genitore lo portera direttamente a scuola per la configurazione.';
    }
    $noteToSave = trim($note) !== '' ? trim($note) : $existingNote;
    dbExec("
        UPDATE iscrizioni_prime_pratiche
        SET tablet_proprio = " . dbI($ownDevice ? 1 : 0) . ",
            tablet_acquistato = 0,
            tablet_acquistato_at = NULL,
            tablet_note = " . dbQ($noteToSave !== '' ? $noteToSave : null) . ",
            updated_at = NOW()
        WHERE id = " . dbI($praticaId) . "
        LIMIT 1
    ");
    iscrizioniPrimeTabletRecordEvent($praticaId, $ownDevice ? 'Tablet gia di proprieta' : 'Tablet proprieta famiglia rimosso', [
        'note' => $noteToSave,
    ]);
    return ['ok' => true, 'message' => $ownDevice ? 'Tablet gia di proprieta registrato.' : 'Segnalazione tablet gia di proprieta rimossa.'];
}

function iscrizioniPrimeTabletAttachmentDir(int $praticaId): string
{
    return iscrizioniPrimeUploadBaseDir() . '/iscrizioni_tablet_rinunce/' . intval($praticaId);
}

function iscrizioniPrimeTabletSaveRenounceAttachment(int $praticaId, array $file): ?array
{
    if (empty($file['tmp_name']) || intval($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (intval($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Caricamento PDF rinuncia non riuscito.');
    }
    $name = trim((string)($file['name'] ?? 'rinuncia-tablet.pdf'));
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        throw new RuntimeException('Allegare un file PDF per la richiesta di rinuncia.');
    }
    $dir = iscrizioniPrimeTabletAttachmentDir($praticaId);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Impossibile creare la cartella per la rinuncia tablet.');
    }
    $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);
    $targetName = date('Ymd_His') . '_' . ($safeName ?: 'rinuncia-tablet.pdf');
    $target = $dir . '/' . $targetName;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Impossibile salvare il PDF della rinuncia tablet.');
    }
    return [
        'absolute_path' => $target,
        'relative_path' => 'data/iscrizioni_tablet_rinunce/' . intval($praticaId) . '/' . $targetName,
        'original_name' => $name,
        'size' => intval(filesize($target) ?: ($file['size'] ?? 0)),
    ];
}

function iscrizioniPrimeTabletSetStatus(int $praticaId, int $tabletScelto, string $status, string $group = '', string $note = ''): array
{
    iscrizioniPrimeEnsureSchema();
    $allowed = ['', 'richiesto', 'confermato', 'escluso', 'rinuncia'];
    if (!in_array($status, $allowed, true)) {
        return ['ok' => false, 'message' => 'Stato tablet non valido.'];
    }
    $group = in_array($group, ['ipad', 'digital_science'], true) ? $group : '';
    dbExec("
        UPDATE iscrizioni_prime_pratiche
        SET tablet_scelto = " . dbI($tabletScelto ? 1 : 0) . ",
            tablet_stato = " . dbQ($tabletScelto ? $status : '') . ",
            tablet_gruppo = " . dbQ($tabletScelto ? ($group !== '' ? $group : null) : null) . ",
            tablet_acquistato = CASE WHEN " . dbI($tabletScelto ? 1 : 0) . " = 1 AND " . dbQ($status) . " = 'confermato' THEN tablet_acquistato ELSE 0 END,
            tablet_acquistato_at = CASE WHEN " . dbI($tabletScelto ? 1 : 0) . " = 1 AND " . dbQ($status) . " = 'confermato' THEN tablet_acquistato_at ELSE NULL END,
            tablet_proprio = CASE WHEN " . dbI($tabletScelto ? 1 : 0) . " = 1 AND " . dbQ($status) . " = 'confermato' THEN tablet_proprio ELSE 0 END,
            tablet_note = " . dbQ($note) . ",
            updated_at = NOW()
        WHERE id = " . dbI($praticaId) . "
        LIMIT 1
    ");
    iscrizioniPrimeTabletRecordEvent($praticaId, 'Stato tablet aggiornato manualmente', [
        'tablet_scelto' => $tabletScelto ? 1 : 0,
        'tablet_stato' => $status,
        'tablet_gruppo' => $group,
        'note' => $note,
    ]);
    return ['ok' => true, 'message' => 'Stato tablet aggiornato senza ripescaggio.'];
}

function iscrizioniPrimeTabletRenounce(int $praticaId, string $note = '', ?array $attachment = null, bool $sendMail = false, string $mailSubject = '', string $mailMessage = '', string $mailSignature = '', ?array $selectedRecipients = null): array
{
    iscrizioniPrimeEnsureSchema();
    $pratica = dbGetFirst("SELECT * FROM iscrizioni_prime_pratiche WHERE id = " . dbI($praticaId) . " LIMIT 1");
    if (!$pratica) {
        return ['ok' => false, 'message' => 'Pratica non trovata.'];
    }

    $attachmentData = $attachment ? iscrizioniPrimeTabletSaveRenounceAttachment($praticaId, $attachment) : null;

    dbExec("START TRANSACTION");
    dbExec("
        UPDATE iscrizioni_prime_pratiche
        SET tablet_scelto = 1,
            tablet_stato = 'rinuncia',
            tablet_acquistato = 0,
            tablet_acquistato_at = NULL,
            tablet_proprio = 0,
            tablet_note = " . dbQ($note) . ",
            tablet_rinuncia_allegato_path = " . dbQ($attachmentData['relative_path'] ?? ($pratica['tablet_rinuncia_allegato_path'] ?? null)) . ",
            tablet_rinuncia_allegato_original_name = " . dbQ($attachmentData['original_name'] ?? ($pratica['tablet_rinuncia_allegato_original_name'] ?? null)) . ",
            tablet_rinuncia_allegato_size = " . dbQ($attachmentData['size'] ?? ($pratica['tablet_rinuncia_allegato_size'] ?? null)) . ",
            updated_at = NOW()
        WHERE id = " . dbI($praticaId) . "
    ");

    $replacement = dbGetFirst("
        SELECT *
        FROM iscrizioni_prime_pratiche
        WHERE tipo_iscrizione = 'prime'
          AND tablet_scelto = 1
          AND tablet_stato = 'escluso'
        ORDER BY tablet_posizione ASC, cognome ASC, nome ASC
        LIMIT 1
    ");

    if ($replacement) {
        $replacementPosition = intval(dbGetValue("
            SELECT COALESCE(MAX(tablet_posizione), 82)
            FROM iscrizioni_prime_pratiche
            WHERE tipo_iscrizione = 'prime'
              AND tablet_scelto = 1
              AND tablet_stato = 'confermato'
              AND COALESCE(tablet_gruppo, '') = 'ipad'
              AND COALESCE(tablet_posizione, 0) > 0
        ") ?? 82) + 1;
        dbExec("
            UPDATE iscrizioni_prime_pratiche
            SET tablet_stato = 'confermato',
                tablet_gruppo = COALESCE(NULLIF(tablet_gruppo, ''), 'ipad'),
                tablet_posizione = " . dbI($replacementPosition) . ",
                tablet_ripescato_da_pratica_id = " . dbI($praticaId) . ",
                tablet_note = CONCAT(COALESCE(tablet_note, ''), " . dbQ(($note !== '' ? "\n" : '') . 'Ripescato per rinuncia di ' . trim((string)($pratica['cognome'] ?? '') . ' ' . (string)($pratica['nome'] ?? ''))) . "),
                updated_at = NOW()
            WHERE id = " . dbI($replacement['id'] ?? 0) . "
        ");
        $replacement['tablet_posizione'] = $replacementPosition;
    }
    dbExec("COMMIT");

    iscrizioniPrimeTabletRecordEvent($praticaId, 'Rinuncia tablet registrata', [
        'note' => $note,
        'allegato' => $attachmentData['relative_path'] ?? ($pratica['tablet_rinuncia_allegato_path'] ?? ''),
        'ripescato_id' => intval($replacement['id'] ?? 0),
    ]);
    if ($replacement) {
        iscrizioniPrimeTabletRecordEvent((int)$replacement['id'], 'Ripescato per classi tablet', [
            'rinuncia_pratica_id' => $praticaId,
            'rinuncia_studente' => trim((string)($pratica['cognome'] ?? '') . ' ' . (string)($pratica['nome'] ?? '')),
            'posizione' => intval($replacement['tablet_posizione'] ?? 0),
        ]);
    }

    $mailResult = null;
    if ($sendMail) {
        $freshPratica = dbGetFirst("SELECT * FROM iscrizioni_prime_pratiche WHERE id = " . dbI($praticaId) . " LIMIT 1") ?: $pratica;
        $attachments = [];
        $attachmentPath = trim((string)($attachmentData['absolute_path'] ?? ''));
        if ($attachmentPath === '' && !empty($freshPratica['tablet_rinuncia_allegato_path'])) {
            $path = realpath(__DIR__ . '/../' . (string)$freshPratica['tablet_rinuncia_allegato_path']);
            $base = realpath(iscrizioniPrimeUploadBaseDir() . '/iscrizioni_tablet_rinunce');
            if ($path && $base && strpos($path, $base) === 0 && is_file($path)) {
                $attachmentPath = $path;
            }
        }
        if ($attachmentPath !== '') {
            $attachments[] = $attachmentPath;
        }
        $mailResult = iscrizioniPrimeSendCustomPracticeMail($freshPratica, $mailSubject, $mailMessage, $mailSignature, $selectedRecipients, $attachments, 'mail_rinuncia_tablet');
    }

    return [
        'ok' => true,
        'message' => $replacement
            ? 'Rinuncia registrata. Ripescato ' . trim((string)($replacement['cognome'] ?? '') . ' ' . (string)($replacement['nome'] ?? '')) . ': inviare avviso ai genitori.'
            : 'Rinuncia registrata. Nessun escluso disponibile per il ripescaggio.',
        'mail' => $mailResult,
        'replacement' => $replacement ? [
            'id' => intval($replacement['id'] ?? 0),
            'cognome' => (string)($replacement['cognome'] ?? ''),
            'nome' => (string)($replacement['nome'] ?? ''),
            'codice_fiscale' => (string)($replacement['codice_fiscale'] ?? ''),
            'tablet_posizione' => intval($replacement['tablet_posizione'] ?? 0),
        ] : null,
    ];
}

function iscrizioniPrimeDocumentTypes($tipo = 'prime'): array
{
    if (is_array($tipo)) {
        $tipo = iscrizioniPrimeTipoIscrizioneFromPratica($tipo);
    } else {
        $tipo = iscrizioniPrimeNormalizeTipoIscrizione($tipo);
    }

    if ($tipo === 'terze') {
        return [
            'pagella_seconda' => 'Pagella finale della classe seconda',
            'documento_cf_studente' => 'Carta identita fronte/retro studente con codice fiscale',
            'documento_cf_genitore_1' => 'Carta identita fronte/retro responsabile 1 con codice fiscale',
            'documento_cf_genitore_2' => 'Carta identita fronte/retro responsabile 2 con codice fiscale',
            'attestazione_erogazione_liberale' => 'Attestazione erogazione liberale PagoPA 50 euro',
            'altro' => 'Altro documento',
        ];
    }

    return [
        'pagella' => 'Pagella',
        'diploma' => 'Diploma / licenza conclusiva',
        'certificazione_competenze' => 'Certificazione delle competenze',
        'invalsi' => 'INVALSI',
        'documento_identita_studente' => 'Documento di identita dello studente',
        'codice_fiscale_studente' => 'Codice fiscale dello studente',
        'documento_identita_genitore_1' => 'Documento di identita del responsabile 1',
        'codice_fiscale_genitore_1' => 'Codice fiscale del responsabile 1',
        'documento_identita_genitore_2' => 'Documento di identita del responsabile 2',
        'codice_fiscale_genitore_2' => 'Codice fiscale del responsabile 2',
        'attestazione_erogazione_liberale' => 'Attestazione erogazione liberale PagoPA 50 euro',
        'altro' => 'Altro documento',
    ];
}

function iscrizioniPrimeSecretaryDocumentTypes($tipo = 'prime'): array
{
    if (is_array($tipo)) {
        $tipo = iscrizioniPrimeTipoIscrizioneFromPratica($tipo);
    } else {
        $tipo = iscrizioniPrimeNormalizeTipoIscrizione($tipo);
    }

    if ($tipo !== 'terze') {
        return [];
    }

    return [
        'nulla_osta_scuola_provenienza' => 'Nulla osta ricevuto dalla scuola di provenienza',
        'carenze_formative_scuola_provenienza' => 'Comunicazione carenze formative ricevuta dalla scuola di provenienza',
    ];
}

function iscrizioniPrimeSecretaryAllowedDocumentTypes(array $pratica): array
{
    return array_merge(iscrizioniPrimeDocumentTypes($pratica), iscrizioniPrimeSecretaryDocumentTypes($pratica));
}

function iscrizioniPrimeUploadBaseDir(): string
{
    return realpath(__DIR__ . '/../data') ?: (__DIR__ . '/../data');
}

function iscrizioniPrimeUploadDir(int $praticaId): string
{
    return iscrizioniPrimeUploadBaseDir() . '/iscrizioni_prime_uploads/' . intval($praticaId);
}

function iscrizioniPrimeCambioScuolaDir(int $praticaId): string
{
    return iscrizioniPrimeUploadBaseDir() . '/iscrizioni_cambio_scuola/' . intval($praticaId);
}

function iscrizioniPrimeDriveEnabled(): bool
{
    try {
        require_once __DIR__ . '/../api/googleDriveLib.php';
        $cfg = googleDriveGetConfig();
        if (empty($cfg->enabled)) {
            return false;
        }

        return !empty($cfg->iscrizioniPrimeEnabled);
    } catch (Throwable $e) {
        return false;
    }
}

function iscrizioniPrimeDriveSafeName(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/[\\\\\/:*?"<>|]+/', '-', $value);
    $value = preg_replace('/\s+/', ' ', (string)$value);
    return trim((string)$value, " .\t\n\r\0\x0B");
}

function iscrizioniPrimeDriveStudentFolderName(array $pratica): string
{
    $parts = [
        trim((string)($pratica['cognome'] ?? '')),
        trim((string)($pratica['nome'] ?? '')),
    ];
    $name = trim(implode(' ', array_filter($parts)));
    $cf = strtoupper(trim((string)($pratica['codice_fiscale'] ?? '')));
    if ($cf !== '') {
        $name .= ' - ' . $cf;
    }

    return iscrizioniPrimeDriveSafeName($name !== '' ? $name : ('Pratica ' . intval($pratica['id'] ?? 0)));
}

function iscrizioniPrimeGeneratedPdfBaseName(array $pratica, string $label): string
{
    $parts = [
        strtoupper(trim((string)$label)),
        strtoupper(trim((string)($pratica['cognome'] ?? ''))),
        strtoupper(trim((string)($pratica['nome'] ?? ''))),
    ];
    $base = trim(implode(' ', array_filter($parts)));
    if ($base === '') {
        $base = 'DOCUMENTO PRATICA ' . intval($pratica['id'] ?? 0);
    }

    return iscrizioniPrimeDriveSafeName($base);
}

function iscrizioniPrimeUniquePdfFileName(string $dir, string $baseName): string
{
    $baseName = iscrizioniPrimeDriveSafeName($baseName);
    if ($baseName === '') {
        $baseName = 'DOCUMENTO';
    }

    $fileName = $baseName . '.pdf';
    if (!file_exists($dir . '/' . $fileName)) {
        return $fileName;
    }

    return $baseName . ' ' . date('Ymd His') . '.pdf';
}

function iscrizioniPrimeDriveFolderId(array $pratica): string
{
    require_once __DIR__ . '/../api/googleDriveLib.php';

    $cfg = googleDriveGetConfig();
    $tipoIscrizione = iscrizioniPrimeTipoIscrizioneFromPratica($pratica);
    if ($tipoIscrizione === 'terze') {
        $rootFolderId = trim((string)($cfg->iscrizioniTerzeFolderId ?? ''));
        $rootFolderName = trim((string)($cfg->iscrizioniTerzeFolderName ?? 'Iscrizioni terze'));
    } else {
        $rootFolderId = trim((string)($cfg->iscrizioniPrimeFolderId ?? ''));
        $rootFolderName = trim((string)($cfg->iscrizioniPrimeFolderName ?? 'Iscrizioni prime'));
    }
    if ($rootFolderId === '') {
        $rootFolderId = googleDriveFindFolderByName($rootFolderName);
        if ($rootFolderId === '') {
            $rootFolderId = googleDriveCreateFolder($rootFolderName);
        }
    }
    if ($rootFolderId === '') {
        throw new RuntimeException('Impossibile trovare o creare la cartella Drive delle iscrizioni ' . $tipoIscrizione . '.');
    }

    $anno = iscrizioniPrimeDriveSafeName((string)($pratica['anno_scolastico'] ?? ''));
    if ($anno === '') {
        $anno = date('Y') . '-' . substr((string)(intval(date('Y')) + 1), -2);
    }

    $annoFolderId = googleDriveGetOrCreateFolderInParent($anno, $rootFolderId);
    return googleDriveGetOrCreateFolderInParent(iscrizioniPrimeDriveStudentFolderName($pratica), $annoFolderId);
}

function iscrizioniPrimeDriveFileName(array $pratica, string $tipo, string $label): string
{
    return iscrizioniPrimeGeneratedPdfBaseName($pratica, $label) . '.pdf';
}

function iscrizioniPrimeNormalizeHeader(array $header): array
{
    $seen = [];
    $normalized = [];

    foreach ($header as $index => $name) {
        $name = trim(iscrizioniPrimeUtf8((string)$name));
        $name = preg_replace('/^\xEF\xBB\xBF/', '', $name);
        $name = preg_replace('/^\x{FEFF}/u', '', $name);

        if ($name === '') {
            $name = 'COLONNA_' . ($index + 1);
        }

        $base = $name;
        if (isset($seen[$base])) {
            $seen[$base]++;
            $name = $base . '__' . $seen[$base];
        } else {
            $seen[$base] = 1;
        }

        $normalized[] = $name;
    }

    return $normalized;
}

function iscrizioniPrimeUtf8(string $value): string
{
    if ($value === '') {
        return '';
    }
    if (function_exists('mb_check_encoding') && mb_check_encoding($value, 'UTF-8')) {
        return $value;
    }
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($value, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
    }
    $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
    if ($converted !== false) {
        return $converted;
    }
    return preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $value) ?? '';
}

function iscrizioniPrimeReadCsv(string $path): array
{
    $fp = fopen($path, 'r');

    if (!$fp) {
        throw new RuntimeException('Impossibile leggere il file CSV.');
    }

    $firstLine = fgets($fp);
    if ($firstLine === false) {
        fclose($fp);
        throw new RuntimeException('Intestazione CSV non valida.');
    }
    $commaCount = substr_count($firstLine, ',');
    $semicolonCount = substr_count($firstLine, ';');
    $delimiter = $semicolonCount > $commaCount ? ';' : ',';
    rewind($fp);

    $header = fgetcsv($fp, 0, $delimiter);
    if (!$header) {
        fclose($fp);
        throw new RuntimeException('Intestazione CSV non valida.');
    }

    $header = iscrizioniPrimeNormalizeHeader($header);
    $rows = [];

    while (($row = fgetcsv($fp, 0, $delimiter)) !== false) {
        if (!count(array_filter($row, fn($value) => trim((string)$value) !== ''))) {
            continue;
        }

        $assoc = [];
        foreach ($header as $index => $name) {
            $assoc[$name] = trim(iscrizioniPrimeUtf8((string)($row[$index] ?? '')));
        }
        $rows[] = $assoc;
    }

    fclose($fp);

    return $rows;
}

function iscrizioniPrimeDate(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $dt = DateTime::createFromFormat('d/m/Y', $value);
    if ($dt instanceof DateTime) {
        return $dt->format('Y-m-d');
    }

    foreach (['Y-m-d H:i:s', 'Y-m-d'] as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d');
        }
    }

    if (preg_match('/^(\d{1,2})-([A-Za-z]{3})-(\d{2,4})$/', $value, $m)) {
        $months = [
            'gen' => '01', 'feb' => '02', 'mar' => '03', 'apr' => '04',
            'mag' => '05', 'giu' => '06', 'lug' => '07', 'ago' => '08',
            'set' => '09', 'ott' => '10', 'nov' => '11', 'dic' => '12',
        ];
        $month = $months[strtolower($m[2])] ?? null;
        if ($month !== null) {
            $year = (int)$m[3];
            if ($year < 100) {
                $year += ($year < 40) ? 2000 : 1900;
            }
            return sprintf('%04d-%02d-%02d', $year, (int)$month, (int)$m[1]);
        }
    }

    return null;
}

function iscrizioniPrimeNormalizeSchoolYear($value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/\s+/', '', $value);
    if (preg_match('/^(\d{4})[\/\-](\d{2}|\d{4})$/', $value, $m)) {
        $start = (int)$m[1];
        $end = (string)$m[2];
        if (strlen($end) === 4) {
            $end = substr($end, -2);
        }
        return sprintf('%04d/%02d', $start, (int)$end);
    }

    return $value;
}

function iscrizioniPrimeSchoolYearWhere(string $column, string $year): string
{
    $year = iscrizioniPrimeNormalizeSchoolYear($year);
    if ($year === '') {
        return '1 = 0';
    }

    $variants = [$year];
    if (preg_match('/^(\d{4})\/(\d{2})$/', $year, $m)) {
        $start = (int)$m[1];
        $end2 = (int)$m[2];
        $century = intdiv($start, 100) * 100;
        $end4 = $century + $end2;
        if ($end4 <= $start) {
            $end4 += 100;
        }
        $variants[] = sprintf('%04d/%04d', $start, $end4);
        $variants[] = sprintf('%04d-%02d', $start, $end2);
        $variants[] = sprintf('%04d-%04d', $start, $end4);
    }
    $variants = array_values(array_unique($variants));

    return $column . ' IN (' . implode(', ', array_map('dbQ', $variants)) . ')';
}

function iscrizioniPrimeJson(array $row): string
{
    return json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function iscrizioniPrimeField(array $row, array $names, $default = null)
{
    foreach ($names as $name) {
        if (array_key_exists($name, $row)) {
            $value = trim((string)$row[$name]);
            if ($value !== '') {
                return $value;
            }
        }
    }

    return $default;
}

function iscrizioniPrimeGenerateToken(): array
{
    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

    return [
        'plain' => $token,
        'hash' => hash('sha256', $token),
        'last4' => substr($token, -4),
    ];
}

function iscrizioniPrimeSetToken(int $praticaId): string
{
    $token = iscrizioniPrimeGenerateToken();

    dbExec("
        UPDATE iscrizioni_prime_pratiche SET
            token_hash = " . dbQ($token['hash']) . ",
            token_last4 = " . dbQ($token['last4']) . ",
            token_created_at = NOW(),
            token_expires_at = DATE_ADD(NOW(), INTERVAL 90 DAY),
            updated_at = NOW()
        WHERE id = " . intval($praticaId) . "
        LIMIT 1
    ");

    return $token['plain'];
}

function iscrizioniPrimeMailConfig(): array
{
    global $__settings;

    $cfg = $__settings->iscrizioniPrime->mail ?? null;
    if (!$cfg) {
        return ['enabled' => false, 'accounts' => []];
    }

    $accounts = [];
    foreach (($cfg->accounts ?? []) as $account) {
        $email = trim((string)($account->email ?? ''));
        $password = (string)($account->password ?? '');
        if ($email !== '') {
            $accounts[] = [
                'email' => $email,
                'password' => $password,
            ];
        }
    }

    return [
        'enabled' => !empty($cfg->enabled),
        'testMode' => !empty($cfg->testMode),
        'maxPerAccountPerDay' => max(1, intval($cfg->maxPerAccountPerDay ?? 450)),
        'batchSize' => max(1, intval($cfg->batchSize ?? 50)),
        'subject' => iscrizioniPrimeMailSubject(),
        'draftSubjectPrime' => trim((string)($cfg->draftSubjectPrime ?? '[BOZZA] Regolarizzazione domanda di iscrizione classe prima a.s. 2026/2027')),
        'draftSubjectTerze' => trim((string)($cfg->draftSubjectTerze ?? '[BOZZA] Regolarizzazione domanda di iscrizione classe terza a.s. 2026/2027')),
        'confirmationSubject' => trim((string)($cfg->confirmationSubject ?? 'Conferma dati iscrizione ricevuta')),
        'fromName' => trim((string)($cfg->fromName ?? 'Iscrizioni')),
        'replyToEmail' => trim((string)($cfg->replyToEmail ?? '')),
        'replyToName' => trim((string)($cfg->replyToName ?? 'Segreteria didattica')),
        'mailFailureAlertEmail' => trim((string)($cfg->mailFailureAlertEmail ?? '')),
        'smtpHost' => trim((string)($cfg->smtpHost ?? ($__settings->local->smtpHost ?? ''))),
        'SMTPSecure' => (string)($cfg->SMTPSecure ?? ($__settings->local->SMTPSecure ?? 'tls')),
        'Port' => intval($cfg->Port ?? ($__settings->local->Port ?? 587)),
        'accounts' => $accounts,
    ];
}

function iscrizioniPrimeMailSubject($tipo = 'prime'): string
{
    return iscrizioniPrimeNormalizeTipoIscrizione(is_array($tipo) ? ($tipo['tipo_iscrizione'] ?? 'prime') : $tipo) === 'terze'
        ? 'Conferma dati iscrizione classi terze'
        : 'Conferma dati iscrizione classi prime';
}

function iscrizioniPrimeClasseTargetLabel(array $pratica): string
{
    return iscrizioniPrimeTipoIscrizioneFromPratica($pratica) === 'terze'
        ? 'future classi terze'
        : 'future classi prime';
}

function iscrizioniPrimeMailTemplate(string $tipoIscrizione): array
{
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);
    $row = dbGetFirst("
        SELECT *
        FROM iscrizioni_prime_mail_templates
        WHERE tipo_iscrizione = " . dbQ($tipoIscrizione) . "
        LIMIT 1
    ");

    return $row ?: ['tipo_iscrizione' => $tipoIscrizione, 'subject' => '', 'body_html' => ''];
}

function iscrizioniPrimeMailSaveTemplate(string $tipoIscrizione, string $subject, string $bodyHtml, string $updatedBy = ''): void
{
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);
    dbExec("
        INSERT INTO iscrizioni_prime_mail_templates
            (tipo_iscrizione, subject, body_html, updated_by, updated_at)
        VALUES
            (" . dbQ($tipoIscrizione) . ", " . dbQ($subject) . ", " . dbQ($bodyHtml) . ", " . dbQ($updatedBy) . ", NOW())
        ON DUPLICATE KEY UPDATE
            subject = VALUES(subject),
            body_html = VALUES(body_html),
            updated_by = VALUES(updated_by),
            updated_at = NOW()
    ");
}

function iscrizioniPrimeDraftSubject(string $tipoIscrizione, ?array $cfg = null): string
{
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);
    $configKey = $tipoIscrizione === 'terze' ? 'draft_subject_terze' : 'draft_subject_prime';
    $row = dbGetFirst("
        SELECT config_value
        FROM iscrizioni_prime_config
        WHERE config_key = " . dbQ($configKey) . "
        LIMIT 1
    ");
    $subject = trim((string)($row['config_value'] ?? ''));
    if ($subject !== '') {
        return $subject;
    }

    if ($cfg === null) {
        $cfg = iscrizioniPrimeMailConfig();
    }

    return $tipoIscrizione === 'terze'
        ? (string)($cfg['draftSubjectTerze'] ?? '[BOZZA] Regolarizzazione domanda di iscrizione classe terza a.s. 2026/2027')
        : (string)($cfg['draftSubjectPrime'] ?? '[BOZZA] Regolarizzazione domanda di iscrizione classe prima a.s. 2026/2027');
}

function iscrizioniPrimeDraftSubjectSave(string $tipoIscrizione, string $subject, string $updatedBy = ''): void
{
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);
    $subject = trim($subject);
    if ($subject === '') {
        throw new Exception('Oggetto bozza obbligatorio.');
    }
    $configKey = $tipoIscrizione === 'terze' ? 'draft_subject_terze' : 'draft_subject_prime';

    dbExec("
        INSERT INTO iscrizioni_prime_config
            (config_key, config_value, updated_by, updated_at)
        VALUES
            (" . dbQ($configKey) . ", " . dbQ($subject) . ", " . dbQ($updatedBy) . ", NOW())
        ON DUPLICATE KEY UPDATE
            config_value = VALUES(config_value),
            updated_by = VALUES(updated_by),
            updated_at = NOW()
    ");
}

function iscrizioniPrimeMailPublicBaseUrl(): string
{
    global $__http_base_link;

    $base = trim((string)($__http_base_link ?? ''));
    if ($base !== '') {
        return rtrim($base, '/');
    }

    $host = trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    if ($host !== '') {
        $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        return ($https ? 'https://' : 'http://') . $host . '/GestOre';
    }

    return 'https://www.buonarroti.tn.it/GestOre';
}

function iscrizioniPrimeMailTranslationsUrl(string $tipoIscrizione): string
{
    return iscrizioniPrimeMailPublicBaseUrl()
        . '/iscrizioni/comunicazione.php?tipo='
        . rawurlencode(iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione));
}

function iscrizioniPrimeMailTranslationsBlock(string $tipoIscrizione): string
{
    $translationsUrl = iscrizioniPrimeMailTranslationsUrl($tipoIscrizione);

    return '
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:18px 0;background:#f8fafc;border:1px solid #dbeafe;border-radius:10px;overflow:hidden;">
            <tr>
                <td style="padding:0;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                        <tr>
                            <td style="height:8px;background:#0ea5e9;font-size:0;line-height:0;">&nbsp;</td>
                            <td style="height:8px;background:#22c55e;font-size:0;line-height:0;">&nbsp;</td>
                            <td style="height:8px;background:#f59e0b;font-size:0;line-height:0;">&nbsp;</td>
                            <td style="height:8px;background:#ef4444;font-size:0;line-height:0;">&nbsp;</td>
                            <td style="height:8px;background:#8b5cf6;font-size:0;line-height:0;">&nbsp;</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td style="padding:16px 18px;text-align:center;font-family:Arial,Helvetica,sans-serif;">
                    <div style="font-size:26px;line-height:1.25;margin-bottom:8px;">
                        &#127470;&#127481; &#127468;&#127463; &#127467;&#127479; &#127465;&#127466; &#127466;&#127480; &#127477;&#127481; &#127482;&#127462; &#127479;&#127482; &#127480;&#127462; &#127464;&#127475; &#127477;&#127472;
                    </div>
                    <div style="font-size:20px;font-weight:800;color:#0f172a;margin-bottom:6px;">
                        Comunicazione disponibile in piu lingue
                    </div>
                    <div style="font-size:14px;color:#475569;line-height:1.45;margin-bottom:14px;">
                        Translations available - Traductions disponibles - Traducciones disponibles - Traducoes disponiveis
                    </div>
                    <a href="' . htmlspecialchars($translationsUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;padding:11px 18px;border-radius:7px;font-weight:800;font-size:15px;">
                        Apri la pagina multilingue
                    </a>
                    <div style="font-size:12px;color:#64748b;line-height:1.45;margin-top:12px;">
                        English - Francais - Deutsch - Espanol - Portugues - Ukrainian - Russian - Arabic - Chinese - Urdu
                    </div>
                </td>
            </tr>
        </table>
    ';
}

function iscrizioniPrimeMailRenderTemplate(string $bodyHtml, array $pratica, string $link): string
{
    global $__settings;

    $tipoIscrizione = iscrizioniPrimeTipoIscrizioneFromPratica($pratica);
    $nomeCompleto = trim((string)(($pratica['nome'] ?? '') . ' ' . ($pratica['cognome'] ?? '')));
    $translationsUrl = iscrizioniPrimeMailTranslationsUrl($tipoIscrizione);
    $translationsBlock = iscrizioniPrimeMailTranslationsBlock($tipoIscrizione);
    $replacements = [
        '{link}' => htmlspecialchars($link, ENT_QUOTES, 'UTF-8'),
        '{traduzioni}' => htmlspecialchars($translationsUrl, ENT_QUOTES, 'UTF-8'),
        '{link_traduzioni}' => htmlspecialchars($translationsUrl, ENT_QUOTES, 'UTF-8'),
        '{blocco_traduzioni}' => $translationsBlock,
        '{traduzioni_box}' => $translationsBlock,
        '{{LINK_COMUNICAZIONE_MULTILINGUE}}' => htmlspecialchars($translationsUrl, ENT_QUOTES, 'UTF-8'),
        '{{BLOCCO_COMUNICAZIONE_MULTILINGUE}}' => $translationsBlock,
        '{studente}' => iscrizioniPrimeMailEscape($nomeCompleto),
        '{nome}' => iscrizioniPrimeMailEscape($pratica['nome'] ?? ''),
        '{cognome}' => iscrizioniPrimeMailEscape($pratica['cognome'] ?? ''),
        '{corso}' => iscrizioniPrimeMailEscape($pratica['corso_studi'] ?? ''),
        '{anno}' => iscrizioniPrimeMailEscape($pratica['anno_scolastico'] ?? ''),
        '{istituto}' => iscrizioniPrimeMailEscape($__settings->local->nomeIstituto ?? 'Istituto'),
    ];

    return strtr($bodyHtml, $replacements);
}

function iscrizioniPrimeMailAttachmentDir(string $tipoIscrizione): string
{
    return iscrizioniPrimeUploadBaseDir() . '/iscrizioni_mail_attachments/' . iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);
}

function iscrizioniPrimeMailAttachmentPaths(string $tipoIscrizione): array
{
    $rows = dbGetAll("
        SELECT file_path
        FROM iscrizioni_prime_mail_attachments
        WHERE tipo_iscrizione = " . dbQ(iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione)) . "
        ORDER BY id ASC
    ");

    $paths = [];
    foreach ($rows as $row) {
        $path = realpath(__DIR__ . '/../' . (string)($row['file_path'] ?? ''));
        if ($path && is_file($path)) {
            $paths[] = $path;
        }
    }
    return $paths;
}

function iscrizioniPrimeMailAttachments(string $tipoIscrizione): array
{
    return dbGetAll("
        SELECT id, original_name, file_size, uploaded_at
        FROM iscrizioni_prime_mail_attachments
        WHERE tipo_iscrizione = " . dbQ(iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione)) . "
        ORDER BY id ASC
    ");
}

function iscrizioniPrimeMailUploadAttachment(string $tipoIscrizione, array $file, string $uploadedBy = ''): array
{
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);
    if (!empty($file['error']) || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'message' => 'Selezionare un PDF da allegare.'];
    }
    $size = intval($file['size'] ?? 0);
    if ($size <= 0 || $size > 10 * 1024 * 1024) {
        return ['ok' => false, 'message' => 'Ogni allegato deve essere inferiore a 10 MB.'];
    }
    $name = (string)($file['name'] ?? 'allegato.pdf');
    $mime = iscrizioniPrimeMimeType($file['tmp_name'], (string)($file['type'] ?? ''));
    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'pdf' && $mime !== 'application/pdf') {
        return ['ok' => false, 'message' => 'Sono ammessi solo allegati PDF.'];
    }

    $dir = iscrizioniPrimeMailAttachmentDir($tipoIscrizione);
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        return ['ok' => false, 'message' => 'Impossibile creare la cartella allegati mail.'];
    }
    $safeName = iscrizioniPrimeDriveSafeName(pathinfo($name, PATHINFO_FILENAME));
    if ($safeName === '') {
        $safeName = 'allegato';
    }
    $targetName = iscrizioniPrimeUniquePdfFileName($dir, $safeName);
    $target = $dir . '/' . $targetName;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return ['ok' => false, 'message' => 'Impossibile salvare allegato.'];
    }

    $relative = 'data/iscrizioni_mail_attachments/' . $tipoIscrizione . '/' . $targetName;
    dbExec("
        INSERT INTO iscrizioni_prime_mail_attachments
            (tipo_iscrizione, file_path, original_name, file_size, uploaded_by, uploaded_at)
        VALUES
            (" . dbQ($tipoIscrizione) . ", " . dbQ($relative) . ", " . dbQ($name) . ", " . intval(filesize($target) ?: $size) . ", " . dbQ($uploadedBy) . ", NOW())
    ");

    return ['ok' => true, 'message' => 'Allegato caricato.'];
}

function iscrizioniPrimeMailAccountCounts(): array
{
    $rows = dbGetAll("
        SELECT account_email, SUM(totale) AS totale
        FROM (
            SELECT account_email, COUNT(*) AS totale
            FROM iscrizioni_prime_mail_log
            WHERE stato IN ('inviata','bounce')
              AND test_mode = 0
              AND sent_at >= CURDATE()
              AND account_email IS NOT NULL
            GROUP BY account_email
            UNION ALL
            SELECT account_email, COUNT(*) AS totale
            FROM iscrizioni_prime_custom_mail_log
            WHERE stato IN ('inviata','bounce')
              AND test_mode = 0
              AND sent_at >= CURDATE()
              AND account_email IS NOT NULL
            GROUP BY account_email
        ) x
        GROUP BY account_email
    ");

    $counts = [];
    foreach ($rows as $row) {
        $counts[(string)$row['account_email']] = intval($row['totale']);
    }

    return $counts;
}

function iscrizioniPrimeMailPendingRecipientCount(string $tipoIscrizione): int
{
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);

    $count = dbGetValue("
        SELECT COALESCE(SUM(
            CASE
                WHEN p.email_genitore_1 IS NOT NULL
                 AND TRIM(p.email_genitore_1) <> ''
                 AND NOT EXISTS (
                    SELECT 1
                    FROM iscrizioni_prime_mail_log l
                    WHERE l.pratica_id = p.id
                      AND LOWER(TRIM(l.recipient_email)) = LOWER(TRIM(p.email_genitore_1))
                      AND l.stato IN ('inviata','bounce')
                      AND l.test_mode = 0
                    LIMIT 1
                 )
                THEN 1 ELSE 0
            END
            +
            CASE
                WHEN p.email_genitore_2 IS NOT NULL
                 AND TRIM(p.email_genitore_2) <> ''
                 AND LOWER(TRIM(p.email_genitore_2)) <> LOWER(TRIM(COALESCE(p.email_genitore_1, '')))
                 AND NOT EXISTS (
                    SELECT 1
                    FROM iscrizioni_prime_mail_log l
                    WHERE l.pratica_id = p.id
                      AND LOWER(TRIM(l.recipient_email)) = LOWER(TRIM(p.email_genitore_2))
                      AND l.stato IN ('inviata','bounce')
                      AND l.test_mode = 0
                    LIMIT 1
                 )
                THEN 1 ELSE 0
            END
        ), 0)
        FROM iscrizioni_prime_pratiche p
        WHERE p.stato IN ('importata', 'bozza', 'da_integrare')
          AND p.tipo_iscrizione = " . dbQ($tipoIscrizione) . "
          AND " . iscrizioniPrimeEffectiveExternalCondition('p') . "
          AND (p.email_genitore_1 IS NOT NULL OR p.email_genitore_2 IS NOT NULL)
    ");

    return intval($count);
}

function iscrizioniPrimeMailStaleLinkRecipientCount(string $tipoIscrizione): int
{
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);

    $count = dbGetValue("
        SELECT COALESCE(SUM(
            CASE
                WHEN p.email_genitore_1 IS NOT NULL
                 AND TRIM(p.email_genitore_1) <> ''
                 AND p.token_last4 IS NOT NULL
                 AND TRIM(p.token_last4) <> ''
                 AND EXISTS (
                    SELECT 1
                    FROM iscrizioni_prime_mail_log l
                    WHERE l.pratica_id = p.id
                      AND LOWER(TRIM(l.recipient_email)) = LOWER(TRIM(p.email_genitore_1))
                      AND l.stato = 'inviata'
                      AND l.test_mode = 0
                      AND COALESCE(l.token_last4, '') <> COALESCE(p.token_last4, '')
                    LIMIT 1
                 )
                THEN 1 ELSE 0
            END
            +
            CASE
                WHEN p.email_genitore_2 IS NOT NULL
                 AND TRIM(p.email_genitore_2) <> ''
                 AND LOWER(TRIM(p.email_genitore_2)) <> LOWER(TRIM(COALESCE(p.email_genitore_1, '')))
                 AND p.token_last4 IS NOT NULL
                 AND TRIM(p.token_last4) <> ''
                 AND EXISTS (
                    SELECT 1
                    FROM iscrizioni_prime_mail_log l
                    WHERE l.pratica_id = p.id
                      AND LOWER(TRIM(l.recipient_email)) = LOWER(TRIM(p.email_genitore_2))
                      AND l.stato = 'inviata'
                      AND l.test_mode = 0
                      AND COALESCE(l.token_last4, '') <> COALESCE(p.token_last4, '')
                    LIMIT 1
                 )
                THEN 1 ELSE 0
            END
        ), 0)
        FROM iscrizioni_prime_pratiche p
        WHERE p.stato IN ('importata', 'bozza', 'da_integrare')
          AND p.tipo_iscrizione = " . dbQ($tipoIscrizione) . "
          AND " . iscrizioniPrimeEffectiveExternalCondition('p') . "
          AND (p.email_genitore_1 IS NOT NULL OR p.email_genitore_2 IS NOT NULL)
    ");

    return intval($count);
}

function iscrizioniPrimePickMailAccount(array $cfg, array $counts): ?array
{
    $best = null;
    $bestCount = PHP_INT_MAX;
    $limit = intval($cfg['maxPerAccountPerDay'] ?? 450);
    $accounts = $cfg['accounts'];
    $replyToEmail = strtolower(trim((string)($cfg['replyToEmail'] ?? '')));

    if ($replyToEmail !== '' && count($accounts) > 1) {
        $filtered = array_values(array_filter($accounts, function ($account) use ($replyToEmail) {
            return strtolower(trim((string)($account['email'] ?? ''))) !== $replyToEmail;
        }));
        if ($filtered) {
            $accounts = $filtered;
        }
    }

    foreach ($accounts as $account) {
        $count = intval($counts[$account['email']] ?? 0);
        if ($count < $limit && $count < $bestCount) {
            $best = $account;
            $bestCount = $count;
        }
    }

    return $best;
}

function iscrizioniPrimeMailBody(array $pratica, string $link, string $originalRecipient = ''): string
{
    global $__settings;

    $template = iscrizioniPrimeMailTemplate(iscrizioniPrimeTipoIscrizioneFromPratica($pratica));
    $customBody = trim((string)($template['body_html'] ?? ''));
    if ($customBody !== '') {
        $html = iscrizioniPrimeMailRenderTemplate($customBody, $pratica, $link);
        if (strpos($html, $link) === false && strpos($html, '{link}') === false) {
            $html .= '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">Apri la pagina di conferma iscrizione</a></p>';
        }
        $translationsUrl = iscrizioniPrimeMailTranslationsUrl(iscrizioniPrimeTipoIscrizioneFromPratica($pratica));
        if (strpos($html, $translationsUrl) === false && strpos($html, '{traduzioni}') === false && strpos($html, '{link_traduzioni}') === false && strpos($html, '{blocco_traduzioni}') === false) {
            $html .= iscrizioniPrimeMailTranslationsBlock(iscrizioniPrimeTipoIscrizioneFromPratica($pratica));
        }
        return $originalRecipient !== '' ? iscrizioniPrimeMailTestBanner($originalRecipient) . $html : $html;
    }

    $nome = trim((string)(($pratica['nome'] ?? '') . ' ' . ($pratica['cognome'] ?? '')));
    $istituto = trim((string)($__settings->local->nomeIstituto ?? 'ITT Buonarroti - Trento'));
    $anno = trim((string)($pratica['anno_scolastico'] ?? '2026-27'));
    $corso = trim((string)($pratica['corso_studi'] ?? ''));
    $classeTarget = iscrizioniPrimeClasseTargetLabel($pratica);
    $testBlock = '';

    if ($originalRecipient !== '') {
        $testBlock = iscrizioniPrimeMailTestBanner($originalRecipient);
    }

    return $testBlock . '
        <div style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#172033;">
            <div style="max-width:720px;margin:0 auto;padding:22px 12px;">
                <div style="background:#ffffff;border:1px solid #dbe3ef;border-radius:8px;overflow:hidden;">
                    <div style="background:#0f766e;color:#ffffff;padding:20px 22px;">
                        <div style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;opacity:.9;">' . iscrizioniPrimeMailEscape($istituto) . '</div>
                        <div style="font-size:24px;font-weight:800;margin-top:4px;">Conferma dati iscrizione</div>
                        <div style="font-size:15px;margin-top:4px;">' . ucfirst($classeTarget) . ' - anno scolastico ' . iscrizioniPrimeMailEscape($anno) . '</div>
                    </div>
                    <div style="padding:22px;">
                        <p style="margin:0 0 12px;font-size:16px;">Gentile famiglia,</p>
                        <p style="margin:0 0 16px;font-size:16px;line-height:1.5;">
                            per completare la procedura di iscrizione alle ' . iscrizioniPrimeMailEscape($classeTarget) . ', chiediamo di confermare i dati anagrafici e caricare i documenti richiesti per
                            <strong>' . iscrizioniPrimeMailEscape($nome) . '</strong>.
                        </p>
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin:16px 0;">
                            <tr>
                                <td style="padding:9px 10px;border-bottom:1px solid #e5e7eb;color:#64748b;width:34%;">Studente</td>
                                <td style="padding:9px 10px;border-bottom:1px solid #e5e7eb;color:#172033;font-weight:700;">' . iscrizioniPrimeMailEscape($nome) . '</td>
                            </tr>
                            <tr>
                                <td style="padding:9px 10px;border-bottom:1px solid #e5e7eb;color:#64748b;">Corso</td>
                                <td style="padding:9px 10px;border-bottom:1px solid #e5e7eb;color:#172033;font-weight:700;">' . iscrizioniPrimeMailEscape($corso) . '</td>
                            </tr>
                            <tr>
                                <td style="padding:9px 10px;color:#64748b;">Anno scolastico</td>
                                <td style="padding:9px 10px;color:#172033;font-weight:700;">' . iscrizioniPrimeMailEscape($anno) . '</td>
                            </tr>
                        </table>
                        <p style="margin:18px 0;text-align:center;">
                            <a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;padding:13px 20px;border-radius:6px;font-weight:800;">
                                Apri la pagina di conferma iscrizione
                            </a>
                        </p>
                        <div style="border-left:5px solid #0ea5e9;background:#eaf6fc;padding:12px 14px;border-radius:6px;margin:16px 0;color:#0f172a;line-height:1.45;">
                            Il link e\' personale e non richiede un account GestOre. Puoi salvare una bozza e rientrare dallo stesso link prima dell\'invio definitivo.
                        </div>
                        ' . iscrizioniPrimeMailTranslationsBlock(iscrizioniPrimeTipoIscrizioneFromPratica($pratica)) . '
                        <p style="margin:18px 0 0;color:#475569;line-height:1.5;">
                            Se i documenti sono cartacei, puoi fotografarli con il telefono direttamente dalla pagina. Le foto verranno trasformate in PDF.
                        </p>
                        <p style="margin:18px 0 0;color:#172033;line-height:1.5;">Cordiali saluti<br><strong>Segreteria didattica</strong></p>
                    </div>
                </div>
            </div>
        </div>
    ';
}

function iscrizioniPrimeMailRecipientsForPratica(array $pratica): array
{
    $recipients = [];
    foreach (['email_genitore_1', 'email_genitore_2'] as $field) {
        $email = strtolower(trim((string)($pratica[$field] ?? '')));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && !isset($recipients[$email])) {
            $recipients[$email] = $email;
        }
    }

    return array_values($recipients);
}

function iscrizioniPrimePublicFromEmail(array $cfg, array $account): string
{
    $replyToEmail = strtolower(trim((string)($cfg['replyToEmail'] ?? '')));
    if ($replyToEmail !== '' && filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
        return $replyToEmail;
    }

    return strtolower(trim((string)($account['email'] ?? '')));
}

function iscrizioniPrimeMailEscape($value): string
{
    $value = trim((string)$value);
    return htmlspecialchars($value !== '' ? $value : '-', ENT_QUOTES, 'UTF-8');
}

function iscrizioniPrimeFormatDateIt($value): string
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00') {
        return '';
    }

    foreach (['Y-m-d', 'Y-m-d H:i:s', 'd/m/Y'] as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime) {
            return $date->format('d/m/Y');
        }
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y', $timestamp) : $value;
}

function iscrizioniPrimeFormatDateTimeIt($value): string
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00 00:00:00') {
        return '';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : $value;
}

function iscrizioniPrimeCurrentActor(): string
{
    global $__useremail, $__utente_nome, $__utente_cognome;
    $name = trim((string)($__utente_nome ?? '') . ' ' . (string)($__utente_cognome ?? ''));
    if ($name !== '') {
        return $name;
    }
    return trim((string)($__useremail ?? ''));
}

function iscrizioniPrimeRecordEvent(int $praticaId, string $tipoEvento, string $titolo, array $options = []): void
{
    if ($praticaId <= 0) {
        return;
    }

    $pratica = dbGetFirst("SELECT tipo_iscrizione FROM iscrizioni_prime_pratiche WHERE id = " . dbI($praticaId) . " LIMIT 1");
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($options['tipo_iscrizione'] ?? ($pratica['tipo_iscrizione'] ?? 'prime'));
    $details = $options['dettagli'] ?? null;
    $detailsJson = is_array($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : null;
    $createdAt = trim((string)($options['created_at'] ?? '')) !== '' ? dbQ((string)$options['created_at']) : 'NOW()';

    dbExec("
        INSERT INTO iscrizioni_prime_eventi
            (pratica_id, tipo_iscrizione, tipo_evento, titolo, stato_precedente, stato_nuovo, oggetto, messaggio, dettagli_json, created_by, created_at)
        VALUES
            (
                " . dbI($praticaId) . ",
                " . dbQ($tipoIscrizione) . ",
                " . dbQ($tipoEvento) . ",
                " . dbQ($titolo) . ",
                " . dbQ($options['stato_precedente'] ?? null) . ",
                " . dbQ($options['stato_nuovo'] ?? null) . ",
                " . dbQ($options['oggetto'] ?? null) . ",
                " . dbQ($options['messaggio'] ?? null) . ",
                " . dbQ($detailsJson) . ",
                " . dbQ($options['created_by'] ?? iscrizioniPrimeCurrentActor()) . ",
                $createdAt
            )
    ");
}

function iscrizioniPrimeMarkSecretaryNews(int $praticaId, string $message): void
{
    if ($praticaId <= 0) {
        return;
    }

    dbExec("
        UPDATE iscrizioni_prime_pratiche SET
            novita_segreteria_at = NOW(),
            novita_segreteria_messaggio = " . dbQ($message) . ",
            updated_at = NOW()
        WHERE id = " . dbI($praticaId) . "
        LIMIT 1
    ");
}

function iscrizioniPrimeClearSecretaryNews(int $praticaId): void
{
    if ($praticaId <= 0) {
        return;
    }

    dbExec("
        UPDATE iscrizioni_prime_pratiche SET
            novita_segreteria_at = NULL,
            novita_segreteria_messaggio = NULL,
            updated_at = NOW()
        WHERE id = " . dbI($praticaId) . "
        LIMIT 1
    ");
}

function iscrizioniPrimeEventsForPratica(array $pratica): array
{
    $praticaId = intval($pratica['id'] ?? 0);
    if ($praticaId <= 0) {
        return [];
    }

    $events = dbGetAll("
        SELECT *
        FROM iscrizioni_prime_eventi
        WHERE pratica_id = " . dbI($praticaId) . "
        ORDER BY created_at DESC, id DESC
    ") ?: [];

    $confirmed = [];
    if (!empty($pratica['dati_confermati_json'])) {
        $decoded = json_decode((string)$pratica['dati_confermati_json'], true);
        if (is_array($decoded)) {
            $confirmed = $decoded;
        }
    }
    $sentAt = trim((string)($confirmed['saved_at'] ?? ''));
    if ($sentAt === '' && in_array((string)($pratica['stato'] ?? ''), ['inviata', 'verifica_iniziale_ok', 'verificata', 'da_integrare', 'annullata'], true)) {
        $sentAt = trim((string)($pratica['updated_at'] ?? ''));
    }
    if ($sentAt !== '') {
        $events[] = [
            'id' => 0,
            'tipo_evento' => 'invio_famiglia',
            'titolo' => 'Conferma dati inviata dalla famiglia',
            'stato_precedente' => null,
            'stato_nuovo' => 'inviata',
            'oggetto' => null,
            'messaggio' => null,
            'dettagli_json' => null,
            'created_by' => 'Famiglia',
            'created_at' => date('Y-m-d H:i:s', strtotime($sentAt) ?: time()),
        ];
    }

    usort($events, static function ($a, $b) {
        $timeA = strtotime((string)($a['created_at'] ?? '')) ?: 0;
        $timeB = strtotime((string)($b['created_at'] ?? '')) ?: 0;
        if ($timeA === $timeB) {
            return intval($b['id'] ?? 0) <=> intval($a['id'] ?? 0);
        }
        return $timeB <=> $timeA;
    });

    return $events;
}

function iscrizioniPrimeMailConfirmedData(array $pratica): array
{
    $confirmed = [];
    if (!empty($pratica['dati_confermati_json'])) {
        $decoded = json_decode((string)$pratica['dati_confermati_json'], true);
        if (is_array($decoded)) {
            $confirmed = $decoded;
        }
    }

    return $confirmed;
}

function iscrizioniPrimeMailValue(array $pratica, array $confirmed, string $field): string
{
    return trim((string)($confirmed[$field] ?? $pratica[$field] ?? ''));
}

function iscrizioniPrimeMailRows(array $rows): string
{
    $html = '';
    foreach ($rows as $row) {
        if (!empty($row[2]) && trim((string)($row[1] ?? '')) === '') {
            continue;
        }
        $html .= '
            <tr>
                <td style="padding:8px 10px;border-bottom:1px solid #e5e7eb;color:#64748b;width:38%;">' . iscrizioniPrimeMailEscape($row[0] ?? '') . '</td>
                <td style="padding:8px 10px;border-bottom:1px solid #e5e7eb;color:#172033;font-weight:600;">' . iscrizioniPrimeMailEscape($row[1] ?? '') . '</td>
            </tr>
        ';
    }

    return $html;
}

function iscrizioniPrimeMailFormatRichText(string $text): string
{
    $lines = preg_split('/\R/u', trim($text));
    if (!$lines) {
        return '';
    }

    $html = '';
    $list = null;
    $closeList = function () use (&$html, &$list) {
        if ($list !== null) {
            $html .= '</' . $list . '>';
            $list = null;
        }
    };
    $formatInline = function (string $value): string {
        $safe = iscrizioniPrimeMailEscape($value);
        return preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $safe);
    };

    foreach ($lines as $line) {
        $line = rtrim((string)$line);
        if (trim($line) === '') {
            $closeList();
            $html .= '<div style="height:8px;line-height:8px;">&nbsp;</div>';
            continue;
        }

        if (preg_match('/^\s*(?:[-*]|&bull;|•)\s+(.+)$/u', $line, $match)) {
            if ($list !== 'ul') {
                $closeList();
                $html .= '<ul style="margin:8px 0 8px 20px;padding:0;">';
                $list = 'ul';
            }
            $html .= '<li style="margin:4px 0;">' . $formatInline($match[1]) . '</li>';
            continue;
        }

        if (preg_match('/^\s*\d+[.)]\s+(.+)$/u', $line, $match)) {
            if ($list !== 'ol') {
                $closeList();
                $html .= '<ol style="margin:8px 0 8px 20px;padding:0;">';
                $list = 'ol';
            }
            $html .= '<li style="margin:4px 0;">' . $formatInline($match[1]) . '</li>';
            continue;
        }

        $closeList();
        $html .= '<p style="margin:0 0 10px;line-height:1.6;">' . $formatInline($line) . '</p>';
    }

    $closeList();
    return $html;
}

function iscrizioniPrimeMailTestBanner(string $recipient): string
{
    return '
        <div style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#172033;">
            <div style="max-width:760px;margin:0 auto;padding:22px 12px 0;">
                <div style="border-left:5px solid #f59e0b;background:#fffbeb;border-radius:8px;padding:12px 14px;color:#7c2d12;">
                    <strong>Modalita\' test:</strong> questa conferma sarebbe stata inviata a
                    <strong>' . iscrizioniPrimeMailEscape($recipient) . '</strong>.
                </div>
            </div>
        </div>
    ';
}

function iscrizioniPrimePublicLinkForPratica(int $praticaId): string
{
    $token = iscrizioniPrimeSetToken($praticaId);
    $base = rtrim((string)($GLOBALS['__http_base_link'] ?? ''), '/');

    return $base . '/iscrizioni/conferma.php?t=' . rawurlencode($token);
}

function iscrizioniPrimeIntegrationRequestBody(array $pratica, string $note, string $link): string
{
    global $__settings;

    $nomeStudente = trim((string)(($pratica['nome'] ?? '') . ' ' . ($pratica['cognome'] ?? '')));
    $istituto = trim((string)($__settings->local->nomeIstituto ?? 'Istituto'));
    $anno = trim((string)($pratica['anno_scolastico'] ?? ''));
    $classeTarget = iscrizioniPrimeClasseTargetLabel($pratica);

    return '
        <div style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#172033;">
            <div style="max-width:760px;margin:0 auto;padding:22px 12px;">
                <div style="background:#ffffff;border:1px solid #dbe3ef;border-radius:8px;overflow:hidden;">
                    <div style="background:#92400e;color:#ffffff;padding:20px 22px;">
                        <div style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;opacity:.9;">' . iscrizioniPrimeMailEscape($istituto) . '</div>
                        <div style="font-size:24px;font-weight:800;margin-top:4px;">Richiesta correzione pratica iscrizione</div>
                        <div style="font-size:15px;margin-top:4px;">' . ucfirst(iscrizioniPrimeMailEscape($classeTarget)) . ' - anno scolastico ' . iscrizioniPrimeMailEscape($anno) . '</div>
                    </div>
                    <div style="padding:22px;">
                        <p style="margin:0 0 14px;line-height:1.55;">Gentile famiglia,</p>
                        <p style="margin:0 0 14px;line-height:1.55;">
                            la Segreteria didattica ha controllato la pratica di iscrizione di
                            <strong>' . iscrizioniPrimeMailEscape($nomeStudente) . '</strong> e chiede una correzione o integrazione.
                        </p>
                        <div style="border-left:6px solid #f59e0b;background:#fffbeb;border-radius:8px;padding:14px 16px;margin:18px 0;color:#7c2d12;line-height:1.55;">
                            <div style="font-weight:800;margin-bottom:6px;">Indicazioni della Segreteria</div>
                            ' . nl2br(iscrizioniPrimeMailEscape($note)) . '
                        </div>
                        <p style="margin:0 0 14px;line-height:1.55;">
                            La pratica e\' stata riaperta. Apri il link personale, correggi i dati o i documenti indicati e poi premi di nuovo
                            <strong>SALVA ED INVIA CONFERMA DATI ISCRIZIONE</strong>.
                        </p>
                        <p style="margin:22px 0;text-align:center;">
                            <a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;padding:13px 20px;border-radius:6px;font-weight:800;">
                                Riapri la pratica e correggi
                            </a>
                        </p>
                        <div style="border-left:5px solid #0ea5e9;background:#eaf6fc;padding:12px 14px;border-radius:6px;margin:16px 0;color:#0f172a;line-height:1.45;">
                            Se devi sostituire un allegato gia\' caricato, entra nella sezione del documento, cancella il PDF non corretto e carica il documento giusto.
                        </div>
                        <p style="margin:18px 0 0;color:#172033;line-height:1.5;">Cordiali saluti<br><strong>Segreteria didattica</strong></p>
                    </div>
                </div>
            </div>
        </div>
    ';
}

function iscrizioniPrimeSendIntegrationRequest(array $pratica, string $note): array
{
    require_once __DIR__ . '/send-mail.php';

    $cfg = iscrizioniPrimeMailConfig();
    if (empty($cfg['enabled'])) {
        return ['ok' => false, 'message' => 'Invio mail iscrizioni non abilitato in GestOre.json.'];
    }
    if (empty($cfg['accounts'])) {
        return ['ok' => false, 'message' => 'Nessun account SMTP iscrizioni configurato.'];
    }

    $recipients = iscrizioniPrimeMailRecipientsForPratica($pratica);
    if (empty($recipients)) {
        return ['ok' => false, 'message' => 'Nessun destinatario valido per la richiesta di integrazione.'];
    }

    $account = iscrizioniPrimePickMailAccount($cfg, iscrizioniPrimeMailAccountCounts());
    if ($account === null) {
        $message = 'Limite giornaliero degli account iscrizioni raggiunto: richiesta integrazione non inviata per pratica ID ' . intval($pratica['id'] ?? 0) . '.';
        iscrizioniPrimeNotifyMailFailure($cfg, $message);
        return ['ok' => false, 'message' => $message];
    }

    $link = iscrizioniPrimePublicLinkForPratica((int)$pratica['id']);
    $body = iscrizioniPrimeIntegrationRequestBody($pratica, $note, $link);
    $nomeStudente = trim((string)(($pratica['nome'] ?? '') . ' ' . ($pratica['cognome'] ?? '')));
    $subject = 'Richiesta correzione pratica iscrizione - ' . ($nomeStudente !== '' ? $nomeStudente : 'studente');
    $errors = [];

    foreach ($recipients as $recipient) {
        $actualRecipient = $recipient;
        $recipientName = $recipient;
        $actualBody = $body;

        if (!empty($cfg['testMode'])) {
            $actualRecipient = $account['email'];
            $recipientName = 'Test iscrizioni';
            $actualBody = iscrizioniPrimeMailTestBanner($recipient) . $body;
        }

        $ok = sendMailCustom($actualRecipient, $recipientName, $subject, $actualBody, [
            'from_email' => iscrizioniPrimePublicFromEmail($cfg, $account),
            'from_name' => $cfg['fromName'],
            'reply_to_email' => $cfg['replyToEmail'] !== '' ? $cfg['replyToEmail'] : $account['email'],
            'reply_to_name' => $cfg['replyToName'],
            'sender_email' => $account['email'],
            'sender_name' => $cfg['fromName'],
            'smtp_host' => $cfg['smtpHost'],
            'smtp_username' => $account['email'],
            'smtp_password' => $account['password'],
            'smtp_secure' => $cfg['SMTPSecure'],
            'smtp_port' => $cfg['Port'],
        ]);

        if (!$ok) {
            $errors[] = $recipient;
        }
    }

    if ($errors) {
        $message = 'Errore invio richiesta integrazione iscrizioni per pratica ID ' . intval($pratica['id'] ?? 0) . '. Destinatari non raggiunti: ' . implode(', ', $errors) . '.';
        iscrizioniPrimeNotifyMailFailure($cfg, $message);
        return ['ok' => false, 'message' => $message];
    }

    return ['ok' => true, 'message' => 'Mail di richiesta integrazione inviata.'];
}

function iscrizioniPrimeCustomPracticeMailBody(array $pratica, string $message, string $signature): string
{
    global $__settings;

    $nomeStudente = trim((string)(($pratica['nome'] ?? '') . ' ' . ($pratica['cognome'] ?? '')));
    $istituto = trim((string)($__settings->local->nomeIstituto ?? 'Istituto'));
    $anno = trim((string)($pratica['anno_scolastico'] ?? ''));
    $classeTarget = iscrizioniPrimeClasseTargetLabel($pratica);
    $signatureHtml = trim($signature) !== ''
        ? '<div style="margin:20px 0 0;color:#172033;line-height:1.55;">' . iscrizioniPrimeMailFormatRichText($signature) . '</div>'
        : '';

    return '
        <div style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#172033;">
            <div style="max-width:760px;margin:0 auto;padding:22px 12px;">
                <div style="background:#ffffff;border:1px solid #dbe3ef;border-radius:8px;overflow:hidden;">
                    <div style="background:#1d4ed8;color:#ffffff;padding:20px 22px;">
                        <div style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;opacity:.9;">' . iscrizioniPrimeMailEscape($istituto) . '</div>
                        <div style="font-size:24px;font-weight:800;margin-top:4px;">Comunicazione dalla Segreteria didattica</div>
                        <div style="font-size:15px;margin-top:4px;">' . ucfirst(iscrizioniPrimeMailEscape($classeTarget)) . ' - anno scolastico ' . iscrizioniPrimeMailEscape($anno) . '</div>
                    </div>
                    <div style="padding:22px;">
                        <p style="margin:0 0 14px;line-height:1.55;">Gentile famiglia,</p>
                        <div style="border-left:5px solid #2563eb;background:#eff6ff;border-radius:8px;padding:14px 16px;margin:16px 0;color:#172033;line-height:1.6;">
                            ' . iscrizioniPrimeMailFormatRichText($message) . '
                        </div>
                        <div style="border-left:5px solid #cbd5e1;background:#f8fafc;border-radius:8px;padding:12px 14px;margin:18px 0;color:#475569;line-height:1.45;">
                            Questa comunicazione riguarda la pratica di iscrizione di
                            <strong>' . iscrizioniPrimeMailEscape($nomeStudente) . '</strong>.
                        </div>
                        ' . $signatureHtml . '
                    </div>
                </div>
            </div>
        </div>
    ';
}

function iscrizioniPrimeSendCustomPracticeMail(array $pratica, string $subject, string $message, string $signature, ?array $selectedRecipients = null, array $attachments = [], string $eventType = 'mail_personalizzata'): array
{
    require_once __DIR__ . '/send-mail.php';

    $cfg = iscrizioniPrimeMailConfig();
    if (empty($cfg['enabled'])) {
        return ['ok' => false, 'message' => 'Invio mail iscrizioni non abilitato in GestOre.json.'];
    }
    if (empty($cfg['accounts'])) {
        return ['ok' => false, 'message' => 'Nessun account SMTP iscrizioni configurato.'];
    }

    $subject = trim($subject);
    $message = trim($message);
    $signature = trim($signature);
    if ($subject === '') {
        return ['ok' => false, 'message' => 'Inserire l\'oggetto della mail.'];
    }
    if ($message === '') {
        return ['ok' => false, 'message' => 'Inserire il testo della comunicazione.'];
    }

    $recipients = iscrizioniPrimeMailRecipientsForPratica($pratica);
    if (is_array($selectedRecipients)) {
        $allowed = array_fill_keys($recipients, true);
        $recipients = array_values(array_unique(array_filter(array_map(
            static fn($email) => strtolower(trim((string)$email)),
            $selectedRecipients
        ), static fn($email) => $email !== '' && isset($allowed[$email]))));
    }
    if (empty($recipients)) {
        return ['ok' => false, 'message' => 'Nessun destinatario valido collegato alla pratica.'];
    }

    $account = iscrizioniPrimePickMailAccount($cfg, iscrizioniPrimeMailAccountCounts());
    if ($account === null) {
        $errorMessage = 'Limite giornaliero degli account iscrizioni raggiunto: comunicazione personalizzata non inviata per pratica ID ' . intval($pratica['id'] ?? 0) . '.';
        iscrizioniPrimeNotifyMailFailure($cfg, $errorMessage);
        return ['ok' => false, 'message' => $errorMessage];
    }

    $body = iscrizioniPrimeCustomPracticeMailBody($pratica, $message, $signature);
    $errors = [];
    $sent = 0;

    foreach ($recipients as $recipient) {
        $actualRecipient = $recipient;
        $recipientName = $recipient;
        $actualBody = $body;

        if (!empty($cfg['testMode'])) {
            $actualRecipient = $account['email'];
            $recipientName = 'Test iscrizioni';
            $actualBody = iscrizioniPrimeMailTestBanner($recipient) . $body;
        }

        $ok = sendMailCustom($actualRecipient, $recipientName, $subject, $actualBody, [
            'from_email' => iscrizioniPrimePublicFromEmail($cfg, $account),
            'from_name' => $cfg['fromName'],
            'reply_to_email' => $cfg['replyToEmail'] !== '' ? $cfg['replyToEmail'] : $account['email'],
            'reply_to_name' => $cfg['replyToName'],
            'sender_email' => $account['email'],
            'sender_name' => $cfg['fromName'],
            'smtp_host' => $cfg['smtpHost'],
            'smtp_username' => $account['email'],
            'smtp_password' => $account['password'],
            'smtp_secure' => $cfg['SMTPSecure'],
            'smtp_port' => $cfg['Port'],
            'attachments' => $attachments,
        ]);

        if ($ok) {
            $sent++;
            continue;
        }
        $errors[] = $recipient;
    }

    if ($errors) {
        $errorMessage = 'Errore invio comunicazione personalizzata iscrizioni per pratica ID ' . intval($pratica['id'] ?? 0) . '. Destinatari non raggiunti: ' . implode(', ', $errors) . '.';
        iscrizioniPrimeNotifyMailFailure($cfg, $errorMessage);
        return ['ok' => false, 'message' => $errorMessage, 'sent' => $sent, 'errors' => $errors];
    }

    info('[iscrizioni] comunicazione personalizzata inviata pratica=' . intval($pratica['id'] ?? 0) . ' destinatari=' . implode(',', $recipients));
    iscrizioniPrimeRecordEvent((int)($pratica['id'] ?? 0), $eventType, $eventType === 'mail_rinuncia_tablet' ? 'Mail conferma rinuncia tablet inviata ai genitori' : 'Mail personalizzata inviata ai genitori', [
        'oggetto' => $subject,
        'messaggio' => $message . ($signature !== '' ? "\n\nFirma:\n" . $signature : ''),
        'dettagli' => [
            'destinatari' => $recipients,
            'inviate' => $sent,
            'allegati' => array_map('basename', $attachments),
        ],
    ]);
    return ['ok' => true, 'message' => 'Comunicazione inviata ai genitori.', 'sent' => $sent, 'recipients' => $recipients];
}

function iscrizioniPrimeCustomMailKey(string $tipoIscrizione, string $subject, string $message, string $signature): string
{
    return hash('sha256', iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione) . "\n" . trim($subject) . "\n" . trim($message) . "\n" . trim($signature));
}

function iscrizioniPrimeCustomMailAudience(string $audience): string
{
    $audience = strtolower(trim($audience));
    return in_array($audience, ['esterni', 'interni', 'interni_bocciati', 'tutte'], true) ? $audience : 'esterni';
}

function iscrizioniPrimeCustomMailAudienceCondition(string $audience, string $alias = 'p'): string
{
    global $__anno_scolastico_corrente_id;

    $audience = iscrizioniPrimeCustomMailAudience($audience);
    if ($audience === 'interni_bocciati') {
        $annoCondition = intval($__anno_scolastico_corrente_id ?? 0) > 0
            ? ' AND t.id_anno_scolastico = ' . intval($__anno_scolastico_corrente_id) . ' '
            : '';
        return " AND " . iscrizioniPrimeEffectiveInternalCondition($alias) . "
            AND EXISTS (
                SELECT 1
                FROM studente s_ib
                INNER JOIN mastercom_tabelloni_scrutini_studenti ts
                    ON ts.id_studente_gestore = s_ib.id
                INNER JOIN mastercom_tabelloni_scrutini t
                    ON t.id = ts.tabellone_id
                WHERE UPPER(TRIM(s_ib.codice_fiscale)) = UPPER(TRIM($alias.codice_fiscale))
                  AND ts.esito_key IN ('non_ammesso', 'in_corso')
                  $annoCondition
                LIMIT 1
            ) ";
    }
    if ($audience === 'interni') {
        return " AND " . iscrizioniPrimeEffectiveInternalCondition($alias) . " ";
    }
    if ($audience === 'tutte') {
        return '';
    }
    return " AND " . iscrizioniPrimeEffectiveExternalCondition($alias) . " ";
}

function iscrizioniPrimeProvisionalClassExistsSql(string $alias = 'p'): string
{
    global $__anno_scolastico_corrente_id;

    $annoCorrenteId = intval($__anno_scolastico_corrente_id ?? 0);
    if ($annoCorrenteId <= 0) {
        return '0 = 1';
    }

    return "EXISTS (
        SELECT 1
        FROM studente s_prov
        INNER JOIN studente_frequenta sf_prov
            ON sf_prov.id_studente = s_prov.id
           AND sf_prov.id_anno_scolastico = " . dbI($annoCorrenteId) . "
        INNER JOIN classi c_prov
            ON c_prov.id = sf_prov.id_classe
        WHERE UPPER(TRIM(s_prov.codice_fiscale)) = UPPER(TRIM($alias.codice_fiscale))
          AND s_prov.attivo = 1
          AND UPPER(TRIM(c_prov.classe)) IN ('MEDIE', 'EE')
        LIMIT 1
    )";
}

function iscrizioniPrimeEffectiveInternalCondition(string $alias = 'p'): string
{
    return "($alias.studente_interno = 1 AND NOT " . iscrizioniPrimeProvisionalClassExistsSql($alias) . ")";
}

function iscrizioniPrimeEffectiveExternalCondition(string $alias = 'p'): string
{
    return "($alias.studente_interno = 0 OR " . iscrizioniPrimeProvisionalClassExistsSql($alias) . ")";
}

function iscrizioniPrimeCustomBulkPendingCount(string $tipoIscrizione, string $communicationKey, bool $testMode, string $audience = 'esterni'): int
{
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);
    $test = $testMode ? 1 : 0;
    $audienceCondition = iscrizioniPrimeCustomMailAudienceCondition($audience, 'p');

    $count = dbGetValue("
        SELECT COALESCE(SUM(
            CASE
                WHEN p.email_genitore_1 IS NOT NULL
                 AND TRIM(p.email_genitore_1) <> ''
                 AND NOT EXISTS (
                    SELECT 1
                    FROM iscrizioni_prime_custom_mail_log l
                    WHERE l.communication_key = " . dbQ($communicationKey) . "
                      AND l.pratica_id = p.id
                      AND LOWER(TRIM(l.recipient_email)) = LOWER(TRIM(p.email_genitore_1))
                      AND l.stato IN ('inviata','bounce')
                      AND l.test_mode = " . intval($test) . "
                    LIMIT 1
                 )
                THEN 1 ELSE 0
            END
            +
            CASE
                WHEN p.email_genitore_2 IS NOT NULL
                 AND TRIM(p.email_genitore_2) <> ''
                 AND LOWER(TRIM(p.email_genitore_2)) <> LOWER(TRIM(COALESCE(p.email_genitore_1, '')))
                 AND NOT EXISTS (
                    SELECT 1
                    FROM iscrizioni_prime_custom_mail_log l
                    WHERE l.communication_key = " . dbQ($communicationKey) . "
                      AND l.pratica_id = p.id
                      AND LOWER(TRIM(l.recipient_email)) = LOWER(TRIM(p.email_genitore_2))
                      AND l.stato IN ('inviata','bounce')
                      AND l.test_mode = " . intval($test) . "
                    LIMIT 1
                 )
                THEN 1 ELSE 0
            END
        ), 0)
        FROM iscrizioni_prime_pratiche p
        WHERE p.tipo_iscrizione = " . dbQ($tipoIscrizione) . "
          AND p.stato <> 'annullata'
          $audienceCondition
    ");

    return intval($count);
}

function iscrizioniPrimeCustomBulkRecipientCount(string $tipoIscrizione, string $audience = 'esterni'): int
{
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);
    $audienceCondition = iscrizioniPrimeCustomMailAudienceCondition($audience, 'p');

    $count = dbGetValue("
        SELECT COUNT(*)
        FROM (
            SELECT p.id AS pratica_id, LOWER(TRIM(p.email_genitore_1)) AS recipient_email
            FROM iscrizioni_prime_pratiche p
            WHERE p.tipo_iscrizione = " . dbQ($tipoIscrizione) . "
              AND p.stato <> 'annullata'
              $audienceCondition
              AND p.email_genitore_1 IS NOT NULL
              AND TRIM(p.email_genitore_1) <> ''
            UNION
            SELECT p.id AS pratica_id, LOWER(TRIM(p.email_genitore_2)) AS recipient_email
            FROM iscrizioni_prime_pratiche p
            WHERE p.tipo_iscrizione = " . dbQ($tipoIscrizione) . "
              AND p.stato <> 'annullata'
              $audienceCondition
              AND p.email_genitore_2 IS NOT NULL
              AND TRIM(p.email_genitore_2) <> ''
              AND LOWER(TRIM(p.email_genitore_2)) <> LOWER(TRIM(COALESCE(p.email_genitore_1, '')))
        ) recipients
    ");

    return intval($count);
}

function iscrizioniPrimeCustomBulkRecipientEmails(string $tipoIscrizione, string $audience = 'esterni'): array
{
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);
    $audienceCondition = iscrizioniPrimeCustomMailAudienceCondition($audience, 'p');

    $rows = dbGetAll("
        SELECT recipient_email
        FROM (
            SELECT LOWER(TRIM(p.email_genitore_1)) AS recipient_email
            FROM iscrizioni_prime_pratiche p
            WHERE p.tipo_iscrizione = " . dbQ($tipoIscrizione) . "
              AND p.stato <> 'annullata'
              $audienceCondition
              AND p.email_genitore_1 IS NOT NULL
              AND TRIM(p.email_genitore_1) <> ''
            UNION
            SELECT LOWER(TRIM(p.email_genitore_2)) AS recipient_email
            FROM iscrizioni_prime_pratiche p
            WHERE p.tipo_iscrizione = " . dbQ($tipoIscrizione) . "
              AND p.stato <> 'annullata'
              $audienceCondition
              AND p.email_genitore_2 IS NOT NULL
              AND TRIM(p.email_genitore_2) <> ''
              AND LOWER(TRIM(p.email_genitore_2)) <> LOWER(TRIM(COALESCE(p.email_genitore_1, '')))
        ) recipients
        WHERE recipient_email <> ''
        ORDER BY recipient_email
    ") ?: [];

    return array_values(array_unique(array_map(
        static fn($row) => strtolower(trim((string)($row['recipient_email'] ?? ''))),
        $rows
    )));
}

function iscrizioniPrimeCustomBulkSentCount(string $tipoIscrizione, string $communicationKey, bool $testMode, string $audience = 'esterni'): int
{
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);
    $audienceCondition = iscrizioniPrimeCustomMailAudienceCondition($audience, 'p');

    $count = dbGetValue("
        SELECT COUNT(*)
        FROM (
            SELECT DISTINCT p.id AS pratica_id, LOWER(TRIM(l.recipient_email)) AS recipient_email
            FROM iscrizioni_prime_custom_mail_log l
            INNER JOIN iscrizioni_prime_pratiche p
                ON p.id = l.pratica_id
            WHERE p.tipo_iscrizione = " . dbQ($tipoIscrizione) . "
              AND p.stato <> 'annullata'
              $audienceCondition
              AND l.communication_key = " . dbQ($communicationKey) . "
              AND l.stato IN ('inviata','bounce')
              AND l.test_mode = " . intval($testMode ? 1 : 0) . "
              AND (
                    LOWER(TRIM(l.recipient_email)) = LOWER(TRIM(COALESCE(p.email_genitore_1, '')))
                 OR LOWER(TRIM(l.recipient_email)) = LOWER(TRIM(COALESCE(p.email_genitore_2, '')))
              )
        ) sent_recipients
    ");

    return intval($count);
}

function iscrizioniPrimeCustomBulkSubjectSentCount(string $tipoIscrizione, string $subject, bool $testMode, string $audience = 'esterni'): int
{
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);
    $audienceCondition = iscrizioniPrimeCustomMailAudienceCondition($audience, 'p');

    $count = dbGetValue("
        SELECT COUNT(*)
        FROM (
            SELECT DISTINCT p.id AS pratica_id, LOWER(TRIM(l.recipient_email)) AS recipient_email
            FROM iscrizioni_prime_custom_mail_log l
            INNER JOIN iscrizioni_prime_pratiche p
                ON p.id = l.pratica_id
            WHERE p.tipo_iscrizione = " . dbQ($tipoIscrizione) . "
              AND p.stato <> 'annullata'
              $audienceCondition
              AND TRIM(COALESCE(l.subject, '')) = " . dbQ(trim($subject)) . "
              AND l.stato IN ('inviata','bounce')
              AND l.test_mode = " . intval($testMode ? 1 : 0) . "
              AND (
                    LOWER(TRIM(l.recipient_email)) = LOWER(TRIM(COALESCE(p.email_genitore_1, '')))
                 OR LOWER(TRIM(l.recipient_email)) = LOWER(TRIM(COALESCE(p.email_genitore_2, '')))
              )
        ) sent_recipients
    ");

    return intval($count);
}

function iscrizioniPrimeCustomBulkScanSentGmailBySubject(string $subject, array $currentRecipients = [], int $maxPerAccount = 100): array
{
    $subject = trim($subject);
    if ($subject === '') {
        return ['checked' => 0, 'matches' => 0, 'test_matches' => 0, 'accounts' => [], 'warnings' => []];
    }

    $currentRecipientSet = array_fill_keys(array_values(array_unique(array_filter(array_map(
        static fn($email) => strtolower(trim((string)$email)),
        $currentRecipients
    )))), true);
    $accounts = iscrizioniPrimeMailBounceAccounts();
    $checked = 0;
    $matches = 0;
    $testMatches = 0;
    $testRecipients = [];
    $testRecipientsOutsideAudience = [];
    $warnings = [];
    $accountRows = [];

    foreach ($accounts as $accountEmail) {
        $accountChecked = 0;
        $accountMatches = 0;
        $accountTestMatches = 0;
        $samples = [];
        $query = 'in:sent newer_than:45d subject:"' . $subject . '"';

        try {
            $list = iscrizioniPrimeMailGmailApiRequestAs(
                $accountEmail,
                'GET',
                'https://gmail.googleapis.com/gmail/v1/users/' . rawurlencode($accountEmail) . '/messages?q=' . rawurlencode($query) . '&maxResults=' . max(1, min(200, $maxPerAccount))
            );
        } catch (Throwable $e) {
            $warnings[] = 'Account ' . $accountEmail . ': ' . $e->getMessage();
            warning('[iscrizioni] controllo Gmail oggetto saltato account=' . $accountEmail . ' errore=' . $e->getMessage());
            $accountRows[] = [
                'account' => $accountEmail,
                'checked' => 0,
                'matches' => 0,
                'test_matches' => 0,
                'samples' => [],
                'warning' => $e->getMessage(),
            ];
            continue;
        }

        foreach (($list['messages'] ?? []) as $messageRef) {
            $gmailMessageId = trim((string)($messageRef['id'] ?? ''));
            if ($gmailMessageId === '') {
                continue;
            }

            try {
                $message = iscrizioniPrimeMailGmailApiRequestAs(
                    $accountEmail,
                    'GET',
                    'https://gmail.googleapis.com/gmail/v1/users/' . rawurlencode($accountEmail) . '/messages/' . rawurlencode($gmailMessageId) . '?format=full'
                );
            } catch (Throwable $e) {
                $warnings[] = 'Messaggio ' . $gmailMessageId . ' su ' . $accountEmail . ': ' . $e->getMessage();
                warning('[iscrizioni] controllo Gmail oggetto messaggio saltato account=' . $accountEmail . ' gmailMessageId=' . $gmailMessageId . ' errore=' . $e->getMessage());
                continue;
            }

            $checked++;
            $accountChecked++;
            $messageSubject = trim(iscrizioniPrimeMailGmailHeader($message, 'Subject'));
            if (stripos($messageSubject, $subject) === false) {
                continue;
            }

            $matches++;
            $accountMatches++;
            $body = iscrizioniPrimeMailGmailExtractText($message['payload'] ?? []);
            $bodyCheck = strtolower($body . "\n" . (string)($message['snippet'] ?? ''));
            $isTest = strpos($bodyCheck, 'modalita') !== false && strpos($bodyCheck, 'test') !== false;
            $isTest = $isTest || strpos($bodyCheck, 'modalità test') !== false || strpos($bodyCheck, 'sarebbe stata inviata') !== false;
            if ($isTest) {
                $testMatches++;
                $accountTestMatches++;
            }
            $intendedRecipient = '';
            if (preg_match('/sarebbe stata inviata a\s+([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/i', $body . "\n" . (string)($message['snippet'] ?? ''), $matchesRecipient)) {
                $intendedRecipient = strtolower(trim((string)$matchesRecipient[1]));
                if ($intendedRecipient !== '') {
                    $testRecipients[$intendedRecipient] = true;
                    if ($currentRecipientSet && empty($currentRecipientSet[$intendedRecipient])) {
                        $testRecipientsOutsideAudience[$intendedRecipient] = true;
                    }
                }
            }

            if (count($samples) < 5) {
                $samples[] = [
                    'subject' => $messageSubject,
                    'to' => iscrizioniPrimeMailGmailHeader($message, 'To'),
                    'date' => iscrizioniPrimeMailGmailHeader($message, 'Date'),
                    'test' => $isTest,
                    'intended_recipient' => $intendedRecipient,
                ];
            }
        }

        $accountRows[] = [
            'account' => $accountEmail,
            'checked' => $accountChecked,
            'matches' => $accountMatches,
            'test_matches' => $accountTestMatches,
            'samples' => $samples,
        ];
    }

    return [
        'checked' => $checked,
        'matches' => $matches,
        'test_matches' => $testMatches,
        'test_unique_recipients' => count($testRecipients),
        'test_recipients_in_current_audience' => count(array_intersect_key($testRecipients, $currentRecipientSet)),
        'test_recipients_outside_current_audience' => count($testRecipientsOutsideAudience),
        'test_recipients_outside_current_audience_samples' => array_slice(array_keys($testRecipientsOutsideAudience), 0, 20),
        'accounts' => $accountRows,
        'warnings' => $warnings,
    ];
}

function iscrizioniPrimeCustomBulkStatus(string $tipoIscrizione, string $subject, string $message, string $signature, string $audience = 'esterni'): array
{
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);
    $subject = trim($subject);
    $message = trim($message);
    $signature = trim($signature);
    $audience = iscrizioniPrimeCustomMailAudience($audience);

    if ($subject === '' || $message === '') {
        return ['ok' => false, 'message' => 'Inserire oggetto e testo della comunicazione.'];
    }

    $cfg = iscrizioniPrimeMailConfig();
    $communicationKey = iscrizioniPrimeCustomMailKey($tipoIscrizione, $subject, $message, $signature);
    $total = iscrizioniPrimeCustomBulkRecipientCount($tipoIscrizione, $audience);
    $currentRecipients = iscrizioniPrimeCustomBulkRecipientEmails($tipoIscrizione, $audience);
    $realSent = iscrizioniPrimeCustomBulkSentCount($tipoIscrizione, $communicationKey, false, $audience);
    $testSent = iscrizioniPrimeCustomBulkSentCount($tipoIscrizione, $communicationKey, true, $audience);
    $realSentSameSubject = iscrizioniPrimeCustomBulkSubjectSentCount($tipoIscrizione, $subject, false, $audience);
    $testSentSameSubject = iscrizioniPrimeCustomBulkSubjectSentCount($tipoIscrizione, $subject, true, $audience);
    $gmailSubjectScan = iscrizioniPrimeCustomBulkScanSentGmailBySubject($subject, $currentRecipients);

    return [
        'ok' => true,
        'communication_key' => $communicationKey,
        'tipo_iscrizione' => $tipoIscrizione,
        'audience' => $audience,
        'test_mode_config' => !empty($cfg['testMode']),
        'total_recipients' => $total,
        'real_sent' => $realSent,
        'test_sent' => $testSent,
        'real_sent_same_subject' => $realSentSameSubject,
        'test_sent_same_subject' => $testSentSameSubject,
        'real_pending' => max(0, $total - $realSent),
        'test_pending' => max(0, $total - $testSent),
        'exact_match' => ($realSentSameSubject === $realSent && $testSentSameSubject === $testSent),
        'gmail_subject_scan' => $gmailSubjectScan,
    ];
}

function iscrizioniPrimeSendCustomBulkMail(string $tipoIscrizione, string $subject, string $message, string $signature, bool $dryRun = false, string $audience = 'esterni'): array
{
    require_once __DIR__ . '/send-mail.php';

    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);
    $cfg = iscrizioniPrimeMailConfig();
    if (empty($cfg['enabled'])) {
        return ['ok' => false, 'message' => 'Invio mail iscrizioni non abilitato in GestOre.json.'];
    }
    if (empty($cfg['accounts'])) {
        return ['ok' => false, 'message' => 'Nessun account SMTP iscrizioni configurato.'];
    }

    $subject = trim($subject);
    $message = trim($message);
    $signature = trim($signature);
    if ($subject === '' || $message === '') {
        return ['ok' => false, 'message' => 'Inserire oggetto e testo della comunicazione.'];
    }

    $communicationKey = iscrizioniPrimeCustomMailKey($tipoIscrizione, $subject, $message, $signature);
    $testMode = !empty($cfg['testMode']);
    $audience = iscrizioniPrimeCustomMailAudience($audience);
    $audienceCondition = iscrizioniPrimeCustomMailAudienceCondition($audience, 'iscrizioni_prime_pratiche');
    $pendingBefore = iscrizioniPrimeCustomBulkPendingCount($tipoIscrizione, $communicationKey, $testMode, $audience);
    $batchSize = intval($cfg['batchSize']);
    if ($pendingBefore <= 0) {
        return [
            'ok' => true,
            'message' => 'Non ci sono destinatari da inviare per questa comunicazione.',
            'sent' => 0,
            'skipped' => 0,
            'remaining' => 0,
            'last_batch' => true,
            'communication_key' => $communicationKey,
            'audience' => $audience,
            'test_mode' => $testMode,
        ];
    }

    $pratiche = dbGetAll("
        SELECT *
        FROM iscrizioni_prime_pratiche
        WHERE tipo_iscrizione = " . dbQ($tipoIscrizione) . "
          AND stato <> 'annullata'
          $audienceCondition
          AND (
            (
                email_genitore_1 IS NOT NULL
                AND TRIM(email_genitore_1) <> ''
                AND NOT EXISTS (
                    SELECT 1
                    FROM iscrizioni_prime_custom_mail_log l
                    WHERE l.communication_key = " . dbQ($communicationKey) . "
                      AND l.pratica_id = iscrizioni_prime_pratiche.id
                      AND LOWER(TRIM(l.recipient_email)) = LOWER(TRIM(iscrizioni_prime_pratiche.email_genitore_1))
                      AND l.stato IN ('inviata','bounce')
                      AND l.test_mode = " . intval($testMode ? 1 : 0) . "
                    LIMIT 1
                )
            )
            OR
            (
                email_genitore_2 IS NOT NULL
                AND TRIM(email_genitore_2) <> ''
                AND LOWER(TRIM(email_genitore_2)) <> LOWER(TRIM(COALESCE(email_genitore_1, '')))
                AND NOT EXISTS (
                    SELECT 1
                    FROM iscrizioni_prime_custom_mail_log l
                    WHERE l.communication_key = " . dbQ($communicationKey) . "
                      AND l.pratica_id = iscrizioni_prime_pratiche.id
                      AND LOWER(TRIM(l.recipient_email)) = LOWER(TRIM(iscrizioni_prime_pratiche.email_genitore_2))
                      AND l.stato IN ('inviata','bounce')
                      AND l.test_mode = " . intval($testMode ? 1 : 0) . "
                    LIMIT 1
                )
            )
          )
        ORDER BY cognome ASC, nome ASC
        LIMIT " . intval($batchSize * 3) . "
    ");

    $counts = iscrizioniPrimeMailAccountCounts();
    $sent = 0;
    $skipped = 0;
    $errors = [];
    $eventRecordedForPractice = [];

    foreach ($pratiche as $pratica) {
        if ($sent >= $batchSize) {
            break;
        }

        $recipients = iscrizioniPrimeMailRecipientsForPratica($pratica);
        if (!$recipients) {
            $skipped++;
            continue;
        }
        $body = iscrizioniPrimeCustomPracticeMailBody($pratica, $message, $signature);

        foreach ($recipients as $recipient) {
            if ($sent >= $batchSize) {
                break 2;
            }

            $already = intval(dbGetValue("
                SELECT COUNT(*)
                FROM iscrizioni_prime_custom_mail_log
                WHERE communication_key = " . dbQ($communicationKey) . "
                  AND pratica_id = " . dbI((int)$pratica['id']) . "
                  AND LOWER(TRIM(recipient_email)) = " . dbQ($recipient) . "
                  AND stato IN ('inviata','bounce')
                  AND test_mode = " . intval($testMode ? 1 : 0) . "
            ") ?? 0);
            if ($already > 0) {
                $skipped++;
                continue;
            }

            $account = iscrizioniPrimePickMailAccount($cfg, $counts);
            if ($account === null) {
                $remaining = $dryRun ? $pendingBefore : iscrizioniPrimeCustomBulkPendingCount($tipoIscrizione, $communicationKey, $testMode, $audience);
                return [
                    'ok' => empty($errors),
                    'message' => 'Limite giornaliero account raggiunto.',
                    'sent' => $sent,
                    'skipped' => $skipped,
                    'remaining' => $remaining,
                    'last_batch' => false,
                    'communication_key' => $communicationKey,
                    'audience' => $audience,
                    'test_mode' => $testMode,
                    'errors' => $errors,
                ];
            }

            if ($dryRun) {
                $sent++;
                $counts[$account['email']] = intval($counts[$account['email']] ?? 0) + 1;
                continue;
            }

            $actualRecipient = $recipient;
            $recipientName = $recipient;
            $actualBody = $body;
            if ($testMode) {
                $actualRecipient = $account['email'];
                $recipientName = 'Test iscrizioni';
                $actualBody = iscrizioniPrimeMailTestBanner($recipient) . $body;
            }

            $ok = sendMailCustom($actualRecipient, $recipientName, $subject, $actualBody, [
                'from_email' => iscrizioniPrimePublicFromEmail($cfg, $account),
                'from_name' => $cfg['fromName'],
                'reply_to_email' => $cfg['replyToEmail'] !== '' ? $cfg['replyToEmail'] : $account['email'],
                'reply_to_name' => $cfg['replyToName'],
                'sender_email' => $account['email'],
                'sender_name' => $cfg['fromName'],
                'smtp_host' => $cfg['smtpHost'],
                'smtp_username' => $account['email'],
                'smtp_password' => $account['password'],
                'smtp_secure' => $cfg['SMTPSecure'],
                'smtp_port' => $cfg['Port'],
            ]);
            $dispatchResult = $GLOBALS['__sendMailLastDispatchResult'] ?? [];

            dbExec("
                INSERT INTO iscrizioni_prime_custom_mail_log
                    (pratica_id, tipo_iscrizione, communication_key, recipient_email, account_email, subject, stato, test_mode, errore, sent_at, created_at)
                VALUES
                    (
                        " . dbI((int)$pratica['id']) . ",
                        " . dbQ($tipoIscrizione) . ",
                        " . dbQ($communicationKey) . ",
                        " . dbQ($recipient) . ",
                        " . dbQ($account['email']) . ",
                        " . dbQ($subject) . ",
                        " . dbQ($ok ? 'inviata' : 'errore') . ",
                        " . intval($testMode ? 1 : 0) . ",
                        " . dbQ($ok ? null : ((string)($dispatchResult['error'] ?? '') !== '' ? (string)$dispatchResult['error'] : 'sendMailCustom ha restituito false')) . ",
                        " . ($ok ? 'NOW()' : 'NULL') . ",
                        NOW()
                    )
                ON DUPLICATE KEY UPDATE
                    account_email = VALUES(account_email),
                    subject = VALUES(subject),
                    stato = VALUES(stato),
                    errore = VALUES(errore),
                    sent_at = VALUES(sent_at),
                    created_at = NOW()
            ");

            if ($ok) {
                $sent++;
                $counts[$account['email']] = intval($counts[$account['email']] ?? 0) + 1;
                $eventKey = (int)$pratica['id'];
                if (!$testMode && empty($eventRecordedForPractice[$eventKey])) {
                    iscrizioniPrimeRecordEvent($eventKey, 'mail_massiva', 'Comunicazione massiva inviata ai genitori', [
                        'oggetto' => $subject,
                        'messaggio' => $message . ($signature !== '' ? "\n\nFirma:\n" . $signature : ''),
                        'dettagli' => [
                            'audience' => $audience,
                            'communication_key' => $communicationKey,
                        ],
                    ]);
                    $eventRecordedForPractice[$eventKey] = true;
                }
            } else {
                $errors[] = $recipient;
            }
        }
    }

    $remaining = $dryRun ? $pendingBefore : iscrizioniPrimeCustomBulkPendingCount($tipoIscrizione, $communicationKey, $testMode, $audience);
    $prefix = $testMode ? 'Modalita test attiva: mail inviate agli account mittenti. ' : '';
    if ($errors) {
        $resultMessage = $prefix . 'Invio completato con errori.';
    } elseif ($sent <= 0) {
        $resultMessage = 'Non ci sono destinatari da inviare in questo lotto.';
    } elseif (!$dryRun && $remaining <= 0) {
        $resultMessage = $prefix . 'Ultimo lotto completato: comunicazione inviata a tutti i destinatari.';
    } else {
        $resultMessage = $prefix . 'Lotto completato. Restano ' . intval($remaining) . ' destinatari da inviare.';
    }

    return [
        'ok' => empty($errors),
        'message' => $resultMessage,
        'sent' => $sent,
        'skipped' => $skipped,
        'remaining' => $remaining,
        'last_batch' => !$dryRun && $remaining <= 0,
        'communication_key' => $communicationKey,
        'audience' => $audience,
        'test_mode' => $testMode,
        'errors' => $errors,
    ];
}

function iscrizioniPrimeDocumentStatusLabel(array $document): string
{
    $stato = (string)($document['stato'] ?? 'mancante');
    if ($stato === 'consegna_cartacea') {
        return 'Consegna cartacea in segreteria';
    }
    if (in_array($stato, ['caricato', 'estratto', 'verificato'], true)) {
        return 'Caricato online';
    }
    return 'Non caricato';
}

function iscrizioniPrimeMailDocumentsTable(array $pratica): string
{
    $labels = iscrizioniPrimeDocumentTypes($pratica);
    $confirmed = iscrizioniPrimeMailConfirmedData($pratica);
    $required = array_flip(iscrizioniPrimeRequiredDocumentTypes($pratica, $confirmed));
    $documents = iscrizioniPrimeDocumentsForPratica((int)$pratica['id']);
    $rows = '';

    foreach ($documents as $document) {
        $tipo = (string)($document['tipo_documento'] ?? '');
        if ($tipo === 'altro' && (string)($document['stato'] ?? 'mancante') === 'mancante') {
            continue;
        }
        if (in_array($tipo, ['documento_identita_genitore_2', 'codice_fiscale_genitore_2', 'documento_cf_genitore_2'], true) && !hasSecondResponsibleForIscrizioniPrime($pratica, $confirmed)) {
            continue;
        }

        $isRequired = isset($required[$tipo]);
        $status = iscrizioniPrimeDocumentStatusLabel($document);
        $statusShort = $status === 'Consegna cartacea in segreteria' ? 'Consegna cartacea' : $status;
        $statusColor = $status === 'Caricato online'
            ? '#166534'
            : ($status === 'Consegna cartacea in segreteria' ? '#92400e' : '#64748b');
        $note = $isRequired ? 'Richiesto' : 'Facoltativo';
        $detail = trim((string)($document['original_name'] ?? ''));
        if ($detail !== '' && $status !== 'Non caricato') {
            $note .= ' - ' . $detail;
        }

        $rows .= '
            <tr>
                <td width="52%" style="padding:10px 12px;border-bottom:1px solid #e5e7eb;color:#172033;font-weight:600;line-height:1.35;vertical-align:top;">' . iscrizioniPrimeMailEscape($labels[$tipo] ?? $tipo) . '</td>
                <td width="48%" style="padding:10px 12px;border-bottom:1px solid #e5e7eb;vertical-align:top;line-height:1.35;">
                    <div style="color:' . $statusColor . ';font-weight:800;">' . iscrizioniPrimeMailEscape($statusShort) . '</div>
                    <div style="color:#64748b;font-size:13px;margin-top:3px;">' . iscrizioniPrimeMailEscape($note) . '</div>
                </td>
            </tr>
        ';
    }

    return '
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
            <thead>
                <tr>
                    <th align="left" width="52%" style="padding:9px 12px;background:#f8fafc;border-bottom:1px solid #e5e7eb;color:#334155;">Documento</th>
                    <th align="left" width="48%" style="padding:9px 12px;background:#f8fafc;border-bottom:1px solid #e5e7eb;color:#334155;">Stato</th>
                </tr>
            </thead>
            <tbody>' . $rows . '</tbody>
        </table>
    ';
}

function iscrizioniPrimePaperDocumentLabels(array $pratica): array
{
    $labels = iscrizioniPrimeDocumentTypes($pratica);
    $confirmed = iscrizioniPrimeMailConfirmedData($pratica);
    $documents = iscrizioniPrimeDocumentsForPratica((int)$pratica['id']);
    $paper = [];

    foreach ($documents as $document) {
        $tipo = (string)($document['tipo_documento'] ?? '');
        if ((string)($document['stato'] ?? '') !== 'consegna_cartacea') {
            continue;
        }
        if ($tipo === 'altro') {
            continue;
        }
        if (in_array($tipo, ['documento_identita_genitore_2', 'codice_fiscale_genitore_2', 'documento_cf_genitore_2'], true) && !hasSecondResponsibleForIscrizioniPrime($pratica, $confirmed)) {
            continue;
        }
        $paper[] = $labels[$tipo] ?? $tipo;
    }

    return $paper;
}

function iscrizioniPrimeSubmissionConfirmationBody(array $pratica): string
{
    global $__settings;

    $nomeStudente = trim((string)(($pratica['nome'] ?? '') . ' ' . ($pratica['cognome'] ?? '')));
    $istituto = trim((string)($__settings->local->nomeIstituto ?? 'Istituto'));
    $anno = trim((string)($pratica['anno_scolastico'] ?? ''));
    $classeTarget = iscrizioniPrimeClasseTargetLabel($pratica);
    $confirmed = iscrizioniPrimeMailConfirmedData($pratica);
    $responsabile1 = trim((string)(($pratica['responsabile_1_cognome'] ?? '') . ' ' . ($pratica['responsabile_1_nome'] ?? '')));
    $responsabile2 = trim((string)(($pratica['responsabile_2_cognome'] ?? '') . ' ' . ($pratica['responsabile_2_nome'] ?? '')));
    $paperDocuments = iscrizioniPrimePaperDocumentLabels($pratica);
    $paperNoticeHtml = '';
    if ($paperDocuments) {
        $paperRows = '';
        foreach ($paperDocuments as $paperDocument) {
            $paperRows .= '<li style="margin:3px 0;">' . iscrizioniPrimeMailEscape($paperDocument) . '</li>';
        }
        $paperLink = iscrizioniPrimePublicLinkForPratica((int)$pratica['id']);
        $paperNoticeHtml = '
                        <div style="border:3px solid #f59e0b;background:#fffbeb;border-radius:10px;padding:16px 18px;margin:22px 0;color:#7c2d12;line-height:1.55;">
                            <div style="font-size:20px;font-weight:900;margin-bottom:8px;">Attenzione: puoi ancora caricare online i documenti segnati come cartacei</div>
                            <p style="margin:0 0 10px;">
                                Nella pratica hai indicato che questi documenti verranno consegnati in copia cartacea alla Segreteria didattica:
                            </p>
                            <ul style="margin:0 0 12px 20px;padding:0;font-weight:800;">' . $paperRows . '</ul>
                            <p style="margin:0 0 12px;">
                                Se cambi idea, non e\' necessario venire di persona in segreteria: puoi riaprire la pratica, cancellare la scelta cartacea, caricare il PDF o fotografare il documento e inviare di nuovo la conferma.
                            </p>
                            <p style="margin:16px 0 0;text-align:center;">
                                <a href="' . htmlspecialchars($paperLink, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#92400e;color:#ffffff;text-decoration:none;padding:13px 20px;border-radius:6px;font-weight:900;">
                                    Modifica la pratica e carica i documenti online
                                </a>
                            </p>
                        </div>
        ';
    }

    $studentRows = iscrizioniPrimeMailRows([
        ['Studente', $nomeStudente],
        ['Codice fiscale', $pratica['codice_fiscale'] ?? ''],
        ['Data nascita', iscrizioniPrimeFormatDateIt($pratica['data_nascita'] ?? '')],
        ['Corso richiesto', $pratica['corso_studi'] ?? ''],
        ['Email studente', iscrizioniPrimeMailValue($pratica, $confirmed, 'email_studente')],
        ['Telefono studente', iscrizioniPrimeMailValue($pratica, $confirmed, 'telefono_studente')],
    ]);

    $responsibleRows = iscrizioniPrimeMailRows([
        [$pratica['responsabile_1_tipo'] ?: 'Responsabile 1', $responsabile1],
        ['Email responsabile 1', iscrizioniPrimeMailValue($pratica, $confirmed, 'email_genitore_1')],
        ['Telefono responsabile 1', iscrizioniPrimeMailValue($pratica, $confirmed, 'telefono_genitore_1')],
        [$pratica['responsabile_2_tipo'] ?: 'Responsabile 2', $responsabile2, true],
        ['Email responsabile 2', iscrizioniPrimeMailValue($pratica, $confirmed, 'email_genitore_2'), true],
        ['Telefono responsabile 2', iscrizioniPrimeMailValue($pratica, $confirmed, 'telefono_genitore_2'), true],
    ]);

    $terzeRowsHtml = '';
    if (iscrizioniPrimeTipoIscrizioneFromPratica($pratica) === 'terze') {
        $materie = $confirmed['carenze_formative_materie'] ?? [];
        if (!is_array($materie)) {
            $materie = [];
        }
        $altro = trim((string)($confirmed['carenze_formative_altro'] ?? ''));
        if ($altro !== '') {
            $materie[] = $altro;
        }
        $carenze = (string)($confirmed['carenze_formative_dichiarate'] ?? '');
        $terzeRows = iscrizioniPrimeMailRows([
            ['Nulla osta richiesto alla scuola di provenienza', !empty($confirmed['nulla_osta_richiesto']) ? 'Si' : 'No'],
            ['Data richiesta nulla osta', iscrizioniPrimeFormatDateIt($confirmed['nulla_osta_data'] ?? '')],
            ['Carenze formative dichiarate', $carenze === 'si' ? 'Si' : ($carenze === 'no' ? 'No' : '')],
            ['Materie con carenza', implode(', ', array_values(array_filter($materie, 'strlen')))],
        ]);
        $terzeRowsHtml = '
                        <h3 style="font-size:18px;margin:22px 0 8px;color:#172033;">Informazioni iscrizione in terza</h3>
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">' . $terzeRows . '</table>
        ';
    }

    return '
        <div style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#172033;">
            <div style="max-width:760px;margin:0 auto;padding:22px 12px;">
                <div style="background:#ffffff;border:1px solid #dbe3ef;border-radius:8px;overflow:hidden;">
                    <div style="background:#0f766e;color:#ffffff;padding:20px 22px;">
                        <div style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;opacity:.9;">' . iscrizioniPrimeMailEscape($istituto) . '</div>
                        <div style="font-size:24px;font-weight:800;margin-top:4px;">Conferma dati iscrizione ricevuta</div>
                        <div style="font-size:15px;margin-top:4px;">' . ucfirst($classeTarget) . ' - anno scolastico ' . iscrizioniPrimeMailEscape($anno) . '</div>
                    </div>
                    <div style="padding:22px;">
                        <p style="margin:0 0 12px;font-size:16px;">Gentile famiglia,</p>
                        <p style="margin:0 0 16px;font-size:16px;line-height:1.5;">
                            confermiamo che la procedura di conferma dati per l\'iscrizione di
                            <strong>' . iscrizioniPrimeMailEscape($nomeStudente) . '</strong> e\' stata inviata correttamente.
                        </p>
                        <div style="border-left:5px solid #15803d;background:#ecfdf5;padding:12px 14px;border-radius:6px;margin:16px 0;color:#14532d;font-weight:700;">
                            La segreteria didattica ha ricevuto i dati confermati e il riepilogo dei documenti.
                        </div>

                        <h3 style="font-size:18px;margin:22px 0 8px;color:#172033;">Riepilogo studente</h3>
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">' . $studentRows . '</table>

                        <h3 style="font-size:18px;margin:22px 0 8px;color:#172033;">Contatti confermati</h3>
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">' . $responsibleRows . '</table>

                        ' . $terzeRowsHtml . '

                        <h3 style="font-size:18px;margin:22px 0 8px;color:#172033;">Documenti</h3>
                        ' . iscrizioniPrimeMailDocumentsTable($pratica) . '

                        ' . $paperNoticeHtml . '
                        <p style="margin:18px 0 0;color:#172033;line-height:1.5;">Cordiali saluti<br><strong>Segreteria didattica</strong></p>
                    </div>
                </div>
            </div>
        </div>
    ';
}

function iscrizioniPrimeGmailHeader(array $message, string $name): string
{
    foreach (($message['payload']['headers'] ?? []) as $header) {
        if (strcasecmp((string)($header['name'] ?? ''), $name) === 0) {
            return (string)($header['value'] ?? '');
        }
    }
    return '';
}

function iscrizioniPrimeGmailDraftSubject(array $cfg, string $tipoIscrizione): string
{
    return iscrizioniPrimeDraftSubject($tipoIscrizione, $cfg);
}

function iscrizioniPrimeGmailSentSubjectFromDraftSubject(string $draftSubject): string
{
    return trim((string)preg_replace('/^\s*\[BOZZA\]\s*/iu', '', $draftSubject));
}

function iscrizioniPrimeMailParseAddress(string $headerValue): array
{
    $headerValue = trim($headerValue);
    if ($headerValue === '') {
        return ['email' => '', 'name' => ''];
    }

    if (preg_match('/^(.*?)<([^>]+)>$/', $headerValue, $matches)) {
        $name = trim(trim((string)$matches[1]), "\"'");
        $email = trim((string)$matches[2]);
        return ['email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '', 'name' => $name];
    }

    return ['email' => filter_var($headerValue, FILTER_VALIDATE_EMAIL) ? $headerValue : '', 'name' => ''];
}

function iscrizioniPrimeGmailDraftFetch(string $accountEmail, string $draftSubject): array
{
    $scope = 'https://www.googleapis.com/auth/gmail.readonly https://www.googleapis.com/auth/gmail.send';
    $query = 'in:drafts subject:"' . $draftSubject . '"';
    $list = sendMailGmailApiRequestRaw(
        $accountEmail,
        $scope,
        'GET',
        'https://gmail.googleapis.com/gmail/v1/users/' . rawurlencode($accountEmail) . '/drafts?q=' . rawurlencode($query) . '&maxResults=10'
    );

    foreach (($list['drafts'] ?? []) as $draftRef) {
        $draftId = trim((string)($draftRef['id'] ?? ''));
        if ($draftId === '') {
            continue;
        }
        $draft = sendMailGmailApiRequestRaw(
            $accountEmail,
            $scope,
            'GET',
            'https://gmail.googleapis.com/gmail/v1/users/' . rawurlencode($accountEmail) . '/drafts/' . rawurlencode($draftId) . '?format=full'
        );
        $message = $draft['message'] ?? [];
        if (is_array($message) && trim(iscrizioniPrimeGmailHeader($message, 'Subject')) === $draftSubject) {
            return $message;
        }
    }

    throw new Exception('Bozza Gmail non trovata in ' . $accountEmail . ' con oggetto: ' . $draftSubject);
}

function iscrizioniPrimeGmailDraftCollectParts(array $payload, string $accountEmail, string $messageId, array &$parts): void
{
    $mimeType = strtolower((string)($payload['mimeType'] ?? ''));
    $filename = trim((string)($payload['filename'] ?? ''));
    $body = $payload['body'] ?? [];

    if ($filename !== '') {
        $data = '';
        if (!empty($body['attachmentId'])) {
            $attachment = sendMailGmailApiRequestRaw(
                $accountEmail,
                'https://www.googleapis.com/auth/gmail.readonly https://www.googleapis.com/auth/gmail.send',
                'GET',
                'https://gmail.googleapis.com/gmail/v1/users/' . rawurlencode($accountEmail) . '/messages/' . rawurlencode($messageId) . '/attachments/' . rawurlencode((string)$body['attachmentId'])
            );
            $data = sendMailGmailApiDecode((string)($attachment['data'] ?? ''));
        } elseif (!empty($body['data'])) {
            $data = sendMailGmailApiDecode((string)$body['data']);
        }

        if ($data === '') {
            warning('[iscrizioni] allegato bozza Gmail senza dati leggibili: ' . $filename);
            return;
        }

        $parts['attachments'][] = [
            'name' => $filename,
            'mime' => $mimeType !== '' ? $mimeType : 'application/octet-stream',
            'data' => $data,
        ];
        return;
    }

    if (!empty($body['data'])) {
        $decoded = sendMailGmailApiDecode((string)$body['data']);
        if ($mimeType === 'text/html') {
            $parts['html'][] = $decoded;
        } elseif ($mimeType === 'text/plain') {
            $parts['plain'][] = $decoded;
        }
    }

    foreach (($payload['parts'] ?? []) as $child) {
        if (is_array($child)) {
            iscrizioniPrimeGmailDraftCollectParts($child, $accountEmail, $messageId, $parts);
        }
    }
}

function iscrizioniPrimeMailApplyDraftPlaceholders(string $content, array $pratica, string $link, string $tipoIscrizione): string
{
    $nomeStudente = trim((string)(($pratica['nome'] ?? '') . ' ' . ($pratica['cognome'] ?? '')));
    $bloccoTraduzioni = iscrizioniPrimeMailTranslationsBlock($tipoIscrizione);
    $plainBloccoTraduzioni = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bloccoTraduzioni)));

    $isHtml = stripos($content, '<html') !== false || stripos($content, '<body') !== false || stripos($content, '<p') !== false || stripos($content, '<div') !== false || stripos($content, '<table') !== false;
    $block = $isHtml ? $bloccoTraduzioni : $plainBloccoTraduzioni;

    $replacements = [
        '{{LINK_PERSONALE}}' => $link,
        '{LINK_PERSONALE}' => $link,
        '{{NOME_STUDENTE}}' => $nomeStudente,
        '{NOME_STUDENTE}' => $nomeStudente,
        '{{BLOCCO_TRADUZIONI}}' => $block,
        '{blocco_traduzioni}' => $block,
        '{blocco traduzioni}' => $block,
        '{{LINK_COMUNICAZIONE_MULTILINGUE}}' => iscrizioniPrimeMailTranslationsUrl($tipoIscrizione),
        '{traduzioni}' => iscrizioniPrimeMailTranslationsUrl($tipoIscrizione),
    ];

    return strtr($content, $replacements);
}

function iscrizioniPrimeSendMailFromGmailDraft(array $cfg, array $account, array $pratica, string $recipient, string $link, string $originalRecipientForBody, string $tipoIscrizione): bool
{
    $accountEmail = strtolower(trim((string)($account['email'] ?? '')));
    $draftSubject = iscrizioniPrimeGmailDraftSubject($cfg, $tipoIscrizione);
    $message = iscrizioniPrimeGmailDraftFetch($accountEmail, $draftSubject);
    $messageId = trim((string)($message['id'] ?? ''));
    if ($messageId === '') {
        throw new Exception('Bozza Gmail senza message id per account ' . $accountEmail);
    }

    $parts = ['html' => [], 'plain' => [], 'attachments' => []];
    iscrizioniPrimeGmailDraftCollectParts($message['payload'] ?? [], $accountEmail, $messageId, $parts);

    $html = trim(implode("\n", $parts['html']));
    $plain = trim(implode("\n", $parts['plain']));
    if ($html === '' && $plain === '') {
        throw new Exception('La bozza Gmail non contiene testo leggibile: ' . $draftSubject);
    }
    if (empty($parts['attachments'])) {
        error('[iscrizioni] invio bloccato: la bozza Gmail non contiene allegati: ' . $draftSubject . ' account=' . $accountEmail);
        return false;
    }
    info('[iscrizioni] bozza Gmail caricata account=' . $accountEmail . ' oggetto=' . $draftSubject . ' allegati=' . count($parts['attachments']));

    $subject = preg_replace('/^\s*\[BOZZA\]\s*/iu', '', $draftSubject);
    $draftFrom = iscrizioniPrimeMailParseAddress(iscrizioniPrimeGmailHeader($message, 'From'));
    $fromEmail = iscrizioniPrimePublicFromEmail($cfg, $account);
    $fromName = $draftFrom['name'] !== '' ? $draftFrom['name'] : $cfg['fromName'];
    $replyToEmail = $cfg['replyToEmail'] !== '' ? $cfg['replyToEmail'] : $fromEmail;
    $replyToName = $cfg['replyToName'] !== '' ? $cfg['replyToName'] : $fromName;
    $html = $html !== '' ? iscrizioniPrimeMailApplyDraftPlaceholders($html, $pratica, $link, $tipoIscrizione) : '';
    $plain = $plain !== '' ? iscrizioniPrimeMailApplyDraftPlaceholders($plain, $pratica, $link, $tipoIscrizione) : '';

    if ($originalRecipientForBody !== '') {
        $banner = iscrizioniPrimeMailTestBanner($originalRecipientForBody);
        $html = $html !== '' ? ($banner . $html) : ($banner . nl2br(htmlspecialchars($plain, ENT_QUOTES, 'UTF-8')));
        $plain = "Modalita test: questa conferma sarebbe stata inviata a $originalRecipientForBody.\n\n" . $plain;
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->isSMTP();
        $mail->Mailer = 'smtp';
        $mail->SMTPDebug = 0;
        $mail->Host = $cfg['smtpHost'];
        $mail->SMTPAuth = true;
        $mail->Username = $accountEmail;
        $mail->Password = (string)($account['password'] ?? '');
        $mail->SMTPSecure = $cfg['SMTPSecure'];
        $mail->SMTPAutoTLS = false;
        $mail->Port = intval($cfg['Port']);
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
        $mail->IsHTML($html !== '');
        $mail->addAddress($recipient, $recipient);
        $mail->setFrom($fromEmail, $fromName, true);
        $mail->addReplyTo($replyToEmail, $replyToName);
        $mail->Sender = $accountEmail;
        $mail->Subject = $subject;
        if ($html !== '') {
            $mail->msgHTML($html);
            if ($plain !== '') {
                $mail->AltBody = $plain;
            }
        } else {
            $mail->Body = $plain;
        }
        foreach ($parts['attachments'] as $attachment) {
            $mail->addStringAttachment(
                (string)$attachment['data'],
                (string)$attachment['name'],
                'base64',
                (string)$attachment['mime']
            );
        }

        return sendMailDispatch($mail, $accountEmail, 'iscrizioniDraft', $recipient, $subject);
    } catch (Throwable $e) {
        error('[iscrizioni] errore invio da bozza Gmail: ' . $e->getMessage());
        try {
            $mail->smtpClose();
        } catch (Throwable $e2) {
        }
        return false;
    }
}

function iscrizioniPrimeCorrectLinkMailBody(array $pratica, string $link, string $tipoIscrizione, bool $manualResend = false): string
{
    global $__settings;

    $nomeStudente = trim((string)(($pratica['nome'] ?? '') . ' ' . ($pratica['cognome'] ?? '')));
    $istituto = trim((string)($__settings->local->nomeIstituto ?? 'Istituto'));
    $anno = trim((string)($pratica['anno_scolastico'] ?? ''));
    $classeTarget = iscrizioniPrimeClasseTargetLabel($pratica);
    $tipoLabel = $tipoIscrizione === 'terze' ? 'classi terze' : 'classi prime';

    $title = $manualResend ? 'Link pratica iscrizione' : 'Rettifica link conferma dati iscrizione';
    $intro = $manualResend
        ? 'ti inviamo nuovamente il link personale per accedere alla pratica di iscrizione di'
        : 'in una precedente comunicazione relativa alla pratica di iscrizione di';
    $introAfter = $manualResend ? '.' : 'potrebbe essere stato inviato un link non piu valido.';
    $warning = $manualResend
        ? '<strong>Usa questo link per rientrare nella pratica.</strong><br>Il link consente di consultare i dati e integrare eventuali documenti finche la pratica non viene verificata dalla segreteria.'
        : '<strong>Usate esclusivamente il nuovo link indicato sotto.</strong><br>Il link ricevuto in precedenza puo essere ignorato.';
    $button = $manualResend ? 'Apri la pratica iscrizione' : 'Apri il link corretto';
    $after = $manualResend
        ? 'Non e necessario ricompilare da capo: eventuali dati o documenti gia salvati nella pratica restano disponibili.'
        : 'Non e necessario ricompilare da capo: eventuali dati o documenti gia salvati nella pratica restano disponibili.';
    $scopeText = $manualResend
        ? 'Questa comunicazione riguarda solo il link personale per la procedura online GestOre per le '
        : 'Questa rettifica riguarda solo il link personale per la procedura online GestOre per le ';

    return '
        <div style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#172033;">
            <div style="max-width:760px;margin:0 auto;padding:22px 12px;">
                <div style="background:#ffffff;border:1px solid #dbe3ef;border-radius:8px;overflow:hidden;">
                    <div style="background:#0f766e;color:#ffffff;padding:20px 22px;">
                        <div style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;opacity:.9;">' . iscrizioniPrimeMailEscape($istituto) . '</div>
                        <div style="font-size:24px;font-weight:800;margin-top:4px;">' . iscrizioniPrimeMailEscape($title) . '</div>
                        <div style="font-size:15px;margin-top:4px;">' . iscrizioniPrimeMailEscape(ucfirst($classeTarget)) . ' - anno scolastico ' . iscrizioniPrimeMailEscape($anno) . '</div>
                    </div>
                    <div style="padding:22px;">
                        <p style="margin:0 0 14px;line-height:1.55;">Gentile famiglia,</p>
                        <p style="margin:0 0 14px;line-height:1.55;">
                            ' . iscrizioniPrimeMailEscape($intro) . '
                            <strong>' . iscrizioniPrimeMailEscape($nomeStudente) . '</strong>
                            ' . iscrizioniPrimeMailEscape($introAfter) . '
                        </p>
                        <div style="border-left:5px solid #f59e0b;background:#fffbeb;border-radius:8px;padding:14px 16px;margin:18px 0;color:#7c2d12;line-height:1.55;">
                            ' . $warning . '
                        </div>
                        <p style="margin:18px 0;text-align:center;">
                            <a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;padding:13px 20px;border-radius:6px;font-weight:800;">
                                ' . iscrizioniPrimeMailEscape($button) . '
                            </a>
                        </p>
                        <p style="margin:0 0 14px;line-height:1.55;">
                            ' . iscrizioniPrimeMailEscape($after) . '
                        </p>
                        <div style="border-left:5px solid #0ea5e9;background:#eaf6fc;border-radius:8px;padding:12px 14px;margin:18px 0;color:#0f172a;line-height:1.45;">
                            ' . iscrizioniPrimeMailEscape($scopeText) . iscrizioniPrimeMailEscape($tipoLabel) . '.
                        </div>
                        <p style="margin:18px 0 0;color:#172033;line-height:1.5;">Cordiali saluti<br><strong>Segreteria didattica</strong></p>
                    </div>
                </div>
            </div>
        </div>
    ';
}

function iscrizioniPrimeSendCorrectLinkMail(array $cfg, array $account, array $pratica, string $recipient, string $link, string $tipoIscrizione, bool $manualResend = false): bool
{
    require_once __DIR__ . '/send-mail.php';

    $recipient = strtolower(trim($recipient));
    $body = iscrizioniPrimeCorrectLinkMailBody($pratica, $link, $tipoIscrizione, $manualResend);
    $nomeStudente = trim((string)(($pratica['nome'] ?? '') . ' ' . ($pratica['cognome'] ?? '')));
    $subjectSuffix = $tipoIscrizione === 'terze' ? 'classi terze' : 'classi prime';
    $subject = ($manualResend ? 'Link pratica iscrizione ' : 'Rettifica link conferma dati iscrizione ') . $subjectSuffix;
    if ($nomeStudente !== '') {
        $subject .= ' - ' . $nomeStudente;
    }

    $actualRecipient = $recipient;
    $recipientName = $recipient;
    $actualBody = $body;
    if (!empty($cfg['testMode'])) {
        $actualRecipient = strtolower(trim((string)($account['email'] ?? '')));
        $recipientName = 'Test iscrizioni';
        $actualBody = iscrizioniPrimeMailTestBanner($recipient) . $body;
    }

    return sendMailCustom($actualRecipient, $recipientName, $subject, $actualBody, [
        'from_email' => iscrizioniPrimePublicFromEmail($cfg, $account),
        'from_name' => $cfg['fromName'],
        'reply_to_email' => $cfg['replyToEmail'] !== '' ? $cfg['replyToEmail'] : $account['email'],
        'reply_to_name' => $cfg['replyToName'],
        'sender_email' => $account['email'],
        'sender_name' => $cfg['fromName'],
        'smtp_host' => $cfg['smtpHost'],
        'smtp_username' => $account['email'],
        'smtp_password' => $account['password'],
        'smtp_secure' => $cfg['SMTPSecure'],
        'smtp_port' => $cfg['Port'],
    ]);
}

function iscrizioniPrimeResendPracticeLink(int $practiceId): array
{
    require_once __DIR__ . '/send-mail.php';

    $practiceId = intval($practiceId);
    if ($practiceId <= 0) {
        return ['ok' => false, 'message' => 'Pratica non valida.'];
    }

    $pratica = dbGetFirst("SELECT * FROM iscrizioni_prime_pratiche WHERE id = " . dbI($practiceId) . " LIMIT 1");
    if (!$pratica) {
        return ['ok' => false, 'message' => 'Pratica non trovata.'];
    }
    if ((string)($pratica['stato'] ?? '') === 'annullata') {
        return ['ok' => false, 'message' => 'La pratica e annullata: non posso reinviare il link.'];
    }

    $cfg = iscrizioniPrimeMailConfig();
    if (empty($cfg['enabled'])) {
        return ['ok' => false, 'message' => 'Invio mail iscrizioni non abilitato in GestOre.json.'];
    }
    if (empty($cfg['accounts'])) {
        return ['ok' => false, 'message' => 'Nessun account SMTP iscrizioni configurato.'];
    }

    $recipients = iscrizioniPrimeMailRecipientsForPratica($pratica);
    if (!$recipients) {
        return ['ok' => false, 'message' => 'Nessuna email genitore valida presente nella pratica.'];
    }

    $token = iscrizioniPrimeSetToken($practiceId);
    $link = ($GLOBALS['__http_base_link'] ?? '') . '/iscrizioni/conferma.php?t=' . rawurlencode($token);
    $tipoIscrizione = iscrizioniPrimeTipoIscrizioneFromPratica($pratica);
    $counts = iscrizioniPrimeMailAccountCounts();
    $sent = 0;
    $errors = [];
    $usedRecipients = [];

    foreach ($recipients as $recipient) {
        $account = iscrizioniPrimePickMailAccount($cfg, $counts);
        if ($account === null) {
            $errors[] = 'Limite giornaliero degli account iscrizioni raggiunto.';
            break;
        }

        $ok = iscrizioniPrimeSendCorrectLinkMail($cfg, $account, $pratica, $recipient, $link, $tipoIscrizione, true);
        $dispatchResult = $GLOBALS['__sendMailLastDispatchResult'] ?? [];
        dbExec("
            INSERT INTO iscrizioni_prime_mail_log
            (pratica_id, recipient_email, account_email, token_last4, stato, test_mode, transport, gmail_message_id, errore, sent_at, created_at)
            VALUES (
                " . dbI($practiceId) . ",
                " . dbQ($recipient) . ",
                " . dbQ($account['email']) . ",
                " . dbQ(substr($token, -4)) . ",
                " . dbQ($ok ? 'inviata' : 'errore') . ",
                " . (!empty($cfg['testMode']) ? '1' : '0') . ",
                " . dbQ((string)($dispatchResult['transport'] ?? '')) . ",
                " . dbQ((string)($dispatchResult['gmail_message_id'] ?? '')) . ",
                " . dbQ($ok ? null : ((string)($dispatchResult['error'] ?? '') !== '' ? (string)$dispatchResult['error'] : 'sendMailCustom ha restituito false')) . ",
                " . ($ok ? 'NOW()' : 'NULL') . ",
                NOW()
            )
        ");

        if ($ok) {
            $sent++;
            $usedRecipients[] = $recipient;
            $counts[$account['email']] = intval($counts[$account['email']] ?? 0) + 1;
        } else {
            $errors[] = $recipient;
        }
    }

    if ($sent > 0) {
        iscrizioniPrimeRecordEvent($practiceId, 'mail_link_pratica', 'Link pratica reinviato ai genitori', [
            'oggetto' => 'Link pratica iscrizione',
            'messaggio' => 'Reinvio manuale del link personale della pratica.',
            'dettagli' => [
                'recipients' => $usedRecipients,
                'token_last4' => substr($token, -4),
            ],
        ]);
    }

    return [
        'ok' => $sent > 0 && empty($errors),
        'message' => $sent > 0
            ? 'Link pratica reinviato a ' . $sent . ' destinatari.'
            : 'Nessun link inviato.',
        'sent' => $sent,
        'recipients' => $usedRecipients,
        'errors' => $errors,
        'token_last4' => substr($token, -4),
    ];
}

function iscrizioniPrimeNotifyMailFailure(array $cfg, string $message): void
{
    $alertEmail = strtolower(trim((string)($cfg['mailFailureAlertEmail'] ?? '')));
    if ($alertEmail === '' || !filter_var($alertEmail, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    require_once __DIR__ . '/send-mail.php';

    @sendMailCustom($alertEmail, 'Amministratore GestOre', 'Errore invio mail iscrizioni prime', nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')));
}

function iscrizioniPrimeSendSubmissionConfirmation(array $pratica): array
{
    require_once __DIR__ . '/send-mail.php';

    $cfg = iscrizioniPrimeMailConfig();
    if (empty($cfg['enabled'])) {
        return ['ok' => false, 'message' => 'Invio mail iscrizioni non abilitato in GestOre.json.'];
    }
    if (empty($cfg['accounts'])) {
        return ['ok' => false, 'message' => 'Nessun account SMTP iscrizioni configurato.'];
    }

    $recipients = iscrizioniPrimeMailRecipientsForPratica($pratica);
    $schoolEmail = strtolower(trim((string)($cfg['replyToEmail'] ?? '')));
    if ($schoolEmail !== '' && filter_var($schoolEmail, FILTER_VALIDATE_EMAIL) && !in_array($schoolEmail, $recipients, true)) {
        $recipients[] = $schoolEmail;
    }
    if (empty($recipients)) {
        return ['ok' => false, 'message' => 'Nessun destinatario valido per la mail di conferma.'];
    }

    $account = iscrizioniPrimePickMailAccount($cfg, iscrizioniPrimeMailAccountCounts());
    if ($account === null) {
        $message = 'Limite giornaliero degli account iscrizioni raggiunto: ricevuta finale non inviata per pratica ID ' . intval($pratica['id'] ?? 0) . '.';
        iscrizioniPrimeNotifyMailFailure($cfg, $message);
        return ['ok' => false, 'message' => $message];
    }

    $body = iscrizioniPrimeSubmissionConfirmationBody($pratica);
    $errors = [];

    foreach ($recipients as $recipient) {
        $actualRecipient = $recipient;
        $recipientName = $recipient;
        $actualBody = $body;

        $isSchoolRecipient = $schoolEmail !== '' && strtolower($recipient) === $schoolEmail;
        if (!empty($cfg['testMode']) && !$isSchoolRecipient) {
            $actualRecipient = $account['email'];
            $recipientName = 'Test iscrizioni';
            $actualBody = iscrizioniPrimeMailTestBanner($recipient) . $body;
        }

        $confirmationSubject = trim((string)$cfg['confirmationSubject']);
        if (stripos($confirmationSubject, 'classi') === false) {
            $confirmationSubject .= iscrizioniPrimeTipoIscrizioneFromPratica($pratica) === 'terze' ? ' - classi terze' : ' - classi prime';
        }

        $ok = sendMailCustom($actualRecipient, $recipientName, $confirmationSubject, $actualBody, [
            'from_email' => iscrizioniPrimePublicFromEmail($cfg, $account),
            'from_name' => $cfg['fromName'],
            'reply_to_email' => $cfg['replyToEmail'] !== '' ? $cfg['replyToEmail'] : $account['email'],
            'reply_to_name' => $cfg['replyToName'],
            'sender_email' => $account['email'],
            'sender_name' => $cfg['fromName'],
            'smtp_host' => $cfg['smtpHost'],
            'smtp_username' => $account['email'],
            'smtp_password' => $account['password'],
            'smtp_secure' => $cfg['SMTPSecure'],
            'smtp_port' => $cfg['Port'],
        ]);

        if (!$ok) {
            $errors[] = $recipient;
        }
    }

    if ($errors) {
        $message = 'Errore invio ricevuta finale iscrizioni per pratica ID ' . intval($pratica['id'] ?? 0) . '. Destinatari non raggiunti: ' . implode(', ', $errors) . '.';
        iscrizioniPrimeNotifyMailFailure($cfg, $message);
        return ['ok' => false, 'message' => $message];
    }

    return ['ok' => true, 'message' => 'Mail di conferma inviata.'];
}

function iscrizioniPrimeSendMailBatch(bool $dryRun = false, string $tipoIscrizione = 'prime'): array
{
    require_once __DIR__ . '/send-mail.php';
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);

    $cfg = iscrizioniPrimeMailConfig();
    if (empty($cfg['enabled'])) {
        return ['ok' => false, 'message' => 'Invio mail iscrizioni non abilitato in GestOre.json.'];
    }
    if (empty($cfg['accounts'])) {
        return ['ok' => false, 'message' => 'Nessun account SMTP iscrizioni configurato.'];
    }

    $batchSize = intval($cfg['batchSize']);
    $pendingBefore = iscrizioniPrimeMailPendingRecipientCount($tipoIscrizione);
    info('[iscrizioni] avvio invio lotto tipo=' . $tipoIscrizione . ' dryRun=' . intval($dryRun) . ' pending=' . intval($pendingBefore) . ' batchSize=' . intval($batchSize));
    if ($pendingBefore <= 0) {
        return [
            'ok' => true,
            'message' => 'Non ci sono mail da inviare: tutte le mail risultano gia inviate oppure non ci sono pratiche esterne con email.',
            'sent' => 0,
            'skipped' => 0,
            'remaining' => 0,
            'last_batch' => true,
            'errors' => [],
        ];
    }

    $pratiche = dbGetAll("
        SELECT *
        FROM iscrizioni_prime_pratiche p
        WHERE p.stato IN ('importata', 'bozza', 'da_integrare')
          AND p.tipo_iscrizione = " . dbQ($tipoIscrizione) . "
          AND " . iscrizioniPrimeEffectiveExternalCondition('p') . "
          AND (p.email_genitore_1 IS NOT NULL OR p.email_genitore_2 IS NOT NULL)
          AND (
            (
                p.email_genitore_1 IS NOT NULL
                AND TRIM(p.email_genitore_1) <> ''
                AND NOT EXISTS (
                    SELECT 1
                    FROM iscrizioni_prime_mail_log l
                    WHERE l.pratica_id = p.id
                      AND LOWER(TRIM(l.recipient_email)) = LOWER(TRIM(p.email_genitore_1))
                      AND l.stato IN ('inviata','bounce')
                      AND l.test_mode = 0
                    LIMIT 1
                )
            )
            OR
            (
                p.email_genitore_2 IS NOT NULL
                AND TRIM(p.email_genitore_2) <> ''
                AND LOWER(TRIM(p.email_genitore_2)) <> LOWER(TRIM(COALESCE(p.email_genitore_1, '')))
                AND NOT EXISTS (
                    SELECT 1
                    FROM iscrizioni_prime_mail_log l
                    WHERE l.pratica_id = p.id
                      AND LOWER(TRIM(l.recipient_email)) = LOWER(TRIM(p.email_genitore_2))
                      AND l.stato IN ('inviata','bounce')
                      AND l.test_mode = 0
                    LIMIT 1
                )
            )
          )
        ORDER BY p.cognome ASC, p.nome ASC
        LIMIT " . intval($batchSize * 2) . "
    ");
    info('[iscrizioni] pratiche candidate lotto tipo=' . $tipoIscrizione . ' count=' . count($pratiche));

    $counts = iscrizioniPrimeMailAccountCounts();
    $sent = 0;
    $errors = [];
    $skipped = 0;

    foreach ($pratiche as $pratica) {
        if ($sent >= $batchSize) {
            break;
        }

        $recipients = iscrizioniPrimeMailRecipientsForPratica($pratica);
        if (empty($recipients)) {
            $skipped++;
            info('[iscrizioni] pratica saltata id=' . intval($pratica['id'] ?? 0) . ' motivo=nessun destinatario valido');
            continue;
        }

        $alreadySentAll = true;
        foreach ($recipients as $recipient) {
            $already = dbGetValue("
                SELECT COUNT(*)
                FROM iscrizioni_prime_mail_log
                WHERE pratica_id = " . intval($pratica['id']) . "
                  AND LOWER(TRIM(recipient_email)) = " . dbQ($recipient) . "
                  AND stato IN ('inviata','bounce')
                  AND test_mode = 0
            ");
            if (intval($already) === 0) {
                $alreadySentAll = false;
                break;
            }
        }
        if ($alreadySentAll) {
            $skipped++;
            info('[iscrizioni] pratica saltata id=' . intval($pratica['id'] ?? 0) . ' motivo=destinatari gia inviati');
            continue;
        }

        $token = '';
        $body = '';

        if (!$dryRun) {
            $token = iscrizioniPrimeSetToken((int)$pratica['id']);
            $link = ($GLOBALS['__http_base_link'] ?? '') . '/iscrizioni/conferma.php?t=' . rawurlencode($token);
        }

        foreach ($recipients as $recipient) {
            if ($sent >= $batchSize) {
                break 2;
            }

            $already = dbGetValue("
                SELECT COUNT(*)
                FROM iscrizioni_prime_mail_log
                WHERE pratica_id = " . intval($pratica['id']) . "
                  AND LOWER(TRIM(recipient_email)) = " . dbQ($recipient) . "
                  AND stato IN ('inviata','bounce')
                  AND test_mode = 0
            ");
            if (intval($already) > 0) {
                continue;
            }

            $account = iscrizioniPrimePickMailAccount($cfg, $counts);
            if ($account === null) {
                $remaining = $dryRun ? $pendingBefore : iscrizioniPrimeMailPendingRecipientCount($tipoIscrizione);
                warning('[iscrizioni] limite giornaliero account raggiunto tipo=' . $tipoIscrizione . ' sent=' . intval($sent) . ' skipped=' . intval($skipped) . ' remaining=' . intval($remaining));
                return [
                    'ok' => empty($errors),
                    'message' => 'Limite giornaliero account raggiunto.',
                    'sent' => $sent,
                    'skipped' => $skipped,
                    'remaining' => $remaining,
                    'last_batch' => false,
                    'errors' => $errors,
                ];
            }

            if ($dryRun) {
                $sent++;
                $counts[$account['email']] = intval($counts[$account['email']] ?? 0) + 1;
                continue;
            }

            $actualRecipient = $recipient;
            $recipientName = $recipient;
            $originalRecipientForBody = '';

            if (!empty($cfg['testMode'])) {
                $actualRecipient = $account['email'];
                $recipientName = 'Test iscrizioni';
                $originalRecipientForBody = $recipient;
            }

            $ok = iscrizioniPrimeSendMailFromGmailDraft($cfg, $account, $pratica, $actualRecipient, $link, $originalRecipientForBody, $tipoIscrizione);
            $dispatchResult = $GLOBALS['__sendMailLastDispatchResult'] ?? [];

            dbExec("
                INSERT INTO iscrizioni_prime_mail_log
                (pratica_id, recipient_email, account_email, token_last4, stato, test_mode, transport, gmail_message_id, errore, sent_at, created_at)
                VALUES (
                    " . intval($pratica['id']) . ",
                    " . dbQ($recipient) . ",
                    " . dbQ($account['email']) . ",
                    " . dbQ(substr($token, -4)) . ",
                    " . dbQ($ok ? 'inviata' : 'errore') . ",
                    " . (!empty($cfg['testMode']) ? '1' : '0') . ",
                    " . dbQ((string)($dispatchResult['transport'] ?? '')) . ",
                    " . dbQ((string)($dispatchResult['gmail_message_id'] ?? '')) . ",
                    " . dbQ($ok ? null : ((string)($dispatchResult['error'] ?? '') !== '' ? (string)$dispatchResult['error'] : 'sendMailCustom ha restituito false')) . ",
                    " . ($ok ? 'NOW()' : 'NULL') . ",
                    NOW()
                )
            ");

            if ($ok) {
                $sent++;
                $counts[$account['email']] = intval($counts[$account['email']] ?? 0) + 1;
            } else {
                $errors[] = $recipient;
            }
        }
    }

    $remaining = $dryRun ? $pendingBefore : iscrizioniPrimeMailPendingRecipientCount($tipoIscrizione);
    info('[iscrizioni] fine invio lotto tipo=' . $tipoIscrizione . ' dryRun=' . intval($dryRun) . ' sent=' . intval($sent) . ' skipped=' . intval($skipped) . ' remaining=' . intval($remaining) . ' errors=' . count($errors));
    if (!empty($errors)) {
        $message = (empty($cfg['testMode']) ? '' : 'Modalita test attiva: mail inviate agli account mittenti. ') . 'Invio completato con errori';
    } elseif ($sent <= 0) {
        $message = 'Non ci sono mail da inviare in questo lotto.';
    } elseif (!$dryRun && $remaining <= 0) {
        $message = (empty($cfg['testMode']) ? '' : 'Modalita test attiva: mail inviate agli account mittenti. ') . 'Ultimo lotto completato: non restano altre mail da inviare.';
    } else {
        $message = (empty($cfg['testMode']) ? '' : 'Modalita test attiva: mail inviate agli account mittenti. ') . 'Lotto completato. Restano ' . intval($remaining) . ' mail da inviare.';
    }

    return [
        'ok' => empty($errors),
        'message' => $message,
        'sent' => $sent,
        'skipped' => $skipped,
        'remaining' => $remaining,
        'last_batch' => !$dryRun && $remaining <= 0,
        'errors' => $errors,
    ];
}

function iscrizioniPrimeResendUpdatedLinkBatch(bool $dryRun = false, string $tipoIscrizione = 'prime'): array
{
    require_once __DIR__ . '/send-mail.php';
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);

    $cfg = iscrizioniPrimeMailConfig();
    if (empty($cfg['enabled'])) {
        return ['ok' => false, 'message' => 'Invio mail iscrizioni non abilitato in GestOre.json.'];
    }
    if (empty($cfg['accounts'])) {
        return ['ok' => false, 'message' => 'Nessun account SMTP iscrizioni configurato.'];
    }

    $batchSize = intval($cfg['batchSize']);
    $pendingBefore = iscrizioniPrimeMailStaleLinkRecipientCount($tipoIscrizione);
    info('[iscrizioni] avvio reinvio link aggiornato tipo=' . $tipoIscrizione . ' dryRun=' . intval($dryRun) . ' pending=' . intval($pendingBefore) . ' batchSize=' . intval($batchSize));
    if ($pendingBefore <= 0) {
        return [
            'ok' => true,
            'message' => 'Non ci sono link da reinviare: i link inviati risultano gia allineati ai token attuali.',
            'sent' => 0,
            'skipped' => 0,
            'remaining' => 0,
            'last_batch' => true,
            'errors' => [],
        ];
    }

    $pratiche = dbGetAll("
        SELECT *
        FROM iscrizioni_prime_pratiche p
        WHERE p.stato IN ('importata', 'bozza', 'da_integrare')
          AND p.tipo_iscrizione = " . dbQ($tipoIscrizione) . "
          AND " . iscrizioniPrimeEffectiveExternalCondition('p') . "
          AND (p.email_genitore_1 IS NOT NULL OR p.email_genitore_2 IS NOT NULL)
          AND p.token_last4 IS NOT NULL
          AND TRIM(p.token_last4) <> ''
          AND (
            (
                p.email_genitore_1 IS NOT NULL
                AND TRIM(p.email_genitore_1) <> ''
                AND EXISTS (
                    SELECT 1
                    FROM iscrizioni_prime_mail_log l
                    WHERE l.pratica_id = p.id
                      AND LOWER(TRIM(l.recipient_email)) = LOWER(TRIM(p.email_genitore_1))
                      AND l.stato = 'inviata'
                      AND l.test_mode = 0
                      AND COALESCE(l.token_last4, '') <> COALESCE(p.token_last4, '')
                    LIMIT 1
                )
            )
            OR
            (
                p.email_genitore_2 IS NOT NULL
                AND TRIM(p.email_genitore_2) <> ''
                AND LOWER(TRIM(p.email_genitore_2)) <> LOWER(TRIM(COALESCE(p.email_genitore_1, '')))
                AND EXISTS (
                    SELECT 1
                    FROM iscrizioni_prime_mail_log l
                    WHERE l.pratica_id = p.id
                      AND LOWER(TRIM(l.recipient_email)) = LOWER(TRIM(p.email_genitore_2))
                      AND l.stato = 'inviata'
                      AND l.test_mode = 0
                      AND COALESCE(l.token_last4, '') <> COALESCE(p.token_last4, '')
                    LIMIT 1
                )
            )
          )
        ORDER BY p.cognome ASC, p.nome ASC
        LIMIT " . intval($batchSize * 2) . "
    ") ?: [];

    $counts = iscrizioniPrimeMailAccountCounts();
    $sent = 0;
    $errors = [];
    $skipped = 0;

    foreach ($pratiche as $pratica) {
        if ($sent >= $batchSize) {
            break;
        }

        $recipients = iscrizioniPrimeMailRecipientsForPratica($pratica);
        $staleRecipients = [];
        foreach ($recipients as $recipient) {
            $stale = dbGetValue("
                SELECT COUNT(*)
                FROM iscrizioni_prime_mail_log
                WHERE pratica_id = " . intval($pratica['id']) . "
                  AND LOWER(TRIM(recipient_email)) = " . dbQ($recipient) . "
                  AND stato = 'inviata'
                  AND test_mode = 0
                  AND COALESCE(token_last4, '') <> " . dbQ((string)($pratica['token_last4'] ?? '')) . "
            ");
            if (intval($stale) > 0) {
                $staleRecipients[] = $recipient;
            }
        }

        if (!$staleRecipients) {
            $skipped++;
            continue;
        }

        $token = '';
        $link = '';
        if (!$dryRun) {
            $token = iscrizioniPrimeSetToken((int)$pratica['id']);
            $link = ($GLOBALS['__http_base_link'] ?? '') . '/iscrizioni/conferma.php?t=' . rawurlencode($token);
        }

        foreach ($staleRecipients as $recipient) {
            if ($sent >= $batchSize) {
                break 2;
            }

            $account = iscrizioniPrimePickMailAccount($cfg, $counts);
            if ($account === null) {
                $remaining = $dryRun ? $pendingBefore : iscrizioniPrimeMailStaleLinkRecipientCount($tipoIscrizione);
                warning('[iscrizioni] limite giornaliero account raggiunto reinvio link tipo=' . $tipoIscrizione . ' sent=' . intval($sent) . ' remaining=' . intval($remaining));
                return [
                    'ok' => empty($errors),
                    'message' => 'Limite giornaliero account raggiunto.',
                    'sent' => $sent,
                    'skipped' => $skipped,
                    'remaining' => $remaining,
                    'last_batch' => false,
                    'errors' => $errors,
                ];
            }

            if ($dryRun) {
                $sent++;
                $counts[$account['email']] = intval($counts[$account['email']] ?? 0) + 1;
                continue;
            }

            $actualRecipient = $recipient;
            $recipientName = $recipient;
            $originalRecipientForBody = '';
            if (!empty($cfg['testMode'])) {
                $actualRecipient = $account['email'];
                $recipientName = 'Test iscrizioni';
                $originalRecipientForBody = $recipient;
            }

            $ok = iscrizioniPrimeSendMailFromGmailDraft($cfg, $account, $pratica, $actualRecipient, $link, $originalRecipientForBody, $tipoIscrizione);
            $dispatchResult = $GLOBALS['__sendMailLastDispatchResult'] ?? [];

            dbExec("
                INSERT INTO iscrizioni_prime_mail_log
                (pratica_id, recipient_email, account_email, token_last4, stato, test_mode, transport, gmail_message_id, errore, sent_at, created_at)
                VALUES (
                    " . intval($pratica['id']) . ",
                    " . dbQ($recipient) . ",
                    " . dbQ($account['email']) . ",
                    " . dbQ(substr($token, -4)) . ",
                    " . dbQ($ok ? 'inviata' : 'errore') . ",
                    " . (!empty($cfg['testMode']) ? '1' : '0') . ",
                    " . dbQ((string)($dispatchResult['transport'] ?? '')) . ",
                    " . dbQ((string)($dispatchResult['gmail_message_id'] ?? '')) . ",
                    " . dbQ($ok ? null : ((string)($dispatchResult['error'] ?? '') !== '' ? (string)$dispatchResult['error'] : 'sendMailCustom ha restituito false')) . ",
                    " . ($ok ? 'NOW()' : 'NULL') . ",
                    NOW()
                )
            ");

            if ($ok) {
                $sent++;
                $counts[$account['email']] = intval($counts[$account['email']] ?? 0) + 1;
            } else {
                $errors[] = $recipient;
            }
        }
    }

    $remaining = $dryRun ? $pendingBefore : iscrizioniPrimeMailStaleLinkRecipientCount($tipoIscrizione);
    info('[iscrizioni] fine reinvio link aggiornato tipo=' . $tipoIscrizione . ' dryRun=' . intval($dryRun) . ' sent=' . intval($sent) . ' skipped=' . intval($skipped) . ' remaining=' . intval($remaining) . ' errors=' . count($errors));
    if (!empty($errors)) {
        $message = (empty($cfg['testMode']) ? '' : 'Modalita test attiva: mail inviate agli account mittenti. ') . 'Reinvio link completato con errori.';
    } elseif ($sent <= 0) {
        $message = 'Non ci sono link da reinviare in questo lotto.';
    } elseif (!$dryRun && $remaining <= 0) {
        $message = (empty($cfg['testMode']) ? '' : 'Modalita test attiva: mail inviate agli account mittenti. ') . 'Ultimo lotto reinvio link completato: tutti i link risultano riallineati.';
    } else {
        $message = (empty($cfg['testMode']) ? '' : 'Modalita test attiva: mail inviate agli account mittenti. ') . 'Lotto reinvio link completato. Restano ' . intval($remaining) . ' link da reinviare.';
    }

    return [
        'ok' => empty($errors),
        'message' => $message,
        'sent' => $sent,
        'skipped' => $skipped,
        'remaining' => $remaining,
        'last_batch' => !$dryRun && $remaining <= 0,
        'errors' => $errors,
    ];
}

function iscrizioniPrimeMailLogClassifyBounce(string $subject, string $snippet, string $body): array
{
    $text = strtolower($subject . "\n" . $snippet . "\n" . $body);

    $limitPatterns = [
        'daily user sending limit exceeded',
        'user-rate limit exceeded',
        'rate limit exceeded',
        'too many messages',
        'you have reached a limit for sending mail',
        '550 5.4.5',
        '550-5.4.5',
        'mail sending limit',
        'limite di invio',
        'hai raggiunto il limite',
    ];
    foreach ($limitPatterns as $pattern) {
        if (strpos($text, $pattern) !== false) {
            return ['type' => 'quota_limit', 'reason' => 'Possibile superamento del limite giornaliero di invio'];
        }
    }

    $mailboxPatterns = [
        'mailbox full',
        'over quota',
        'quota exceeded',
        'mailbox is full',
        'casella piena',
        'spazio esaurito',
    ];
    foreach ($mailboxPatterns as $pattern) {
        if (strpos($text, $pattern) !== false) {
            return ['type' => 'mailbox_full', 'reason' => 'Casella destinatario piena'];
        }
    }

    $invalidPatterns = [
        'address not found',
        'user unknown',
        'no such user',
        'recipient address rejected',
        'invalid recipient',
        'does not exist',
        'indirizzo non trovato',
        'utente sconosciuto',
    ];
    foreach ($invalidPatterns as $pattern) {
        if (strpos($text, $pattern) !== false) {
            return ['type' => 'invalid_recipient', 'reason' => 'Indirizzo destinatario errato o inesistente'];
        }
    }

    return ['type' => 'other_bounce', 'reason' => 'Mancata consegna non classificata automaticamente'];
}

function iscrizioniPrimeMailGmailApiRequestAs(string $accountEmail, string $method, string $url, $body = null): array
{
    require_once __DIR__ . '/send-mail.php';

    return sendMailGmailApiRequestRaw($accountEmail, 'https://www.googleapis.com/auth/gmail.readonly', $method, $url, $body);
}

function iscrizioniPrimeMailGmailHeader(array $message, string $name): string
{
    foreach (($message['payload']['headers'] ?? []) as $header) {
        if (strcasecmp((string)($header['name'] ?? ''), $name) === 0) {
            return (string)($header['value'] ?? '');
        }
    }
    return '';
}

function iscrizioniPrimeMailGmailDecode($data): string
{
    $data = strtr((string)$data, '-_', '+/');
    return (string)base64_decode($data);
}

function iscrizioniPrimeMailGmailExtractText(array $payload): string
{
    $chunks = [];
    if (!empty($payload['body']['data'])) {
        $chunks[] = iscrizioniPrimeMailGmailDecode($payload['body']['data']);
    }
    foreach (($payload['parts'] ?? []) as $part) {
        $chunks[] = iscrizioniPrimeMailGmailExtractText($part);
    }
    return trim(implode("\n", array_filter($chunks)));
}

function iscrizioniPrimeMailLogFindByBounceText(string $text, string $accountEmail): ?array
{
    iscrizioniPrimeEnsureSchema();

    $accountEmail = strtolower(trim($accountEmail));
    foreach (dbGetAll("
        SELECT
            l.*,
            p.cognome,
            p.nome,
            p.tipo_iscrizione,
            p.codice_fiscale
        FROM iscrizioni_prime_mail_log l
        INNER JOIN iscrizioni_prime_pratiche p ON p.id = l.pratica_id
        WHERE l.account_email = " . dbQ($accountEmail) . "
          AND l.stato IN ('inviata', 'bounce')
          AND l.test_mode = 0
          AND l.sent_at >= DATE_SUB(NOW(), INTERVAL 21 DAY)
        ORDER BY l.sent_at DESC
        LIMIT 1000
    ") as $row) {
        $email = strtolower(trim((string)($row['recipient_email'] ?? '')));
        if ($email !== '' && stripos($text, $email) !== false) {
            return $row;
        }
    }

    return null;
}

function iscrizioniPrimeMailLogMarkBounce(int $logId, string $type, string $reason, string $gmailMessageId, string $snippet): void
{
    iscrizioniPrimeEnsureSchema();

    dbExec("
        UPDATE iscrizioni_prime_mail_log
        SET stato = 'bounce',
            bounce_type = " . dbQ($type) . ",
            bounce_reason = " . dbQ($reason) . ",
            bounce_message_id = " . dbQ($gmailMessageId) . ",
            bounce_snippet = " . dbQ($snippet) . ",
            bounced_at = COALESCE(bounced_at, NOW()),
            checked_at = NOW()
        WHERE id = " . dbI($logId) . "
        LIMIT 1
    ");
}

function iscrizioniPrimeMailLogMarkChecked(array $logIds): void
{
    iscrizioniPrimeEnsureSchema();
    $ids = array_values(array_filter(array_map('intval', $logIds), static fn($id) => $id > 0));
    if (!$ids) {
        return;
    }

    dbExec("
        UPDATE iscrizioni_prime_mail_log
        SET checked_at = NOW()
        WHERE id IN (" . implode(',', $ids) . ")
    ");
}

function iscrizioniPrimeMailLogMarkUnmatchedBounce(string $accountEmail, string $gmailMessageId, string $type, string $reason, string $subject, string $snippet): void
{
    iscrizioniPrimeEnsureSchema();

    dbExec("
        INSERT INTO iscrizioni_prime_mail_bounce_unmatched
            (account_email, gmail_message_id, bounce_type, bounce_reason, subject, snippet, checked_at, created_at)
        VALUES
            (" . dbQ(strtolower(trim($accountEmail))) . ",
             " . dbQ($gmailMessageId) . ",
             " . dbQ($type) . ",
             " . dbQ($reason) . ",
             " . dbQ($subject) . ",
             " . dbQ($snippet) . ",
             NOW(),
             NOW())
        ON DUPLICATE KEY UPDATE
            bounce_type = VALUES(bounce_type),
            bounce_reason = VALUES(bounce_reason),
            subject = VALUES(subject),
            snippet = VALUES(snippet),
            checked_at = NOW()
    ");
}

function iscrizioniPrimeMailBounceAccounts(): array
{
    $cfg = iscrizioniPrimeMailConfig();
    $accounts = [];
    foreach (($cfg['accounts'] ?? []) as $account) {
        $email = strtolower(trim((string)($account['email'] ?? '')));
        if ($email !== '') {
            $accounts[] = $email;
        }
    }
    return array_values(array_unique($accounts));
}

function iscrizioniPrimeMailHeaderEmails(string $headerValue): array
{
    preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $headerValue, $matches);
    return array_values(array_unique(array_map(static fn($email) => strtolower(trim($email)), $matches[0] ?? [])));
}

function iscrizioniPrimeMailExtractConfirmationTokens(string $text): array
{
    preg_match_all('/conferma\.php\?[^\\s"\'<>]*?(?:^|[?&])t=([A-Za-z0-9_\-]+)/i', $text, $matches);
    if (empty($matches[1])) {
        preg_match_all('/[?&]t=([A-Za-z0-9_\-]{20,})/i', $text, $matches);
    }
    return array_values(array_unique(array_map('trim', $matches[1] ?? [])));
}

function iscrizioniPrimeMailHasCurrentTokenSent(int $practiceId, string $recipient, string $tokenLast4): bool
{
    if ($practiceId <= 0 || trim($recipient) === '' || trim($tokenLast4) === '') {
        return false;
    }

    return intval(dbGetValue("
        SELECT COUNT(*)
        FROM iscrizioni_prime_mail_log
        WHERE pratica_id = " . dbI($practiceId) . "
          AND LOWER(TRIM(recipient_email)) = " . dbQ(strtolower(trim($recipient))) . "
          AND stato = 'inviata'
          AND test_mode = 0
          AND token_last4 = " . dbQ($tokenLast4) . "
    ")) > 0;
}

function iscrizioniPrimeMailCurrentTokenMatchesSentLink(array $pratica, string $sentToken): bool
{
    $sentToken = trim($sentToken);
    if ($sentToken === '') {
        return false;
    }
    return hash('sha256', $sentToken) === trim((string)($pratica['token_hash'] ?? ''));
}

function iscrizioniPrimeFindPracticeForSentLinkRecipient(string $recipient, string $tipoIscrizione): ?array
{
    $recipient = strtolower(trim($recipient));
    if ($recipient === '') {
        return null;
    }
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);

    return dbGetFirst("
        SELECT *
        FROM iscrizioni_prime_pratiche p
        WHERE p.tipo_iscrizione = " . dbQ($tipoIscrizione) . "
          AND p.stato IN ('importata', 'bozza', 'da_integrare')
          AND " . iscrizioniPrimeEffectiveExternalCondition('p') . "
          AND (
                LOWER(TRIM(p.email_genitore_1)) = " . dbQ($recipient) . "
             OR LOWER(TRIM(p.email_genitore_2)) = " . dbQ($recipient) . "
          )
        ORDER BY p.id DESC
        LIMIT 1
    ") ?: null;
}

function iscrizioniPrimeCollectWrongSentLinksFromGmail(string $tipoIscrizione = 'prime', int $maxPerAccount = 100): array
{
    iscrizioniPrimeEnsureSchema();
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);
    $cfg = iscrizioniPrimeMailConfig();
    $subject = iscrizioniPrimeGmailSentSubjectFromDraftSubject(iscrizioniPrimeGmailDraftSubject($cfg, $tipoIscrizione));
    $accounts = iscrizioniPrimeMailBounceAccounts();
    $wrong = [];
    $seen = [];
    $checked = 0;
    $warnings = [];
    $searchSubject = $tipoIscrizione === 'terze'
        ? 'Regolarizzazione domanda di iscrizione classe terza'
        : 'Regolarizzazione domanda di iscrizione classe prima';

    foreach ($accounts as $accountEmail) {
        $query = 'in:sent newer_than:45d subject:"' . $searchSubject . '"';
        try {
            $list = iscrizioniPrimeMailGmailApiRequestAs(
                $accountEmail,
                'GET',
                'https://gmail.googleapis.com/gmail/v1/users/' . rawurlencode($accountEmail) . '/messages?q=' . rawurlencode($query) . '&maxResults=' . max(1, min(500, $maxPerAccount))
            );
        } catch (Throwable $e) {
            $warnings[] = 'Account ' . $accountEmail . ': ' . $e->getMessage();
            warning('[iscrizioni] controllo link inviati saltato per account=' . $accountEmail . ' errore=' . $e->getMessage());
            continue;
        }

        foreach (($list['messages'] ?? []) as $messageRef) {
            $gmailMessageId = trim((string)($messageRef['id'] ?? ''));
            if ($gmailMessageId === '') {
                continue;
            }
            try {
                $message = iscrizioniPrimeMailGmailApiRequestAs(
                    $accountEmail,
                    'GET',
                    'https://gmail.googleapis.com/gmail/v1/users/' . rawurlencode($accountEmail) . '/messages/' . rawurlencode($gmailMessageId) . '?format=full'
                );
            } catch (Throwable $e) {
                $warnings[] = 'Messaggio ' . $gmailMessageId . ' su ' . $accountEmail . ': ' . $e->getMessage();
                warning('[iscrizioni] controllo link inviati messaggio saltato account=' . $accountEmail . ' gmailMessageId=' . $gmailMessageId . ' errore=' . $e->getMessage());
                continue;
            }
            $checked++;
            $messageSubject = trim(iscrizioniPrimeMailGmailHeader($message, 'Subject'));
            if ($subject !== '' && stripos($messageSubject, $subject) === false && stripos($messageSubject, $searchSubject) === false) {
                continue;
            }
            $body = iscrizioniPrimeMailGmailExtractText($message['payload'] ?? []);
            $tokens = iscrizioniPrimeMailExtractConfirmationTokens($body . "\n" . (string)($message['snippet'] ?? ''));
            if (!$tokens) {
                continue;
            }

            $recipients = array_merge(
                iscrizioniPrimeMailHeaderEmails(iscrizioniPrimeMailGmailHeader($message, 'To')),
                iscrizioniPrimeMailHeaderEmails(iscrizioniPrimeMailGmailHeader($message, 'Cc')),
                iscrizioniPrimeMailHeaderEmails(iscrizioniPrimeMailGmailHeader($message, 'Bcc'))
            );
            $recipients = array_values(array_unique($recipients));
            foreach ($recipients as $recipient) {
                $pratica = iscrizioniPrimeFindPracticeForSentLinkRecipient($recipient, $tipoIscrizione);
                if (!$pratica) {
                    continue;
                }
                $tokenLast4 = trim((string)($pratica['token_last4'] ?? ''));
                if (iscrizioniPrimeMailHasCurrentTokenSent((int)$pratica['id'], $recipient, $tokenLast4)) {
                    continue;
                }
                $hasCurrentInMessage = false;
                foreach ($tokens as $sentToken) {
                    if (iscrizioniPrimeMailCurrentTokenMatchesSentLink($pratica, $sentToken)) {
                        $hasCurrentInMessage = true;
                        break;
                    }
                }
                if ($hasCurrentInMessage) {
                    continue;
                }

                $key = intval($pratica['id']) . '|' . $recipient;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $wrong[] = [
                    'account_email' => $accountEmail,
                    'gmail_message_id' => $gmailMessageId,
                    'recipient_email' => $recipient,
                    'pratica' => $pratica,
                ];
            }
        }
    }

    return ['checked' => $checked, 'wrong' => $wrong, 'warnings' => $warnings];
}

function iscrizioniPrimeResendCorrectLinkFromSentMailBatch(bool $dryRun = false, string $tipoIscrizione = 'prime'): array
{
    require_once __DIR__ . '/send-mail.php';
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);
    $cfg = iscrizioniPrimeMailConfig();
    $batchSize = intval($cfg['batchSize'] ?? 50);
    $scan = iscrizioniPrimeCollectWrongSentLinksFromGmail($tipoIscrizione, max(100, $batchSize * 4));
    $wrong = $scan['wrong'];
    $warnings = $scan['warnings'] ?? [];

    if (!$wrong) {
        return [
            'ok' => empty($warnings),
            'message' => empty($warnings)
                ? 'Controllo posta inviata completato: non risultano link da correggere.'
                : 'Controllo posta inviata completato con avvisi: non risultano link da correggere negli account controllati.',
            'sent' => 0,
            'skipped' => 0,
            'remaining' => 0,
            'last_batch' => true,
            'checked' => intval($scan['checked'] ?? 0),
            'warnings' => $warnings,
            'errors' => [],
        ];
    }

    $groups = [];
    foreach ($wrong as $item) {
        $pratica = $item['pratica'] ?? [];
        $practiceId = intval($pratica['id'] ?? 0);
        if ($practiceId <= 0) {
            continue;
        }
        if (!isset($groups[$practiceId])) {
            $groups[$practiceId] = [
                'pratica' => $pratica,
                'recipients' => iscrizioniPrimeMailRecipientsForPratica($pratica),
                'detected_recipients' => [],
            ];
        }
        $recipient = strtolower(trim((string)($item['recipient_email'] ?? '')));
        if ($recipient !== '') {
            $groups[$practiceId]['detected_recipients'][$recipient] = true;
        }
    }

    $counts = iscrizioniPrimeMailAccountCounts();
    $sent = 0;
    $skipped = 0;
    $errors = [];
    $details = [];
    $totalRecipients = 0;
    foreach ($groups as $group) {
        $totalRecipients += count($group['recipients'] ?? []);
    }

    foreach ($groups as $practiceId => $group) {
        $pratica = $group['pratica'];
        $recipients = $group['recipients'] ?? [];
        if (!$recipients) {
            $skipped++;
            continue;
        }
        $recipientCount = count($recipients);

        if ($sent > 0 && $sent + $recipientCount > $batchSize) {
            break;
        }

        if ($dryRun) {
            foreach ($recipients as $recipient) {
                $account = iscrizioniPrimePickMailAccount($cfg, $counts);
                if ($account === null) {
                    break 2;
                }
                $sent++;
                $counts[$account['email']] = intval($counts[$account['email']] ?? 0) + 1;
                $details[] = [
                    'pratica_id' => $practiceId,
                    'studente' => trim((string)($pratica['cognome'] ?? '') . ' ' . (string)($pratica['nome'] ?? '')),
                    'codice_fiscale' => (string)($pratica['codice_fiscale'] ?? ''),
                    'recipient_email' => $recipient,
                    'account_email' => (string)($account['email'] ?? ''),
                ];
            }
            continue;
        }

        $token = iscrizioniPrimeSetToken($practiceId);
        $link = ($GLOBALS['__http_base_link'] ?? '') . '/iscrizioni/conferma.php?t=' . rawurlencode($token);

        foreach ($recipients as $recipient) {
            $account = iscrizioniPrimePickMailAccount($cfg, $counts);
            if ($account === null) {
                break 2;
            }

            $ok = iscrizioniPrimeSendCorrectLinkMail($cfg, $account, $pratica, $recipient, $link, $tipoIscrizione);
            $dispatchResult = $GLOBALS['__sendMailLastDispatchResult'] ?? [];
            dbExec("
                INSERT INTO iscrizioni_prime_mail_log
                (pratica_id, recipient_email, account_email, token_last4, stato, test_mode, transport, gmail_message_id, errore, sent_at, created_at)
                VALUES (
                    " . dbI($practiceId) . ",
                    " . dbQ($recipient) . ",
                    " . dbQ($account['email']) . ",
                    " . dbQ(substr($token, -4)) . ",
                    " . dbQ($ok ? 'inviata' : 'errore') . ",
                    " . (!empty($cfg['testMode']) ? '1' : '0') . ",
                    " . dbQ((string)($dispatchResult['transport'] ?? '')) . ",
                    " . dbQ((string)($dispatchResult['gmail_message_id'] ?? '')) . ",
                    " . dbQ($ok ? null : ((string)($dispatchResult['error'] ?? '') !== '' ? (string)$dispatchResult['error'] : 'sendMailCustom ha restituito false')) . ",
                    " . ($ok ? 'NOW()' : 'NULL') . ",
                    NOW()
                )
            ");

            if ($ok) {
                $sent++;
                $counts[$account['email']] = intval($counts[$account['email']] ?? 0) + 1;
                $details[] = [
                    'pratica_id' => $practiceId,
                    'studente' => trim((string)($pratica['cognome'] ?? '') . ' ' . (string)($pratica['nome'] ?? '')),
                    'codice_fiscale' => (string)($pratica['codice_fiscale'] ?? ''),
                    'recipient_email' => $recipient,
                    'account_email' => (string)($account['email'] ?? ''),
                ];
            } else {
                $errors[] = $recipient;
            }
        }
    }

    $remaining = max(0, $totalRecipients - $sent - $skipped);
    return [
        'ok' => empty($errors) && empty($warnings),
        'message' => $dryRun
            ? (empty($warnings) ? 'Simulazione controllo posta inviata completata.' : 'Simulazione controllo posta inviata completata con avvisi.')
            : (empty($warnings)
                ? ($remaining <= 0 ? 'Correzione link completata.' : 'Lotto correzione link completato. Restano ' . intval($remaining) . ' link da correggere.')
                : ($remaining <= 0 ? 'Correzione link completata con avvisi.' : 'Lotto correzione link completato con avvisi. Restano ' . intval($remaining) . ' link da correggere.')),
        'sent' => $sent,
        'skipped' => $skipped,
        'remaining' => $remaining,
        'last_batch' => $remaining <= 0,
        'checked' => intval($scan['checked'] ?? 0),
        'details' => $details,
        'warnings' => $warnings,
        'errors' => $errors,
    ];
}

function iscrizioniPrimeMailBounceCheckAccount(string $accountEmail, int $maxResults = 30, string $tipoIscrizione = ''): array
{
    iscrizioniPrimeEnsureSchema();

    $accountEmail = strtolower(trim($accountEmail));
    $tipoIscrizione = $tipoIscrizione === '' ? '' : iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);
    $query = 'newer_than:21d (from:(mailer-daemon OR mailer-daemon@googlemail.com OR postmaster OR "Mail Delivery Subsystem") OR subject:("Delivery Status Notification" OR "Undelivered Mail Returned" OR "Mail delivery failed" OR "Message not delivered"))';
    $list = iscrizioniPrimeMailGmailApiRequestAs(
        $accountEmail,
        'GET',
        'https://gmail.googleapis.com/gmail/v1/users/' . rawurlencode($accountEmail) . '/messages?q=' . rawurlencode($query) . '&maxResults=' . max(1, min(100, $maxResults))
    );

    $checked = 0;
    $matched = 0;
    $unmatched = 0;
    $bounces = [];
    $checkedLogIds = [];

    foreach (($list['messages'] ?? []) as $messageRef) {
        $gmailMessageId = (string)($messageRef['id'] ?? '');
        if ($gmailMessageId === '') {
            continue;
        }

        $message = iscrizioniPrimeMailGmailApiRequestAs(
            $accountEmail,
            'GET',
            'https://gmail.googleapis.com/gmail/v1/users/' . rawurlencode($accountEmail) . '/messages/' . rawurlencode($gmailMessageId) . '?format=full'
        );

        $checked++;
        $subject = iscrizioniPrimeMailGmailHeader($message, 'Subject');
        $snippet = (string)($message['snippet'] ?? '');
        $body = iscrizioniPrimeMailGmailExtractText($message['payload'] ?? []);
        $searchText = $subject . "\n" . $snippet . "\n" . $body;
        $classification = iscrizioniPrimeMailLogClassifyBounce($subject, $snippet, $body);
        $logRow = iscrizioniPrimeMailLogFindByBounceText($searchText, $accountEmail);

        if ($logRow && ($tipoIscrizione === '' || (string)($logRow['tipo_iscrizione'] ?? '') === $tipoIscrizione)) {
            $matched++;
            $checkedLogIds[] = (int)$logRow['id'];
            iscrizioniPrimeMailLogMarkBounce((int)$logRow['id'], $classification['type'], $classification['reason'], $gmailMessageId, $snippet);
            $bounces[] = [
                'log_id' => (int)$logRow['id'],
                'pratica_id' => (int)$logRow['pratica_id'],
                'tipo_iscrizione' => (string)($logRow['tipo_iscrizione'] ?? ''),
                'student' => trim((string)($logRow['cognome'] ?? '') . ' ' . (string)($logRow['nome'] ?? '')),
                'codice_fiscale' => (string)($logRow['codice_fiscale'] ?? ''),
                'recipient_email' => (string)($logRow['recipient_email'] ?? ''),
                'account_email' => $accountEmail,
                'type' => $classification['type'],
                'reason' => $classification['reason'],
                'snippet' => $snippet,
            ];
        } else {
            $unmatched++;
            iscrizioniPrimeMailLogMarkUnmatchedBounce($accountEmail, $gmailMessageId, $classification['type'], $classification['reason'], $subject, $snippet);
            if ($tipoIscrizione === '') {
                $bounces[] = [
                    'log_id' => 0,
                    'pratica_id' => 0,
                    'tipo_iscrizione' => '',
                    'student' => '',
                    'codice_fiscale' => '',
                    'recipient_email' => '',
                    'account_email' => $accountEmail,
                    'type' => $classification['type'],
                    'reason' => $classification['reason'],
                    'snippet' => $snippet,
                ];
            }
        }
    }

    iscrizioniPrimeMailLogMarkChecked($checkedLogIds);

    return [
        'account' => $accountEmail,
        'checked' => $checked,
        'matched' => $matched,
        'unmatched' => $unmatched,
        'bounces' => $bounces,
    ];
}

function iscrizioniPrimeMailBounceSummary(int $maxResults = 30, string $tipoIscrizione = ''): array
{
    $summary = [
        'ok' => true,
        'accounts' => [],
        'totals' => [
            'checked' => 0,
            'matched' => 0,
            'unmatched' => 0,
            'quota_limit' => 0,
            'invalid_recipient' => 0,
            'mailbox_full' => 0,
            'other_bounce' => 0,
        ],
    ];

    foreach (iscrizioniPrimeMailBounceAccounts() as $accountEmail) {
        try {
            $result = iscrizioniPrimeMailBounceCheckAccount($accountEmail, $maxResults, $tipoIscrizione);
            $summary['accounts'][] = $result;
            $summary['totals']['checked'] += intval($result['checked'] ?? 0);
            $summary['totals']['matched'] += intval($result['matched'] ?? 0);
            $summary['totals']['unmatched'] += intval($result['unmatched'] ?? 0);
            foreach (($result['bounces'] ?? []) as $bounce) {
                $type = (string)($bounce['type'] ?? 'other_bounce');
                if (!array_key_exists($type, $summary['totals'])) {
                    $type = 'other_bounce';
                }
                $summary['totals'][$type]++;
            }
        } catch (Throwable $e) {
            $summary['ok'] = false;
            $summary['accounts'][] = [
                'account' => $accountEmail,
                'error' => $e->getMessage(),
            ];
        }
    }

    return $summary;
}

function iscrizioniPrimeMailBounceReportRows(string $tipoIscrizione = '', int $days = 30): array
{
    iscrizioniPrimeEnsureSchema();
    $tipoWhere = '';
    $tipoIscrizione = trim($tipoIscrizione);
    if ($tipoIscrizione !== '') {
        $tipoWhere = " AND p.tipo_iscrizione = " . dbQ(iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione));
    }
    $days = max(1, min(365, $days));

    return dbGetAll("
        SELECT
            l.id,
            p.tipo_iscrizione,
            p.cognome,
            p.nome,
            p.codice_fiscale,
            p.corso_studi,
            l.recipient_email,
            l.account_email,
            l.stato,
            l.bounce_type,
            l.bounce_reason,
            l.bounce_snippet,
            l.sent_at,
            l.bounced_at,
            l.checked_at
        FROM iscrizioni_prime_mail_log l
        INNER JOIN iscrizioni_prime_pratiche p ON p.id = l.pratica_id
        WHERE l.stato = 'bounce'
          AND l.test_mode = 0
          AND COALESCE(l.bounced_at, l.checked_at, l.sent_at, l.created_at) >= DATE_SUB(NOW(), INTERVAL " . intval($days) . " DAY)
          " . $tipoWhere . "
        ORDER BY COALESCE(l.bounced_at, l.checked_at, l.sent_at, l.created_at) DESC, p.cognome ASC, p.nome ASC
    ");
}

function iscrizioniPrimeMailBounceUnmatchedReportRows(int $days = 30): array
{
    iscrizioniPrimeEnsureSchema();
    $days = max(1, min(365, $days));

    return dbGetAll("
        SELECT
            account_email,
            gmail_message_id,
            bounce_type,
            bounce_reason,
            subject,
            snippet,
            checked_at,
            created_at
        FROM iscrizioni_prime_mail_bounce_unmatched
        WHERE checked_at >= DATE_SUB(NOW(), INTERVAL " . intval($days) . " DAY)
        ORDER BY checked_at DESC
    ");
}

function iscrizioniPrimeGetByToken(string $token): ?array
{
    $token = trim($token);
    if ($token === '') {
        return null;
    }

    if (preg_match('/^admin_preview:(\d+)$/', $token, $matches)) {
        require_once __DIR__ . '/checkSession.php';
        ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');
        return dbGetFirst("
            SELECT *
            FROM iscrizioni_prime_pratiche
            WHERE id = " . dbI($matches[1]) . "
            LIMIT 1
        ") ?: null;
    }

    $hash = hash('sha256', $token);

    return dbGetFirst("
        SELECT *
        FROM iscrizioni_prime_pratiche
        WHERE token_hash = " . dbQ($hash) . "
          AND (token_expires_at IS NULL OR token_expires_at >= NOW())
          AND stato <> 'annullata'
        LIMIT 1
    ");
}

function iscrizioniPrimeTrimValue($value): ?string
{
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function iscrizioniPrimeContactFieldLabels(): array
{
    return [
        'email_studente' => 'Email studente',
        'telefono_studente' => 'Telefono studente',
        'email_genitore_1' => 'Email responsabile 1',
        'telefono_genitore_1' => 'Telefono responsabile 1',
        'email_genitore_2' => 'Email responsabile 2',
        'telefono_genitore_2' => 'Telefono responsabile 2',
    ];
}

function iscrizioniPrimeContactCompareValue(string $field, $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    if (strpos($field, 'email') !== false) {
        return strtolower($value);
    }

    if (strpos($field, 'telefono') !== false) {
        return preg_replace('/[\s\-.\/()]+/', '', $value) ?: '';
    }

    return $value;
}

function iscrizioniPrimeRecordContactChanges(array $pratica, array $confirmed): void
{
    $labels = iscrizioniPrimeContactFieldLabels();
    $praticaId = intval($pratica['id'] ?? 0);
    if ($praticaId <= 0) {
        return;
    }

    foreach ($labels as $field => $label) {
        if (!array_key_exists($field, $confirmed)) {
            continue;
        }

        $oldValue = iscrizioniPrimeTrimValue($pratica[$field] ?? null);
        $newValue = iscrizioniPrimeTrimValue($confirmed[$field] ?? null);
        if (iscrizioniPrimeContactCompareValue($field, $oldValue) === iscrizioniPrimeContactCompareValue($field, $newValue)) {
            continue;
        }

        dbExec("
            INSERT INTO iscrizioni_contatti_variazioni
                (pratica_id, tipo_iscrizione, campo, etichetta, valore_precedente, valore_nuovo, stato, created_at)
            VALUES
                (
                    " . dbI($praticaId) . ",
                    " . dbQ(iscrizioniPrimeTipoIscrizioneFromPratica($pratica)) . ",
                    " . dbQ($field) . ",
                    " . dbQ($label) . ",
                    " . dbQ($oldValue) . ",
                    " . dbQ($newValue) . ",
                    'da_lavorare',
                    NOW()
                )
        ");
    }
}

function iscrizioniPrimeSaveDraftByToken(string $token, array $data): array
{
    $pratica = iscrizioniPrimeGetByToken($token);
    if (!$pratica) {
        return ['ok' => false, 'message' => 'Link non valido, scaduto o pratica non disponibile.'];
    }

    if (in_array((string)$pratica['stato'], ['verificata', 'annullata'], true)) {
        return ['ok' => false, 'message' => 'La pratica non puo essere modificata in questo stato.'];
    }

    $previousConfirmed = [];
    if (!empty($pratica['dati_confermati_json'])) {
        $decodedPrevious = json_decode((string)$pratica['dati_confermati_json'], true);
        if (is_array($decodedPrevious)) {
            $previousConfirmed = $decodedPrevious;
        }
    }
    $savedAt = date('c');
    if (iscrizioniPrimeIsReceivedBySecretaryState((string)($pratica['stato'] ?? '')) && !empty($previousConfirmed['saved_at'])) {
        $savedAt = (string)$previousConfirmed['saved_at'];
    }

    $carenzeMaterie = [];
    $carenzeAltroSelected = false;
    foreach ((array)($data['carenze_formative_materie'] ?? []) as $materia) {
        $materia = trim((string)$materia);
        if ($materia === '__ALTRO__') {
            $carenzeAltroSelected = true;
            continue;
        }
        if ($materia !== '' && !in_array($materia, $carenzeMaterie, true)) {
            $carenzeMaterie[] = $materia;
        }
    }
    $carenzeDichiarate = trim((string)($data['carenze_formative_dichiarate'] ?? ''));
    if (!in_array($carenzeDichiarate, ['si', 'no'], true)) {
        $carenzeDichiarate = '';
    }
    if ($carenzeDichiarate !== 'si') {
        $carenzeMaterie = [];
        $data['carenze_formative_altro'] = null;
    } elseif (!$carenzeAltroSelected) {
        $data['carenze_formative_altro'] = null;
    }
    $nullaOstaData = iscrizioniPrimeDate((string)($data['nulla_osta_data'] ?? ''));

    $confirmed = [
        'email_studente' => iscrizioniPrimeTrimValue($data['email_studente'] ?? null),
        'telefono_studente' => iscrizioniPrimeTrimValue($data['telefono_studente'] ?? null),
        'email_genitore_1' => iscrizioniPrimeTrimValue($data['email_genitore_1'] ?? null),
        'telefono_genitore_1' => iscrizioniPrimeTrimValue($data['telefono_genitore_1'] ?? null),
        'email_genitore_2' => iscrizioniPrimeTrimValue($data['email_genitore_2'] ?? null),
        'telefono_genitore_2' => iscrizioniPrimeTrimValue($data['telefono_genitore_2'] ?? null),
        'nulla_osta_richiesto' => !empty($data['nulla_osta_richiesto']) ? 1 : 0,
        'nulla_osta_data' => $nullaOstaData,
        'carenze_formative_dichiarate' => $carenzeDichiarate,
        'carenze_formative_materie' => $carenzeMaterie,
        'carenze_formative_altro' => iscrizioniPrimeTrimValue($data['carenze_formative_altro'] ?? null),
        'privacy_confermata' => !empty($data['privacy_confermata']) ? 1 : 0,
        'saved_at' => $savedAt,
    ];

    if ($confirmed['email_genitore_1'] === null && $confirmed['email_genitore_2'] === null) {
        return ['ok' => false, 'message' => 'Indicare almeno una email di un responsabile.'];
    }

    iscrizioniPrimeRecordContactChanges($pratica, $confirmed);

    $nextPracticeState = iscrizioniPrimeIsReceivedBySecretaryState((string)($pratica['stato'] ?? '')) ? 'inviata' : 'bozza';
    dbExec("
        UPDATE iscrizioni_prime_pratiche SET
            email_studente = " . dbQ($confirmed['email_studente']) . ",
            telefono_studente = " . dbQ($confirmed['telefono_studente']) . ",
            email_genitore_1 = " . dbQ($confirmed['email_genitore_1']) . ",
            telefono_genitore_1 = " . dbQ($confirmed['telefono_genitore_1']) . ",
            email_genitore_2 = " . dbQ($confirmed['email_genitore_2']) . ",
            telefono_genitore_2 = " . dbQ($confirmed['telefono_genitore_2']) . ",
            nulla_osta_richiesto = " . intval($confirmed['nulla_osta_richiesto']) . ",
            nulla_osta_data = " . dbQ($confirmed['nulla_osta_data']) . ",
            carenze_formative_dichiarate = " . dbQ($confirmed['carenze_formative_dichiarate']) . ",
            carenze_formative_materie = " . dbQ(json_encode($confirmed['carenze_formative_materie'], JSON_UNESCAPED_UNICODE)) . ",
            carenze_formative_altro = " . dbQ($confirmed['carenze_formative_altro']) . ",
            dati_confermati_json = " . dbQ(json_encode($confirmed, JSON_UNESCAPED_UNICODE)) . ",
            stato = " . dbQ($nextPracticeState) . ",
            updated_at = NOW()
        WHERE id = " . intval($pratica['id']) . "
        LIMIT 1
    ");

    iscrizioniPrimeEnsureDocumentRows((int)$pratica['id'], $pratica);

    return [
        'ok' => true,
        'message' => $nextPracticeState === 'inviata' ? 'Modifiche salvate. La pratica resta inviata alla segreteria.' : 'Bozza salvata.',
        'stato' => $nextPracticeState,
    ];
}

function iscrizioniPrimeRequiredDocumentTypes(array $pratica, array $confirmed = []): array
{
    if (iscrizioniPrimeTipoIscrizioneFromPratica($pratica) === 'terze') {
        $types = array_values(array_filter(
            array_keys(iscrizioniPrimeDocumentTypes($pratica)),
            fn($tipo) => !in_array($tipo, ['altro', 'attestazione_erogazione_liberale'], true)
        ));
        if (!hasSecondResponsibleForIscrizioniPrime($pratica, $confirmed)) {
            $types = array_values(array_filter($types, fn($tipo) => $tipo !== 'documento_cf_genitore_2'));
        }

        return $types;
    }

    $types = array_values(array_filter(
        array_keys(iscrizioniPrimeDocumentTypes($pratica)),
        fn($tipo) => !in_array($tipo, ['altro', 'attestazione_erogazione_liberale'], true)
    ));
    if (!hasSecondResponsibleForIscrizioniPrime($pratica, $confirmed)) {
        $types = array_values(array_filter($types, fn($tipo) => !in_array($tipo, ['documento_identita_genitore_2', 'codice_fiscale_genitore_2'], true)));
    }

    return $types;
}

function hasSecondResponsibleForIscrizioniPrime(array $pratica, array $confirmed = []): bool
{
    $values = [
        $pratica['responsabile_2_cognome'] ?? '',
        $pratica['responsabile_2_nome'] ?? '',
        $confirmed['email_genitore_2'] ?? $pratica['email_genitore_2'] ?? '',
        $confirmed['telefono_genitore_2'] ?? $pratica['telefono_genitore_2'] ?? '',
    ];

    foreach ($values as $value) {
        if (trim((string)$value) !== '') {
            return true;
        }
    }

    return false;
}

function iscrizioniPrimeEnsureDocumentRows(int $praticaId, ?array $pratica = null): void
{
    if ($pratica === null) {
        $pratica = dbGetFirst("SELECT tipo_iscrizione FROM iscrizioni_prime_pratiche WHERE id = " . intval($praticaId) . " LIMIT 1") ?: [];
    }

    $types = array_keys(iscrizioniPrimeDocumentTypes($pratica));
    $typesSql = implode(', ', array_map('dbQ', $types));

    dbExec("
        DELETE FROM iscrizioni_prime_documenti
        WHERE pratica_id = " . intval($praticaId) . "
          AND tipo_documento NOT IN (" . $typesSql . ")
          AND stato = 'mancante'
          AND (file_path IS NULL OR file_path = '')
          AND (drive_file_id IS NULL OR drive_file_id = '')
    ");

    foreach ($types as $tipo) {
        dbExec("
            INSERT IGNORE INTO iscrizioni_prime_documenti (pratica_id, tipo_documento, stato)
            VALUES (" . intval($praticaId) . ", " . dbQ($tipo) . ", 'mancante')
        ");
    }
}

function iscrizioniPrimeDocumentsForPratica(int $praticaId): array
{
    $pratica = dbGetFirst("SELECT tipo_iscrizione FROM iscrizioni_prime_pratiche WHERE id = " . intval($praticaId) . " LIMIT 1") ?: [];
    iscrizioniPrimeEnsureDocumentRows($praticaId, $pratica);
    $types = array_keys(iscrizioniPrimeDocumentTypes($pratica));

    return dbGetAll("
        SELECT *
        FROM iscrizioni_prime_documenti
        WHERE pratica_id = " . intval($praticaId) . "
          AND tipo_documento IN (" . implode(', ', array_map('dbQ', $types)) . ")
        ORDER BY FIELD(tipo_documento, " . implode(', ', array_map('dbQ', $types)) . ")
    ");
}

function iscrizioniPrimeSecretaryDocumentsForPratica(int $praticaId): array
{
    $pratica = dbGetFirst("SELECT tipo_iscrizione FROM iscrizioni_prime_pratiche WHERE id = " . intval($praticaId) . " LIMIT 1") ?: [];
    $types = array_keys(iscrizioniPrimeSecretaryDocumentTypes($pratica));
    if (!$types) {
        return [];
    }

    $rows = dbGetAll("
        SELECT *
        FROM iscrizioni_prime_documenti
        WHERE pratica_id = " . intval($praticaId) . "
          AND tipo_documento IN (" . implode(', ', array_map('dbQ', $types)) . ")
        ORDER BY FIELD(tipo_documento, " . implode(', ', array_map('dbQ', $types)) . ")
    ");

    $byType = [];
    foreach ($rows as $row) {
        $byType[(string)$row['tipo_documento']] = $row;
    }

    $documents = [];
    foreach ($types as $tipo) {
        $documents[] = $byType[$tipo] ?? [
            'pratica_id' => $praticaId,
            'tipo_documento' => $tipo,
            'stato' => 'mancante',
            'original_name' => null,
            'file_path' => null,
            'drive_file_id' => null,
        ];
    }

    return $documents;
}

function iscrizioniPrimeUploadedFiles(array $fileInput): array
{
    if (!isset($fileInput['name'])) {
        return [];
    }

    if (!is_array($fileInput['name'])) {
        return [$fileInput];
    }

    $files = [];
    foreach ($fileInput['name'] as $index => $name) {
        $files[] = [
            'name' => $name,
            'type' => $fileInput['type'][$index] ?? '',
            'tmp_name' => $fileInput['tmp_name'][$index] ?? '',
            'error' => $fileInput['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $fileInput['size'][$index] ?? 0,
        ];
    }

    return $files;
}

function iscrizioniPrimeMimeType(string $path, string $fallback = 'application/octet-stream'): string
{
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string)finfo_file($finfo, $path);
            finfo_close($finfo);
            if ($mime !== '') {
                return $mime;
            }
        }
    }

    return $fallback;
}

function iscrizioniPrimeEnsureTcpdf(): bool
{
    if (class_exists('TCPDF')) {
        return true;
    }

    $tcpdf = __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';
    if (file_exists($tcpdf)) {
        require_once $tcpdf;
    }

    return class_exists('TCPDF');
}

function iscrizioniPrimeEnsureFpdi(): bool
{
    if (class_exists('\\setasign\\Fpdi\\Tcpdf\\Fpdi')) {
        return true;
    }

    iscrizioniPrimeEnsureTcpdf();

    $fpdiAutoload = __DIR__ . '/vendor/setasign/fpdi/src/autoload.php';
    if (file_exists($fpdiAutoload)) {
        require_once $fpdiAutoload;
    }

    return class_exists('\\setasign\\Fpdi\\Tcpdf\\Fpdi');
}

function iscrizioniPrimeImagesToPdf(array $files, string $target): bool
{
    if (!iscrizioniPrimeEnsureTcpdf()) {
        return false;
    }

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(false, 10);

    foreach ($files as $file) {
        $size = @getimagesize($file['tmp_name']);
        if (!$size) {
            return false;
        }

        [$imageWidth, $imageHeight] = $size;
        $orientation = $imageWidth > $imageHeight ? 'L' : 'P';
        $pdf->AddPage($orientation, 'A4');

        $pageWidth = $pdf->getPageWidth() - 20;
        $pageHeight = $pdf->getPageHeight() - 20;
        $ratio = min($pageWidth / max(1, $imageWidth), $pageHeight / max(1, $imageHeight));
        $renderWidth = $imageWidth * $ratio;
        $renderHeight = $imageHeight * $ratio;
        $x = 10 + (($pageWidth - $renderWidth) / 2);
        $y = 10 + (($pageHeight - $renderHeight) / 2);

        $pdf->Image($file['tmp_name'], $x, $y, $renderWidth, $renderHeight, '', '', '', true, 150);
    }

    $pdf->Output($target, 'F');
    return file_exists($target) && filesize($target) > 0;
}

function iscrizioniPrimeAppendImageToPdf($pdf, string $path): bool
{
    $size = @getimagesize($path);
    if (!$size) {
        return false;
    }

    [$imageWidth, $imageHeight] = $size;
    $orientation = $imageWidth > $imageHeight ? 'L' : 'P';
    $pdf->AddPage($orientation, 'A4');

    $pageWidth = $pdf->getPageWidth() - 20;
    $pageHeight = $pdf->getPageHeight() - 20;
    $ratio = min($pageWidth / max(1, $imageWidth), $pageHeight / max(1, $imageHeight));
    $renderWidth = $imageWidth * $ratio;
    $renderHeight = $imageHeight * $ratio;
    $x = 10 + (($pageWidth - $renderWidth) / 2);
    $y = 10 + (($pageHeight - $renderHeight) / 2);

    $pdf->Image($path, $x, $y, $renderWidth, $renderHeight, '', '', '', true, 150);
    return true;
}

function iscrizioniPrimeMergeFilesToPdf(array $files, string $target): bool
{
    if (!iscrizioniPrimeEnsureFpdi()) {
        return false;
    }

    $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(false, 10);

    foreach ($files as $file) {
        $path = (string)$file['tmp_name'];
        if (($file['kind'] ?? '') === 'pdf') {
            $pageCount = $pdf->setSourceFile($path);
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);
                $orientation = ($size['width'] ?? 0) > ($size['height'] ?? 0) ? 'L' : 'P';
                $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height'], true);
            }
            continue;
        }

        if (!iscrizioniPrimeAppendImageToPdf($pdf, $path)) {
            return false;
        }
    }

    $pdf->Output($target, 'F');
    return file_exists($target) && filesize($target) > 0;
}

function iscrizioniPrimeDocumentPathForAppend(array $document, array &$temporaryFiles): ?string
{
    if (!empty($document['file_path'])) {
        $absolute = realpath(__DIR__ . '/../' . $document['file_path']);
        $base = realpath(iscrizioniPrimeUploadBaseDir() . '/iscrizioni_prime_uploads');
        if ($absolute && $base && strpos($absolute, $base) === 0 && is_file($absolute)) {
            return $absolute;
        }
    }

    $driveFileId = trim((string)($document['drive_file_id'] ?? ''));
    if ($driveFileId === '') {
        return null;
    }

    require_once __DIR__ . '/../api/googleDriveLib.php';
    $download = googleDriveDownloadFileContent($driveFileId);
    $temporaryPath = tempnam(sys_get_temp_dir(), 'iscrizioni_append_');
    if ($temporaryPath === false) {
        return null;
    }

    file_put_contents($temporaryPath, (string)($download['content'] ?? ''));
    $temporaryFiles[] = $temporaryPath;
    return $temporaryPath;
}

function iscrizioniPrimeUploadDocumentByToken(string $token, string $tipo, array $file, string $uploadMode = 'replace'): array
{
    $pratica = iscrizioniPrimeGetByToken($token);
    if (!$pratica) {
        return ['ok' => false, 'message' => 'Link non valido, scaduto o pratica non disponibile.'];
    }

    if (in_array((string)$pratica['stato'], ['verificata', 'annullata'], true)) {
        return ['ok' => false, 'message' => 'La pratica non puo essere modificata in questo stato.'];
    }

    $types = iscrizioniPrimeSecretaryAllowedDocumentTypes($pratica);
    if (!isset($types[$tipo])) {
        return ['ok' => false, 'message' => 'Tipo documento non valido.'];
    }

    $files = array_values(array_filter(iscrizioniPrimeUploadedFiles($file), function ($item) {
        return intval($item['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }));

    if (empty($files)) {
        return ['ok' => false, 'message' => 'Selezionare un file da caricare.'];
    }

    if (count($files) > 12) {
        return ['ok' => false, 'message' => 'Caricare al massimo 12 file per documento.'];
    }

    $totalSize = 0;
    $originalNames = [];
    $preparedFiles = [];
    $pdfCount = 0;
    $imageCount = 0;

    foreach ($files as $currentFile) {
        if (!empty($currentFile['error'])) {
            return ['ok' => false, 'message' => 'Errore durante il caricamento di uno dei file.'];
        }
        if (empty($currentFile['tmp_name']) || !is_uploaded_file($currentFile['tmp_name'])) {
            return ['ok' => false, 'message' => 'Uno dei file non e valido.'];
        }

        $size = intval($currentFile['size'] ?? 0);
        $totalSize += $size;
        if ($size <= 0 || $size > 20 * 1024 * 1024) {
            return ['ok' => false, 'message' => 'Ogni file deve essere inferiore a 20 MB.'];
        }

        $originalName = (string)($currentFile['name'] ?? 'documento');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $mime = iscrizioniPrimeMimeType($currentFile['tmp_name'], (string)($currentFile['type'] ?? ''));
        $originalNames[] = $originalName;

        if ($extension === 'pdf' || $mime === 'application/pdf') {
            $currentFile['kind'] = 'pdf';
            $preparedFiles[] = $currentFile;
            $pdfCount++;
            continue;
        }

        if (in_array($extension, ['jpg', 'jpeg', 'png'], true) || in_array($mime, ['image/jpeg', 'image/png'], true)) {
            $currentFile['kind'] = 'image';
            $preparedFiles[] = $currentFile;
            $imageCount++;
            continue;
        }

        return ['ok' => false, 'message' => 'Formato non supportato. Caricare PDF oppure foto JPG/PNG.'];
    }

    if ($totalSize > 60 * 1024 * 1024) {
        return ['ok' => false, 'message' => 'Il caricamento complessivo deve essere inferiore a 60 MB.'];
    }

    $uploadMode = $uploadMode === 'append' ? 'append' : 'replace';
    iscrizioniPrimeEnsureDocumentRows((int)$pratica['id'], $pratica);
    $previousDocument = dbGetFirst("
        SELECT *
        FROM iscrizioni_prime_documenti
        WHERE pratica_id = " . intval($pratica['id']) . "
          AND tipo_documento = " . dbQ($tipo) . "
        LIMIT 1
    ");
    $temporaryAppendFiles = [];
    $appendedToPrevious = false;

    if (
        $uploadMode === 'append'
        && $previousDocument
        && (string)($previousDocument['stato'] ?? '') !== 'mancante'
        && (!empty($previousDocument['file_path']) || !empty($previousDocument['drive_file_id']))
    ) {
        try {
            $previousPath = iscrizioniPrimeDocumentPathForAppend($previousDocument, $temporaryAppendFiles);
        } catch (Throwable $e) {
            $previousPath = null;
        }

        if (!$previousPath) {
            foreach ($temporaryAppendFiles as $temporaryFile) {
                @unlink($temporaryFile);
            }
            return ['ok' => false, 'message' => 'Impossibile recuperare il PDF gia caricato da unire ai nuovi file.'];
        }

        array_unshift($preparedFiles, [
            'tmp_name' => $previousPath,
            'kind' => 'pdf',
            'name' => 'PDF gia caricato',
        ]);
        $pdfCount++;
        $appendedToPrevious = true;
    }

    $dir = iscrizioniPrimeUploadDir((int)$pratica['id']);
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        foreach ($temporaryAppendFiles as $temporaryFile) {
            @unlink($temporaryFile);
        }
        return ['ok' => false, 'message' => 'Impossibile creare la cartella di destinazione.'];
    }

    $denyFile = dirname($dir) . '/.htaccess';
    if (!file_exists($denyFile)) {
        @file_put_contents($denyFile, "Require all denied\n");
    }

    $generatedBaseName = iscrizioniPrimeGeneratedPdfBaseName($pratica, $types[$tipo]);
    $fileName = iscrizioniPrimeUniquePdfFileName($dir, $generatedBaseName);
    $target = $dir . '/' . $fileName;
    $storedOriginalName = $fileName;
    $mime = 'application/pdf';

    if (!$appendedToPrevious && $pdfCount === 1 && $imageCount === 0) {
        if (!move_uploaded_file($preparedFiles[0]['tmp_name'], $target)) {
            foreach ($temporaryAppendFiles as $temporaryFile) {
                @unlink($temporaryFile);
            }
            return ['ok' => false, 'message' => 'Impossibile salvare il file caricato.'];
        }
    } elseif (!iscrizioniPrimeMergeFilesToPdf($preparedFiles, $target)) {
        foreach ($temporaryAppendFiles as $temporaryFile) {
            @unlink($temporaryFile);
        }
        return ['ok' => false, 'message' => 'Impossibile unire i file in PDF. Verificare che i PDF non siano protetti e che le foto siano JPG/PNG.'];
    }
    foreach ($temporaryAppendFiles as $temporaryFile) {
        @unlink($temporaryFile);
    }

    $relativePath = 'data/iscrizioni_prime_uploads/' . intval($pratica['id']) . '/' . $fileName;
    $storedSize = file_exists($target) ? filesize($target) : $totalSize;
    $storageType = 'LOCAL';
    $driveFileId = '';
    $driveWebViewLink = '';
    $driveFolderId = '';

    if (iscrizioniPrimeDriveEnabled()) {
        try {
            require_once __DIR__ . '/../api/googleDriveLib.php';
            $driveFolderId = iscrizioniPrimeDriveFolderId($pratica);
            $driveName = iscrizioniPrimeDriveFileName($pratica, $tipo, $types[$tipo]);
            $upload = googleDriveUploadFile($target, $driveName, $driveFolderId, 'application/pdf');
            $driveFileId = trim((string)($upload['id'] ?? ''));
            if ($driveFileId === '') {
                throw new RuntimeException('Upload Drive completato senza ID file.');
            }
            $driveWebViewLink = (string)($upload['webViewLink'] ?? '');
            $storageType = 'DRIVE';
        } catch (Throwable $e) {
            @unlink($target);
            return ['ok' => false, 'message' => 'Impossibile caricare il documento su Google Drive: ' . $e->getMessage()];
        }
    }

    iscrizioniPrimeEnsureDocumentRows((int)$pratica['id'], $pratica);
    dbExec("
        UPDATE iscrizioni_prime_documenti SET
            stato = 'caricato',
            file_path = " . dbQ($relativePath) . ",
            original_name = " . dbQ($storedOriginalName) . ",
            mime_type = " . dbQ($mime) . ",
            file_size = " . intval($storedSize) . ",
            storage_type = " . dbQ($storageType) . ",
            drive_file_id = " . dbQ($driveFileId) . ",
            drive_web_view_link = " . dbQ($driveWebViewLink) . ",
            drive_folder_id = " . dbQ($driveFolderId) . ",
            uploaded_at = NOW()
        WHERE pratica_id = " . intval($pratica['id']) . "
          AND tipo_documento = " . dbQ($tipo) . "
        LIMIT 1
    ");

    if ($previousDocument) {
        $previousDriveFileId = trim((string)($previousDocument['drive_file_id'] ?? ''));
        if ($previousDriveFileId !== '' && $previousDriveFileId !== $driveFileId) {
            try {
                require_once __DIR__ . '/../api/googleDriveLib.php';
                googleDriveDeleteFile($previousDriveFileId);
            } catch (Throwable $e) {
                // Il nuovo caricamento e' gia salvato: non blocchiamo la pratica per una pulizia Drive fallita.
            }
        }

        if (!empty($previousDocument['file_path']) && (string)$previousDocument['file_path'] !== $relativePath) {
            $previousAbsolute = realpath(__DIR__ . '/../' . $previousDocument['file_path']);
            $base = realpath(iscrizioniPrimeUploadBaseDir() . '/iscrizioni_prime_uploads');
            if ($previousAbsolute && $base && strpos($previousAbsolute, $base) === 0 && is_file($previousAbsolute)) {
                @unlink($previousAbsolute);
            }
        }
    }

    $nextPracticeState = iscrizioniPrimeIsReceivedBySecretaryState((string)($pratica['stato'] ?? '')) ? 'inviata' : 'bozza';
    $newsMessage = null;
    if (iscrizioniPrimeIsReceivedBySecretaryState((string)($pratica['stato'] ?? ''))) {
        $newsMessage = $appendedToPrevious
            ? 'La famiglia ha aggiunto nuovi file al documento: ' . $types[$tipo] . '. Deve reinviare la conferma.'
            : 'La famiglia ha caricato o sostituito il documento: ' . $types[$tipo] . '. Deve reinviare la conferma.';
    }
    dbExec("
        UPDATE iscrizioni_prime_pratiche SET
            stato = " . dbQ($nextPracticeState) . ",
            novita_segreteria_at = " . ($newsMessage ? 'NOW()' : 'novita_segreteria_at') . ",
            novita_segreteria_messaggio = " . ($newsMessage ? dbQ($newsMessage) : 'novita_segreteria_messaggio') . ",
            updated_at = NOW()
        WHERE id = " . intval($pratica['id']) . "
        LIMIT 1
    ");
    if ($newsMessage) {
        iscrizioniPrimeRecordEvent((int)$pratica['id'], 'allegati_modificati', 'Allegati modificati dopo invio', [
            'messaggio' => $newsMessage,
            'created_by' => 'Famiglia',
            'dettagli' => [
                'documento' => $types[$tipo],
                'modalita' => $appendedToPrevious ? 'aggiunta al PDF esistente' : 'caricamento/sostituzione',
            ],
        ]);
    }

    return [
        'ok' => true,
        'message' => $appendedToPrevious
            ? $types[$tipo] . ' aggiornato. I nuovi file sono stati aggiunti al PDF gia caricato.'
            : $types[$tipo] . ' caricato. Il PDF e stato generato: puoi aprirlo con Visualizza PDF caricato.',
        'document' => [
            'tipo_documento' => $tipo,
            'stato' => 'caricato',
            'original_name' => $storedOriginalName,
            'file_size' => $storedSize,
        ],
    ];
}

function iscrizioniPrimeUploadSecretaryPdf(int $praticaId, string $tipo, array $file): array
{
    $pratica = dbGetFirst("SELECT * FROM iscrizioni_prime_pratiche WHERE id = " . dbI($praticaId) . " LIMIT 1");
    if (!$pratica) {
        return ['ok' => false, 'message' => 'Pratica non trovata.'];
    }

    $types = iscrizioniPrimeSecretaryAllowedDocumentTypes($pratica);
    if (!isset($types[$tipo])) {
        return ['ok' => false, 'message' => 'Tipo documento non valido.'];
    }

    $files = array_values(array_filter(iscrizioniPrimeUploadedFiles($file), function ($item) {
        return intval($item['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }));
    if (empty($files)) {
        return ['ok' => false, 'message' => 'Selezionare almeno un PDF da caricare.'];
    }
    if (count($files) > 12) {
        return ['ok' => false, 'message' => 'Caricare al massimo 12 PDF per documento.'];
    }

    $totalSize = 0;
    $preparedFiles = [];
    foreach ($files as $currentFile) {
        if (!empty($currentFile['error']) || empty($currentFile['tmp_name']) || !is_uploaded_file($currentFile['tmp_name'])) {
            return ['ok' => false, 'message' => 'Uno dei PDF selezionati non e valido.'];
        }

        $size = intval($currentFile['size'] ?? 0);
        $totalSize += $size;
        if ($size <= 0 || $size > 30 * 1024 * 1024) {
            return ['ok' => false, 'message' => 'Ogni PDF deve essere inferiore a 30 MB.'];
        }

        $originalName = (string)($currentFile['name'] ?? 'documento.pdf');
        $mime = iscrizioniPrimeMimeType($currentFile['tmp_name'], (string)($currentFile['type'] ?? ''));
        if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'pdf' && $mime !== 'application/pdf') {
            return ['ok' => false, 'message' => 'Caricare solo file PDF.'];
        }
        $currentFile['kind'] = 'pdf';
        $preparedFiles[] = $currentFile;
    }
    if ($totalSize > 90 * 1024 * 1024) {
        return ['ok' => false, 'message' => 'Il caricamento complessivo deve essere inferiore a 90 MB.'];
    }

    iscrizioniPrimeEnsureDocumentRows((int)$pratica['id'], $pratica);
    $previousDocument = dbGetFirst("
        SELECT *
        FROM iscrizioni_prime_documenti
        WHERE pratica_id = " . dbI($praticaId) . "
          AND tipo_documento = " . dbQ($tipo) . "
        LIMIT 1
    ");

    $dir = iscrizioniPrimeUploadDir((int)$pratica['id']);
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        return ['ok' => false, 'message' => 'Impossibile creare la cartella di destinazione.'];
    }
    $denyFile = dirname($dir) . '/.htaccess';
    if (!file_exists($denyFile)) {
        @file_put_contents($denyFile, "Require all denied\n");
    }

    $generatedBaseName = iscrizioniPrimeGeneratedPdfBaseName($pratica, $types[$tipo]);
    $fileName = iscrizioniPrimeUniquePdfFileName($dir, $generatedBaseName);
    $target = $dir . '/' . $fileName;
    if (count($preparedFiles) === 1) {
        if (!move_uploaded_file($preparedFiles[0]['tmp_name'], $target)) {
            return ['ok' => false, 'message' => 'Impossibile salvare il PDF caricato.'];
        }
    } elseif (!iscrizioniPrimeMergeFilesToPdf($preparedFiles, $target)) {
        return ['ok' => false, 'message' => 'Impossibile salvare il PDF caricato.'];
    }

    $relativePath = 'data/iscrizioni_prime_uploads/' . intval($pratica['id']) . '/' . $fileName;
    $storageType = 'LOCAL';
    $driveFileId = null;
    $driveWebViewLink = null;
    $driveFolderId = null;

    if (iscrizioniPrimeDriveEnabled()) {
        try {
            require_once __DIR__ . '/../api/googleDriveLib.php';
            $driveFolderId = iscrizioniPrimeDriveFolderId($pratica);
            $upload = googleDriveUploadFile($target, iscrizioniPrimeDriveFileName($pratica, $tipo, $types[$tipo]), $driveFolderId, 'application/pdf');
            $driveFileId = trim((string)($upload['id'] ?? ''));
            if ($driveFileId === '') {
                throw new RuntimeException('Upload Drive completato senza ID file.');
            }
            $driveWebViewLink = (string)($upload['webViewLink'] ?? '');
            $storageType = 'DRIVE';
        } catch (Throwable $e) {
            @unlink($target);
            return ['ok' => false, 'message' => 'Impossibile caricare il documento su Google Drive: ' . $e->getMessage()];
        }
    }

    dbExec("
        INSERT IGNORE INTO iscrizioni_prime_documenti (pratica_id, tipo_documento, stato)
        VALUES (" . dbI($praticaId) . ", " . dbQ($tipo) . ", 'mancante')
    ");

    dbExec("
        UPDATE iscrizioni_prime_documenti SET
            stato = 'caricato',
            file_path = " . dbQ($relativePath) . ",
            original_name = " . dbQ($fileName) . ",
            mime_type = 'application/pdf',
            file_size = " . intval(filesize($target) ?: $totalSize) . ",
            storage_type = " . dbQ($storageType) . ",
            drive_file_id = " . dbQ($driveFileId) . ",
            drive_web_view_link = " . dbQ($driveWebViewLink) . ",
            drive_folder_id = " . dbQ($driveFolderId) . ",
            uploaded_at = NOW(),
            note = 'PDF caricato dalla segreteria didattica'
        WHERE pratica_id = " . dbI($praticaId) . "
          AND tipo_documento = " . dbQ($tipo) . "
        LIMIT 1
    ");

    if ($previousDocument && !empty($previousDocument['drive_file_id']) && (string)$previousDocument['drive_file_id'] !== (string)$driveFileId) {
        try {
            require_once __DIR__ . '/../api/googleDriveLib.php';
            googleDriveDeleteFile((string)$previousDocument['drive_file_id']);
        } catch (Throwable $e) {
        }
    }

    if ($previousDocument && !empty($previousDocument['file_path']) && (string)$previousDocument['file_path'] !== $relativePath) {
        $previousAbsolute = realpath(__DIR__ . '/../' . $previousDocument['file_path']);
        $base = realpath(iscrizioniPrimeUploadBaseDir() . '/iscrizioni_prime_uploads');
        if ($previousAbsolute && $base && strpos($previousAbsolute, $base) === 0 && is_file($previousAbsolute)) {
            @unlink($previousAbsolute);
        }
    }

    return [
        'ok' => true,
        'message' => $types[$tipo] . (count($preparedFiles) > 1 ? ' caricato dalla segreteria: PDF unico generato da ' . count($preparedFiles) . ' file.' : ' caricato dalla segreteria.'),
    ];
}

function iscrizioniPrimeCambioScuolaAllowedValues(string $field): array
{
    $values = [
        'canale' => ['mail', 'telefono', 'presenza', 'altro'],
        'colloquio_stato' => ['da_valutare', 'da_fare', 'fatto', 'non_necessario'],
        'nulla_osta_stato' => ['da_richiedere', 'richiesto', 'ricevuto', 'evaso_inviato', 'non_necessario'],
        'documenti_stato' => ['da_verificare', 'manca_qualcosa', 'completi'],
        'pratica_stato' => ['aperta', 'in_attesa', 'completata'],
    ];
    return $values[$field] ?? [];
}

function iscrizioniPrimeCambioScuolaNormalizeValue(string $field, string $value): string
{
    $value = trim($value);
    $allowed = iscrizioniPrimeCambioScuolaAllowedValues($field);
    if (!$allowed) {
        return $value;
    }
    return in_array($value, $allowed, true) ? $value : $allowed[0];
}

function iscrizioniPrimeGetCambioScuola(int $praticaId): ?array
{
    $row = dbGetFirst("
        SELECT *
        FROM iscrizioni_prime_cambio_scuola
        WHERE pratica_id = " . dbI($praticaId) . "
        LIMIT 1
    ");
    return $row ?: null;
}

function iscrizioniPrimeCambioScuolaEventi(int $praticaId): array
{
    return dbGetAll("
        SELECT *
        FROM iscrizioni_prime_cambio_scuola_eventi
        WHERE pratica_id = " . dbI($praticaId) . "
        ORDER BY created_at DESC, id DESC
    ");
}

function iscrizioniPrimeSaveCambioScuola(int $praticaId, array $data, ?array $file, string $updatedBy = ''): array
{
    $pratica = dbGetFirst("SELECT * FROM iscrizioni_prime_pratiche WHERE id = " . dbI($praticaId) . " LIMIT 1");
    if (!$pratica) {
        return ['ok' => false, 'message' => 'Pratica non trovata.'];
    }

    $richiestaData = trim((string)($data['richiesta_data'] ?? ''));
    if ($richiestaData !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $richiestaData)) {
        return ['ok' => false, 'message' => 'Data richiesta non valida.'];
    }

    $canale = iscrizioniPrimeCambioScuolaNormalizeValue('canale', (string)($data['canale'] ?? 'mail'));
    $idIstitutoDestinazione = intval($data['id_istituto_destinazione'] ?? 0) ?: null;
    $scuolaDestinazione = trim((string)($data['scuola_destinazione'] ?? ''));
    $nomeIstitutoDestinazione = scuoleIstitutiNameById($idIstitutoDestinazione);
    if ($nomeIstitutoDestinazione !== '') {
        $scuolaDestinazione = $nomeIstitutoDestinazione;
    }
    $indirizzoDestinazione = trim((string)($data['indirizzo_destinazione'] ?? ''));
    $colloquioStato = iscrizioniPrimeCambioScuolaNormalizeValue('colloquio_stato', (string)($data['colloquio_stato'] ?? 'da_valutare'));
    $nullaOstaStato = iscrizioniPrimeCambioScuolaNormalizeValue('nulla_osta_stato', (string)($data['nulla_osta_stato'] ?? 'da_richiedere'));
    $documentiStato = iscrizioniPrimeCambioScuolaNormalizeValue('documenti_stato', (string)($data['documenti_stato'] ?? 'da_verificare'));
    $praticaStato = iscrizioniPrimeCambioScuolaNormalizeValue('pratica_stato', (string)($data['pratica_stato'] ?? 'aperta'));
    $note = trim((string)($data['note'] ?? ''));

    $eventRelativePath = null;
    $eventOriginalName = null;
    $eventFileSize = null;

    if ($file && intval($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (!empty($file['error']) || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'message' => 'Allegato non valido.'];
        }
        $size = intval($file['size'] ?? 0);
        if ($size <= 0 || $size > 20 * 1024 * 1024) {
            return ['ok' => false, 'message' => 'Il PDF della richiesta deve essere inferiore a 20 MB.'];
        }
        $name = (string)($file['name'] ?? 'richiesta-cambio-scuola.pdf');
        $mime = iscrizioniPrimeMimeType($file['tmp_name'], (string)($file['type'] ?? ''));
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'pdf' && $mime !== 'application/pdf') {
            return ['ok' => false, 'message' => 'La copia della richiesta deve essere un PDF.'];
        }

        $dir = iscrizioniPrimeCambioScuolaDir($praticaId);
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            return ['ok' => false, 'message' => 'Impossibile creare la cartella cambio scuola.'];
        }
        $denyFile = dirname($dir) . '/.htaccess';
        if (!file_exists($denyFile)) {
            @file_put_contents($denyFile, "Require all denied\n");
        }

        $baseName = iscrizioniPrimeDriveSafeName('CAMBIO SCUOLA ' . ($pratica['cognome'] ?? '') . ' ' . ($pratica['nome'] ?? ''));
        if ($baseName === '') {
            $baseName = 'cambio-scuola';
        }
        $targetName = iscrizioniPrimeUniquePdfFileName($dir, $baseName);
        $target = $dir . '/' . $targetName;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            return ['ok' => false, 'message' => 'Impossibile salvare il PDF della richiesta.'];
        }

        $eventRelativePath = 'data/iscrizioni_cambio_scuola/' . intval($praticaId) . '/' . $targetName;
        $eventOriginalName = $name;
        $eventFileSize = intval(filesize($target) ?: $size);
    }

    $existing = iscrizioniPrimeGetCambioScuola($praticaId);
    $summaryPath = $eventRelativePath ?: ($existing['allegato_path'] ?? null);
    $summaryOriginalName = $eventOriginalName ?: ($existing['allegato_original_name'] ?? null);
    $summaryFileSize = $eventFileSize ?: ($existing['allegato_size'] ?? null);

    dbExec("
        INSERT INTO iscrizioni_prime_cambio_scuola
            (pratica_id, tipo_iscrizione, richiesta_data, canale, id_istituto_destinazione, scuola_destinazione, indirizzo_destinazione, colloquio_stato, nulla_osta_stato, documenti_stato, pratica_stato, note, allegato_path, allegato_original_name, allegato_size, created_by, created_at, updated_at)
        VALUES
            (
                " . dbI($praticaId) . ",
                " . dbQ(iscrizioniPrimeTipoIscrizioneFromPratica($pratica)) . ",
                " . dbQ($richiestaData) . ",
                " . dbQ($canale) . ",
                " . dbI($idIstitutoDestinazione) . ",
                " . dbQ($scuolaDestinazione) . ",
                " . dbQ($indirizzoDestinazione) . ",
                " . dbQ($colloquioStato) . ",
                " . dbQ($nullaOstaStato) . ",
                " . dbQ($documentiStato) . ",
                " . dbQ($praticaStato) . ",
                " . dbQ($note) . ",
                " . dbQ($summaryPath) . ",
                " . dbQ($summaryOriginalName) . ",
                " . dbI($summaryFileSize) . ",
                " . dbQ($updatedBy) . ",
                NOW(),
                NOW()
            )
        ON DUPLICATE KEY UPDATE
            tipo_iscrizione = VALUES(tipo_iscrizione),
            richiesta_data = VALUES(richiesta_data),
            canale = VALUES(canale),
            id_istituto_destinazione = VALUES(id_istituto_destinazione),
            scuola_destinazione = VALUES(scuola_destinazione),
            indirizzo_destinazione = VALUES(indirizzo_destinazione),
            colloquio_stato = VALUES(colloquio_stato),
            nulla_osta_stato = VALUES(nulla_osta_stato),
            documenti_stato = VALUES(documenti_stato),
            pratica_stato = VALUES(pratica_stato),
            note = VALUES(note),
            allegato_path = VALUES(allegato_path),
            allegato_original_name = VALUES(allegato_original_name),
            allegato_size = VALUES(allegato_size),
            updated_at = NOW()
    ");

    $cambioScuolaId = intval(dbGetValue("
        SELECT id
        FROM iscrizioni_prime_cambio_scuola
        WHERE pratica_id = " . dbI($praticaId) . "
        LIMIT 1
    ") ?? 0);

    dbExec("
        INSERT INTO iscrizioni_prime_cambio_scuola_eventi
            (cambio_scuola_id, pratica_id, tipo_iscrizione, richiesta_data, canale, id_istituto_destinazione, scuola_destinazione, indirizzo_destinazione, colloquio_stato, nulla_osta_stato, documenti_stato, pratica_stato, stato_pratica_precedente, note, allegato_path, allegato_original_name, allegato_size, created_by, created_at)
        VALUES
            (
                " . dbI($cambioScuolaId) . ",
                " . dbI($praticaId) . ",
                " . dbQ(iscrizioniPrimeTipoIscrizioneFromPratica($pratica)) . ",
                " . dbQ($richiestaData) . ",
                " . dbQ($canale) . ",
                " . dbI($idIstitutoDestinazione) . ",
                " . dbQ($scuolaDestinazione) . ",
                " . dbQ($indirizzoDestinazione) . ",
                " . dbQ($colloquioStato) . ",
                " . dbQ($nullaOstaStato) . ",
                " . dbQ($documentiStato) . ",
                " . dbQ($praticaStato) . ",
                " . dbQ((string)($pratica['stato'] ?? '')) . ",
                " . dbQ($note) . ",
                " . dbQ($eventRelativePath) . ",
                " . dbQ($eventOriginalName) . ",
                " . dbI($eventFileSize) . ",
                " . dbQ($updatedBy) . ",
                NOW()
            )
    ");

    iscrizioniPrimeRecordEvent($praticaId, 'cambio_scuola', 'Cambio scuola / non prosegue registrato', [
        'stato_precedente' => (string)($pratica['stato'] ?? ''),
        'stato_nuovo' => 'annullata',
        'messaggio' => $note,
        'created_by' => $updatedBy,
        'dettagli' => [
            'richiesta_data' => $richiestaData,
            'canale' => $canale,
            'scuola_destinazione' => $scuolaDestinazione,
            'indirizzo_destinazione' => $indirizzoDestinazione,
            'colloquio_stato' => $colloquioStato,
            'nulla_osta_stato' => $nullaOstaStato,
            'documenti_stato' => $documentiStato,
            'pratica_stato' => $praticaStato,
            'allegato' => $eventOriginalName,
        ],
    ]);

    dbExec("
        UPDATE iscrizioni_prime_pratiche SET
            stato = 'annullata',
            updated_at = NOW()
        WHERE id = " . dbI($praticaId) . "
        LIMIT 1
    ");

    return [
        'ok' => true,
        'message' => 'Cambio scuola registrato. La pratica non ricevera\' piu comunicazioni automatiche.',
        'record' => iscrizioniPrimeGetCambioScuola($praticaId),
        'eventi' => iscrizioniPrimeCambioScuolaEventi($praticaId),
    ];
}

function iscrizioniPrimeUndoLastCambioScuola(int $praticaId): array
{
    iscrizioniPrimeEnsureSchema();

    $pratica = dbGetFirst("SELECT * FROM iscrizioni_prime_pratiche WHERE id = " . dbI($praticaId) . " LIMIT 1");
    if (!$pratica) {
        return ['ok' => false, 'message' => 'Pratica non trovata.'];
    }

    $last = dbGetFirst("
        SELECT *
        FROM iscrizioni_prime_cambio_scuola_eventi
        WHERE pratica_id = " . dbI($praticaId) . "
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");
    if (!$last) {
        return ['ok' => false, 'message' => 'Non ci sono aggiornamenti da annullare.'];
    }

    $attachmentPath = trim((string)($last['allegato_path'] ?? ''));
    if ($attachmentPath !== '') {
        $path = realpath(__DIR__ . '/../' . $attachmentPath);
        $base = realpath(iscrizioniPrimeUploadBaseDir() . '/iscrizioni_cambio_scuola');
        if ($path && $base && strpos($path, $base) === 0 && is_file($path)) {
            @unlink($path);
        }
    }

    dbExec("
        DELETE FROM iscrizioni_prime_cambio_scuola_eventi
        WHERE id = " . dbI($last['id']) . "
          AND pratica_id = " . dbI($praticaId) . "
        LIMIT 1
    ");

    $previous = dbGetFirst("
        SELECT *
        FROM iscrizioni_prime_cambio_scuola_eventi
        WHERE pratica_id = " . dbI($praticaId) . "
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");

    if ($previous) {
        dbExec("
            UPDATE iscrizioni_prime_cambio_scuola SET
                tipo_iscrizione = " . dbQ((string)($previous['tipo_iscrizione'] ?? iscrizioniPrimeTipoIscrizioneFromPratica($pratica))) . ",
                richiesta_data = " . dbQ((string)($previous['richiesta_data'] ?? '')) . ",
                canale = " . dbQ((string)($previous['canale'] ?? 'mail')) . ",
                id_istituto_destinazione = " . dbI($previous['id_istituto_destinazione'] ?? null) . ",
                scuola_destinazione = " . dbQ((string)($previous['scuola_destinazione'] ?? '')) . ",
                indirizzo_destinazione = " . dbQ((string)($previous['indirizzo_destinazione'] ?? '')) . ",
                colloquio_stato = " . dbQ((string)($previous['colloquio_stato'] ?? 'da_valutare')) . ",
                nulla_osta_stato = " . dbQ((string)($previous['nulla_osta_stato'] ?? 'da_richiedere')) . ",
                documenti_stato = " . dbQ((string)($previous['documenti_stato'] ?? 'da_verificare')) . ",
                pratica_stato = " . dbQ((string)($previous['pratica_stato'] ?? 'aperta')) . ",
                note = " . dbQ((string)($previous['note'] ?? '')) . ",
                allegato_path = " . dbQ((string)($previous['allegato_path'] ?? '')) . ",
                allegato_original_name = " . dbQ((string)($previous['allegato_original_name'] ?? '')) . ",
                allegato_size = " . dbI($previous['allegato_size'] ?? null) . ",
                updated_at = NOW()
            WHERE pratica_id = " . dbI($praticaId) . "
            LIMIT 1
        ");

        dbExec("
            UPDATE iscrizioni_prime_pratiche SET
                stato = 'annullata',
                updated_at = NOW()
            WHERE id = " . dbI($praticaId) . "
            LIMIT 1
        ");
    } else {
        $fallbackState = trim((string)($last['stato_pratica_precedente'] ?? ''));
        if ($fallbackState === '' || $fallbackState === 'annullata') {
            $fallbackState = 'importata';
        }
        dbExec("DELETE FROM iscrizioni_prime_cambio_scuola WHERE pratica_id = " . dbI($praticaId) . " LIMIT 1");
        dbExec("
            UPDATE iscrizioni_prime_pratiche SET
                stato = " . dbQ($fallbackState) . ",
                updated_at = NOW()
            WHERE id = " . dbI($praticaId) . "
            LIMIT 1
        ");
    }

    return [
        'ok' => true,
        'message' => 'Ultimo aggiornamento cambio scuola annullato.',
        'record' => iscrizioniPrimeGetCambioScuola($praticaId),
        'eventi' => iscrizioniPrimeCambioScuolaEventi($praticaId),
    ];
}

function iscrizioniPrimeCambioScuolaAllegatoPath(int $praticaId, int $eventoId = 0): ?string
{
    if ($eventoId > 0) {
        $record = dbGetFirst("
            SELECT *
            FROM iscrizioni_prime_cambio_scuola_eventi
            WHERE id = " . dbI($eventoId) . "
              AND pratica_id = " . dbI($praticaId) . "
            LIMIT 1
        ");
    } else {
        $record = iscrizioniPrimeGetCambioScuola($praticaId);
    }
    if (!$record || empty($record['allegato_path'])) {
        return null;
    }
    $path = realpath(__DIR__ . '/../' . (string)$record['allegato_path']);
    $base = realpath(iscrizioniPrimeUploadBaseDir() . '/iscrizioni_cambio_scuola');
    if (!$path || !$base || strpos($path, $base) !== 0 || !is_file($path)) {
        return null;
    }
    return $path;
}

function iscrizioniPrimeDeleteDocumentByToken(string $token, string $tipo): array
{
    $pratica = iscrizioniPrimeGetByToken($token);
    if (!$pratica) {
        return ['ok' => false, 'message' => 'Link non valido, scaduto o pratica non disponibile.'];
    }

    if (in_array((string)$pratica['stato'], ['verificata', 'annullata'], true)) {
        return ['ok' => false, 'message' => 'La pratica non puo essere modificata in questo stato.'];
    }

    $types = iscrizioniPrimeDocumentTypes($pratica);
    if (!isset($types[$tipo])) {
        return ['ok' => false, 'message' => 'Tipo documento non valido.'];
    }

    $document = dbGetFirst("
        SELECT *
        FROM iscrizioni_prime_documenti
        WHERE pratica_id = " . intval($pratica['id']) . "
          AND tipo_documento = " . dbQ($tipo) . "
        LIMIT 1
    ");

    if ($document && !empty($document['drive_file_id'])) {
        try {
            require_once __DIR__ . '/../api/googleDriveLib.php';
            googleDriveDeleteFile((string)$document['drive_file_id']);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Impossibile cancellare il documento da Google Drive: ' . $e->getMessage()];
        }
    }

    if ($document && !empty($document['file_path'])) {
        $absolute = realpath(__DIR__ . '/../' . $document['file_path']);
        $base = realpath(iscrizioniPrimeUploadBaseDir() . '/iscrizioni_prime_uploads');
        if ($absolute && $base && strpos($absolute, $base) === 0 && is_file($absolute)) {
            @unlink($absolute);
        }
    }

    dbExec("
        UPDATE iscrizioni_prime_documenti SET
            stato = 'mancante',
            file_path = NULL,
            original_name = NULL,
            mime_type = NULL,
            file_size = NULL,
            storage_type = 'LOCAL',
            drive_file_id = NULL,
            drive_web_view_link = NULL,
            drive_folder_id = NULL,
            uploaded_at = NULL,
            extracted_json = NULL,
            verified_at = NULL
        WHERE pratica_id = " . intval($pratica['id']) . "
          AND tipo_documento = " . dbQ($tipo) . "
        LIMIT 1
    ");

    $nextPracticeState = iscrizioniPrimeIsReceivedBySecretaryState((string)($pratica['stato'] ?? '')) ? 'inviata' : 'bozza';
    $newsMessage = iscrizioniPrimeIsReceivedBySecretaryState((string)($pratica['stato'] ?? ''))
        ? 'La famiglia ha cancellato il documento: ' . $types[$tipo] . '. Deve reinviare la conferma.'
        : null;
    dbExec("
        UPDATE iscrizioni_prime_pratiche SET
            stato = " . dbQ($nextPracticeState) . ",
            novita_segreteria_at = " . ($newsMessage ? 'NOW()' : 'novita_segreteria_at') . ",
            novita_segreteria_messaggio = " . ($newsMessage ? dbQ($newsMessage) : 'novita_segreteria_messaggio') . ",
            updated_at = NOW()
        WHERE id = " . intval($pratica['id']) . "
        LIMIT 1
    ");
    if ($newsMessage) {
        iscrizioniPrimeRecordEvent((int)$pratica['id'], 'allegati_modificati', 'Allegato cancellato dopo invio', [
            'messaggio' => $newsMessage,
            'created_by' => 'Famiglia',
            'dettagli' => [
                'documento' => $types[$tipo],
                'modalita' => 'cancellazione',
            ],
        ]);
    }

    return ['ok' => true, 'message' => $types[$tipo] . ' cancellato.'];
}

function iscrizioniPrimeMarkDocumentPaperByToken(string $token, string $tipo): array
{
    $pratica = iscrizioniPrimeGetByToken($token);
    if (!$pratica) {
        return ['ok' => false, 'message' => 'Link non valido, scaduto o pratica non disponibile.'];
    }

    if (in_array((string)$pratica['stato'], ['verificata', 'annullata'], true)) {
        return ['ok' => false, 'message' => 'La pratica non puo essere modificata in questo stato.'];
    }

    $types = iscrizioniPrimeDocumentTypes($pratica);
    if (!isset($types[$tipo])) {
        return ['ok' => false, 'message' => 'Tipo documento non valido.'];
    }

    $delete = iscrizioniPrimeDeleteDocumentByToken($token, $tipo);
    if (!$delete['ok']) {
        return $delete;
    }

    iscrizioniPrimeEnsureDocumentRows((int)$pratica['id'], $pratica);
    dbExec("
        UPDATE iscrizioni_prime_documenti SET
            stato = 'consegna_cartacea',
            original_name = 'Consegna cartacea in segreteria didattica',
            note = 'Il genitore ha dichiarato che consegnera'' una fotocopia in segreteria didattica.',
            uploaded_at = NOW()
        WHERE pratica_id = " . intval($pratica['id']) . "
          AND tipo_documento = " . dbQ($tipo) . "
        LIMIT 1
    ");

    $nextPracticeState = iscrizioniPrimeIsReceivedBySecretaryState((string)($pratica['stato'] ?? '')) ? 'inviata' : 'bozza';
    $newsMessage = iscrizioniPrimeIsReceivedBySecretaryState((string)($pratica['stato'] ?? ''))
        ? 'La famiglia ha scelto consegna cartacea per: ' . $types[$tipo] . '. Deve reinviare la conferma.'
        : null;
    dbExec("
        UPDATE iscrizioni_prime_pratiche SET
            stato = " . dbQ($nextPracticeState) . ",
            novita_segreteria_at = " . ($newsMessage ? 'NOW()' : 'novita_segreteria_at') . ",
            novita_segreteria_messaggio = " . ($newsMessage ? dbQ($newsMessage) : 'novita_segreteria_messaggio') . ",
            updated_at = NOW()
        WHERE id = " . intval($pratica['id']) . "
        LIMIT 1
    ");
    if ($newsMessage) {
        iscrizioniPrimeRecordEvent((int)$pratica['id'], 'allegati_modificati', 'Scelta consegna cartacea dopo invio', [
            'messaggio' => $newsMessage,
            'created_by' => 'Famiglia',
            'dettagli' => [
                'documento' => $types[$tipo],
                'modalita' => 'consegna cartacea',
            ],
        ]);
    }

    return [
        'ok' => true,
        'message' => $types[$tipo] . ': registrata consegna cartacea in segreteria didattica.',
        'document' => [
            'tipo_documento' => $tipo,
            'stato' => 'consegna_cartacea',
            'original_name' => 'Consegna cartacea in segreteria didattica',
        ],
    ];
}

function iscrizioniPrimeSubmitByToken(string $token, array $data): array
{
    $saved = iscrizioniPrimeSaveDraftByToken($token, $data);
    if (!$saved['ok']) {
        return $saved;
    }

    $pratica = iscrizioniPrimeGetByToken($token);
    if (!$pratica) {
        return ['ok' => false, 'message' => 'Link non valido, scaduto o pratica non disponibile.'];
    }

    $confirmed = [];
    if (!empty($pratica['dati_confermati_json'])) {
        $decoded = json_decode((string)$pratica['dati_confermati_json'], true);
        if (is_array($decoded)) {
            $confirmed = $decoded;
        }
    }

    if (empty($confirmed['privacy_confermata'])) {
        return ['ok' => false, 'message' => 'Prima di inviare devi confermare che i dati indicati sono corretti o aggiornati.'];
    }

    if (iscrizioniPrimeTipoIscrizioneFromPratica($pratica) === 'terze') {
        if (empty($confirmed['nulla_osta_richiesto']) || empty($confirmed['nulla_osta_data'])) {
            return ['ok' => false, 'message' => 'Prima di inviare devi confermare di aver richiesto il nulla osta alla scuola di provenienza e indicare la data della richiesta.'];
        }
        if (!in_array((string)($confirmed['carenze_formative_dichiarate'] ?? ''), ['si', 'no'], true)) {
            return ['ok' => false, 'message' => 'Prima di inviare devi indicare se sono presenti carenze formative.'];
        }
        if ((string)$confirmed['carenze_formative_dichiarate'] === 'si') {
            $materie = (array)($confirmed['carenze_formative_materie'] ?? []);
            $altro = trim((string)($confirmed['carenze_formative_altro'] ?? ''));
            if (!$materie && $altro === '') {
                return ['ok' => false, 'message' => 'Hai indicato che sono presenti carenze formative: seleziona almeno una materia oppure scegli Altro e scrivi la materia.'];
            }
        }
    }

    $documents = iscrizioniPrimeDocumentsForPratica((int)$pratica['id']);
    $byType = [];
    foreach ($documents as $document) {
        $byType[(string)$document['tipo_documento']] = $document;
    }

    $labels = iscrizioniPrimeDocumentTypes($pratica);
    $missing = [];
    foreach (iscrizioniPrimeRequiredDocumentTypes($pratica, $confirmed) as $tipoDocumento) {
        $document = $byType[$tipoDocumento] ?? null;
        $stato = (string)($document['stato'] ?? 'mancante');
        if (!in_array($stato, ['caricato', 'consegna_cartacea', 'estratto', 'verificato'], true)) {
            $missing[] = $labels[$tipoDocumento] ?? $tipoDocumento;
        }
    }

    if ($missing) {
        return [
            'ok' => false,
            'message' => 'Prima di inviare devi caricare o segnare come consegna cartacea questi documenti: ' . implode(', ', $missing) . '.',
        ];
    }

    dbExec("
        UPDATE iscrizioni_prime_pratiche SET
            stato = 'inviata',
            novita_segreteria_at = NOW(),
            novita_segreteria_messaggio = " . dbQ(iscrizioniPrimeIsReceivedBySecretaryState((string)($pratica['stato'] ?? '')) ? 'La famiglia ha reinviato la conferma dati iscrizione.' : 'La famiglia ha inviato la conferma dati iscrizione.') . ",
            updated_at = NOW()
        WHERE id = " . intval($pratica['id']) . "
        LIMIT 1
    ");

    iscrizioniPrimeRecordEvent((int)$pratica['id'], 'invio_famiglia', 'Conferma dati inviata dalla famiglia', [
        'stato_precedente' => (string)($pratica['stato'] ?? ''),
        'stato_nuovo' => 'inviata',
        'created_by' => 'Famiglia',
    ]);

    $pratica = dbGetFirst("SELECT * FROM iscrizioni_prime_pratiche WHERE id = " . intval($pratica['id']) . " LIMIT 1") ?: $pratica;
    $pratica['stato'] = 'inviata';
    $syncGestore = ['ok' => true, 'skipped' => true];
    try {
        $syncGestore = iscrizioniPrimeSyncGestoreStudentAndParents($pratica);
    } catch (Throwable $e) {
        $syncGestore = ['ok' => false, 'message' => $e->getMessage()];
        warning('[iscrizioni] errore sincronizzazione anagrafica GestOre pratica ID ' . intval($pratica['id']) . ': ' . $e->getMessage());
    }

    $mail = iscrizioniPrimeSendSubmissionConfirmation($pratica);
    $message = 'Domanda inviata. I dati e i documenti sono stati registrati.';
    if (empty($syncGestore['ok'])) {
        $message .= ' Attenzione: non e\' stato possibile creare automaticamente l\'anagrafica studente/genitori in GestOre. La segreteria dovra verificarla.';
    }
    if (!empty($mail['ok'])) {
        $message .= ' Abbiamo inviato una mail di conferma ai genitori e alla segreteria.';
    } else {
        $message .= ' Attenzione: la registrazione e\' riuscita, ma non e\' stato possibile inviare la mail di conferma. La segreteria e\' stata avvisata se e\' configurata la mail di emergenza.';
    }

    return ['ok' => true, 'message' => $message, 'stato' => 'inviata', 'mail' => $mail, 'sync_gestore' => $syncGestore];
}

function iscrizioniPrimeDocumentFileByToken(string $token, string $tipo): ?array
{
    $pratica = iscrizioniPrimeGetByToken($token);
    if (!$pratica) {
        return null;
    }

    $types = iscrizioniPrimeDocumentTypes($pratica);
    if (!isset($types[$tipo])) {
        return null;
    }

    $document = dbGetFirst("
        SELECT *
        FROM iscrizioni_prime_documenti
        WHERE pratica_id = " . intval($pratica['id']) . "
          AND tipo_documento = " . dbQ($tipo) . "
          AND stato <> 'mancante'
          AND (file_path IS NOT NULL OR drive_file_id IS NOT NULL)
        LIMIT 1
    ");

    if (!$document) {
        return null;
    }

    if (!empty($document['file_path'])) {
        $absolute = realpath(__DIR__ . '/../' . $document['file_path']);
        $base = realpath(iscrizioniPrimeUploadBaseDir() . '/iscrizioni_prime_uploads');
        if ($absolute && $base && strpos($absolute, $base) === 0 && is_file($absolute)) {
            $document['absolute_path'] = $absolute;
            $document['label'] = $types[$tipo];
            return $document;
        }
    }

    $storageType = strtoupper((string)($document['storage_type'] ?? 'LOCAL'));
    if ($storageType === 'DRIVE' && trim((string)($document['drive_file_id'] ?? '')) !== '') {
        $document['label'] = $types[$tipo];
        return $document;
    }

    return null;
}

function iscrizioniPrimeFirstFilled(array $row, array $names): ?string
{
    foreach ($names as $name) {
        $value = trim((string)($row[$name] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return null;
}

function iscrizioniPrimeUpdateContacts(array $anagraficaRows, string $tipoIscrizione = 'prime'): array
{
    $updated = 0;
    $ignored = 0;
    $internalSkipped = 0;
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);

    foreach ($anagraficaRows as $row) {
        $cf = strtoupper(trim((string)($row['CODICE FISCALE'] ?? '')));
        if ($cf === '') {
            $ignored++;
            continue;
        }
        $anno = iscrizioniPrimeNormalizeSchoolYear($row['ANNO SCOLASTICO'] ?? '');
        $annoCondition = $anno !== '' ? " AND " . iscrizioniPrimeSchoolYearWhere('p.anno_scolastico', $anno) : '';

        $internal = dbGetFirst("
            SELECT id
            FROM iscrizioni_prime_pratiche p
            WHERE p.codice_fiscale = " . dbQ($cf) . "
              AND p.tipo_iscrizione = " . dbQ($tipoIscrizione) . "
              AND " . iscrizioniPrimeEffectiveInternalCondition('p') . "
            $annoCondition
            LIMIT 1
        ");
        if ($internal) {
            $internalSkipped++;
            continue;
        }

        $pratica = dbGetFirst("
            SELECT id
            FROM iscrizioni_prime_pratiche p
            WHERE p.codice_fiscale = " . dbQ($cf) . "
              AND p.tipo_iscrizione = " . dbQ($tipoIscrizione) . "
              AND " . iscrizioniPrimeEffectiveExternalCondition('p') . "
            $annoCondition
            LIMIT 1
        ");

        if (!$pratica) {
            $ignored++;
            continue;
        }

        $resp1Phone = iscrizioniPrimeFirstFilled($row, [
            'RESPONSABILE1_TELEFONO CELLULARE',
            'RESPONSABILE1_ALTRO RECAPITO TELEFONICO',
            'RESPONSABILE1_TELEFONO RESIDENZA',
            'RESPONSABILE1_TELEFONO DOMICILIO',
        ]);
        $resp2Phone = iscrizioniPrimeFirstFilled($row, [
            'RESPONSABILE2_TELEFONO CELLULARE',
            'RESPONSABILE2_ALTRO RECAPITO TELEFONICO',
            'RESPONSABILE2_TELEFONO RESIDENZA',
            'RESPONSABILE2_TELEFONO DOMICILIO',
        ]);
        $studentPhone = iscrizioniPrimeFirstFilled($row, [
            'TELEFONO CELLULARE',
            'ALTRO RECAPITO TELEFONICO',
            'TELEFONO RESIDENZA',
            'TELEFONO DOMICILIO',
        ]);

        dbExec("
            UPDATE iscrizioni_prime_pratiche SET
                email_studente = " . dbQ($row['EMAIL'] ?? null) . ",
                telefono_studente = " . dbQ($studentPhone) . ",
                email_genitore_1 = " . dbQ($row['RESPONSABILE1_EMAIL'] ?? null) . ",
                email_genitore_2 = " . dbQ($row['RESPONSABILE2_EMAIL'] ?? null) . ",
                telefono_genitore_1 = " . dbQ($resp1Phone) . ",
                telefono_genitore_2 = " . dbQ($resp2Phone) . ",
                responsabile_1_tipo = " . dbQ($row['RESPONSABILE1_TIPO RAPPORTO'] ?? null) . ",
                responsabile_1_cognome = " . dbQ($row['RESPONSABILE1_COGNOME'] ?? null) . ",
                responsabile_1_nome = " . dbQ($row['RESPONSABILE1_NOME'] ?? null) . ",
                responsabile_1_codice_fiscale = " . dbQ($row['RESPONSABILE1_CODICE FISCALE'] ?? null) . ",
                responsabile_2_tipo = " . dbQ($row['RESPONSABILE2_TIPO RAPPORTO'] ?? null) . ",
                responsabile_2_cognome = " . dbQ($row['RESPONSABILE2_COGNOME'] ?? null) . ",
                responsabile_2_nome = " . dbQ($row['RESPONSABILE2_NOME'] ?? null) . ",
                responsabile_2_codice_fiscale = " . dbQ($row['RESPONSABILE2_CODICE FISCALE'] ?? null) . ",
                raw_anagrafica_json = " . dbQ(iscrizioniPrimeJson($row)) . ",
                updated_at = NOW()
            WHERE id = " . intval($pratica['id']) . "
            LIMIT 1
        ");

        $updated++;
    }

    return ['updated' => $updated, 'ignored' => $ignored, 'internal_skipped' => $internalSkipped];
}

function iscrizioniPrimeStudentIsInternal(string $cf): bool
{
    global $__anno_scolastico_corrente_id;

    $cf = strtoupper(trim($cf));
    if ($cf === '') {
        return false;
    }

    $annoCorrenteId = intval($__anno_scolastico_corrente_id ?? 0);
    if ($annoCorrenteId <= 0) {
        return false;
    }

    $id = dbGetValue("
        SELECT s.id
        FROM studente s
        INNER JOIN studente_frequenta sf
                ON sf.id_studente = s.id
               AND sf.id_anno_scolastico = " . dbI($annoCorrenteId) . "
               AND sf.id_classe <> 0
        INNER JOIN classi c
                ON c.id = sf.id_classe
               AND UPPER(TRIM(c.classe)) NOT IN ('MEDIE', 'EE')
        WHERE s.codice_fiscale = " . dbQ($cf) . "
          AND s.attivo = 1
        LIMIT 1
    ");

    return intval($id) > 0;
}

function iscrizioniPrimeMarkCurrentStudentsAsInternal(string $tipoIscrizione = 'prime'): int
{
    global $__anno_scolastico_corrente_id;

    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);
    $annoCorrenteId = intval($__anno_scolastico_corrente_id ?? 0);
    if ($annoCorrenteId <= 0) {
        return 0;
    }

    $before = intval(dbGetValue("
        SELECT COUNT(*)
        FROM iscrizioni_prime_pratiche
        WHERE tipo_iscrizione = " . dbQ($tipoIscrizione) . "
          AND studente_interno = 1
    ") ?? 0);

    dbExec("
        UPDATE iscrizioni_prime_pratiche p
        INNER JOIN studente s
                ON s.codice_fiscale = p.codice_fiscale
               AND s.attivo = 1
        INNER JOIN studente_frequenta sf
                ON sf.id_studente = s.id
               AND sf.id_anno_scolastico = " . dbI($annoCorrenteId) . "
               AND sf.id_classe <> 0
        INNER JOIN classi c
                ON c.id = sf.id_classe
               AND UPPER(TRIM(c.classe)) NOT IN ('MEDIE', 'EE')
        SET p.studente_interno = 1,
            p.updated_at = NOW()
        WHERE p.tipo_iscrizione = " . dbQ($tipoIscrizione) . "
          AND p.studente_interno = 0
    ");

    $after = intval(dbGetValue("
        SELECT COUNT(*)
        FROM iscrizioni_prime_pratiche
        WHERE tipo_iscrizione = " . dbQ($tipoIscrizione) . "
          AND studente_interno = 1
    ") ?? 0);

    return max(0, $after - $before);
}

function iscrizioniPrimeStudentIdForCurrentYear(string $cf): int
{
    global $__anno_scolastico_corrente_id;

    $cf = strtoupper(trim($cf));
    $annoCorrenteId = intval($__anno_scolastico_corrente_id ?? 0);
    if ($cf === '' || $annoCorrenteId <= 0) {
        return 0;
    }

    return intval(dbGetValue("
        SELECT s.id
        FROM studente s
        INNER JOIN studente_frequenta sf
                ON sf.id_studente = s.id
               AND sf.id_anno_scolastico = " . dbI($annoCorrenteId) . "
               AND sf.id_classe <> 0
        WHERE s.codice_fiscale = " . dbQ($cf) . "
          AND s.attivo = 1
        LIMIT 1
    ") ?? 0);
}

function iscrizioniPrimeCurrentSchoolYearId(): int
{
    global $__anno_scolastico_corrente_id;

    $annoCorrenteId = intval($__anno_scolastico_corrente_id ?? 0);
    if ($annoCorrenteId > 0) {
        return $annoCorrenteId;
    }

    return intval(dbGetValue("SELECT anno_scolastico_id FROM anno_scolastico_corrente LIMIT 1") ?? 0);
}

function iscrizioniPrimeCurrentClassForCf(string $cf, ?int $annoScolasticoId = null): string
{
    $cf = strtoupper(trim($cf));
    $annoScolasticoId = $annoScolasticoId !== null ? intval($annoScolasticoId) : iscrizioniPrimeCurrentSchoolYearId();
    if ($cf === '' || $annoScolasticoId <= 0) {
        return '';
    }

    return trim((string)(dbGetValue("
        SELECT c.classe
        FROM studente s
        INNER JOIN studente_frequenta sf
                ON sf.id_studente = s.id
               AND sf.id_anno_scolastico = " . dbI($annoScolasticoId) . "
               AND sf.id_classe <> 0
        INNER JOIN classi c ON c.id = sf.id_classe
        WHERE UPPER(TRIM(s.codice_fiscale)) = " . dbQ($cf) . "
          AND s.attivo = 1
        ORDER BY sf.id DESC
        LIMIT 1
    ") ?? ''));
}

function iscrizioniPrimeSecondaOutcomeForCf(string $cf, ?int $annoScolasticoId = null): string
{
    $cf = strtoupper(trim($cf));
    $annoScolasticoId = $annoScolasticoId !== null ? intval($annoScolasticoId) : iscrizioniPrimeCurrentSchoolYearId();
    if ($cf === '' || $annoScolasticoId <= 0) {
        return '';
    }

    try {
        return trim((string)(dbGetValue("
            SELECT s.esito_key
            FROM mastercom_tabelloni_scrutini t
            INNER JOIN mastercom_tabelloni_scrutini_studenti s ON s.tabellone_id = t.id
            INNER JOIN studente st ON st.id = s.id_studente_gestore
            WHERE t.id_anno_scolastico = " . dbI($annoScolasticoId) . "
              AND t.periodo = '9'
              AND UPPER(TRIM(st.codice_fiscale)) = " . dbQ($cf) . "
              AND (
                    UPPER(TRIM(t.classe)) LIKE '2%'
                 OR UPPER(TRIM(t.classe_tabellone)) LIKE '2%'
              )
            ORDER BY t.id DESC, s.id DESC
            LIMIT 1
        ") ?? ''));
    } catch (Throwable $e) {
        return '';
    }
}

function iscrizioniPrimeIsPromotedFromSecondaDigitalScience(string $cf): bool
{
    $class = strtoupper(trim(iscrizioniPrimeCurrentClassForCf($cf)));
    if (!preg_match('/^2DS\b/u', $class)) {
        return false;
    }

    $outcome = iscrizioniPrimeSecondaOutcomeForCf($cf);
    return $outcome === '' || in_array($outcome, ['ammesso', 'anno_estero'], true);
}

function iscrizioniPrimeClassIdByCode(string $classe): int
{
    $classe = strtoupper(trim($classe));
    if ($classe === '') {
        return 0;
    }

    $id = intval(dbGetValue("SELECT id FROM classi WHERE UPPER(TRIM(classe)) = " . dbQ($classe) . " LIMIT 1") ?? 0);
    if ($id > 0) {
        return $id;
    }

    $fields = ['classe' => dbQ($classe)];
    if (iscrizioniPrimeTableColumnExists('classi', 'anno')) {
        $fields['anno'] = '0';
    }
    if (iscrizioniPrimeTableColumnExists('classi', 'attiva')) {
        $fields['attiva'] = '1';
    }

    dbExec("INSERT INTO classi (`" . implode('`, `', array_keys($fields)) . "`) VALUES (" . implode(', ', array_values($fields)) . ")");
    return intval(dblastId());
}

function iscrizioniPrimeTableColumnExists(string $table, string $column): bool
{
    return intval(dbGetValue("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = " . dbQ($table) . "
          AND COLUMN_NAME = " . dbQ($column) . "
    ") ?? 0) > 0;
}

function iscrizioniPrimeRelazioneId(string $tipo): int
{
    $tipo = strtolower(trim($tipo));
    $map = [
        'padre' => 'padre',
        'madre' => 'madre',
        'tutore' => 'tutore',
        'tutrice' => 'tutore',
        'affidatario' => 'affidatario',
        'affidataria' => 'affidataria',
        'genitore' => 'genitore',
        'responsabile' => 'genitore',
    ];
    $relazione = $map[$tipo] ?? $tipo;

    $id = intval(dbGetValue("SELECT id FROM genitori_relazioni WHERE LOWER(TRIM(relazione)) = " . dbQ($relazione) . " LIMIT 1") ?? 0);
    if ($id > 0) {
        return $id;
    }

    return intval(dbGetValue("SELECT id FROM genitori_relazioni WHERE LOWER(TRIM(relazione)) = 'genitore' LIMIT 1") ?? 0);
}

function iscrizioniPrimeGestoreStudentActiveFromPratica(array $pratica): int
{
    $stato = (string)($pratica['stato'] ?? '');
    return in_array($stato, ['inviata', 'verifica_iniziale_ok', 'da_integrare', 'verificata'], true) ? 1 : 0;
}

function iscrizioniPrimeUpsertGestoreStudent(array $pratica, ?int $attivo = null): int
{
    $cf = strtoupper(trim((string)($pratica['codice_fiscale'] ?? '')));
    if ($cf === '') {
        return 0;
    }

    $sesso = strtoupper(trim((string)($pratica['sesso'] ?? '')));
    if (!in_array($sesso, ['M', 'F'], true)) {
        $sesso = null;
    }
    $email = iscrizioniPrimeTrimValue($pratica['email_studente'] ?? null);
    $studenteId = intval(dbGetValue("SELECT id FROM studente WHERE codice_fiscale = " . dbQ($cf) . " LIMIT 1") ?? 0);
    $attivo = $attivo === null ? iscrizioniPrimeGestoreStudentActiveFromPratica($pratica) : ($attivo ? 1 : 0);

    if ($studenteId > 0) {
        dbExec("
            UPDATE studente SET
                cognome = " . dbQ($pratica['cognome'] ?? '') . ",
                nome = " . dbQ($pratica['nome'] ?? '') . ",
                email = " . dbQ($email) . ",
                codice_fiscale = " . dbQ($cf) . ",
                sesso = " . dbQ($sesso) . ",
                attivo = " . dbI($attivo) . "
            WHERE id = " . dbI($studenteId) . "
            LIMIT 1
        ");
        return $studenteId;
    }

    dbExec("
        INSERT INTO studente (cognome, nome, email, username, codice_fiscale, sesso, attivo)
        VALUES (
            " . dbQ($pratica['cognome'] ?? '') . ",
            " . dbQ($pratica['nome'] ?? '') . ",
            " . dbQ($email) . ",
            '',
            " . dbQ($cf) . ",
            " . dbQ($sesso) . ",
            " . dbI($attivo) . "
        )
    ");

    return intval(dblastId());
}

function iscrizioniPrimeSyncGestoreStudentFromPractice(array $pratica, ?int $attivo = null): array
{
    if (!empty($pratica['studente_interno'])) {
        return ['ok' => true, 'skipped' => true, 'message' => 'Studente interno: anagrafica GestOre gia presente.'];
    }

    $tipoIscrizione = iscrizioniPrimeTipoIscrizioneFromPratica($pratica);
    $classeProvvisoria = $tipoIscrizione === 'terze' ? 'EE' : 'MEDIE';
    $studenteId = iscrizioniPrimeUpsertGestoreStudent($pratica, $attivo);
    if ($studenteId <= 0) {
        return ['ok' => false, 'message' => 'Codice fiscale studente mancante: impossibile creare anagrafica GestOre.'];
    }

    iscrizioniPrimeUpsertGestoreFrequency($studenteId, $classeProvvisoria);

    return [
        'ok' => true,
        'skipped' => false,
        'studente_id' => $studenteId,
        'classe' => $classeProvvisoria,
        'attivo' => $attivo === null ? iscrizioniPrimeGestoreStudentActiveFromPratica($pratica) : ($attivo ? 1 : 0),
    ];
}

function iscrizioniPrimeUpsertGestoreFrequency(int $studenteId, string $classeCode): void
{
    $annoCorrenteId = iscrizioniPrimeCurrentSchoolYearId();
    $classeId = iscrizioniPrimeClassIdByCode($classeCode);
    if ($studenteId <= 0 || $annoCorrenteId <= 0 || $classeId <= 0) {
        warning('[iscrizioni] impossibile creare frequenza provvisoria studente=' . $studenteId . ' anno=' . $annoCorrenteId . ' classe=' . $classeCode . ' id_classe=' . $classeId);
        return;
    }

    dbExec("
        INSERT INTO studente_frequenta (id_studente, id_anno_scolastico, id_classe)
        VALUES (" . dbI($studenteId) . ", " . dbI($annoCorrenteId) . ", " . dbI($classeId) . ")
        ON DUPLICATE KEY UPDATE id_classe = VALUES(id_classe)
    ");
}

function iscrizioniPrimeUpsertGestoreParent(array $parent, int $studenteId): int
{
    $cf = strtoupper(trim((string)($parent['codice_fiscale'] ?? '')));
    $email = iscrizioniPrimeTrimValue($parent['email'] ?? null);
    $cognome = trim((string)($parent['cognome'] ?? ''));
    $nome = trim((string)($parent['nome'] ?? ''));

    if ($studenteId <= 0 || ($cf === '' && $email === '') || ($cognome === '' && $nome === '')) {
        return 0;
    }

    $where = $cf !== ''
        ? "codice_fiscale = " . dbQ($cf)
        : "LOWER(TRIM(email)) = " . dbQ(strtolower((string)$email));

    $genitoreId = intval(dbGetValue("SELECT id FROM genitori WHERE $where LIMIT 1") ?? 0);
    $username = $email !== null ? $email : '';

    if ($genitoreId > 0) {
        dbExec("
            UPDATE genitori SET
                cognome = " . dbQ($cognome) . ",
                nome = " . dbQ($nome) . ",
                email = " . dbQ($email) . ",
                codice_fiscale = " . dbQ($cf) . ",
                username = " . dbQ($username) . ",
                attivo = 1
            WHERE id = " . dbI($genitoreId) . "
            LIMIT 1
        ");
    } else {
        dbExec("
            INSERT INTO genitori (cognome, nome, email, codice_fiscale, username, attivo, last_login, last_IP)
            VALUES (" . dbQ($cognome) . ", " . dbQ($nome) . ", " . dbQ($email) . ", " . dbQ($cf) . ", " . dbQ($username) . ", 1, '', '')
        ");
        $genitoreId = intval(dblastId());
    }

    $relazioneId = iscrizioniPrimeRelazioneId((string)($parent['tipo'] ?? 'genitore'));
    if ($relazioneId > 0) {
        dbExec("
            INSERT INTO genitori_studenti (id_genitore, id_studente, id_relazione)
            VALUES (" . dbI($genitoreId) . ", " . dbI($studenteId) . ", " . dbI($relazioneId) . ")
            ON DUPLICATE KEY UPDATE id_relazione = VALUES(id_relazione)
        ");
    }

    return $genitoreId;
}

function iscrizioniPrimeSyncGestoreStudentAndParents(array $pratica): array
{
    if (!empty($pratica['studente_interno'])) {
        return ['ok' => true, 'skipped' => true, 'message' => 'Studente interno: anagrafica GestOre gia presente.'];
    }

    $syncStudent = iscrizioniPrimeSyncGestoreStudentFromPractice($pratica);
    if (empty($syncStudent['ok'])) {
        return $syncStudent;
    }
    $studenteId = intval($syncStudent['studente_id'] ?? 0);

    $parents = [
        [
            'tipo' => $pratica['responsabile_1_tipo'] ?? 'genitore',
            'cognome' => $pratica['responsabile_1_cognome'] ?? '',
            'nome' => $pratica['responsabile_1_nome'] ?? '',
            'codice_fiscale' => $pratica['responsabile_1_codice_fiscale'] ?? '',
            'email' => $pratica['email_genitore_1'] ?? '',
        ],
        [
            'tipo' => $pratica['responsabile_2_tipo'] ?? 'genitore',
            'cognome' => $pratica['responsabile_2_cognome'] ?? '',
            'nome' => $pratica['responsabile_2_nome'] ?? '',
            'codice_fiscale' => $pratica['responsabile_2_codice_fiscale'] ?? '',
            'email' => $pratica['email_genitore_2'] ?? '',
        ],
    ];

    $parentIds = [];
    foreach ($parents as $parent) {
        $parentId = iscrizioniPrimeUpsertGestoreParent($parent, $studenteId);
        if ($parentId > 0) {
            $parentIds[] = $parentId;
        }
    }

    info('[iscrizioni] sincronizzata anagrafica GestOre studente id=' . $studenteId . ' classe=' . (string)($syncStudent['classe'] ?? '') . ' genitori=' . implode(',', $parentIds));

    return [
        'ok' => true,
        'skipped' => false,
        'studente_id' => $studenteId,
        'classe' => (string)($syncStudent['classe'] ?? ''),
        'attivo' => intval($syncStudent['attivo'] ?? 0),
        'genitori_ids' => $parentIds,
    ];
}

function iscrizioniPrimeApplyInternalContacts(int $praticaId, string $cf): bool
{
    $studentId = iscrizioniPrimeStudentIdForCurrentYear($cf);
    if ($praticaId <= 0 || $studentId <= 0) {
        return false;
    }

    $student = dbGetFirst("
        SELECT email
        FROM studente
        WHERE id = " . dbI($studentId) . "
        LIMIT 1
    ") ?: [];

    $parents = dbGetAll("
        SELECT
            g.cognome,
            g.nome,
            g.email,
            g.codice_fiscale,
            gr.relazione
        FROM genitori_studenti gs
        INNER JOIN genitori g
                ON g.id = gs.id_genitore
               AND g.attivo = 1
        LEFT JOIN genitori_relazioni gr
               ON gr.id = gs.id_relazione
        WHERE gs.id_studente = " . dbI($studentId) . "
        ORDER BY
            CASE LOWER(COALESCE(gr.relazione, ''))
                WHEN 'padre' THEN 1
                WHEN 'madre' THEN 2
                WHEN 'tutore' THEN 3
                ELSE 4
            END,
            g.cognome ASC,
            g.nome ASC
        LIMIT 2
    ");

    $p1 = $parents[0] ?? [];
    $p2 = $parents[1] ?? [];

    dbExec("
        UPDATE iscrizioni_prime_pratiche SET
            email_studente = " . dbQ($student['email'] ?? null) . ",
            email_genitore_1 = " . dbQ($p1['email'] ?? null) . ",
            responsabile_1_tipo = " . dbQ($p1['relazione'] ?? null) . ",
            responsabile_1_cognome = " . dbQ($p1['cognome'] ?? null) . ",
            responsabile_1_nome = " . dbQ($p1['nome'] ?? null) . ",
            responsabile_1_codice_fiscale = " . dbQ($p1['codice_fiscale'] ?? null) . ",
            email_genitore_2 = " . dbQ($p2['email'] ?? null) . ",
            responsabile_2_tipo = " . dbQ($p2['relazione'] ?? null) . ",
            responsabile_2_cognome = " . dbQ($p2['cognome'] ?? null) . ",
            responsabile_2_nome = " . dbQ($p2['nome'] ?? null) . ",
            responsabile_2_codice_fiscale = " . dbQ($p2['codice_fiscale'] ?? null) . ",
            updated_at = NOW()
        WHERE id = " . dbI($praticaId) . "
        LIMIT 1
    ");

    return true;
}

function iscrizioniPrimeUpsert(array $prime, ?array $dsa, string $tipoIscrizione = 'prime', ?bool $studenteInterno = null, ?array $licenzaMedia = null): array
{
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);
    $cf = strtoupper(trim((string)iscrizioniPrimeField($prime, ['CODICE FISCALE STUDENTE', 'CODICE FISCALE', 'CHIAVE FISCALE'], $dsa['CODICE FISCALE'] ?? '')));
    $anno = iscrizioniPrimeNormalizeSchoolYear(iscrizioniPrimeField($prime, ['ANNO SCOLASTICO'], $dsa['ANNO SCOLASTICO'] ?? ''));

    if ($cf === '' || $anno === '') {
        return ['ok' => false, 'error' => 'codice fiscale o anno scolastico mancante'];
    }

    $existing = dbGetFirst("
        SELECT id, token_hash
        FROM iscrizioni_prime_pratiche
        WHERE " . iscrizioniPrimeSchoolYearWhere('anno_scolastico', $anno) . "
          AND tipo_iscrizione = " . dbQ($tipoIscrizione) . "
          AND codice_fiscale = " . dbQ($cf) . "
        ORDER BY (anno_scolastico = " . dbQ($anno) . ") DESC, id DESC
        LIMIT 1
    ");

    if (!$existing) {
        $sameCfOtherAnno = dbGetFirst("
            SELECT id, anno_scolastico
            FROM iscrizioni_prime_pratiche
            WHERE tipo_iscrizione = " . dbQ($tipoIscrizione) . "
              AND codice_fiscale = " . dbQ($cf) . "
            ORDER BY updated_at DESC, id DESC
            LIMIT 1
        ");
        if ($sameCfOtherAnno) {
            warning('[iscrizioni] nuova pratica con stesso codice fiscale ma anno diverso tipo=' . $tipoIscrizione . ' cf=' . $cf . ' anno_nuovo=' . $anno . ' anno_esistente=' . (string)$sameCfOtherAnno['anno_scolastico'] . ' id_esistente=' . intval($sameCfOtherAnno['id']));
        }
    }

    $token = null;
    if (!$existing || trim((string)($existing['token_hash'] ?? '')) === '') {
        $token = iscrizioniPrimeGenerateToken();
    }

    $fields = [
        'anno_scolastico' => $anno,
        'tipo_iscrizione' => $tipoIscrizione,
        'studente_interno' => $studenteInterno === null ? 0 : ($studenteInterno ? 1 : 0),
        'codice_domanda' => iscrizioniPrimeField($prime, ['CODICE DOMANDA']),
        'codice_sidi' => iscrizioniPrimeField($prime, ['CODICE SIDI'], $dsa['CODICE SIDI'] ?? null),
        'codice_giada' => $dsa['CODICE GIADA'] ?? null,
        'codice_fiscale' => $cf,
        'cognome' => iscrizioniPrimeField($prime, ['COGNOME STUDENTE', 'COGNOME'], $dsa['COGNOME'] ?? ''),
        'nome' => iscrizioniPrimeField($prime, ['NOME STUDENTE', 'NOME'], $dsa['NOME'] ?? ''),
        'sesso' => iscrizioniPrimeField($prime, ['SESSO'], $dsa['SESSO'] ?? null),
        'data_nascita' => iscrizioniPrimeDate((string)iscrizioniPrimeField($prime, ['DATA NASCITA STUDENTE', 'DATA NASCITA'], $dsa['DATA NASCITA'] ?? '')),
        'nazione_nascita' => iscrizioniPrimeField($prime, ['NAZIONE NASCITA']),
        'provincia_nascita' => iscrizioniPrimeField($prime, ['PROVINCIA NASCITA']),
        'comune_nascita' => iscrizioniPrimeField($prime, ['COMUNE NASCITA']),
        'luogo_nascita' => iscrizioniPrimeField($prime, ['LUOGO NASCITA']),
        'cittadinanza' => iscrizioniPrimeField($prime, ['CITTADINANZA']),
        'nazione_residenza' => iscrizioniPrimeField($prime, ['NAZIONE RESIDENZA']),
        'provincia_residenza' => iscrizioniPrimeField($prime, ['PROVINCIA RESIDENZA']),
        'sigla_provincia_residenza' => iscrizioniPrimeField($prime, ['SIGLA PROV. RESIDENZA']),
        'comune_residenza' => iscrizioniPrimeField($prime, ['COMUNE RESIDENZA']),
        'frazione_residenza' => iscrizioniPrimeField($prime, ['FRAZIONE RESIDENZA']),
        'cap_residenza' => iscrizioniPrimeField($prime, ['CAP RESIDENZA']),
        'indirizzo_residenza' => iscrizioniPrimeField($prime, ['INDIRIZZO RESIDENZA']),
        'telefono_residenza' => iscrizioniPrimeField($prime, ['TEL. RESIDENZA']),
        'scuola_provenienza' => iscrizioniPrimeField($licenzaMedia ?? [], ['DENOMINAZIONE ISTITUZIONE SCOLASTICA']),
        'anno_esame_licenza' => iscrizioniPrimeField($licenzaMedia ?? [], ['ANNO ESAME']),
        'esito_esame_licenza' => iscrizioniPrimeField($licenzaMedia ?? [], ['ESITO']),
        'voto_esame_licenza' => iscrizioniPrimeField($licenzaMedia ?? [], ['VOTO']),
        'sezione_richiesta' => iscrizioniPrimeField($prime, ['SEZIONE']),
        'lingua_straniera_1' => iscrizioniPrimeField($prime, ['LINGUA STRANIERA 1']),
        'lingua_straniera_2' => iscrizioniPrimeField($prime, ['LINGUA STRANIERA 2']),
        'lingua_straniera_3' => iscrizioniPrimeField($prime, ['LINGUA STRANIERA 3']),
        'trattamento_immagini' => iscrizioniPrimeField($prime, ['TRATTAMENTO IMMAGINI']),
        'esami_integrativi_da_verificare' => ($tipoIscrizione === 'terze' && $studenteInterno === false) ? 1 : 0,
        'unita_scolastica' => iscrizioniPrimeField($prime, ['UNITA SCOLASTICA DI ISCRIZIONE', 'UNITA SCOLASTICA'], $dsa['UNITA SCOLASTICA'] ?? null),
        'corso_studi' => iscrizioniPrimeField($prime, ['CORSO DI STUDI DI ISCRIZIONE', 'CORSO DI STUDI', 'CORSO STUDI'], $dsa['CORSO STUDI'] ?? null),
        'id_indirizzo_gestore' => intval($prime['id_indirizzo_gestore'] ?? 0) ?: null,
        'anno_corso' => iscrizioniPrimeField($prime, ['ANNO DI CORSO', 'ANNO CORSO'], $dsa['ANNO CORSO'] ?? null),
        'mensa' => iscrizioniPrimeField($prime, ['MENSA'], $dsa['MENSA'] ?? null),
        'religione' => iscrizioniPrimeField($prime, ['RELIGIONE'], $dsa['RELIGIONE'] ?? null),
        'scelta_alternativa_religione' => iscrizioniPrimeField($prime, ['SCELTA ALTERNATIVA RELIGIONE', 'RELIGIONE ALTERNATIVA'], $dsa['SCELTA ALTERNATIVA RELIGIONE'] ?? null),
        'richiesta_trasporto' => iscrizioniPrimeField($prime, ['RICHIESTA TRASPORTO', 'TRASPORTI'], $dsa['RICHIESTA TRASPORTO'] ?? null),
        'scelta_formativa' => iscrizioniPrimeField($prime, ['SCELTA FORMATIVA'], $dsa['SCELTA FORMATIVA'] ?? null),
        'certificazione_online' => $dsa['DICHIARAZIONE CERTIFICAZIONE ONLINE'] ?? ($dsa['COLONNA_52'] ?? null),
        'email_studente' => iscrizioniPrimeField($prime, ['EMAIL']),
        'telefono_studente' => iscrizioniPrimeField($prime, ['TELEFONO CELLULARE', 'TEL. RESIDENZA']),
        'raw_prime_json' => iscrizioniPrimeJson($prime),
        'raw_dsa_json' => $dsa ? iscrizioniPrimeJson($dsa) : null,
        'raw_licenza_media_json' => $licenzaMedia ? iscrizioniPrimeJson($licenzaMedia) : null,
    ];
    if ($tipoIscrizione === 'terze' && iscrizioniPrimeIsPromotedFromSecondaDigitalScience($cf)) {
        $digitalScienceAddressId = iscrizioniPrimeGestoreAddressIdFromText('DIGITAL SCIENCE');
        if ($digitalScienceAddressId > 0) {
            $fields['id_indirizzo_gestore'] = $digitalScienceAddressId;
        }
        $fields['corso_studi'] = 'DIGITAL SCIENCE';
        $fields['scelta_formativa'] = 'DIGITAL SCIENCE';
    }
    $resolvedAddressId = iscrizioniPrimeGestoreAddressIdFromPractice($fields);
    if ($resolvedAddressId > 0) {
        $fields['id_indirizzo_gestore'] = $resolvedAddressId;
    }

    if ($existing) {
        $sets = [];
        foreach ($fields as $field => $value) {
            $sets[] = "$field = " . dbQ($value);
        }
        $sets[] = "updated_at = NOW()";

        if ($token !== null) {
            $sets[] = "token_hash = " . dbQ($token['hash']);
            $sets[] = "token_last4 = " . dbQ($token['last4']);
            $sets[] = "token_created_at = NOW()";
            $sets[] = "token_expires_at = DATE_ADD(NOW(), INTERVAL 90 DAY)";
        }

        $id = intval($existing['id']);
        dbExec("UPDATE iscrizioni_prime_pratiche SET " . implode(', ', $sets) . " WHERE id = $id LIMIT 1");
        iscrizioniPrimeEnsureDocumentRows($id, $fields);
        if ($tipoIscrizione === 'terze' && !empty($fields['studente_interno'])) {
            iscrizioniPrimeApplyInternalContacts($id, $cf);
        }
        $praticaSync = dbGetFirst("SELECT * FROM iscrizioni_prime_pratiche WHERE id = " . dbI($id) . " LIMIT 1") ?: array_merge($fields, ['id' => $id]);
        iscrizioniPrimeSyncGestoreStudentFromPractice($praticaSync);
        studentiAttrSyncFromDsaCsvRow($cf, $dsa, $tipoIscrizione . ':' . $id);
        $movimentiLink = iscrizioniPrimeLinkExistingEntrataMovimenti($id, $fields);

        return [
            'ok' => true,
            'inserted' => false,
            'id' => $id,
            'token' => $token['plain'] ?? null,
            'codice_fiscale' => $cf,
            'studente' => trim((string)($fields['cognome'] ?? '') . ' ' . (string)($fields['nome'] ?? '')),
            'anno_scolastico' => $anno,
            'corso_studi' => (string)($fields['corso_studi'] ?? ''),
            'movimenti_entrata_link' => $movimentiLink,
        ];
    }

    $columns = array_keys($fields);
    $values = array_map(fn($value) => dbQ($value), array_values($fields));

    if ($token !== null) {
        $columns = array_merge($columns, ['token_hash', 'token_last4', 'token_created_at', 'token_expires_at']);
        $values = array_merge($values, [dbQ($token['hash']), dbQ($token['last4']), 'NOW()', 'DATE_ADD(NOW(), INTERVAL 90 DAY)']);
    }

    $columns = array_merge($columns, ['imported_at', 'updated_at']);
    $values = array_merge($values, ['NOW()', 'NOW()']);

    dbExec("INSERT INTO iscrizioni_prime_pratiche (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")");
    $id = dblastId();

    iscrizioniPrimeEnsureDocumentRows((int)$id, $fields);
    if ($tipoIscrizione === 'terze' && !empty($fields['studente_interno'])) {
        iscrizioniPrimeApplyInternalContacts((int)$id, $cf);
    }
    $praticaSync = dbGetFirst("SELECT * FROM iscrizioni_prime_pratiche WHERE id = " . dbI($id) . " LIMIT 1") ?: array_merge($fields, ['id' => intval($id)]);
    iscrizioniPrimeSyncGestoreStudentFromPractice($praticaSync);
    studentiAttrSyncFromDsaCsvRow($cf, $dsa, $tipoIscrizione . ':' . intval($id));
    $movimentiLink = iscrizioniPrimeLinkExistingEntrataMovimenti((int)$id, $fields);

    info('[iscrizioni] pratica inserita tipo=' . $tipoIscrizione . ' id=' . intval($id) . ' cf=' . $cf . ' anno=' . $anno . ' interno=' . intval(!empty($fields['studente_interno'])));

    return [
        'ok' => true,
        'inserted' => true,
        'id' => intval($id),
        'token' => $token['plain'] ?? null,
        'codice_fiscale' => $cf,
        'studente' => trim((string)($fields['cognome'] ?? '') . ' ' . (string)($fields['nome'] ?? '')),
        'anno_scolastico' => $anno,
        'corso_studi' => (string)($fields['corso_studi'] ?? ''),
        'movimenti_entrata_link' => $movimentiLink,
    ];
}

function iscrizioniPrimeLinkExistingEntrataMovimenti(int $praticaId, array $fields): array
{
    $stats = ['linked' => 0, 'already_linked' => 0, 'conflicts' => 0, 'details' => [], 'conflict_details' => []];
    $cf = strtoupper(trim((string)($fields['codice_fiscale'] ?? '')));
    if ($praticaId <= 0 || $cf === '') {
        return $stats;
    }

    $tableExists = intval(dbGetValue("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'studenti_movimenti_pratiche'
    ")) > 0;
    if (!$tableExists) {
        return $stats;
    }

    $linkColumnExists = intval(dbGetValue("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'studenti_movimenti_pratiche'
          AND COLUMN_NAME = 'id_pratica_iscrizione'
    ")) > 0;
    if (!$linkColumnExists) {
        return $stats;
    }

    $movimenti = dbGetAll("
        SELECT id, cognome, nome, codice_fiscale, classe_origine, classe_richiesta, stato_pratica, id_pratica_iscrizione
        FROM studenti_movimenti_pratiche
        WHERE tipo_pratica = 'entrata'
          AND stato_pratica <> 'annullata'
          AND codice_fiscale IS NOT NULL
          AND codice_fiscale <> ''
          AND UPPER(TRIM(codice_fiscale)) = " . dbQ($cf) . "
        ORDER BY id DESC
    ") ?: [];

    foreach ($movimenti as $movimento) {
        $movementId = intval($movimento['id'] ?? 0);
        $linkedPracticeId = intval($movimento['id_pratica_iscrizione'] ?? 0);
        $detail = [
            'id' => $praticaId,
            'studente' => trim((string)($fields['cognome'] ?? '') . ' ' . (string)($fields['nome'] ?? '')),
            'codice_fiscale' => $cf,
            'motivo' => 'movimento entrata #' . $movementId . ' - ' . (string)($movimento['stato_pratica'] ?? ''),
        ];

        if ($movementId <= 0) {
            continue;
        }
        if ($linkedPracticeId === $praticaId) {
            $stats['already_linked']++;
            $detail['motivo'] .= ' gia collegato';
            $stats['details'][] = $detail;
            continue;
        }
        if ($linkedPracticeId > 0 && $linkedPracticeId !== $praticaId) {
            $stats['conflicts']++;
            $detail['motivo'] .= ' gia collegato a pratica #' . $linkedPracticeId;
            $stats['conflict_details'][] = $detail;
            continue;
        }

        dbExec("
            UPDATE studenti_movimenti_pratiche
            SET id_pratica_iscrizione = " . dbI($praticaId) . ",
                updated_at = NOW()
            WHERE id = " . dbI($movementId) . "
              AND (id_pratica_iscrizione IS NULL OR id_pratica_iscrizione = 0)
            LIMIT 1
        ");
        $stats['linked']++;
        $detail['motivo'] .= ' collegato';
        $stats['details'][] = $detail;
    }

    return $stats;
}

function iscrizioniPrimeBackfillEntrataMovimentiLinks(string $tipoIscrizione): array
{
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);
    $stats = ['linked' => 0, 'already_linked' => 0, 'conflicts' => 0, 'details' => [], 'conflict_details' => []];

    $tableExists = intval(dbGetValue("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'studenti_movimenti_pratiche'
    ")) > 0;
    if (!$tableExists) {
        return $stats;
    }

    $rows = dbGetAll("
        SELECT
            p.id,
            p.cognome,
            p.nome,
            p.codice_fiscale,
            p.corso_studi
        FROM studenti_movimenti_pratiche m
        INNER JOIN iscrizioni_prime_pratiche p
            ON UPPER(TRIM(p.codice_fiscale)) = UPPER(TRIM(m.codice_fiscale))
           AND p.tipo_iscrizione = " . dbQ($tipoIscrizione) . "
           AND p.stato <> 'annullata'
        WHERE m.tipo_pratica = 'entrata'
          AND m.stato_pratica <> 'annullata'
          AND (m.id_pratica_iscrizione IS NULL OR m.id_pratica_iscrizione = 0)
          AND p.id = (
              SELECT p2.id
              FROM iscrizioni_prime_pratiche p2
              WHERE p2.tipo_iscrizione = p.tipo_iscrizione
                AND p2.stato <> 'annullata'
                AND UPPER(TRIM(p2.codice_fiscale)) = UPPER(TRIM(p.codice_fiscale))
              ORDER BY p2.updated_at DESC, p2.id DESC
              LIMIT 1
          )
        GROUP BY p.id, p.cognome, p.nome, p.codice_fiscale, p.corso_studi
        ORDER BY p.cognome ASC, p.nome ASC
    ") ?: [];

    foreach ($rows as $row) {
        $result = iscrizioniPrimeLinkExistingEntrataMovimenti(intval($row['id'] ?? 0), $row);
        $stats['linked'] += intval($result['linked'] ?? 0);
        $stats['already_linked'] += intval($result['already_linked'] ?? 0);
        $stats['conflicts'] += intval($result['conflicts'] ?? 0);
        foreach (($result['details'] ?? []) as $detail) {
            if (count($stats['details']) < 80) {
                $stats['details'][] = $detail;
            }
        }
        foreach (($result['conflict_details'] ?? []) as $detail) {
            if (count($stats['conflict_details']) < 80) {
                $stats['conflict_details'][] = $detail;
            }
        }
    }

    return $stats;
}

function iscrizioniPrimeUpdateDsaRows(array $dsaRows, string $tipoIscrizione): array
{
    $stats = ['updated' => 0, 'ignored' => 0, 'updated_details' => [], 'ignored_details' => []];
    foreach ($dsaRows as $row) {
        $cf = strtoupper(trim((string)iscrizioniPrimeField($row, ['CODICE FISCALE STUDENTE', 'CODICE FISCALE', 'CHIAVE FISCALE'], '')));
        if ($cf === '') {
            $stats['ignored']++;
            if (count($stats['ignored_details']) < 40) {
                $stats['ignored_details'][] = ['studente' => trim((string)($row['COGNOME'] ?? '') . ' ' . (string)($row['NOME'] ?? '')), 'motivo' => 'codice fiscale mancante'];
            }
            continue;
        }

        $practice = dbGetFirst("
            SELECT id
            FROM iscrizioni_prime_pratiche
            WHERE tipo_iscrizione = " . dbQ($tipoIscrizione) . "
              AND UPPER(TRIM(codice_fiscale)) = " . dbQ($cf) . "
              AND stato <> 'annullata'
            ORDER BY id DESC
            LIMIT 1
        ");
        if (!$practice) {
            $stats['ignored']++;
            if (count($stats['ignored_details']) < 40) {
                $stats['ignored_details'][] = ['studente' => trim((string)($row['COGNOME'] ?? '') . ' ' . (string)($row['NOME'] ?? '')), 'codice_fiscale' => $cf, 'motivo' => 'pratica non trovata'];
            }
            studentiAttrSyncFromDsaCsvRow($cf, $row, $tipoIscrizione . ':standalone');
            continue;
        }

        dbExec("
            UPDATE iscrizioni_prime_pratiche SET
                codice_sidi = COALESCE(NULLIF(codice_sidi, ''), " . dbQ($row['CODICE SIDI'] ?? null) . "),
                codice_giada = COALESCE(NULLIF(codice_giada, ''), " . dbQ($row['CODICE GIADA'] ?? null) . "),
                certificazione_online = " . dbQ($row['DICHIARAZIONE CERTIFICAZIONE ONLINE'] ?? ($row['COLONNA_52'] ?? null)) . ",
                raw_dsa_json = " . dbQ(iscrizioniPrimeJson($row)) . ",
                updated_at = NOW()
            WHERE id = " . dbI($practice['id'] ?? 0) . "
            LIMIT 1
        ");
        studentiAttrSyncFromDsaCsvRow($cf, $row, $tipoIscrizione . ':' . intval($practice['id'] ?? 0));
        $stats['updated']++;
        if (count($stats['updated_details']) < 40) {
            $stats['updated_details'][] = ['id' => intval($practice['id'] ?? 0), 'studente' => trim((string)($row['COGNOME'] ?? '') . ' ' . (string)($row['NOME'] ?? '')), 'codice_fiscale' => $cf];
        }
    }
    return $stats;
}

function iscrizioniPrimeUpdateLicenzaMediaRows(array $licenzaMediaRows, string $tipoIscrizione): array
{
    $stats = ['updated' => 0, 'ignored' => 0, 'updated_details' => [], 'ignored_details' => []];
    foreach ($licenzaMediaRows as $row) {
        $cf = strtoupper(trim((string)iscrizioniPrimeField($row, ['CODICE FISCALE STUDENTE', 'CODICE FISCALE', 'CHIAVE FISCALE'], '')));
        if ($cf === '') {
            $stats['ignored']++;
            if (count($stats['ignored_details']) < 40) {
                $stats['ignored_details'][] = ['studente' => trim((string)($row['COGNOME'] ?? '') . ' ' . (string)($row['NOME'] ?? '')), 'motivo' => 'codice fiscale mancante'];
            }
            continue;
        }
        $practice = dbGetFirst("
            SELECT id
            FROM iscrizioni_prime_pratiche
            WHERE tipo_iscrizione = " . dbQ($tipoIscrizione) . "
              AND UPPER(TRIM(codice_fiscale)) = " . dbQ($cf) . "
              AND stato <> 'annullata'
            ORDER BY id DESC
            LIMIT 1
        ");
        if (!$practice) {
            $stats['ignored']++;
            if (count($stats['ignored_details']) < 40) {
                $stats['ignored_details'][] = ['studente' => trim((string)($row['COGNOME'] ?? '') . ' ' . (string)($row['NOME'] ?? '')), 'codice_fiscale' => $cf, 'motivo' => 'pratica non trovata'];
            }
            continue;
        }
        dbExec("
            UPDATE iscrizioni_prime_pratiche SET
                scuola_provenienza = " . dbQ(iscrizioniPrimeField($row, ['DENOMINAZIONE ISTITUZIONE SCOLASTICA', 'SCUOLA DI PROVENIENZA'])) . ",
                anno_esame_licenza = " . dbQ(iscrizioniPrimeField($row, ['ANNO ESAME'])) . ",
                esito_esame_licenza = " . dbQ(iscrizioniPrimeField($row, ['ESITO'])) . ",
                voto_esame_licenza = " . dbQ(iscrizioniPrimeField($row, ['VOTO'])) . ",
                raw_licenza_media_json = " . dbQ(iscrizioniPrimeJson($row)) . ",
                updated_at = NOW()
            WHERE id = " . dbI($practice['id'] ?? 0) . "
            LIMIT 1
        ");
        $stats['updated']++;
        if (count($stats['updated_details']) < 40) {
            $stats['updated_details'][] = ['id' => intval($practice['id'] ?? 0), 'studente' => trim((string)($row['COGNOME'] ?? '') . ' ' . (string)($row['NOME'] ?? '')), 'codice_fiscale' => $cf, 'voto' => (string)iscrizioniPrimeField($row, ['VOTO'], '')];
        }
    }
    return $stats;
}

function iscrizioniPrimeAdditionalParentNotes(array $row, string $tipoIscrizione): string
{
    $parts = [];
    if ($tipoIscrizione === 'prime') {
        foreach ([
            'RISPOSTA 6' => 'Richieste personali',
            'RISPOSTA 8' => 'Annotazioni quesito 8',
        ] as $field => $label) {
            $value = trim((string)iscrizioniPrimeField($row, [$field], ''));
            if ($value !== '') {
                $parts[] = $label . ': ' . $value;
            }
        }
    }

    $annotazioni = trim((string)iscrizioniPrimeField($row, ['ANNOTAZIONI'], ''));
    if ($annotazioni !== '') {
        $parts[] = 'Annotazioni: ' . $annotazioni;
    }

    return trim(implode("\n", array_values(array_unique($parts))));
}

function iscrizioniPrimeAdditionalDesignCurvature(array $row): string
{
    $course = iscrizioniPrimeNormalizeTextForAddress((string)iscrizioniPrimeField($row, ['CORSO DI STUDI DI ISCRIZIONE', 'CORSO DI STUDI', 'CORSO STUDI'], ''));
    if (strpos($course, 'COSTRUZIONI') === false || strpos($course, 'TERRITORIO') === false) {
        return '';
    }

    for ($i = 1; $i <= 25; $i++) {
        $question = iscrizioniPrimeNormalizeTextForAddress((string)iscrizioniPrimeField($row, ['QUESITO ' . $i], ''));
        if ($question === '') {
            continue;
        }
        if (strpos($question, 'INSERIMENTO') === false
            || strpos($question, 'CURVATURA') === false
            || strpos($question, 'DESIGN') === false
            || strpos($question, 'RIQUALIFICAZIONE') === false) {
            continue;
        }
        $answer = iscrizioniPrimeNormalizeTextForAddress((string)iscrizioniPrimeField($row, ['RISPOSTA ' . $i], ''));
        if ($answer === 'SI') {
            return 'design';
        }
        if ($answer === 'NO') {
            return 'normale';
        }
    }

    return '';
}

function iscrizioniPrimeUpdateAdditionalRows(array $rows, string $tipoIscrizione): array
{
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);
    $stats = [
        'rows' => count($rows),
        'updated' => 0,
        'ignored' => 0,
        'updated_details' => [],
        'ignored_details' => [],
    ];

    foreach ($rows as $row) {
        $cf = strtoupper(trim((string)iscrizioniPrimeField($row, ['CODICE FISCALE STUDENTE', 'CODICE FISCALE', 'CHIAVE FISCALE'], '')));
        $anno = iscrizioniPrimeNormalizeSchoolYear(iscrizioniPrimeField($row, ['ANNO SCOLASTICO'], ''));
        $student = trim((string)iscrizioniPrimeField($row, ['COGNOME STUDENTE', 'COGNOME'], '') . ' ' . (string)iscrizioniPrimeField($row, ['NOME STUDENTE', 'NOME'], ''));
        if ($cf === '') {
            $stats['ignored']++;
            if (count($stats['ignored_details']) < 40) {
                $stats['ignored_details'][] = ['studente' => $student, 'motivo' => 'codice fiscale mancante'];
            }
            continue;
        }

        $whereYear = $anno !== '' ? iscrizioniPrimeSchoolYearWhere('anno_scolastico', $anno) : '1 = 1';
        $practice = dbGetFirst("
            SELECT id
            FROM iscrizioni_prime_pratiche
            WHERE tipo_iscrizione = " . dbQ($tipoIscrizione) . "
              AND UPPER(TRIM(codice_fiscale)) = " . dbQ($cf) . "
              AND stato <> 'annullata'
              AND " . $whereYear . "
            ORDER BY updated_at DESC, id DESC
            LIMIT 1
        ");
        if (!$practice) {
            $stats['ignored']++;
            if (count($stats['ignored_details']) < 40) {
                $stats['ignored_details'][] = ['studente' => $student, 'codice_fiscale' => $cf, 'motivo' => 'pratica non trovata'];
            }
            continue;
        }

        $notes = iscrizioniPrimeAdditionalParentNotes($row, $tipoIscrizione);
        $curvature = $tipoIscrizione === 'terze' ? iscrizioniPrimeAdditionalDesignCurvature($row) : '';
        if ($notes === '' && $curvature === '') {
            $stats['ignored']++;
            if (count($stats['ignored_details']) < 40) {
                $stats['ignored_details'][] = ['id' => intval($practice['id'] ?? 0), 'studente' => $student, 'codice_fiscale' => $cf, 'motivo' => 'nessun dato aggiuntivo valorizzato'];
            }
            continue;
        }

        $sets = ['raw_dati_aggiuntivi_json = ' . dbQ(iscrizioniPrimeJson($row)), 'updated_at = NOW()'];
        if ($notes !== '') {
            $sets[] = 'note_genitori_iscrizione = ' . dbQ($notes);
        }
        if ($curvature !== '') {
            $sets[] = 'curvatura_design = ' . dbQ($curvature);
        }
        dbExec("
            UPDATE iscrizioni_prime_pratiche
            SET " . implode(", ", $sets) . "
            WHERE id = " . dbI($practice['id'] ?? 0) . "
            LIMIT 1
        ");

        $stats['updated']++;
        if (count($stats['updated_details']) < 80) {
            $detail = ['id' => intval($practice['id'] ?? 0), 'studente' => $student, 'codice_fiscale' => $cf];
            if ($notes !== '') {
                $detail['motivo'] = 'note genitori';
            }
            if ($curvature !== '') {
                $detail['corso_studi'] = 'curvatura ' . $curvature;
            }
            $stats['updated_details'][] = $detail;
        }
    }

    return $stats;
}

function iscrizioniPrimeReadSpreadsheetRows(string $path): array
{
    require_once __DIR__ . '/vendor/autoload.php';

    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
    $sheet = $spreadsheet->getSheet(0);
    $rawRows = $sheet->toArray(null, true, true, true);
    if (!$rawRows) {
        return [];
    }
    $headerRow = array_shift($rawRows);
    $headers = iscrizioniPrimeNormalizeHeader(array_values($headerRow));
    $columns = array_keys($headerRow);
    $rows = [];
    foreach ($rawRows as $rawRow) {
        $assoc = [];
        $hasValue = false;
        foreach ($columns as $index => $columnKey) {
            $name = $headers[$index] ?? '';
            if ($name === '') {
                continue;
            }
            $value = trim((string)($rawRow[$columnKey] ?? ''));
            if ($value !== '') {
                $hasValue = true;
            }
            $assoc[$name] = $value;
        }
        if ($hasValue) {
            $rows[] = $assoc;
        }
    }
    $spreadsheet->disconnectWorksheets();
    return $rows;
}

function iscrizioniPrimeImportSchoolAttributesFromXls(string $path, string $sourceName = ''): array
{
    studentiAttrEnsureTables();
    $rows = iscrizioniPrimeReadSpreadsheetRows($path);
    $stats = [
        'rows' => count($rows),
        'matched_students' => 0,
        'unmatched_students' => 0,
        'updated_attributes' => 0,
        'active_by_code' => array_fill_keys(array_keys(studentiAttrMap()), 0),
        'unmatched_examples' => [],
        'matched_examples' => [],
    ];

    foreach ($rows as $row) {
        $cf = strtoupper(trim((string)iscrizioniPrimeField($row, ['CODICE FISCALE', 'CHIAVE FISCALE', 'CODICE FISCALE STUDENTE'], '')));
        if ($cf === '') {
            $stats['unmatched_students']++;
            continue;
        }
        $student = studentiAttrFindStudentByFiscalCode($cf);
        if (!$student) {
            $stats['unmatched_students']++;
            if (count($stats['unmatched_examples']) < 20) {
                $stats['unmatched_examples'][] = [
                    'studente' => trim((string)iscrizioniPrimeField($row, ['COGNOME'], '') . ' ' . (string)iscrizioniPrimeField($row, ['NOME'], '')),
                    'codice_fiscale' => $cf,
                ];
            }
            continue;
        }

        $stats['matched_students']++;
        if (count($stats['matched_examples']) < 20) {
            $stats['matched_examples'][] = [
                'id' => intval($student['id'] ?? 0),
                'studente' => trim((string)($student['cognome'] ?? '') . ' ' . (string)($student['nome'] ?? '')),
                'codice_fiscale' => $cf,
            ];
        }
        $text = '';
        foreach ($row as $key => $value) {
            $text .= ' ' . (string)$key . ' ' . (string)$value;
        }
        $parsed = studentiAttrParseNote($text);
        $sourceHash = hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        foreach ($parsed as $code => $active) {
            studentiAttrUpsert((int)$student['id'], (string)$code, (bool)$active, 'xls_certificazioni', $sourceName, $sourceHash);
            $stats['updated_attributes']++;
            if ($active) {
                $stats['active_by_code'][$code]++;
            }
        }
    }

    return $stats;
}

function iscrizioniPrimeImport(?string $primePath, ?string $dsaPath, string $primeName = '', string $dsaName = '', string $createdBy = '', ?string $anagraficaPath = null, string $anagraficaName = '', string $tipoIscrizione = 'prime', ?string $licenzaMediaPath = null, string $licenzaMediaName = '', ?string $dsaSchoolPath = null, string $dsaSchoolName = '', ?string $additionalPath = null, string $additionalName = ''): array
{
    iscrizioniPrimeEnsureSchema();
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);

    $primeRows = $primePath ? iscrizioniPrimeReadCsv($primePath) : [];
    $dsaRows = $dsaPath ? iscrizioniPrimeReadCsv($dsaPath) : [];
    $anagraficaRows = $anagraficaPath ? iscrizioniPrimeReadCsv($anagraficaPath) : [];
    $licenzaMediaRows = $licenzaMediaPath ? iscrizioniPrimeReadCsv($licenzaMediaPath) : [];
    $additionalRows = $additionalPath ? iscrizioniPrimeReadCsv($additionalPath) : [];
    $dsaByCf = [];
    $licenzaMediaByCf = [];

    foreach ($dsaRows as $row) {
        $cf = strtoupper(trim((string)($row['CODICE FISCALE'] ?? '')));
        if ($cf !== '') {
            $dsaByCf[$cf] = $row;
        }
    }

    foreach ($licenzaMediaRows as $row) {
        $cf = strtoupper(trim((string)($row['CODICE FISCALE'] ?? '')));
        if ($cf !== '') {
            $licenzaMediaByCf[$cf] = $row;
        }
    }

    $inserted = 0;
    $updated = 0;
    $errors = [];
    $generatedTokens = [];
    $internal = 0;
    $external = 0;
    $insertedDetails = [];
    $updatedDetails = [];
    $licenzaLinkedDetails = [];
    $movementLinked = 0;
    $movementAlreadyLinked = 0;
    $movementConflicts = 0;
    $movementLinkedDetails = [];
    $movementConflictDetails = [];

    foreach ($primeRows as $index => $row) {
        $cf = strtoupper(trim((string)iscrizioniPrimeField($row, ['CODICE FISCALE STUDENTE', 'CODICE FISCALE', 'CHIAVE FISCALE'], '')));
        $isInternal = iscrizioniPrimeStudentIsInternal($cf);
        if ($isInternal) {
            $internal++;
        } else {
            $external++;
        }
        $result = iscrizioniPrimeUpsert($row, $dsaByCf[$cf] ?? null, $tipoIscrizione, $isInternal, $licenzaMediaByCf[$cf] ?? null);

        if (!$result['ok']) {
            $errors[] = 'Riga PRIME ' . ($index + 2) . ': ' . $result['error'];
            continue;
        }

        $movimentiLink = $result['movimenti_entrata_link'] ?? [];
        $movementLinked += intval($movimentiLink['linked'] ?? 0);
        $movementAlreadyLinked += intval($movimentiLink['already_linked'] ?? 0);
        $movementConflicts += intval($movimentiLink['conflicts'] ?? 0);
        foreach (($movimentiLink['details'] ?? []) as $detail) {
            if (count($movementLinkedDetails) < 80) {
                $movementLinkedDetails[] = $detail;
            }
        }
        foreach (($movimentiLink['conflict_details'] ?? []) as $detail) {
            if (count($movementConflictDetails) < 80) {
                $movementConflictDetails[] = $detail;
            }
        }

        if ($result['inserted']) {
            $inserted++;
            $insertedDetails[] = [
                'id' => intval($result['id'] ?? 0),
                'studente' => (string)($result['studente'] ?? ''),
                'codice_fiscale' => (string)($result['codice_fiscale'] ?? $cf),
                'corso_studi' => (string)($result['corso_studi'] ?? ''),
            ];
        } else {
            $updated++;
            if (count($updatedDetails) < 40) {
                $updatedDetails[] = [
                    'id' => intval($result['id'] ?? 0),
                    'studente' => (string)($result['studente'] ?? ''),
                    'codice_fiscale' => (string)($result['codice_fiscale'] ?? $cf),
                    'corso_studi' => (string)($result['corso_studi'] ?? ''),
                ];
            }
        }

        if (isset($licenzaMediaByCf[$cf])) {
            $licenzaLinkedDetails[] = [
                'id' => intval($result['id'] ?? 0),
                'studente' => (string)($result['studente'] ?? ''),
                'codice_fiscale' => $cf,
                'voto' => (string)iscrizioniPrimeField($licenzaMediaByCf[$cf], ['VOTO'], ''),
            ];
        }

        if (!empty($result['token'])) {
            $generatedTokens[] = [
                'pratica_id' => $result['id'],
                'token' => $result['token'],
            ];
        }
    }

    $dsaStandalone = ['updated' => 0, 'ignored' => 0];
    if (empty($primeRows) && !empty($dsaRows)) {
        $dsaStandalone = iscrizioniPrimeUpdateDsaRows($dsaRows, $tipoIscrizione);
    }

    $licenzaStandalone = ['updated' => 0, 'ignored' => 0];
    if (empty($primeRows) && !empty($licenzaMediaRows)) {
        $licenzaStandalone = iscrizioniPrimeUpdateLicenzaMediaRows($licenzaMediaRows, $tipoIscrizione);
    }

    $additionalStats = ['rows' => count($additionalRows), 'updated' => 0, 'ignored' => 0, 'updated_details' => [], 'ignored_details' => []];
    if (!empty($additionalRows)) {
        $additionalStats = iscrizioniPrimeUpdateAdditionalRows($additionalRows, $tipoIscrizione);
    }

    $contacts = ['updated' => 0, 'ignored' => 0, 'internal_skipped' => 0];
    if (!empty($anagraficaRows)) {
        $contacts = iscrizioniPrimeUpdateContacts($anagraficaRows, $tipoIscrizione);
    }
    $schoolAttrs = [
        'rows' => 0,
        'matched_students' => 0,
        'unmatched_students' => 0,
        'updated_attributes' => 0,
        'active_by_code' => array_fill_keys(array_keys(studentiAttrMap()), 0),
        'unmatched_examples' => [],
    ];
    if ($dsaSchoolPath) {
        $schoolAttrs = iscrizioniPrimeImportSchoolAttributesFromXls($dsaSchoolPath, $dsaSchoolName);
    }
    $markedInternal = iscrizioniPrimeMarkCurrentStudentsAsInternal($tipoIscrizione);
    $movementBackfill = iscrizioniPrimeBackfillEntrataMovimentiLinks($tipoIscrizione);
    $movementLinked += intval($movementBackfill['linked'] ?? 0);
    $movementAlreadyLinked += intval($movementBackfill['already_linked'] ?? 0);
    $movementConflicts += intval($movementBackfill['conflicts'] ?? 0);
    foreach (($movementBackfill['details'] ?? []) as $detail) {
        if (count($movementLinkedDetails) < 80) {
            $movementLinkedDetails[] = $detail;
        }
    }
    foreach (($movementBackfill['conflict_details'] ?? []) as $detail) {
        if (count($movementConflictDetails) < 80) {
            $movementConflictDetails[] = $detail;
        }
    }

    dbExec("
        INSERT INTO iscrizioni_prime_import_log
        (created_at, created_by, prime_filename, dsa_filename, anagrafica_filename, licenza_media_filename, dati_aggiuntivi_filename, dsa_school_filename, righe_prime, righe_dsa, righe_anagrafica, righe_licenza_media, righe_dati_aggiuntivi, righe_dsa_school, attributi_school_aggiornati, attributi_school_non_agganciati, inserite, aggiornate, contatti_aggiornati, contatti_ignorati, tipo_iscrizione, errori_json)
        VALUES (
            NOW(),
            " . dbQ($createdBy) . ",
            " . dbQ($primeName) . ",
            " . dbQ($dsaName) . ",
            " . dbQ($anagraficaName) . ",
            " . dbQ($licenzaMediaName) . ",
            " . dbQ($additionalName) . ",
            " . dbQ($dsaSchoolName) . ",
            " . intval(count($primeRows)) . ",
            " . intval(count($dsaRows)) . ",
            " . intval(count($anagraficaRows)) . ",
            " . intval(count($licenzaMediaRows)) . ",
            " . intval(count($additionalRows)) . ",
            " . intval($schoolAttrs['rows'] ?? 0) . ",
            " . intval($schoolAttrs['updated_attributes'] ?? 0) . ",
            " . intval($schoolAttrs['unmatched_students'] ?? 0) . ",
            " . intval($inserted) . ",
            " . intval($updated) . ",
            " . intval($contacts['updated']) . ",
            " . intval($contacts['ignored']) . ",
            " . dbQ($tipoIscrizione) . ",
            " . dbQ(json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "
        )
    ");

    return [
        'prime_rows' => count($primeRows),
        'dsa_rows' => count($dsaRows),
        'inserted' => $inserted,
        'updated' => $updated,
        'inserted_details' => $insertedDetails,
        'updated_details' => $updatedDetails,
        'contact_rows' => count($anagraficaRows),
        'licenza_media_rows' => count($licenzaMediaRows),
        'licenza_media_linked' => count($licenzaLinkedDetails),
        'licenza_media_linked_details' => array_slice($licenzaLinkedDetails, 0, 40),
        'movimenti_entrata_collegati' => $movementLinked,
        'movimenti_entrata_gia_collegati' => $movementAlreadyLinked,
        'movimenti_entrata_conflitti' => $movementConflicts,
        'movimenti_entrata_collegati_details' => $movementLinkedDetails,
        'movimenti_entrata_conflitti_details' => $movementConflictDetails,
        'dsa_updated' => $dsaStandalone['updated'],
        'dsa_ignored' => $dsaStandalone['ignored'],
        'dsa_updated_details' => $dsaStandalone['updated_details'] ?? [],
        'dsa_ignored_details' => $dsaStandalone['ignored_details'] ?? [],
        'licenza_media_updated' => $licenzaStandalone['updated'],
        'licenza_media_ignored' => $licenzaStandalone['ignored'],
        'licenza_media_updated_details' => $licenzaStandalone['updated_details'] ?? [],
        'licenza_media_ignored_details' => $licenzaStandalone['ignored_details'] ?? [],
        'additional_rows' => $additionalStats['rows'] ?? 0,
        'additional_updated' => $additionalStats['updated'] ?? 0,
        'additional_ignored' => $additionalStats['ignored'] ?? 0,
        'additional_updated_details' => $additionalStats['updated_details'] ?? [],
        'additional_ignored_details' => $additionalStats['ignored_details'] ?? [],
        'contacts_updated' => $contacts['updated'],
        'contacts_ignored' => $contacts['ignored'],
        'contacts_internal_skipped' => $contacts['internal_skipped'] ?? 0,
        'school_attr_rows' => $schoolAttrs['rows'] ?? 0,
        'school_attr_matched' => $schoolAttrs['matched_students'] ?? 0,
        'school_attr_unmatched' => $schoolAttrs['unmatched_students'] ?? 0,
        'school_attr_updated' => $schoolAttrs['updated_attributes'] ?? 0,
        'school_attr_active_by_code' => $schoolAttrs['active_by_code'] ?? [],
        'school_attr_matched_examples' => $schoolAttrs['matched_examples'] ?? [],
        'school_attr_unmatched_examples' => $schoolAttrs['unmatched_examples'] ?? [],
        'tipo_iscrizione' => $tipoIscrizione,
        'interni' => $internal,
        'esterni' => $external,
        'interni_marcati_da_gestore' => $markedInternal,
        'errors' => $errors,
        'generated_tokens' => $generatedTokens,
    ];
}

function iscrizioniTerzeManualSave(array $data, string $createdBy = ''): array
{
    iscrizioniPrimeEnsureSchema();

    $cf = strtoupper(trim((string)($data['codice_fiscale'] ?? '')));
    $cognome = strtoupper(trim((string)($data['cognome'] ?? '')));
    $nome = strtoupper(trim((string)($data['nome'] ?? '')));
    $anno = iscrizioniPrimeNormalizeSchoolYear($data['anno_scolastico'] ?? '2026/27');
    $corso = trim((string)($data['corso_studi'] ?? ''));

    if ($cf === '' || $cognome === '' || $nome === '') {
        return ['ok' => false, 'message' => 'Cognome, nome e codice fiscale sono obbligatori.'];
    }
    if (!preg_match('/^[A-Z0-9]{16}$/', $cf)) {
        return ['ok' => false, 'message' => 'Codice fiscale non valido.'];
    }

    $prime = [
        'CODICE DOMANDA' => 'MANUALE-' . $cf,
        'ANNO SCOLASTICO' => $anno,
        'COGNOME STUDENTE' => $cognome,
        'NOME STUDENTE' => $nome,
        'DATA NASCITA STUDENTE' => trim((string)($data['data_nascita'] ?? '')),
        'CODICE FISCALE STUDENTE' => $cf,
        'UNITA SCOLASTICA DI ISCRIZIONE' => 'ISTITUTO TECNICO PER IL SETTORE TECNOLOGICO',
        'CORSO DI STUDI DI ISCRIZIONE' => $corso,
        'ANNO DI CORSO' => '3',
        'ANNOTAZIONI' => 'Pratica terze inserita manualmente in GestOre da ' . $createdBy,
    ];

    $result = iscrizioniPrimeUpsert($prime, null, 'terze', false);
    if (empty($result['ok'])) {
        return ['ok' => false, 'message' => $result['error'] ?? 'Errore salvataggio pratica manuale.'];
    }

    dbExec("
        UPDATE iscrizioni_prime_pratiche SET
            id_indirizzo_gestore = " . dbI(intval($data['id_indirizzo_gestore'] ?? 0) ?: iscrizioniPrimeGestoreAddressIdFromText($corso)) . ",
            email_studente = " . dbQ($data['email_studente'] ?? null) . ",
            telefono_studente = " . dbQ($data['telefono_studente'] ?? null) . ",
            email_genitore_1 = " . dbQ($data['email_genitore_1'] ?? null) . ",
            telefono_genitore_1 = " . dbQ($data['telefono_genitore_1'] ?? null) . ",
            email_genitore_2 = " . dbQ($data['email_genitore_2'] ?? null) . ",
            telefono_genitore_2 = " . dbQ($data['telefono_genitore_2'] ?? null) . ",
            responsabile_1_tipo = " . dbQ($data['responsabile_1_tipo'] ?? 'Responsabile 1') . ",
            responsabile_1_cognome = " . dbQ($data['responsabile_1_cognome'] ?? null) . ",
            responsabile_1_nome = " . dbQ($data['responsabile_1_nome'] ?? null) . ",
            responsabile_2_tipo = " . dbQ($data['responsabile_2_tipo'] ?? 'Responsabile 2') . ",
            responsabile_2_cognome = " . dbQ($data['responsabile_2_cognome'] ?? null) . ",
            responsabile_2_nome = " . dbQ($data['responsabile_2_nome'] ?? null) . ",
            note_interne = CONCAT(COALESCE(note_interne, ''), " . dbQ("\nPratica inserita manualmente per eventuali esami integrativi.") . "),
            updated_at = NOW()
        WHERE id = " . dbI($result['id']) . "
        LIMIT 1
    ");

    return ['ok' => true, 'message' => 'Pratica manuale terze salvata.', 'id' => intval($result['id'])];
}
