<?php

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/iscrizioniPrimeLib.php';
require_once __DIR__ . '/studentiMovimentiLib.php';
require_once __DIR__ . '/scuoleIstitutiLib.php';

function genitoriColloquiEnsureTables(): void
{
    dbExec("
        CREATE TABLE IF NOT EXISTS genitori_colloqui (
            id int NOT NULL AUTO_INCREMENT,
            ambito enum('entrata','uscita','iscrizione_prime','iscrizione_terze','altro') NOT NULL DEFAULT 'altro',
            id_pratica_iscrizione int DEFAULT NULL,
            id_movimento int DEFAULT NULL,
            cognome varchar(120) DEFAULT NULL,
            nome varchar(120) DEFAULT NULL,
            codice_fiscale varchar(16) DEFAULT NULL,
            classe varchar(40) DEFAULT NULL,
            anno_corso tinyint DEFAULT NULL,
            classe_iscrizione varchar(40) DEFAULT NULL,
            indirizzo_iscrizione varchar(255) DEFAULT NULL,
            gruppo_iscrizione varchar(120) DEFAULT NULL,
            id_istituto_provenienza int DEFAULT NULL,
            scuola_provenienza varchar(255) DEFAULT NULL,
            indirizzo_provenienza varchar(255) DEFAULT NULL,
            id_istituto_destinazione int DEFAULT NULL,
            scuola_destinazione varchar(255) DEFAULT NULL,
            indirizzo_destinazione varchar(255) DEFAULT NULL,
            referente_scuola_destinazione varchar(255) DEFAULT NULL,
            responsabile_1_tipo varchar(50) DEFAULT NULL,
            responsabile_1_cognome varchar(100) DEFAULT NULL,
            responsabile_1_nome varchar(100) DEFAULT NULL,
            responsabile_1_codice_fiscale varchar(16) DEFAULT NULL,
            email_genitore_1 varchar(255) DEFAULT NULL,
            telefono_genitore_1 varchar(50) DEFAULT NULL,
            responsabile_2_tipo varchar(50) DEFAULT NULL,
            responsabile_2_cognome varchar(100) DEFAULT NULL,
            responsabile_2_nome varchar(100) DEFAULT NULL,
            responsabile_2_codice_fiscale varchar(16) DEFAULT NULL,
            email_genitore_2 varchar(255) DEFAULT NULL,
            telefono_genitore_2 varchar(50) DEFAULT NULL,
            libri_da_restituire tinyint NOT NULL DEFAULT 0,
            libri_restituiti_at date DEFAULT NULL,
            ricevuta_libri_path varchar(500) DEFAULT NULL,
            ricevuta_libri_original_name varchar(255) DEFAULT NULL,
            ricevuta_libri_size int DEFAULT NULL,
            studente_bocciato tinyint NOT NULL DEFAULT 0,
            referente varchar(255) DEFAULT NULL,
            richiesta_data date DEFAULT NULL,
            appuntamento_at datetime DEFAULT NULL,
            stato enum('richiesto','da_fissare','fissato','svolto','approvato','non_approvato','annullato') NOT NULL DEFAULT 'richiesto',
            esito enum('','ingresso_ok','uscita_ok','integrazione','non_idoneo','rinuncia') NOT NULL DEFAULT '',
            esami_integrativi text DEFAULT NULL,
            carenze_note text DEFAULT NULL,
            libri_note text DEFAULT NULL,
            note text DEFAULT NULL,
            allegato_path varchar(500) DEFAULT NULL,
            allegato_original_name varchar(255) DEFAULT NULL,
            allegato_size int DEFAULT NULL,
            notifica_inviata_at datetime DEFAULT NULL,
            created_by varchar(255) DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_colloqui_ambito (ambito),
            KEY idx_colloqui_pratica (id_pratica_iscrizione),
            KEY idx_colloqui_movimento (id_movimento),
            KEY idx_colloqui_stato (stato),
            KEY idx_colloqui_appuntamento (appuntamento_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    dbExec("
        CREATE TABLE IF NOT EXISTS genitori_colloqui_incontri (
            id int NOT NULL AUTO_INCREMENT,
            colloquio_id int NOT NULL,
            incontro_at datetime DEFAULT NULL,
            tipo varchar(40) NOT NULL DEFAULT 'colloquio',
            referente varchar(255) DEFAULT NULL,
            partecipanti varchar(500) DEFAULT NULL,
            esito varchar(60) NOT NULL DEFAULT '',
            note mediumtext DEFAULT NULL,
            created_by varchar(255) DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_incontri_colloquio (colloquio_id),
            KEY idx_incontri_at (incontro_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    dbExec("
        CREATE TABLE IF NOT EXISTS genitori_colloqui_incontri_allegati (
            id int NOT NULL AUTO_INCREMENT,
            incontro_id int NOT NULL,
            nome_file varchar(255) NOT NULL,
            path_file varchar(500) NOT NULL,
            mime_type varchar(120) DEFAULT NULL,
            dimensione int DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_incontri_allegati_incontro (incontro_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    dbExec("
        CREATE TABLE IF NOT EXISTS genitori_colloqui_eventi (
            id int NOT NULL AUTO_INCREMENT,
            colloquio_id int NOT NULL,
            tipo_evento varchar(60) NOT NULL DEFAULT 'salvataggio',
            descrizione varchar(255) DEFAULT NULL,
            stato varchar(40) DEFAULT NULL,
            esito varchar(60) DEFAULT NULL,
            ambito varchar(40) DEFAULT NULL,
            dati_json mediumtext DEFAULT NULL,
            allegato_path varchar(500) DEFAULT NULL,
            allegato_original_name varchar(255) DEFAULT NULL,
            created_by varchar(255) DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_colloqui_eventi_colloquio (colloquio_id),
            KEY idx_colloqui_eventi_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    genitoriColloquiEnsureColumn('genitori_colloqui', 'anno_corso', "ALTER TABLE genitori_colloqui ADD COLUMN anno_corso tinyint DEFAULT NULL AFTER classe");
    genitoriColloquiEnsureColumn('genitori_colloqui', 'classe_iscrizione', "ALTER TABLE genitori_colloqui ADD COLUMN classe_iscrizione varchar(40) DEFAULT NULL AFTER anno_corso");
    genitoriColloquiEnsureColumn('genitori_colloqui', 'indirizzo_iscrizione', "ALTER TABLE genitori_colloqui ADD COLUMN indirizzo_iscrizione varchar(255) DEFAULT NULL AFTER classe_iscrizione");
    genitoriColloquiEnsureColumn('genitori_colloqui', 'gruppo_iscrizione', "ALTER TABLE genitori_colloqui ADD COLUMN gruppo_iscrizione varchar(120) DEFAULT NULL AFTER indirizzo_iscrizione");
    genitoriColloquiEnsureColumn('genitori_colloqui', 'id_istituto_provenienza', "ALTER TABLE genitori_colloqui ADD COLUMN id_istituto_provenienza int DEFAULT NULL AFTER gruppo_iscrizione");
    genitoriColloquiEnsureColumn('genitori_colloqui', 'scuola_provenienza', "ALTER TABLE genitori_colloqui ADD COLUMN scuola_provenienza varchar(255) DEFAULT NULL AFTER id_istituto_provenienza");
    genitoriColloquiEnsureColumn('genitori_colloqui', 'indirizzo_provenienza', "ALTER TABLE genitori_colloqui ADD COLUMN indirizzo_provenienza varchar(255) DEFAULT NULL AFTER scuola_provenienza");
    genitoriColloquiEnsureColumn('genitori_colloqui', 'id_istituto_destinazione', "ALTER TABLE genitori_colloqui ADD COLUMN id_istituto_destinazione int DEFAULT NULL AFTER indirizzo_provenienza");
    genitoriColloquiEnsureColumn('genitori_colloqui', 'scuola_destinazione', "ALTER TABLE genitori_colloqui ADD COLUMN scuola_destinazione varchar(255) DEFAULT NULL AFTER id_istituto_destinazione");
    genitoriColloquiEnsureColumn('genitori_colloqui', 'indirizzo_destinazione', "ALTER TABLE genitori_colloqui ADD COLUMN indirizzo_destinazione varchar(255) DEFAULT NULL AFTER scuola_destinazione");
    genitoriColloquiEnsureColumn('genitori_colloqui', 'referente_scuola_destinazione', "ALTER TABLE genitori_colloqui ADD COLUMN referente_scuola_destinazione varchar(255) DEFAULT NULL AFTER indirizzo_destinazione");
    $parentColumns = [
        'responsabile_1_tipo' => "ALTER TABLE genitori_colloqui ADD COLUMN responsabile_1_tipo varchar(50) DEFAULT NULL AFTER indirizzo_provenienza",
        'responsabile_1_cognome' => "ALTER TABLE genitori_colloqui ADD COLUMN responsabile_1_cognome varchar(100) DEFAULT NULL AFTER responsabile_1_tipo",
        'responsabile_1_nome' => "ALTER TABLE genitori_colloqui ADD COLUMN responsabile_1_nome varchar(100) DEFAULT NULL AFTER responsabile_1_cognome",
        'responsabile_1_codice_fiscale' => "ALTER TABLE genitori_colloqui ADD COLUMN responsabile_1_codice_fiscale varchar(16) DEFAULT NULL AFTER responsabile_1_nome",
        'email_genitore_1' => "ALTER TABLE genitori_colloqui ADD COLUMN email_genitore_1 varchar(255) DEFAULT NULL AFTER responsabile_1_codice_fiscale",
        'telefono_genitore_1' => "ALTER TABLE genitori_colloqui ADD COLUMN telefono_genitore_1 varchar(50) DEFAULT NULL AFTER email_genitore_1",
        'responsabile_2_tipo' => "ALTER TABLE genitori_colloqui ADD COLUMN responsabile_2_tipo varchar(50) DEFAULT NULL AFTER telefono_genitore_1",
        'responsabile_2_cognome' => "ALTER TABLE genitori_colloqui ADD COLUMN responsabile_2_cognome varchar(100) DEFAULT NULL AFTER responsabile_2_tipo",
        'responsabile_2_nome' => "ALTER TABLE genitori_colloqui ADD COLUMN responsabile_2_nome varchar(100) DEFAULT NULL AFTER responsabile_2_cognome",
        'responsabile_2_codice_fiscale' => "ALTER TABLE genitori_colloqui ADD COLUMN responsabile_2_codice_fiscale varchar(16) DEFAULT NULL AFTER responsabile_2_nome",
        'email_genitore_2' => "ALTER TABLE genitori_colloqui ADD COLUMN email_genitore_2 varchar(255) DEFAULT NULL AFTER responsabile_2_codice_fiscale",
        'telefono_genitore_2' => "ALTER TABLE genitori_colloqui ADD COLUMN telefono_genitore_2 varchar(50) DEFAULT NULL AFTER email_genitore_2",
    ];
    foreach ($parentColumns as $column => $alterSql) {
        genitoriColloquiEnsureColumn('genitori_colloqui', $column, $alterSql);
    }
    genitoriColloquiEnsureColumn('genitori_colloqui', 'libri_da_restituire', "ALTER TABLE genitori_colloqui ADD COLUMN libri_da_restituire tinyint NOT NULL DEFAULT 0 AFTER indirizzo_destinazione");
    genitoriColloquiEnsureColumn('genitori_colloqui', 'libri_restituiti_at', "ALTER TABLE genitori_colloqui ADD COLUMN libri_restituiti_at date DEFAULT NULL AFTER libri_da_restituire");
    genitoriColloquiEnsureColumn('genitori_colloqui', 'ricevuta_libri_path', "ALTER TABLE genitori_colloqui ADD COLUMN ricevuta_libri_path varchar(500) DEFAULT NULL AFTER libri_restituiti_at");
    genitoriColloquiEnsureColumn('genitori_colloqui', 'ricevuta_libri_original_name', "ALTER TABLE genitori_colloqui ADD COLUMN ricevuta_libri_original_name varchar(255) DEFAULT NULL AFTER ricevuta_libri_path");
    genitoriColloquiEnsureColumn('genitori_colloqui', 'ricevuta_libri_size', "ALTER TABLE genitori_colloqui ADD COLUMN ricevuta_libri_size int DEFAULT NULL AFTER ricevuta_libri_original_name");
    genitoriColloquiEnsureColumn('genitori_colloqui', 'studente_bocciato', "ALTER TABLE genitori_colloqui ADD COLUMN studente_bocciato tinyint NOT NULL DEFAULT 0 AFTER ricevuta_libri_size");
    genitoriColloquiBackfillLegacyIncontri();
    genitoriColloquiBackfillMovementClasseOrigine();
    genitoriColloquiBackfillMovementLinks();
}

function genitoriColloquiEnsureColumn(string $table, string $column, string $alterSql): void
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

function genitoriColloquiBackfillLegacyIncontri(): void
{
    dbExec("
        INSERT INTO genitori_colloqui_incontri
            (colloquio_id, incontro_at, tipo, referente, partecipanti, esito, note, created_by, created_at, updated_at)
        SELECT
            c.id,
            c.appuntamento_at,
            'colloquio',
            c.referente,
            '',
            c.esito,
            c.note,
            c.created_by,
            COALESCE(c.created_at, NOW()),
            COALESCE(c.updated_at, NOW())
        FROM genitori_colloqui c
        WHERE ((c.note IS NOT NULL AND TRIM(c.note) <> '') OR c.appuntamento_at IS NOT NULL)
          AND NOT EXISTS (
              SELECT 1
              FROM genitori_colloqui_incontri i
              WHERE i.colloquio_id = c.id
              LIMIT 1
          )
    ");
}

function genitoriColloquiBackfillMovementClasseOrigine(): void
{
    dbExec("
        UPDATE studenti_movimenti_pratiche m
        INNER JOIN genitori_colloqui c ON c.id_movimento = m.id
        SET m.classe_origine = TRIM(c.classe),
            m.updated_at = NOW()
        WHERE TRIM(COALESCE(c.classe, '')) <> ''
          AND TRIM(COALESCE(m.classe_origine, '')) = ''
    ");
}

function genitoriColloquiBackfillMovementLinks(): void
{
    if (!dbGetValue("SHOW TABLES LIKE 'studenti_movimenti_pratiche'")) {
        return;
    }
    dbExec("
        UPDATE genitori_colloqui c
        INNER JOIN studenti_movimenti_pratiche m
         ON m.tipo_pratica = CASE WHEN c.ambito = 'entrata' THEN 'entrata' ELSE m.tipo_pratica END
         AND (
              (TRIM(COALESCE(c.codice_fiscale, '')) <> ''
               AND UPPER(TRIM(COALESCE(c.codice_fiscale, ''))) = UPPER(TRIM(COALESCE(m.codice_fiscale, ''))))
              OR
              (TRIM(COALESCE(c.cognome, '')) <> ''
               AND TRIM(COALESCE(c.nome, '')) <> ''
               AND LOWER(TRIM(COALESCE(c.cognome, ''))) = LOWER(TRIM(COALESCE(m.cognome, '')))
               AND LOWER(TRIM(COALESCE(c.nome, ''))) = LOWER(TRIM(COALESCE(m.nome, '')))
               AND (TRIM(COALESCE(c.codice_fiscale, '')) = '' OR TRIM(COALESCE(m.codice_fiscale, '')) = '')
              )
         )
        SET c.id_movimento = m.id,
            c.updated_at = NOW()
        WHERE c.ambito IN ('entrata', 'uscita')
          AND (c.id_movimento IS NULL OR c.id_movimento = 0)
          AND COALESCE(m.fonte, '') <> 'colloquio_genitori'
          AND (
              (c.ambito = 'entrata' AND m.tipo_pratica = 'entrata')
              OR (c.ambito = 'uscita' AND m.tipo_pratica <> 'entrata')
          )
    ");
}

function genitoriColloquiCleanupAutoCreatedMovimenti(): array
{
    genitoriColloquiEnsureTables();
    if (!dbGetValue("SHOW TABLES LIKE 'studenti_movimenti_pratiche'")) {
        return ['found' => 0, 'deleted' => 0, 'unlinked' => 0];
    }

    $rows = dbGetAll("
        SELECT id
        FROM studenti_movimenti_pratiche
        WHERE fonte = 'colloquio_genitori'
        ORDER BY id ASC
    ") ?: [];
    $ids = array_values(array_filter(array_map(static function ($row) {
        return intval($row['id'] ?? 0);
    }, $rows), static fn($id) => $id > 0));

    if (!$ids) {
        return ['found' => 0, 'deleted' => 0, 'unlinked' => 0];
    }

    $idList = implode(',', $ids);
    $unlinked = intval(dbGetValue("
        SELECT COUNT(*)
        FROM genitori_colloqui
        WHERE id_movimento IN ($idList)
    ") ?? 0);
    dbExec("
        UPDATE genitori_colloqui
        SET id_movimento = NULL,
            updated_at = NOW()
        WHERE id_movimento IN ($idList)
    ");

    $deleted = 0;
    foreach ($ids as $id) {
        if (studentiMovimentiDeletePractice($id)) {
            $deleted++;
        }
    }

    return ['found' => count($ids), 'deleted' => $deleted, 'unlinked' => $unlinked];
}

function genitoriColloquiActor(): string
{
    global $__useremail, $__utente_nome, $__utente_cognome;
    $name = trim((string)($__utente_nome ?? '') . ' ' . (string)($__utente_cognome ?? ''));
    return $name !== '' ? $name : trim((string)($__useremail ?? ''));
}

function genitoriColloquiUpperName($value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
}

function genitoriColloquiUploadDir(int $id): string
{
    return __DIR__ . '/../data/genitori_colloqui/' . $id;
}

function genitoriColloquiNormalizeDate(?string $value): ?string
{
    $value = trim((string)$value);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
}

function genitoriColloquiNormalizeDateTime(?string $date, ?string $time): ?string
{
    $date = genitoriColloquiNormalizeDate($date);
    $time = trim((string)$time);
    if (!$date || !preg_match('/^\d{2}:\d{2}$/', $time)) {
        return null;
    }
    return $date . ' ' . $time . ':00';
}

function genitoriColloquiAllowed(string $value, array $allowed, string $default): string
{
    $value = trim($value);
    return in_array($value, $allowed, true) ? $value : $default;
}

function genitoriColloquiFindOrCreateStudentForEntrata(array $fields): int
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

function genitoriColloquiSyncContactsToMovement(int $movementId, array $fields): void
{
    $ambito = (string)($fields['ambito'] ?? '');
    if ($movementId <= 0 || !in_array($ambito, ['entrata', 'uscita'], true)) {
        return;
    }
    $updates = [];
    foreach ([
        'cognome',
        'nome',
        'codice_fiscale',
        'classe_iscrizione' => 'classe_richiesta',
    ] as $source => $target) {
        if (is_int($source)) {
            $source = $target;
        }
        $value = trim((string)($fields[$source] ?? ''));
        if ($value !== '') {
            $updates[] = "`$target` = " . dbQ($value);
        }
    }
    $classeOrigine = trim((string)($fields['classe'] ?? ''));
    if ($classeOrigine === '') {
        $classeOrigine = trim((string)($fields['indirizzo_provenienza'] ?? ''));
    }
    if ($classeOrigine !== '') {
        $updates[] = "`classe_origine` = " . dbQ($classeOrigine);
        if ($ambito === 'entrata') {
            $updates[] = "`indirizzo_provenienza` = ''";
        }
    }
    $annoCorso = intval($fields['anno_corso'] ?? 0);
    if ($annoCorso > 0) {
        $updates[] = "`anno_corso` = " . dbI($annoCorso);
    }
    if ($ambito === 'entrata') {
        foreach ([
            'id_istituto_provenienza',
            'scuola_provenienza',
            'indirizzo_iscrizione' => 'indirizzo_destinazione',
        ] as $source => $target) {
            if (is_int($source)) {
                $source = $target;
            }
            if ($source === 'id_istituto_provenienza') {
                $value = intval($fields[$source] ?? 0);
                if ($value > 0) {
                    $updates[] = "`$target` = " . dbI($value);
                }
                continue;
            }
            $value = trim((string)($fields[$source] ?? ''));
            if ($value !== '') {
                $updates[] = "`$target` = " . dbQ($value);
            }
        }
    } elseif ($ambito === 'uscita') {
        foreach ([
            'id_istituto_destinazione',
            'scuola_destinazione',
            'indirizzo_destinazione',
        ] as $field) {
            if ($field === 'id_istituto_destinazione') {
                $value = intval($fields[$field] ?? 0);
                if ($value > 0) {
                    $updates[] = "`$field` = " . dbI($value);
                }
                continue;
            }
            $value = trim((string)($fields[$field] ?? ''));
            if ($value !== '') {
                $updates[] = "`$field` = " . dbQ($value);
            }
        }
    }
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
        'carenze_note',
    ] as $field) {
        $value = trim((string)($fields[$field] ?? ''));
        if ($value !== '') {
            $updates[] = "`$field` = " . dbQ($value);
        }
    }
    if (trim((string)($fields['esami_integrativi'] ?? '')) !== '') {
        $updates[] = "`esami_integrativi` = 1";
        $updates[] = "`esami_integrativi_note` = " . dbQ(trim((string)$fields['esami_integrativi']));
        $updates[] = "`doc_esami_integrativi` = IF(`doc_esami_integrativi` = 'non_necessario', 'mancante', `doc_esami_integrativi`)";
    }
    if (trim((string)($fields['carenze_note'] ?? '')) !== '') {
        $updates[] = "`carenze_presenti` = 1";
        $updates[] = "`doc_carenze` = IF(`doc_carenze` = 'non_necessario', 'mancante', `doc_carenze`)";
    }
    if (!$updates) {
        return;
    }
    $updates[] = "updated_at = NOW()";
    dbExec("
        UPDATE studenti_movimenti_pratiche
        SET " . implode(", ", $updates) . "
        WHERE id = " . dbI($movementId) . "
        LIMIT 1
    ");
}

function genitoriColloquiClearEsamiIntegrativiForMovement(int $movementId): int
{
    if ($movementId <= 0) {
        return 0;
    }
    genitoriColloquiEnsureTables();

    $rows = dbGetAll("
        SELECT id, esito, esami_integrativi
        FROM genitori_colloqui
        WHERE id_movimento = " . dbI($movementId) . "
          AND ambito = 'entrata'
          AND (esito = 'integrazione' OR TRIM(COALESCE(esami_integrativi, '')) <> '')
    ") ?: [];
    if (!$rows) {
        return 0;
    }

    foreach ($rows as $row) {
        genitoriColloquiAddEvent((int)$row['id'], 'sync_movimento', 'Esami integrativi rimossi da Entrate / uscite', [
            'esito_precedente' => (string)($row['esito'] ?? ''),
            'esami_integrativi_precedenti' => (string)($row['esami_integrativi'] ?? ''),
        ]);
    }

    dbExec("
        UPDATE genitori_colloqui
        SET esito = IF(esito = 'integrazione', '', esito),
            esami_integrativi = NULL,
            updated_at = NOW()
        WHERE id_movimento = " . dbI($movementId) . "
          AND ambito = 'entrata'
          AND (esito = 'integrazione' OR TRIM(COALESCE(esami_integrativi, '')) <> '')
    ");

    return count($rows);
}

function genitoriColloquiSave(array $data, ?array $file = null, ?array $receiptFile = null): int
{
    genitoriColloquiEnsureTables();

    $id = intval($data['id'] ?? 0);
    $ambito = genitoriColloquiAllowed((string)($data['ambito'] ?? 'altro'), ['entrata','uscita','iscrizione_prime','iscrizione_terze','altro'], 'altro');
    $stato = genitoriColloquiAllowed((string)($data['stato'] ?? 'richiesto'), ['richiesto','da_fissare','fissato','svolto','approvato','non_approvato','annullato'], 'richiesto');
    $esito = genitoriColloquiAllowed((string)($data['esito'] ?? ''), ['','ingresso_ok','uscita_ok','integrazione','non_idoneo','rinuncia'], '');
    if (($ambito === 'entrata' && $esito === 'uscita_ok') || ($ambito === 'uscita' && $esito === 'ingresso_ok')) {
        $esito = '';
    }
    $appuntamentoAt = genitoriColloquiNormalizeDateTime($data['appuntamento_data'] ?? null, $data['appuntamento_ora'] ?? null);
    $richiestaData = genitoriColloquiNormalizeDate($data['richiesta_data'] ?? null);
    $libriRestituitiAt = genitoriColloquiNormalizeDate($data['libri_restituiti_at'] ?? null);
    $idIstitutoProvenienza = intval($data['id_istituto_provenienza'] ?? 0) ?: null;
    $scuolaProvenienza = trim((string)($data['scuola_provenienza'] ?? ''));
    $istitutoProvenienzaName = scuoleIstitutiNameById($idIstitutoProvenienza);
    if ($istitutoProvenienzaName !== '') {
        $scuolaProvenienza = $istitutoProvenienzaName;
    }
    $idIstitutoDestinazione = intval($data['id_istituto_destinazione'] ?? 0) ?: null;
    $scuolaDestinazione = trim((string)($data['scuola_destinazione'] ?? ''));
    $istitutoName = scuoleIstitutiNameById($idIstitutoDestinazione);
    if ($istitutoName !== '') {
        $scuolaDestinazione = $istitutoName;
    }

    $fields = [
        'ambito' => $ambito,
        'id_pratica_iscrizione' => intval($data['id_pratica_iscrizione'] ?? 0) ?: null,
        'id_movimento' => intval($data['id_movimento'] ?? 0) ?: null,
        'cognome' => genitoriColloquiUpperName($data['cognome'] ?? ''),
        'nome' => genitoriColloquiUpperName($data['nome'] ?? ''),
        'codice_fiscale' => strtoupper(trim((string)($data['codice_fiscale'] ?? ''))),
        'classe' => trim((string)($data['classe'] ?? '')),
        'anno_corso' => intval($data['anno_corso'] ?? 0) ?: null,
        'classe_iscrizione' => trim((string)($data['classe_iscrizione'] ?? '')),
        'indirizzo_iscrizione' => trim((string)($data['indirizzo_iscrizione'] ?? '')),
        'gruppo_iscrizione' => trim((string)($data['gruppo_iscrizione'] ?? '')),
        'id_istituto_provenienza' => $idIstitutoProvenienza,
        'scuola_provenienza' => $scuolaProvenienza,
        'indirizzo_provenienza' => trim((string)($data['indirizzo_provenienza'] ?? '')),
        'id_istituto_destinazione' => $idIstitutoDestinazione,
        'scuola_destinazione' => $scuolaDestinazione,
        'indirizzo_destinazione' => trim((string)($data['indirizzo_destinazione'] ?? '')),
        'referente_scuola_destinazione' => trim((string)($data['referente_scuola_destinazione'] ?? '')),
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
        'libri_da_restituire' => !empty($data['libri_da_restituire']) ? 1 : 0,
        'libri_restituiti_at' => $libriRestituitiAt,
        'studente_bocciato' => !empty($data['studente_bocciato']) ? 1 : 0,
        'referente' => trim((string)($data['referente'] ?? 'prof.ssa Ceschini')),
        'richiesta_data' => $richiestaData,
        'appuntamento_at' => $appuntamentoAt,
        'stato' => $stato,
        'esito' => $esito,
        'esami_integrativi' => trim((string)($data['esami_integrativi'] ?? '')),
        'carenze_note' => trim((string)($data['carenze_note'] ?? '')),
        'libri_note' => trim((string)($data['libri_note'] ?? '')),
        'note' => trim((string)($data['note'] ?? '')),
    ];
    if ($id > 0) {
        dbExec("
            UPDATE genitori_colloqui SET
                ambito = " . dbQ($fields['ambito']) . ",
                id_pratica_iscrizione = " . dbI($fields['id_pratica_iscrizione']) . ",
                id_movimento = " . dbI($fields['id_movimento']) . ",
                cognome = " . dbQ($fields['cognome']) . ",
                nome = " . dbQ($fields['nome']) . ",
                codice_fiscale = " . dbQ($fields['codice_fiscale']) . ",
                classe = " . dbQ($fields['classe']) . ",
                anno_corso = " . dbI($fields['anno_corso']) . ",
                classe_iscrizione = " . dbQ($fields['classe_iscrizione']) . ",
                indirizzo_iscrizione = " . dbQ($fields['indirizzo_iscrizione']) . ",
                gruppo_iscrizione = " . dbQ($fields['gruppo_iscrizione']) . ",
                id_istituto_provenienza = " . dbI($fields['id_istituto_provenienza']) . ",
                scuola_provenienza = " . dbQ($fields['scuola_provenienza']) . ",
                indirizzo_provenienza = " . dbQ($fields['indirizzo_provenienza']) . ",
                id_istituto_destinazione = " . dbI($fields['id_istituto_destinazione']) . ",
                scuola_destinazione = " . dbQ($fields['scuola_destinazione']) . ",
                indirizzo_destinazione = " . dbQ($fields['indirizzo_destinazione']) . ",
                referente_scuola_destinazione = " . dbQ($fields['referente_scuola_destinazione']) . ",
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
                libri_da_restituire = " . dbI($fields['libri_da_restituire']) . ",
                libri_restituiti_at = " . dbQ($fields['libri_restituiti_at']) . ",
                studente_bocciato = " . dbI($fields['studente_bocciato']) . ",
                referente = " . dbQ($fields['referente']) . ",
                richiesta_data = " . dbQ($fields['richiesta_data']) . ",
                appuntamento_at = " . dbQ($fields['appuntamento_at']) . ",
                stato = " . dbQ($fields['stato']) . ",
                esito = " . dbQNotNull($fields['esito'], '') . ",
                esami_integrativi = " . dbQ($fields['esami_integrativi']) . ",
                carenze_note = " . dbQ($fields['carenze_note']) . ",
                libri_note = " . dbQ($fields['libri_note']) . ",
                note = " . dbQ($fields['note']) . ",
                updated_at = NOW()
            WHERE id = " . dbI($id) . "
            LIMIT 1
        ");
    } else {
        dbExec("
            INSERT INTO genitori_colloqui
                (ambito, id_pratica_iscrizione, id_movimento, cognome, nome, codice_fiscale, classe, anno_corso, classe_iscrizione, indirizzo_iscrizione, gruppo_iscrizione, id_istituto_provenienza, scuola_provenienza, indirizzo_provenienza, id_istituto_destinazione, scuola_destinazione, indirizzo_destinazione, referente_scuola_destinazione, responsabile_1_tipo, responsabile_1_cognome, responsabile_1_nome, responsabile_1_codice_fiscale, email_genitore_1, telefono_genitore_1, responsabile_2_tipo, responsabile_2_cognome, responsabile_2_nome, responsabile_2_codice_fiscale, email_genitore_2, telefono_genitore_2, libri_da_restituire, libri_restituiti_at, studente_bocciato, referente, richiesta_data, appuntamento_at, stato, esito, esami_integrativi, carenze_note, libri_note, note, created_by, created_at, updated_at)
            VALUES
                (
                    " . dbQ($fields['ambito']) . ",
                    " . dbI($fields['id_pratica_iscrizione']) . ",
                    " . dbI($fields['id_movimento']) . ",
                    " . dbQ($fields['cognome']) . ",
                    " . dbQ($fields['nome']) . ",
                    " . dbQ($fields['codice_fiscale']) . ",
                    " . dbQ($fields['classe']) . ",
                    " . dbI($fields['anno_corso']) . ",
                    " . dbQ($fields['classe_iscrizione']) . ",
                    " . dbQ($fields['indirizzo_iscrizione']) . ",
                    " . dbQ($fields['gruppo_iscrizione']) . ",
                    " . dbI($fields['id_istituto_provenienza']) . ",
                    " . dbQ($fields['scuola_provenienza']) . ",
                    " . dbQ($fields['indirizzo_provenienza']) . ",
                    " . dbI($fields['id_istituto_destinazione']) . ",
                    " . dbQ($fields['scuola_destinazione']) . ",
                    " . dbQ($fields['indirizzo_destinazione']) . ",
                    " . dbQ($fields['referente_scuola_destinazione']) . ",
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
                    " . dbI($fields['libri_da_restituire']) . ",
                    " . dbQ($fields['libri_restituiti_at']) . ",
                    " . dbI($fields['studente_bocciato']) . ",
                    " . dbQ($fields['referente']) . ",
                    " . dbQ($fields['richiesta_data']) . ",
                    " . dbQ($fields['appuntamento_at']) . ",
                    " . dbQ($fields['stato']) . ",
                    " . dbQNotNull($fields['esito'], '') . ",
                    " . dbQ($fields['esami_integrativi']) . ",
                    " . dbQ($fields['carenze_note']) . ",
                    " . dbQ($fields['libri_note']) . ",
                    " . dbQ($fields['note']) . ",
                    " . dbQ(genitoriColloquiActor()) . ",
                    NOW(),
                    NOW()
                )
        ");
        $id = intval(dblastId());
    }

    if ($file && intval($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        genitoriColloquiAttachFile($id, $file);
    }
    if ($receiptFile && intval($receiptFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        genitoriColloquiAttachReceipt($id, $receiptFile);
    }

    genitoriColloquiAddEvent($id, $id > 0 && intval($data['id'] ?? 0) > 0 ? 'aggiornamento' : 'creazione', 'Colloquio salvato', $fields);
    genitoriColloquiSyncContactsToMovement(intval($fields['id_movimento'] ?? 0), $fields);
    if (in_array($fields['ambito'], ['entrata', 'iscrizione_prime', 'iscrizione_terze'], true)) {
        iscrizioniPrimeSyncBocciatoAltraScuola(!empty($fields['studente_bocciato']), [
            'id_pratica_iscrizione' => $fields['id_pratica_iscrizione'],
            'id_movimento' => $fields['id_movimento'],
            'id_colloquio' => $id,
            'codice_fiscale' => $fields['codice_fiscale'],
        ]);
    }

    if (in_array($stato, ['svolto','approvato','non_approvato'], true) || $esito !== '') {
        genitoriColloquiPropagateOutcome($id);
    }

    return $id;
}

function genitoriColloquiAttachFile(int $id, array $file): void
{
    if ($id <= 0 || intval($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
        return;
    }
    $original = basename((string)($file['name'] ?? 'colloquio.pdf'));
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
        throw new RuntimeException('Allegato non valido: carica PDF o immagini JPG/PNG.');
    }
    $dir = genitoriColloquiUploadDir($id);
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException('Impossibile creare la cartella allegati colloquio.');
    }
    $safeName = preg_replace('/[^A-Za-z0-9_.-]+/u', '_', $original);
    $target = $dir . '/' . date('Ymd_His') . '_' . $safeName;
    if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
        throw new RuntimeException('Impossibile salvare allegato colloquio.');
    }
    $relative = 'data/genitori_colloqui/' . $id . '/' . basename($target);
    dbExec("
        UPDATE genitori_colloqui SET
            allegato_path = " . dbQ($relative) . ",
            allegato_original_name = " . dbQ($original) . ",
            allegato_size = " . dbI(filesize($target) ?: intval($file['size'] ?? 0)) . ",
            updated_at = NOW()
        WHERE id = " . dbI($id) . "
        LIMIT 1
    ");
}

function genitoriColloquiAttachReceipt(int $id, array $file): void
{
    if ($id <= 0 || intval($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
        return;
    }
    $original = basename((string)($file['name'] ?? 'ricevuta_libri.pdf'));
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
        throw new RuntimeException('Ricevuta libri non valida: carica PDF o immagini JPG/PNG.');
    }
    $dir = genitoriColloquiUploadDir($id);
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException('Impossibile creare la cartella allegati colloquio.');
    }
    $safeName = preg_replace('/[^A-Za-z0-9_.-]+/u', '_', $original);
    $target = $dir . '/' . date('Ymd_His') . '_ricevuta_libri_' . $safeName;
    if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
        throw new RuntimeException('Impossibile salvare ricevuta libri.');
    }
    $relative = 'data/genitori_colloqui/' . $id . '/' . basename($target);
    dbExec("
        UPDATE genitori_colloqui SET
            ricevuta_libri_path = " . dbQ($relative) . ",
            ricevuta_libri_original_name = " . dbQ($original) . ",
            ricevuta_libri_size = " . dbI(filesize($target) ?: intval($file['size'] ?? 0)) . ",
            updated_at = NOW()
        WHERE id = " . dbI($id) . "
        LIMIT 1
    ");
}

function genitoriColloquiIncontroUploadDir(int $incontroId): string
{
    return __DIR__ . '/../data/genitori_colloqui/incontri/' . $incontroId;
}

function genitoriColloquiSaveIncontro(array $data, ?array $files = null): int
{
    genitoriColloquiEnsureTables();
    $colloquioId = intval($data['colloquio_id'] ?? 0);
    if ($colloquioId <= 0) {
        throw new RuntimeException('Salva prima la scheda generale del colloquio.');
    }
    $exists = dbGetValue("SELECT id FROM genitori_colloqui WHERE id = " . dbI($colloquioId) . " LIMIT 1");
    if (!$exists) {
        throw new RuntimeException('Scheda colloquio non trovata.');
    }

    $id = intval($data['incontro_id'] ?? 0);
    $tipo = genitoriColloquiAllowed((string)($data['incontro_tipo'] ?? 'colloquio'), ['colloquio','telefono','mail','incontro_scuola','altro'], 'colloquio');
    $esito = genitoriColloquiAllowed((string)($data['incontro_esito'] ?? ''), ['','ingresso_ok','uscita_ok','integrazione','non_idoneo','rinuncia'], '');
    $ambitoColloquio = (string)dbGetValue("SELECT ambito FROM genitori_colloqui WHERE id = " . dbI($colloquioId) . " LIMIT 1");
    if (($ambitoColloquio === 'entrata' && $esito === 'uscita_ok') || ($ambitoColloquio === 'uscita' && $esito === 'ingresso_ok')) {
        $esito = '';
    }
    $incontroAt = genitoriColloquiNormalizeDateTime($data['incontro_data'] ?? null, $data['incontro_ora'] ?? null);
    $referente = trim((string)($data['incontro_referente'] ?? ''));
    $partecipanti = trim((string)($data['incontro_partecipanti'] ?? ''));
    $note = trim((string)($data['incontro_note'] ?? ''));

    if ($id > 0) {
        dbExec("
            UPDATE genitori_colloqui_incontri SET
                incontro_at = " . dbQ($incontroAt) . ",
                tipo = " . dbQ($tipo) . ",
                referente = " . dbQ($referente) . ",
                partecipanti = " . dbQ($partecipanti) . ",
                esito = " . dbQNotNull($esito, '') . ",
                note = " . dbQ($note) . ",
                updated_at = NOW()
            WHERE id = " . dbI($id) . "
              AND colloquio_id = " . dbI($colloquioId) . "
            LIMIT 1
        ");
    } else {
        dbExec("
            INSERT INTO genitori_colloqui_incontri
                (colloquio_id, incontro_at, tipo, referente, partecipanti, esito, note, created_by, created_at, updated_at)
            VALUES
                (
                    " . dbI($colloquioId) . ",
                    " . dbQ($incontroAt) . ",
                    " . dbQ($tipo) . ",
                    " . dbQ($referente) . ",
                    " . dbQ($partecipanti) . ",
                    " . dbQNotNull($esito, '') . ",
                    " . dbQ($note) . ",
                    " . dbQ(genitoriColloquiActor()) . ",
                    NOW(),
                    NOW()
                )
        ");
        $id = intval(dblastId());
    }

    genitoriColloquiAttachIncontroFiles($id, $files);
    genitoriColloquiAddEvent($colloquioId, 'incontro', 'Colloquio/incontro registrato', [
        'stato' => 'svolto',
        'esito' => $esito,
        'note' => $note,
        'referente' => $referente,
        'partecipanti' => $partecipanti,
    ]);
    dbExec("
        UPDATE genitori_colloqui
        SET stato = 'svolto',
            esito = " . dbQNotNull($esito, '') . ",
            appuntamento_at = COALESCE(appuntamento_at, " . dbQ($incontroAt) . "),
            updated_at = NOW()
        WHERE id = " . dbI($colloquioId) . "
        LIMIT 1
    ");
    genitoriColloquiPropagateOutcome($colloquioId);

    return $id;
}

function genitoriColloquiAttachIncontroFiles(int $incontroId, ?array $files): void
{
    if ($incontroId <= 0 || !$files || empty($files['name'])) {
        return;
    }
    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
    $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
    $types = is_array($files['type']) ? $files['type'] : [$files['type'] ?? null];
    $sizes = is_array($files['size']) ? $files['size'] : [$files['size'] ?? null];
    $dir = genitoriColloquiIncontroUploadDir($incontroId);
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException('Impossibile creare la cartella allegati incontro.');
    }
    foreach ($names as $index => $name) {
        if (intval($errors[$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($tmpNames[$index])) {
            continue;
        }
        $original = basename((string)$name);
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $safeName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . ($extension ? '.' . $extension : '');
        $target = $dir . '/' . $safeName;
        if (!move_uploaded_file((string)$tmpNames[$index], $target)) {
            throw new RuntimeException('Impossibile salvare allegato incontro.');
        }
        $relative = 'data/genitori_colloqui/incontri/' . $incontroId . '/' . $safeName;
        dbExec("
            INSERT INTO genitori_colloqui_incontri_allegati
                (incontro_id, nome_file, path_file, mime_type, dimensione, created_at)
            VALUES
                (
                    " . dbI($incontroId) . ",
                    " . dbQ($original) . ",
                    " . dbQ($relative) . ",
                    " . dbQ((string)($types[$index] ?? '')) . ",
                    " . dbI(intval($sizes[$index] ?? 0) ?: null) . ",
                    NOW()
                )
        ");
    }
}

function genitoriColloquiIncontriForIds(array $ids): array
{
    $ids = array_values(array_filter(array_map('intval', $ids), static fn($id) => $id > 0));
    if (!$ids) {
        return [];
    }
    genitoriColloquiEnsureTables();
    $rows = dbGetAll("
        SELECT *
        FROM genitori_colloqui_incontri
        WHERE colloquio_id IN (" . implode(',', $ids) . ")
        ORDER BY COALESCE(incontro_at, created_at) DESC, id DESC
    ") ?: [];
    $incontroIds = array_values(array_filter(array_map(static fn($row) => intval($row['id'] ?? 0), $rows)));
    $attachments = [];
    if ($incontroIds) {
        $attachmentRows = dbGetAll("
            SELECT *
            FROM genitori_colloqui_incontri_allegati
            WHERE incontro_id IN (" . implode(',', $incontroIds) . ")
            ORDER BY created_at DESC, id DESC
        ") ?: [];
        foreach ($attachmentRows as $attachment) {
            $attachments[intval($attachment['incontro_id'] ?? 0)][] = $attachment;
        }
    }
    $grouped = [];
    foreach ($rows as $row) {
        $row['allegati'] = $attachments[intval($row['id'] ?? 0)] ?? [];
        $grouped[intval($row['colloquio_id'] ?? 0)][] = $row;
    }
    return $grouped;
}

function genitoriColloquiAddEvent(int $id, string $type, string $description, array $fields = []): void
{
    if ($id <= 0) {
        return;
    }
    $row = dbGetFirst("SELECT allegato_path, allegato_original_name FROM genitori_colloqui WHERE id = " . dbI($id) . " LIMIT 1") ?: [];
    $dataJson = json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $lastEvent = dbGetFirst("
        SELECT colloquio_id, tipo_evento, descrizione, stato, esito, ambito, dati_json, allegato_path, allegato_original_name
        FROM genitori_colloqui_eventi
        WHERE colloquio_id = " . dbI($id) . "
        ORDER BY id DESC
        LIMIT 1
    ") ?: null;
    if ($lastEvent && genitoriColloquiEventDuplicateKey($lastEvent) === genitoriColloquiEventDuplicateKey([
        'colloquio_id' => $id,
        'tipo_evento' => $type,
        'descrizione' => $description,
        'stato' => $fields['stato'] ?? null,
        'esito' => $fields['esito'] ?? null,
        'ambito' => $fields['ambito'] ?? null,
        'dati_json' => $dataJson,
        'allegato_path' => $row['allegato_path'] ?? null,
        'allegato_original_name' => $row['allegato_original_name'] ?? null,
    ])) {
        return;
    }
    dbExec("
        INSERT INTO genitori_colloqui_eventi
            (colloquio_id, tipo_evento, descrizione, stato, esito, ambito, dati_json, allegato_path, allegato_original_name, created_by, created_at)
        VALUES
            (
                " . dbI($id) . ",
                " . dbQ($type) . ",
                " . dbQ($description) . ",
                " . dbQ($fields['stato'] ?? null) . ",
                " . dbQ($fields['esito'] ?? null) . ",
                " . dbQ($fields['ambito'] ?? null) . ",
                " . dbQ($dataJson) . ",
                " . dbQ($row['allegato_path'] ?? null) . ",
                " . dbQ($row['allegato_original_name'] ?? null) . ",
                " . dbQ(genitoriColloquiActor()) . ",
                NOW()
            )
    ");
}

function genitoriColloquiEventDuplicateKey(array $row): string
{
    $data = json_decode((string)($row['dati_json'] ?? '{}'), true);
    if (!is_array($data)) {
        $data = [];
    }
    ksort($data);
    $description = trim((string)($row['descrizione'] ?? ''));
    $type = trim((string)($row['tipo_evento'] ?? ''));
    if ($description === 'Colloquio salvato' || in_array($type, ['creazione', 'aggiornamento'], true)) {
        return implode('|', [
            intval($row['colloquio_id'] ?? 0),
            'salvataggio_colloquio',
            genitoriColloquiNormalizeDuplicateText((string)($data['note'] ?? '')),
            genitoriColloquiNormalizeDuplicateText((string)($data['libri_note'] ?? '')),
            genitoriColloquiNormalizeDuplicateText((string)($data['esami_integrativi'] ?? '')),
            genitoriColloquiNormalizeDuplicateText((string)($data['carenze_note'] ?? '')),
            trim((string)($row['allegato_path'] ?? '')),
            trim((string)($row['allegato_original_name'] ?? '')),
        ]);
    }
    return implode('|', [
        intval($row['colloquio_id'] ?? 0),
        $type,
        $description,
        trim((string)($row['stato'] ?? '')),
        trim((string)($row['esito'] ?? '')),
        trim((string)($row['ambito'] ?? '')),
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        trim((string)($row['allegato_path'] ?? '')),
        trim((string)($row['allegato_original_name'] ?? '')),
    ]);
}

function genitoriColloquiNormalizeDuplicateText(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/\s+/u', ' ', $text);
    return $text ?? '';
}

function genitoriColloquiCleanupDuplicateEvents(): array
{
    genitoriColloquiEnsureTables();
    $rows = dbGetAll("
        SELECT id, colloquio_id, tipo_evento, descrizione, stato, esito, ambito, dati_json, allegato_path, allegato_original_name
        FROM genitori_colloqui_eventi
        ORDER BY colloquio_id ASC, id DESC
    ") ?: [];
    $seen = [];
    $deleteIds = [];
    foreach ($rows as $row) {
        $key = genitoriColloquiEventDuplicateKey($row);
        if (isset($seen[$key])) {
            $deleteIds[] = intval($row['id'] ?? 0);
            continue;
        }
        $seen[$key] = true;
    }
    $deleteIds = array_values(array_filter($deleteIds, static fn($id) => $id > 0));
    if ($deleteIds) {
        dbExec("DELETE FROM genitori_colloqui_eventi WHERE id IN (" . implode(',', array_map('intval', $deleteIds)) . ")");
    }
    return ['read' => count($rows), 'deleted' => count($deleteIds)];
}

function genitoriColloquiEsitoLabel(string $esito): string
{
    $labels = [
        'ingresso_ok' => 'Esito positivo: ingresso approvato',
        'uscita_ok' => 'Esito positivo: uscita approvata',
        'integrazione' => 'Deve fare esami integrativi',
        'non_idoneo' => 'Esito negativo: non idoneo',
        'rinuncia' => 'Rinuncia',
    ];
    return $labels[$esito] ?? 'Esito non indicato';
}

function genitoriColloquiPropagateOutcome(int $id, bool $sendNotification = true): void
{
    $row = dbGetFirst("SELECT * FROM genitori_colloqui WHERE id = " . dbI($id) . " LIMIT 1");
    if (!$row) {
        return;
    }

    $student = trim((string)($row['cognome'] ?? '') . ' ' . (string)($row['nome'] ?? ''));
    $esito = (string)($row['esito'] ?? '');
    $esitoLabel = genitoriColloquiEsitoLabel($esito);
    $title = 'Colloquio genitori svolto - ' . $esitoLabel;
    if ((string)($row['stato'] ?? '') === 'approvato') {
        $title = 'Colloquio genitori svolto e approvato - ' . $esitoLabel;
    } elseif ((string)($row['stato'] ?? '') === 'non_approvato') {
        $title = 'Colloquio genitori svolto non approvato - ' . $esitoLabel;
    }

    $messageParts = array_filter([
        $student !== '' ? 'Studente: ' . $student : '',
        'Esito colloquio: ' . $esitoLabel,
        trim((string)($row['classe_iscrizione'] ?? '')) !== '' ? 'Classe iscrizione: ' . trim((string)$row['classe_iscrizione']) : '',
        trim((string)($row['indirizzo_iscrizione'] ?? '')) !== '' ? 'Indirizzo/gruppo: ' . trim((string)$row['indirizzo_iscrizione']) . ' ' . trim((string)($row['gruppo_iscrizione'] ?? '')) : '',
        trim((string)($row['scuola_destinazione'] ?? '')) !== '' ? 'Scuola destinazione: ' . trim((string)$row['scuola_destinazione']) : '',
        trim((string)($row['indirizzo_destinazione'] ?? '')) !== '' ? 'Indirizzo destinazione: ' . trim((string)$row['indirizzo_destinazione']) : '',
        !empty($row['libri_da_restituire']) ? 'Libri da restituire: si' : '',
        trim((string)($row['note'] ?? '')) !== '' ? 'Note: ' . trim((string)$row['note']) : '',
        trim((string)($row['esami_integrativi'] ?? '')) !== '' ? 'Esami integrativi: ' . trim((string)$row['esami_integrativi']) : '',
        trim((string)($row['carenze_note'] ?? '')) !== '' ? 'Carenze: ' . trim((string)$row['carenze_note']) : '',
        trim((string)($row['libri_note'] ?? '')) !== '' ? 'Libri: ' . trim((string)$row['libri_note']) : '',
    ]);
    $message = implode("\n", $messageParts);

    $practiceId = intval($row['id_pratica_iscrizione'] ?? 0);
    if ($practiceId > 0) {
        iscrizioniPrimeRecordEvent($practiceId, 'colloquio_genitori', $title, [
            'messaggio' => $message,
            'created_by' => genitoriColloquiActor(),
            'dettagli' => [
                'ambito' => $row['ambito'] ?? '',
                'stato_colloquio' => $row['stato'] ?? '',
                'esito' => $row['esito'] ?? '',
                'referente' => $row['referente'] ?? '',
                'appuntamento' => $row['appuntamento_at'] ?? '',
                'classe_iscrizione' => $row['classe_iscrizione'] ?? '',
                'indirizzo_iscrizione' => $row['indirizzo_iscrizione'] ?? '',
                'scuola_destinazione' => $row['scuola_destinazione'] ?? '',
                'indirizzo_destinazione' => $row['indirizzo_destinazione'] ?? '',
            ],
        ]);
    }

    $movementId = intval($row['id_movimento'] ?? 0);
    if ($movementId > 0) {
        $ambito = (string)($row['ambito'] ?? '');
        $movementState = $ambito === 'entrata' ? 'colloquio_entrata' : 'colloquio_uscita';
        if ($ambito === 'entrata' && $esito === 'non_idoneo') {
            $movementState = 'non_idoneo';
        } elseif ($ambito === 'entrata' && ($esito === 'integrazione' || trim((string)($row['esami_integrativi'] ?? '')) !== '')) {
            $movementState = 'esami_integrativi';
        } elseif ($ambito === 'entrata' && $esito === 'ingresso_ok') {
            $movementState = 'idoneo_iscrizione';
        }
        $movementType = $ambito === 'entrata' ? 'entrata' : 'uscita';
        $allowedStates = studentiMovimentiStatiPerTipo();
        if (!in_array($movementState, $allowedStates[$movementType] ?? [], true)) {
            $movementState = studentiMovimentiDefaultStato($movementType);
        }
        $extraUpdates = [];
        if ($movementType === 'entrata' && trim((string)($row['esami_integrativi'] ?? '')) !== '') {
            $extraUpdates[] = "esami_integrativi = 1";
            $extraUpdates[] = "esami_integrativi_note = " . dbQ(trim((string)$row['esami_integrativi']));
            $extraUpdates[] = "doc_esami_integrativi = IF(doc_esami_integrativi = 'non_necessario', 'mancante', doc_esami_integrativi)";
        }
        if ($movementType === 'entrata' && trim((string)($row['carenze_note'] ?? '')) !== '') {
            $extraUpdates[] = "carenze_presenti = 1";
            $extraUpdates[] = "carenze_note = " . dbQ(trim((string)$row['carenze_note']));
            $extraUpdates[] = "doc_carenze = IF(doc_carenze = 'non_necessario', 'mancante', doc_carenze)";
        }
        dbExec("
            UPDATE studenti_movimenti_pratiche
            SET stato_pratica = " . dbQ($movementState) . ",
                " . ($extraUpdates ? implode(",\n                ", $extraUpdates) . "," : "") . "
                updated_at = NOW()
            WHERE id = " . dbI($movementId) . "
              AND tipo_pratica = " . dbQ($movementType) . "
            LIMIT 1
        ");
        $alreadyLogged = intval(dbGetValue("
            SELECT COUNT(*)
            FROM studenti_movimenti_eventi
            WHERE id_pratica = " . dbI($movementId) . "
              AND tipo_evento = 'colloquio_genitori'
              AND descrizione = " . dbQ($title) . "
              AND stato_pratica = " . dbQ($movementState) . "
              AND COALESCE(note, '') = " . dbQ($message) . "
        ") ?? 0);
        if ($alreadyLogged === 0) {
            studentiMovimentiAddEvent($movementId, 'colloquio_genitori', $title, [
                'id_colloquio_genitori' => $id,
                'tipo_pratica' => $movementType,
                'stato_pratica' => $movementState,
                'note' => $message,
                'id_istituto_provenienza' => intval($row['id_istituto_provenienza'] ?? 0) ?: null,
                'scuola_provenienza' => (string)($row['scuola_provenienza'] ?? ''),
                'indirizzo_provenienza' => (string)($row['indirizzo_provenienza'] ?? ''),
                'scuola_destinazione' => (string)($row['scuola_destinazione'] ?? ''),
                'indirizzo_destinazione' => (string)($row['indirizzo_destinazione'] ?? ''),
            ], genitoriColloquiActor());
        }
    }

    if ($sendNotification && empty($row['notifica_inviata_at']) && in_array((string)($row['stato'] ?? ''), ['approvato','non_approvato'], true)) {
        if (genitoriColloquiNotifySecretary($row, $title, $message)) {
            dbExec("UPDATE genitori_colloqui SET notifica_inviata_at = NOW() WHERE id = " . dbI($id) . " LIMIT 1");
        }
    }
}

function genitoriColloquiRepropagateLinkedOutcomes(): array
{
    genitoriColloquiEnsureTables();
    $rows = dbGetAll("
        SELECT id
        FROM genitori_colloqui
        WHERE id_movimento IS NOT NULL
          AND id_movimento > 0
          AND (
              stato IN ('svolto','approvato','non_approvato')
              OR esito <> ''
              OR TRIM(COALESCE(esami_integrativi, '')) <> ''
              OR TRIM(COALESCE(carenze_note, '')) <> ''
          )
        ORDER BY updated_at DESC, id DESC
    ") ?: [];

    $updated = 0;
    $errors = [];
    foreach ($rows as $row) {
        $id = intval($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        try {
            genitoriColloquiPropagateOutcome($id, false);
            $updated++;
        } catch (Throwable $e) {
            if (count($errors) < 10) {
                $errors[] = 'Colloquio #' . $id . ': ' . $e->getMessage();
            }
        }
    }

    return ['read' => count($rows), 'updated' => $updated, 'errors' => $errors];
}

function genitoriColloquiHistoryForIds(array $ids): array
{
    $ids = array_values(array_filter(array_map('intval', $ids), static fn($id) => $id > 0));
    if (!$ids) {
        return [];
    }
    genitoriColloquiEnsureTables();
    $rows = dbGetAll("
        SELECT *
        FROM genitori_colloqui_eventi
        WHERE colloquio_id IN (" . implode(',', $ids) . ")
        ORDER BY created_at DESC, id DESC
    ") ?: [];
    $history = [];
    foreach ($rows as $row) {
        $history[intval($row['colloquio_id'] ?? 0)][] = $row;
    }
    return $history;
}

function genitoriColloquiDeleteEvent(int $eventId): bool
{
    if ($eventId <= 0) {
        return false;
    }
    genitoriColloquiEnsureTables();
    dbExec("DELETE FROM genitori_colloqui_eventi WHERE id = " . dbI($eventId) . " LIMIT 1");
    return true;
}

function genitoriColloquiUpdateEvent(int $eventId, string $description, string $note, string $libriNote): bool
{
    if ($eventId <= 0) {
        return false;
    }
    genitoriColloquiEnsureTables();
    $event = dbGetFirst("
        SELECT dati_json
        FROM genitori_colloqui_eventi
        WHERE id = " . dbI($eventId) . "
        LIMIT 1
    ");
    if (!$event) {
        return false;
    }
    $data = json_decode((string)($event['dati_json'] ?? '{}'), true);
    if (!is_array($data)) {
        $data = [];
    }
    $data['note'] = trim($note);
    $data['libri_note'] = trim($libriNote);
    dbExec("
        UPDATE genitori_colloqui_eventi
        SET descrizione = " . dbQ(trim($description) !== '' ? trim($description) : 'Aggiornamento storico') . ",
            dati_json = " . dbQ(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "
        WHERE id = " . dbI($eventId) . "
        LIMIT 1
    ");
    return true;
}

function genitoriColloquiDelete(int $id): bool
{
    if ($id <= 0) {
        return false;
    }

    genitoriColloquiEnsureTables();
    $row = dbGetFirst("SELECT id FROM genitori_colloqui WHERE id = " . dbI($id) . " LIMIT 1");
    if (!$row) {
        return false;
    }

    dbExec("DELETE FROM genitori_colloqui_eventi WHERE colloquio_id = " . dbI($id));
    dbExec("DELETE FROM genitori_colloqui WHERE id = " . dbI($id) . " LIMIT 1");

    $dir = realpath(genitoriColloquiUploadDir($id));
    $base = realpath(__DIR__ . '/../data/genitori_colloqui');
    if ($dir && $base && strpos(str_replace('\\', '/', $dir), str_replace('\\', '/', $base) . '/') === 0 && is_dir($dir)) {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($dir);
    }

    return true;
}

function genitoriColloquiNotifySecretary(array $row, string $title, string $message): bool
{
    require_once __DIR__ . '/send-mail.php';

    $cfg = iscrizioniPrimeMailConfig();
    $to = trim((string)($cfg['replyToEmail'] ?? ''));
    if ($to === '') {
        return false;
    }
    $student = trim((string)($row['cognome'] ?? '') . ' ' . (string)($row['nome'] ?? ''));
    $subject = '[GestOre] ' . $title . ($student !== '' ? ' - ' . $student : '');
    $body = '<p><strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong></p>'
        . '<p>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>'
        . '<p>Referente: ' . htmlspecialchars((string)($row['referente'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>';
    return sendMail($to, 'Segreteria didattica', $subject, $body);
}

function genitoriColloquiSyncRequestedFromMovimenti(): array
{
    genitoriColloquiEnsureTables();
    studentiMovimentiEnsureTables();

    $rows = dbGetAll("
        SELECT *
        FROM studenti_movimenti_pratiche
        WHERE tipo_pratica IN ('uscita','ritiro','entrata')
          AND stato_pratica IN ('colloquio_richiesto','colloquio_da_programmare','colloquio_programmato')
          AND stato_pratica <> 'annullata'
        ORDER BY updated_at DESC, id DESC
    ") ?: [];

    $created = 0;
    $updated = 0;
    foreach ($rows as $movement) {
        $movementId = intval($movement['id'] ?? 0);
        if ($movementId <= 0) {
            continue;
        }

        $movementState = (string)($movement['stato_pratica'] ?? '');
        $colloquioState = $movementState === 'colloquio_programmato'
            ? 'fissato'
            : ($movementState === 'colloquio_da_programmare' ? 'da_fissare' : 'richiesto');
        $ambito = (string)($movement['tipo_pratica'] ?? '') === 'entrata' ? 'entrata' : 'uscita';
        $note = "COLLOQUIO RICHIESTO - da rispondere.\nCreato automaticamente da Entrate / uscite.";

        $existing = dbGetFirst("
            SELECT *
            FROM genitori_colloqui
            WHERE id_movimento = " . dbI($movementId) . "
            LIMIT 1
        ");

        if ($existing && in_array((string)($existing['stato'] ?? ''), ['svolto','approvato','non_approvato','annullato'], true)) {
            continue;
        }

        $classe = trim((string)($movement['classe_origine'] ?? ''));
        $classeIscrizione = trim((string)($movement['classe_richiesta'] ?? ''));
        if ($classe === '' && $classeIscrizione !== '') {
            $classe = $classeIscrizione;
        }
        $richiestaDataSql = "DATE(" . dbQ((string)($movement['updated_at'] ?? date('Y-m-d H:i:s'))) . ")";

        if ($existing) {
            $existingNote = trim((string)($existing['note'] ?? ''));
            $noteSql = ($existingNote === '' || strpos($existingNote, 'COLLOQUIO RICHIESTO') === 0) ? dbQ($note) : 'note';
            dbExec("
                UPDATE genitori_colloqui SET
                    ambito = " . dbQ($ambito) . ",
                    cognome = " . dbQ((string)($movement['cognome'] ?? '')) . ",
                    nome = " . dbQ((string)($movement['nome'] ?? '')) . ",
                    codice_fiscale = " . dbQ((string)($movement['codice_fiscale'] ?? '')) . ",
                    classe = " . dbQ($classe) . ",
                    anno_corso = " . dbI(intval($movement['anno_corso'] ?? 0) ?: null) . ",
                    classe_iscrizione = " . dbQ($classeIscrizione) . ",
                    id_istituto_provenienza = " . dbI(intval($movement['id_istituto_provenienza'] ?? 0) ?: null) . ",
                    scuola_provenienza = " . dbQ((string)($movement['scuola_provenienza'] ?? '')) . ",
                    indirizzo_provenienza = " . dbQ((string)($movement['indirizzo_provenienza'] ?? '')) . ",
                    id_istituto_destinazione = " . dbI(intval($movement['id_istituto_destinazione'] ?? 0) ?: null) . ",
                    scuola_destinazione = " . dbQ((string)($movement['scuola_destinazione'] ?? '')) . ",
                    indirizzo_destinazione = " . dbQ((string)($movement['indirizzo_destinazione'] ?? '')) . ",
                    responsabile_1_tipo = " . dbQ((string)($movement['responsabile_1_tipo'] ?? '')) . ",
                    responsabile_1_cognome = " . dbQ((string)($movement['responsabile_1_cognome'] ?? '')) . ",
                    responsabile_1_nome = " . dbQ((string)($movement['responsabile_1_nome'] ?? '')) . ",
                    responsabile_1_codice_fiscale = " . dbQ((string)($movement['responsabile_1_codice_fiscale'] ?? '')) . ",
                    email_genitore_1 = " . dbQ((string)($movement['email_genitore_1'] ?? '')) . ",
                    telefono_genitore_1 = " . dbQ((string)($movement['telefono_genitore_1'] ?? '')) . ",
                    responsabile_2_tipo = " . dbQ((string)($movement['responsabile_2_tipo'] ?? '')) . ",
                    responsabile_2_cognome = " . dbQ((string)($movement['responsabile_2_cognome'] ?? '')) . ",
                    responsabile_2_nome = " . dbQ((string)($movement['responsabile_2_nome'] ?? '')) . ",
                    responsabile_2_codice_fiscale = " . dbQ((string)($movement['responsabile_2_codice_fiscale'] ?? '')) . ",
                    email_genitore_2 = " . dbQ((string)($movement['email_genitore_2'] ?? '')) . ",
                    telefono_genitore_2 = " . dbQ((string)($movement['telefono_genitore_2'] ?? '')) . ",
                    richiesta_data = COALESCE(richiesta_data, $richiestaDataSql),
                    stato = " . dbQ($colloquioState) . ",
                    note = $noteSql,
                    updated_at = NOW()
                WHERE id = " . dbI(intval($existing['id'])) . "
                LIMIT 1
            ");
            genitoriColloquiAddEvent((int)$existing['id'], 'sync_movimento', 'Colloquio aggiornato da Entrate / uscite', [
                'stato' => $colloquioState,
                'ambito' => $ambito,
                'note' => $note,
            ]);
            $updated++;
            continue;
        }

        dbExec("
            INSERT INTO genitori_colloqui
                (ambito, id_movimento, cognome, nome, codice_fiscale, classe, anno_corso, classe_iscrizione, id_istituto_provenienza, scuola_provenienza, indirizzo_provenienza, id_istituto_destinazione, scuola_destinazione, indirizzo_destinazione, responsabile_1_tipo, responsabile_1_cognome, responsabile_1_nome, responsabile_1_codice_fiscale, email_genitore_1, telefono_genitore_1, responsabile_2_tipo, responsabile_2_cognome, responsabile_2_nome, responsabile_2_codice_fiscale, email_genitore_2, telefono_genitore_2, referente, richiesta_data, stato, esito, note, created_by, created_at, updated_at)
            VALUES
                (
                    " . dbQ($ambito) . ",
                    " . dbI($movementId) . ",
                    " . dbQ((string)($movement['cognome'] ?? '')) . ",
                    " . dbQ((string)($movement['nome'] ?? '')) . ",
                    " . dbQ((string)($movement['codice_fiscale'] ?? '')) . ",
                    " . dbQ($classe) . ",
                    " . dbI(intval($movement['anno_corso'] ?? 0) ?: null) . ",
                    " . dbQ($classeIscrizione) . ",
                    " . dbI(intval($movement['id_istituto_provenienza'] ?? 0) ?: null) . ",
                    " . dbQ((string)($movement['scuola_provenienza'] ?? '')) . ",
                    " . dbQ((string)($movement['indirizzo_provenienza'] ?? '')) . ",
                    " . dbI(intval($movement['id_istituto_destinazione'] ?? 0) ?: null) . ",
                    " . dbQ((string)($movement['scuola_destinazione'] ?? '')) . ",
                    " . dbQ((string)($movement['indirizzo_destinazione'] ?? '')) . ",
                    " . dbQ((string)($movement['responsabile_1_tipo'] ?? '')) . ",
                    " . dbQ((string)($movement['responsabile_1_cognome'] ?? '')) . ",
                    " . dbQ((string)($movement['responsabile_1_nome'] ?? '')) . ",
                    " . dbQ((string)($movement['responsabile_1_codice_fiscale'] ?? '')) . ",
                    " . dbQ((string)($movement['email_genitore_1'] ?? '')) . ",
                    " . dbQ((string)($movement['telefono_genitore_1'] ?? '')) . ",
                    " . dbQ((string)($movement['responsabile_2_tipo'] ?? '')) . ",
                    " . dbQ((string)($movement['responsabile_2_cognome'] ?? '')) . ",
                    " . dbQ((string)($movement['responsabile_2_nome'] ?? '')) . ",
                    " . dbQ((string)($movement['responsabile_2_codice_fiscale'] ?? '')) . ",
                    " . dbQ((string)($movement['email_genitore_2'] ?? '')) . ",
                    " . dbQ((string)($movement['telefono_genitore_2'] ?? '')) . ",
                    " . dbQ('prof.ssa Ceschini') . ",
                    $richiestaDataSql,
                    " . dbQ($colloquioState) . ",
                    '',
                    " . dbQ($note) . ",
                    " . dbQ(genitoriColloquiActor()) . ",
                    NOW(),
                    NOW()
                )
        ");
        $newId = intval(dblastId());
        genitoriColloquiAddEvent($newId, 'sync_movimento', 'Colloquio richiesto da Entrate / uscite', [
            'stato' => $colloquioState,
            'ambito' => $ambito,
            'note' => $note,
        ]);
        $created++;
    }

    return ['created' => $created, 'updated' => $updated];
}

function genitoriColloquiAll(): array
{
    genitoriColloquiEnsureTables();
    return dbGetAll("
        SELECT *
        FROM genitori_colloqui
        ORDER BY
            cognome ASC,
            nome ASC,
            COALESCE(appuntamento_at, updated_at) DESC,
            FIELD(stato, 'richiesto','da_fissare','fissato','svolto','approvato','non_approvato','annullato')
    ") ?: [];
}

function genitoriColloquiIscrizioniOptions(): array
{
    iscrizioniPrimeEnsureSchema();
    return dbGetAll("
        SELECT id, tipo_iscrizione, cognome, nome, codice_fiscale, corso_studi, stato,
               scuola_provenienza, bocciato_altra_scuola,
               responsabile_1_tipo, responsabile_1_cognome, responsabile_1_nome, responsabile_1_codice_fiscale,
               email_genitore_1, telefono_genitore_1,
               responsabile_2_tipo, responsabile_2_cognome, responsabile_2_nome, responsabile_2_codice_fiscale,
               email_genitore_2, telefono_genitore_2
        FROM iscrizioni_prime_pratiche
        WHERE stato IN ('importata','bozza','inviata','verificata','da_integrare','annullata')
        ORDER BY cognome ASC, nome ASC
        LIMIT 800
    ") ?: [];
}

function genitoriColloquiMovimentiOptions(): array
{
    studentiMovimentiEnsureTables();
    return dbGetAll("
        SELECT id, tipo_pratica, cognome, nome, codice_fiscale, classe_origine, classe_richiesta, stato_pratica,
               id_istituto_provenienza, scuola_provenienza, indirizzo_provenienza, bocciato_altra_scuola, scuola_destinazione, indirizzo_destinazione, anno_corso,
               responsabile_1_tipo, responsabile_1_cognome, responsabile_1_nome, responsabile_1_codice_fiscale,
               email_genitore_1, telefono_genitore_1,
               responsabile_2_tipo, responsabile_2_cognome, responsabile_2_nome, responsabile_2_codice_fiscale,
               email_genitore_2, telefono_genitore_2
        FROM studenti_movimenti_pratiche
        ORDER BY cognome ASC, nome ASC, tipo_pratica ASC, updated_at DESC
        LIMIT 800
    ") ?: [];
}
