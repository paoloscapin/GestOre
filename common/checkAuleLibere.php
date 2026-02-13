<?php
/**
 * checkAuleLibere.php (common)
 * - supporta durataOre=1 o 2 (controllo su MBApp: oralezione)
 */

require_once __DIR__ . '/checkSession.php';
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/__Settings.php';
require_once __DIR__ . '/connectMBApp.php';

debug("check aule libere");
header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['dataGiorno']) || !isset($_POST['ora'])) {
    $message = "errore nel recupero lista aule libere: parametri mancanti";
    error($message);
    echo json_encode(["status" => "error", "message" => $message], JSON_UNESCAPED_UNICODE);
    exit();
}

$tipoAula_raw     = isset($_POST['tipo']) ? (string)$_POST['tipo'] : 'TUTTE';
$dataGiorno_raw   = (string)$_POST['dataGiorno'];
$ora_raw          = (string)$_POST['ora'];
$includeAula_raw  = isset($_POST['includeAula']) ? trim((string)$_POST['includeAula']) : '';
$durataOre_raw    = isset($_POST['durataOre']) ? (int)$_POST['durataOre'] : 1;

$durataOre = (int)$durataOre_raw;
if ($durataOre < 1) $durataOre = 1;
if ($durataOre > 2) $durataOre = 2;

// ORARI deve combaciare col JS
$ORARI = ["07:50","08:40","09:30","10:30","11:20","12:10","13:00","13:50","14:40","15:30","16:20","17:10","18:00","18:50","19:40","20:30","21:30","22:20"];

$ora2_raw = '';
if ($durataOre === 2) {
    $idx = array_search($ora_raw, $ORARI, true);
    if ($idx === false || !isset($ORARI[$idx + 1])) {
        // non esiste slot da 2 ore
        echo json_encode(["status" => "ok", "data" => [], "durataOre" => 2, "ora2" => ""], JSON_UNESCAPED_UNICODE);
        exit();
    }
    $ora2_raw = $ORARI[$idx + 1];
}

// --- Escaping (MBApp DB) ---
global $__conMBApp;
if (!($__conMBApp instanceof mysqli)) {
    $message = "connessione MBApp non disponibile";
    error($message);
    echo json_encode(["status" => "error", "message" => $message], JSON_UNESCAPED_UNICODE);
    exit();
}

$tipoAula    = mysqli_real_escape_string($__conMBApp, $tipoAula_raw);
$dataGiorno  = mysqli_real_escape_string($__conMBApp, $dataGiorno_raw);
$ora         = mysqli_real_escape_string($__conMBApp, $ora_raw);
$ora2        = mysqli_real_escape_string($__conMBApp, $ora2_raw);
$includeAula = mysqli_real_escape_string($__conMBApp, $includeAula_raw);

// filtro "libera a ora" oppure "libera a ora e ora2"
$condLibera1 = "
    NOT EXISTS (
        SELECT 1 FROM oralezione o
        WHERE o.nroAula = a.nroAula
          AND o.dataGiorno = '$dataGiorno'
          AND o.ora = '$ora'
    )
";

$condLibera2 = "";
if ($durataOre === 2) {
    $condLibera2 = "
      AND NOT EXISTS (
          SELECT 1 FROM oralezione o2
          WHERE o2.nroAula = a.nroAula
            AND o2.dataGiorno = '$dataGiorno'
            AND o2.ora = '$ora2'
      )
    ";
}

// query aule libere
if ($tipoAula === 'TUTTE') {
    $qFree = "
        SELECT a.nroAula, a.tipo, a.descrizione, 0 AS is_current
        FROM aula a
        WHERE a.prenotabile = 'SI'
          AND $condLibera1
          $condLibera2
    ";
} else {
    $qFree = "
        SELECT a.nroAula, a.tipo, a.descrizione, 0 AS is_current
        FROM aula a
        WHERE a.tipo = '$tipoAula'
          AND a.prenotabile = 'SI'
          AND $condLibera1
          $condLibera2
    ";
}

// query aula corrente (includila sempre se passata, anche se occupata)
if ($includeAula !== '') {
    $qCurrent = "
        SELECT a.nroAula, a.tipo, a.descrizione, 1 AS is_current
        FROM aula a
        WHERE a.prenotabile = 'SI'
          AND a.nroAula = '$includeAula'
    ";
    if ($tipoAula !== 'TUTTE') {
        $qCurrent .= " AND a.tipo = '$tipoAula' ";
    }

    // UNION robusto
    $query = "
        SELECT t.nroAula, t.tipo, t.descrizione, t.is_current
        FROM (
            $qCurrent
            UNION
            $qFree
        ) t
        ORDER BY t.is_current DESC, t.nroAula
    ";
} else {
    $query = $qFree . " ORDER BY a.nroAula";
}

debug("query aule libere: " . preg_replace('/\s+/', ' ', trim($query)));

$result = mb_dbGetAll($query);
if ($result === null) $result = [];

$message = "recupero lista aule libere per tipo=$tipoAula_raw dataGiorno=$dataGiorno_raw ora=$ora_raw durataOre=$durataOre includeAula=$includeAula_raw";
info($message);

echo json_encode([
    "status" => "ok",
    "data" => $result,
    "durataOre" => $durataOre,
    "ora2" => $ora2_raw
], JSON_UNESCAPED_UNICODE);
exit();
