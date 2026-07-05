<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

iscrizioniPrimeEnsureSchema();

function itv_parse_metric(string $field, string $label): ?float
{
    $raw = trim((string)($_POST[$field] ?? ''));
    if ($raw === '') {
        return null;
    }
    $value = str_replace(',', '.', $raw);
    if (!is_numeric($value)) {
        throw new InvalidArgumentException($label . ' deve essere un numero.');
    }
    $float = round((float)$value, 2);
    if ($float < 0 || $float > 10) {
        throw new InvalidArgumentException($label . ' deve essere compreso tra 0 e 10.');
    }
    return $float;
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Pratica non valida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $before = dbGetFirst("
        SELECT *
        FROM iscrizioni_prime_pratiche
        WHERE id = " . dbI($id) . "
          AND tipo_iscrizione = 'terze'
        LIMIT 1
    ");
    if (!$before) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Pratica terza non trovata.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $fields = [
        'terza_media_pagella' => itv_parse_metric('terza_media_pagella', 'Media pagella'),
        'terza_voto_matematica' => itv_parse_metric('terza_voto_matematica', 'Voto matematica'),
        'terza_voto_italiano' => itv_parse_metric('terza_voto_italiano', 'Voto italiano'),
        'terza_voto_capacita_relazionale' => itv_parse_metric('terza_voto_capacita_relazionale', 'Capacita relazionale'),
    ];

    $sets = [];
    $changes = [];
    foreach ($fields as $field => $value) {
        $sets[] = $field . ' = ' . dbF($value);
        $old = $before[$field] ?? null;
        $oldNorm = ($old === null || $old === '') ? null : round((float)$old, 2);
        $newNorm = $value === null ? null : round((float)$value, 2);
        if ($oldNorm !== $newNorm) {
            $changes[$field] = [
                'prima' => $oldNorm,
                'dopo' => $newNorm,
            ];
        }
    }
    $sets[] = 'updated_at = NOW()';

    dbExec("
        UPDATE iscrizioni_prime_pratiche SET
            " . implode(",\n            ", $sets) . "
        WHERE id = " . dbI($id) . "
        LIMIT 1
    ");

    if ($changes) {
        iscrizioniPrimeRecordEvent($id, 'valori_formazione_classi', 'Valori pagella seconda aggiornati', [
            'dettagli' => $changes,
        ]);
    }

    echo json_encode(['ok' => true, 'message' => 'Valori salvati per la formazione classi.', 'changes' => count($changes)], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
