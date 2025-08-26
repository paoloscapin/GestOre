<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if (isset($_GET['corso_id']) && $_GET['corso_id'] != "") {
    $corso_id = intval($_GET['corso_id']); // sicurezza: forzo a intero
    // da dove prendo elenco studenti
    $anno_corrente = intval($__anno_scolastico_corrente_id);

// 🔹 Query studenti con carenze e classe
$query = "
    SELECT 
        c.id AS carenza_id,
        c.id_studente AS studente_id,
        s.cognome AS cognome,
        s.nome AS nome,
        f.id_classe AS classe_id,
        f.id_anno_scolastico AS anno_id,
        classi.classe AS classe
    FROM carenze c
    INNER JOIN studente s 
        ON s.id = c.id_studente
    INNER JOIN studente_frequenta f
        ON f.id_studente = s.id
    INNER JOIN classi classi
        ON classi.id = f.id_classe
    WHERE f.id_anno_scolastico = $anno_corrente
";

$studenti = dbGetAll($query);

// 🔹 Costruzione della struttura JSON
$struct_json = [
    'stud' => $studenti
];

header('Content-Type: application/json; charset=utf-8');
echo json_encode($struct_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

}
