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
// Mail HTML (mail-ui)
// -------------------------------
$title = "CANCELLAZIONE ISCRIZIONE";
$intro = "Conferma: la tua iscrizione all’attività è stata cancellata correttamente.";

$content = '
  <div style="margin:0 0 12px 0;">
    ' . badge('CANCELLAZIONE CONFERMATA', '#ffedd5', '#9a3412') . '
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

  <div style="font-size:13.5px;line-height:1.55;color:#374151;">
    Se vuoi, puoi prenotarti a un’altra attività disponibile in <b>GestOre</b>.
  </div>
';

$footer = "Messaggio automatico da GestOre – {$__settings->local->nomeIstituto}";

$to = $studente_email;
$toName = $studente_nome . " " . $studente_cognome;

$toCC = $email_genitori;
$toCCName = $nominativo_genitori;

$mailsubject = 'GestOre - Annullamento iscrizione ' . $categoria . ' - materia ' . $materia;

$body = mailWrap(
  $title,
  strtoupper($studente_cognome) . " " . strtoupper($studente_nome),
  $intro,
  $content,
  $footer,
  'warning' // 🎨 cancellazione volontaria studente (arancione). Se vuoi rosso: 'annullamento'
);

info("Invio mail cancellazione iscrizione allo studente: $to ($toName) con CC a: $toCC $toCCName");

if ($toCC != "") {
  sendMailCC($to, $toName, $toCC, $toCCName, $mailsubject, $body);
  info("Mail cancellazione iscrizione inviata anche ai genitori - email: $toCC");
} else {
  sendMail($to, $toName, $mailsubject, $body);
  info("Mail cancellazione iscrizione inviata allo studente - email: $to");
}

info("Mail cancellazione iscrizione inviata allo studente - email: $to");

?>
