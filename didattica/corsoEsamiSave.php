<?php
/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

/**
 * Quota in modo sicuro un valore stringa per SQL.
 */
function sqlv($val) {
    if ($val === null) return "NULL";
    if (is_string($val)) {
        $trim = trim($val);
        if ($trim === '') return "NULL";
        return "'" . addslashes($trim) . "'";
    }
    return "'" . addslashes((string)$val) . "'";
}

header('Content-Type: application/json; charset=utf-8');

if (empty($_POST['corso_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parametro corso_id mancante']);
    exit;
}

$corso_id   = intval($_POST['corso_id']);
$argomenti  = $_POST['argomenti'] ?? '';
$data_inizio_esame = $_POST['data_inizio'] ?? null;  // atteso "YYYY-MM-DD HH:MM:SS"
$data_fine_esame = $_POST['data_fine'] ?? null;  // atteso "YYYY-MM-DD HH:MM:SS"
$aula_esame = $_POST['aula'] ?? null;
$firmato = $_POST['firmato'] ?? 0;
$studenti   = $_POST['studenti'] ?? [];

error("POST ricevuto: " . print_r($_POST, true));

try {
    // 1) Inserisci o aggiorna la data d'esame in corso_esami_date
    $id_data = null;

    if ($data_inizio_esame) {
        $row = dbGetFirst("SELECT id FROM corso_esami_date WHERE id_corso = $corso_id");
        if ($row) {
            $id_data = intval($row['id']);
            dbExec(
                "UPDATE corso_esami_date
                 SET data_inizio_esame = " . sqlv($data_inizio_esame) . ",
                     data_fine_esame = " . sqlv($data_fine_esame) . ",
                     aula = " . sqlv($aula_esame) . ",
                     firmato = " . intval($firmato) . "
                 WHERE id = $id_data"
            );
        } else {
            dbExec(
                "INSERT INTO corso_esami_date (id_corso, data_inizio_esame, data_fine_esame, aula, firmato)
                 VALUES ($corso_id, " . sqlv($data_inizio_esame) . ", " . sqlv($data_fine_esame) . ", " . sqlv($aula_esame) . ", " . intval($firmato) . ")"
            );
            if (function_exists('dbLastId')) {
                $id_data = dbLastId();
            } else {
                $rowLast = dbGetFirst("SELECT LAST_INSERT_ID() AS id");
                $id_data = $rowLast ? intval($rowLast['id']) : null;
            }
        }
    } else {
        $row = dbGetFirst("SELECT id FROM corso_esami_date WHERE id_corso = $corso_id");
        if ($row) {
            $id_data = intval($row['id']);
        }
    }

    if ($id_data === null && !empty($studenti)) {
        throw new Exception("Nessuna data d'esame impostata. Impostare data e aula e salvare.");
    }

    // 2) Inserisci/Aggiorna esiti
    foreach ($studenti as $stud) {
        $stud_id   = intval($stud['id_studente']);
        $presente  = isset($stud['presente']) ? intval($stud['presente']) : 0;
        $tipologia = $stud['tipo'] ?? null;
        $voto      = ($stud['voto'] !== '' && $stud['voto'] !== null) ? floatval($stud['voto']) : null;

        $whereData = ($id_data !== null) ? " AND id_esame_data = $id_data" : " AND id_esame_data IS NULL";
        $rowEsito = dbGetFirst(
            "SELECT id FROM corso_esiti 
             WHERE id_studente = $stud_id 
               AND id_corso = $corso_id
               $whereData"
        );

        if ($rowEsito) {
            dbExec(
                "UPDATE corso_esiti
                 SET presente = $presente,
                     tipo_prova = " . sqlv($tipologia) . ",
                     voto = " . ($voto !== null ? $voto : "NULL") . ",
                     argomenti = " . sqlv($argomenti) . ",
                     updated_at = NOW()
                 WHERE id = {$rowEsito['id']}"
            );
        } else {
            dbExec(
                "INSERT INTO corso_esiti
                    (id_corso, id_esame_data, id_studente, presente, tipo_prova, voto, argomenti, inviato_registro, created_at, updated_at)
                 VALUES
                    ($corso_id, " . ($id_data !== null ? $id_data : "NULL") . ",
                     $stud_id, $presente,
                     " . sqlv($tipologia) . ",
                     " . ($voto !== null ? $voto : "NULL") . ",
                     " . sqlv($argomenti) . ",
                     0, NOW(), NOW())"
            );
        }
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
