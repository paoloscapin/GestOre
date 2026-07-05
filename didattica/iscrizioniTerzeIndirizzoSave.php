<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

try {
    iscrizioniPrimeEnsureSchema();
    $id = intval($_POST['id'] ?? 0);
    $idIndirizzo = intval($_POST['id_indirizzo_gestore'] ?? 0);
    if ($id <= 0) {
        throw new RuntimeException('Pratica non valida.');
    }
    if ($idIndirizzo > 0) {
        $exists = dbGetFirst("SELECT id FROM indirizzo WHERE id = " . dbI($idIndirizzo) . " AND id BETWEEN 1 AND 10 LIMIT 1");
        if (!$exists) {
            throw new RuntimeException('Indirizzo GestOre non valido.');
        }
    }
    dbExec("
        UPDATE iscrizioni_prime_pratiche
        SET id_indirizzo_gestore = " . dbI($idIndirizzo > 0 ? $idIndirizzo : null) . ",
            updated_at = NOW()
        WHERE id = " . dbI($id) . "
          AND tipo_iscrizione = 'terze'
        LIMIT 1
    ");
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
