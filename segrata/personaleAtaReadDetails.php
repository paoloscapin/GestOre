<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('dirigente','segreteria-ata');

if (!isset($_POST['id'])) { http_response_code(400); exit; }
$id = intval($_POST['id']);

$row = dbGetFirst("SELECT * FROM personale_ata WHERE id = $id LIMIT 1");
header('Content-Type: application/json; charset=utf-8');
echo json_encode($row ? $row : []);
