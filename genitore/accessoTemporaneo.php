<?php

/**
 * Accesso temporaneo genitori da link nelle comunicazioni carenze.
 */

require_once '../common/__Util.php';
require_once '../common/carenzeParentAccessLib.php';

function gatMessage(string $title, string $message): void
{
    echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</title>';
    echo '<style>body{margin:0;background:#f3f6f8;font-family:Arial,Helvetica,sans-serif;color:#1f2937;display:flex;min-height:100vh;align-items:center;justify-content:center}.box{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 12px 30px rgba(15,23,42,.12);max-width:520px;padding:28px;text-align:center}.box h1{font-size:22px;margin:0 0 12px}.box p{font-size:15px;line-height:1.5;color:#4b5563}.box a{display:inline-block;margin-top:14px;background:#0f766e;color:#fff;text-decoration:none;font-weight:700;border-radius:8px;padding:10px 14px}</style>';
    echo '</head><body><div class="box"><h1>' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h1><p>' . nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p><a href="../index.php">Torna al login</a></div></body></html>';
    exit;
}

function gatClientIp(): string
{
    foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'] as $key) {
        $raw = (string)($_SERVER[$key] ?? '');
        foreach (preg_split('/\s*,\s*/', $raw) ?: [] as $ip) {
            $ip = trim($ip);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return 'UNKNOWN';
}

$token = trim((string)($_GET['t'] ?? ''));
$access = carenzeParentAccessFindByToken($token);
if (!$access) {
    gatMessage('Link non valido o scaduto', 'Il link di accesso temporaneo non e valido oppure e scaduto. Contatta la segreteria didattica per ricevere una nuova comunicazione.');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_name('GESTORESESSID');
    @session_start();
}

$_SESSION['utente_id'] = -1;
$_SESSION['genitore_id'] = intval($access['id_genitore'] ?? 0);
$_SESSION['genitore_nome'] = (string)($access['nome'] ?? '');
$_SESSION['genitore_cognome'] = (string)($access['cognome'] ?? '');
$_SESSION['genitore_email'] = (string)($access['email'] ?? '');
$_SESSION['genitore_codice_fiscale'] = (string)($access['codice_fiscale'] ?? '');
$_SESSION['username'] = trim((string)($access['nome'] ?? '') . '.' . (string)($access['cognome'] ?? ''));
$_SESSION['utente_nome'] = (string)($access['nome'] ?? '');
$_SESSION['utente_cognome'] = (string)($access['cognome'] ?? '');
$_SESSION['utente_ruolo'] = 'genitore';
$_SESSION['__useremail'] = (string)($access['email'] ?? '');
$_SESSION['__username'] = $_SESSION['username'];
$_SESSION['accesso_temporaneo_carenze'] = 1;
$_SESSION['accesso_temporaneo_carenze_studente_id'] = intval($access['id_studente'] ?? 0);
$_SESSION['LAST_ACTIVITY'] = time();
$_SESSION['EXPIRE_AFTER'] = 7200;

$ip = gatClientIp();
carenzeParentAccessMarkUsed((int)($access['id'] ?? 0), $ip);
dbExec("
    UPDATE genitori
    SET last_login = NOW(),
        last_IP = " . dbQ(substr($ip, 0, 80)) . "
    WHERE id = " . dbI(intval($access['id_genitore'] ?? 0)) . "
    LIMIT 1
");

redirect('/genitore/carenze.php');

?>
