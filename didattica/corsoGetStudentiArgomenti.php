<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

// Recupera l'id della data del corso
$id_data_corso = isset($_POST['data_id']) ? intval($_POST['data_id']) : 0;

if ($id_data_corso <= 0) {
    echo json_encode(["success" => false, "error" => "id_data_corso mancante"]);
    exit;
}

// Recupera id_corso dalla data
$corso_info = dbGetFirst("
    SELECT id_corso, firmato
    FROM corso_date
    WHERE id = $id_data_corso
");

if (!$corso_info) {
    echo json_encode(["success" => false, "error" => "Data corso non trovata"]);
    exit;
}

$id_corso = $corso_info['id_corso'];
$firmato = isset($corso_info['firmato']) ? intval($corso_info['firmato']) : 0;

// Usa l'anno scolastico corrente
global $__anno_scolastico_corrente_id;
$id_anno_scolastico = $__anno_scolastico_corrente_id;

// Elenco studenti iscritti al corso con la loro classe
$studenti = dbGetAll("
    SELECT s.id, CONCAT(s.cognome,' ',s.nome) AS nominativo, cl.classe AS classe
    FROM corso_iscritti ci
    INNER JOIN studente s ON s.id = ci.id_studente
    LEFT JOIN studente_frequenta sf ON sf.id_studente = s.id AND sf.id_anno_scolastico = $id_anno_scolastico
    LEFT JOIN classi cl ON cl.id = sf.id_classe
    WHERE ci.id_corso = $id_corso
    ORDER BY s.cognome, s.nome
");

// Presenze già registrate
$presenze = dbGetAll("
    SELECT id_studente
    FROM corso_presenti
    WHERE id_data_corso = $id_data_corso
");

$mapPresenze = [];
foreach ($presenze as $p) {
    $mapPresenze[$p['id_studente']] = 1; // 1 = presente
}

// Aggiungi stato di presenza agli studenti
foreach ($studenti as &$s) {
    $s['presente'] = isset($mapPresenze[$s['id']]) ? $mapPresenze[$s['id']] : 0;
}

// Argomento già registrato
$arg = dbGetValue("
    SELECT argomento 
    FROM corso_argomenti 
    WHERE id_data_corso = $id_data_corso
");

echo json_encode([
    "success" => true,
    "studenti" => $studenti,
    "argomento" => $arg,
    "firmato" => $firmato
]);
