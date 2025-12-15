<?php
/**
 * Salva esito d'esame per una specifica sessione già esistente
 * (1° o 2° tentativo).
 * 
 * Non crea nuove righe in corso_esami_date.
 */

require_once '../common/checkSession.php';

/** Quota in modo sicuro un valore stringa per SQL. */
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
$id_esame_data = isset($_POST['id_esame_data']) ? $_POST['id_esame_data'] : null;
$argomenti  = $_POST['argomenti'] ?? '';
$data_inizio_esame = $_POST['data_inizio'] ?? null;
$data_fine_esame   = $_POST['data_fine'] ?? null;
$aula_esame        = $_POST['aula'] ?? null;
$firmato           = isset($_POST['firmato']) ? intval($_POST['firmato']) : 0;
$studenti          = $_POST['studenti'] ?? [];

try {
    // 🔎 Se id_esame_data è mancante o -1 → crea una nuova sessione
    if (empty($id_esame_data) || intval($id_esame_data) <= 0) {
        // recupera prossimo numero di tentativo (1, 2, ...)
        $tentativo = dbGetValue("
            SELECT IFNULL(MAX(tentativo), 0) + 1 
            FROM corso_esami_date 
            WHERE id_corso = $corso_id
        ");

        dbExec("
            INSERT INTO corso_esami_date 
                (id_corso, tentativo, data_inizio_esame, data_fine_esame, aula, firmato, created_at, updated_at)
            VALUES
                ($corso_id, $tentativo, 
                 " . sqlv($data_inizio_esame) . ", 
                 " . sqlv($data_fine_esame) . ", 
                 " . sqlv($aula_esame) . ", 
                 " . intval($firmato) . ", 
                 NOW(), NOW())
        ");

        // ottieni il nuovo id
        $id_esame_data = dblastId();

        // 🔹 Se è il primo tentativo, inserisci tutti gli iscritti del corso in corso_esiti
    if ($tentativo == 1) {
        dbExec("
            INSERT INTO corso_esiti (id_corso, id_esame_data, id_studente, presente, tipo_prova, voto, argomenti, inviato_registro, created_at, updated_at)
            SELECT 
                ci.id_corso,
                $id_esame_data,
                ci.id_studente,
                0 AS presente,
                NULL AS tipo_prova,
                NULL AS voto,
                NULL AS argomenti,
                0 AS inviato_registro,
                NOW(), NOW()
            FROM corso_iscritti ci
            WHERE ci.id_corso = $corso_id
        ");
    }
    } else {
        // 🔹 Aggiorna la sessione esistente
        dbExec("
            UPDATE corso_esami_date
            SET data_inizio_esame = " . sqlv($data_inizio_esame) . ",
                data_fine_esame = " . sqlv($data_fine_esame) . ",
                aula = " . sqlv($aula_esame) . ",
                firmato = " . intval($firmato) . ",
                updated_at = NOW()
            WHERE id = $id_esame_data
              AND id_corso = $corso_id
        ");
    }


    // 🔹 Aggiorna o inserisci gli esiti relativi a quella sessione
    foreach ($studenti as $stud) {
        $stud_id   = intval($stud['id_studente']);
        $presente  = isset($stud['presente']) ? intval($stud['presente']) : 0;
        $tipologia = $stud['tipo'] ?? null;
        $voto      = ($stud['voto'] !== '' && $stud['voto'] !== null) ? floatval($stud['voto']) : null;

        // controlla se esiste già l'esito per quella sessione
        $rowEsito = dbGetFirst("
            SELECT id FROM corso_esiti 
            WHERE id_corso = $corso_id 
              AND id_studente = $stud_id 
              AND id_esame_data = $id_esame_data
        ");

        if ($rowEsito) {
            dbExec("
                UPDATE corso_esiti
                SET presente = $presente,
                    tipo_prova = " . sqlv($tipologia) . ",
                    voto = " . ($voto !== null ? $voto : "NULL") . ",
                    argomenti = " . sqlv($argomenti) . ",
                    updated_at = NOW()
                WHERE id = {$rowEsito['id']}
            ");
        } else {
            dbExec("
                INSERT INTO corso_esiti
                    (id_corso, id_esame_data, id_studente, presente, tipo_prova, voto, argomenti, inviato_registro, created_at, updated_at)
                VALUES
                    ($corso_id, $id_esame_data, $stud_id, $presente,
                     " . sqlv($tipologia) . ",
                     " . ($voto !== null ? $voto : "NULL") . ",
                     " . sqlv($argomenti) . ",
                     0, NOW(), NOW())
            ");
        }
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
