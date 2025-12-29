<?php
/**
 * Rimuove assegnazione alla 2ª sessione:
 * - trova mapping in corso_carenze_seconda
 * - blocca se esame corso2 (tentativo=1) firmato
 * - cancella esiti sul corso2, iscrizione sul corso2, mapping
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

header('Content-Type: application/json; charset=utf-8');

$id_corso    = intval($_POST['id_corso'] ?? 0);      // corso1
$id_studente = intval($_POST['id_studente'] ?? 0);

if ($id_corso <= 0 || $id_studente <= 0) {
    echo json_encode(['success' => false, 'error' => 'Parametri mancanti']);
    exit;
}

try {
    mysqli_begin_transaction($__con);

    // 1) mapping corso1 -> corso2 per lo studente
    $map = dbGetFirst("
        SELECT id, id_corso_secondo
        FROM corso_carenze_seconda
        WHERE id_corso_primo = $id_corso
          AND id_studente    = $id_studente
        LIMIT 1
    ");

    if (!$map) {
        mysqli_commit($__con);
        echo json_encode(['success' => true, 'msg' => 'Nessuna 2ª sessione da rimuovere']);
        exit;
    }

    $id_map    = intval($map['id'] ?? 0);
    $id_corso2 = intval($map['id_corso_secondo'] ?? 0);

    if ($id_map <= 0 || $id_corso2 <= 0) {
        mysqli_rollback($__con);
        echo json_encode(['success' => false, 'error' => 'Mapping non valido']);
        exit;
    }

    // 2) blocco se esame corso2 (tentativo=1) firmato
    $firmato = dbGetValue("
        SELECT firmato
        FROM corso_esami_date
        WHERE id_corso = $id_corso2
          AND tentativo = 1
        LIMIT 1
    ");
    if (intval($firmato) === 1) {
        mysqli_rollback($__con);
        echo json_encode(['success' => false, 'error' => 'Esame del corso collegato già firmato: impossibile rimuovere']);
        exit;
    }

    // 3) cancello esiti studente su corso2
    dbExec("DELETE FROM corso_esiti WHERE id_corso = $id_corso2 AND id_studente = $id_studente");

    // 4) cancello iscrizione su corso2
    dbExec("DELETE FROM corso_iscritti WHERE id_corso = $id_corso2 AND id_studente = $id_studente");

    // 5) cancello mapping
    dbExec("DELETE FROM corso_carenze_seconda WHERE id = $id_map LIMIT 1");

    mysqli_commit($__con);
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if (isset($__con)) @mysqli_rollback($__con);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
