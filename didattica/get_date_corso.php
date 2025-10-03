<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

header('Content-Type: application/json');

$corso_id = isset($_POST['corso_id']) ? intval($_POST['corso_id']) : 0;

if ($corso_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID corso non valido']);
    exit;
}

try {
    $query = "SELECT id, data_inizio, data_fine, aula FROM corso_date WHERE id_corso = $corso_id ORDER BY data_inizio ASC";
    $dates = dbGetAll($query);

    if ($dates === null) $dates = [];

    echo json_encode(['success' => true, 'date' => $dates]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
