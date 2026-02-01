<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/send-mail.php';
require_once '../common/mail-ui.php';

ruoloRichiesto('studente', 'segreteria-didattica', 'dirigente');

// -------------------------------
// Link Google Calendar
// -------------------------------
$linkCalendar = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
    . '&dates=' . $datetime_sportello . 'Z%2F' . $datetime_fine_sportello . 'Z'
    . '&details=' . urlencode(
        "Attività $categoria - materia $materia - Aula $luogo"
    )
    . '&location=' . urlencode(
        'Istituto Tecnico Tecnologico Buonarroti, Via Brigata Acqui, 15, 38122 Trento TN, Italia'
    )
    . '&text=' . urlencode(
        "Attività $categoria - materia $materia - Aula $luogo"
    );

// -------------------------------
// Mail HTML (mail-ui)
// -------------------------------
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
    'studente'   // 🎨 tema studente
);

// -------------------------------
// Invio mail
// -------------------------------
$to = $studente_email;
$toName = $studente_nome . " " . $studente_cognome;
$mailsubject = 'GestOre - Iscrizione attività ' . $categoria . ' - materia ' . $materia;

info("Invio mail iscrizione allo studente: $to ($toName)");

if ($email_genitori != "") {
    sendMailCC($to, $toName, $email_genitori, $nominativo_genitori, $mailsubject, $body);
    info("Mail iscrizione inviata anche ai genitori: $email_genitori");
} else {
    sendMail($to, $toName, $mailsubject, $body);
    info("Mail iscrizione inviata allo studente: $to");
}

?>
