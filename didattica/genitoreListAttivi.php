<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('segreteria-didattica', 'dirigente');

$query = "SELECT id, cognome, nome FROM genitori WHERE attivo = 1 ORDER BY cognome, nome";
echo json_encode(dbGetAll($query));
