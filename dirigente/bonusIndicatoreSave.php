
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
$area_id = isset($_POST['bonus_area_id']) ? intval($_POST['bonus_area_id']) : 0;

$codice = isset($_POST['codice']) ? escapeString($_POST['codice']) : '';
$descrizione = isset($_POST['descrizione']) ? escapeString($_POST['descrizione']) : '';
$valore_massimo = isset($_POST['valore_massimo']) && $_POST['valore_massimo'] !== '' ? intval($_POST['valore_massimo']) : 'NULL';
$valido = isset($_POST['valido']) ? intval($_POST['valido']) : 1;

if ($area_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Area non valida']);
    exit;
}

if (empty($codice) || empty($descrizione)) {
    echo json_encode(['success' => false, 'message' => 'Codice e descrizione sono obbligatori']);
    exit;
}

try {
    if ($id > 0) {
        $q = "
            UPDATE bonus_indicatore
            SET codice = '$codice',
                descrizione = '$descrizione',
                valore_massimo = $valore_massimo,
                valido = $valido,
                bonus_area_id = $area_id
            WHERE id = $id
              AND anno_scolastico_id = $anno;
        ";
        dbExec($q);
        echo json_encode(['success' => true, 'message' => 'Indicatore aggiornato']);
    } else {
        $q = "
            INSERT INTO bonus_indicatore
            (valido, codice, descrizione, valore_massimo, bonus_area_id, anno_scolastico_id)
            VALUES
            ($valido, '$codice', '$descrizione', $valore_massimo, $area_id, $anno);
        ";
        dbExec($q);
        echo json_encode(['success' => true, 'message' => 'Indicatore creato']);
    }
} catch (Exception $e) {
    warning("Errore bonusIndicatoreSave: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore salvataggio indicatore']);
}
