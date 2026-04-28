<?php

declare(strict_types=1);

require_once __DIR__ . '/connect.php';

function ticketEventiTimeZone(): DateTimeZone
{
    static $tz = null;
    if ($tz === null) {
        $tz = new DateTimeZone('Europe/Rome');
    }

    return $tz;
}

function ticketEventiDateTime(?string $value): ?DateTimeImmutable
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $tz = ticketEventiTimeZone();
    $formats = ['Y-m-d H:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i'];

    foreach ($formats as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $value, $tz);
        if ($dt instanceof DateTimeImmutable) {
            return $dt;
        }
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return null;
    }

    return (new DateTimeImmutable('@' . $ts))->setTimezone($tz);
}

function ticketEventiNow(): DateTimeImmutable
{
    return new DateTimeImmutable('now', ticketEventiTimeZone());
}

function ticketEventiEnsureSchema(): void
{
    dbExec("
        CREATE TABLE IF NOT EXISTS ticket_evento (
            id INT AUTO_INCREMENT PRIMARY KEY,
            anno_scolastico_id INT NOT NULL,
            titolo VARCHAR(255) NOT NULL,
            descrizione TEXT NULL,
            luogo VARCHAR(255) NULL,
            data_evento DATETIME NOT NULL,
            apertura_prenotazioni DATETIME NULL,
            chiusura_prenotazioni DATETIME NULL,
            max_posti_per_utente INT NOT NULL DEFAULT 1,
            max_posti_totali INT NULL,
            visibile_studenti TINYINT(1) NOT NULL DEFAULT 1,
            visibile_docenti TINYINT(1) NOT NULL DEFAULT 1,
            visibile_ata TINYINT(1) NOT NULL DEFAULT 1,
            stato VARCHAR(20) NOT NULL DEFAULT 'bozza',
            creato_da_utente_id INT NULL,
            aggiornato_da_utente_id INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_ticket_evento_anno (anno_scolastico_id),
            INDEX idx_ticket_evento_stato (stato),
            INDEX idx_ticket_evento_data (data_evento)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");

    dbExec("
        CREATE TABLE IF NOT EXISTS ticket_prenotazione (
            id INT AUTO_INCREMENT PRIMARY KEY,
            evento_id INT NOT NULL,
            anno_scolastico_id INT NOT NULL,
            utente_id INT NULL,
            ruolo VARCHAR(30) NOT NULL,
            riferimento_id INT NOT NULL,
            nominativo VARCHAR(255) NOT NULL,
            email VARCHAR(255) NULL,
            classe_label VARCHAR(100) NULL,
            numero_posti INT NOT NULL DEFAULT 1,
            note VARCHAR(1000) NULL,
            stato VARCHAR(20) NOT NULL DEFAULT 'attiva',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_ticket_prenotazione_attiva (evento_id, ruolo, riferimento_id),
            INDEX idx_ticket_prenotazione_evento (evento_id),
            INDEX idx_ticket_prenotazione_anno (anno_scolastico_id),
            CONSTRAINT fk_ticket_prenotazione_evento FOREIGN KEY (evento_id) REFERENCES ticket_evento(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");
}

function ticketEventiCurrentActor(): ?array
{
    global $__utente_id, $__utente_ruolo;
    global $__studente_id, $__studente_nome, $__studente_cognome, $__studente_email, $__anno_scolastico_corrente_id;
    global $__docente_id, $__docente_nome, $__docente_cognome, $__docente_email;
    global $__ata_id, $__ata_nome, $__ata_cognome, $__ata_email;

    if (impersonaRuolo('docente') && !empty($__docente_id)) {
        return [
            'ruolo' => 'docente',
            'riferimento_id' => (int)$__docente_id,
            'utente_id' => ($__utente_id !== null ? (int)$__utente_id : -1),
            'nominativo' => trim((string)$__docente_cognome . ' ' . (string)$__docente_nome),
            'email' => (string)$__docente_email,
            'classe_label' => '',
        ];
    }

    if (impersonaRuolo('studente') && !empty($__studente_id)) {
        return [
            'ruolo' => 'studente',
            'riferimento_id' => (int)$__studente_id,
            'utente_id' => ($__utente_id !== null ? (int)$__utente_id : -1),
            'nominativo' => trim((string)$__studente_cognome . ' ' . (string)$__studente_nome),
            'email' => (string)$__studente_email,
            'classe_label' => (string)ticketEventiStudenteClasse((int)$__studente_id, (int)$__anno_scolastico_corrente_id),
        ];
    }

    if (haRuolo('personale-ata') && !empty($__ata_id)) {
        return [
            'ruolo' => 'personale-ata',
            'riferimento_id' => (int)$__ata_id,
            'utente_id' => ($__utente_id !== null ? (int)$__utente_id : -1),
            'nominativo' => trim((string)$__ata_cognome . ' ' . (string)$__ata_nome),
            'email' => (string)$__ata_email,
            'classe_label' => '',
        ];
    }

    return null;
}

function ticketEventiStudenteClasse(int $studenteId, int $annoId): string
{
    $row = dbGetFirst("
        SELECT c.classe
        FROM studente_frequenta sf
        INNER JOIN classi c ON c.id = sf.id_classe
        WHERE sf.id_studente = " . dbI($studenteId) . "
          AND sf.id_anno_scolastico = " . dbI($annoId) . "
        LIMIT 1
    ");

    return trim((string)($row['classe'] ?? ''));
}

function ticketEventiVisibilityColumn(string $ruolo): string
{
    return match ($ruolo) {
        'studente' => 'visibile_studenti',
        'docente' => 'visibile_docenti',
        'personale-ata' => 'visibile_ata',
        default => '',
    };
}

function ticketEventiRoleLabel(string $ruolo): string
{
    return match ($ruolo) {
        'studente' => 'Studenti',
        'docente' => 'Docenti',
        'personale-ata' => 'ATA',
        default => $ruolo,
    };
}

function ticketEventiNormalizeDateTime(?string $value): ?string
{
    $dt = ticketEventiDateTime($value);
    if (!$dt) {
        return null;
    }

    return $dt->format('Y-m-d H:i:s');
}

function ticketEventiFormatDateTime(?string $value): string
{
    $dt = ticketEventiDateTime($value);
    if (!$dt) {
        return trim((string)$value);
    }

    return $dt->format('d/m/Y H:i');
}

function ticketEventiFormatDateTimeInput(?string $value): string
{
    $dt = ticketEventiDateTime($value);
    if (!$dt) {
        return '';
    }

    return $dt->format('Y-m-d\TH:i');
}

function ticketEventiBookingWindowLabel(array $evento): string
{
    $parts = [];

    if (!empty($evento['apertura_prenotazioni'])) {
        $parts[] = 'Apre: ' . ticketEventiFormatDateTime((string)$evento['apertura_prenotazioni']);
    }
    if (!empty($evento['chiusura_prenotazioni'])) {
        $parts[] = 'Chiude: ' . ticketEventiFormatDateTime((string)$evento['chiusura_prenotazioni']);
    }

    return implode(' | ', $parts);
}

function ticketEventiAllowedRolesLabel(array $evento): string
{
    $labels = [];

    if (!empty($evento['visibile_studenti'])) {
        $labels[] = 'Studenti';
    }
    if (!empty($evento['visibile_docenti'])) {
        $labels[] = 'Docenti';
    }
    if (!empty($evento['visibile_ata'])) {
        $labels[] = 'ATA';
    }

    return implode(', ', $labels);
}

function ticketEventiStatoLabel(string $stato): string
{
    return match ($stato) {
        'aperto' => 'Aperto',
        'chiuso' => 'Chiuso',
        default => 'Bozza',
    };
}

function ticketEventiSaveEvent(array $data, int $annoId, int $utenteId): array
{
    $titolo = trim((string)($data['titolo'] ?? ''));
    $descrizione = trim((string)($data['descrizione'] ?? ''));
    $luogo = trim((string)($data['luogo'] ?? ''));
    $dataEvento = ticketEventiNormalizeDateTime((string)($data['data_evento'] ?? ''));
    $apertura = ticketEventiNormalizeDateTime((string)($data['apertura_prenotazioni'] ?? ''));
    $chiusura = ticketEventiNormalizeDateTime((string)($data['chiusura_prenotazioni'] ?? ''));
    $maxPerUtente = max(1, (int)($data['max_posti_per_utente'] ?? 1));
    $maxTotali = max(0, (int)($data['max_posti_totali'] ?? 0));
    $visibileStudenti = !empty($data['visibile_studenti']) ? 1 : 0;
    $visibileDocenti = !empty($data['visibile_docenti']) ? 1 : 0;
    $visibileAta = !empty($data['visibile_ata']) ? 1 : 0;
    $stato = (string)($data['stato'] ?? 'bozza');

    if ($titolo === '') {
        return ['ok' => false, 'message' => 'Inserisci il titolo dell\'evento.'];
    }

    if ($dataEvento === null) {
        return ['ok' => false, 'message' => 'Inserisci una data evento valida.'];
    }

    if ($apertura !== null && $chiusura !== null && ticketEventiDateTime($apertura) > ticketEventiDateTime($chiusura)) {
        return ['ok' => false, 'message' => 'La chiusura prenotazioni deve essere successiva all\'apertura.'];
    }

    if ($chiusura !== null && ticketEventiDateTime($chiusura) > ticketEventiDateTime($dataEvento)) {
        return ['ok' => false, 'message' => 'La chiusura prenotazioni non puo superare la data dell\'evento.'];
    }

    if (!in_array($stato, ['bozza', 'aperto', 'chiuso'], true)) {
        $stato = 'bozza';
    }

    if ($visibileStudenti === 0 && $visibileDocenti === 0 && $visibileAta === 0) {
        return ['ok' => false, 'message' => 'Seleziona almeno un tipo di utente abilitato alla prenotazione.'];
    }

    dbExec("
        INSERT INTO ticket_evento (
            anno_scolastico_id,
            titolo,
            descrizione,
            luogo,
            data_evento,
            apertura_prenotazioni,
            chiusura_prenotazioni,
            max_posti_per_utente,
            max_posti_totali,
            visibile_studenti,
            visibile_docenti,
            visibile_ata,
            stato,
            creato_da_utente_id,
            aggiornato_da_utente_id
        ) VALUES (
            " . dbI($annoId) . ",
            " . dbQ($titolo) . ",
            " . dbQ($descrizione) . ",
            " . dbQ($luogo) . ",
            " . dbQ($dataEvento) . ",
            " . dbQ($apertura) . ",
            " . dbQ($chiusura) . ",
            " . dbI($maxPerUtente) . ",
            " . ($maxTotali > 0 ? dbI($maxTotali) : 'NULL') . ",
            " . dbI($visibileStudenti) . ",
            " . dbI($visibileDocenti) . ",
            " . dbI($visibileAta) . ",
            " . dbQ($stato) . ",
            " . ($utenteId > 0 ? dbI($utenteId) : 'NULL') . ",
            " . ($utenteId > 0 ? dbI($utenteId) : 'NULL') . "
        )
    ");

    return ['ok' => true, 'message' => 'Evento creato con successo.'];
}

function ticketEventiUpdateEvent(int $eventoId, array $data, int $annoId, int $utenteId): array
{
    $evento = ticketEventiGetEventById($eventoId, $annoId);
    if (!$evento) {
        return ['ok' => false, 'message' => 'Evento non trovato.'];
    }

    $titolo = trim((string)($data['titolo'] ?? ''));
    $descrizione = trim((string)($data['descrizione'] ?? ''));
    $luogo = trim((string)($data['luogo'] ?? ''));
    $dataEvento = ticketEventiNormalizeDateTime((string)($data['data_evento'] ?? ''));
    $apertura = ticketEventiNormalizeDateTime((string)($data['apertura_prenotazioni'] ?? ''));
    $chiusura = ticketEventiNormalizeDateTime((string)($data['chiusura_prenotazioni'] ?? ''));
    $maxPerUtente = max(1, (int)($data['max_posti_per_utente'] ?? 1));
    $maxTotali = max(0, (int)($data['max_posti_totali'] ?? 0));
    $visibileStudenti = !empty($data['visibile_studenti']) ? 1 : 0;
    $visibileDocenti = !empty($data['visibile_docenti']) ? 1 : 0;
    $visibileAta = !empty($data['visibile_ata']) ? 1 : 0;
    $stato = (string)($data['stato'] ?? 'bozza');

    if ($titolo === '') {
        return ['ok' => false, 'message' => 'Inserisci il titolo dell\'evento.'];
    }

    if ($dataEvento === null) {
        return ['ok' => false, 'message' => 'Inserisci una data evento valida.'];
    }

    if ($apertura !== null && $chiusura !== null && ticketEventiDateTime($apertura) > ticketEventiDateTime($chiusura)) {
        return ['ok' => false, 'message' => 'La chiusura prenotazioni deve essere successiva all\'apertura.'];
    }

    if ($chiusura !== null && ticketEventiDateTime($chiusura) > ticketEventiDateTime($dataEvento)) {
        return ['ok' => false, 'message' => 'La chiusura prenotazioni non puo superare la data dell\'evento.'];
    }

    if (!in_array($stato, ['bozza', 'aperto', 'chiuso'], true)) {
        $stato = 'bozza';
    }

    if ($visibileStudenti === 0 && $visibileDocenti === 0 && $visibileAta === 0) {
        return ['ok' => false, 'message' => 'Seleziona almeno un tipo di utente abilitato alla prenotazione.'];
    }

    dbExec("
        UPDATE ticket_evento
        SET titolo = " . dbQ($titolo) . ",
            descrizione = " . dbQ($descrizione) . ",
            luogo = " . dbQ($luogo) . ",
            data_evento = " . dbQ($dataEvento) . ",
            apertura_prenotazioni = " . dbQ($apertura) . ",
            chiusura_prenotazioni = " . dbQ($chiusura) . ",
            max_posti_per_utente = " . dbI($maxPerUtente) . ",
            max_posti_totali = " . ($maxTotali > 0 ? dbI($maxTotali) : 'NULL') . ",
            visibile_studenti = " . dbI($visibileStudenti) . ",
            visibile_docenti = " . dbI($visibileDocenti) . ",
            visibile_ata = " . dbI($visibileAta) . ",
            stato = " . dbQ($stato) . ",
            aggiornato_da_utente_id = " . ($utenteId > 0 ? dbI($utenteId) : 'NULL') . ",
            updated_at = NOW()
        WHERE id = " . dbI($eventoId) . "
          AND anno_scolastico_id = " . dbI($annoId) . "
        LIMIT 1
    ");

    return ['ok' => true, 'message' => 'Evento aggiornato con successo.'];
}

function ticketEventiUpdateEventStatus(int $eventoId, int $annoId, string $stato, int $utenteId): array
{
    if (!in_array($stato, ['bozza', 'aperto', 'chiuso'], true)) {
        return ['ok' => false, 'message' => 'Stato evento non valido.'];
    }

    $evento = ticketEventiGetEventById($eventoId, $annoId);
    if (!$evento) {
        return ['ok' => false, 'message' => 'Evento non trovato.'];
    }

    dbExec("
        UPDATE ticket_evento
        SET stato = " . dbQ($stato) . ",
            aggiornato_da_utente_id = " . ($utenteId > 0 ? dbI($utenteId) : 'NULL') . ",
            updated_at = NOW()
        WHERE id = " . dbI($eventoId) . "
          AND anno_scolastico_id = " . dbI($annoId) . "
        LIMIT 1
    ");

    return ['ok' => true, 'message' => 'Stato evento aggiornato.'];
}

function ticketEventiDeleteEvent(int $eventoId, int $annoId): array
{
    $evento = ticketEventiGetEventById($eventoId, $annoId);
    if (!$evento) {
        return ['ok' => false, 'message' => 'Evento non trovato.'];
    }

    dbExec("
        DELETE FROM ticket_evento
        WHERE id = " . dbI($eventoId) . "
          AND anno_scolastico_id = " . dbI($annoId) . "
        LIMIT 1
    ");

    return ['ok' => true, 'message' => 'Evento eliminato con successo.'];
}

function ticketEventiGetEventsForAdmin(int $annoId): array
{
    return dbGetAll("
        SELECT
            e.*,
            COALESCE(SUM(CASE WHEN p.stato = 'attiva' THEN p.numero_posti ELSE 0 END), 0) AS posti_prenotati,
            COALESCE(COUNT(CASE WHEN p.stato = 'attiva' THEN 1 END), 0) AS prenotazioni_attive
        FROM ticket_evento e
        LEFT JOIN ticket_prenotazione p ON p.evento_id = e.id
        WHERE e.anno_scolastico_id = " . dbI($annoId) . "
        GROUP BY e.id
        ORDER BY e.data_evento DESC, e.id DESC
    ") ?: [];
}

function ticketEventiGetReservationsForEvent(int $eventoId): array
{
    return dbGetAll("
        SELECT *
        FROM ticket_prenotazione
        WHERE evento_id = " . dbI($eventoId) . "
          AND stato = 'attiva'
        ORDER BY ruolo ASC, nominativo ASC
    ") ?: [];
}

function ticketEventiGetReservationsForAllocation(int $eventoId, ?int $annoId = null): array
{
    global $__anno_scolastico_corrente_id;

    $annoId = $annoId !== null ? $annoId : (int)$__anno_scolastico_corrente_id;

    return dbGetAll("
        SELECT
            p.*,
            COALESCE(pd.classe_di_concorso, '') AS classe_di_concorso,
            COALESCE(UPPER(TRIM(dep.sigla)), '') AS dipartimento_sigla,
            COALESCE(dep.nome, '') AS dipartimento_nome
        FROM ticket_prenotazione p
        LEFT JOIN profilo_docente pd
            ON pd.id = (
                SELECT MAX(pd2.id)
                FROM profilo_docente pd2
                WHERE pd2.docente_id = p.riferimento_id
                  AND pd2.anno_scolastico_id = " . dbI($annoId) . "
            )
        LEFT JOIN classe_concorso_dipartimento ccd
            ON TRIM(ccd.classe_di_concorso) = TRIM(pd.classe_di_concorso)
        LEFT JOIN dipartimenti dep
            ON dep.id = ccd.dipartimento_id
        WHERE p.evento_id = " . dbI($eventoId) . "
          AND p.stato = 'attiva'
        ORDER BY
            CASE
                WHEN p.ruolo = 'docente' THEN 1
                WHEN p.ruolo = 'personale-ata' THEN 2
                ELSE 3
            END,
            nominativo ASC
    ") ?: [];
}

function ticketEventiGetVisibleEventsForActor(array $actor, int $annoId): array
{
    $visibilityColumn = ticketEventiVisibilityColumn((string)$actor['ruolo']);
    if ($visibilityColumn === '') {
        return [];
    }

    return dbGetAll("
        SELECT
            e.*,
            p.id AS prenotazione_id,
            p.numero_posti AS prenotazione_numero_posti,
            p.note AS prenotazione_note,
            p.stato AS prenotazione_stato,
            COALESCE((
                SELECT SUM(px.numero_posti)
                FROM ticket_prenotazione px
                WHERE px.evento_id = e.id
                  AND px.stato = 'attiva'
            ), 0) AS posti_prenotati
        FROM ticket_evento e
        LEFT JOIN ticket_prenotazione p
            ON p.evento_id = e.id
           AND p.ruolo = " . dbQ((string)$actor['ruolo']) . "
           AND p.riferimento_id = " . dbI((int)$actor['riferimento_id']) . "
        WHERE e.anno_scolastico_id = " . dbI($annoId) . "
          AND e.$visibilityColumn = 1
        ORDER BY
            CASE WHEN e.stato = 'aperto' THEN 0 WHEN e.stato = 'bozza' THEN 1 ELSE 2 END,
            e.data_evento ASC,
            e.id DESC
    ") ?: [];
}

function ticketEventiIsBookable(array $evento): bool
{
    if (($evento['stato'] ?? '') !== 'aperto') {
        return false;
    }

    $now = ticketEventiNow();
    $apertura = !empty($evento['apertura_prenotazioni']) ? ticketEventiDateTime((string)$evento['apertura_prenotazioni']) : null;
    $chiusura = !empty($evento['chiusura_prenotazioni']) ? ticketEventiDateTime((string)$evento['chiusura_prenotazioni']) : null;
    $dataEvento = !empty($evento['data_evento']) ? ticketEventiDateTime((string)$evento['data_evento']) : null;

    if ($apertura !== null && $now < $apertura) {
        return false;
    }

    if ($chiusura !== null && $now > $chiusura) {
        return false;
    }

    if ($dataEvento !== null && $now > $dataEvento) {
        return false;
    }

    return true;
}

function ticketEventiIsPast(array $evento): bool
{
    $dataEvento = !empty($evento['data_evento']) ? ticketEventiDateTime((string)$evento['data_evento']) : null;
    if ($dataEvento === null) {
        return false;
    }

    return ticketEventiNow() > $dataEvento;
}

function ticketEventiGetEventById(int $eventoId, int $annoId): ?array
{
    return dbGetFirst("
        SELECT
            e.*,
            COALESCE((
                SELECT SUM(p.numero_posti)
                FROM ticket_prenotazione p
                WHERE p.evento_id = e.id
                  AND p.stato = 'attiva'
            ), 0) AS posti_prenotati
        FROM ticket_evento e
        WHERE e.id = " . dbI($eventoId) . "
          AND e.anno_scolastico_id = " . dbI($annoId) . "
        LIMIT 1
    ");
}

function ticketEventiUpsertReservation(array $actor, array $evento, int $numeroPosti, string $note, int $annoId): array
{
    $numeroPosti = max(1, $numeroPosti);
    $maxPerUtente = max(1, (int)($evento['max_posti_per_utente'] ?? 1));
    if ($numeroPosti > $maxPerUtente) {
        return ['ok' => false, 'message' => 'Numero posti superiore al massimo consentito per utente.'];
    }

    if (!ticketEventiIsBookable($evento)) {
        return ['ok' => false, 'message' => 'Le prenotazioni per questo evento non sono aperte.'];
    }

    $visibilityColumn = ticketEventiVisibilityColumn((string)$actor['ruolo']);
    if ($visibilityColumn === '' || empty($evento[$visibilityColumn])) {
        return ['ok' => false, 'message' => 'Questo evento non è prenotabile per il tuo profilo.'];
    }

    $current = dbGetFirst("
        SELECT *
        FROM ticket_prenotazione
        WHERE evento_id = " . dbI((int)$evento['id']) . "
          AND ruolo = " . dbQ((string)$actor['ruolo']) . "
          AND riferimento_id = " . dbI((int)$actor['riferimento_id']) . "
        LIMIT 1
    ");

    $postiGiaPrenotati = (int)($evento['posti_prenotati'] ?? 0);
    $postiSenzaCorrente = $postiGiaPrenotati - (int)($current['numero_posti'] ?? 0);
    $maxTotali = (int)($evento['max_posti_totali'] ?? 0);

    if ($maxTotali > 0 && ($postiSenzaCorrente + $numeroPosti) > $maxTotali) {
        return ['ok' => false, 'message' => 'Non ci sono abbastanza posti disponibili per questa richiesta.'];
    }

    if ($current) {
        dbExec("
            UPDATE ticket_prenotazione
            SET numero_posti = " . dbI($numeroPosti) . ",
                note = " . dbQ($note) . ",
                nominativo = " . dbQ((string)$actor['nominativo']) . ",
                email = " . dbQ((string)$actor['email']) . ",
                classe_label = " . dbQ((string)$actor['classe_label']) . ",
                stato = 'attiva',
                anno_scolastico_id = " . dbI($annoId) . ",
                updated_at = NOW()
            WHERE id = " . dbI((int)$current['id']) . "
        ");

        return ['ok' => true, 'message' => 'Prenotazione aggiornata con successo.'];
    }

    dbExec("
        INSERT INTO ticket_prenotazione (
            evento_id,
            anno_scolastico_id,
            utente_id,
            ruolo,
            riferimento_id,
            nominativo,
            email,
            classe_label,
            numero_posti,
            note,
            stato
        ) VALUES (
            " . dbI((int)$evento['id']) . ",
            " . dbI($annoId) . ",
            " . dbI((int)($actor['utente_id'] ?? -1)) . ",
            " . dbQ((string)$actor['ruolo']) . ",
            " . dbI((int)$actor['riferimento_id']) . ",
            " . dbQ((string)$actor['nominativo']) . ",
            " . dbQ((string)$actor['email']) . ",
            " . dbQ((string)$actor['classe_label']) . ",
            " . dbI($numeroPosti) . ",
            " . dbQ($note) . ",
            'attiva'
        )
    ");

    return ['ok' => true, 'message' => 'Prenotazione registrata con successo.'];
}

function ticketEventiCancelReservation(array $actor, int $eventoId): array
{
    $current = dbGetFirst("
        SELECT *
        FROM ticket_prenotazione
        WHERE evento_id = " . dbI($eventoId) . "
          AND ruolo = " . dbQ((string)$actor['ruolo']) . "
          AND riferimento_id = " . dbI((int)$actor['riferimento_id']) . "
        LIMIT 1
    ");

    if (!$current) {
        return ['ok' => false, 'message' => 'Prenotazione non trovata.'];
    }

    dbExec("DELETE FROM ticket_prenotazione WHERE id = " . dbI((int)$current['id']));
    return ['ok' => true, 'message' => 'Prenotazione annullata.'];
}
