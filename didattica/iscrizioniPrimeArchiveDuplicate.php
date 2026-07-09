<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

function ip_archive_duplicate_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

try {
    iscrizioniPrimeEnsureSchema();

    $id = intval($_POST['id'] ?? 0);
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($_POST['tipo_iscrizione'] ?? 'terze');
    if ($id <= 0) {
        ip_archive_duplicate_json(['ok' => false, 'message' => 'Pratica non valida.'], 400);
    }

    $practice = dbGetFirst("
        SELECT *
        FROM iscrizioni_prime_pratiche
        WHERE id = " . dbI($id) . "
          AND tipo_iscrizione = " . dbQ($tipoIscrizione) . "
        LIMIT 1
    ");
    if (!$practice) {
        ip_archive_duplicate_json(['ok' => false, 'message' => 'Pratica non trovata.'], 404);
    }

    $state = (string)($practice['stato'] ?? '');
    if (!in_array($state, ['importata', 'bozza'], true)) {
        ip_archive_duplicate_json(['ok' => false, 'message' => 'Puoi archiviare come doppione solo pratiche importate o compilabili non inviate.'], 400);
    }

    $raw = json_decode((string)($practice['raw_prime_json'] ?? ''), true);
    if (is_array($raw) && (($raw['FONTE'] ?? '') === 'movimenti_entrata')) {
        ip_archive_duplicate_json(['ok' => false, 'message' => 'Questa e la pratica generata dai movimenti: non va archiviata come doppione.'], 400);
    }

    $sentMails = intval(dbGetValue("
        SELECT COUNT(*)
        FROM iscrizioni_prime_mail_log
        WHERE pratica_id = " . dbI($id) . "
          AND stato IN ('inviata', 'bounce')
          AND test_mode = 0
    ") ?? 0);
    if ($sentMails > 0) {
        ip_archive_duplicate_json(['ok' => false, 'message' => 'La pratica ha gia mail reali inviate: non la archivio automaticamente.'], 400);
    }

    $cf = strtoupper(trim((string)($practice['codice_fiscale'] ?? '')));
    if ($cf === '') {
        ip_archive_duplicate_json(['ok' => false, 'message' => 'Codice fiscale mancante: impossibile verificare il doppione.'], 400);
    }

    if (!iscrizioniPrimeTableExists('studenti_movimenti_pratiche')) {
        ip_archive_duplicate_json(['ok' => false, 'message' => 'Tabella movimenti non disponibile.'], 400);
    }

    $movement = dbGetFirst("
        SELECT m.id, m.id_pratica_iscrizione, p.id AS pratica_movimento_id
        FROM studenti_movimenti_pratiche m
        LEFT JOIN iscrizioni_prime_pratiche p
          ON p.id = m.id_pratica_iscrizione
         AND p.tipo_iscrizione = " . dbQ($tipoIscrizione) . "
         AND p.stato <> 'annullata'
        WHERE m.tipo_pratica = 'entrata'
          AND m.stato_pratica <> 'annullata'
          AND UPPER(TRIM(m.codice_fiscale)) = " . dbQ($cf) . "
          AND COALESCE(m.id_pratica_iscrizione, 0) <> " . dbI($id) . "
        ORDER BY m.updated_at DESC, m.id DESC
        LIMIT 1
    ");
    if (!$movement || intval($movement['id'] ?? 0) <= 0) {
        ip_archive_duplicate_json(['ok' => false, 'message' => 'Non trovo una pratica movimenti collegata allo stesso studente: non posso archiviare il doppione in automatico.'], 400);
    }

    dbExec("
        UPDATE iscrizioni_prime_pratiche
        SET stato = 'annullata',
            note_interne = CONCAT(COALESCE(note_interne, ''), '\nPratica archiviata dalla segreteria: doppione importato, pratica corretta collegata al movimento entrata #" . intval($movement['id']) . ".'),
            updated_at = NOW()
        WHERE id = " . dbI($id) . "
        LIMIT 1
    ");

    iscrizioniPrimeRecordEvent($id, 'archiviazione_doppione', 'Doppione importato archiviato', [
        'stato_precedente' => $state,
        'stato_nuovo' => 'annullata',
        'messaggio' => 'Pratica importata archiviata: esiste pratica corretta generata/collegata dal movimento entrata #' . intval($movement['id']) . '.',
        'dettagli' => [
            'movimento_entrata_id' => intval($movement['id']),
            'pratica_movimento_id' => intval($movement['id_pratica_iscrizione'] ?? 0),
            'codice_fiscale' => $cf,
        ],
    ]);

    ip_archive_duplicate_json(['ok' => true, 'message' => 'Doppione archiviato. Rimane attiva la pratica collegata al movimento entrata.']);
} catch (Throwable $e) {
    ip_archive_duplicate_json(['ok' => false, 'message' => 'Errore archiviazione doppione: ' . $e->getMessage()], 500);
}
