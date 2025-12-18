<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('segreteria-didattica', 'dirigente');

echo json_encode(dbGetAll("SELECT id, nome FROM relazioni ORDER BY nome"));
