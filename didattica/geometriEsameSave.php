<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

function ge_fail($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = intval($_POST['id'] ?? -1);
$codice = strtoupper(trim((string)($_POST['codice'] ?? '')));
$titolo = trim((string)($_POST['titolo'] ?? ''));
$descrizione = trim((string)($_POST['descrizione'] ?? ''));
$anno_corso = intval($_POST['anno_corso'] ?? 0);
$ordine = intval($_POST['ordine'] ?? 0);
$attivo = intval($_POST['attivo'] ?? 0) === 1 ? 1 : 0;

if ($codice === '' || $titolo === '' || !in_array($anno_corso, [3, 4, 5], true)) {
    ge_fail('Compila codice, titolo e anno corso');
}

$existing = intval(dbGetValue("
    SELECT COUNT(*)
    FROM geometri_esami
    WHERE codice = " . dbQ($codice) . "
      AND id <> " . dbI($id) . "
"));
if ($existing > 0) {
    ge_fail('Codice esame già presente');
}

if ($id > 0) {
    dbExec("
        UPDATE geometri_esami
        SET codice=" . dbQ($codice) . ",
            titolo=" . dbQ($titolo) . ",
            descrizione=" . dbQ($descrizione) . ",
            anno_corso=" . dbI($anno_corso) . ",
            ordine=" . dbI($ordine) . ",
            attivo=" . dbI($attivo) . "
        WHERE id=" . dbI($id) . "
    ");
    $esame_id = $id;
} else {
    dbExec("
        INSERT INTO geometri_esami (codice, titolo, descrizione, anno_corso, ordine, attivo)
        VALUES (" . dbQ($codice) . ", " . dbQ($titolo) . ", " . dbQ($descrizione) . ", " . dbI($anno_corso) . ", " . dbI($ordine) . ", " . dbI($attivo) . ")
    ");
    $esame_id = intval(dblastId());
}

echo json_encode(['success' => true, 'id' => $esame_id], JSON_UNESCAPED_UNICODE);
