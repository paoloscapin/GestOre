<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/checkSession.php';

// check request
debug("check aule libere");
header('Content-Type: application/json; charset=utf-8');

if (isset($_POST['dataGiorno']) != "" && isset($_POST['ora'])) {

    $tipoAula = isset($_POST['tipo']) ? (string)$_POST['tipo'] : 'TUTTE';
    $dataGiorno_raw = (string)$_POST['dataGiorno'];
    $ora_raw = (string)$_POST['ora'];

    $includeAula_raw = isset($_POST['includeAula']) ? trim((string)$_POST['includeAula']) : '';

    // --- Escaping (MBApp DB) ---
    // NB: mb_dbGetAll usa la connessione $__conMBApp; qui usiamo mysqli_real_escape_string su quella.
    global $__conMBApp;

    $tipoAula   = mysqli_real_escape_string($__conMBApp, $tipoAula);
    $dataGiorno = mysqli_real_escape_string($__conMBApp, $dataGiorno_raw);
    $ora        = mysqli_real_escape_string($__conMBApp, $ora_raw);
    $includeAula = mysqli_real_escape_string($__conMBApp, $includeAula_raw);

    // query aule libere
    if ($tipoAula == 'TUTTE') {
        $qFree = "
            SELECT a.nroAula, a.tipo, a.descrizione, 0 AS is_current
            FROM aula a
            WHERE a.prenotabile = 'SI'
              AND NOT EXISTS (
                  SELECT 1
                  FROM oralezione o
                  WHERE o.nroAula = a.nroAula
                    AND o.dataGiorno = '$dataGiorno'
                    AND o.ora = '$ora'
              )
        ";
    } else {
        $qFree = "
            SELECT a.nroAula, a.tipo, a.descrizione, 0 AS is_current
            FROM aula a
            WHERE a.tipo = '$tipoAula'
              AND a.prenotabile = 'SI'
              AND NOT EXISTS (
                  SELECT 1
                  FROM oralezione o
                  WHERE o.nroAula = a.nroAula
                    AND o.dataGiorno = '$dataGiorno'
                    AND o.ora = '$ora'
              )
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

        if ($tipoAula != 'TUTTE') {
            $qCurrent .= " AND a.tipo = '$tipoAula' ";
        }

        // UNION: niente duplicati se l'aula corrente è già libera
        // Wrappo per ordinare bene
        $query = "
            SELECT t.nroAula, t.tipo, t.descrizione, t.is_current
            FROM (
                ($qCurrent)
                UNION
                ($qFree)
            ) t
            ORDER BY t.is_current DESC, t.nroAula
        ";

    } else {
        $query = $qFree . " ORDER BY a.nroAula";
    }

    debug("query aule libere: " . $query);

    $result = mb_dbGetAll($query);
    if ($result === null) {
        $result = array();
    }

    $message = "recupero lista aule libere per tipo=$tipoAula dataGiorno=$dataGiorno ora=$ora includeAula=$includeAula";
    info($message);

    echo json_encode(["status" => "ok", "data" => $result], JSON_UNESCAPED_UNICODE);
    exit();

} else {
    $message = "errore nel recupero lista aule libere: parametri mancanti";
    error($message);
    echo json_encode(["status" => "error", "message" => $message], JSON_UNESCAPED_UNICODE);
    exit();
}
