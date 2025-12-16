<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

header('Content-Type: application/json; charset=utf-8');

// Ricevo: corsi_id, new_doc_id
$corsi_id   = intval($_POST['corsi_id']   ?? 0);
$new_doc_id = intval($_POST['new_doc_id'] ?? 0);

if ($corsi_id <= 0 || $new_doc_id <= 0) {
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

// Escape minimo per evitare errori con apici
$titolo = addslashes($corso['titolo'] ?? '');

// Inserisco il nuovo corso duplicato
$query = "
    INSERT INTO corso
        (id_materia, id_docente, titolo, in_itinere, carenza, id_anno_scolastico)
    VALUES
        (" . intval($corso['id_materia']) . ",
         $new_doc_id,
         '$titolo',
         " . intval($corso['in_itinere']) . ",
         " . intval($corso['carenza']) . ",
         " . intval($corso['id_anno_scolastico']) . ")
";
dbExec($query);

$new_corso_id = intval(dblastId());
if ($new_corso_id <= 0) {
    echo json_encode(["ok" => false, "msg" => "Errore creazione nuovo corso"]);
    exit;
}

// 🔹 Date del corso originale
$query = "SELECT * FROM corso_date WHERE id_corso = $corsi_id";
$date = dbGetAll($query);

// Inserisco le date nel nuovo corso
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
$query = "SELECT * FROM corso_iscritti WHERE id_corso = $corsi_id";
$iscritti = dbGetAll($query);

// Inserisco gli iscritti nel nuovo corso
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

// Restituisco il risultato
echo json_encode(["ok" => true, "new_corso_id" => $new_corso_id]);
exit;
