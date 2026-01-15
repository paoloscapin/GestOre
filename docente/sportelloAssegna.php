<?php
/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

header('Content-Type: application/json; charset=utf-8');

$sportello_id = intval($_POST['sportello_id'] ?? 0);
$docente_id = intval($__docente_id ?? 0);

if ($sportello_id <= 0 || $docente_id <= 0) {
    echo json_encode(["ok" => false, "msg" => "Parametri non validi"]);
    exit;
}

// Verifica che lo sportello sia davvero una bozza assegnabile (attivo=0 e docente_id=0)
$check = dbGetFirst("SELECT id, docente_id, attivo, firmato, cancellato FROM sportello WHERE id = $sportello_id LIMIT 1");
if (!$check) {
    echo json_encode(["ok" => false, "msg" => "Sportello non trovato"]);
    exit;
}

if (intval($check['cancellato'] ?? 0) === 1 || intval($check['firmato'] ?? 0) === 1) {
    echo json_encode(["ok" => false, "msg" => "Sportello non assegnabile (cancellato o firmato)"]);
    exit;
}

if (intval($check['attivo'] ?? 0) !== 0) {
    echo json_encode(["ok" => false, "msg" => "Sportello già attivo"]);
    exit;
}

if (intval($check['docente_id'] ?? 0) !== 0) {
    echo json_encode(["ok" => false, "msg" => "Sportello già assegnato da un altro docente"]);
    exit;
}

// Assegna: docente_id = docente loggato.
// IMPORTANTE: lasciamo attivo=0 finché non viene inserito 'luogo' e salvato.
try {
    dbExec("UPDATE sportello SET docente_id = $docente_id WHERE id = $sportello_id AND docente_id = 0 AND attivo = 0");
    echo json_encode(["ok" => true]);
    exit;
} catch (Exception $e) {
    echo json_encode(["ok" => false, "msg" => "Errore DB: " . $e->getMessage()]);
    exit;
}
