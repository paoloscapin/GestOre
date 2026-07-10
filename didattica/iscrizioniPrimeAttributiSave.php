<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';

ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=UTF-8');

try {
    iscrizioniPrimeEnsureSchema();
    $praticaId = intval($_POST['pratica_id'] ?? 0);
    if ($praticaId <= 0) {
        throw new RuntimeException('Pratica non valida.');
    }

    $pratica = dbGetFirst("
        SELECT *
        FROM iscrizioni_prime_pratiche
        WHERE id = " . dbI($praticaId) . "
        LIMIT 1
    ");
    if (!$pratica) {
        throw new RuntimeException('Pratica non trovata.');
    }

    $studentId = intval(dbGetValue("
        SELECT id
        FROM studente
        WHERE UPPER(TRIM(codice_fiscale)) = " . dbQ(strtoupper(trim((string)($pratica['codice_fiscale'] ?? '')))) . "
        ORDER BY attivo DESC, id DESC
        LIMIT 1
    ") ?? 0);
    if ($studentId <= 0) {
        $studentId = iscrizioniPrimeUpsertGestoreStudent($pratica);
    }
    if ($studentId <= 0) {
        throw new RuntimeException('Anagrafica studente non collegata.');
    }

    $attrs = [
        STUD_ATTR_R7A2 => intval($_POST['dsa'] ?? 0) === 1,
        STUD_ATTR_Q4M9 => intval($_POST['legge_104'] ?? 0) === 1,
        STUD_ATTR_Z8C3 => intval($_POST['fascia_c'] ?? 0) === 1,
    ];
    foreach ($attrs as $code => $active) {
        studentiAttrUpsert($studentId, (string)$code, (bool)$active, 'iscrizioni_pratica', 'pratica:' . $praticaId);
    }

    iscrizioniPrimeRecordEvent($praticaId, 'attributi_studente', 'Attributi studente aggiornati dalla pratica iscrizione', [
        'dsa' => $attrs[STUD_ATTR_R7A2],
        'legge_104' => $attrs[STUD_ATTR_Q4M9],
        'fascia_c' => $attrs[STUD_ATTR_Z8C3],
    ]);

    echo json_encode([
        'ok' => true,
        'message' => 'Attributi aggiornati.',
        'attributi' => studentiAttrActiveForStudentWithSource($studentId),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
