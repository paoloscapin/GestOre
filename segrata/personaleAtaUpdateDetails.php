<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('dirigente','segreteria-ata');

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

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) paJsonError('ID non valido.');

$nome               = trim($_POST['nome'] ?? '');
$cognome            = trim($_POST['cognome'] ?? '');
$email              = trim($_POST['email'] ?? '');
$usernameNuovo      = trim($_POST['username'] ?? '');
$matricola          = trim($_POST['matricola'] ?? '');
$tipoContratto      = trim($_POST['tipo_contratto'] ?? '');
$codiceFiscale      = trim($_POST['codice_fiscale'] ?? '');
$idProfilo          = isset($_POST['id_profilo']) && $_POST['id_profilo'] !== '' ? intval($_POST['id_profilo']) : "NULL";
$attivo             = isset($_POST['attivo']) ? intval($_POST['attivo']) : 0;

$usernameOriginale   = trim($_POST['username_originale'] ?? '');
$currentAssignmentId = intval($_POST['current_assignment_id'] ?? 0);
$currentUfficioId    = isset($_POST['current_assignment_ufficio_id']) && $_POST['current_assignment_ufficio_id'] !== '' ? intval($_POST['current_assignment_ufficio_id']) : 0;
$currentDataInizio   = trim($_POST['current_assignment_data_inizio'] ?? '');

$newUfficioId        = isset($_POST['id_ufficio']) && $_POST['id_ufficio'] !== '' ? intval($_POST['id_ufficio']) : 0;
$dataInizio          = trim($_POST['data_inizio_assegnazione'] ?? date('Y-m-d'));

$allowedContratti = [
    '',
    'INDETERMINATO',
    'DETERMINATO ANNUALE',
    'DETERMINATO BREVE'
];

if (!in_array($tipoContratto, $allowedContratti, true)) {
    paJsonError('Tipo contratto non valido.');
}

if ($cognome === '' || $nome === '') paJsonError('Nome e cognome sono obbligatori.');
if ($usernameNuovo === '') paJsonError('Lo username è obbligatorio.');
if ($usernameOriginale === '') $usernameOriginale = $usernameNuovo;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInizio)) $dataInizio = date('Y-m-d');

$existing = dbGetFirst("SELECT id FROM personale_ata WHERE username = '".paEsc($usernameNuovo)."' AND id <> $id LIMIT 1");
if ($existing) paJsonError('Esiste già un altro dipendente con questo username.');

dbExec("
    UPDATE personale_ata SET
        cognome='".paEsc($cognome)."',
        nome='".paEsc($nome)."',
        email='".paEsc($email)."',
        username='".paEsc($usernameNuovo)."',
        matricola='".paEsc($matricola)."',
        tipo_contratto='".paEsc($tipoContratto)."',
        codice_fiscale='".paEsc($codiceFiscale)."',
        id_profilo=".($idProfilo === "NULL" ? "NULL" : intval($idProfilo)).",
        attivo=$attivo
    WHERE id=$id
    LIMIT 1
");

if ($usernameOriginale !== $usernameNuovo) {
    dbExec("
        UPDATE personale_ata_assegnazioni
        SET username = '".paEsc($usernameNuovo)."'
        WHERE username = '".paEsc($usernameOriginale)."'
    ");
}

$changedOffice = ($currentUfficioId !== $newUfficioId);

if ($changedOffice) {
    if ($currentAssignmentId > 0) {
        if ($currentDataInizio !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $currentDataInizio)) {
            if ($dataInizio < $currentDataInizio) {
                paJsonError('La data decorrenza del nuovo ufficio non può essere precedente alla data di inizio dell\'assegnazione corrente.');
            }

            if ($dataInizio === $currentDataInizio) {
                if ($newUfficioId > 0) {
                    dbExec("
                        UPDATE personale_ata_assegnazioni
                        SET id_ufficio = $newUfficioId
                        WHERE id = $currentAssignmentId
                        LIMIT 1
                    ");
                } else {
                    dbExec("
                        DELETE FROM personale_ata_assegnazioni
                        WHERE id = $currentAssignmentId
                        LIMIT 1
                    ");
                }

                echo json_encode([
                    'ok' => true,
                    'message' => 'Ufficio aggiornato sulla stessa decorrenza.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        dbExec("
            UPDATE personale_ata_assegnazioni
            SET
                data_fine = DATE_SUB('".paEsc($dataInizio)."', INTERVAL 1 DAY),
                attiva = 0
            WHERE id = $currentAssignmentId
            LIMIT 1
        ");
    }

    if ($newUfficioId > 0) {
        dbExec("
            INSERT INTO personale_ata_assegnazioni
                (username, id_ufficio, data_inizio, data_fine, attiva)
            VALUES
                (
                    '".paEsc($usernameNuovo)."',
                    $newUfficioId,
                    '".paEsc($dataInizio)."',
                    NULL,
                    1
                )
        ");
    }
}

echo json_encode([
    'ok' => true,
    'message' => $changedOffice
        ? 'Scheda aggiornata e storico ufficio registrato.'
        : 'Scheda aggiornata correttamente.'
], JSON_UNESCAPED_UNICODE);