<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('esterno', 'docente', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

$rows = dbGetAll("
    SELECT id, codice, titolo, anno_corso, ordine
    FROM geometri_esami
    WHERE attivo=1
    ORDER BY anno_corso ASC, ordine ASC, titolo ASC
");
if (!$rows) $rows = [];

echo json_encode(['success' => true, 'esami' => $rows], JSON_UNESCAPED_UNICODE);
