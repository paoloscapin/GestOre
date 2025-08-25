<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if (isset($_POST['corsi_id']) && $_POST['corsi_id'] != "") {
    $corsi_id = intval($_POST['corsi_id']); // sicurezza: forzo a intero
    $anno_corrente = intval($__anno_scolastico_corrente_id);

    // 🔹 Dati del corso
    $query = "
        SELECT c.id AS corso_id,
               c.id_materia AS materia_id,
               c.id_docente AS doc_id,
               c.titolo AS titolo,
               c.id_anno_scolastico AS anno_id
        FROM corso c
        WHERE c.id = $corsi_id
    ";
    $corso = dbGetFirst($query);

    // 🔹 Date del corso
    $query = "
        SELECT 
            d.id AS data_id,
            d.id_corso AS corso_id,
            d.data AS corso_data,
            d.aula AS corso_aula
        FROM corso_date d
        WHERE d.id_corso = $corsi_id
        ORDER BY d.data
    ";
    $date = dbGetAll($query);

    // 🔹 Studenti iscritti (solo anno scolastico corrente)
    $query = "
        SELECT 
            s.id AS stud_id,
            s.nome AS stud_nome,
            s.cognome AS stud_cognome,
            c.id AS classe_id,
            c.classe AS classe
        FROM corso_iscritti i
        INNER JOIN studente s 
            ON s.id = i.id_studente
        INNER JOIN studente_frequenta f 
            ON f.id_studente = s.id 
           AND f.id_anno_scolastico = $anno_corrente
        INNER JOIN classi c 
            ON c.id = f.id_classe
        WHERE i.id_corso = $corsi_id
        ORDER BY s.cognome, s.nome
    ";
    $studenti = dbGetAll($query);

    // 🔹 Costruzione della struttura JSON
    $struct_json = [
        'corso' => $corso,
        'date' => $date,
        'studenti' => $studenti
    ];

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($struct_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
