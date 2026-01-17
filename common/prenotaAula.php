<?php
/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/checkSession.php';
require_once __DIR__ . '/connectMBApp.php'; // <-- importante: qui c'è $__conMBApp

header('Content-Type: application/json; charset=utf-8');

function jsonOut($ok, $extra = []) {
    echo json_encode(array_merge(['ok' => (bool)$ok], $extra));
    exit;
}

global $__conMBApp;
if (!($__conMBApp instanceof mysqli)) {
    jsonOut(false, ['error' => 'Connessione DB MBApp non disponibile']);
}

// helper escape (NO prepared, quindi obbligatorio)
function esc($s) {
    global $__conMBApp;
    return mysqli_real_escape_string($__conMBApp, (string)$s);
}

// helper exec "sicura" (NO exit)
function qexec($sql) {
    global $__conMBApp;
    debug($sql);
    $res = mysqli_query($__conMBApp, $sql);
    if ($res === false) {
        $err = mysqli_error($__conMBApp);
        error("errore in esecuzione query.\nquery=$sql\nerror message=$err");
        return [false, $err];
    }
    return [true, null];
}

// ✅ check request corretto
if (
    !isset($_POST['nroAula'], $_POST['dataInizio'], $_POST['oraInizio'], $_POST['oraFine']) ||
    trim($_POST['nroAula']) === '' ||
    trim($_POST['dataInizio']) === '' ||
    trim($_POST['oraInizio']) === '' ||
    trim($_POST['oraFine']) === ''
) {
    jsonOut(false, ['error' => 'Parametri mancanti: nroAula, dataInizio, oraInizio, oraFine']);
}

$nroAula    = esc(trim($_POST['nroAula']));
$dataInizio = esc(trim($_POST['dataInizio']));
$dataFine   = $dataInizio;
$oraInizio  = esc(trim($_POST['oraInizio']));
$oraFine    = esc(trim($_POST['oraFine']));

$docenti  = $__username;       // nel tuo esempio avevi 0
$motivo   = esc($_POST['motivo'] ?? "");
$dettagli = esc($_POST['dettagli'] ?? "");
$username = mb_dbGetValue("SELECT username FROM utente WHERE email1 = '" . esc($__docente_email ?? '') . "'");
$attivitaProgetto = esc($_POST['attivitaProgetto'] ?? "sportello di prova");

$stato = 'CONFERMATO';
/* =====================================================
   2️⃣ calcolo GIORNO (lunedì, martedì, ...)
===================================================== */
$giorni = [
    'Sunday'    => 'domenica',
    'Monday'    => 'lunedi',
    'Tuesday'   => 'martedi',
    'Wednesday' => 'mercoledi',
    'Thursday'  => 'giovedi',
    'Friday'    => 'venerdi',
    'Saturday'  => 'sabato'
];

$engDay = date('l', strtotime($dataInizio));
$giorno = $giorni[$engDay] ?? '';

debug("prenotazione aula $nroAula per data $dataInizio ora $oraInizio");

try {
    // ✅ START TRANSACTION
    mysqli_begin_transaction($__conMBApp);

    // 1) assenze
    $sql1 = "
        INSERT INTO assenze (docenti, dataInizio, dataFine, oraInizio, oraFine, motivo, dettagli, stato)
        VALUES ('$docenti', '$dataInizio', '$dataFine', '$oraInizio', '$oraFine', '$motivo', '$dettagli', '$stato')
    ";
    [$ok1, $err1] = qexec($sql1);
    if (!$ok1) throw new Exception("Insert assenze fallita: $err1");
    $assenzaId = mb_dblastId();
    debug("assenza creata con id $assenzaId");

    // 2) oralezione
    $sql2 = "
        INSERT INTO oralezione (nroAula, dataGiorno, giorno, ora, attivitaProgetto, stato, idAssenza)
        VALUES ('$nroAula', '$dataInizio', '$giorno', '$oraInizio', '$attivitaProgetto', '$stato', $assenzaId)
    ";
    [$ok2, $err2] = qexec($sql2);
    if (!$ok2) throw new Exception("Insert oralezione fallita: $err2");
    $idCalendario = mb_dblastId();
    debug("oralezione creata con id $idCalendario");

    // 3) utilizza
    $sql3 = "
        INSERT INTO utilizza (idCalendario, username, idAssenza)
        VALUES ($idCalendario, '$username', $assenzaId)
    ";
    [$ok3, $err3] = qexec($sql3);
    if (!$ok3) throw new Exception("Insert utilizza fallita: $err3");
    debug("utilizza creata per utente $username");

    // ✅ COMMIT
    mysqli_commit($__conMBApp);

    jsonOut(true, [
        'assenzaId' => (int)$assenzaId,
        'idCalendario' => (int)$idCalendario,
        'msg' => "Prenotazione aula $nroAula confermata"
    ]);

} catch (Throwable $e) {
    // ✅ ROLLBACK
    mysqli_rollback($__conMBApp);
    jsonOut(false, ['error' => $e->getMessage()]);
}
