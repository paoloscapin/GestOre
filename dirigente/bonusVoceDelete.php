<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('dirigente');
require_once '../common/connect.php';

header('Content-Type: application/json; charset=utf-8');

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$anno = isset($_POST['anno_scolastico_id']) ? intval($_POST['anno_scolastico_id']) : intval($__anno_scolastico_corrente_id);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID non valido']);
    exit;
}

if ($anno <= 0) {
    echo json_encode(['success' => false, 'message' => 'Anno scolastico non valido']);
    exit;
}

try {
    dbExec("UPDATE bonus SET valido = 0 WHERE id = $id AND anno_scolastico_id = $anno LIMIT 1;");
    echo json_encode(['success' => true, 'message' => 'Bonus disattivato']);
    exit;

} catch (Exception $e) {
    warning("Errore bonusVoceDelete: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore eliminazione bonus']);
    exit;
}
