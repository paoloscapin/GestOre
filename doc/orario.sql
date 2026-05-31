-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Creato il: Mag 31, 2026 alle 15:26
-- Versione del server: 10.11.16-MariaDB-cll-lve-log
-- Versione PHP: 8.4.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `gvgtcyej_gestione_ore`
--

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_alternanza_gruppo`
--

CREATE TABLE `orario_alternanza_gruppo` (
  `id` int(11) NOT NULL,
  `id_scenario` int(11) DEFAULT NULL,
  `id_anno_scolastico` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descrizione` text DEFAULT NULL,
  `attivo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_alternanza_riga`
--

CREATE TABLE `orario_alternanza_riga` (
  `id` int(11) NOT NULL,
  `id_gruppo` int(11) NOT NULL,
  `id_classe` int(11) NOT NULL,
  `id_materia_periodo_1` int(11) NOT NULL,
  `id_materia_periodo_2` int(11) NOT NULL,
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_aula_disponibilita`
--

CREATE TABLE `orario_aula_disponibilita` (
  `id` int(11) NOT NULL,
  `id_scenario` int(11) NOT NULL,
  `id_aula` int(11) NOT NULL,
  `id_slot` int(11) NOT NULL,
  `stato` enum('DISPONIBILE','NON_DISPONIBILE','RISERVATA') NOT NULL DEFAULT 'DISPONIBILE',
  `motivo` varchar(255) DEFAULT NULL,
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_aula_gruppo`
--

CREATE TABLE `orario_aula_gruppo` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descrizione` text DEFAULT NULL,
  `attivo` tinyint(1) NOT NULL DEFAULT 1,
  `ordine` int(11) NOT NULL DEFAULT 100,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_aula_gruppo_aula`
--

CREATE TABLE `orario_aula_gruppo_aula` (
  `id` int(11) NOT NULL,
  `id_gruppo` int(11) NOT NULL,
  `id_aula` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_blocco_sequenza_catalogo`
--

CREATE TABLE `orario_blocco_sequenza_catalogo` (
  `id` int(11) NOT NULL,
  `sequenza` varchar(50) NOT NULL,
  `descrizione` varchar(255) DEFAULT NULL,
  `attiva` tinyint(1) NOT NULL DEFAULT 1,
  `ordine` int(11) NOT NULL DEFAULT 100
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_calendario_giorno_speciale`
--

CREATE TABLE `orario_calendario_giorno_speciale` (
  `id` int(11) NOT NULL,
  `id_scenario` int(11) NOT NULL,
  `data_giorno` date NOT NULL,
  `tipo` enum('FESTIVITA','VACANZA','PONTE','SOSPENSIONE_LEZIONI','GIORNO_SPECIALE','ALTRO') NOT NULL DEFAULT 'VACANZA',
  `descrizione` varchar(255) DEFAULT NULL,
  `lezioni_sospese` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_calendario_scolastico`
--

CREATE TABLE `orario_calendario_scolastico` (
  `id` int(11) NOT NULL,
  `id_scenario` int(11) NOT NULL,
  `data_inizio_lezioni` date NOT NULL,
  `data_fine_lezioni` date NOT NULL,
  `data_inizio_primo_periodo` date NOT NULL,
  `data_fine_primo_periodo` date NOT NULL,
  `data_inizio_secondo_periodo` date NOT NULL,
  `data_fine_secondo_periodo` date NOT NULL,
  `tipo_periodo` enum('TRIMESTRE_PENTAMESTRE','QUADRIMESTRI','ALTRO') NOT NULL DEFAULT 'TRIMESTRE_PENTAMESTRE',
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_classe_articolata_classe`
--

CREATE TABLE `orario_classe_articolata_classe` (
  `id` int(11) NOT NULL,
  `id_gruppo` int(11) NOT NULL,
  `id_classe` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_classe_articolata_gruppo`
--

CREATE TABLE `orario_classe_articolata_gruppo` (
  `id` int(11) NOT NULL,
  `id_scenario` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descrizione` text DEFAULT NULL,
  `attivo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_classe_articolata_gruppo_materie`
--

CREATE TABLE `orario_classe_articolata_gruppo_materie` (
  `id` int(11) NOT NULL,
  `id_gruppo_articolato` int(11) NOT NULL,
  `id_classe` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `note` text DEFAULT NULL,
  `attivo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_classe_articolata_gruppo_materie_riga`
--

CREATE TABLE `orario_classe_articolata_gruppo_materie_riga` (
  `id` int(11) NOT NULL,
  `id_gruppo_materie` int(11) NOT NULL,
  `id_materia` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_classe_articolata_materia`
--

CREATE TABLE `orario_classe_articolata_materia` (
  `id` int(11) NOT NULL,
  `id_gruppo` int(11) NOT NULL,
  `id_materia` int(11) NOT NULL,
  `tipo` enum('COMUNE','SEPARATA_SINCRONIZZATA') NOT NULL DEFAULT 'COMUNE',
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_classe_articolata_sincronizzazione`
--

CREATE TABLE `orario_classe_articolata_sincronizzazione` (
  `id` int(11) NOT NULL,
  `id_gruppo_articolato` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `id_gruppo_materie_a` int(11) NOT NULL,
  `id_gruppo_materie_b` int(11) NOT NULL,
  `ore_settimanali` decimal(4,1) DEFAULT NULL,
  `attivo` tinyint(1) NOT NULL DEFAULT 1,
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_classe_aula`
--

CREATE TABLE `orario_classe_aula` (
  `id` int(11) NOT NULL,
  `id_scenario` int(11) NOT NULL,
  `id_classe` int(11) NOT NULL,
  `id_aula` int(11) NOT NULL,
  `tipo` enum('PREDEFINITA','PREFERITA','VIETATA') NOT NULL DEFAULT 'PREDEFINITA',
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_classe_piano_orario`
--

CREATE TABLE `orario_classe_piano_orario` (
  `id` int(11) NOT NULL,
  `id_anno_scolastico` int(11) NOT NULL,
  `id_classe` int(11) NOT NULL,
  `id_piano_orario` int(11) NOT NULL,
  `attivo` tinyint(1) NOT NULL DEFAULT 1,
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_classe_slot_vincolo`
--

CREATE TABLE `orario_classe_slot_vincolo` (
  `id` int(11) NOT NULL,
  `id_scenario` int(11) NOT NULL,
  `id_classe` int(11) NOT NULL,
  `id_slot` int(11) NOT NULL,
  `stato` enum('DISPONIBILE','NON_DISPONIBILE','OBBLIGATORIO','PREFERITO') NOT NULL DEFAULT 'NON_DISPONIBILE',
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_classe_vincolo`
--

CREATE TABLE `orario_classe_vincolo` (
  `id` int(11) NOT NULL,
  `id_scenario` int(11) NOT NULL,
  `id_classe` int(11) NOT NULL,
  `ore_settimanali_totali` decimal(4,1) NOT NULL DEFAULT 0.0,
  `giorni_lezione` tinyint(4) NOT NULL DEFAULT 5,
  `pomeriggi_settimanali` tinyint(4) NOT NULL DEFAULT 2,
  `vincola_pomeriggio` tinyint(1) NOT NULL DEFAULT 0,
  `giorno_pomeriggio_vincolato` tinyint(4) DEFAULT NULL,
  `pausa_pranzo` time DEFAULT NULL,
  `ora_termine_massima` time NOT NULL DEFAULT '16:20:00',
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_conflitto`
--

CREATE TABLE `orario_conflitto` (
  `id` int(11) NOT NULL,
  `id_scenario` int(11) NOT NULL,
  `id_soluzione` int(11) DEFAULT NULL,
  `tipo` enum('DOCENTE_DOPPIO','CLASSE_DOPPIA','AULA_DOPPIA','ORE_MANCANTI','ORE_ECCESSIVE','AULA_NON_COMPATIBILE','DOCENTE_NON_DISPONIBILE','VINCOLO_NON_RISPETTATO','ALTRO') NOT NULL,
  `livello` enum('ERRORE','WARNING','INFO') NOT NULL DEFAULT 'ERRORE',
  `id_docente` int(11) DEFAULT NULL,
  `id_classe` int(11) DEFAULT NULL,
  `id_materia` int(11) DEFAULT NULL,
  `id_aula` int(11) DEFAULT NULL,
  `id_slot` int(11) DEFAULT NULL,
  `messaggio` text NOT NULL,
  `dettagli_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dettagli_json`)),
  `risolto` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_docente_disponibilita`
--

CREATE TABLE `orario_docente_disponibilita` (
  `id` int(11) NOT NULL,
  `id_scenario` int(11) NOT NULL,
  `id_docente` int(11) NOT NULL,
  `id_slot` int(11) NOT NULL,
  `stato` enum('DISPONIBILE','NON_DISPONIBILE','PREFERITO','SCONSIGLIATO') NOT NULL DEFAULT 'DISPONIBILE',
  `peso` int(11) NOT NULL DEFAULT 0,
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_docente_insegna_scenario`
--

CREATE TABLE `orario_docente_insegna_scenario` (
  `id` int(11) NOT NULL,
  `id_scenario` int(11) NOT NULL,
  `id_docente` int(11) DEFAULT NULL,
  `docente_temporaneo` varchar(255) DEFAULT NULL,
  `docente_da_nominare` tinyint(1) NOT NULL DEFAULT 0,
  `docente_key` varchar(255) NOT NULL DEFAULT '',
  `id_classe` int(11) NOT NULL,
  `id_materia` int(11) NOT NULL,
  `origine` enum('DA_ANNO_PRECEDENTE','IMPORT_FILE','MANUALE') NOT NULL DEFAULT 'MANUALE',
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_fabbisogno_classe`
--

CREATE TABLE `orario_fabbisogno_classe` (
  `id` int(11) NOT NULL,
  `id_scenario` int(11) NOT NULL,
  `id_classe` int(11) NOT NULL,
  `id_docente` int(11) NOT NULL,
  `id_materia` int(11) NOT NULL,
  `ore_settimanali` decimal(4,1) NOT NULL DEFAULT 0.0,
  `ore_blocco_preferito` tinyint(4) DEFAULT NULL,
  `richiede_aula_specifica` tinyint(1) NOT NULL DEFAULT 0,
  `id_aula_preferita` int(11) DEFAULT NULL,
  `gruppo_classe` varchar(50) DEFAULT NULL,
  `compresenza` tinyint(1) NOT NULL DEFAULT 0,
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_import_classe_alias`
--

CREATE TABLE `orario_import_classe_alias` (
  `id` int(11) NOT NULL,
  `alias_classe` varchar(50) NOT NULL,
  `id_classe` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_import_docente_temporaneo_alias`
--

CREATE TABLE `orario_import_docente_temporaneo_alias` (
  `id` int(11) NOT NULL,
  `docente_temporaneo` varchar(255) NOT NULL,
  `id_docente` int(11) NOT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_import_materia_alias`
--

CREATE TABLE `orario_import_materia_alias` (
  `id` int(11) NOT NULL,
  `alias_materia` varchar(255) NOT NULL,
  `id_materia` int(11) NOT NULL,
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_log`
--

CREATE TABLE `orario_log` (
  `id` int(11) NOT NULL,
  `id_scenario` int(11) DEFAULT NULL,
  `livello` enum('INFO','WARNING','ERROR','DEBUG') NOT NULL DEFAULT 'INFO',
  `azione` varchar(100) NOT NULL,
  `messaggio` text NOT NULL,
  `dettagli_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dettagli_json`)),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_materia_aula`
--

CREATE TABLE `orario_materia_aula` (
  `id` int(11) NOT NULL,
  `id_scenario` int(11) NOT NULL,
  `id_materia` int(11) NOT NULL,
  `id_aula` int(11) NOT NULL,
  `preferita` tinyint(1) NOT NULL DEFAULT 0,
  `obbligatoria` tinyint(1) NOT NULL DEFAULT 0,
  `attiva` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_materia_scambio_periodo`
--

CREATE TABLE `orario_materia_scambio_periodo` (
  `id` int(11) NOT NULL,
  `id_piano_orario` int(11) NOT NULL,
  `id_classe_a` int(11) DEFAULT NULL,
  `id_classe_b` int(11) DEFAULT NULL,
  `id_materia_a` int(11) NOT NULL,
  `id_materia_b` int(11) NOT NULL,
  `periodo_a` enum('PRIMO_PERIODO','SECONDO_PERIODO') NOT NULL,
  `periodo_b` enum('PRIMO_PERIODO','SECONDO_PERIODO') NOT NULL,
  `note` text DEFAULT NULL,
  `attivo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_monteore_classe_override`
--

CREATE TABLE `orario_monteore_classe_override` (
  `id` int(11) NOT NULL,
  `id_anno_scolastico` int(11) NOT NULL,
  `id_classe` int(11) NOT NULL,
  `id_materia` int(11) NOT NULL,
  `ore_settimanali` decimal(4,1) DEFAULT NULL,
  `ore_laboratorio` decimal(4,1) DEFAULT NULL,
  `ore_compresenza` decimal(4,1) DEFAULT NULL,
  `blocco_preferito` tinyint(4) DEFAULT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_piano_materia_aula_richiesta`
--

CREATE TABLE `orario_piano_materia_aula_richiesta` (
  `id` int(11) NOT NULL,
  `id_piano_orario_materia` int(11) NOT NULL,
  `tipo_ora` enum('TEORIA','LABORATORIO') NOT NULL,
  `progressivo` tinyint(4) NOT NULL DEFAULT 1,
  `modalita` enum('NESSUNA','AULA_FISSA','GRUPPO_AULE') NOT NULL DEFAULT 'NESSUNA',
  `id_aula` int(11) DEFAULT NULL,
  `id_gruppo_aula` int(11) DEFAULT NULL,
  `obbligatoria` tinyint(1) NOT NULL DEFAULT 1,
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_piano_orario`
--

CREATE TABLE `orario_piano_orario` (
  `id` int(11) NOT NULL,
  `id_anno_scolastico` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descrizione` text DEFAULT NULL,
  `anno_classe` tinyint(4) DEFAULT NULL,
  `id_indirizzo` int(11) DEFAULT NULL,
  `attivo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_piano_orario_classe_alias`
--

CREATE TABLE `orario_piano_orario_classe_alias` (
  `id` int(11) NOT NULL,
  `id_piano_orario` int(11) NOT NULL,
  `alias_classe` varchar(50) NOT NULL,
  `id_classe` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_piano_orario_materia`
--

CREATE TABLE `orario_piano_orario_materia` (
  `id` int(11) NOT NULL,
  `id_piano_orario` int(11) NOT NULL,
  `id_materia` int(11) NOT NULL,
  `ore_teoria` decimal(4,1) NOT NULL DEFAULT 0.0,
  `ore_laboratorio` decimal(4,1) NOT NULL DEFAULT 0.0,
  `periodicita` enum('ANNUALE','PRIMO_PERIODO','SECONDO_PERIODO','ALTERNATA') NOT NULL DEFAULT 'ANNUALE',
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_piano_orario_materia_blocco`
--

CREATE TABLE `orario_piano_orario_materia_blocco` (
  `id` int(11) NOT NULL,
  `id_piano_orario_materia` int(11) NOT NULL,
  `tipo_ora` enum('TEORIA','LABORATORIO') NOT NULL DEFAULT 'TEORIA',
  `sequenza` varchar(50) NOT NULL,
  `preferita` tinyint(1) NOT NULL DEFAULT 0,
  `peso` int(11) NOT NULL DEFAULT 100,
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_scenario`
--

CREATE TABLE `orario_scenario` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `id_anno_scolastico` int(11) NOT NULL,
  `descrizione` text DEFAULT NULL,
  `stato` enum('BOZZA','VALIDATO','GENERATO','PUBBLICATO','ARCHIVIATO') NOT NULL DEFAULT 'BOZZA',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_slot`
--

CREATE TABLE `orario_slot` (
  `id` int(11) NOT NULL,
  `id_scenario` int(11) NOT NULL,
  `giorno` tinyint(4) NOT NULL,
  `ora_index` tinyint(4) NOT NULL,
  `ora_inizio` time NOT NULL,
  `ora_fine` time NOT NULL,
  `attivo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_soluzione`
--

CREATE TABLE `orario_soluzione` (
  `id` int(11) NOT NULL,
  `id_scenario` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `stato` enum('BOZZA','VALIDA','CON_CONFLITTI','PUBBLICATA','SCARTATA') NOT NULL DEFAULT 'BOZZA',
  `punteggio_totale` int(11) DEFAULT NULL,
  `conflitti_rigidi` int(11) NOT NULL DEFAULT 0,
  `conflitti_morbidi` int(11) NOT NULL DEFAULT 0,
  `generata_da` enum('MANUALE','MOTORE') NOT NULL DEFAULT 'MOTORE',
  `log_generazione` mediumtext DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_soluzione_lezione`
--

CREATE TABLE `orario_soluzione_lezione` (
  `id` int(11) NOT NULL,
  `id_soluzione` int(11) NOT NULL,
  `id_scenario` int(11) NOT NULL,
  `id_classe` int(11) NOT NULL,
  `id_docente` int(11) NOT NULL,
  `id_materia` int(11) NOT NULL,
  `id_aula` int(11) DEFAULT NULL,
  `id_slot` int(11) NOT NULL,
  `gruppo_classe` varchar(50) DEFAULT NULL,
  `compresenza` tinyint(1) NOT NULL DEFAULT 0,
  `bloccata` tinyint(1) NOT NULL DEFAULT 0,
  `origine` enum('AUTO','MANUALE','IMPORTATA') NOT NULL DEFAULT 'AUTO',
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_vincolo`
--

CREATE TABLE `orario_vincolo` (
  `id` int(11) NOT NULL,
  `id_scenario` int(11) NOT NULL,
  `tipo` enum('DOCENTE_NON_DISPONIBILE','DOCENTE_PREFERISCE','CLASSE_NON_DISPONIBILE','AULA_NON_DISPONIBILE','MATERIA_SLOT_OBBLIGATO','MATERIA_SLOT_VIETATO','BLOCCO_ORE','GIORNO_LIBERO','ALTRO') NOT NULL,
  `livello` enum('RIGIDO','MORBIDO') NOT NULL DEFAULT 'RIGIDO',
  `peso` int(11) NOT NULL DEFAULT 100,
  `id_docente` int(11) DEFAULT NULL,
  `id_classe` int(11) DEFAULT NULL,
  `id_materia` int(11) DEFAULT NULL,
  `id_aula` int(11) DEFAULT NULL,
  `id_slot` int(11) DEFAULT NULL,
  `valore_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`valore_json`)),
  `descrizione` text DEFAULT NULL,
  `attivo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_vincolo_bilanciamento_pomeriggi`
--

CREATE TABLE `orario_vincolo_bilanciamento_pomeriggi` (
  `id` int(11) NOT NULL,
  `id_scenario` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL DEFAULT 'Bilanciamento pomeriggi',
  `giorni_da_bilanciare` varchar(50) NOT NULL DEFAULT '1,2,3,4,5',
  `livello` enum('RIGIDO','MORBIDO') NOT NULL DEFAULT 'MORBIDO',
  `peso` int(11) NOT NULL DEFAULT 100,
  `scarto_massimo` tinyint(4) DEFAULT NULL,
  `attivo` tinyint(1) NOT NULL DEFAULT 1,
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `orario_vincolo_pomeriggi_gruppo`
--

CREATE TABLE `orario_vincolo_pomeriggi_gruppo` (
  `id` int(11) NOT NULL,
  `id_scenario` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `filtro_anno_classe` varchar(50) NOT NULL,
  `pomeriggi_settimanali` tinyint(4) NOT NULL DEFAULT 2,
  `giorni_ammessi` varchar(50) DEFAULT NULL,
  `giorni_obbligatori` varchar(50) DEFAULT NULL,
  `distribuzione` enum('UNIFORME','VINCOLATA_UNIFORME') NOT NULL DEFAULT 'UNIFORME',
  `attivo` tinyint(1) NOT NULL DEFAULT 1,
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indici per le tabelle scaricate
--

--
-- Indici per le tabelle `orario_alternanza_gruppo`
--
ALTER TABLE `orario_alternanza_gruppo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_alt_gruppo_scenario` (`id_scenario`),
  ADD KEY `idx_alt_gruppo_anno` (`id_anno_scolastico`);

--
-- Indici per le tabelle `orario_alternanza_riga`
--
ALTER TABLE `orario_alternanza_riga`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_alt_riga_gruppo` (`id_gruppo`),
  ADD KEY `idx_alt_riga_classe` (`id_classe`),
  ADD KEY `idx_alt_riga_materia_p1` (`id_materia_periodo_1`),
  ADD KEY `idx_alt_riga_materia_p2` (`id_materia_periodo_2`);

--
-- Indici per le tabelle `orario_aula_disponibilita`
--
ALTER TABLE `orario_aula_disponibilita`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_aula_slot` (`id_scenario`,`id_aula`,`id_slot`),
  ADD KEY `idx_aula_disp_aula` (`id_aula`),
  ADD KEY `idx_aula_disp_slot` (`id_slot`);

--
-- Indici per le tabelle `orario_aula_gruppo`
--
ALTER TABLE `orario_aula_gruppo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_aula_gruppo_nome` (`nome`);

--
-- Indici per le tabelle `orario_aula_gruppo_aula`
--
ALTER TABLE `orario_aula_gruppo_aula`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_gruppo_aula` (`id_gruppo`,`id_aula`),
  ADD KEY `idx_gruppo_aula_gruppo` (`id_gruppo`),
  ADD KEY `idx_gruppo_aula_aula` (`id_aula`);

--
-- Indici per le tabelle `orario_blocco_sequenza_catalogo`
--
ALTER TABLE `orario_blocco_sequenza_catalogo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_sequenza_catalogo` (`sequenza`);

--
-- Indici per le tabelle `orario_calendario_giorno_speciale`
--
ALTER TABLE `orario_calendario_giorno_speciale`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_giorno_speciale` (`id_scenario`,`data_giorno`,`tipo`),
  ADD KEY `idx_cal_giorno_scenario` (`id_scenario`),
  ADD KEY `idx_cal_giorno_data` (`data_giorno`);

--
-- Indici per le tabelle `orario_calendario_scolastico`
--
ALTER TABLE `orario_calendario_scolastico`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_calendario_scenario` (`id_scenario`);

--
-- Indici per le tabelle `orario_classe_articolata_classe`
--
ALTER TABLE `orario_classe_articolata_classe`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_art_classe` (`id_gruppo`,`id_classe`),
  ADD KEY `fk_art_classe_classe` (`id_classe`);

--
-- Indici per le tabelle `orario_classe_articolata_gruppo`
--
ALTER TABLE `orario_classe_articolata_gruppo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_art_gruppo_scenario` (`id_scenario`);

--
-- Indici per le tabelle `orario_classe_articolata_gruppo_materie`
--
ALTER TABLE `orario_classe_articolata_gruppo_materie`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_art_gm_gruppo` (`id_gruppo_articolato`),
  ADD KEY `idx_art_gm_classe` (`id_classe`);

--
-- Indici per le tabelle `orario_classe_articolata_gruppo_materie_riga`
--
ALTER TABLE `orario_classe_articolata_gruppo_materie_riga`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_art_gm_materia` (`id_gruppo_materie`,`id_materia`),
  ADD KEY `fk_art_gm_riga_materia` (`id_materia`);

--
-- Indici per le tabelle `orario_classe_articolata_materia`
--
ALTER TABLE `orario_classe_articolata_materia`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_art_materia` (`id_gruppo`,`id_materia`),
  ADD KEY `fk_art_materia_materia` (`id_materia`);

--
-- Indici per le tabelle `orario_classe_articolata_sincronizzazione`
--
ALTER TABLE `orario_classe_articolata_sincronizzazione`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_art_sync_gruppo` (`id_gruppo_articolato`),
  ADD KEY `fk_art_sync_gm_a` (`id_gruppo_materie_a`),
  ADD KEY `fk_art_sync_gm_b` (`id_gruppo_materie_b`);

--
-- Indici per le tabelle `orario_classe_aula`
--
ALTER TABLE `orario_classe_aula`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_classe_aula` (`id_scenario`,`id_classe`,`id_aula`),
  ADD KEY `idx_classe_aula_classe` (`id_classe`),
  ADD KEY `idx_classe_aula_aula` (`id_aula`);

--
-- Indici per le tabelle `orario_classe_piano_orario`
--
ALTER TABLE `orario_classe_piano_orario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_classe_piano` (`id_anno_scolastico`,`id_classe`),
  ADD KEY `idx_classe_piano_classe` (`id_classe`),
  ADD KEY `idx_classe_piano_piano` (`id_piano_orario`),
  ADD KEY `idx_classe_piano_attivo` (`attivo`);

--
-- Indici per le tabelle `orario_classe_slot_vincolo`
--
ALTER TABLE `orario_classe_slot_vincolo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_classe_slot_vincolo` (`id_scenario`,`id_classe`,`id_slot`),
  ADD KEY `fk_csv_classe` (`id_classe`),
  ADD KEY `fk_csv_slot` (`id_slot`);

--
-- Indici per le tabelle `orario_classe_vincolo`
--
ALTER TABLE `orario_classe_vincolo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_classe_vincolo` (`id_scenario`,`id_classe`),
  ADD KEY `idx_classe_vincolo_scenario` (`id_scenario`),
  ADD KEY `idx_classe_vincolo_classe` (`id_classe`);

--
-- Indici per le tabelle `orario_conflitto`
--
ALTER TABLE `orario_conflitto`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conflitto_scenario` (`id_scenario`),
  ADD KEY `idx_conflitto_soluzione` (`id_soluzione`),
  ADD KEY `idx_conflitto_tipo` (`tipo`),
  ADD KEY `idx_conflitto_livello` (`livello`),
  ADD KEY `fk_conflitto_docente` (`id_docente`),
  ADD KEY `fk_conflitto_classe` (`id_classe`),
  ADD KEY `fk_conflitto_materia` (`id_materia`),
  ADD KEY `fk_conflitto_aula` (`id_aula`),
  ADD KEY `fk_conflitto_slot` (`id_slot`);

--
-- Indici per le tabelle `orario_docente_disponibilita`
--
ALTER TABLE `orario_docente_disponibilita`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_docente_slot` (`id_scenario`,`id_docente`,`id_slot`),
  ADD KEY `idx_docente_disp_docente` (`id_docente`),
  ADD KEY `idx_docente_disp_slot` (`id_slot`);

--
-- Indici per le tabelle `orario_docente_insegna_scenario`
--
ALTER TABLE `orario_docente_insegna_scenario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_orario_assegnazione` (`id_scenario`,`id_classe`,`id_materia`,`docente_key`),
  ADD KEY `idx_odi_scenario` (`id_scenario`),
  ADD KEY `idx_odi_docente` (`id_docente`),
  ADD KEY `idx_odi_classe` (`id_classe`),
  ADD KEY `idx_odi_materia` (`id_materia`),
  ADD KEY `idx_odi_docente_key` (`docente_key`);

--
-- Indici per le tabelle `orario_fabbisogno_classe`
--
ALTER TABLE `orario_fabbisogno_classe`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fabbisogno_scenario` (`id_scenario`),
  ADD KEY `idx_fabbisogno_classe` (`id_classe`),
  ADD KEY `idx_fabbisogno_docente` (`id_docente`),
  ADD KEY `idx_fabbisogno_materia` (`id_materia`),
  ADD KEY `idx_fabbisogno_aula` (`id_aula_preferita`);

--
-- Indici per le tabelle `orario_import_classe_alias`
--
ALTER TABLE `orario_import_classe_alias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_alias_classe` (`alias_classe`),
  ADD KEY `fk_import_alias_classe` (`id_classe`);

--
-- Indici per le tabelle `orario_import_docente_temporaneo_alias`
--
ALTER TABLE `orario_import_docente_temporaneo_alias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_docente_temporaneo` (`docente_temporaneo`),
  ADD KEY `fk_doc_temp_alias_docente` (`id_docente`);

--
-- Indici per le tabelle `orario_import_materia_alias`
--
ALTER TABLE `orario_import_materia_alias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_alias_materia` (`alias_materia`),
  ADD KEY `fk_orario_import_materia_alias` (`id_materia`);

--
-- Indici per le tabelle `orario_log`
--
ALTER TABLE `orario_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_orario_log_scenario` (`id_scenario`),
  ADD KEY `idx_orario_log_livello` (`livello`),
  ADD KEY `idx_orario_log_azione` (`azione`);

--
-- Indici per le tabelle `orario_materia_aula`
--
ALTER TABLE `orario_materia_aula`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_materia_aula` (`id_scenario`,`id_materia`,`id_aula`),
  ADD KEY `idx_materia_aula_materia` (`id_materia`),
  ADD KEY `idx_materia_aula_aula` (`id_aula`);

--
-- Indici per le tabelle `orario_materia_scambio_periodo`
--
ALTER TABLE `orario_materia_scambio_periodo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_scambio_piano` (`id_piano_orario`),
  ADD KEY `idx_scambio_materia_a` (`id_materia_a`),
  ADD KEY `idx_scambio_materia_b` (`id_materia_b`);

--
-- Indici per le tabelle `orario_monteore_classe_override`
--
ALTER TABLE `orario_monteore_classe_override`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_override_classe_materia` (`id_anno_scolastico`,`id_classe`,`id_materia`),
  ADD KEY `idx_override_classe` (`id_classe`),
  ADD KEY `idx_override_materia` (`id_materia`);

--
-- Indici per le tabelle `orario_piano_materia_aula_richiesta`
--
ALTER TABLE `orario_piano_materia_aula_richiesta`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_richiesta_piano_materia` (`id_piano_orario_materia`),
  ADD KEY `idx_richiesta_tipo_ora` (`tipo_ora`),
  ADD KEY `idx_richiesta_aula` (`id_aula`),
  ADD KEY `idx_richiesta_gruppo` (`id_gruppo_aula`);

--
-- Indici per le tabelle `orario_piano_orario`
--
ALTER TABLE `orario_piano_orario`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_piano_anno` (`id_anno_scolastico`),
  ADD KEY `idx_piano_anno_classe` (`anno_classe`),
  ADD KEY `idx_piano_indirizzo` (`id_indirizzo`),
  ADD KEY `idx_piano_attivo` (`attivo`);

--
-- Indici per le tabelle `orario_piano_orario_classe_alias`
--
ALTER TABLE `orario_piano_orario_classe_alias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_piano_alias_classe` (`id_piano_orario`,`alias_classe`),
  ADD KEY `idx_poca_piano` (`id_piano_orario`),
  ADD KEY `idx_poca_alias` (`alias_classe`),
  ADD KEY `idx_poca_classe` (`id_classe`);

--
-- Indici per le tabelle `orario_piano_orario_materia`
--
ALTER TABLE `orario_piano_orario_materia`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_piano_materia` (`id_piano_orario`,`id_materia`),
  ADD KEY `idx_piano_materia_materia` (`id_materia`);

--
-- Indici per le tabelle `orario_piano_orario_materia_blocco`
--
ALTER TABLE `orario_piano_orario_materia_blocco`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_blocco_tipo` (`id_piano_orario_materia`,`tipo_ora`),
  ADD KEY `idx_blocco_piano_materia` (`id_piano_orario_materia`);

--
-- Indici per le tabelle `orario_scenario`
--
ALTER TABLE `orario_scenario`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_orario_scenario_anno` (`id_anno_scolastico`),
  ADD KEY `idx_orario_scenario_stato` (`stato`);

--
-- Indici per le tabelle `orario_slot`
--
ALTER TABLE `orario_slot`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_orario_slot` (`id_scenario`,`giorno`,`ora_index`),
  ADD KEY `idx_orario_slot_scenario` (`id_scenario`),
  ADD KEY `idx_orario_slot_giorno` (`giorno`);

--
-- Indici per le tabelle `orario_soluzione`
--
ALTER TABLE `orario_soluzione`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_soluzione_scenario` (`id_scenario`),
  ADD KEY `idx_soluzione_stato` (`stato`);

--
-- Indici per le tabelle `orario_soluzione_lezione`
--
ALTER TABLE `orario_soluzione_lezione`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_lezione_soluzione` (`id_soluzione`),
  ADD KEY `idx_lezione_scenario` (`id_scenario`),
  ADD KEY `idx_lezione_classe_slot` (`id_classe`,`id_slot`),
  ADD KEY `idx_lezione_docente_slot` (`id_docente`,`id_slot`),
  ADD KEY `idx_lezione_aula_slot` (`id_aula`,`id_slot`),
  ADD KEY `idx_lezione_materia` (`id_materia`),
  ADD KEY `fk_lezione_slot` (`id_slot`);

--
-- Indici per le tabelle `orario_vincolo`
--
ALTER TABLE `orario_vincolo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vincolo_scenario` (`id_scenario`),
  ADD KEY `idx_vincolo_tipo` (`tipo`),
  ADD KEY `idx_vincolo_docente` (`id_docente`),
  ADD KEY `idx_vincolo_classe` (`id_classe`),
  ADD KEY `idx_vincolo_materia` (`id_materia`),
  ADD KEY `idx_vincolo_aula` (`id_aula`),
  ADD KEY `idx_vincolo_slot` (`id_slot`);

--
-- Indici per le tabelle `orario_vincolo_bilanciamento_pomeriggi`
--
ALTER TABLE `orario_vincolo_bilanciamento_pomeriggi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_bil_pom_scenario` (`id_scenario`);

--
-- Indici per le tabelle `orario_vincolo_pomeriggi_gruppo`
--
ALTER TABLE `orario_vincolo_pomeriggi_gruppo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vpg_scenario` (`id_scenario`);

--
-- AUTO_INCREMENT per le tabelle scaricate
--

--
-- AUTO_INCREMENT per la tabella `orario_alternanza_gruppo`
--
ALTER TABLE `orario_alternanza_gruppo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_alternanza_riga`
--
ALTER TABLE `orario_alternanza_riga`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_aula_disponibilita`
--
ALTER TABLE `orario_aula_disponibilita`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_aula_gruppo`
--
ALTER TABLE `orario_aula_gruppo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_aula_gruppo_aula`
--
ALTER TABLE `orario_aula_gruppo_aula`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_blocco_sequenza_catalogo`
--
ALTER TABLE `orario_blocco_sequenza_catalogo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_calendario_giorno_speciale`
--
ALTER TABLE `orario_calendario_giorno_speciale`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_calendario_scolastico`
--
ALTER TABLE `orario_calendario_scolastico`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_classe_articolata_classe`
--
ALTER TABLE `orario_classe_articolata_classe`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_classe_articolata_gruppo`
--
ALTER TABLE `orario_classe_articolata_gruppo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_classe_articolata_gruppo_materie`
--
ALTER TABLE `orario_classe_articolata_gruppo_materie`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_classe_articolata_gruppo_materie_riga`
--
ALTER TABLE `orario_classe_articolata_gruppo_materie_riga`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_classe_articolata_materia`
--
ALTER TABLE `orario_classe_articolata_materia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_classe_articolata_sincronizzazione`
--
ALTER TABLE `orario_classe_articolata_sincronizzazione`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_classe_aula`
--
ALTER TABLE `orario_classe_aula`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_classe_piano_orario`
--
ALTER TABLE `orario_classe_piano_orario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_classe_slot_vincolo`
--
ALTER TABLE `orario_classe_slot_vincolo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_classe_vincolo`
--
ALTER TABLE `orario_classe_vincolo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_conflitto`
--
ALTER TABLE `orario_conflitto`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_docente_disponibilita`
--
ALTER TABLE `orario_docente_disponibilita`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_docente_insegna_scenario`
--
ALTER TABLE `orario_docente_insegna_scenario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_fabbisogno_classe`
--
ALTER TABLE `orario_fabbisogno_classe`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_import_classe_alias`
--
ALTER TABLE `orario_import_classe_alias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_import_docente_temporaneo_alias`
--
ALTER TABLE `orario_import_docente_temporaneo_alias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_import_materia_alias`
--
ALTER TABLE `orario_import_materia_alias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_log`
--
ALTER TABLE `orario_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_materia_aula`
--
ALTER TABLE `orario_materia_aula`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_materia_scambio_periodo`
--
ALTER TABLE `orario_materia_scambio_periodo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_monteore_classe_override`
--
ALTER TABLE `orario_monteore_classe_override`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_piano_materia_aula_richiesta`
--
ALTER TABLE `orario_piano_materia_aula_richiesta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_piano_orario`
--
ALTER TABLE `orario_piano_orario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_piano_orario_classe_alias`
--
ALTER TABLE `orario_piano_orario_classe_alias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_piano_orario_materia`
--
ALTER TABLE `orario_piano_orario_materia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_piano_orario_materia_blocco`
--
ALTER TABLE `orario_piano_orario_materia_blocco`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_scenario`
--
ALTER TABLE `orario_scenario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_slot`
--
ALTER TABLE `orario_slot`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_soluzione`
--
ALTER TABLE `orario_soluzione`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_soluzione_lezione`
--
ALTER TABLE `orario_soluzione_lezione`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_vincolo`
--
ALTER TABLE `orario_vincolo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_vincolo_bilanciamento_pomeriggi`
--
ALTER TABLE `orario_vincolo_bilanciamento_pomeriggi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `orario_vincolo_pomeriggi_gruppo`
--
ALTER TABLE `orario_vincolo_pomeriggi_gruppo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Limiti per le tabelle scaricate
--

--
-- Limiti per la tabella `orario_alternanza_gruppo`
--
ALTER TABLE `orario_alternanza_gruppo`
  ADD CONSTRAINT `fk_alt_gruppo_scenario` FOREIGN KEY (`id_scenario`) REFERENCES `orario_scenario` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_alternanza_riga`
--
ALTER TABLE `orario_alternanza_riga`
  ADD CONSTRAINT `fk_alt_riga_classe` FOREIGN KEY (`id_classe`) REFERENCES `classi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_alt_riga_gruppo` FOREIGN KEY (`id_gruppo`) REFERENCES `orario_alternanza_gruppo` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_alt_riga_materia_p1` FOREIGN KEY (`id_materia_periodo_1`) REFERENCES `materia` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_alt_riga_materia_p2` FOREIGN KEY (`id_materia_periodo_2`) REFERENCES `materia` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_aula_disponibilita`
--
ALTER TABLE `orario_aula_disponibilita`
  ADD CONSTRAINT `fk_aula_disp_aula` FOREIGN KEY (`id_aula`) REFERENCES `aule` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_aula_disp_scenario` FOREIGN KEY (`id_scenario`) REFERENCES `orario_scenario` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_aula_disp_slot` FOREIGN KEY (`id_slot`) REFERENCES `orario_slot` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_aula_gruppo_aula`
--
ALTER TABLE `orario_aula_gruppo_aula`
  ADD CONSTRAINT `fk_gruppo_aula_aula` FOREIGN KEY (`id_aula`) REFERENCES `aule` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_gruppo_aula_gruppo` FOREIGN KEY (`id_gruppo`) REFERENCES `orario_aula_gruppo` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_calendario_giorno_speciale`
--
ALTER TABLE `orario_calendario_giorno_speciale`
  ADD CONSTRAINT `fk_cal_giorno_scenario` FOREIGN KEY (`id_scenario`) REFERENCES `orario_scenario` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_calendario_scolastico`
--
ALTER TABLE `orario_calendario_scolastico`
  ADD CONSTRAINT `fk_calendario_scenario` FOREIGN KEY (`id_scenario`) REFERENCES `orario_scenario` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_classe_articolata_classe`
--
ALTER TABLE `orario_classe_articolata_classe`
  ADD CONSTRAINT `fk_art_classe_classe` FOREIGN KEY (`id_classe`) REFERENCES `classi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_art_classe_gruppo` FOREIGN KEY (`id_gruppo`) REFERENCES `orario_classe_articolata_gruppo` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_classe_articolata_gruppo`
--
ALTER TABLE `orario_classe_articolata_gruppo`
  ADD CONSTRAINT `fk_art_gruppo_scenario` FOREIGN KEY (`id_scenario`) REFERENCES `orario_scenario` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_classe_articolata_gruppo_materie`
--
ALTER TABLE `orario_classe_articolata_gruppo_materie`
  ADD CONSTRAINT `fk_art_gm_classe` FOREIGN KEY (`id_classe`) REFERENCES `classi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_art_gm_gruppo` FOREIGN KEY (`id_gruppo_articolato`) REFERENCES `orario_classe_articolata_gruppo` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_classe_articolata_gruppo_materie_riga`
--
ALTER TABLE `orario_classe_articolata_gruppo_materie_riga`
  ADD CONSTRAINT `fk_art_gm_riga_gruppo` FOREIGN KEY (`id_gruppo_materie`) REFERENCES `orario_classe_articolata_gruppo_materie` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_art_gm_riga_materia` FOREIGN KEY (`id_materia`) REFERENCES `materia` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_classe_articolata_materia`
--
ALTER TABLE `orario_classe_articolata_materia`
  ADD CONSTRAINT `fk_art_materia_gruppo` FOREIGN KEY (`id_gruppo`) REFERENCES `orario_classe_articolata_gruppo` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_art_materia_materia` FOREIGN KEY (`id_materia`) REFERENCES `materia` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_classe_articolata_sincronizzazione`
--
ALTER TABLE `orario_classe_articolata_sincronizzazione`
  ADD CONSTRAINT `fk_art_sync_gm_a` FOREIGN KEY (`id_gruppo_materie_a`) REFERENCES `orario_classe_articolata_gruppo_materie` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_art_sync_gm_b` FOREIGN KEY (`id_gruppo_materie_b`) REFERENCES `orario_classe_articolata_gruppo_materie` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_art_sync_gruppo` FOREIGN KEY (`id_gruppo_articolato`) REFERENCES `orario_classe_articolata_gruppo` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_classe_aula`
--
ALTER TABLE `orario_classe_aula`
  ADD CONSTRAINT `fk_classe_aula_aula` FOREIGN KEY (`id_aula`) REFERENCES `aule` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_classe_aula_classe` FOREIGN KEY (`id_classe`) REFERENCES `classi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_classe_aula_scenario` FOREIGN KEY (`id_scenario`) REFERENCES `orario_scenario` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_classe_piano_orario`
--
ALTER TABLE `orario_classe_piano_orario`
  ADD CONSTRAINT `fk_classe_piano_classe` FOREIGN KEY (`id_classe`) REFERENCES `classi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_classe_piano_piano` FOREIGN KEY (`id_piano_orario`) REFERENCES `orario_piano_orario` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_classe_slot_vincolo`
--
ALTER TABLE `orario_classe_slot_vincolo`
  ADD CONSTRAINT `fk_csv_classe` FOREIGN KEY (`id_classe`) REFERENCES `classi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_csv_scenario` FOREIGN KEY (`id_scenario`) REFERENCES `orario_scenario` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_csv_slot` FOREIGN KEY (`id_slot`) REFERENCES `orario_slot` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_classe_vincolo`
--
ALTER TABLE `orario_classe_vincolo`
  ADD CONSTRAINT `fk_classe_vincolo_classe` FOREIGN KEY (`id_classe`) REFERENCES `classi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_classe_vincolo_scenario` FOREIGN KEY (`id_scenario`) REFERENCES `orario_scenario` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_conflitto`
--
ALTER TABLE `orario_conflitto`
  ADD CONSTRAINT `fk_conflitto_aula` FOREIGN KEY (`id_aula`) REFERENCES `aule` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_conflitto_classe` FOREIGN KEY (`id_classe`) REFERENCES `classi` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_conflitto_docente` FOREIGN KEY (`id_docente`) REFERENCES `docente` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_conflitto_materia` FOREIGN KEY (`id_materia`) REFERENCES `materia` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_conflitto_scenario` FOREIGN KEY (`id_scenario`) REFERENCES `orario_scenario` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_conflitto_slot` FOREIGN KEY (`id_slot`) REFERENCES `orario_slot` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_conflitto_soluzione` FOREIGN KEY (`id_soluzione`) REFERENCES `orario_soluzione` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_docente_disponibilita`
--
ALTER TABLE `orario_docente_disponibilita`
  ADD CONSTRAINT `fk_docente_disp_docente` FOREIGN KEY (`id_docente`) REFERENCES `docente` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_docente_disp_scenario` FOREIGN KEY (`id_scenario`) REFERENCES `orario_scenario` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_docente_disp_slot` FOREIGN KEY (`id_slot`) REFERENCES `orario_slot` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_docente_insegna_scenario`
--
ALTER TABLE `orario_docente_insegna_scenario`
  ADD CONSTRAINT `fk_odi_classe` FOREIGN KEY (`id_classe`) REFERENCES `classi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_odi_docente` FOREIGN KEY (`id_docente`) REFERENCES `docente` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_odi_materia` FOREIGN KEY (`id_materia`) REFERENCES `materia` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_odi_scenario` FOREIGN KEY (`id_scenario`) REFERENCES `orario_scenario` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_fabbisogno_classe`
--
ALTER TABLE `orario_fabbisogno_classe`
  ADD CONSTRAINT `fk_fabbisogno_aula` FOREIGN KEY (`id_aula_preferita`) REFERENCES `aule` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_fabbisogno_classe` FOREIGN KEY (`id_classe`) REFERENCES `classi` (`id`),
  ADD CONSTRAINT `fk_fabbisogno_docente` FOREIGN KEY (`id_docente`) REFERENCES `docente` (`id`),
  ADD CONSTRAINT `fk_fabbisogno_materia` FOREIGN KEY (`id_materia`) REFERENCES `materia` (`id`),
  ADD CONSTRAINT `fk_fabbisogno_scenario` FOREIGN KEY (`id_scenario`) REFERENCES `orario_scenario` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_import_classe_alias`
--
ALTER TABLE `orario_import_classe_alias`
  ADD CONSTRAINT `fk_import_alias_classe` FOREIGN KEY (`id_classe`) REFERENCES `classi` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_import_docente_temporaneo_alias`
--
ALTER TABLE `orario_import_docente_temporaneo_alias`
  ADD CONSTRAINT `fk_doc_temp_alias_docente` FOREIGN KEY (`id_docente`) REFERENCES `docente` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_import_materia_alias`
--
ALTER TABLE `orario_import_materia_alias`
  ADD CONSTRAINT `fk_orario_import_materia_alias` FOREIGN KEY (`id_materia`) REFERENCES `materia` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_log`
--
ALTER TABLE `orario_log`
  ADD CONSTRAINT `fk_orario_log_scenario` FOREIGN KEY (`id_scenario`) REFERENCES `orario_scenario` (`id`) ON DELETE SET NULL;

--
-- Limiti per la tabella `orario_materia_aula`
--
ALTER TABLE `orario_materia_aula`
  ADD CONSTRAINT `fk_materia_aula_aula` FOREIGN KEY (`id_aula`) REFERENCES `aule` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_materia_aula_materia` FOREIGN KEY (`id_materia`) REFERENCES `materia` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_materia_aula_scenario` FOREIGN KEY (`id_scenario`) REFERENCES `orario_scenario` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_materia_scambio_periodo`
--
ALTER TABLE `orario_materia_scambio_periodo`
  ADD CONSTRAINT `fk_scambio_materia_a` FOREIGN KEY (`id_materia_a`) REFERENCES `materia` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_scambio_materia_b` FOREIGN KEY (`id_materia_b`) REFERENCES `materia` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_scambio_piano` FOREIGN KEY (`id_piano_orario`) REFERENCES `orario_piano_orario` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_monteore_classe_override`
--
ALTER TABLE `orario_monteore_classe_override`
  ADD CONSTRAINT `fk_override_classe` FOREIGN KEY (`id_classe`) REFERENCES `classi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_override_materia` FOREIGN KEY (`id_materia`) REFERENCES `materia` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_piano_materia_aula_richiesta`
--
ALTER TABLE `orario_piano_materia_aula_richiesta`
  ADD CONSTRAINT `fk_richiesta_aula` FOREIGN KEY (`id_aula`) REFERENCES `aule` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_richiesta_gruppo` FOREIGN KEY (`id_gruppo_aula`) REFERENCES `orario_aula_gruppo` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_richiesta_piano_materia` FOREIGN KEY (`id_piano_orario_materia`) REFERENCES `orario_piano_orario_materia` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_piano_orario_classe_alias`
--
ALTER TABLE `orario_piano_orario_classe_alias`
  ADD CONSTRAINT `fk_poca_classe` FOREIGN KEY (`id_classe`) REFERENCES `classi` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_poca_piano` FOREIGN KEY (`id_piano_orario`) REFERENCES `orario_piano_orario` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_piano_orario_materia`
--
ALTER TABLE `orario_piano_orario_materia`
  ADD CONSTRAINT `fk_piano_materia_materia` FOREIGN KEY (`id_materia`) REFERENCES `materia` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_piano_materia_piano` FOREIGN KEY (`id_piano_orario`) REFERENCES `orario_piano_orario` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_piano_orario_materia_blocco`
--
ALTER TABLE `orario_piano_orario_materia_blocco`
  ADD CONSTRAINT `fk_blocco_piano_materia` FOREIGN KEY (`id_piano_orario_materia`) REFERENCES `orario_piano_orario_materia` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_slot`
--
ALTER TABLE `orario_slot`
  ADD CONSTRAINT `fk_orario_slot_scenario` FOREIGN KEY (`id_scenario`) REFERENCES `orario_scenario` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_soluzione`
--
ALTER TABLE `orario_soluzione`
  ADD CONSTRAINT `fk_soluzione_scenario` FOREIGN KEY (`id_scenario`) REFERENCES `orario_scenario` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_soluzione_lezione`
--
ALTER TABLE `orario_soluzione_lezione`
  ADD CONSTRAINT `fk_lezione_aula` FOREIGN KEY (`id_aula`) REFERENCES `aule` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_lezione_classe` FOREIGN KEY (`id_classe`) REFERENCES `classi` (`id`),
  ADD CONSTRAINT `fk_lezione_docente` FOREIGN KEY (`id_docente`) REFERENCES `docente` (`id`),
  ADD CONSTRAINT `fk_lezione_materia` FOREIGN KEY (`id_materia`) REFERENCES `materia` (`id`),
  ADD CONSTRAINT `fk_lezione_scenario` FOREIGN KEY (`id_scenario`) REFERENCES `orario_scenario` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lezione_slot` FOREIGN KEY (`id_slot`) REFERENCES `orario_slot` (`id`),
  ADD CONSTRAINT `fk_lezione_soluzione` FOREIGN KEY (`id_soluzione`) REFERENCES `orario_soluzione` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_vincolo`
--
ALTER TABLE `orario_vincolo`
  ADD CONSTRAINT `fk_vincolo_aula` FOREIGN KEY (`id_aula`) REFERENCES `aule` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vincolo_classe` FOREIGN KEY (`id_classe`) REFERENCES `classi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vincolo_docente` FOREIGN KEY (`id_docente`) REFERENCES `docente` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vincolo_materia` FOREIGN KEY (`id_materia`) REFERENCES `materia` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vincolo_scenario` FOREIGN KEY (`id_scenario`) REFERENCES `orario_scenario` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vincolo_slot` FOREIGN KEY (`id_slot`) REFERENCES `orario_slot` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_vincolo_bilanciamento_pomeriggi`
--
ALTER TABLE `orario_vincolo_bilanciamento_pomeriggi`
  ADD CONSTRAINT `fk_bil_pom_scenario` FOREIGN KEY (`id_scenario`) REFERENCES `orario_scenario` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `orario_vincolo_pomeriggi_gruppo`
--
ALTER TABLE `orario_vincolo_pomeriggi_gruppo`
  ADD CONSTRAINT `fk_vpg_scenario` FOREIGN KEY (`id_scenario`) REFERENCES `orario_scenario` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
