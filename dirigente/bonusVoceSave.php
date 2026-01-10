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
$indicatore_id = isset($_POST['bonus_indicatore_id']) ? intval($_POST['bonus_indicatore_id']) : 0;

$codice = isset($_POST['codice']) ? escapeString($_POST['codice']) : '';
$descrittori = isset($_POST['descrittori']) ? escapeString($_POST['descrittori']) : '';
$evidenze = isset($_POST['evidenze']) ? escapeString($_POST['evidenze']) : '';
$valore_previsto = (isset($_POST['valore_previsto']) && $_POST['valore_previsto'] !== '') ? intval($_POST['valore_previsto']) : 'NULL';
$valido = isset($_POST['valido']) ? intval($_POST['valido']) : 1;

if ($indicatore_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Indicatore non valido']);
    exit;
}

if ($anno <= 0) {
    echo json_encode(['success' => false, 'message' => 'Anno scolastico non valido']);
    exit;
}

if (empty($codice) || empty($descrittori)) {
    echo json_encode(['success' => false, 'message' => 'Codice e descrittori sono obbligatori']);
    exit;
}

try {
    if ($id > 0) {
        $q = "
            UPDATE bonus
            SET codice = '$codice',
                descrittori = '$descrittori',
                evidenze = '$evidenze',
                valore_previsto = $valore_previsto,
                valido = $valido,
                bonus_indicatore_id = $indicatore_id
            WHERE id = $id
              AND anno_scolastico_id = $anno
            LIMIT 1;
        ";
        dbExec($q);

        echo json_encode(['success' => true, 'message' => 'Bonus aggiornato']);
        exit;
    }

    $q = "
        INSERT INTO bonus
        (valido, codice, descrittori, evidenze, valore_previsto, bonus_indicatore_id, anno_scolastico_id)
        VALUES
        ($valido, '$codice', '$descrittori', '$evidenze', $valore_previsto, $indicatore_id, $anno);
    ";
    dbExec($q);

    echo json_encode(['success' => true, 'message' => 'Bonus creato']);
    exit;

} catch (Exception $e) {
    warning('Errore bonusVoceSave: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore salvataggio bonus']);
    exit;
}
