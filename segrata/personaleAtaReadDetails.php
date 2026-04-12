<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('dirigente','segreteria-ata', 'ras');

header('Content-Type: application/json; charset=utf-8');

function paEsc($s) {
    if (isset($GLOBALS['__conn']) && $GLOBALS['__conn']) return mysqli_real_escape_string($GLOBALS['__conn'], $s);
    if (isset($GLOBALS['conn']) && $GLOBALS['conn']) return mysqli_real_escape_string($GLOBALS['conn'], $s);
    return addslashes($s);
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'ID mancante'], JSON_UNESCAPED_UNICODE);
    exit;
}

$personale = dbGetFirst("
    SELECT
        p.*,
        pr.nome AS profilo_nome
    FROM personale_ata p
    LEFT JOIN personale_ata_profili pr ON pr.id = p.id_profilo
    WHERE p.id = $id
    LIMIT 1
");

if (!$personale) {
    echo json_encode(['ok' => false, 'message' => 'Dipendente non trovato'], JSON_UNESCAPED_UNICODE);
    exit;
}

$username = paEsc($personale['username']);

$assegnazioneCorrente = dbGetFirst("
    SELECT
        a.*,
        u.nome AS ufficio_nome
    FROM personale_ata_assegnazioni a
    LEFT JOIN personale_ata_uffici u ON u.id = a.id_ufficio
    WHERE a.username = '$username'
      AND (a.attiva = 1 OR a.data_fine IS NULL)
    ORDER BY a.data_inizio DESC, a.id DESC
    LIMIT 1
");

$storico = dbGetAll("
    SELECT
        a.id,
        a.username,
        a.id_ufficio,
        a.data_inizio,
        a.data_fine,
        a.attiva,
        u.nome AS ufficio_nome
    FROM personale_ata_assegnazioni a
    LEFT JOIN personale_ata_uffici u ON u.id = a.id_ufficio
    WHERE a.username = '$username'
    ORDER BY a.data_inizio DESC, a.id DESC
");
if (!is_array($storico)) $storico = [];

echo json_encode([
    'ok' => true,
    'personale' => $personale,
    'assegnazione_corrente' => $assegnazioneCorrente ?: null,
    'storico' => $storico
], JSON_UNESCAPED_UNICODE);