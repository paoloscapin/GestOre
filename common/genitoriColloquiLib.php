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
            id_istituto_destinazione int DEFAULT NULL,
            scuola_destinazione varchar(255) DEFAULT NULL,
            indirizzo_destinazione varchar(255) DEFAULT NULL,
            libri_da_restituire tinyint NOT NULL DEFAULT 0,
            libri_restituiti_at date DEFAULT NULL,
            ricevuta_libri_path varchar(500) DEFAULT NULL,
            ricevuta_libri_original_name varchar(255) DEFAULT NULL,
            ricevuta_libri_size int DEFAULT NULL,
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
    genitoriColloquiEnsureColumn('genitori_colloqui', 'id_istituto_destinazione', "ALTER TABLE genitori_colloqui ADD COLUMN id_istituto_destinazione int DEFAULT NULL AFTER gruppo_iscrizione");
    genitoriColloquiEnsureColumn('genitori_colloqui', 'scuola_destinazione', "ALTER TABLE genitori_colloqui ADD COLUMN scuola_destinazione varchar(255) DEFAULT NULL AFTER id_istituto_destinazione");
    genitoriColloquiEnsureColumn('genitori_colloqui', 'indirizzo_destinazione', "ALTER TABLE genitori_colloqui ADD COLUMN indirizzo_destinazione varchar(255) DEFAULT NULL AFTER scuola_destinazione");
    genitoriColloquiEnsureColumn('genitori_colloqui', 'libri_da_restituire', "ALTER TABLE genitori_colloqui ADD COLUMN libri_da_restituire tinyint NOT NULL DEFAULT 0 AFTER indirizzo_destinazione");
    genitoriColloquiEnsureColumn('genitori_colloqui', 'libri_restituiti_at', "ALTER TABLE genitori_colloqui ADD COLUMN libri_restituiti_at date DEFAULT NULL AFTER libri_da_restituire");
    genitoriColloquiEnsureColumn('genitori_colloqui', 'ricevuta_libri_path', "ALTER TABLE genitori_colloqui ADD COLUMN ricevuta_libri_path varchar(500) DEFAULT NULL AFTER libri_restituiti_at");
    genitoriColloquiEnsureColumn('genitori_colloqui', 'ricevuta_libri_original_name', "ALTER TABLE genitori_colloqui ADD COLUMN ricevuta_libri_original_name varchar(255) DEFAULT NULL AFTER ricevuta_libri_path");
    genitoriColloquiEnsureColumn('genitori_colloqui', 'ricevuta_libri_size', "ALTER TABLE genitori_colloqui ADD COLUMN ricevuta_libri_size int DEFAULT NULL AFTER ricevuta_libri_original_name");
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

function genitoriColloquiActor(): string
{
    global $__useremail, $__utente_nome, $__utente_cognome;
    $name = trim((string)($__utente_nome ?? '') . ' ' . (string)($__utente_cognome ?? ''));
    return $name !== '' ? $name : trim((string)($__useremail ?? ''));
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

function genitoriColloquiSave(array $data, ?array $file = null, ?array $receiptFile = null): int
{
    genitoriColloquiEnsureTables();

    $id = intval($data['id'] ?? 0);
    $ambito = genitoriColloquiAllowed((string)($data['ambito'] ?? 'altro'), ['entrata','uscita','iscrizione_prime','iscrizione_terze','altro'], 'altro');
    $stato = genitoriColloquiAllowed((string)($data['stato'] ?? 'richiesto'), ['richiesto','da_fissare','fissato','svolto','approvato','non_approvato','annullato'], 'richiesto');
    $esito = genitoriColloquiAllowed((string)($data['esito'] ?? ''), ['','ingresso_ok','uscita_ok','integrazione','non_idoneo','rinuncia'], '');
    $appuntamentoAt = genitoriColloquiNormalizeDateTime($data['appuntamento_data'] ?? null, $data['appuntamento_ora'] ?? null);
    $richiestaData = genitoriColloquiNormalizeDate($data['richiesta_data'] ?? null);
    $libriRestituitiAt = genitoriColloquiNormalizeDate($data['libri_restituiti_at'] ?? null);
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
        'cognome' => trim((string)($data['cognome'] ?? '')),
        'nome' => trim((string)($data['nome'] ?? '')),
        'codice_fiscale' => strtoupper(trim((string)($data['codice_fiscale'] ?? ''))),
        'classe' => trim((string)($data['classe'] ?? '')),
        'anno_corso' => intval($data['anno_corso'] ?? 0) ?: null,
        'classe_iscrizione' => trim((string)($data['classe_iscrizione'] ?? '')),
        'indirizzo_iscrizione' => trim((string)($data['indirizzo_iscrizione'] ?? '')),
        'gruppo_iscrizione' => trim((string)($data['gruppo_iscrizione'] ?? '')),
        'id_istituto_destinazione' => $idIstitutoDestinazione,
        'scuola_destinazione' => $scuolaDestinazione,
        'indirizzo_destinazione' => trim((string)($data['indirizzo_destinazione'] ?? '')),
        'libri_da_restituire' => !empty($data['libri_da_restituire']) ? 1 : 0,
        'libri_restituiti_at' => $libriRestituitiAt,
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
                id_istituto_destinazione = " . dbI($fields['id_istituto_destinazione']) . ",
                scuola_destinazione = " . dbQ($fields['scuola_destinazione']) . ",
                indirizzo_destinazione = " . dbQ($fields['indirizzo_destinazione']) . ",
                libri_da_restituire = " . dbI($fields['libri_da_restituire']) . ",
                libri_restituiti_at = " . dbQ($fields['libri_restituiti_at']) . ",
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
                (ambito, id_pratica_iscrizione, id_movimento, cognome, nome, codice_fiscale, classe, anno_corso, classe_iscrizione, indirizzo_iscrizione, gruppo_iscrizione, id_istituto_destinazione, scuola_destinazione, indirizzo_destinazione, libri_da_restituire, libri_restituiti_at, referente, richiesta_data, appuntamento_at, stato, esito, esami_integrativi, carenze_note, libri_note, note, created_by, created_at, updated_at)
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
                    " . dbI($fields['id_istituto_destinazione']) . ",
                    " . dbQ($fields['scuola_destinazione']) . ",
                    " . dbQ($fields['indirizzo_destinazione']) . ",
                    " . dbI($fields['libri_da_restituire']) . ",
                    " . dbQ($fields['libri_restituiti_at']) . ",
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

    if (in_array($stato, ['svolto','approvato','non_approvato'], true)) {
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

function genitoriColloquiAddEvent(int $id, string $type, string $description, array $fields = []): void
{
    if ($id <= 0) {
        return;
    }
    $row = dbGetFirst("SELECT allegato_path, allegato_original_name FROM genitori_colloqui WHERE id = " . dbI($id) . " LIMIT 1") ?: [];
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
                " . dbQ(json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . ",
                " . dbQ($row['allegato_path'] ?? null) . ",
                " . dbQ($row['allegato_original_name'] ?? null) . ",
                " . dbQ(genitoriColloquiActor()) . ",
                NOW()
            )
    ");
}

function genitoriColloquiPropagateOutcome(int $id): void
{
    $row = dbGetFirst("SELECT * FROM genitori_colloqui WHERE id = " . dbI($id) . " LIMIT 1");
    if (!$row) {
        return;
    }

    $student = trim((string)($row['cognome'] ?? '') . ' ' . (string)($row['nome'] ?? ''));
    $title = 'Colloquio genitori svolto';
    if ((string)($row['stato'] ?? '') === 'approvato') {
        $title = 'Colloquio genitori svolto e approvato';
    } elseif ((string)($row['stato'] ?? '') === 'non_approvato') {
        $title = 'Colloquio genitori svolto non approvato';
    }

    $messageParts = array_filter([
        $student !== '' ? 'Studente: ' . $student : '',
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
        studentiMovimentiAddEvent($movementId, 'colloquio_genitori', $title, [
            'tipo_pratica' => (string)($row['ambito'] ?? ''),
            'stato_pratica' => (string)($row['esito'] ?? ''),
            'note' => $message,
            'scuola_destinazione' => (string)($row['scuola_destinazione'] ?? ''),
            'indirizzo_destinazione' => (string)($row['indirizzo_destinazione'] ?? ''),
        ], genitoriColloquiActor());
    }

    if (empty($row['notifica_inviata_at']) && in_array((string)($row['stato'] ?? ''), ['approvato','non_approvato'], true)) {
        if (genitoriColloquiNotifySecretary($row, $title, $message)) {
            dbExec("UPDATE genitori_colloqui SET notifica_inviata_at = NOW() WHERE id = " . dbI($id) . " LIMIT 1");
        }
    }
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

function genitoriColloquiAll(): array
{
    genitoriColloquiEnsureTables();
    return dbGetAll("
        SELECT *
        FROM genitori_colloqui
        ORDER BY
            FIELD(stato, 'richiesto','da_fissare','fissato','svolto','approvato','non_approvato','annullato'),
            COALESCE(appuntamento_at, updated_at) DESC,
            cognome ASC,
            nome ASC
    ") ?: [];
}

function genitoriColloquiIscrizioniOptions(): array
{
    iscrizioniPrimeEnsureSchema();
    return dbGetAll("
        SELECT id, tipo_iscrizione, cognome, nome, codice_fiscale, corso_studi, stato
        FROM iscrizioni_prime_pratiche
        WHERE stato IN ('inviata','verificata','da_integrare','annullata')
        ORDER BY cognome ASC, nome ASC
        LIMIT 800
    ") ?: [];
}

function genitoriColloquiMovimentiOptions(): array
{
    studentiMovimentiEnsureTables();
    return dbGetAll("
        SELECT id, tipo_pratica, cognome, nome, codice_fiscale, classe_origine, classe_richiesta, stato_pratica
        FROM studenti_movimenti_pratiche
        ORDER BY updated_at DESC, cognome ASC, nome ASC
        LIMIT 800
    ") ?: [];
}
