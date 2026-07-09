<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
require_once '../common/formazioneClassiLib.php';
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
    $practice = dbGetFirst("
        SELECT id, codice_fiscale, anno_corso
        FROM iscrizioni_prime_pratiche
        WHERE id = " . dbI($id) . "
          AND tipo_iscrizione = 'terze'
        LIMIT 1
    ");
    if (!$practice) {
        throw new RuntimeException('Pratica non trovata.');
    }
    $indirizzoNome = '';
    if ($idIndirizzo > 0) {
        $indirizzoNome = trim((string)(dbGetValue("
            SELECT nome
            FROM indirizzo
            WHERE id = " . dbI($idIndirizzo) . "
            LIMIT 1
        ") ?? ''));
    }
    $corsoStudiSql = $idIndirizzo > 0
        ? 'corso_studi = ' . dbQ($indirizzoNome)
        : 'corso_studi = corso_studi';
    dbExec("
        UPDATE iscrizioni_prime_pratiche
        SET id_indirizzo_gestore = " . dbI($idIndirizzo > 0 ? $idIndirizzo : null) . ",
            " . $corsoStudiSql . ",
            updated_at = NOW()
        WHERE id = " . dbI($id) . "
          AND tipo_iscrizione = 'terze'
        LIMIT 1
    ");
    $codiceFiscale = strtoupper(trim((string)($practice['codice_fiscale'] ?? '')));
    $annoCorso = intval($practice['anno_corso'] ?? 3) ?: 3;
    if (iscrizioniPrimeTableExists('studenti_movimenti_pratiche') && iscrizioniPrimeTableColumnExists('studenti_movimenti_pratiche', 'id_indirizzo_gestore')) {
        $where = ["id_pratica_iscrizione = " . dbI($id)];
        if ($codiceFiscale !== '') {
            $where[] = "(UPPER(TRIM(codice_fiscale)) = " . dbQ($codiceFiscale) . " AND COALESCE(anno_corso, 0) = " . dbI($annoCorso) . ")";
        }
        dbExec("
            UPDATE studenti_movimenti_pratiche
            SET id_indirizzo_gestore = " . dbI($idIndirizzo > 0 ? $idIndirizzo : null) . ",
                indirizzo_destinazione = " . ($idIndirizzo > 0 ? dbQ($indirizzoNome) : "indirizzo_destinazione") . ",
                updated_at = NOW()
            WHERE tipo_pratica = 'entrata'
              AND stato_pratica <> 'annullata'
              AND (" . implode(' OR ', $where) . ")
        ");
    }
    if (iscrizioniPrimeTableExists('genitori_colloqui')
        && iscrizioniPrimeTableColumnExists('genitori_colloqui', 'id_indirizzo_gestore')
        && iscrizioniPrimeTableColumnExists('genitori_colloqui', 'indirizzo_iscrizione')) {
        $where = ["id_pratica_iscrizione = " . dbI($id)];
        if ($codiceFiscale !== '') {
            $where[] = "(UPPER(TRIM(codice_fiscale)) = " . dbQ($codiceFiscale) . " AND COALESCE(anno_corso, 0) = " . dbI($annoCorso) . ")";
        }
        dbExec("
            UPDATE genitori_colloqui
            SET id_indirizzo_gestore = " . dbI($idIndirizzo > 0 ? $idIndirizzo : null) . ",
                indirizzo_iscrizione = " . ($idIndirizzo > 0 ? dbQ($indirizzoNome) : "indirizzo_iscrizione") . ",
                updated_at = NOW()
            WHERE ambito IN ('entrata', 'iscrizione_terze')
              AND (" . implode(' OR ', $where) . ")
        ");
    }
    formazioneClassiSyncTerzaStudentAddressChange(
        (string)($practice['anno_scolastico'] ?? ''),
        $codiceFiscale,
        $indirizzoNome
    );
    echo json_encode([
        'ok' => true,
        'indirizzo_gestore_nome' => $indirizzoNome,
        'corso_studi' => $idIndirizzo > 0 ? $indirizzoNome : ($practice['corso_studi'] ?? ''),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
