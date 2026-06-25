<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

iscrizioniPrimeEnsureSchema();
$id = intval($_GET['id'] ?? 0);
$eventoId = intval($_GET['evento_id'] ?? 0);
$path = $id > 0 ? iscrizioniPrimeCambioScuolaAllegatoPath($id, $eventoId) : null;
if (!$path) {
    http_response_code(404);
    echo 'Allegato non trovato.';
    exit;
}

$record = $eventoId > 0
    ? (dbGetFirst("SELECT * FROM iscrizioni_prime_cambio_scuola_eventi WHERE id = " . dbI($eventoId) . " AND pratica_id = " . dbI($id) . " LIMIT 1") ?: [])
    : (iscrizioniPrimeGetCambioScuola($id) ?: []);
$filename = trim((string)($record['allegato_original_name'] ?? 'richiesta-cambio-scuola.pdf'));
if ($filename === '') {
    $filename = 'richiesta-cambio-scuola.pdf';
}

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . str_replace('"', '', $filename) . '"');
readfile($path);
