<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('dirigente');
require_once '../common/connect.php';

header('Content-Type: application/json; charset=utf-8');

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$anno = isset($_POST['anno_scolastico_id']) ? intval($_POST['anno_scolastico_id']) : $__anno_scolastico_corrente_id;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID non valido']);
    exit;
}

try {
    // soft delete indicatore
    dbExec("UPDATE bonus_indicatore SET valido = 0 WHERE id = $id AND anno_scolastico_id = $anno;");

    // soft delete bonus figli nello stesso anno
    dbExec("UPDATE bonus SET valido = 0 WHERE bonus_indicatore_id = $id AND anno_scolastico_id = $anno;");

    echo json_encode(['success' => true, 'message' => 'Indicatore disattivato']);
} catch (Exception $e) {
    warning("Errore bonusIndicatoreDelete: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore eliminazione indicatore']);
}
