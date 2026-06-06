<?php

/**
 * Utilities for parent exit permits: parent notifications and MasterCom sync.
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/mastercom/admin_lib.php';
require_once __DIR__ . '/notifichePreferenzeLib.php';

function permessiUscitaColumnExists(string $columnName): bool
{
    return mastercomAdminTableColumnExists('permessi_uscita', $columnName);
}

function permessiUscitaStateLabel($state): string
{
    switch (intval($state)) {
        case 1:
            return 'Richiesto';
        case 2:
            return 'Confermato';
        case 3:
            return 'Annullato per assenza dello studente';
        case 4:
            return 'Rifiutato';
        default:
            return 'Sconosciuto';
    }
}

function permessiUscitaCurrentTimestamp(): string
{
    return (new DateTime('now', new DateTimeZone('Europe/Rome')))->format('d/m/Y H:i:s');
}

function permessiUscitaLoad(int $id): ?array
{
    global $__anno_scolastico_corrente_id;

    if ($id <= 0) {
        return null;
    }

    $annoJoin = '';
    if (isset($__anno_scolastico_corrente_id) && intval($__anno_scolastico_corrente_id) > 0) {
        $annoJoin = ' AND sf.id_anno_scolastico = ' . dbI($__anno_scolastico_corrente_id);
    }

    $row = dbGetFirst("
        SELECT
            pu.*,
            g.nome AS genitore_nome,
            g.cognome AS genitore_cognome,
            g.email AS genitore_email,
            s.nome AS studente_nome,
            s.cognome AS studente_cognome,
            s.codice_fiscale AS studente_codice_fiscale,
            sf.id_classe AS studente_id_classe,
            c.classe AS studente_classe,
            ms.id_studente_gestore AS mastercom_id_studente_gestore,
            ms.mastercom_id_studente,
            ms.mastercom_id_classe_corrente,
            ms.nome AS mastercom_nome,
            ms.cognome AS mastercom_cognome,
            ms.codice_fiscale AS mastercom_codice_fiscale,
            mc.id_classe_gestore AS mastercom_id_classe_gestore,
            mc.nome AS mastercom_classe
        FROM permessi_uscita pu
        INNER JOIN genitori g ON g.id = pu.id_genitore
        INNER JOIN studente s ON s.id = pu.id_studente
        LEFT JOIN studente_frequenta sf ON sf.id_studente = pu.id_studente $annoJoin
        LEFT JOIN classi c ON c.id = sf.id_classe
        LEFT JOIN mastercom_studenti ms
            ON ms.id_studente_gestore = pu.id_studente
           AND (
                sf.id_classe IS NULL
                OR ms.mastercom_id_classe_corrente IN (
                    SELECT mc_match.mastercom_id_classe
                    FROM mastercom_classi mc_match
                    WHERE mc_match.id_classe_gestore = sf.id_classe
                )
           )
        LEFT JOIN mastercom_classi mc ON mc.mastercom_id_classe = ms.mastercom_id_classe_corrente
        WHERE pu.id = " . dbI($id) . "
        LIMIT 1
    ");

    return is_array($row) ? $row : null;
}

function permessiUscitaFormatDate(string $date): string
{
    $dt = DateTime::createFromFormat('Y-m-d', trim($date), new DateTimeZone('Europe/Rome'));
    return $dt instanceof DateTime ? $dt->format('d/m/Y') : $date;
}

function permessiUscitaFormatTime(string $time): string
{
    $time = trim($time);
    if (preg_match('/^(\d{2}:\d{2})/', $time, $matches)) {
        return $matches[1];
    }
    return $time;
}

function permessiUscitaTelegramH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function permessiUscitaTelegramIcon(array $permesso, string $reason): string
{
    if ($reason === 'creazione') {
        return '📝';
    }
    if ($reason === 'cancellazione') {
        return '🗑️';
    }

    switch (intval($permesso['stato'] ?? 0)) {
        case 2:
            return '✅';
        case 3:
        case 4:
            return '❌';
        default:
            return '🔔';
    }
}

function permessiUscitaDuplicateKey(array $permesso): string
{
    return implode('|', [
        trim((string)($permesso['data'] ?? '')),
        intval($permesso['id_studente'] ?? 0),
        permessiUscitaFormatTime((string)($permesso['ora_uscita'] ?? '')),
        intval($permesso['rientro'] ?? 0),
        permessiUscitaFormatTime((string)($permesso['ora_rientro'] ?? '')),
    ]);
}

function permessiUscitaFindAlreadySentDuplicate(array $permesso): ?array
{
    if (!permessiUscitaColumnExists('mastercom_sync_stato')) {
        return null;
    }

    $id = intval($permesso['id'] ?? 0);
    $idStudente = intval($permesso['id_studente'] ?? 0);
    $date = trim((string)($permesso['data'] ?? ''));
    $exitHour = permessiUscitaFormatTime((string)($permesso['ora_uscita'] ?? ''));
    $returnHour = permessiUscitaFormatTime((string)($permesso['ora_rientro'] ?? ''));
    $rientro = intval($permesso['rientro'] ?? 0);

    if ($idStudente <= 0 || $date === '' || $exitHour === '') {
        return null;
    }

    $row = dbGetFirst("
        SELECT id, mastercom_sync_stato, mastercom_sync_last_note
        FROM permessi_uscita
        WHERE id <> " . dbI($id) . "
          AND id_studente = " . dbI($idStudente) . "
          AND data = " . dbQ($date) . "
          AND TIME_FORMAT(ora_uscita, '%H:%i') = " . dbQ($exitHour) . "
          AND rientro = " . dbI($rientro) . "
          AND TIME_FORMAT(ora_rientro, '%H:%i') = " . dbQ($returnHour) . "
          AND stato = 2
          AND mastercom_sync_stato = 'INVIATO'
        ORDER BY id ASC
        LIMIT 1
    ");

    return is_array($row) ? $row : null;
}

function permessiUscitaMailHtml(array $permesso, string $title, string $intro, string $reason = ''): string
{
    $student = trim((string)($permesso['studente_nome'] ?? '') . ' ' . (string)($permesso['studente_cognome'] ?? ''));
    $class = trim((string)($permesso['studente_classe'] ?? ''));
    $state = permessiUscitaStateLabel($permesso['stato'] ?? 0);
    $date = permessiUscitaFormatDate((string)($permesso['data'] ?? ''));
    $time = permessiUscitaFormatTime((string)($permesso['ora_uscita'] ?? ''));
    $reason = trim((string)($permesso['motivo'] ?? ''));
    $note = trim((string)($permesso['note_segreteria'] ?? ''));

    $badgeStyle = [
        1 => ['RICHIESTA RICEVUTA', '#fef3c7', '#92400e', 'warning'],
        2 => ['PERMESSO CONFERMATO', '#dcfce7', '#14532d', 'default'],
        3 => ['ANNULLATO PER ASSENZA', '#fee2e2', '#7f1d1d', 'annullamento'],
        4 => ['PERMESSO RIFIUTATO', '#fee2e2', '#7f1d1d', 'annullamento'],
    ];
    $style = $badgeStyle[intval($permesso['stato'] ?? 0)] ?? ['AGGIORNAMENTO', '#e5e7eb', '#374151', 'default'];
    if ($reason === 'cancellazione') {
        $style = ['RICHIESTA CANCELLATA', '#fee2e2', '#7f1d1d', 'annullamento'];
        $state = 'Cancellato dal genitore';
    }

    $content = '
        <div style="margin:0 0 12px 0;">
            ' . badge($style[0], $style[1], $style[2]) . '
        </div>

        <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:12px 12px;margin:0 0 14px 0;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                ' . kvRow('Studente', $student) . '
                ' . kvRow('Classe', $class !== '' ? $class : '-') . '
                ' . kvRow('Data', $date) . '
                ' . kvRow('Ora uscita', $time) . '
                ' . kvRow('Stato', $state) . '
                ' . kvRow('Motivo', $reason) . '
                ' . ($note !== '' ? kvRow('Note segreteria', $note) : '') . '
            </table>
        </div>

        <div style="font-size:13.5px;line-height:1.55;color:#374151;">
            Questa comunicazione riguarda la richiesta di permesso di uscita indicata sopra.
        </div>
    ';

    return mailWrap(
        strtoupper($title),
        trim((string)($permesso['genitore_nome'] ?? '') . ' ' . (string)($permesso['genitore_cognome'] ?? '')),
        $intro,
        $content,
        'Per informazioni contatta la segreteria didattica.',
        $style[3]
    );
}

function permessiUscitaSendParentMailFromRow(array $permesso, string $reason): bool
{
    require_once __DIR__ . '/mail-ui.php';

    $genitoreId = intval($permesso['id_genitore'] ?? 0);
    if ($genitoreId <= 0) {
        $id = intval($permesso['id'] ?? 0);
        info("permessi_uscita: notifica non inviata per permesso id=$id, id genitore mancante");
        return false;
    }

    $toName = trim((string)($permesso['genitore_nome'] ?? '') . ' ' . (string)($permesso['genitore_cognome'] ?? ''));
    $state = permessiUscitaStateLabel($permesso['stato'] ?? 0);
    $student = trim((string)($permesso['studente_nome'] ?? '') . ' ' . (string)($permesso['studente_cognome'] ?? ''));

    if ($reason === 'creazione') {
        $subject = 'GestOre - Richiesta permesso di uscita ricevuta';
        $title = 'Richiesta permesso ricevuta';
        $intro = 'La richiesta di permesso di uscita e stata registrata ed e in attesa di validazione.';
    } elseif ($reason === 'cancellazione') {
        $subject = 'GestOre - Richiesta permesso di uscita cancellata';
        $title = 'Richiesta permesso cancellata';
        $intro = 'La richiesta di permesso di uscita per ' . $student . ' e stata cancellata prima della validazione.';
    } else {
        $subject = 'GestOre - Permesso di uscita ' . $state;
        $title = 'Aggiornamento permesso di uscita';
        $intro = 'Lo stato della richiesta di permesso di uscita per ' . $student . ' e stato aggiornato.';
    }

    $mailHtml = permessiUscitaMailHtml($permesso, $title, $intro, $reason);
    $class = trim((string)($permesso['studente_classe'] ?? ''));
    $date = permessiUscitaFormatDate((string)($permesso['data'] ?? ''));
    $exitTime = permessiUscitaFormatTime((string)($permesso['ora_uscita'] ?? ''));
    $returnTime = intval($permesso['rientro'] ?? 0) === 1 ? permessiUscitaFormatTime((string)($permesso['ora_rientro'] ?? '')) : '';
    $motivo = trim((string)($permesso['motivo'] ?? ''));
    $telegramIcon = permessiUscitaTelegramIcon($permesso, $reason);

    $telegramText = $telegramIcon . ' <b>' . permessiUscitaTelegramH($title) . '</b>' . "\n"
        . permessiUscitaTelegramH($intro) . "\n\n"
        . '👤 <b>Studente</b>: ' . permessiUscitaTelegramH($student !== '' ? $student : '-') . "\n"
        . '🏫 <b>Classe</b>: ' . permessiUscitaTelegramH($class !== '' ? $class : '-') . "\n"
        . '📅 <b>Data</b>: ' . permessiUscitaTelegramH($date) . "\n"
        . '🕒 <b>Ora uscita</b>: ' . permessiUscitaTelegramH($exitTime !== '' ? $exitTime : '-') . "\n"
        . ($returnTime !== '' ? '↩️ <b>Ora rientro</b>: ' . permessiUscitaTelegramH($returnTime) . "\n" : '')
        . $telegramIcon . ' <b>Stato</b>: ' . permessiUscitaTelegramH($state)
        . ($motivo !== '' ? "\n" . '💬 <b>Motivo</b>: ' . permessiUscitaTelegramH($motivo) : '');

    $result = notifichePreferenzeInviaGenitore($genitoreId, 'permessi', $subject, $mailHtml, $telegramText, [], ['parse_mode' => 'HTML']);
    $ok = !empty($result['ok']);
    $id = intval($permesso['id'] ?? 0);
    info("permessi_uscita: notifica reason=$reason id=$id ok=" . ($ok ? '1' : '0') . " channels=" . json_encode($result['channels'] ?? []));
    return $ok;
}

function permessiUscitaSendParentMail(int $id, string $reason): bool
{
    $permesso = permessiUscitaLoad($id);
    if (!$permesso) {
        return false;
    }

    return permessiUscitaSendParentMailFromRow($permesso, $reason);
}

function permessiUscitaSetSyncState(int $id, string $state, string $note = '', string $error = ''): void
{
    $set = [];
    if (permessiUscitaColumnExists('mastercom_sync_stato')) {
        $set[] = 'mastercom_sync_stato = ' . dbQ($state);
    }
    if (permessiUscitaColumnExists('mastercom_sync_at')) {
        $set[] = 'mastercom_sync_at = NOW()';
    }
    if (permessiUscitaColumnExists('mastercom_sync_attempts')) {
        $set[] = 'mastercom_sync_attempts = COALESCE(mastercom_sync_attempts, 0) + 1';
    }
    if (permessiUscitaColumnExists('mastercom_sync_last_note')) {
        $set[] = 'mastercom_sync_last_note = ' . dbQ($note);
    }
    if (permessiUscitaColumnExists('mastercom_sync_last_error')) {
        $set[] = 'mastercom_sync_last_error = ' . dbQ($error);
    }

    if (!empty($set)) {
        dbExec('UPDATE permessi_uscita SET ' . implode(', ', $set) . ' WHERE id = ' . dbI($id) . ' LIMIT 1');
    }
}

function permessiUscitaSetPresenceSnapshot(int $id, string $state, string $label, string $detail): void
{
    $set = [];
    if (permessiUscitaColumnExists('mastercom_presence_stato')) {
        $set[] = 'mastercom_presence_stato = ' . dbQ($state);
    }
    if (permessiUscitaColumnExists('mastercom_presence_label')) {
        $set[] = 'mastercom_presence_label = ' . dbQ($label);
    }
    if (permessiUscitaColumnExists('mastercom_presence_detail')) {
        $set[] = 'mastercom_presence_detail = ' . dbQ($detail);
    }
    if (permessiUscitaColumnExists('mastercom_presence_at')) {
        $set[] = 'mastercom_presence_at = NOW()';
    }

    if (!empty($set)) {
        dbExec('UPDATE permessi_uscita SET ' . implode(', ', $set) . ' WHERE id = ' . dbI($id) . ' LIMIT 1');
    }
}

function permessiUscitaFreezePresence(int $id): void
{
    if ($id <= 0 || !permessiUscitaColumnExists('mastercom_presence_stato')) {
        return;
    }

    require_once __DIR__ . '/mastercom/noirc_lib.php';

    $permesso = permessiUscitaLoad($id);
    if (!$permesso) {
        return;
    }

    $student = permessiUscitaMastercomStudent($permesso);
    if ($student === null) {
        permessiUscitaSetPresenceSnapshot($id, 'NON_COLLEGATO', 'Non collegato', 'Studente non collegato alla mirror MasterCom');
        return;
    }

    $today = (new DateTime('now', new DateTimeZone('Europe/Rome')))->format('Y-m-d');
    $date = trim((string)($permesso['data'] ?? ''));
    if ($date !== $today) {
        permessiUscitaSetPresenceSnapshot($id, 'NON_DISPONIBILE', 'Solo oggi', 'Snapshot appello disponibile solo per la giornata corrente');
        return;
    }

    $hour = (new DateTime('now', new DateTimeZone('Europe/Rome')))->format('H:i');
    $presenceResult = mastercomNoIrcLoadPresenceMap([$student], $date, $hour);
    $presence = is_array($presenceResult['map'][$student['mastercom_id_studente']] ?? null)
        ? $presenceResult['map'][$student['mastercom_id_studente']]
        : [
            'stato' => 'NON_VERIFICATO',
            'label' => 'Da verificare',
            'detail' => trim((string)($presenceResult['error'] ?? 'Snapshot non disponibile')),
        ];

    permessiUscitaSetPresenceSnapshot(
        $id,
        strtoupper(trim((string)($presence['stato'] ?? 'NON_VERIFICATO'))),
        trim((string)($presence['label'] ?? 'Da verificare')),
        trim((string)($presence['detail'] ?? ''))
    );
}

function permessiUscitaAppendNote(int $id, string $line): void
{
    $line = trim($line);
    if ($id <= 0 || $line === '') {
        return;
    }

    $currentNote = (string)dbGetValue("SELECT note_segreteria FROM permessi_uscita WHERE id = " . dbI($id) . " LIMIT 1");
    $lastLine = trim((string)preg_replace('/^.*\[(\d{2}\/\d{2}\/\d{4}|\d{4}-\d{2}-\d{2}) \d{2}:\d{2}:\d{2}\]\s*/s', '', $currentNote));
    if ($lastLine === $line) {
        return;
    }

    $stamp = '[' . permessiUscitaCurrentTimestamp() . '] ' . $line;
    dbExec("
        UPDATE permessi_uscita
        SET note_segreteria = TRIM(CONCAT(COALESCE(note_segreteria, ''), CASE WHEN COALESCE(note_segreteria, '') = '' THEN '' ELSE '\n' END, " . dbQ($stamp) . "))
        WHERE id = " . dbI($id) . "
        LIMIT 1
    ");
}

function permessiUscitaMastercomStudent(array $permesso): ?array
{
    $studentId = intval($permesso['mastercom_id_studente'] ?? 0);
    $classId = intval($permesso['mastercom_id_classe_corrente'] ?? 0);
    if ($studentId <= 0 || $classId <= 0) {
        return null;
    }

    $linkedLocalId = intval($permesso['mastercom_id_studente_gestore'] ?? 0);
    $localId = intval($permesso['id_studente'] ?? 0);
    if ($localId > 0 && $linkedLocalId > 0 && $linkedLocalId !== $localId) {
        return null;
    }

    $localCf = mastercomAdminNormCompact($permesso['studente_codice_fiscale'] ?? '');
    $mastercomCf = mastercomAdminNormCompact($permesso['mastercom_codice_fiscale'] ?? '');
    if ($localCf === '' || $mastercomCf === '') {
        return null;
    }
    if ($localCf !== '' && $mastercomCf !== '' && $localCf !== $mastercomCf) {
        return null;
    }

    $localClassId = intval($permesso['studente_id_classe'] ?? 0);
    $mastercomLocalClassId = intval($permesso['mastercom_id_classe_gestore'] ?? 0);
    if ($localClassId > 0 && $mastercomLocalClassId > 0 && $localClassId !== $mastercomLocalClassId) {
        return null;
    }

    return [
        'mastercom_id_studente' => $studentId,
        'mastercom_id_classe_corrente' => $classId,
        'cognome' => trim((string)($permesso['mastercom_cognome'] ?? $permesso['studente_cognome'] ?? '')),
        'nome' => trim((string)($permesso['mastercom_nome'] ?? $permesso['studente_nome'] ?? '')),
        'classe' => trim((string)($permesso['mastercom_classe'] ?? $permesso['studente_classe'] ?? '')),
    ];
}

function permessiUscitaExitType(string $hour): int
{
    require_once __DIR__ . '/mastercom/noirc_lib.php';

    return mastercomNoIrcIsAfternoonHour($hour) ? 9 : 3;
}

function permessiUscitaPermissionDateTime(array $permesso): DateTime
{
    $date = trim((string)($permesso['data'] ?? ''));
    $hour = permessiUscitaFormatTime((string)($permesso['ora_uscita'] ?? ''));
    $dt = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $hour, new DateTimeZone('Europe/Rome'));
    if (!$dt instanceof DateTime) {
        $dt = new DateTime('now', new DateTimeZone('Europe/Rome'));
    }
    return $dt;
}

function permessiUscitaTimeToMinutes(string $time): int
{
    $time = permessiUscitaFormatTime($time);
    if (!preg_match('/^(\d{2}):(\d{2})$/', $time, $matches)) {
        return -1;
    }
    return intval($matches[1]) * 60 + intval($matches[2]);
}

function permessiUscitaPresenceOverride(array $permesso, ?DateTime $now = null): ?array
{
    $syncState = strtoupper(trim((string)($permesso['mastercom_sync_stato'] ?? '')));
    if ($syncState !== 'INVIATO') {
        return null;
    }

    $date = trim((string)($permesso['data'] ?? ''));
    if ($date === '') {
        return null;
    }

    $now = $now ?: new DateTime('now', new DateTimeZone('Europe/Rome'));
    if ($date !== $now->format('Y-m-d')) {
        return null;
    }

    $exitHour = permessiUscitaFormatTime((string)($permesso['ora_uscita'] ?? ''));
    $exitMinute = permessiUscitaTimeToMinutes($exitHour);
    $nowMinute = intval($now->format('H')) * 60 + intval($now->format('i'));
    if ($exitMinute < 0 || $nowMinute < $exitMinute) {
        return null;
    }

    $returnHour = permessiUscitaFormatTime((string)($permesso['ora_rientro'] ?? ''));
    $returnMinute = permessiUscitaTimeToMinutes($returnHour);
    $hasReturn = intval($permesso['rientro'] ?? 0) === 1
        && $returnMinute > $exitMinute
        && $returnHour !== '00:00';

    if ($hasReturn && $nowMinute >= $returnMinute) {
        $label = 'Uscito con permesso ' . $exitHour . ' - Rientrato alle ' . $returnHour;
        $detail = 'Permesso di uscita inviato a MasterCom: uscito alle ' . $exitHour . ' e rientrato alle ' . $returnHour . '.';
    } else {
        $label = 'Uscito con permesso';
        $detail = 'Permesso di uscita inviato a MasterCom: uscito alle ' . $exitHour . '.';
        if ($hasReturn) {
            $detail .= ' Rientro previsto alle ' . $returnHour . '.';
        }
    }

    return [
        'stato' => 'USCITO_PERMESSO',
        'label' => $label,
        'detail' => $detail,
        'color' => '#337ab7',
    ];
}

function permessiUscitaPresenceHasManualExit(array $presence, string $date, string $hour): bool
{
    if (!is_array($presence['appeal'] ?? null)) {
        return false;
    }

    $appeal = $presence['appeal'];
    $entries = [];
    foreach (['permessi', 'uscite'] as $key) {
        if (is_array($appeal[$key] ?? null)) {
            $entries = array_merge($entries, $appeal[$key]);
        }
    }
    if (is_array($appeal['assenze'] ?? null)) {
        foreach ($appeal['assenze'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $tipo = intval($entry['tipo'] ?? $entry['tipo_assenza'] ?? 0);
            if (in_array($tipo, [3, 9], true)) {
                $entries[] = $entry;
            }
        }
    }
    if (is_array($appeal['stato']['ultimo'] ?? null)) {
        $entry = $appeal['stato']['ultimo'];
        $tipo = intval($entry['tipo'] ?? $entry['tipo_assenza'] ?? 0);
        if (in_array($tipo, [3, 9], true)) {
            $entries[] = $entry;
        }
    }
    if (empty($entries)) {
        return false;
    }

    if (!function_exists('mastercomNoIrcPresenceEntryTimeMinutes') || !function_exists('mastercomNoIrcTimeToMinutes')) {
        return true;
    }

    $permitMinute = mastercomNoIrcTimeToMinutes($hour);
    if ($permitMinute < 0) {
        return true;
    }

    $hasUntimedEntry = false;
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $entryMinute = mastercomNoIrcPresenceEntryTimeMinutes($entry, $date);
        if ($entryMinute < 0) {
            $hasUntimedEntry = true;
            continue;
        }
        if ($entryMinute === $permitMinute) {
            return true;
        }
    }

    return $hasUntimedEntry;
}

function permessiUscitaSyncOne(int $id): array
{
    require_once __DIR__ . '/mastercom/noirc_lib.php';

    if (!permessiUscitaColumnExists('mastercom_sync_stato')) {
        return [
            'ok' => false,
            'id' => $id,
            'status' => 'MIGRAZIONE_MANCANTE',
            'message' => 'Esegui prima doc/permessi_uscita_mastercom_sync.sql: senza stato sync il sistema non puo evitare duplicati su MasterCom.',
        ];
    }

    $permesso = permessiUscitaLoad($id);
    if (!$permesso) {
        return ['ok' => false, 'id' => $id, 'status' => 'NOT_FOUND', 'message' => 'Permesso non trovato'];
    }

    if (intval($permesso['stato'] ?? 0) !== 2) {
        return ['ok' => true, 'id' => $id, 'status' => 'SKIP', 'message' => 'Permesso non confermato'];
    }

    $student = permessiUscitaMastercomStudent($permesso);
    if ($student === null) {
        $message = 'Studente non collegato alla mirror MasterCom';
        permessiUscitaSetSyncState($id, 'ERRORE', $message, $message);
        return ['ok' => false, 'id' => $id, 'status' => 'ERRORE', 'message' => $message];
    }

    $date = trim((string)($permesso['data'] ?? ''));
    $hour = permessiUscitaFormatTime((string)($permesso['ora_uscita'] ?? ''));
    $today = (new DateTime('now', new DateTimeZone('Europe/Rome')))->format('Y-m-d');
    if ($date !== $today) {
        return [
            'ok' => true,
            'id' => $id,
            'status' => 'SKIP_DATE',
            'message' => 'Sync non eseguito: lo stato appello MasterCom e disponibile solo per la giornata corrente.',
        ];
    }

    $presenceResult = mastercomNoIrcLoadPresenceMap([$student], $date, $hour);
    $presence = is_array($presenceResult['map'][$student['mastercom_id_studente']] ?? null)
        ? $presenceResult['map'][$student['mastercom_id_studente']]
        : ['stato' => 'NON_VERIFICATO', 'label' => 'Da verificare', 'detail' => 'Snapshot non disponibile'];

    $currentSyncState = strtoupper(trim((string)($permesso['mastercom_sync_stato'] ?? '')));
    $currentSyncNote = trim((string)($permesso['mastercom_sync_last_note'] ?? ''));
    $wasSentByGestore = $currentSyncState === 'INVIATO'
        || stripos($currentSyncNote, 'permesso inviato a mastercom') !== false;

    if ($wasSentByGestore && permessiUscitaPresenceHasManualExit($presence, $date, $hour)) {
        $message = 'Permesso gia presente su MasterCom per invio GestOre.';
        permessiUscitaSetPresenceSnapshot($id, 'USCITO_PERMESSO', 'Uscito con permesso', $message);
        permessiUscitaSetSyncState($id, 'INVIATO', $message, '');
        return ['ok' => true, 'id' => $id, 'status' => 'INVIATO', 'message' => $message];
    }

    $sentDuplicate = permessiUscitaFindAlreadySentDuplicate($permesso);
    if ($sentDuplicate !== null) {
        $message = 'Permesso duplicato gia inviato da GestOre con richiesta #' . intval($sentDuplicate['id']) . ': ignorato per evitare doppio invio.';
        permessiUscitaSetSyncState($id, 'INVIATO', $message, '');
        return ['ok' => true, 'id' => $id, 'status' => 'DUPLICATO_GESTORE', 'message' => $message];
    }

    if (permessiUscitaPresenceHasManualExit($presence, $date, $hour)) {
        $message = 'Permesso gia inserito manualmente su MasterCom.';
        permessiUscitaSetPresenceSnapshot($id, 'PERMESSO', 'Permesso MasterCom', $message);
        permessiUscitaSetSyncState($id, 'INVIATO', $message, '');
        permessiUscitaAppendNote($id, $message);
        return ['ok' => true, 'id' => $id, 'status' => 'INVIATO', 'message' => $message];
    }

    $mcState = strtoupper(trim((string)($presence['stato'] ?? 'NON_VERIFICATO')));
    $alreadyOut = in_array($mcState, ['USCITA', 'PERMESSO'], true);
    $isPresent = in_array($mcState, ['PRESENTE', 'ENTRATA_RITARDO', 'EVENTO'], true);
    $permissionDt = permessiUscitaPermissionDateTime($permesso);
    $now = new DateTime('now', new DateTimeZone('Europe/Rome'));

    if ($alreadyOut) {
        if ($wasSentByGestore) {
            $message = 'Permesso gia presente su MasterCom per invio GestOre.';
            permessiUscitaSetPresenceSnapshot($id, 'USCITO_PERMESSO', 'Uscito con permesso', $message);
            permessiUscitaSetSyncState($id, 'INVIATO', $message, '');
            return ['ok' => true, 'id' => $id, 'status' => 'INVIATO', 'message' => $message];
        }
        $message = 'Permesso gia inserito manualmente su MasterCom.';
        permessiUscitaSetSyncState($id, 'INVIATO', $message, '');
        permessiUscitaAppendNote($id, $message);
        return ['ok' => true, 'id' => $id, 'status' => 'INVIATO', 'message' => $message];
    }

    if (!$isPresent) {
        $detail = trim((string)($presence['detail'] ?? $presence['label'] ?? $mcState));
        if ($mcState === 'NON_VERIFICATO' && $now < $permissionDt) {
            $currentHour = $now->format('H:i');
            $currentPresenceResult = mastercomNoIrcLoadPresenceMap([$student], $date, $currentHour);
            $currentPresence = is_array($currentPresenceResult['map'][$student['mastercom_id_studente']] ?? null)
                ? $currentPresenceResult['map'][$student['mastercom_id_studente']]
                : [];
            $currentState = strtoupper(trim((string)($currentPresence['stato'] ?? 'NON_VERIFICATO')));
            if (in_array($currentState, ['PRESENTE', 'ENTRATA_RITARDO', 'EVENTO'], true)) {
                $currentDetail = trim((string)($currentPresence['detail'] ?? ''));
                $hasFutureManualPermit = stripos($currentDetail, 'uscita/permesso') !== false
                    || stripos($currentDetail, 'uscita registrata') !== false
                    || stripos($currentDetail, 'permesso registrato') !== false;
                permessiUscitaSetPresenceSnapshot(
                    $id,
                    $currentState,
                    trim((string)($currentPresence['label'] ?? 'Presente')),
                    $currentDetail !== '' ? $currentDetail : 'Studente presente ora su MasterCom'
                );
                if ($hasFutureManualPermit) {
                    $message = 'Permesso gia inserito manualmente su MasterCom.';
                    permessiUscitaSetSyncState($id, 'INVIATO', $message, '');
                    permessiUscitaAppendNote($id, $message);
                    return ['ok' => true, 'id' => $id, 'status' => 'INVIATO', 'message' => $message];
                }
                $message = 'Studente presente ora su MasterCom; l ora del permesso e futura e MasterCom non indica una lezione corrente per quell ora. Il permesso resta da inviare/riprovare piu tardi.';
                permessiUscitaSetSyncState($id, 'DA_INVIARE', $message, '');
                return ['ok' => true, 'id' => $id, 'status' => 'DA_INVIARE', 'message' => $message];
            }

            $message = 'MasterCom non indica una lezione all ora del permesso, ma il permesso e futuro: non viene trattato come assenza e resta da inviare/riprovare piu tardi.';
            permessiUscitaSetSyncState($id, 'DA_INVIARE', $message, '');
            return ['ok' => true, 'id' => $id, 'status' => 'DA_INVIARE', 'message' => $message];
        }

        if ($now > $permissionDt) {
            dbExec("UPDATE permessi_uscita SET stato = 3 WHERE id = " . dbI($id) . " LIMIT 1");
            permessiUscitaSetPresenceSnapshot($id, $mcState, trim((string)($presence['label'] ?? 'Da verificare')), trim((string)($presence['detail'] ?? '')));
            $message = 'Permesso annullato: lo studente risulta assente/non presente su MasterCom all ora prevista. ' . $detail;
            permessiUscitaSetSyncState($id, 'ANNULLATO_ASSENTE', $message, '');
            permessiUscitaAppendNote($id, $message);
            permessiUscitaSendParentMail($id, 'stato');
            return ['ok' => true, 'id' => $id, 'status' => 'ANNULLATO_ASSENTE', 'message' => $message];
        }

        $message = 'Studente non presente su MasterCom: il permesso resta in attesa di nuovo sync. ' . $detail;
        permessiUscitaSetSyncState($id, 'ASSENTE_ATTESA', $message, '');
        permessiUscitaAppendNote($id, $message);
        return ['ok' => true, 'id' => $id, 'status' => 'ASSENTE_ATTESA', 'message' => $message];
    }

    $type = permessiUscitaExitType($hour);
    $typeLabels = mastercomNoIrcAbsenceTypeLabels();
    $plan = [
        'kind' => 'create',
        'summary' => 'Inserira su MasterCom una ' . ($typeLabels[$type] ?? 'Uscita in Anticipo') . ' con orario ' . $hour . '.',
        'payload' => mastercomNoIrcBuildAdminAbsencePayload($student, $date, $hour, $type, 'inserisci_assenze_studente_update', [
            'tipo_giustificazione' => 1,
            'motivazione' => 'Permesso di uscita autorizzato da GestOre',
        ]),
        'type_label' => $typeLabels[$type] ?? '',
    ];

    $execute = mastercomNoIrcExecuteMastercomAction($plan);
    if (empty($execute['ok'])) {
        $error = trim((string)($execute['error'] ?? 'Errore invio MasterCom'));
        permessiUscitaSetSyncState($id, 'ERRORE', 'Errore invio MasterCom', $error);
        return ['ok' => false, 'id' => $id, 'status' => 'ERRORE', 'message' => $error];
    }

    $message = 'Permesso inviato a MasterCom: ' . ($typeLabels[$type] ?? 'Uscita in anticipo') . ' ore ' . $hour . '.';
    permessiUscitaSetSyncState($id, 'INVIATO', $message, '');
    permessiUscitaAppendNote($id, $message);
    return ['ok' => true, 'id' => $id, 'status' => 'INVIATO', 'message' => $message];
}

function permessiUscitaSyncPending(string $date = ''): array
{
    $date = trim($date);
    if ($date === '') {
        $date = (new DateTime('now', new DateTimeZone('Europe/Rome')))->format('Y-m-d');
    }

    $whereSync = '';
    if (permessiUscitaColumnExists('mastercom_sync_stato')) {
        $whereSync = " AND (mastercom_sync_stato IS NULL OR mastercom_sync_stato IN ('DA_INVIARE', 'ASSENTE_ATTESA', 'ERRORE'))";
    }

    $ids = dbGetAll("
        SELECT id
        FROM permessi_uscita
        WHERE stato = 2
          AND data = " . dbQ($date) . "
          $whereSync
        ORDER BY ora_uscita ASC, id ASC
    ");
    if (!is_array($ids)) {
        $ids = [];
    }

    $results = [];
    foreach ($ids as $row) {
        $results[] = permessiUscitaSyncOne(intval($row['id'] ?? 0));
    }

    return [
        'ok' => true,
        'date' => $date,
        'count' => count($results),
        'results' => $results,
    ];
}

function permessiUscitaMarkConfirmedForSync(int $id): void
{
    if ($id <= 0 || !permessiUscitaColumnExists('mastercom_sync_stato')) {
        return;
    }
    dbExec("
        UPDATE permessi_uscita
        SET mastercom_sync_stato = 'DA_INVIARE',
            mastercom_sync_last_error = NULL,
            mastercom_sync_last_note = NULL
        WHERE id = " . dbI($id) . "
        LIMIT 1
    ");
}
