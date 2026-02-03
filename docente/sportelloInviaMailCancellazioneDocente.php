<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/send-mail.php';
require_once '../common/mail-ui.php';

ruoloRichiesto('docente', 'segreteria-didattica', 'segreteria-docenti', 'dirigente');

/**
 * True se questo file è eseguito direttamente (URL), false se incluso via require/include.
 */
$isStandalone = (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__));

/**
 * In modalità include: NON devo rompere la risposta JSON del chiamante.
 */
function _silentFail($msg, $extra = [])
{
    warning("[sportelloInviaMailCancellazioneDocente] " . $msg . (empty($extra) ? "" : " | " . json_encode($extra, JSON_UNESCAPED_UNICODE)));
    return false;
}
function _silentOk($msg, $extra = [])
{
    info("[sportelloInviaMailCancellazioneDocente] " . $msg . (empty($extra) ? "" : " | " . json_encode($extra, JSON_UNESCAPED_UNICODE)));
    return true;
}

/**
 * Utility: normalizza data in dd/mm/yyyy per subject/body.
 */
function _normDateIt($data)
{
    $data = trim((string)$data);
    if ($data === '') return '';

    // YYYY-mm-dd
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $data)) {
        $p = explode('-', substr($data, 0, 10));
        return $p[2] . '/' . $p[1] . '/' . $p[0];
    }
    // dd-mm-YYYY
    if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $data)) {
        $p = explode('-', $data);
        return $p[0] . '/' . $p[1] . '/' . $p[2];
    }
    // dd/mm/YYYY già ok o altro: lascio
    return $data;
}

/**
 * ===== 1) Recupero parametri da:
 * - variabili già definite nello scope del chiamante
 * - oppure $_POST
 */
$sportello_id = 0;

// priorità: variabili già presenti (quando incluso via require)
if (isset($sportello_id) && (int)$sportello_id > 0) {
    $sportello_id = (int)$sportello_id;
} elseif (isset($id) && (int)$id > 0) {
    $sportello_id = (int)$id;
} else {
    // fallback POST
    $sportello_id = (int)($_POST['sportello_id'] ?? $_POST['id'] ?? 0);
}

if ($sportello_id <= 0) {
    if ($isStandalone) {
        http_response_code(400);
        echo "Parametri mancanti: sportello_id/id";
        exit;
    }
    _silentFail("Manca sportello_id (né variabile né POST)");
    return; // IMPORTANTISSIMO quando incluso
}

/**
 * ===== 2) Recupero dati sportello dal DB (fonte di verità)
 * così funziona anche se non mi passano nulla via POST.
 */
$s = dbGetFirst("
    SELECT
        id,
        docente_id,
        materia_id,
        data,
        ora,
        categoria,
        luogo
    FROM sportello
    WHERE id = " . (int)$sportello_id . "
    LIMIT 1
");

if (!$s) {
    if ($isStandalone) {
        http_response_code(404);
        echo "Sportello non trovato (id=$sportello_id)";
        exit;
    }
    _silentFail("Sportello non trovato", ['id' => $sportello_id]);
    return;
}

$docente_id = (int)($s['docente_id'] ?? 0);
$materia_id = (int)($s['materia_id'] ?? 0);
$data       = (string)($s['data'] ?? '');
$ora        = (string)($s['ora'] ?? '');
$categoria  = (string)($s['categoria'] ?? 'sportello');
$luogo      = (string)($s['luogo'] ?? '');

/**
 * ===== 3) Docente e materia
 */
if ($docente_id <= 0) {
    // sportello in bozza/non assegnato: non ha senso mandare mail al docente
    if ($isStandalone) {
        http_response_code(400);
        echo "Sportello senza docente assegnato (docente_id=0).";
        exit;
    }
    _silentFail("Sportello senza docente assegnato", ['id' => $sportello_id]);
    return;
}

$doc = dbGetFirst("SELECT cognome, nome, email FROM docente WHERE id = " . (int)$docente_id . " LIMIT 1");
$docente_cognome = (string)($doc['cognome'] ?? '');
$docente_nome    = (string)($doc['nome'] ?? '');
$docente_email   = (string)($doc['email'] ?? '');

if (trim($docente_email) === '') {
    if ($isStandalone) {
        http_response_code(400);
        echo "Email docente non trovata (docente_id=$docente_id).";
        exit;
    }
    _silentFail("Email docente non trovata", ['docente_id' => $docente_id, 'sportello_id' => $sportello_id]);
    return;
}

$materia = '';
if ($materia_id > 0) {
    $materia = (string)dbGetValue("SELECT nome FROM materia WHERE id = " . (int)$materia_id . " LIMIT 1");
}
if (trim($materia) === '') $materia = '—';

$to     = $docente_email;
$toName = trim($docente_nome . ' ' . $docente_cognome);

$dataIt = _normDateIt($data);

/**
 * ===== 4) Email (solo docente) - messaggio semplice “annullato”
 */
$title = "ANNULLAMENTO ATTIVITÀ<br>" . strtoupper($categoria);
$intro = "Notifica automatica: l’attività indicata sotto è stata annullata.";

$content = '
  <div style="margin:0 0 12px 0;">
    ' . badge('ANNULLATO', '#fee2e2', '#7f1d1d') . '
  </div>

  <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:12px 12px;margin:0 0 14px 0;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
      ' . kvRow('Attività', htmlspecialchars($categoria, ENT_QUOTES, 'UTF-8')) . '
      ' . kvRow('Materia', htmlspecialchars($materia, ENT_QUOTES, 'UTF-8')) . '
      ' . kvRow('Data', htmlspecialchars($dataIt, ENT_QUOTES, 'UTF-8')) . '
      ' . kvRow('Ora', htmlspecialchars($ora, ENT_QUOTES, 'UTF-8')) . '
      ' . kvRow('Aula', htmlspecialchars(($luogo !== '' ? $luogo : '—'), ENT_QUOTES, 'UTF-8')) . '
      ' . kvRow('ID Sportello', (string)$sportello_id) . '
    </table>
  </div>

  <div style="font-size:13.5px;line-height:1.55;color:#374151;">
    Questa email è stata inviata automaticamente da GestOre.
  </div>
';

$footer = "Messaggio automatico da GestOre – annullamento attività.";
$body   = mailWrap($title, $toName, $intro, $content, $footer, 'annullamento');

$subject = "GestOre - Annullamento attività ($categoria) - $materia - $dataIt $ora";

info("Invio mail annullamento al docente: $to ($toName) sportello_id=$sportello_id");
sendMail($to, $toName, $subject, $body);
info("Inviata mail annullamento sportello id=$sportello_id a docente_id=$docente_id");

/**
 * ===== 5) Output
 * - standalone: rispondo
 * - include: NON devo echo/exit
 */
if ($isStandalone) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "OK";
    exit;
}

_silentOk("Mail inviata", ['sportello_id' => $sportello_id, 'docente_id' => $docente_id]);
return;
