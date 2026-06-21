<?php

defined('GESTORE_BOOTSTRAP') || define('GESTORE_BOOTSTRAP', true);

require_once __DIR__ . '/connect.php';

function iscrizioniPrimeEnsureSchema(): void
{
    dbExec("
        CREATE TABLE IF NOT EXISTS iscrizioni_prime_pratiche (
          id int NOT NULL AUTO_INCREMENT,
          anno_scolastico varchar(9) NOT NULL,
          codice_domanda varchar(30) DEFAULT NULL,
          codice_sidi varchar(30) DEFAULT NULL,
          codice_giada varchar(30) DEFAULT NULL,
          codice_fiscale varchar(16) NOT NULL,
          cognome varchar(100) NOT NULL,
          nome varchar(100) NOT NULL,
          sesso char(1) DEFAULT NULL,
          data_nascita date DEFAULT NULL,
          unita_scolastica varchar(255) DEFAULT NULL,
          corso_studi varchar(255) DEFAULT NULL,
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
          stato enum('importata','bozza','inviata','verificata','da_integrare','annullata') NOT NULL DEFAULT 'importata',
          dati_confermati_json mediumtext DEFAULT NULL,
          raw_prime_json mediumtext DEFAULT NULL,
          raw_dsa_json mediumtext DEFAULT NULL,
          raw_anagrafica_json mediumtext DEFAULT NULL,
          note_interne text DEFAULT NULL,
          imported_at datetime NOT NULL,
          updated_at datetime NOT NULL,
          PRIMARY KEY (id),
          UNIQUE KEY idx_iscrizioni_prime_anno_cf (anno_scolastico, codice_fiscale),
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
        CREATE TABLE IF NOT EXISTS iscrizioni_prime_import_log (
          id int NOT NULL AUTO_INCREMENT,
          created_at datetime NOT NULL,
          created_by varchar(255) DEFAULT NULL,
          prime_filename varchar(255) DEFAULT NULL,
          dsa_filename varchar(255) DEFAULT NULL,
          anagrafica_filename varchar(255) DEFAULT NULL,
          righe_prime int NOT NULL DEFAULT 0,
          righe_dsa int NOT NULL DEFAULT 0,
          righe_anagrafica int NOT NULL DEFAULT 0,
          inserite int NOT NULL DEFAULT 0,
          aggiornate int NOT NULL DEFAULT 0,
          contatti_aggiornati int NOT NULL DEFAULT 0,
          contatti_ignorati int NOT NULL DEFAULT 0,
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
          stato enum('inviata','errore') NOT NULL,
          test_mode tinyint NOT NULL DEFAULT 0,
          errore text DEFAULT NULL,
          sent_at datetime DEFAULT NULL,
          created_at datetime NOT NULL,
          PRIMARY KEY (id),
          KEY idx_iscrizioni_mail_pratica (pratica_id),
          KEY idx_iscrizioni_mail_recipient (recipient_email),
          KEY idx_iscrizioni_mail_account_day (account_email, sent_at),
          KEY idx_iscrizioni_mail_stato (stato)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'responsabile_1_tipo', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN responsabile_1_tipo varchar(50) DEFAULT NULL AFTER telefono_genitore_2");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'email_studente', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN email_studente varchar(255) DEFAULT NULL AFTER certificazione_online");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'telefono_studente', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN telefono_studente varchar(50) DEFAULT NULL AFTER email_studente");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'responsabile_1_cognome', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN responsabile_1_cognome varchar(100) DEFAULT NULL AFTER responsabile_1_tipo");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'responsabile_1_nome', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN responsabile_1_nome varchar(100) DEFAULT NULL AFTER responsabile_1_cognome");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'responsabile_1_codice_fiscale', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN responsabile_1_codice_fiscale varchar(16) DEFAULT NULL AFTER responsabile_1_nome");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'responsabile_2_tipo', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN responsabile_2_tipo varchar(50) DEFAULT NULL AFTER responsabile_1_codice_fiscale");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'responsabile_2_cognome', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN responsabile_2_cognome varchar(100) DEFAULT NULL AFTER responsabile_2_tipo");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'responsabile_2_nome', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN responsabile_2_nome varchar(100) DEFAULT NULL AFTER responsabile_2_cognome");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'responsabile_2_codice_fiscale', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN responsabile_2_codice_fiscale varchar(16) DEFAULT NULL AFTER responsabile_2_nome");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_pratiche', 'raw_anagrafica_json', "ALTER TABLE iscrizioni_prime_pratiche ADD COLUMN raw_anagrafica_json mediumtext DEFAULT NULL AFTER raw_dsa_json");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_import_log', 'anagrafica_filename', "ALTER TABLE iscrizioni_prime_import_log ADD COLUMN anagrafica_filename varchar(255) DEFAULT NULL AFTER dsa_filename");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_import_log', 'righe_anagrafica', "ALTER TABLE iscrizioni_prime_import_log ADD COLUMN righe_anagrafica int NOT NULL DEFAULT 0 AFTER righe_dsa");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_import_log', 'contatti_aggiornati', "ALTER TABLE iscrizioni_prime_import_log ADD COLUMN contatti_aggiornati int NOT NULL DEFAULT 0 AFTER aggiornate");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_import_log', 'contatti_ignorati', "ALTER TABLE iscrizioni_prime_import_log ADD COLUMN contatti_ignorati int NOT NULL DEFAULT 0 AFTER contatti_aggiornati");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_mail_log', 'test_mode', "ALTER TABLE iscrizioni_prime_mail_log ADD COLUMN test_mode tinyint NOT NULL DEFAULT 0 AFTER stato");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_documenti', 'storage_type', "ALTER TABLE iscrizioni_prime_documenti ADD COLUMN storage_type enum('LOCAL','DRIVE') NOT NULL DEFAULT 'LOCAL' AFTER file_size");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_documenti', 'drive_file_id', "ALTER TABLE iscrizioni_prime_documenti ADD COLUMN drive_file_id varchar(255) DEFAULT NULL AFTER storage_type");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_documenti', 'drive_web_view_link', "ALTER TABLE iscrizioni_prime_documenti ADD COLUMN drive_web_view_link varchar(500) DEFAULT NULL AFTER drive_file_id");
    iscrizioniPrimeEnsureColumn('iscrizioni_prime_documenti', 'drive_folder_id', "ALTER TABLE iscrizioni_prime_documenti ADD COLUMN drive_folder_id varchar(255) DEFAULT NULL AFTER drive_web_view_link");
    iscrizioniPrimeEnsureDocumentStatusEnum();
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

function iscrizioniPrimeDocumentTypes(): array
{
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

function iscrizioniPrimeUploadBaseDir(): string
{
    return realpath(__DIR__ . '/../data') ?: (__DIR__ . '/../data');
}

function iscrizioniPrimeUploadDir(int $praticaId): string
{
    return iscrizioniPrimeUploadBaseDir() . '/iscrizioni_prime_uploads/' . intval($praticaId);
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
    $rootFolderId = trim((string)($cfg->iscrizioniPrimeFolderId ?? ''));
    $rootFolderName = trim((string)($cfg->iscrizioniPrimeFolderName ?? 'Iscrizioni prime'));
    if ($rootFolderId === '') {
        $rootFolderId = googleDriveFindFolderByName($rootFolderName);
        if ($rootFolderId === '') {
            $rootFolderId = googleDriveCreateFolder($rootFolderName);
        }
    }
    if ($rootFolderId === '') {
        throw new RuntimeException('Impossibile trovare o creare la cartella Drive delle iscrizioni prime.');
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
        $name = trim((string)$name);
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

function iscrizioniPrimeReadCsv(string $path): array
{
    $fp = fopen($path, 'r');

    if (!$fp) {
        throw new RuntimeException('Impossibile leggere il file CSV.');
    }

    $header = fgetcsv($fp, 0, ',');
    if (!$header) {
        fclose($fp);
        throw new RuntimeException('Intestazione CSV non valida.');
    }

    $header = iscrizioniPrimeNormalizeHeader($header);
    $rows = [];

    while (($row = fgetcsv($fp, 0, ',')) !== false) {
        if (!count(array_filter($row, fn($value) => trim((string)$value) !== ''))) {
            continue;
        }

        $assoc = [];
        foreach ($header as $index => $name) {
            $assoc[$name] = trim((string)($row[$index] ?? ''));
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

function iscrizioniPrimeJson(array $row): string
{
    return json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
        if ($email !== '' && $password !== '') {
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

function iscrizioniPrimeMailSubject(): string
{
    return 'Conferma dati iscrizione classi prime';
}

function iscrizioniPrimeMailAccountCounts(): array
{
    $rows = dbGetAll("
        SELECT account_email, COUNT(*) AS totale
        FROM iscrizioni_prime_mail_log
        WHERE stato = 'inviata'
          AND test_mode = 0
          AND sent_at >= CURDATE()
          AND account_email IS NOT NULL
        GROUP BY account_email
    ");

    $counts = [];
    foreach ($rows as $row) {
        $counts[(string)$row['account_email']] = intval($row['totale']);
    }

    return $counts;
}

function iscrizioniPrimePickMailAccount(array $cfg, array $counts): ?array
{
    $best = null;
    $bestCount = PHP_INT_MAX;
    $limit = intval($cfg['maxPerAccountPerDay'] ?? 450);

    foreach ($cfg['accounts'] as $account) {
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

    $nome = trim((string)(($pratica['nome'] ?? '') . ' ' . ($pratica['cognome'] ?? '')));
    $istituto = trim((string)($__settings->local->nomeIstituto ?? 'ITT Buonarroti - Trento'));
    $anno = trim((string)($pratica['anno_scolastico'] ?? '2026-27'));
    $corso = trim((string)($pratica['corso_studi'] ?? ''));
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
                        <div style="font-size:15px;margin-top:4px;">Future classi prime - anno scolastico ' . iscrizioniPrimeMailEscape($anno) . '</div>
                    </div>
                    <div style="padding:22px;">
                        <p style="margin:0 0 12px;font-size:16px;">Gentile famiglia,</p>
                        <p style="margin:0 0 16px;font-size:16px;line-height:1.5;">
                            per completare la procedura di iscrizione alle future classi prime, chiediamo di confermare i dati anagrafici e caricare i documenti richiesti per
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
    $labels = iscrizioniPrimeDocumentTypes();
    $confirmed = iscrizioniPrimeMailConfirmedData($pratica);
    $required = array_flip(iscrizioniPrimeRequiredDocumentTypes($pratica, $confirmed));
    $documents = iscrizioniPrimeDocumentsForPratica((int)$pratica['id']);
    $rows = '';

    foreach ($documents as $document) {
        $tipo = (string)($document['tipo_documento'] ?? '');
        if ($tipo === 'altro' && (string)($document['stato'] ?? 'mancante') === 'mancante') {
            continue;
        }
        if (in_array($tipo, ['documento_identita_genitore_2', 'codice_fiscale_genitore_2'], true) && !hasSecondResponsibleForIscrizioniPrime($pratica, $confirmed)) {
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

function iscrizioniPrimeSubmissionConfirmationBody(array $pratica): string
{
    global $__settings;

    $nomeStudente = trim((string)(($pratica['nome'] ?? '') . ' ' . ($pratica['cognome'] ?? '')));
    $istituto = trim((string)($__settings->local->nomeIstituto ?? 'Istituto'));
    $anno = trim((string)($pratica['anno_scolastico'] ?? ''));
    $confirmed = iscrizioniPrimeMailConfirmedData($pratica);
    $responsabile1 = trim((string)(($pratica['responsabile_1_cognome'] ?? '') . ' ' . ($pratica['responsabile_1_nome'] ?? '')));
    $responsabile2 = trim((string)(($pratica['responsabile_2_cognome'] ?? '') . ' ' . ($pratica['responsabile_2_nome'] ?? '')));

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

    return '
        <div style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#172033;">
            <div style="max-width:760px;margin:0 auto;padding:22px 12px;">
                <div style="background:#ffffff;border:1px solid #dbe3ef;border-radius:8px;overflow:hidden;">
                    <div style="background:#0f766e;color:#ffffff;padding:20px 22px;">
                        <div style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;opacity:.9;">' . iscrizioniPrimeMailEscape($istituto) . '</div>
                        <div style="font-size:24px;font-weight:800;margin-top:4px;">Conferma dati iscrizione ricevuta</div>
                        <div style="font-size:15px;margin-top:4px;">Future classi prime - anno scolastico ' . iscrizioniPrimeMailEscape($anno) . '</div>
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

                        <h3 style="font-size:18px;margin:22px 0 8px;color:#172033;">Documenti</h3>
                        ' . iscrizioniPrimeMailDocumentsTable($pratica) . '

                        <p style="margin:18px 0 0;color:#475569;line-height:1.5;">
                            Se qualche documento e\' stato indicato come consegna cartacea, dovra\' essere portato in segreteria didattica.
                        </p>
                        <p style="margin:18px 0 0;color:#172033;line-height:1.5;">Cordiali saluti<br><strong>Segreteria didattica</strong></p>
                    </div>
                </div>
            </div>
        </div>
    ';
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

        $ok = sendMailCustom($actualRecipient, $recipientName, $cfg['confirmationSubject'], $actualBody, [
            'from_email' => $account['email'],
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

function iscrizioniPrimeSendMailBatch(bool $dryRun = false): array
{
    require_once __DIR__ . '/send-mail.php';

    $cfg = iscrizioniPrimeMailConfig();
    if (empty($cfg['enabled'])) {
        return ['ok' => false, 'message' => 'Invio mail iscrizioni non abilitato in GestOre.json.'];
    }
    if (empty($cfg['accounts'])) {
        return ['ok' => false, 'message' => 'Nessun account SMTP iscrizioni configurato.'];
    }

    $batchSize = intval($cfg['batchSize']);
    $pratiche = dbGetAll("
        SELECT *
        FROM iscrizioni_prime_pratiche p
        WHERE p.stato IN ('importata', 'bozza', 'da_integrare')
          AND (p.email_genitore_1 IS NOT NULL OR p.email_genitore_2 IS NOT NULL)
        ORDER BY p.cognome ASC, p.nome ASC
        LIMIT " . intval($batchSize * 2) . "
    ");

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
            continue;
        }

        $alreadySentAll = true;
        foreach ($recipients as $recipient) {
            $already = dbGetValue("
                SELECT COUNT(*)
                FROM iscrizioni_prime_mail_log
                WHERE pratica_id = " . intval($pratica['id']) . "
                  AND recipient_email = " . dbQ($recipient) . "
                  AND stato = 'inviata'
                  AND test_mode = 0
            ");
            if (intval($already) === 0) {
                $alreadySentAll = false;
                break;
            }
        }
        if ($alreadySentAll) {
            $skipped++;
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
                  AND recipient_email = " . dbQ($recipient) . "
                  AND stato = 'inviata'
                  AND test_mode = 0
            ");
            if (intval($already) > 0) {
                continue;
            }

            $account = iscrizioniPrimePickMailAccount($cfg, $counts);
            if ($account === null) {
                return [
                    'ok' => empty($errors),
                    'message' => 'Limite giornaliero account raggiunto.',
                    'sent' => $sent,
                    'skipped' => $skipped,
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

              $body = iscrizioniPrimeMailBody($pratica, $link, $originalRecipientForBody);

            $ok = sendMailCustom($actualRecipient, $recipientName, $cfg['subject'], $body, [
                'from_email' => $account['email'],
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

            dbExec("
                INSERT INTO iscrizioni_prime_mail_log
                (pratica_id, recipient_email, account_email, token_last4, stato, test_mode, errore, sent_at, created_at)
                VALUES (
                    " . intval($pratica['id']) . ",
                    " . dbQ($recipient) . ",
                    " . dbQ($account['email']) . ",
                    " . dbQ(substr($token, -4)) . ",
                    " . dbQ($ok ? 'inviata' : 'errore') . ",
                    " . (!empty($cfg['testMode']) ? '1' : '0') . ",
                    " . dbQ($ok ? null : 'sendMailCustom ha restituito false') . ",
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

    return [
        'ok' => empty($errors),
          'message' => (empty($cfg['testMode']) ? '' : 'Modalita test attiva: mail inviate agli account mittenti. ') . (empty($errors) ? 'Invio completato' : 'Invio completato con errori'),
        'sent' => $sent,
        'skipped' => $skipped,
        'errors' => $errors,
    ];
}

function iscrizioniPrimeGetByToken(string $token): ?array
{
    $token = trim($token);
    if ($token === '') {
        return null;
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

function iscrizioniPrimeSaveDraftByToken(string $token, array $data): array
{
    $pratica = iscrizioniPrimeGetByToken($token);
    if (!$pratica) {
        return ['ok' => false, 'message' => 'Link non valido, scaduto o pratica non disponibile.'];
    }

    if (in_array((string)$pratica['stato'], ['inviata', 'verificata', 'annullata'], true)) {
        return ['ok' => false, 'message' => 'La pratica non puo essere modificata in questo stato.'];
    }

    $confirmed = [
        'email_studente' => iscrizioniPrimeTrimValue($data['email_studente'] ?? null),
        'telefono_studente' => iscrizioniPrimeTrimValue($data['telefono_studente'] ?? null),
        'email_genitore_1' => iscrizioniPrimeTrimValue($data['email_genitore_1'] ?? null),
        'telefono_genitore_1' => iscrizioniPrimeTrimValue($data['telefono_genitore_1'] ?? null),
        'email_genitore_2' => iscrizioniPrimeTrimValue($data['email_genitore_2'] ?? null),
        'telefono_genitore_2' => iscrizioniPrimeTrimValue($data['telefono_genitore_2'] ?? null),
        'privacy_confermata' => !empty($data['privacy_confermata']) ? 1 : 0,
        'saved_at' => date('c'),
    ];

    if ($confirmed['email_genitore_1'] === null && $confirmed['email_genitore_2'] === null) {
        return ['ok' => false, 'message' => 'Indicare almeno una email di un responsabile.'];
    }

    dbExec("
        UPDATE iscrizioni_prime_pratiche SET
            email_studente = " . dbQ($confirmed['email_studente']) . ",
            telefono_studente = " . dbQ($confirmed['telefono_studente']) . ",
            email_genitore_1 = " . dbQ($confirmed['email_genitore_1']) . ",
            telefono_genitore_1 = " . dbQ($confirmed['telefono_genitore_1']) . ",
            email_genitore_2 = " . dbQ($confirmed['email_genitore_2']) . ",
            telefono_genitore_2 = " . dbQ($confirmed['telefono_genitore_2']) . ",
            dati_confermati_json = " . dbQ(json_encode($confirmed, JSON_UNESCAPED_UNICODE)) . ",
            stato = 'bozza',
            updated_at = NOW()
        WHERE id = " . intval($pratica['id']) . "
        LIMIT 1
    ");

    iscrizioniPrimeEnsureDocumentRows((int)$pratica['id']);

    return ['ok' => true, 'message' => 'Bozza salvata.', 'stato' => 'bozza'];
}

function iscrizioniPrimeRequiredDocumentTypes(array $pratica, array $confirmed = []): array
{
    $types = array_values(array_filter(
        array_keys(iscrizioniPrimeDocumentTypes()),
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

function iscrizioniPrimeEnsureDocumentRows(int $praticaId): void
{
    foreach (array_keys(iscrizioniPrimeDocumentTypes()) as $tipo) {
        dbExec("
            INSERT IGNORE INTO iscrizioni_prime_documenti (pratica_id, tipo_documento, stato)
            VALUES (" . intval($praticaId) . ", " . dbQ($tipo) . ", 'mancante')
        ");
    }
}

function iscrizioniPrimeDocumentsForPratica(int $praticaId): array
{
    iscrizioniPrimeEnsureDocumentRows($praticaId);
    $types = array_keys(iscrizioniPrimeDocumentTypes());

    return dbGetAll("
        SELECT *
        FROM iscrizioni_prime_documenti
        WHERE pratica_id = " . intval($praticaId) . "
          AND tipo_documento IN (" . implode(', ', array_map('dbQ', $types)) . ")
        ORDER BY FIELD(tipo_documento, " . implode(', ', array_map('dbQ', $types)) . ")
    ");
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

    if (in_array((string)$pratica['stato'], ['inviata', 'verificata', 'annullata'], true)) {
        return ['ok' => false, 'message' => 'La pratica non puo essere modificata in questo stato.'];
    }

    $types = iscrizioniPrimeDocumentTypes();
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
    iscrizioniPrimeEnsureDocumentRows((int)$pratica['id']);
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

    iscrizioniPrimeEnsureDocumentRows((int)$pratica['id']);
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

    dbExec("
        UPDATE iscrizioni_prime_pratiche SET
            stato = 'bozza',
            updated_at = NOW()
        WHERE id = " . intval($pratica['id']) . "
        LIMIT 1
    ");

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

function iscrizioniPrimeDeleteDocumentByToken(string $token, string $tipo): array
{
    $pratica = iscrizioniPrimeGetByToken($token);
    if (!$pratica) {
        return ['ok' => false, 'message' => 'Link non valido, scaduto o pratica non disponibile.'];
    }

    if (in_array((string)$pratica['stato'], ['inviata', 'verificata', 'annullata'], true)) {
        return ['ok' => false, 'message' => 'La pratica non puo essere modificata in questo stato.'];
    }

    $types = iscrizioniPrimeDocumentTypes();
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

    dbExec("
        UPDATE iscrizioni_prime_pratiche SET
            stato = 'bozza',
            updated_at = NOW()
        WHERE id = " . intval($pratica['id']) . "
        LIMIT 1
    ");

    return ['ok' => true, 'message' => $types[$tipo] . ' cancellato.'];
}

function iscrizioniPrimeMarkDocumentPaperByToken(string $token, string $tipo): array
{
    $pratica = iscrizioniPrimeGetByToken($token);
    if (!$pratica) {
        return ['ok' => false, 'message' => 'Link non valido, scaduto o pratica non disponibile.'];
    }

    if (in_array((string)$pratica['stato'], ['inviata', 'verificata', 'annullata'], true)) {
        return ['ok' => false, 'message' => 'La pratica non puo essere modificata in questo stato.'];
    }

    $types = iscrizioniPrimeDocumentTypes();
    if (!isset($types[$tipo])) {
        return ['ok' => false, 'message' => 'Tipo documento non valido.'];
    }

    $delete = iscrizioniPrimeDeleteDocumentByToken($token, $tipo);
    if (!$delete['ok']) {
        return $delete;
    }

    iscrizioniPrimeEnsureDocumentRows((int)$pratica['id']);
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

    dbExec("
        UPDATE iscrizioni_prime_pratiche SET
            stato = 'bozza',
            updated_at = NOW()
        WHERE id = " . intval($pratica['id']) . "
        LIMIT 1
    ");

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

    $documents = iscrizioniPrimeDocumentsForPratica((int)$pratica['id']);
    $byType = [];
    foreach ($documents as $document) {
        $byType[(string)$document['tipo_documento']] = $document;
    }

    $labels = iscrizioniPrimeDocumentTypes();
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
            updated_at = NOW()
        WHERE id = " . intval($pratica['id']) . "
        LIMIT 1
    ");

    $pratica['stato'] = 'inviata';
    $mail = iscrizioniPrimeSendSubmissionConfirmation($pratica);
    $message = 'Domanda inviata. I dati e i documenti sono stati registrati.';
    if (!empty($mail['ok'])) {
        $message .= ' Abbiamo inviato una mail di conferma ai genitori e alla segreteria.';
    } else {
        $message .= ' Attenzione: la registrazione e\' riuscita, ma non e\' stato possibile inviare la mail di conferma. La segreteria e\' stata avvisata se e\' configurata la mail di emergenza.';
    }

    return ['ok' => true, 'message' => $message, 'stato' => 'inviata', 'mail' => $mail];
}

function iscrizioniPrimeDocumentFileByToken(string $token, string $tipo): ?array
{
    $pratica = iscrizioniPrimeGetByToken($token);
    if (!$pratica) {
        return null;
    }

    $types = iscrizioniPrimeDocumentTypes();
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

function iscrizioniPrimeUpdateContacts(array $anagraficaRows): array
{
    $updated = 0;
    $ignored = 0;

    foreach ($anagraficaRows as $row) {
        $cf = strtoupper(trim((string)($row['CODICE FISCALE'] ?? '')));
        if ($cf === '') {
            $ignored++;
            continue;
        }
        $anno = trim((string)($row['ANNO SCOLASTICO'] ?? ''));
        $annoCondition = $anno !== '' ? " AND anno_scolastico = " . dbQ($anno) : '';

        $pratica = dbGetFirst("
            SELECT id
            FROM iscrizioni_prime_pratiche
            WHERE codice_fiscale = " . dbQ($cf) . "
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

    return ['updated' => $updated, 'ignored' => $ignored];
}

function iscrizioniPrimeUpsert(array $prime, ?array $dsa): array
{
    $cf = strtoupper(trim((string)($prime['CODICE FISCALE STUDENTE'] ?? ($dsa['CODICE FISCALE'] ?? ''))));
    $anno = trim((string)($prime['ANNO SCOLASTICO'] ?? ($dsa['ANNO SCOLASTICO'] ?? '')));

    if ($cf === '' || $anno === '') {
        return ['ok' => false, 'error' => 'codice fiscale o anno scolastico mancante'];
    }

    $existing = dbGetFirst("
        SELECT id, token_hash
        FROM iscrizioni_prime_pratiche
        WHERE anno_scolastico = " . dbQ($anno) . "
          AND codice_fiscale = " . dbQ($cf) . "
        LIMIT 1
    ");

    $token = null;
    if (!$existing || trim((string)($existing['token_hash'] ?? '')) === '') {
        $token = iscrizioniPrimeGenerateToken();
    }

    $fields = [
        'anno_scolastico' => $anno,
        'codice_domanda' => $prime['CODICE DOMANDA'] ?? null,
        'codice_sidi' => $dsa['CODICE SIDI'] ?? null,
        'codice_giada' => $dsa['CODICE GIADA'] ?? null,
        'codice_fiscale' => $cf,
        'cognome' => $prime['COGNOME STUDENTE'] ?? ($dsa['COGNOME'] ?? ''),
        'nome' => $prime['NOME STUDENTE'] ?? ($dsa['NOME'] ?? ''),
        'sesso' => $dsa['SESSO'] ?? null,
        'data_nascita' => iscrizioniPrimeDate($prime['DATA NASCITA STUDENTE'] ?? ($dsa['DATA NASCITA'] ?? '')),
        'unita_scolastica' => $prime['UNITA SCOLASTICA DI ISCRIZIONE'] ?? ($dsa['UNITA SCOLASTICA'] ?? null),
        'corso_studi' => $prime['CORSO DI STUDI DI ISCRIZIONE'] ?? ($dsa['CORSO STUDI'] ?? null),
        'anno_corso' => $prime['ANNO DI CORSO'] ?? ($dsa['ANNO CORSO'] ?? null),
        'mensa' => $dsa['MENSA'] ?? null,
        'religione' => $dsa['RELIGIONE'] ?? null,
        'scelta_alternativa_religione' => $dsa['SCELTA ALTERNATIVA RELIGIONE'] ?? null,
        'richiesta_trasporto' => $dsa['RICHIESTA TRASPORTO'] ?? null,
        'scelta_formativa' => $dsa['SCELTA FORMATIVA'] ?? null,
        'certificazione_online' => $dsa['DICHIARAZIONE CERTIFICAZIONE ONLINE'] ?? ($dsa['COLONNA_52'] ?? null),
        'raw_prime_json' => iscrizioniPrimeJson($prime),
        'raw_dsa_json' => $dsa ? iscrizioniPrimeJson($dsa) : null,
    ];

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
        iscrizioniPrimeEnsureDocumentRows($id);

        return ['ok' => true, 'inserted' => false, 'id' => $id, 'token' => $token['plain'] ?? null];
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

    iscrizioniPrimeEnsureDocumentRows((int)$id);

    return ['ok' => true, 'inserted' => true, 'id' => intval($id), 'token' => $token['plain'] ?? null];
}

function iscrizioniPrimeImport(string $primePath, string $dsaPath, string $primeName = '', string $dsaName = '', string $createdBy = '', ?string $anagraficaPath = null, string $anagraficaName = ''): array
{
    iscrizioniPrimeEnsureSchema();

    $primeRows = iscrizioniPrimeReadCsv($primePath);
    $dsaRows = iscrizioniPrimeReadCsv($dsaPath);
    $anagraficaRows = $anagraficaPath ? iscrizioniPrimeReadCsv($anagraficaPath) : [];
    $dsaByCf = [];

    foreach ($dsaRows as $row) {
        $cf = strtoupper(trim((string)($row['CODICE FISCALE'] ?? '')));
        if ($cf !== '') {
            $dsaByCf[$cf] = $row;
        }
    }

    $inserted = 0;
    $updated = 0;
    $errors = [];
    $generatedTokens = [];

    foreach ($primeRows as $index => $row) {
        $cf = strtoupper(trim((string)($row['CODICE FISCALE STUDENTE'] ?? '')));
        $result = iscrizioniPrimeUpsert($row, $dsaByCf[$cf] ?? null);

        if (!$result['ok']) {
            $errors[] = 'Riga PRIME ' . ($index + 2) . ': ' . $result['error'];
            continue;
        }

        if ($result['inserted']) {
            $inserted++;
        } else {
            $updated++;
        }

        if (!empty($result['token'])) {
            $generatedTokens[] = [
                'pratica_id' => $result['id'],
                'token' => $result['token'],
            ];
        }
    }

    $contacts = ['updated' => 0, 'ignored' => 0];
    if (!empty($anagraficaRows)) {
        $contacts = iscrizioniPrimeUpdateContacts($anagraficaRows);
    }

    dbExec("
        INSERT INTO iscrizioni_prime_import_log
        (created_at, created_by, prime_filename, dsa_filename, anagrafica_filename, righe_prime, righe_dsa, righe_anagrafica, inserite, aggiornate, contatti_aggiornati, contatti_ignorati, errori_json)
        VALUES (
            NOW(),
            " . dbQ($createdBy) . ",
            " . dbQ($primeName) . ",
            " . dbQ($dsaName) . ",
            " . dbQ($anagraficaName) . ",
            " . intval(count($primeRows)) . ",
            " . intval(count($dsaRows)) . ",
            " . intval(count($anagraficaRows)) . ",
            " . intval($inserted) . ",
            " . intval($updated) . ",
            " . intval($contacts['updated']) . ",
            " . intval($contacts['ignored']) . ",
            " . dbQ(json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "
        )
    ");

    return [
        'prime_rows' => count($primeRows),
        'dsa_rows' => count($dsaRows),
        'inserted' => $inserted,
        'updated' => $updated,
        'contact_rows' => count($anagraficaRows),
        'contacts_updated' => $contacts['updated'],
        'contacts_ignored' => $contacts['ignored'],
        'errors' => $errors,
        'generated_tokens' => $generatedTokens,
    ];
}
