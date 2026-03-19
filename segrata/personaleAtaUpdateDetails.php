<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('dirigente','segreteria-ata');

function esc($s) {
    if (isset($GLOBALS['__conn']) && $GLOBALS['__conn']) return mysqli_real_escape_string($GLOBALS['__conn'], $s);
    if (isset($GLOBALS['conn']) && $GLOBALS['conn']) return mysqli_real_escape_string($GLOBALS['conn'], $s);
    return addslashes($s);
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit; }

$nome           = esc($_POST['nome'] ?? '');
$cognome        = esc($_POST['cognome'] ?? '');
$email          = esc($_POST['email'] ?? '');
$username       = esc($_POST['username'] ?? '');
$matricola      = esc($_POST['matricola'] ?? '');
$codice_fiscale = esc($_POST['codice_fiscale'] ?? '');
$ruolo          = esc($_POST['ruolo'] ?? '');
$attivo         = isset($_POST['attivo']) ? intval($_POST['attivo']) : 0;

$query = "
UPDATE personale_ata SET
    cognome='$cognome',
    nome='$nome',
    email='$email',
    username='$username',
    matricola='$matricola',
    codice_fiscale='$codice_fiscale',
    ruolo='$ruolo',
    attivo=$attivo
WHERE id=$id
LIMIT 1
";
dbExec($query);
echo "OK";
