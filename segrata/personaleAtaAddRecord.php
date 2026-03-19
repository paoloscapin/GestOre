<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('dirigente','segreteria-ata');

function esc($s) {
    // prova i nomi più probabili della connessione globale
    if (isset($GLOBALS['__conn']) && $GLOBALS['__conn']) return mysqli_real_escape_string($GLOBALS['__conn'], $s);
    if (isset($GLOBALS['conn']) && $GLOBALS['conn']) return mysqli_real_escape_string($GLOBALS['conn'], $s);
    return addslashes($s);
}

$nome          = esc($_POST['nome'] ?? '');
$cognome       = esc($_POST['cognome'] ?? '');
$email         = esc($_POST['email'] ?? '');
$username      = esc($_POST['username'] ?? '');
$matricola     = esc($_POST['matricola'] ?? '');
$codice_fiscale= esc($_POST['codice_fiscale'] ?? '');
$ruolo         = esc($_POST['ruolo'] ?? '');
$attivo        = isset($_POST['attivo']) ? intval($_POST['attivo']) : 0;

$query = "
INSERT INTO personale_ata (cognome, nome, email, username, matricola, codice_fiscale, ruolo, attivo)
VALUES ('$cognome', '$nome', '$email', '$username', '$matricola', '$codice_fiscale', '$ruolo', $attivo)
";
dbExec($query);
echo "OK";
