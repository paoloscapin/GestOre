<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';      // ✅ serve per fallback email da DB
require_once '../common/send-mail.php';
require_once '../common/mail-ui.php';

ruoloRichiesto('studente', 'segreteria-didattica', 'dirigente');

/**
 * =========================================================
 *  FALLBACK VARIABILI (il file può essere incluso con require)
 * =========================================================
 */

// se mancano le variabili attese, provo a rimediare senza “rompere” l’esecuzione
$studente_nome    = isset($studente_nome) ? (string)$studente_nome : '';
$studente_cognome = isset($studente_cognome) ? (string)$studente_cognome : '';
$studente_email   = isset($studente_email) ? trim((string)$studente_email) : '';

$docente_nome     = isset($docente_nome) ? (string)$docente_nome : '';
$docente_cognome  = isset($docente_cognome) ? (string)$docente_cognome : '';

$materia   = isset($materia) ? (string)$materia : '';
$categoria = isset($categoria) ? (string)$categoria : '';
$data      = isset($data) ? (string)$data : '';
$ora       = isset($ora) ? (string)$ora : '';
$luogo     = isset($luogo) ? (string)$luogo : '';

$datetime_sportello      = isset($datetime_sportello) ? (string)$datetime_sportello : '';
$datetime_fine_sportello = isset($datetime_fine_sportello) ? (string)$datetime_fine_sportello : '';

$email_genitori       = isset($email_genitori) ? trim((string)$email_genitori) : '';
$nominativo_genitori  = isset($nominativo_genitori) ? trim((string)$nominativo_genitori) : '';

/**
 * =========================================================
 *  Se email studente vuota -> ricalcolo da DB
 * =========================================================
 */
if ($studente_email === '' && !empty($__studente_id)) {
    try {
      debug("prima di dbfirst per email studente");
      debug("SELECT nome, cognome, email FROM studente WHERE id = " . (int)$__studente_id . " LIMIT 1");
        $row = dbGetFirst("SELECT nome, cognome, email FROM studente WHERE id = " . (int)$__studente_id . " LIMIT 1");
        if ($row) {
            if ($studente_nome === '')    $studente_nome = (string)($row['nome'] ?? '');
            if ($studente_cognome === '') $studente_cognome = (string)($row['cognome'] ?? '');
            $studente_email = trim((string)($row['email'] ?? ''));
        }
    } catch (Throwable $e) {
        warning("[sportelloMailIscrizioneStudente] fallback email studente EX: " . $e->getMessage());
    }
}

if ($studente_email === '') {
    // ✅ non invio: evito errore (to) e non sporco output
    warning("[sportelloMailIscrizioneStudente] email studente VUOTA: impossibile inviare. studente_id=" . (int)($__studente_id ?? 0));
    return;
}

/**
 * =========================================================
 *  Link Google Calendar
 * =========================================================
 */
$linkCalendar = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
    . '&dates=' . $datetime_sportello . 'Z%2F' . $datetime_fine_sportello . 'Z'
    . '&details=' . urlencode("Attività $categoria - materia $materia - Aula $luogo")
    . '&location=' . urlencode('Istituto Tecnico Tecnologico Buonarroti, Via Brigata Acqui, 15, 38122 Trento TN, Italia')
    . '&text=' . urlencode("Attività $categoria - materia $materia - Aula $luogo");

/**
 * =========================================================
 *  Mail HTML (mail-ui)
 * =========================================================
 */
$title = "ISCRIZIONE ATTIVITÀ";
$intro = "Conferma della tua iscrizione all’attività selezionata.";

$content = '
  <div style="margin:0 0 12px 0;">
    ' . badge('ISCRIZIONE CONFERMATA', '#dcfce7', '#14532d') . '
  </div>

  <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:12px 12px;margin:0 0 14px 0;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
      ' . kvRow('Attività', strtoupper((string)$categoria)) . '
      ' . kvRow('Materia', (string)$materia) . '
      ' . kvRow('Data', (string)$data) . '
      ' . kvRow('Ora', (string)$ora) . '
      ' . kvRow('Docente', strtoupper((string)$docente_cognome . " " . (string)$docente_nome)) . '
      ' . kvRow('Aula', ((string)$luogo !== '' ? (string)$luogo : '—')) . '
    </table>
  </div>

  <div style="font-size:13.5px;line-height:1.55;color:#374151;margin-bottom:10px;">
    Puoi aggiungere l’attività al tuo calendario personale:
  </div>

  <div style="text-align:center;margin-top:10px;">
    <a href="' . $linkCalendar . '" target="_blank"
       style="display:inline-block;padding:10px 18px;border-radius:10px;
              background:#0f766e;color:#ffffff;text-decoration:none;
              font-weight:800;font-size:14px;">
      📅 Aggiungi a Google Calendar
    </a>
  </div>
';

$footer = "Messaggio automatico da GestOre – {$__settings->local->nomeIstituto}";

$body = mailWrap(
    $title,
    strtoupper($studente_cognome) . " " . strtoupper($studente_nome),
    $intro,
    $content,
    $footer,
    'studente'
);

/**
 * =========================================================
 *  Invio mail
 * =========================================================
 */
$to = $studente_email;
$toName = trim($studente_nome . " " . $studente_cognome);
$mailsubject = 'GestOre - Iscrizione attività ' . $categoria . ' - materia ' . $materia;

info("Invio mail iscrizione allo studente: $to ($toName)");

try {
    if ($email_genitori !== "") {
        sendMailCC($to, $toName, $email_genitori, $nominativo_genitori, $mailsubject, $body);
        info("Mail iscrizione inviata anche ai genitori: $email_genitori");
    } else {
        sendMail($to, $toName, $mailsubject, $body);
        info("Mail iscrizione inviata allo studente: $to");
    }
} catch (Throwable $e) {
    warning("[sportelloMailIscrizioneStudente] invio mail EX: " . $e->getMessage());
    // non rilancio: non deve rompere la risposta JSON del chiamante
    return;
}

return;
