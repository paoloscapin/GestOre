<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

iscrizioniPrimeEnsureSchema();
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Pratica non valida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pratica = dbGetFirst("
    SELECT id, cognome, nome, codice_fiscale, tipo_iscrizione, stato
    FROM iscrizioni_prime_pratiche
    WHERE id = " . dbI($id) . "
    LIMIT 1
");
if (!$pratica) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Pratica non trovata.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$record = iscrizioniPrimeGetCambioScuola($id) ?: [
    'richiesta_data' => date('Y-m-d'),
    'canale' => 'mail',
    'id_istituto_destinazione' => '',
    'scuola_destinazione' => '',
    'indirizzo_destinazione' => '',
    'colloquio_stato' => 'da_valutare',
    'nulla_osta_stato' => 'da_richiedere',
    'documenti_stato' => 'da_verificare',
    'pratica_stato' => 'aperta',
    'note' => '',
    'allegato_original_name' => '',
];
$eventi = iscrizioniPrimeCambioScuolaEventi($id);
if (!$eventi && !empty($record['id'])) {
    $eventi[] = [
        'id' => 0,
        'pratica_id' => $id,
        'richiesta_data' => $record['richiesta_data'] ?? null,
        'canale' => $record['canale'] ?? null,
        'id_istituto_destinazione' => $record['id_istituto_destinazione'] ?? null,
        'scuola_destinazione' => $record['scuola_destinazione'] ?? '',
        'indirizzo_destinazione' => $record['indirizzo_destinazione'] ?? '',
        'colloquio_stato' => $record['colloquio_stato'] ?? null,
        'nulla_osta_stato' => $record['nulla_osta_stato'] ?? null,
        'documenti_stato' => $record['documenti_stato'] ?? null,
        'pratica_stato' => $record['pratica_stato'] ?? null,
        'note' => $record['note'] ?? '',
        'allegato_path' => $record['allegato_path'] ?? null,
        'allegato_original_name' => $record['allegato_original_name'] ?? '',
        'created_by' => $record['created_by'] ?? '',
        'created_at' => $record['updated_at'] ?? ($record['created_at'] ?? ''),
    ];
}

echo json_encode([
    'ok' => true,
    'pratica' => $pratica,
    'record' => $record,
    'eventi' => $eventi,
    'allegato_url' => !empty($record['allegato_path']) ? 'iscrizioniPrimeCambioScuolaAllegato.php?id=' . intval($id) : '',
], JSON_UNESCAPED_UNICODE);
