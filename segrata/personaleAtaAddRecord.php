<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('dirigente','segreteria-ata'. 'ras');

header('Content-Type: application/json; charset=utf-8');

function paEsc($s) {
    if (isset($GLOBALS['__conn']) && $GLOBALS['__conn']) return mysqli_real_escape_string($GLOBALS['__conn'], $s);
    if (isset($GLOBALS['conn']) && $GLOBALS['conn']) return mysqli_real_escape_string($GLOBALS['conn'], $s);
    return addslashes($s);
}

function paJsonError($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

$nome           = trim($_POST['nome'] ?? '');
$cognome        = trim($_POST['cognome'] ?? '');
$email          = trim($_POST['email'] ?? '');
$username       = trim($_POST['username'] ?? '');
$matricola      = trim($_POST['matricola'] ?? '');
$tipoContratto  = trim($_POST['tipo_contratto'] ?? '');
$codiceFiscale  = trim($_POST['codice_fiscale'] ?? '');
$idProfilo      = isset($_POST['id_profilo']) && $_POST['id_profilo'] !== '' ? intval($_POST['id_profilo']) : "NULL";
$attivo         = isset($_POST['attivo']) ? intval($_POST['attivo']) : 0;

$idUfficio      = isset($_POST['id_ufficio']) && $_POST['id_ufficio'] !== '' ? intval($_POST['id_ufficio']) : 0;
$dataInizio     = trim($_POST['data_inizio_assegnazione'] ?? date('Y-m-d'));

if ($cognome === '' || $nome === '') paJsonError('Nome e cognome sono obbligatori.');
if ($username === '') paJsonError('Lo username è obbligatorio.');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInizio)) $dataInizio = date('Y-m-d');

$dup = dbGetFirst("SELECT id FROM personale_ata WHERE username = '".paEsc($username)."' LIMIT 1");
if ($dup) paJsonError('Esiste già un dipendente con questo username.');

dbExec("
    INSERT INTO personale_ata
        (cognome, nome, email, username, matricola, tipo_contratto, codice_fiscale, id_profilo, attivo)
    VALUES
        (
            '".paEsc($cognome)."',
            '".paEsc($nome)."',
            '".paEsc($email)."',
            '".paEsc($username)."',
            '".paEsc($matricola)."',
            '".paEsc($tipoContratto)."',
            '".paEsc($codiceFiscale)."',
            ".($idProfilo === "NULL" ? "NULL" : intval($idProfilo)).",
            $attivo
        )
");

if ($idUfficio > 0) {
    dbExec("
        INSERT INTO personale_ata_assegnazioni
            (username, id_ufficio, data_inizio, data_fine, attiva)
        VALUES
            (
                '".paEsc($username)."',
                $idUfficio,
                '".paEsc($dataInizio)."',
                NULL,
                1
            )
    ");
}

echo json_encode([
    'ok' => true,
    'message' => 'Dipendente inserito correttamente.'
], JSON_UNESCAPED_UNICODE);