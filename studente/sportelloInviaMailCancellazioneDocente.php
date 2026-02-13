<?php

/**
 * sportelloInviaMailCancellazioneDocente.php
 *
 * Mail al docente quando si cancella l'ULTIMO studente iscritto:
 * -> in mancanza di altre iscrizioni l'attività viene annullata.
 *
 * UI coerente con GestOre: usa mail-ui.php (mailWrap, badge, kvRow)
 *
 * PRECONDIZIONI (variabili già disponibili nel contesto che include questo file):
 * - $categoria, $materia, $luogo, $data, $ora
 * - $docente_email, $docente_nome, $docente_cognome
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
    warning("sportelloInviaMailCancellazioneDocente: docente_email vuota -> skip");
    return;
}

// -------------------------------
// Mail UI (coerente con GestOre)
// -------------------------------
$title = "ANNULLAMENTO ATTIVITÀ";
$intro = "L’attività è stata <b>annullata automaticamente</b> perché l’ultimo studente iscritto si è cancellato.";

$content = '
    <div style="margin:0 0 12px 0;">
        ' . badge('ANNULLATO – 0 iscritti', '#fee2e2', '#7f1d1d') . '
    </div>

    <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:14px;padding:12px 12px;margin:0 0 14px 0;">
        <div style="font-weight:900;font-size:14px;margin-bottom:6px;color:#9a3412;">
            Motivo annullamento
        </div>
        <div style="font-size:13.5px;line-height:1.55;color:#7c2d12;">
            Non risultano più iscrizioni attive per questa attività.
        </div>
    </div>

    <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:12px 12px;margin:0 0 14px 0;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
            ' . kvRow('Attività', ($categoria !== '' ? strtoupper($categoria) : '—')) . '
            ' . kvRow('Materia', ($materia !== '' ? $materia : '—')) . '
            ' . kvRow('Data', ($data !== '' ? $data : '—')) . '
            ' . kvRow('Ora', ($ora !== '' ? $ora : '—')) . '
            ' . kvRow('Aula', ($luogo !== '' ? $luogo : '—')) . '
        </table>
    </div>
';

$footer = "Se dovesse iscriversi nuovamente uno studente, riceverai una nuova notifica.";

// tipo destinatario 'docente' per eventuali variazioni di stile se previste in mailWrap
$body = mailWrap($title, $toName, $intro, $content, $footer, 'docente');
$subject = 'GestOre - Annullamento attività ' . ($categoria !== '' ? $categoria : '') . ' - materia ' . ($materia !== '' ? $materia : '');

// -------------------------------
// Send
// -------------------------------
info("Invio mail annullamento (ultimo studente cancellato) al docente: $to $toName");
sendMail($to, $toName, $subject, $body);

info("Inviata mail annullamento al docente (ultimo studente cancellato) - email: " . $docente_email);

?>
