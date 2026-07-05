<?php

/**
 * Pratiche entrata/uscita studenti.
 *
 *  This file is part of GestOre
 *  @author     OpenAI Codex
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/mastercom/tabelloni_lib.php';
require_once __DIR__ . '/scuoleIstitutiLib.php';
require_once __DIR__ . '/iscrizioniPrimeLib.php';

function studentiMovimentiColumnExists(string $table, string $column): bool
{
    $row = dbGetFirst("SHOW COLUMNS FROM `$table` LIKE " . dbQ($column));
    return $row !== null;
}

function studentiMovimentiEnsureTables(): void
{
    scuoleIstitutiEnsureTable();

    dbExec("
        CREATE TABLE IF NOT EXISTS `studenti_movimenti_pratiche` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `tipo_pratica` ENUM('bocciato_reiscrizione','uscita','ritiro','entrata') NOT NULL,
            `stato_pratica` VARCHAR(60) NOT NULL DEFAULT 'da_verificare',
            `id_studente` INT NULL,
            `cognome` VARCHAR(120) NULL,
            `nome` VARCHAR(120) NULL,
            `codice_fiscale` VARCHAR(20) NULL,
            `data_nascita` DATE NULL,
            `luogo_nascita` VARCHAR(150) NULL,
            `responsabile_1_tipo` VARCHAR(50) NULL,
            `responsabile_1_cognome` VARCHAR(100) NULL,
            `responsabile_1_nome` VARCHAR(100) NULL,
            `responsabile_1_codice_fiscale` VARCHAR(16) NULL,
            `email_genitore_1` VARCHAR(255) NULL,
            `telefono_genitore_1` VARCHAR(50) NULL,
            `responsabile_2_tipo` VARCHAR(50) NULL,
            `responsabile_2_cognome` VARCHAR(100) NULL,
            `responsabile_2_nome` VARCHAR(100) NULL,
            `responsabile_2_codice_fiscale` VARCHAR(16) NULL,
            `email_genitore_2` VARCHAR(255) NULL,
            `telefono_genitore_2` VARCHAR(50) NULL,
            `classe_origine` VARCHAR(80) NULL,
            `classe_richiesta` VARCHAR(80) NULL,
            `anno_corso` TINYINT NULL,
            `id_istituto_destinazione` INT NULL,
            `scuola_destinazione` VARCHAR(255) NULL,
            `indirizzo_destinazione` VARCHAR(255) NULL,
            `id_indirizzo_gestore` INT NULL,
            `id_istituto_provenienza` INT NULL,
            `scuola_provenienza` VARCHAR(255) NULL,
            `indirizzo_provenienza` VARCHAR(255) NULL,
            `doppio_bocciato` TINYINT(1) NOT NULL DEFAULT 0,
            `doppio_bocciato_non_consecutivo` TINYINT(1) NOT NULL DEFAULT 0,
            `esami_integrativi` TINYINT(1) NOT NULL DEFAULT 0,
            `esami_integrativi_note` TEXT NULL,
            `carenze_presenti` TINYINT(1) NOT NULL DEFAULT 0,
            `carenze_note` TEXT NULL,
            `doc_modulo_iscrizione` VARCHAR(30) NOT NULL DEFAULT 'mancante',
            `doc_nulla_osta_entrata` VARCHAR(30) NOT NULL DEFAULT 'mancante',
            `doc_pagella_precedente` VARCHAR(30) NOT NULL DEFAULT 'mancante',
            `doc_carenze` VARCHAR(30) NOT NULL DEFAULT 'non_necessario',
            `doc_esami_integrativi` VARCHAR(30) NOT NULL DEFAULT 'non_necessario',
            `fonte` VARCHAR(40) NOT NULL DEFAULT 'manuale',
            `id_pratica_iscrizione` INT NULL,
            `id_cambio_scuola_iscrizione` INT NULL,
            `note` TEXT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_movimenti_tipo_stato` (`tipo_pratica`, `stato_pratica`),
            KEY `idx_movimenti_studente` (`id_studente`),
            KEY `idx_movimenti_cf` (`codice_fiscale`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");

    if (!studentiMovimentiColumnExists('studenti_movimenti_pratiche', 'anno_corso')) {
        dbExec("ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `anno_corso` TINYINT NULL AFTER `classe_richiesta`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_pratiche', 'fonte')) {
        dbExec("ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `fonte` VARCHAR(40) NOT NULL DEFAULT 'manuale' AFTER `esami_integrativi`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_pratiche', 'id_pratica_iscrizione')) {
        dbExec("ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `id_pratica_iscrizione` INT NULL AFTER `fonte`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_pratiche', 'id_cambio_scuola_iscrizione')) {
        dbExec("ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `id_cambio_scuola_iscrizione` INT NULL AFTER `id_pratica_iscrizione`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_pratiche', 'id_istituto_destinazione')) {
        dbExec("ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `id_istituto_destinazione` INT NULL AFTER `anno_corso`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_pratiche', 'indirizzo_destinazione')) {
        dbExec("ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `indirizzo_destinazione` VARCHAR(255) NULL AFTER `scuola_destinazione`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_pratiche', 'id_indirizzo_gestore')) {
        dbExec("ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `id_indirizzo_gestore` INT NULL AFTER `indirizzo_destinazione`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_pratiche', 'id_istituto_provenienza')) {
        dbExec("ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `id_istituto_provenienza` INT NULL AFTER `indirizzo_destinazione`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_pratiche', 'indirizzo_provenienza')) {
        dbExec("ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `indirizzo_provenienza` VARCHAR(255) NULL AFTER `scuola_provenienza`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_pratiche', 'doppio_bocciato')) {
        dbExec("ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `doppio_bocciato` TINYINT(1) NOT NULL DEFAULT 0 AFTER `indirizzo_provenienza`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_pratiche', 'doppio_bocciato_non_consecutivo')) {
        dbExec("ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `doppio_bocciato_non_consecutivo` TINYINT(1) NOT NULL DEFAULT 0 AFTER `doppio_bocciato`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_pratiche', 'data_nascita')) {
        dbExec("ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `data_nascita` DATE NULL AFTER `codice_fiscale`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_pratiche', 'luogo_nascita')) {
        dbExec("ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `luogo_nascita` VARCHAR(150) NULL AFTER `data_nascita`");
    }
    $entrataColumns = [
        'esami_integrativi_note' => "ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `esami_integrativi_note` TEXT NULL AFTER `esami_integrativi`",
        'carenze_presenti' => "ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `carenze_presenti` TINYINT(1) NOT NULL DEFAULT 0 AFTER `esami_integrativi_note`",
        'carenze_note' => "ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `carenze_note` TEXT NULL AFTER `carenze_presenti`",
        'doc_modulo_iscrizione' => "ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `doc_modulo_iscrizione` VARCHAR(30) NOT NULL DEFAULT 'mancante' AFTER `carenze_note`",
        'doc_nulla_osta_entrata' => "ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `doc_nulla_osta_entrata` VARCHAR(30) NOT NULL DEFAULT 'mancante' AFTER `doc_modulo_iscrizione`",
        'doc_pagella_precedente' => "ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `doc_pagella_precedente` VARCHAR(30) NOT NULL DEFAULT 'mancante' AFTER `doc_nulla_osta_entrata`",
        'doc_carenze' => "ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `doc_carenze` VARCHAR(30) NOT NULL DEFAULT 'non_necessario' AFTER `doc_pagella_precedente`",
        'doc_esami_integrativi' => "ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `doc_esami_integrativi` VARCHAR(30) NOT NULL DEFAULT 'non_necessario' AFTER `doc_carenze`",
    ];
    foreach ($entrataColumns as $column => $alterSql) {
        if (!studentiMovimentiColumnExists('studenti_movimenti_pratiche', $column)) {
            dbExec($alterSql);
        }
    }
    $parentColumns = [
        'responsabile_1_tipo' => "ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `responsabile_1_tipo` VARCHAR(50) NULL AFTER `luogo_nascita`",
        'responsabile_1_cognome' => "ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `responsabile_1_cognome` VARCHAR(100) NULL AFTER `responsabile_1_tipo`",
        'responsabile_1_nome' => "ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `responsabile_1_nome` VARCHAR(100) NULL AFTER `responsabile_1_cognome`",
        'responsabile_1_codice_fiscale' => "ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `responsabile_1_codice_fiscale` VARCHAR(16) NULL AFTER `responsabile_1_nome`",
        'email_genitore_1' => "ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `email_genitore_1` VARCHAR(255) NULL AFTER `responsabile_1_codice_fiscale`",
        'telefono_genitore_1' => "ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `telefono_genitore_1` VARCHAR(50) NULL AFTER `email_genitore_1`",
        'responsabile_2_tipo' => "ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `responsabile_2_tipo` VARCHAR(50) NULL AFTER `telefono_genitore_1`",
        'responsabile_2_cognome' => "ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `responsabile_2_cognome` VARCHAR(100) NULL AFTER `responsabile_2_tipo`",
        'responsabile_2_nome' => "ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `responsabile_2_nome` VARCHAR(100) NULL AFTER `responsabile_2_cognome`",
        'responsabile_2_codice_fiscale' => "ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `responsabile_2_codice_fiscale` VARCHAR(16) NULL AFTER `responsabile_2_nome`",
        'email_genitore_2' => "ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `email_genitore_2` VARCHAR(255) NULL AFTER `responsabile_2_codice_fiscale`",
        'telefono_genitore_2' => "ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `telefono_genitore_2` VARCHAR(50) NULL AFTER `email_genitore_2`",
    ];
    foreach ($parentColumns as $column => $alterSql) {
        if (!studentiMovimentiColumnExists('studenti_movimenti_pratiche', $column)) {
            dbExec($alterSql);
        }
    }

    dbExec("
        CREATE TABLE IF NOT EXISTS `studenti_movimenti_allegati` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `id_pratica` INT NOT NULL,
            `id_evento` INT NULL,
            `tipo_allegato` VARCHAR(80) NOT NULL DEFAULT 'documento',
            `nome_file` VARCHAR(255) NOT NULL,
            `path_file` VARCHAR(500) NOT NULL,
            `mime_type` VARCHAR(120) NULL,
            `dimensione` INT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_movimenti_allegati_pratica` (`id_pratica`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");
    if (!studentiMovimentiColumnExists('studenti_movimenti_allegati', 'id_evento')) {
        dbExec("ALTER TABLE studenti_movimenti_allegati ADD COLUMN `id_evento` INT NULL AFTER `id_pratica`");
    }

    dbExec("
        CREATE TABLE IF NOT EXISTS `studenti_movimenti_eventi` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `id_pratica` INT NOT NULL,
            `tipo_evento` VARCHAR(80) NOT NULL DEFAULT 'salvataggio',
            `id_colloquio_genitori` INT NULL,
            `descrizione` VARCHAR(255) NULL,
            `stato_pratica` VARCHAR(60) NULL,
            `tipo_pratica` VARCHAR(60) NULL,
            `id_istituto_destinazione` INT NULL,
            `scuola_destinazione` VARCHAR(255) NULL,
            `indirizzo_destinazione` VARCHAR(255) NULL,
            `id_istituto_provenienza` INT NULL,
            `scuola_provenienza` VARCHAR(255) NULL,
            `indirizzo_provenienza` VARCHAR(255) NULL,
            `tipo_allegato` VARCHAR(80) NULL,
            `allegato_path` VARCHAR(500) NULL,
            `allegato_original_name` VARCHAR(255) NULL,
            `note` TEXT NULL,
            `created_by` VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_movimenti_eventi_pratica` (`id_pratica`),
            KEY `idx_movimenti_eventi_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");
    if (!studentiMovimentiColumnExists('studenti_movimenti_eventi', 'id_istituto_destinazione')) {
        dbExec("ALTER TABLE studenti_movimenti_eventi ADD COLUMN `id_istituto_destinazione` INT NULL AFTER `tipo_pratica`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_eventi', 'indirizzo_destinazione')) {
        dbExec("ALTER TABLE studenti_movimenti_eventi ADD COLUMN `indirizzo_destinazione` VARCHAR(255) NULL AFTER `scuola_destinazione`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_eventi', 'id_istituto_provenienza')) {
        dbExec("ALTER TABLE studenti_movimenti_eventi ADD COLUMN `id_istituto_provenienza` INT NULL AFTER `indirizzo_destinazione`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_eventi', 'indirizzo_provenienza')) {
        dbExec("ALTER TABLE studenti_movimenti_eventi ADD COLUMN `indirizzo_provenienza` VARCHAR(255) NULL AFTER `scuola_provenienza`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_eventi', 'id_colloquio_genitori')) {
        dbExec("ALTER TABLE studenti_movimenti_eventi ADD COLUMN `id_colloquio_genitori` INT NULL AFTER `tipo_evento`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_eventi', 'tipo_allegato')) {
        dbExec("ALTER TABLE studenti_movimenti_eventi ADD COLUMN `tipo_allegato` VARCHAR(80) NULL AFTER `indirizzo_provenienza`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_eventi', 'allegato_path')) {
        dbExec("ALTER TABLE studenti_movimenti_eventi ADD COLUMN `allegato_path` VARCHAR(500) NULL AFTER `tipo_allegato`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_eventi', 'allegato_original_name')) {
        dbExec("ALTER TABLE studenti_movimenti_eventi ADD COLUMN `allegato_original_name` VARCHAR(255) NULL AFTER `allegato_path`");
    }

    studentiMovimentiNormalizeInvalidStates();
    studentiMovimentiNormalizeColloquioStates();
}

function studentiMovimentiNormalizeInvalidStates(): void
{
    foreach (studentiMovimentiStatiPerTipo() as $tipoPratica => $allowedStates) {
        if (empty($allowedStates)) {
            continue;
        }
        $quotedAllowedStates = implode(',', array_map('dbQ', $allowedStates));
        dbExec("
            UPDATE studenti_movimenti_pratiche
            SET stato_pratica = " . dbQ(studentiMovimentiDefaultStato($tipoPratica)) . "
            WHERE tipo_pratica = " . dbQ($tipoPratica) . "
              AND stato_pratica NOT IN ($quotedAllowedStates)
        ");
    }
}

function studentiMovimentiNormalizeColloquioStates(): void
{
    if (!dbGetValue("SHOW TABLES LIKE 'genitori_colloqui'")) {
        return;
    }
    dbExec("
        UPDATE studenti_movimenti_pratiche p
        INNER JOIN genitori_colloqui c ON c.id_movimento = p.id
        SET p.stato_pratica = 'esami_integrativi',
            p.updated_at = GREATEST(p.updated_at, c.updated_at)
        WHERE p.tipo_pratica = 'entrata'
          AND p.stato_pratica = 'contatto_ricevuto'
          AND (c.esito = 'integrazione' OR TRIM(COALESCE(c.esami_integrativi, '')) <> '')
    ");
    dbExec("
        UPDATE studenti_movimenti_pratiche p
        INNER JOIN genitori_colloqui c ON c.id_movimento = p.id
        SET p.stato_pratica = 'colloquio_entrata',
            p.updated_at = GREATEST(p.updated_at, c.updated_at)
        WHERE p.tipo_pratica = 'entrata'
          AND p.stato_pratica = 'contatto_ricevuto'
          AND (c.esito = 'ingresso_ok' OR c.stato IN ('svolto','approvato'))
    ");
}

function studentiMovimentiTipi(): array
{
    return [
        'bocciato_reiscrizione' => 'Bocciato - reiscrizione',
        'uscita' => 'Cambio scuola / nulla osta',
        'ritiro' => 'Ritiro',
        'entrata' => 'Nuovo studente in entrata',
    ];
}

function studentiMovimentiStati(): array
{
    return [
        'da_verificare' => 'Da verificare',
        'reiscrizione_confermata' => 'Reiscrizione confermata',
        'cambia_scuola' => 'Cambia scuola',
        'si_ritira' => 'Si ritira',
        'colloquio_richiesto' => 'Colloquio richiesto',
        'colloquio_da_programmare' => 'Colloquio da programmare',
        'colloquio_programmato' => 'Colloquio programmato',
        'richiesta_nulla_osta' => 'Richiesta nulla osta ricevuta',
        'firmato_un_genitore' => 'Firmato da un responsabile',
        'firmato_entrambi' => 'Firmato da entrambi i responsabili',
        'colloquio_uscita' => 'Colloquio uscita svolto',
        'colloquio_entrata' => 'Colloquio entrata svolto',
        'nulla_osta_entrata_richiesto' => 'Nulla osta richiesto dal genitore',
        'nulla_osta_entrata_rilasciato' => 'Nulla osta rilasciato dalla scuola di provenienza',
        'nulla_osta_inviato' => 'Nulla osta inviato',
        'contatto_ricevuto' => 'Contatto ricevuto',
        'documenti_in_verifica' => 'Documenti in verifica',
        'esami_integrativi' => 'Deve fare esami integrativi',
        'idoneo_iscrizione' => 'Idoneo all iscrizione',
        'non_idoneo' => 'Non idoneo',
        'chiusa' => 'Chiusa',
        'annullata' => 'Annullata',
    ];
}

function studentiMovimentiStatiPerTipo(): array
{
    return [
        'bocciato_reiscrizione' => [
            'da_verificare',
            'reiscrizione_confermata',
            'chiusa',
            'annullata',
        ],
        'uscita' => [
            'da_verificare',
            'cambia_scuola',
            'richiesta_nulla_osta',
            'firmato_un_genitore',
            'firmato_entrambi',
            'colloquio_richiesto',
            'colloquio_da_programmare',
            'colloquio_programmato',
            'colloquio_uscita',
            'nulla_osta_inviato',
            'chiusa',
            'annullata',
        ],
        'ritiro' => [
            'da_verificare',
            'si_ritira',
            'firmato_un_genitore',
            'firmato_entrambi',
            'colloquio_richiesto',
            'colloquio_da_programmare',
            'colloquio_programmato',
            'colloquio_uscita',
            'chiusa',
            'annullata',
        ],
        'entrata' => [
            'contatto_ricevuto',
            'colloquio_richiesto',
            'colloquio_da_programmare',
            'colloquio_programmato',
            'colloquio_entrata',
            'nulla_osta_entrata_richiesto',
            'nulla_osta_entrata_rilasciato',
            'documenti_in_verifica',
            'esami_integrativi',
            'idoneo_iscrizione',
            'non_idoneo',
            'chiusa',
            'annullata',
        ],
    ];
}

function studentiMovimentiDefaultStato(string $tipoPratica): string
{
    $map = studentiMovimentiStatiPerTipo();
    return (string)($map[$tipoPratica][0] ?? 'da_verificare');
}

function studentiMovimentiH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function studentiMovimentiUpperName($value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
}

function studentiMovimentiCurrentActor(): string
{
    $name = trim((string)($GLOBALS['__utente_nome'] ?? '') . ' ' . (string)($GLOBALS['__utente_cognome'] ?? ''));
    if ($name !== '') {
        return $name;
    }
    return trim((string)($GLOBALS['__useremail'] ?? $GLOBALS['__username'] ?? ''));
}

function studentiMovimentiNormalizeDate(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    return null;
}

function studentiMovimentiDocumentStatus(string $value, string $default = 'mancante'): string
{
    $value = trim($value);
    $allowed = ['mancante', 'richiesto', 'ricevuto', 'non_necessario'];
    return in_array($value, $allowed, true) ? $value : $default;
}

function studentiMovimentiFindOrCreateStudentForEntrata(array $fields): int
{
    $cognome = trim((string)($fields['cognome'] ?? ''));
    $nome = trim((string)($fields['nome'] ?? ''));
    $cf = strtoupper(trim((string)($fields['codice_fiscale'] ?? '')));
    if ($cognome === '' || $nome === '') {
        return 0;
    }

    if ($cf !== '') {
        $studentId = intval(dbGetValue("
            SELECT id
            FROM studente
            WHERE UPPER(TRIM(codice_fiscale)) = " . dbQ($cf) . "
            LIMIT 1
        ") ?? 0);
        if ($studentId > 0) {
            return $studentId;
        }
    }

    $studentId = intval(dbGetValue("
        SELECT id
        FROM studente
        WHERE LOWER(TRIM(cognome)) = LOWER(" . dbQ($cognome) . ")
          AND LOWER(TRIM(nome)) = LOWER(" . dbQ($nome) . ")
        LIMIT 1
    ") ?? 0);
    if ($studentId > 0) {
        return $studentId;
    }

    dbExec("
        INSERT INTO studente (cognome, nome, email, username, codice_fiscale, sesso, attivo)
        VALUES (
            " . dbQ($cognome) . ",
            " . dbQ($nome) . ",
            NULL,
            '',
            " . dbQ($cf) . ",
            NULL,
            1
        )
    ");

    return intval(dblastId());
}

function studentiMovimentiSchoolYearForIscrizione(string $tipoIscrizione): string
{
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($tipoIscrizione);
    $defaultStart = intval(date('Y'));
    $default = sprintf('%04d/%02d', $defaultStart, ($defaultStart + 1) % 100);
    $latest = trim((string)(dbGetValue("
        SELECT anno_scolastico
        FROM iscrizioni_prime_pratiche
        WHERE tipo_iscrizione = " . dbQ($tipoIscrizione) . "
          AND stato <> 'annullata'
        ORDER BY updated_at DESC, id DESC
        LIMIT 1
    ") ?? ''));
    if ($latest !== '') {
        $latest = iscrizioniPrimeNormalizeSchoolYear($latest);
        if (preg_match('/^(\d{4})\//', $latest, $m) && intval($m[1]) >= $defaultStart) {
            return $latest;
        }
    }

    return $default;
}

function studentiMovimentiTipoIscrizioneForEntrata(array $fields): string
{
    $annoCorso = intval($fields['anno_corso'] ?? 0);
    if ($annoCorso <= 0) {
        $annoCorso = studentiMovimentiClassYear((string)($fields['classe_richiesta'] ?? '')) ?? 0;
    }
    if ($annoCorso <= 0) {
        $annoCorso = studentiMovimentiClassYear((string)($fields['classe_origine'] ?? '')) ?? 0;
    }
    return $annoCorso === 3 ? 'terze' : ($annoCorso === 1 ? 'prime' : '');
}

function studentiMovimentiIndirizzoNomeById(?int $id): string
{
    $id = intval($id ?? 0);
    if ($id <= 0) {
        return '';
    }
    return trim((string)(dbGetValue("SELECT nome FROM indirizzo WHERE id = " . dbI($id) . " LIMIT 1") ?? ''));
}

function studentiMovimentiUpdateIscrizioneContactsFromEntrata(int $praticaId, array $fields, bool $force = false): void
{
    if ($praticaId <= 0) {
        return;
    }
    $sets = [];
    foreach ([
        'responsabile_1_tipo',
        'responsabile_1_cognome',
        'responsabile_1_nome',
        'responsabile_1_codice_fiscale',
        'email_genitore_1',
        'telefono_genitore_1',
        'responsabile_2_tipo',
        'responsabile_2_cognome',
        'responsabile_2_nome',
        'responsabile_2_codice_fiscale',
        'email_genitore_2',
        'telefono_genitore_2',
    ] as $field) {
        $value = trim((string)($fields[$field] ?? ''));
        if ($value === '') {
            continue;
        }
        $sets[] = $field . ' = ' . ($force
            ? dbQ($value)
            : "CASE WHEN COALESCE(" . $field . ", '') = '' THEN " . dbQ($value) . " ELSE " . $field . " END");
    }
    if (!$sets) {
        return;
    }
    $sets[] = 'updated_at = NOW()';
    dbExec("
        UPDATE iscrizioni_prime_pratiche
        SET " . implode(", ", $sets) . "
        WHERE id = " . dbI($praticaId) . "
        LIMIT 1
    ");
}

function studentiMovimentiEnsureIscrizioneForEntrata(array &$fields, int $movementId = 0): void
{
    if (($fields['tipo_pratica'] ?? '') !== 'entrata') {
        return;
    }
    if (intval($fields['id_pratica_iscrizione'] ?? 0) > 0) {
        studentiMovimentiUpdateIscrizioneContactsFromEntrata(intval($fields['id_pratica_iscrizione']), $fields);
        return;
    }

    $tipoIscrizione = studentiMovimentiTipoIscrizioneForEntrata($fields);
    if (!in_array($tipoIscrizione, ['prime', 'terze'], true)) {
        return;
    }

    iscrizioniPrimeEnsureSchema();
    $cf = strtoupper(trim((string)($fields['codice_fiscale'] ?? '')));
    $cognome = studentiMovimentiUpperName($fields['cognome'] ?? '');
    $nome = studentiMovimentiUpperName($fields['nome'] ?? '');
    if ($cf === '' || $cognome === '' || $nome === '') {
        throw new RuntimeException('Per una entrata di prima o terza servono cognome, nome e codice fiscale per creare la pratica iscrizione.');
    }

    $annoScolastico = studentiMovimentiSchoolYearForIscrizione($tipoIscrizione);
    $existing = dbGetFirst("
        SELECT id, raw_prime_json
        FROM iscrizioni_prime_pratiche
        WHERE " . iscrizioniPrimeSchoolYearWhere('anno_scolastico', $annoScolastico) . "
          AND tipo_iscrizione = " . dbQ($tipoIscrizione) . "
          AND UPPER(TRIM(codice_fiscale)) = " . dbQ($cf) . "
          AND stato <> 'annullata'
        ORDER BY updated_at DESC, id DESC
        LIMIT 1
    ");
    if ($existing) {
        $fields['id_pratica_iscrizione'] = intval($existing['id']);
        $raw = json_decode((string)($existing['raw_prime_json'] ?? ''), true);
        studentiMovimentiUpdateIscrizioneContactsFromEntrata(intval($existing['id']), $fields, is_array($raw) && (($raw['FONTE'] ?? '') === 'movimenti_entrata'));
        return;
    }

    $annoCorso = $tipoIscrizione === 'terze' ? 3 : 1;
    $indirizzoNome = studentiMovimentiIndirizzoNomeById(intval($fields['id_indirizzo_gestore'] ?? 0));
    $corsoStudi = $tipoIscrizione === 'terze'
        ? ($indirizzoNome !== '' ? $indirizzoNome : trim((string)($fields['indirizzo_destinazione'] ?? '')))
        : ($indirizzoNome !== '' ? $indirizzoNome : 'BIENNIO SETTORE TECNOLOGICO');
    $codiceDomanda = $movementId > 0 ? ('MOV-ENT-' . $movementId) : ('MOV-ENT-' . $cf);
    $token = iscrizioniPrimeGenerateToken();
    $raw = [
        'FONTE' => 'movimenti_entrata',
        'ID_MOVIMENTO' => $movementId,
        'CODICE DOMANDA' => $codiceDomanda,
        'ANNO SCOLASTICO' => $annoScolastico,
        'COGNOME STUDENTE' => $cognome,
        'NOME STUDENTE' => $nome,
        'CODICE FISCALE STUDENTE' => $cf,
        'ANNO DI CORSO' => (string)$annoCorso,
        'CORSO DI STUDI DI ISCRIZIONE' => $corsoStudi,
    ];

    dbExec("
        INSERT INTO iscrizioni_prime_pratiche
            (anno_scolastico, codice_domanda, codice_fiscale, tipo_iscrizione, studente_interno,
             cognome, nome, data_nascita, luogo_nascita, scuola_provenienza, corso_studi, id_indirizzo_gestore, anno_corso,
             responsabile_1_tipo, responsabile_1_cognome, responsabile_1_nome, responsabile_1_codice_fiscale,
             email_genitore_1, telefono_genitore_1,
             responsabile_2_tipo, responsabile_2_cognome, responsabile_2_nome, responsabile_2_codice_fiscale,
             email_genitore_2, telefono_genitore_2,
             token_hash, token_last4, token_created_at, token_expires_at,
             raw_prime_json, note_interne, imported_at, updated_at)
        VALUES
            (" . dbQ($annoScolastico) . ",
             " . dbQ($codiceDomanda) . ",
             " . dbQ($cf) . ",
             " . dbQ($tipoIscrizione) . ",
             0,
             " . dbQ($cognome) . ",
             " . dbQ($nome) . ",
             " . dbQ($fields['data_nascita'] ?? null) . ",
             " . dbQ($fields['luogo_nascita'] ?? null) . ",
             " . dbQ($fields['scuola_provenienza'] ?? null) . ",
             " . dbQ($corsoStudi) . ",
             " . dbI($fields['id_indirizzo_gestore'] ?? null) . ",
             " . dbI($annoCorso) . ",
             " . dbQ($fields['responsabile_1_tipo'] ?? null) . ",
             " . dbQ($fields['responsabile_1_cognome'] ?? null) . ",
             " . dbQ($fields['responsabile_1_nome'] ?? null) . ",
             " . dbQ($fields['responsabile_1_codice_fiscale'] ?? null) . ",
             " . dbQ($fields['email_genitore_1'] ?? null) . ",
             " . dbQ($fields['telefono_genitore_1'] ?? null) . ",
             " . dbQ($fields['responsabile_2_tipo'] ?? null) . ",
             " . dbQ($fields['responsabile_2_cognome'] ?? null) . ",
             " . dbQ($fields['responsabile_2_nome'] ?? null) . ",
             " . dbQ($fields['responsabile_2_codice_fiscale'] ?? null) . ",
             " . dbQ($fields['email_genitore_2'] ?? null) . ",
             " . dbQ($fields['telefono_genitore_2'] ?? null) . ",
             " . dbQ($token['hash']) . ",
             " . dbQ($token['last4']) . ",
             NOW(),
             DATE_ADD(NOW(), INTERVAL 90 DAY),
             " . dbQ(iscrizioniPrimeJson($raw)) . ",
             " . dbQ('Pratica creata automaticamente da movimenti studenti in entrata.') . ",
             NOW(),
             NOW())
    ");

    $praticaId = intval(dblastId());
    iscrizioniPrimeEnsureDocumentRows($praticaId);
    $fields['id_pratica_iscrizione'] = $praticaId;
}

function studentiMovimentiEnsureIscrizioniForEntrate(): array
{
    studentiMovimentiEnsureTables();
    $rows = dbGetAll("
        SELECT *
        FROM studenti_movimenti_pratiche
        WHERE tipo_pratica = 'entrata'
          AND stato_pratica <> 'annullata'
          AND COALESCE(id_pratica_iscrizione, 0) = 0
          AND COALESCE(anno_corso, 0) IN (1, 3)
        ORDER BY id ASC
    ") ?: [];

    $createdOrLinked = 0;
    $skipped = 0;
    $errors = [];
    foreach ($rows as $row) {
        $fields = $row;
        try {
            studentiMovimentiEnsureIscrizioneForEntrata($fields, intval($row['id'] ?? 0));
            $praticaId = intval($fields['id_pratica_iscrizione'] ?? 0);
            if ($praticaId > 0) {
                dbExec("
                    UPDATE studenti_movimenti_pratiche
                    SET id_pratica_iscrizione = " . dbI($praticaId) . ",
                        updated_at = NOW()
                    WHERE id = " . dbI($row['id'] ?? 0) . "
                    LIMIT 1
                ");
                $createdOrLinked++;
            } else {
                $skipped++;
            }
        } catch (Throwable $e) {
            $skipped++;
            if (count($errors) < 20) {
                $errors[] = trim((string)($row['cognome'] ?? '') . ' ' . (string)($row['nome'] ?? '')) . ': ' . $e->getMessage();
            }
        }
    }

    return ['read' => count($rows), 'linked' => $createdOrLinked, 'skipped' => $skipped, 'errors' => $errors];
}

function studentiMovimentiLinkColloquiToPractice(int $practiceId, array $fields): int
{
    if ($practiceId <= 0 || !dbGetValue("SHOW TABLES LIKE 'genitori_colloqui'")) {
        return 0;
    }
    if ((string)($fields['fonte'] ?? '') === 'colloquio_genitori') {
        return 0;
    }
    $tipoPratica = (string)($fields['tipo_pratica'] ?? '');
    $ambito = $tipoPratica === 'entrata' ? 'entrata' : 'uscita';
    if (!in_array($ambito, ['entrata', 'uscita'], true)) {
        return 0;
    }

    $cf = strtoupper(trim((string)($fields['codice_fiscale'] ?? '')));
    $cognome = trim((string)($fields['cognome'] ?? ''));
    $nome = trim((string)($fields['nome'] ?? ''));
    $matchParts = [];
    if ($cf !== '') {
        $matchParts[] = "UPPER(TRIM(COALESCE(codice_fiscale, ''))) = " . dbQ($cf);
    }
    if ($cognome !== '' && $nome !== '') {
        $matchParts[] = "(
            LOWER(TRIM(COALESCE(cognome, ''))) = LOWER(" . dbQ($cognome) . ")
            AND LOWER(TRIM(COALESCE(nome, ''))) = LOWER(" . dbQ($nome) . ")
            AND (TRIM(COALESCE(codice_fiscale, '')) = '' OR " . dbQ($cf) . " = '')
        )";
    }
    if (!$matchParts) {
        return 0;
    }
    $matchWhere = '(' . implode(' OR ', $matchParts) . ')';

    dbExec("
        UPDATE genitori_colloqui
        SET id_movimento = " . dbI($practiceId) . ",
            updated_at = NOW()
        WHERE ambito = " . dbQ($ambito) . "
          AND (id_movimento IS NULL OR id_movimento = 0)
          AND ($matchWhere)
    ");

    $linked = intval(dbGetValue("
        SELECT COUNT(*)
        FROM genitori_colloqui
        WHERE id_movimento = " . dbI($practiceId) . "
          AND ambito = " . dbQ($ambito) . "
    ") ?? 0);
    return $linked;
}

function studentiMovimentiUploadDir(int $practiceId): string
{
    return dirname(__DIR__) . '/data/movimenti_studenti/' . $practiceId;
}

function studentiMovimentiPublicPath(string $absolutePath): string
{
    $base = str_replace('\\', '/', dirname(__DIR__));
    $path = str_replace('\\', '/', $absolutePath);
    if (strpos($path, $base . '/') === 0) {
        return '../' . substr($path, strlen($base) + 1);
    }
    return $absolutePath;
}

function studentiMovimentiSavePractice(array $data): int
{
    studentiMovimentiEnsureTables();

    $id = intval($data['id'] ?? 0);
    $createdBy = studentiMovimentiCurrentActor();
    $fields = [
        'tipo_pratica' => trim((string)($data['tipo_pratica'] ?? 'entrata')),
        'stato_pratica' => trim((string)($data['stato_pratica'] ?? 'da_verificare')),
        'id_studente' => intval($data['id_studente'] ?? 0) ?: null,
        'cognome' => studentiMovimentiUpperName($data['cognome'] ?? ''),
        'nome' => studentiMovimentiUpperName($data['nome'] ?? ''),
        'codice_fiscale' => strtoupper(trim((string)($data['codice_fiscale'] ?? ''))),
        'data_nascita' => studentiMovimentiNormalizeDate($data['data_nascita'] ?? null),
        'luogo_nascita' => trim((string)($data['luogo_nascita'] ?? '')),
        'responsabile_1_tipo' => trim((string)($data['responsabile_1_tipo'] ?? '')),
        'responsabile_1_cognome' => trim((string)($data['responsabile_1_cognome'] ?? '')),
        'responsabile_1_nome' => trim((string)($data['responsabile_1_nome'] ?? '')),
        'responsabile_1_codice_fiscale' => strtoupper(trim((string)($data['responsabile_1_codice_fiscale'] ?? ''))),
        'email_genitore_1' => trim((string)($data['email_genitore_1'] ?? '')),
        'telefono_genitore_1' => trim((string)($data['telefono_genitore_1'] ?? '')),
        'responsabile_2_tipo' => trim((string)($data['responsabile_2_tipo'] ?? '')),
        'responsabile_2_cognome' => trim((string)($data['responsabile_2_cognome'] ?? '')),
        'responsabile_2_nome' => trim((string)($data['responsabile_2_nome'] ?? '')),
        'responsabile_2_codice_fiscale' => strtoupper(trim((string)($data['responsabile_2_codice_fiscale'] ?? ''))),
        'email_genitore_2' => trim((string)($data['email_genitore_2'] ?? '')),
        'telefono_genitore_2' => trim((string)($data['telefono_genitore_2'] ?? '')),
        'classe_origine' => trim((string)($data['classe_origine'] ?? '')),
        'classe_richiesta' => trim((string)($data['classe_richiesta'] ?? '')),
        'anno_corso' => intval($data['anno_corso'] ?? 0) ?: null,
        'id_istituto_destinazione' => intval($data['id_istituto_destinazione'] ?? 0) ?: null,
        'scuola_destinazione' => trim((string)($data['scuola_destinazione'] ?? '')),
        'indirizzo_destinazione' => trim((string)($data['indirizzo_destinazione'] ?? '')),
        'id_indirizzo_gestore' => intval($data['id_indirizzo_gestore'] ?? 0) ?: null,
        'id_istituto_provenienza' => intval($data['id_istituto_provenienza'] ?? 0) ?: null,
        'scuola_provenienza' => trim((string)($data['scuola_provenienza'] ?? '')),
        'indirizzo_provenienza' => trim((string)($data['indirizzo_provenienza'] ?? '')),
        'doppio_bocciato' => !empty($data['doppio_bocciato']) ? 1 : 0,
        'doppio_bocciato_non_consecutivo' => !empty($data['doppio_bocciato_non_consecutivo']) ? 1 : 0,
        'esami_integrativi' => !empty($data['esami_integrativi']) ? 1 : 0,
        'esami_integrativi_note' => trim((string)($data['esami_integrativi_note'] ?? '')),
        'carenze_presenti' => !empty($data['carenze_presenti']) ? 1 : 0,
        'carenze_note' => trim((string)($data['carenze_note'] ?? '')),
        'doc_modulo_iscrizione' => studentiMovimentiDocumentStatus((string)($data['doc_modulo_iscrizione'] ?? ''), 'mancante'),
        'doc_nulla_osta_entrata' => studentiMovimentiDocumentStatus((string)($data['doc_nulla_osta_entrata'] ?? ''), 'mancante'),
        'doc_pagella_precedente' => studentiMovimentiDocumentStatus((string)($data['doc_pagella_precedente'] ?? ''), 'mancante'),
        'doc_carenze' => studentiMovimentiDocumentStatus((string)($data['doc_carenze'] ?? ''), !empty($data['carenze_presenti']) ? 'mancante' : 'non_necessario'),
        'doc_esami_integrativi' => studentiMovimentiDocumentStatus((string)($data['doc_esami_integrativi'] ?? ''), !empty($data['esami_integrativi']) ? 'mancante' : 'non_necessario'),
        'fonte' => trim((string)($data['fonte'] ?? 'manuale')),
        'id_pratica_iscrizione' => intval($data['id_pratica_iscrizione'] ?? 0) ?: null,
        'id_cambio_scuola_iscrizione' => intval($data['id_cambio_scuola_iscrizione'] ?? 0) ?: null,
        'note' => trim((string)($data['note'] ?? '')),
    ];
    $allowedStates = studentiMovimentiStatiPerTipo();
    if (!isset($allowedStates[$fields['tipo_pratica']])) {
        $fields['tipo_pratica'] = 'uscita';
    }
    if ($fields['doppio_bocciato'] && $fields['tipo_pratica'] === 'bocciato_reiscrizione') {
        $fields['tipo_pratica'] = 'uscita';
        if (in_array($fields['stato_pratica'], ['reiscrizione_confermata', 'chiusa'], true)) {
            $fields['stato_pratica'] = 'cambia_scuola';
        }
    }
    if (!in_array($fields['stato_pratica'], $allowedStates[$fields['tipo_pratica']], true)) {
        $fields['stato_pratica'] = studentiMovimentiDefaultStato($fields['tipo_pratica']);
    }
    $nomeDestinazione = scuoleIstitutiNameById($fields['id_istituto_destinazione']);
    if ($nomeDestinazione !== '') {
        $fields['scuola_destinazione'] = $nomeDestinazione;
    }
    $nomeProvenienza = scuoleIstitutiNameById($fields['id_istituto_provenienza']);
    if ($nomeProvenienza !== '') {
        $fields['scuola_provenienza'] = $nomeProvenienza;
    }
    if ($fields['id_studente']) {
        $student = dbGetFirst("SELECT cognome, nome, codice_fiscale FROM studente WHERE id = " . dbI($fields['id_studente']) . " LIMIT 1");
        if ($student) {
            $fields['cognome'] = $fields['cognome'] !== '' ? $fields['cognome'] : (string)($student['cognome'] ?? '');
            $fields['nome'] = $fields['nome'] !== '' ? $fields['nome'] : (string)($student['nome'] ?? '');
            $fields['codice_fiscale'] = $fields['codice_fiscale'] !== '' ? $fields['codice_fiscale'] : (string)($student['codice_fiscale'] ?? '');
        }
    } elseif ($fields['tipo_pratica'] === 'entrata') {
        $studentId = studentiMovimentiFindOrCreateStudentForEntrata($fields);
        if ($studentId > 0) {
            $fields['id_studente'] = $studentId;
        }
    }
    if ($id > 0) {
        $existingContacts = dbGetFirst("
            SELECT esami_integrativi, esami_integrativi_note,
                   data_nascita, luogo_nascita,
                   responsabile_1_tipo, responsabile_1_cognome, responsabile_1_nome, responsabile_1_codice_fiscale,
                   email_genitore_1, telefono_genitore_1,
                   responsabile_2_tipo, responsabile_2_cognome, responsabile_2_nome, responsabile_2_codice_fiscale,
                   email_genitore_2, telefono_genitore_2
            FROM studenti_movimenti_pratiche
            WHERE id = " . dbI($id) . "
            LIMIT 1
        ") ?: [];
        foreach (array_keys($existingContacts) as $contactField) {
            if (($fields[$contactField] ?? null) === null || trim((string)($fields[$contactField] ?? '')) === '') {
                $fields[$contactField] = $existingContacts[$contactField] ?? $fields[$contactField] ?? null;
            }
        }
    }
    studentiMovimentiEnsureIscrizioneForEntrata($fields, $id);
    $shouldClearLinkedColloquiEsami = $id > 0
        && $fields['tipo_pratica'] === 'entrata'
        && intval($fields['esami_integrativi']) === 0
        && (
            intval($existingContacts['esami_integrativi'] ?? 0) === 1
            || trim((string)($existingContacts['esami_integrativi_note'] ?? '')) !== ''
        );

    if ($id > 0) {
        dbExec("
            UPDATE studenti_movimenti_pratiche
            SET tipo_pratica = " . dbQ($fields['tipo_pratica']) . ",
                stato_pratica = " . dbQ($fields['stato_pratica']) . ",
                id_studente = " . dbI($fields['id_studente']) . ",
                cognome = " . dbQ($fields['cognome']) . ",
                nome = " . dbQ($fields['nome']) . ",
                codice_fiscale = " . dbQ($fields['codice_fiscale']) . ",
                data_nascita = " . dbQ($fields['data_nascita']) . ",
                luogo_nascita = " . dbQ($fields['luogo_nascita']) . ",
                responsabile_1_tipo = " . dbQ($fields['responsabile_1_tipo']) . ",
                responsabile_1_cognome = " . dbQ($fields['responsabile_1_cognome']) . ",
                responsabile_1_nome = " . dbQ($fields['responsabile_1_nome']) . ",
                responsabile_1_codice_fiscale = " . dbQ($fields['responsabile_1_codice_fiscale']) . ",
                email_genitore_1 = " . dbQ($fields['email_genitore_1']) . ",
                telefono_genitore_1 = " . dbQ($fields['telefono_genitore_1']) . ",
                responsabile_2_tipo = " . dbQ($fields['responsabile_2_tipo']) . ",
                responsabile_2_cognome = " . dbQ($fields['responsabile_2_cognome']) . ",
                responsabile_2_nome = " . dbQ($fields['responsabile_2_nome']) . ",
                responsabile_2_codice_fiscale = " . dbQ($fields['responsabile_2_codice_fiscale']) . ",
                email_genitore_2 = " . dbQ($fields['email_genitore_2']) . ",
                telefono_genitore_2 = " . dbQ($fields['telefono_genitore_2']) . ",
                classe_origine = " . dbQ($fields['classe_origine']) . ",
                classe_richiesta = " . dbQ($fields['classe_richiesta']) . ",
                anno_corso = " . dbI($fields['anno_corso']) . ",
                id_istituto_destinazione = " . dbI($fields['id_istituto_destinazione']) . ",
                scuola_destinazione = " . dbQ($fields['scuola_destinazione']) . ",
                indirizzo_destinazione = " . dbQ($fields['indirizzo_destinazione']) . ",
                id_indirizzo_gestore = " . dbI($fields['id_indirizzo_gestore']) . ",
                id_istituto_provenienza = " . dbI($fields['id_istituto_provenienza']) . ",
                scuola_provenienza = " . dbQ($fields['scuola_provenienza']) . ",
                indirizzo_provenienza = " . dbQ($fields['indirizzo_provenienza']) . ",
                doppio_bocciato = " . dbI($fields['doppio_bocciato']) . ",
                doppio_bocciato_non_consecutivo = " . dbI($fields['doppio_bocciato_non_consecutivo']) . ",
                esami_integrativi = " . dbI($fields['esami_integrativi']) . ",
                esami_integrativi_note = " . dbQ($fields['esami_integrativi_note']) . ",
                carenze_presenti = " . dbI($fields['carenze_presenti']) . ",
                carenze_note = " . dbQ($fields['carenze_note']) . ",
                doc_modulo_iscrizione = " . dbQ($fields['doc_modulo_iscrizione']) . ",
                doc_nulla_osta_entrata = " . dbQ($fields['doc_nulla_osta_entrata']) . ",
                doc_pagella_precedente = " . dbQ($fields['doc_pagella_precedente']) . ",
                doc_carenze = " . dbQ($fields['doc_carenze']) . ",
                doc_esami_integrativi = " . dbQ($fields['doc_esami_integrativi']) . ",
                fonte = " . dbQ($fields['fonte']) . ",
                id_pratica_iscrizione = " . dbI($fields['id_pratica_iscrizione']) . ",
                id_cambio_scuola_iscrizione = " . dbI($fields['id_cambio_scuola_iscrizione']) . ",
                note = " . dbQ($fields['note']) . ",
                updated_at = NOW()
            WHERE id = " . dbI($id) . "
            LIMIT 1
        ");
        studentiMovimentiAddEvent($id, 'salvataggio', 'Pratica aggiornata', $fields, $createdBy);
        studentiMovimentiLinkColloquiToPractice($id, $fields);
        if ($shouldClearLinkedColloquiEsami && dbGetValue("SHOW TABLES LIKE 'genitori_colloqui'")) {
            require_once __DIR__ . '/genitoriColloquiLib.php';
            $clearedColloqui = genitoriColloquiClearEsamiIntegrativiForMovement($id);
            if ($clearedColloqui > 0) {
                studentiMovimentiAddEvent($id, 'sync_colloqui', 'Esami integrativi rimossi dai colloqui collegati', [
                    'tipo_pratica' => $fields['tipo_pratica'],
                    'stato_pratica' => $fields['stato_pratica'],
                    'note' => 'Colloqui aggiornati: ' . $clearedColloqui,
                ], $createdBy);
            }
        }
        return $id;
    }

    dbExec("
        INSERT INTO studenti_movimenti_pratiche (
            tipo_pratica, stato_pratica, id_studente, cognome, nome, codice_fiscale,
            data_nascita, luogo_nascita,
            responsabile_1_tipo, responsabile_1_cognome, responsabile_1_nome, responsabile_1_codice_fiscale,
            email_genitore_1, telefono_genitore_1,
            responsabile_2_tipo, responsabile_2_cognome, responsabile_2_nome, responsabile_2_codice_fiscale,
            email_genitore_2, telefono_genitore_2,
            classe_origine, classe_richiesta, anno_corso,
            id_istituto_destinazione, scuola_destinazione, indirizzo_destinazione, id_indirizzo_gestore,
            id_istituto_provenienza, scuola_provenienza, indirizzo_provenienza,
            doppio_bocciato, doppio_bocciato_non_consecutivo,
            esami_integrativi, esami_integrativi_note, carenze_presenti, carenze_note,
            doc_modulo_iscrizione, doc_nulla_osta_entrata, doc_pagella_precedente, doc_carenze, doc_esami_integrativi,
            fonte, id_pratica_iscrizione, id_cambio_scuola_iscrizione, note, created_at, updated_at
        ) VALUES (
            " . dbQ($fields['tipo_pratica']) . ",
            " . dbQ($fields['stato_pratica']) . ",
            " . dbI($fields['id_studente']) . ",
            " . dbQ($fields['cognome']) . ",
            " . dbQ($fields['nome']) . ",
            " . dbQ($fields['codice_fiscale']) . ",
            " . dbQ($fields['data_nascita']) . ",
            " . dbQ($fields['luogo_nascita']) . ",
            " . dbQ($fields['responsabile_1_tipo']) . ",
            " . dbQ($fields['responsabile_1_cognome']) . ",
            " . dbQ($fields['responsabile_1_nome']) . ",
            " . dbQ($fields['responsabile_1_codice_fiscale']) . ",
            " . dbQ($fields['email_genitore_1']) . ",
            " . dbQ($fields['telefono_genitore_1']) . ",
            " . dbQ($fields['responsabile_2_tipo']) . ",
            " . dbQ($fields['responsabile_2_cognome']) . ",
            " . dbQ($fields['responsabile_2_nome']) . ",
            " . dbQ($fields['responsabile_2_codice_fiscale']) . ",
            " . dbQ($fields['email_genitore_2']) . ",
            " . dbQ($fields['telefono_genitore_2']) . ",
            " . dbQ($fields['classe_origine']) . ",
            " . dbQ($fields['classe_richiesta']) . ",
            " . dbI($fields['anno_corso']) . ",
            " . dbI($fields['id_istituto_destinazione']) . ",
            " . dbQ($fields['scuola_destinazione']) . ",
            " . dbQ($fields['indirizzo_destinazione']) . ",
            " . dbI($fields['id_indirizzo_gestore']) . ",
            " . dbI($fields['id_istituto_provenienza']) . ",
            " . dbQ($fields['scuola_provenienza']) . ",
            " . dbQ($fields['indirizzo_provenienza']) . ",
            " . dbI($fields['doppio_bocciato']) . ",
            " . dbI($fields['doppio_bocciato_non_consecutivo']) . ",
            " . dbI($fields['esami_integrativi']) . ",
            " . dbQ($fields['esami_integrativi_note']) . ",
            " . dbI($fields['carenze_presenti']) . ",
            " . dbQ($fields['carenze_note']) . ",
            " . dbQ($fields['doc_modulo_iscrizione']) . ",
            " . dbQ($fields['doc_nulla_osta_entrata']) . ",
            " . dbQ($fields['doc_pagella_precedente']) . ",
            " . dbQ($fields['doc_carenze']) . ",
            " . dbQ($fields['doc_esami_integrativi']) . ",
            " . dbQ($fields['fonte']) . ",
            " . dbI($fields['id_pratica_iscrizione']) . ",
            " . dbI($fields['id_cambio_scuola_iscrizione']) . ",
            " . dbQ($fields['note']) . ",
            NOW(),
            NOW()
        )
    ");
    $newId = intval(dblastId());
    studentiMovimentiAddEvent($newId, 'creazione', 'Pratica creata', $fields, $createdBy);
    studentiMovimentiLinkColloquiToPractice($newId, $fields);
    return $newId;
}

function studentiMovimentiDeletePractice(int $practiceId): bool
{
    if ($practiceId <= 0) {
        return false;
    }
    $exists = intval(dbGetValue("
        SELECT id
        FROM studenti_movimenti_pratiche
        WHERE id = " . dbI($practiceId) . "
        LIMIT 1
    ") ?? 0);
    if ($exists <= 0) {
        return false;
    }
    dbExec("DELETE FROM studenti_movimenti_eventi WHERE id_pratica = " . dbI($practiceId));
    dbExec("DELETE FROM studenti_movimenti_allegati WHERE id_pratica = " . dbI($practiceId));
    dbExec("DELETE FROM studenti_movimenti_pratiche WHERE id = " . dbI($practiceId) . " LIMIT 1");
    $hasColloquiTable = dbGetValue("SHOW TABLES LIKE 'genitori_colloqui'");
    if ($hasColloquiTable) {
        dbExec("UPDATE genitori_colloqui SET id_movimento = NULL WHERE id_movimento = " . dbI($practiceId));
    }
    return true;
}

function studentiMovimentiAddEvent(int $practiceId, string $type, string $description, array $fields = [], string $createdBy = ''): void
{
    if ($practiceId <= 0) {
        return;
    }
    $linkedColloquioId = intval($fields['id_colloquio_genitori'] ?? 0);
    if ($type === 'colloquio_genitori' && $linkedColloquioId > 0) {
        $existingEvent = dbGetFirst("
            SELECT *
            FROM studenti_movimenti_eventi
            WHERE id_pratica = " . dbI($practiceId) . "
              AND tipo_evento = 'colloquio_genitori'
              AND id_colloquio_genitori = " . dbI($linkedColloquioId) . "
            ORDER BY id DESC
            LIMIT 1
        ") ?: [];
        $existingEventId = intval($existingEvent['id'] ?? 0);
        if ($existingEventId > 0) {
            $nextValues = [
                'descrizione' => $description,
                'stato_pratica' => $fields['stato_pratica'] ?? null,
                'tipo_pratica' => $fields['tipo_pratica'] ?? null,
                'id_istituto_destinazione' => $fields['id_istituto_destinazione'] ?? null,
                'scuola_destinazione' => $fields['scuola_destinazione'] ?? null,
                'indirizzo_destinazione' => $fields['indirizzo_destinazione'] ?? null,
                'id_istituto_provenienza' => $fields['id_istituto_provenienza'] ?? null,
                'scuola_provenienza' => $fields['scuola_provenienza'] ?? null,
                'indirizzo_provenienza' => $fields['indirizzo_provenienza'] ?? null,
                'tipo_allegato' => $fields['tipo_allegato'] ?? null,
                'allegato_path' => $fields['allegato_path'] ?? null,
                'allegato_original_name' => $fields['allegato_original_name'] ?? null,
                'note' => $fields['note'] ?? null,
                'created_by' => $createdBy,
            ];
            $changed = false;
            foreach ($nextValues as $field => $value) {
                if (trim((string)($existingEvent[$field] ?? '')) !== trim((string)($value ?? ''))) {
                    $changed = true;
                    break;
                }
            }
            if (!$changed) {
                return;
            }
            dbExec("
                UPDATE studenti_movimenti_eventi
                SET descrizione = " . dbQ($description) . ",
                    stato_pratica = " . dbQ($fields['stato_pratica'] ?? null) . ",
                    tipo_pratica = " . dbQ($fields['tipo_pratica'] ?? null) . ",
                    id_istituto_destinazione = " . dbI($fields['id_istituto_destinazione'] ?? null) . ",
                    scuola_destinazione = " . dbQ($fields['scuola_destinazione'] ?? null) . ",
                    indirizzo_destinazione = " . dbQ($fields['indirizzo_destinazione'] ?? null) . ",
                    id_istituto_provenienza = " . dbI($fields['id_istituto_provenienza'] ?? null) . ",
                    scuola_provenienza = " . dbQ($fields['scuola_provenienza'] ?? null) . ",
                    indirizzo_provenienza = " . dbQ($fields['indirizzo_provenienza'] ?? null) . ",
                    tipo_allegato = " . dbQ($fields['tipo_allegato'] ?? null) . ",
                    allegato_path = " . dbQ($fields['allegato_path'] ?? null) . ",
                    allegato_original_name = " . dbQ($fields['allegato_original_name'] ?? null) . ",
                    note = " . dbQ($fields['note'] ?? null) . ",
                    created_by = " . dbQ($createdBy) . ",
                    created_at = NOW()
                WHERE id = " . dbI($existingEventId) . "
                LIMIT 1
            ");
            return;
        }
    }
    dbExec("
        INSERT INTO studenti_movimenti_eventi (
            id_pratica, tipo_evento, id_colloquio_genitori, descrizione, stato_pratica, tipo_pratica,
            id_istituto_destinazione, scuola_destinazione, indirizzo_destinazione,
            id_istituto_provenienza, scuola_provenienza, indirizzo_provenienza,
            tipo_allegato, allegato_path, allegato_original_name,
            note, created_by, created_at
        ) VALUES (
            " . dbI($practiceId) . ",
            " . dbQ($type) . ",
            " . dbI($linkedColloquioId > 0 ? $linkedColloquioId : null) . ",
            " . dbQ($description) . ",
            " . dbQ($fields['stato_pratica'] ?? null) . ",
            " . dbQ($fields['tipo_pratica'] ?? null) . ",
            " . dbI($fields['id_istituto_destinazione'] ?? null) . ",
            " . dbQ($fields['scuola_destinazione'] ?? null) . ",
            " . dbQ($fields['indirizzo_destinazione'] ?? null) . ",
            " . dbI($fields['id_istituto_provenienza'] ?? null) . ",
            " . dbQ($fields['scuola_provenienza'] ?? null) . ",
            " . dbQ($fields['indirizzo_provenienza'] ?? null) . ",
            " . dbQ($fields['tipo_allegato'] ?? null) . ",
            " . dbQ($fields['allegato_path'] ?? null) . ",
            " . dbQ($fields['allegato_original_name'] ?? null) . ",
            " . dbQ($fields['note'] ?? null) . ",
            " . dbQ($createdBy) . ",
            NOW()
        )
    ");
}

function studentiMovimentiHistoryForPractices(array $practiceIds): array
{
    $ids = array_values(array_filter(array_map('intval', $practiceIds), static fn($id) => $id > 0));
    if (!$ids) {
        return [];
    }
    $rows = dbGetAll("
        SELECT *
        FROM studenti_movimenti_eventi
        WHERE id_pratica IN (" . implode(',', $ids) . ")
        ORDER BY created_at DESC, id DESC
    ") ?: [];
    $history = [];
    $seen = [];
    foreach ($rows as $row) {
        $practiceId = intval($row['id_pratica'] ?? 0);
        $dedupeKey = studentiMovimentiHistoryDedupeKey($practiceId, $row);
        if (isset($seen[$dedupeKey])) {
            continue;
        }
        $seen[$dedupeKey] = true;
        $history[$practiceId][] = $row;
    }
    return $history;
}

function studentiMovimentiDeleteEvent(int $eventId): bool
{
    if ($eventId <= 0) {
        return false;
    }
    studentiMovimentiEnsureTables();
    $exists = intval(dbGetValue("
        SELECT id
        FROM studenti_movimenti_eventi
        WHERE id = " . dbI($eventId) . "
        LIMIT 1
    ") ?? 0);
    if ($exists <= 0) {
        return false;
    }
    dbExec("DELETE FROM studenti_movimenti_eventi WHERE id = " . dbI($eventId) . " LIMIT 1");
    return true;
}

function studentiMovimentiUpdateEvent(int $eventId, string $description, string $note): bool
{
    if ($eventId <= 0) {
        return false;
    }
    studentiMovimentiEnsureTables();
    $exists = intval(dbGetValue("
        SELECT id
        FROM studenti_movimenti_eventi
        WHERE id = " . dbI($eventId) . "
        LIMIT 1
    ") ?? 0);
    if ($exists <= 0) {
        return false;
    }
    dbExec("
        UPDATE studenti_movimenti_eventi
        SET descrizione = " . dbQ(trim($description) !== '' ? trim($description) : 'Aggiornamento pratica') . ",
            note = " . dbQ(trim($note)) . "
        WHERE id = " . dbI($eventId) . "
        LIMIT 1
    ");
    return true;
}

function studentiMovimentiAttachFileToEvent(int $eventId, array $file, string $type): bool
{
    if ($eventId <= 0 || empty($file['tmp_name']) || intval($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return false;
    }
    studentiMovimentiEnsureTables();
    $event = dbGetFirst("
        SELECT *
        FROM studenti_movimenti_eventi
        WHERE id = " . dbI($eventId) . "
        LIMIT 1
    ") ?: [];
    $practiceId = intval($event['id_pratica'] ?? 0);
    if ($practiceId <= 0) {
        return false;
    }

    $type = trim($type) !== '' ? trim($type) : 'documento';
    $original = basename((string)($file['name'] ?? 'documento.pdf'));
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
        throw new RuntimeException('Allegato non valido: carica PDF o immagini JPG/PNG.');
    }

    $dir = studentiMovimentiUploadDir($practiceId);
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException('Impossibile creare la cartella allegati.');
    }
    $safeName = preg_replace('/[^A-Za-z0-9_.-]+/u', '_', $original);
    $target = $dir . '/' . date('Ymd_His') . '_' . $safeName;
    if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
        throw new RuntimeException('Impossibile salvare allegato.');
    }

    dbExec("
        INSERT INTO studenti_movimenti_allegati (
            id_pratica, id_evento, tipo_allegato, nome_file, path_file, mime_type, dimensione, created_at
        ) VALUES (
            " . dbI($practiceId) . ",
            " . dbI($eventId) . ",
            " . dbQ($type) . ",
            " . dbQ($original) . ",
            " . dbQ($target) . ",
            " . dbQ($file['type'] ?? '') . ",
            " . dbI($file['size'] ?? null) . ",
            NOW()
        )
    ");

    if (trim((string)($event['allegato_path'] ?? '')) === '') {
        dbExec("
            UPDATE studenti_movimenti_eventi
            SET tipo_allegato = " . dbQ($type) . ",
                allegato_path = " . dbQ($target) . ",
                allegato_original_name = " . dbQ($original) . "
            WHERE id = " . dbI($eventId) . "
            LIMIT 1
        ");
    }
    return true;
}

function studentiMovimentiDeleteAttachment(int $attachmentId): bool
{
    if ($attachmentId <= 0) {
        return false;
    }
    studentiMovimentiEnsureTables();
    $attachment = dbGetFirst("
        SELECT *
        FROM studenti_movimenti_allegati
        WHERE id = " . dbI($attachmentId) . "
        LIMIT 1
    ") ?: [];
    if (!$attachment) {
        return false;
    }

    $path = (string)($attachment['path_file'] ?? '');
    dbExec("DELETE FROM studenti_movimenti_allegati WHERE id = " . dbI($attachmentId) . " LIMIT 1");

    $eventId = intval($attachment['id_evento'] ?? 0);
    dbExec("
        UPDATE studenti_movimenti_eventi
        SET allegato_path = NULL,
            allegato_original_name = NULL,
            tipo_allegato = NULL
        WHERE id_pratica = " . dbI($attachment['id_pratica'] ?? 0) . "
          AND allegato_path = " . dbQ($path) . "
    ");

    $baseDir = str_replace('\\', '/', dirname(__DIR__) . '/data/movimenti_studenti/');
    $normalizedPath = str_replace('\\', '/', $path);
    if ($normalizedPath !== '' && strpos($normalizedPath, $baseDir) === 0 && is_file($path)) {
        @unlink($path);
    }
    return true;
}

function studentiMovimentiHistoryDedupeKey(int $practiceId, array $row): string
{
    if (($row['tipo_evento'] ?? '') === 'colloquio_genitori') {
        $linkedColloquioId = intval($row['id_colloquio_genitori'] ?? 0);
        if ($linkedColloquioId > 0) {
            return $practiceId . '|colloquio_genitori|' . $linkedColloquioId;
        }
        $noteFields = studentiMovimentiParseHistoryNote((string)($row['note'] ?? ''));
        $note = studentiMovimentiNormalizeHistoryText((string)($row['note'] ?? ''));
        $student = studentiMovimentiNormalizeHistoryText($noteFields['studente'] ?? '');
        $class = studentiMovimentiNormalizeHistoryText($noteFields['classe iscrizione'] ?? '');
        $practiceNote = studentiMovimentiNormalizeHistoryText($noteFields['note'] ?? '');
        return implode('|', [
            $practiceId,
            'colloquio_genitori',
            studentiMovimentiNormalizeHistoryText((string)($row['created_by'] ?? '')),
            $student !== '' ? $student : substr($note, 0, 120),
            $class,
            $practiceNote !== '' ? $practiceNote : substr($note, 0, 180),
        ]);
    }

    return implode('|', [
        $practiceId,
        (string)($row['tipo_evento'] ?? ''),
        (string)($row['descrizione'] ?? ''),
        (string)($row['stato_pratica'] ?? ''),
        (string)($row['tipo_pratica'] ?? ''),
        (string)($row['note'] ?? ''),
    ]);
}

function studentiMovimentiParseHistoryNote(string $note): array
{
    $fields = [];
    foreach (preg_split('/\R/', str_replace(["\r\n", "\r"], "\n", $note)) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, ':') === false) {
            continue;
        }
        [$key, $value] = explode(':', $line, 2);
        $fields[strtolower(trim($key))] = trim($value);
    }
    return $fields;
}

function studentiMovimentiNormalizeHistoryText(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/\s+/', ' ', $text);
    return $text ?? '';
}

function studentiMovimentiCurrentYearId(): int
{
    global $__anno_scolastico_corrente_id;
    return intval($__anno_scolastico_corrente_id ?? 0);
}

function studentiMovimentiClassYear(string $classLabel): ?int
{
    $year = mastercomTabelloniClassYearFromName($classLabel);
    return $year > 0 ? $year : null;
}

function studentiMovimentiSyncBocciatiFromTabelloni(int $schoolYearId): array
{
    studentiMovimentiEnsureTables();
    mastercomTabelloniEnsureTables();
    mastercomTabelloniRefreshDerivedFields();

    $rows = dbGetAll("
        SELECT
            t.classe,
            t.classe_tabellone,
            s.id_studente_gestore,
            s.studente_nome,
            s.esito_key,
            st.cognome,
            st.nome,
            st.codice_fiscale,
            cls_summary.classe AS classe_gestore_studente,
            mcls_summary.nome AS classe_mastercom_studente
        FROM mastercom_tabelloni_scrutini t
        INNER JOIN mastercom_tabelloni_scrutini_studenti s ON s.tabellone_id = t.id
        LEFT JOIN studente st ON st.id = s.id_studente_gestore
        LEFT JOIN studente_frequenta sf_summary ON sf_summary.id = (
            SELECT sf2.id
            FROM studente_frequenta sf2
            WHERE sf2.id_studente = s.id_studente_gestore
              AND sf2.id_anno_scolastico = t.id_anno_scolastico
            ORDER BY sf2.id DESC
            LIMIT 1
        )
        LEFT JOIN classi cls_summary ON cls_summary.id = sf_summary.id_classe
        LEFT JOIN mastercom_studenti mstu_summary ON mstu_summary.id = (
            SELECT ms2.id
            FROM mastercom_studenti ms2
            WHERE ms2.mastercom_id_studente = s.mastercom_id_studente
            ORDER BY ms2.id DESC
            LIMIT 1
        )
        LEFT JOIN mastercom_classi mcls_summary ON mcls_summary.mastercom_id_classe = mstu_summary.mastercom_id_classe_corrente
        WHERE t.id_anno_scolastico = " . dbI($schoolYearId) . "
          AND t.periodo = '9'
          AND s.esito_key IN ('non_ammesso', 'in_corso')
        GROUP BY t.id, t.classe, t.classe_tabellone, s.id_studente_gestore, s.studente_nome, s.esito_key,
                 st.cognome, st.nome, st.codice_fiscale, cls_summary.classe, mcls_summary.nome
        ORDER BY t.classe ASC, st.cognome ASC, st.nome ASC, s.studente_nome ASC
    ") ?: [];

    $created = 0;
    $existing = 0;
    $updatedExisting = 0;
    $withoutGestoreId = 0;
    $withoutGestoreExamples = [];
    $byYear = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 0 => 0];
    foreach ($rows as $row) {
        $classLabel = mastercomTabelloniSummaryEffectiveClassLabel($row);
        $annoCorso = studentiMovimentiClassYear($classLabel);
        $yearKey = ($annoCorso !== null && $annoCorso >= 1 && $annoCorso <= 5) ? $annoCorso : 0;
        $byYear[$yearKey]++;

        $studentId = intval($row['id_studente_gestore'] ?? 0);
        if ($studentId <= 0) {
            $withoutGestoreId++;
            if (count($withoutGestoreExamples) < 20) {
                $withoutGestoreExamples[] = trim((string)($row['studente_nome'] ?? '') . ' - ' . $classLabel);
            }
            continue;
        }
        $already = dbGetFirst("
            SELECT id, anno_corso, classe_origine, cognome, nome, codice_fiscale
            FROM studenti_movimenti_pratiche
            WHERE id_studente = " . dbI($studentId) . "
              AND tipo_pratica IN ('bocciato_reiscrizione','uscita','ritiro')
              AND stato_pratica <> 'annullata'
            LIMIT 1
        ");
        if ($already !== null) {
            $updates = [];
            if (intval($already['anno_corso'] ?? 0) <= 0 && $annoCorso !== null) {
                $updates[] = "anno_corso = " . dbI($annoCorso);
            }
            if (trim((string)($already['classe_origine'] ?? '')) === '' && $classLabel !== '') {
                $updates[] = "classe_origine = " . dbQ($classLabel);
            }
            if (trim((string)($already['cognome'] ?? '')) === '' && trim((string)($row['cognome'] ?? '')) !== '') {
                $updates[] = "cognome = " . dbQ($row['cognome']);
            }
            if (trim((string)($already['nome'] ?? '')) === '' && trim((string)($row['nome'] ?? '')) !== '') {
                $updates[] = "nome = " . dbQ($row['nome']);
            }
            if (trim((string)($already['codice_fiscale'] ?? '')) === '' && trim((string)($row['codice_fiscale'] ?? '')) !== '') {
                $updates[] = "codice_fiscale = " . dbQ($row['codice_fiscale']);
            }
            if (!empty($updates)) {
                $updates[] = "updated_at = NOW()";
                dbExec("
                    UPDATE studenti_movimenti_pratiche
                    SET " . implode(", ", $updates) . "
                    WHERE id = " . dbI($already['id']) . "
                    LIMIT 1
                ");
                $updatedExisting++;
            }
            $existing++;
            continue;
        }

        studentiMovimentiSavePractice([
            'tipo_pratica' => 'bocciato_reiscrizione',
            'stato_pratica' => 'da_verificare',
            'id_studente' => $studentId,
            'cognome' => $row['cognome'] ?? '',
            'nome' => $row['nome'] ?? '',
            'codice_fiscale' => $row['codice_fiscale'] ?? '',
            'classe_origine' => $classLabel,
            'anno_corso' => $annoCorso,
            'fonte' => 'tabelloni',
            'note' => 'Pratica creata automaticamente da tabellone finale: studente non ammesso o con esito in corso.',
        ]);
        $created++;
    }

    return [
        'read' => count($rows),
        'created' => $created,
        'existing' => $existing,
        'updated_existing' => $updatedExisting,
        'without_gestore_id' => $withoutGestoreId,
        'without_gestore_examples' => $withoutGestoreExamples,
        'by_year' => $byYear,
    ];
}

function studentiMovimentiSyncCambioScuolaDaIscrizioni(): array
{
    studentiMovimentiEnsureTables();
    require_once __DIR__ . '/iscrizioniPrimeLib.php';
    iscrizioniPrimeEnsureSchema();

    $rows = dbGetAll("
        SELECT
            p.id AS pratica_iscrizione_id,
            p.tipo_iscrizione,
            p.cognome,
            p.nome,
            p.codice_fiscale,
            p.email_genitore_1,
            p.telefono_genitore_1,
            p.responsabile_1_tipo,
            p.responsabile_1_cognome,
            p.responsabile_1_nome,
            p.responsabile_1_codice_fiscale,
            p.email_genitore_2,
            p.telefono_genitore_2,
            p.responsabile_2_tipo,
            p.responsabile_2_cognome,
            p.responsabile_2_nome,
            p.responsabile_2_codice_fiscale,
            p.corso_studi,
            p.id_indirizzo_gestore,
            p.studente_interno,
            c.id AS cambio_id,
            c.richiesta_data,
            c.canale,
            c.id_istituto_destinazione,
            c.scuola_destinazione,
            c.indirizzo_destinazione,
            c.colloquio_stato,
            c.nulla_osta_stato,
            c.documenti_stato,
            c.pratica_stato,
            c.note,
            s.id AS id_studente,
            cls.classe AS classe_corrente
        FROM iscrizioni_prime_pratiche p
        INNER JOIN iscrizioni_prime_cambio_scuola c ON c.pratica_id = p.id
        LEFT JOIN studente s ON UPPER(TRIM(s.codice_fiscale)) = UPPER(TRIM(p.codice_fiscale))
          AND COALESCE(s.attivo, 1) = 1
        LEFT JOIN studente_frequenta sf ON sf.id = (
            SELECT sf2.id
            FROM studente_frequenta sf2
            WHERE sf2.id_studente = s.id
              AND sf2.id_anno_scolastico = " . dbI(studentiMovimentiCurrentYearId()) . "
            ORDER BY sf2.id DESC
            LIMIT 1
        )
        LEFT JOIN classi cls ON cls.id = sf.id_classe
        WHERE p.stato = 'annullata'
          AND p.tipo_iscrizione IN ('prime','terze')
        ORDER BY p.cognome ASC, p.nome ASC
    ") ?: [];

    $created = 0;
    $updated = 0;
    foreach ($rows as $row) {
        $tipoIscrizione = (string)($row['tipo_iscrizione'] ?? 'prime');
        $isCurrentStudent = intval($row['id_studente'] ?? 0) > 0
            && !in_array(strtoupper(trim((string)($row['classe_corrente'] ?? ''))), ['MEDIE', 'EE'], true);
        $annoCorso = studentiMovimentiClassYear((string)($row['classe_corrente'] ?? ''));
        if ($annoCorso === null) {
            $annoCorso = $tipoIscrizione === 'terze' ? 3 : 1;
        }
        $state = studentiMovimentiStateFromCambioScuola((string)($row['pratica_stato'] ?? ''), (string)($row['nulla_osta_stato'] ?? ''));
        $noteParts = [
            'Sincronizzato da cambio scuola iscrizioni ' . $tipoIscrizione . '.',
        ];
        if (!empty($row['richiesta_data'])) {
            $noteParts[] = 'Richiesta: ' . studentiMovimentiFormatDateIt((string)$row['richiesta_data']);
        }
        if (!empty($row['canale'])) {
            $noteParts[] = 'Canale: ' . $row['canale'];
        }
        if (!empty($row['note'])) {
            $noteParts[] = 'Note iscrizioni: ' . $row['note'];
        }
        $note = implode("\n", $noteParts);

        $existing = dbGetFirst("
            SELECT *
            FROM studenti_movimenti_pratiche
            WHERE (
                    id_pratica_iscrizione = " . dbI($row['pratica_iscrizione_id']) . "
                 OR id_cambio_scuola_iscrizione = " . dbI($row['cambio_id']) . "
                 OR (
                        codice_fiscale IS NOT NULL
                    AND codice_fiscale <> ''
                    AND UPPER(TRIM(codice_fiscale)) = " . dbQ(strtoupper(trim((string)$row['codice_fiscale']))) . "
                    AND tipo_pratica IN ('bocciato_reiscrizione','uscita','ritiro')
                    AND stato_pratica <> 'annullata'
                    )
            )
            LIMIT 1
        ");

        $data = [
            'id' => intval($existing['id'] ?? 0),
            'tipo_pratica' => 'uscita',
            'stato_pratica' => $state,
            'id_studente' => $isCurrentStudent ? intval($row['id_studente']) : null,
            'cognome' => $row['cognome'] ?? '',
            'nome' => $row['nome'] ?? '',
            'codice_fiscale' => $row['codice_fiscale'] ?? '',
            'responsabile_1_tipo' => $row['responsabile_1_tipo'] ?? '',
            'responsabile_1_cognome' => $row['responsabile_1_cognome'] ?? '',
            'responsabile_1_nome' => $row['responsabile_1_nome'] ?? '',
            'responsabile_1_codice_fiscale' => $row['responsabile_1_codice_fiscale'] ?? '',
            'email_genitore_1' => $row['email_genitore_1'] ?? '',
            'telefono_genitore_1' => $row['telefono_genitore_1'] ?? '',
            'responsabile_2_tipo' => $row['responsabile_2_tipo'] ?? '',
            'responsabile_2_cognome' => $row['responsabile_2_cognome'] ?? '',
            'responsabile_2_nome' => $row['responsabile_2_nome'] ?? '',
            'responsabile_2_codice_fiscale' => $row['responsabile_2_codice_fiscale'] ?? '',
            'email_genitore_2' => $row['email_genitore_2'] ?? '',
            'telefono_genitore_2' => $row['telefono_genitore_2'] ?? '',
            'classe_origine' => $row['classe_corrente'] ?: ($tipoIscrizione === 'prime' ? 'Prima iscrizione' : 'Terza iscrizione'),
            'classe_richiesta' => '',
            'anno_corso' => $annoCorso,
            'id_istituto_destinazione' => intval($row['id_istituto_destinazione'] ?? 0) ?: null,
            'scuola_destinazione' => $row['scuola_destinazione'] ?? '',
            'indirizzo_destinazione' => $row['indirizzo_destinazione'] ?? '',
            'id_indirizzo_gestore' => intval($row['id_indirizzo_gestore'] ?? 0) ?: null,
            'id_istituto_provenienza' => null,
            'scuola_provenienza' => '',
            'indirizzo_provenienza' => '',
            'esami_integrativi' => 0,
            'fonte' => 'iscrizioni',
            'id_pratica_iscrizione' => intval($row['pratica_iscrizione_id']),
            'id_cambio_scuola_iscrizione' => intval($row['cambio_id']),
            'note' => $note,
        ];
        if ($existing && !studentiMovimentiPracticeNeedsUpdate($existing, $data)) {
            continue;
        }
        studentiMovimentiSavePractice($data);
        if ($existing) {
            $updated++;
        } else {
            $created++;
        }
    }

    return ['read' => count($rows), 'created' => $created, 'updated' => $updated];
}

function studentiMovimentiPracticeNeedsUpdate(array $existing, array $data): bool
{
    foreach ([
        'tipo_pratica',
        'stato_pratica',
        'id_studente',
        'cognome',
        'nome',
        'codice_fiscale',
        'classe_origine',
        'classe_richiesta',
        'anno_corso',
        'id_istituto_destinazione',
        'scuola_destinazione',
        'indirizzo_destinazione',
        'id_indirizzo_gestore',
        'id_istituto_provenienza',
        'scuola_provenienza',
        'indirizzo_provenienza',
        'doppio_bocciato',
        'esami_integrativi',
        'fonte',
        'id_pratica_iscrizione',
        'id_cambio_scuola_iscrizione',
        'note',
    ] as $field) {
        $left = trim((string)($existing[$field] ?? ''));
        $right = trim((string)($data[$field] ?? ''));
        if ($left !== $right) {
            return true;
        }
    }
    return false;
}

function studentiMovimentiStateFromCambioScuola(string $praticaStato, string $nullaOstaStato): string
{
    if (in_array($nullaOstaStato, ['evaso_inviato'], true)) {
        return 'nulla_osta_inviato';
    }
    if (in_array($nullaOstaStato, ['richiesto', 'ricevuto'], true)) {
        return 'richiesta_nulla_osta';
    }
    if (in_array($praticaStato, ['completata'], true)) {
        return 'chiusa';
    }
    return 'cambia_scuola';
}

function studentiMovimentiFormatDateIt(?string $date): string
{
    $date = trim((string)$date);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $date, $match)) {
        return $date;
    }
    return $match[3] . '/' . $match[2] . '/' . $match[1];
}

function studentiMovimentiFormatDateTimeIt(?string $dateTime): string
{
    $dateTime = trim((string)$dateTime);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/', $dateTime, $match)) {
        return $dateTime;
    }
    return $match[3] . '/' . $match[2] . '/' . $match[1] . (isset($match[4]) && $match[4] !== '' ? ' ' . $match[4] . ':' . $match[5] : '');
}

function studentiMovimentiAttachFile(int $practiceId, array $file, string $type): void
{
    if ($practiceId <= 0 || empty($file['tmp_name']) || intval($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return;
    }
    $type = trim($type) !== '' ? trim($type) : 'documento';
    $original = basename((string)($file['name'] ?? 'documento.pdf'));
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
        throw new RuntimeException('Allegato non valido: carica PDF o immagini JPG/PNG.');
    }

    $dir = studentiMovimentiUploadDir($practiceId);
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException('Impossibile creare la cartella allegati.');
    }
    $safeName = preg_replace('/[^A-Za-z0-9_.-]+/u', '_', $original);
    $target = $dir . '/' . date('Ymd_His') . '_' . $safeName;
    if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
        throw new RuntimeException('Impossibile salvare allegato.');
    }

    dbExec("
        INSERT INTO studenti_movimenti_allegati (
            id_pratica, tipo_allegato, nome_file, path_file, mime_type, dimensione, created_at
        ) VALUES (
            " . dbI($practiceId) . ",
            " . dbQ($type) . ",
            " . dbQ($original) . ",
            " . dbQ($target) . ",
            " . dbQ($file['type'] ?? '') . ",
            " . dbI($file['size'] ?? null) . ",
            NOW()
        )
    ");
    studentiMovimentiAddEvent($practiceId, 'allegato', 'Allegato aggiunto: ' . $original, [
        'tipo_allegato' => $type,
        'allegato_path' => $target,
        'allegato_original_name' => $original,
        'note' => 'Tipo allegato: ' . $type,
    ], studentiMovimentiCurrentActor());
    $docMap = [
        'modulo_iscrizione' => 'doc_modulo_iscrizione',
        'nulla_osta_entrata' => 'doc_nulla_osta_entrata',
        'nulla_osta' => 'doc_nulla_osta_entrata',
        'pagella_precedente' => 'doc_pagella_precedente',
        'documenti_carenze' => 'doc_carenze',
        'documenti_esami_integrativi' => 'doc_esami_integrativi',
    ];
    if (isset($docMap[$type])) {
        dbExec("
            UPDATE studenti_movimenti_pratiche
            SET `" . $docMap[$type] . "` = 'ricevuto',
                updated_at = NOW()
            WHERE id = " . dbI($practiceId) . "
            LIMIT 1
        ");
    }
}

function studentiMovimentiAttachFiles(int $practiceId, array $files, string $type): int
{
    if ($practiceId <= 0 || empty($files)) {
        return 0;
    }
    if (!is_array($files['name'] ?? null)) {
        studentiMovimentiAttachFile($practiceId, $files, $type);
        return intval(($files['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK);
    }

    $count = 0;
    $names = $files['name'] ?? [];
    $tmpNames = $files['tmp_name'] ?? [];
    $errors = $files['error'] ?? [];
    $types = $files['type'] ?? [];
    $sizes = $files['size'] ?? [];
    foreach ($names as $index => $name) {
        $file = [
            'name' => $name,
            'tmp_name' => $tmpNames[$index] ?? '',
            'error' => $errors[$index] ?? UPLOAD_ERR_NO_FILE,
            'type' => $types[$index] ?? '',
            'size' => $sizes[$index] ?? null,
        ];
        if (intval($file['error']) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        studentiMovimentiAttachFile($practiceId, $file, $type);
        $count++;
    }
    return $count;
}
