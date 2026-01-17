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
    if (isset($_POST['tipo'])) {
        $tipoAula = $_POST['tipo'];
    } else {
        $tipoAula = 'TUTTE';
    }
    $dataGiorno = $_POST['dataGiorno'];
    $ora = $_POST['ora'];

    // delete record
    if ($tipoAula == 'TUTTE') {
        $query = "SELECT a.nroAula, a.tipo, a.descrizione FROM aula a WHERE a.prenotabile = 'SI' AND NOT EXISTS ( SELECT 1 FROM oralezione o WHERE o.nroAula = a.nroAula AND o.dataGiorno = '$dataGiorno' AND o.ora = '$ora') ORDER BY a.nroAula";
    } else {
        $query = "SELECT a.nroAula, a.tipo, a.descrizione FROM aula a WHERE a.tipo = '$tipoAula' AND a.prenotabile = 'SI' AND NOT EXISTS ( SELECT 1 FROM oralezione o WHERE o.nroAula = a.nroAula AND o.dataGiorno = '$dataGiorno' AND o.ora = '$ora') ORDER BY a.nroAula";
    }
    $result = mb_dbGetAll($query);
    if ($result === null) {
        $result = array();
    }

    $message = "recupero lista aule libere per tipo=$tipoAula dataGiorno=$dataGiorno ora=$ora";
    info($message);
    echo json_encode(["status" => "ok", "data" => $result], JSON_UNESCAPED_UNICODE);
    exit();
} else {
    $message = "errore nel recupero lista aule libere: parametri mancanti";
    error($message);
    echo json_encode(["status" => "error", "message" => $message], JSON_UNESCAPED_UNICODE);
    exit();
}
