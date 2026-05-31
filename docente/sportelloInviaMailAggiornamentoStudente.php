<?php

/**
 * Invia agli studenti iscritti la notifica di variazione data/ora/aula sportello.
 * Usabile sia da docente/sportelloAggiorna.php sia da didattica/sportelloSave.php.
 */

require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/../common/send-mail.php';
require_once __DIR__ . '/../common/mail-ui.php';

ruoloRichiesto('docente', 'segreteria-didattica', 'segreteria-docenti', 'dirigente');

if (!function_exists('_sportelloUpdateDateIt')) {
    function _sportelloUpdateDateIt($data): string
    {
        $data = trim((string)$data);
        if ($data === '') return '-';
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $data)) {
            $p = explode('-', substr($data, 0, 10));
            return $p[2] . '/' . $p[1] . '/' . $p[0];
        }
        return $data;
    }
}

$sportello_id = (int)($id ?? $_POST['sportello_id'] ?? $_POST['id'] ?? 0);
if ($sportello_id <= 0) {
    warning("sportelloInviaMailAggiornamentoStudente: sportello_id/id mancante");
    return;
}

$oldSportello = is_array($sportello_update_old ?? null) ? $sportello_update_old : [];

$s = dbGetFirst("
    SELECT
        s.id,
        s.materia_id,
        s.data,
        s.ora,
        s.categoria,
        s.luogo,
        s.cancellato,
        m.nome AS materia,
        d.cognome AS docente_cognome,
        d.nome AS docente_nome
    FROM sportello s
    LEFT JOIN materia m ON m.id = s.materia_id
    LEFT JOIN docente d ON d.id = s.docente_id
    WHERE s.id = " . (int)$sportello_id . "
    LIMIT 1
");

if (!$s) {
    warning("sportelloInviaMailAggiornamentoStudente: sportello non trovato id=$sportello_id");
    return;
}

if ((int)($s['cancellato'] ?? 0) === 1) {
    info("sportelloInviaMailAggiornamentoStudente: sportello cancellato, skip id=$sportello_id");
    return;
}

$oldData = (string)($oldSportello['data'] ?? '');
$oldOra = (string)($oldSportello['ora'] ?? '');
$oldLuogo = trim((string)($oldSportello['luogo'] ?? ''));

$newData = (string)($s['data'] ?? '');
$newOra = (string)($s['ora'] ?? '');
$newLuogo = trim((string)($s['luogo'] ?? ''));

$changed = ($oldData !== '' && $oldData !== $newData)
    || ($oldOra !== '' && $oldOra !== $newOra)
    || ($oldLuogo !== $newLuogo);

if (!$changed) {
    info("sportelloInviaMailAggiornamentoStudente: nessuna variazione data/ora/aula id=$sportello_id");
    return;
}

$studenti = dbGetAll("
    SELECT
        st.id AS studente_id,
        st.cognome AS studente_cognome,
        st.nome AS studente_nome,
        st.email AS studente_email
    FROM sportello_studente ss
    INNER JOIN studente st ON st.id = ss.studente_id
    WHERE ss.sportello_id = " . (int)$sportello_id . "
") ?: [];

if (empty($studenti)) {
    info("sportelloInviaMailAggiornamentoStudente: nessuno studente iscritto id=$sportello_id");
    return;
}

$categoria = (string)($s['categoria'] ?? 'sportello');
$materia = trim((string)($s['materia'] ?? ''));
if ($materia === '') $materia = '-';
$docente = strtoupper(trim((string)($s['docente_cognome'] ?? '') . ' ' . (string)($s['docente_nome'] ?? '')));
if ($docente === '') $docente = '-';

$oldDataIt = _sportelloUpdateDateIt($oldData);
$newDataIt = _sportelloUpdateDateIt($newData);
$oldAula = $oldLuogo !== '' ? $oldLuogo : '-';
$newAula = $newLuogo !== '' ? $newLuogo : '-';

foreach ($studenti as $row) {
    $studente_id = (int)($row['studente_id'] ?? 0);
    $studente_email = trim((string)($row['studente_email'] ?? ''));
    $studente_cognome = (string)($row['studente_cognome'] ?? '');
    $studente_nome = (string)($row['studente_nome'] ?? '');

    if ($studente_id <= 0 || $studente_email === '') {
        warning("sportelloInviaMailAggiornamentoStudente: studente_id/email non validi sportello_id=$sportello_id");
        continue;
    }

    $genitori = dbGetAll("
        SELECT g.cognome, g.nome, g.email
        FROM genitori g
        INNER JOIN genitori_studenti gs ON gs.id_genitore = g.id
        WHERE g.attivo = 1
          AND gs.id_studente = $studente_id
    ") ?: [];

    $email_genitori = "";
    $nominativo_genitori = "";
    foreach ($genitori as $genitore) {
        $mailG = trim((string)($genitore['email'] ?? ''));
        if ($mailG === '') continue;
        if ($email_genitori !== "") {
            $email_genitori .= ", ";
            $nominativo_genitori .= ", ";
        }
        $email_genitori .= $mailG;
        $nominativo_genitori .= trim((string)($genitore['cognome'] ?? '') . " " . (string)($genitore['nome'] ?? ''));
    }

    $title = "AGGIORNAMENTO SPORTELLO";
    $intro = "Notifica: lo sportello a cui sei iscritto è stato aggiornato.";

    $content = '
      <div style="margin:0 0 12px 0;">
        ' . badge('SPORTELLO AGGIORNATO', '#dbeafe', '#1e3a8a') . '
      </div>

      <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:12px 12px;margin:0 0 14px 0;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
          ' . kvRow('Attività', strtoupper($categoria)) . '
          ' . kvRow('Materia', $materia) . '
          ' . kvRow('Docente', $docente) . '
          ' . kvRow('Data precedente', $oldDataIt) . '
          ' . kvRow('Ora precedente', ($oldOra !== '' ? $oldOra : '-')) . '
          ' . kvRow('Aula precedente', $oldAula) . '
          ' . kvRow('Nuova data', $newDataIt) . '
          ' . kvRow('Nuova ora', ($newOra !== '' ? $newOra : '-')) . '
          ' . kvRow('Nuova aula', $newAula) . '
          ' . kvRow('ID Sportello', (string)$sportello_id) . '
        </table>
      </div>

      <div style="font-size:13.5px;line-height:1.55;color:#374151;">
        La tua iscrizione resta valida. Controlla i nuovi dettagli prima di presentarti allo sportello.
      </div>
    ';

    $footer = "Se hai dubbi, contatta il docente o consulta GestOre.";
    $body = mailWrap($title, strtoupper(trim($studente_cognome . " " . $studente_nome)), $intro, $content, $footer, 'docente');

    $to = $studente_email;
    $toName = trim($studente_nome . " " . $studente_cognome);
    $subject = 'GestOre - Aggiornamento sportello ' . $categoria . ' - materia ' . $materia;

    info("Invio mail aggiornamento sportello allo studente: $to ($toName) sportello_id=$sportello_id");
    if ($email_genitori !== "") {
        sendMailCC($to, $toName, $email_genitori, $nominativo_genitori, $subject, $body);
        info("Mail aggiornamento sportello inviata anche ai genitori: $email_genitori");
    } else {
        sendMail($to, $toName, $subject, $body);
    }
}

return;
