<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+
 */

require_once '../common/checkSession.php';
require_once '../common/send-mail.php';
require_once '../common/mail-ui.php';

$docente_id = $_POST["docente_id"] ?? '';
$oggetto_modifica = $_POST["oggetto_modifica"] ?? '';

$docente = dbGetFirst("SELECT * FROM docente WHERE docente.id = '" . addslashes($docente_id) . "'");

$connection = 'http';
if ($__settings->system->https) {
    $connection = 'https';
}
$url = "$connection://$_SERVER[HTTP_HOST]" . $__application_base_path . '/index.php';

$nomeDoc = (string)($docente['nome'] ?? '');
$cognomeDoc = (string)($docente['cognome'] ?? '');
$to = (string)($docente['email'] ?? '');
$toName = trim($nomeDoc . " " . $cognomeDoc);

$oggettoSafe = htmlspecialchars((string)$oggetto_modifica, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$urlSafe = htmlspecialchars((string)$url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$title = "RICHIESTA REVISIONATA";
$intro = "Aggiornamento sulla richiesta di <b>{$oggettoSafe}</b>.";

$content = '
  <div style="margin:0 0 12px 0;">
    ' . badge('ESITO DISPONIBILE', '#dbeafe', '#1d4ed8') . '
  </div>

  <div style="font-size:13.5px;line-height:1.6;color:#374151;margin:0 0 14px 0;">
    La tua richiesta di <b>' . $oggettoSafe . '</b> è stata revisionata e la <b>versione approvata</b> è ora disponibile su GestOre.
  </div>

  <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:12px 12px;margin:0 0 14px 0;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
      ' . kvRow('Oggetto', (string)$oggetto_modifica) . '
      ' . kvRow('Azione consigliata', 'Apri GestOre e verifica i dettagli') . '
    </table>
  </div>

  <div style="text-align:center;margin:18px 0 6px 0;">
    <a href="' . $urlSafe . '"
       style="display:inline-block;background:#1d4ed8;color:#ffffff;text-decoration:none;
              padding:12px 16px;border-radius:12px;font-weight:800;font-size:14px;">
      Apri GestOre
    </a>
  </div>

  <div style="text-align:center;font-size:12.5px;color:#6b7280;line-height:1.4;">
    Se il pulsante non funziona, copia e incolla questo link nel browser:<br>
    <span style="word-break:break-all;">' . $urlSafe . '</span>
  </div>

  <div style="margin-top:14px;font-size:13px;line-height:1.55;color:#374151;">
    In caso di dubbi, puoi rivolgerti al <b>Dirigente Scolastico</b>.
  </div>
';

$footer = htmlspecialchars((string)$__settings->name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . ' – '
        . htmlspecialchars((string)$__settings->local->nomeIstituto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$html_msg = mailWrap($title, $toName, $intro, $content, $footer, 'docente');

info("Invio mail al docente: " . $to . " " . $toName);
echo "Inviata mail al docente: " . $to . " " . $toName . " - ";

$mailsubject = 'GestOre - Aggiornamento attività previste: ' . $oggetto_modifica;
sendMail($to, $toName, $mailsubject, $html_msg);
info("inviata mail " . $mailsubject);

?>
