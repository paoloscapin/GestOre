<?php

/**
 * sportelloInviaMailIscrizioneDocente.php
 *
 * Mail al docente quando si iscrive il PRIMO studente allo sportello.
 * UI coerente: usa mail-ui.php (mailWrap, badge, kvRow)
 *
 * PRECONDIZIONI:
 * - Questo file viene incluso da un contesto dove esistono già queste variabili:
 *   $datetime_sportello, $datetime_fine_sportello (formato tipo YYYYMMDDTHHMMSS o simili, usati per Calendar)
 *   $categoria, $materia, $luogo, $data, $ora
 *   $docente_email, $docente_nome, $docente_cognome
 *
 * NOTE:
 * - Non usa più template_mail_iscrivi_docente.html
 */

require_once '../common/checkSession.php';
require_once '../common/send-mail.php';
require_once '../common/mail-ui.php';

ruoloRichiesto('studente', 'segreteria-didattica', 'dirigente');

// -------------------------------
// Helpers
// -------------------------------
function safe_str($v): string {
    return trim((string)$v);
}

// normalizza valori attesi
$categoria = safe_str($categoria ?? '');
$materia   = safe_str($materia ?? '');
$luogo     = safe_str($luogo ?? '');
$data      = safe_str($data ?? '');
$ora       = safe_str($ora ?? '');

$docente_email   = safe_str($docente_email ?? '');
$docente_nome    = safe_str($docente_nome ?? '');
$docente_cognome = safe_str($docente_cognome ?? '');

$to = $docente_email;
$toName = trim($docente_nome . " " . $docente_cognome);

if ($to === '') {
    warning("sportelloInviaMailIscrizioneDocente: docente_email vuota -> skip");
    return;
}

// -------------------------------
// Link Google Calendar
// -------------------------------
// Attenzione: qui mantengo la tua logica, ma pulisco l'urlencode (niente urlencode doppio su $luogo)
// $datetime_sportello / $datetime_fine_sportello li presumo già pronti per URL (tipicamente YYYYMMDDTHHMMSS)
$datetime_sportello      = safe_str($datetime_sportello ?? '');
$datetime_fine_sportello = safe_str($datetime_fine_sportello ?? '');

$linkCalendar = '';
if ($datetime_sportello !== '' && $datetime_fine_sportello !== '') {

    $text = "Attività $categoria - materia $materia" . ($luogo !== '' ? " - Aula $luogo" : "");
    $details = $text;

    $location = 'Istituto Tecnico Tecnologico Buonarroti, Via Brigata Acqui, 15, 38122 Trento TN, Italia';

    $linkCalendar =
        'https://calendar.google.com/calendar/render?action=TEMPLATE' .
        '&dates=' . rawurlencode($datetime_sportello . 'Z/' . $datetime_fine_sportello . 'Z') .
        '&text=' . rawurlencode($text) .
        '&details=' . rawurlencode($details) .
        '&location=' . rawurlencode($location);
}

// -------------------------------
// Mail UI (coerente con GestOre)
// -------------------------------
$title = "PRIMA ISCRIZIONE STUDENTE";
$intro = "È arrivata la <b>prima iscrizione</b> alla tua attività.";

$content = '
    <div style="margin:0 0 12px 0;">
        ' . badge('NUOVA ISCRIZIONE', '#dcfce7', '#14532d') . '
    </div>

    <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:12px 12px;margin:0 0 14px 0;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
            ' . kvRow('Attività', ($categoria !== '' ? $categoria : '—')) . '
            ' . kvRow('Materia', ($materia !== '' ? $materia : '—')) . '
            ' . kvRow('Data', ($data !== '' ? $data : '—')) . '
            ' . kvRow('Ora', ($ora !== '' ? $ora : '—')) . '
            ' . kvRow('Aula', ($luogo !== '' ? $luogo : '—')) . '
        </table>
    </div>
';

if ($linkCalendar !== '') {
    $content .= '
        <div style="margin:12px 0 0 0;padding:12px;border:1px solid #e5e7eb;border-radius:14px;background:#ffffff;">
            <div style="font-weight:800;color:#111827;margin-bottom:6px;">Promemoria</div>
            <div style="font-size:13.5px;line-height:1.55;color:#374151;">
                Puoi aggiungere l’evento al tuo calendario:
                <br>
                <a href="' . htmlspecialchars($linkCalendar, ENT_QUOTES, 'UTF-8') . '" target="_blank" style="color:#2563eb;text-decoration:none;font-weight:800;">
                    Apri Google Calendar
                </a>
            </div>
        </div>
    ';
}

$footer = "Gestisci o modifica lo sportello da <b>GestOre</b>.";

$body = mailWrap($title, $toName, $intro, $content, $footer, 'docente');
$subject = 'GestOre - Prima iscrizione studente - ' . ($categoria !== '' ? $categoria : 'attività') . ' - ' . ($materia !== '' ? $materia : '');

// -------------------------------
// Send
// -------------------------------
info("Invio mail al docente (prima iscrizione): $to $toName");
sendMail($to, $toName, $subject, $body);
info("Inviata mail al docente per prima iscrizione - email: $docente_email");

?>
