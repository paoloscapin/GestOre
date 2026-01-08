<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

header('Content-Type: application/json; charset=utf-8');

// Ricevo: corsi_id, new_doc_id (new_doc_id ora è opzionale: se non arriva, duplica identico)
$corsi_id   = intval($_POST['corsi_id']   ?? 0);
$new_doc_id = intval($_POST['new_doc_id'] ?? 0);

if ($corsi_id <= 0) {
    echo json_encode(["ok" => false, "msg" => "Parametri non validi"]);
    exit;
}

$anno_corrente = intval($__anno_scolastico_corrente_id);

// 🔹 Dati del corso
$query = "
    SELECT *
    FROM corso
    WHERE id = $corsi_id
      AND id_anno_scolastico = $anno_corrente
";
$corso = dbGetFirst($query);

if (!$corso) {
    echo json_encode(["ok" => false, "msg" => "Corso non trovato"]);
    exit;
}

// docente target: se passato esplicitamente uso quello, altrimenti copio identico
$docente_target = ($new_doc_id > 0) ? $new_doc_id : intval($corso['id_docente'] ?? 0);

// Escape minimo per evitare errori con apici
$titolo = addslashes($corso['titolo'] ?? '');

// campi extra (se esistono nella tabella)
$carenza_sessione = isset($corso['carenza_sessione']) ? intval($corso['carenza_sessione']) : 0;

// Transazione (se dbExec lancia eccezioni bene, altrimenti comunque aiuta)
dbExec("START TRANSACTION");

try {

    // Inserisco il nuovo corso duplicato (copio tutto ciò che serve)
    $query = "
        INSERT INTO corso
            (id_materia, id_docente, titolo, in_itinere, carenza, carenza_sessione, id_anno_scolastico)
        VALUES
            (" . intval($corso['id_materia']) . ",
             $docente_target,
             '$titolo',
             " . intval($corso['in_itinere']) . ",
             " . intval($corso['carenza']) . ",
             $carenza_sessione,
             " . intval($corso['id_anno_scolastico']) . ")
    ";
    dbExec($query);

    $new_corso_id = intval(dblastId());
    if ($new_corso_id <= 0) {
        dbExec("ROLLBACK");
        echo json_encode(["ok" => false, "msg" => "Errore creazione nuovo corso"]);
        exit;
    }

    // 🔹 Docenti multipli (corso_docenti) - se esistono li copio pari pari
    // Se non esiste tabella o non ci sono righe, non blocco.
    $docenti = [];
    try {
        $docenti = dbGetAll("SELECT id_docente, principale FROM corso_docenti WHERE id_corso = $corsi_id");
    } catch (Exception $e) {
        $docenti = [];
    }

    if (is_array($docenti) && count($docenti) > 0) {
        foreach ($docenti as $d) {
            $id_doc = intval($d['id_docente'] ?? 0);
            if ($id_doc <= 0) continue;
            $principale = intval($d['principale'] ?? 0);

            dbExec("
                INSERT INTO corso_docenti (id_corso, id_docente, principale)
                VALUES ($new_corso_id, $id_doc, $principale)
            ");
        }
    } else {
        // fallback: almeno il docente principale del corso
        if ($docente_target > 0) {
            // se la tabella esiste, provo a inserire (se non esiste, ignoro)
            try {
                dbExec("
                    INSERT INTO corso_docenti (id_corso, id_docente, principale)
                    VALUES ($new_corso_id, $docente_target, 1)
                ");
            } catch (Exception $e) {
                // ignoro
            }
        }
    }

    // 🔹 Date del corso originale
    $date = dbGetAll("SELECT * FROM corso_date WHERE id_corso = $corsi_id");

    foreach ($date as $d) {
        $data_inizio = addslashes($d['data_inizio'] ?? '');
        $data_fine   = addslashes($d['data_fine'] ?? '');
        $aula        = addslashes($d['aula'] ?? '');

        $query = "
            INSERT INTO corso_date
                (id_corso, data_inizio, data_fine, aula)
            VALUES
                ($new_corso_id, '$data_inizio', '$data_fine', '$aula')
        ";
        dbExec($query);
    }

    // 🔹 Iscritti del corso originale
    $iscritti = dbGetAll("SELECT * FROM corso_iscritti WHERE id_corso = $corsi_id");

    foreach ($iscritti as $i) {
        $id_studente = intval($i['id_studente'] ?? 0);
        if ($id_studente <= 0) continue;

        $query = "
            INSERT INTO corso_iscritti
                (id_corso, id_studente)
            VALUES
                ($new_corso_id, $id_studente)
        ";
        dbExec($query);
    }

    dbExec("COMMIT");

    echo json_encode(["ok" => true, "new_corso_id" => $new_corso_id]);
    exit;

} catch (Exception $e) {
    try { dbExec("ROLLBACK"); } catch (Exception $e2) {}
    echo json_encode(["ok" => false, "msg" => "Errore duplicazione: " . $e->getMessage()]);
    exit;
}
