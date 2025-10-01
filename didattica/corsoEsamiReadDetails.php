<?php
/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if (empty($_POST['corso_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parametro corso_id mancante']);
    exit;
}

$corso_id = intval($_POST['corso_id']);
$anno_corrente = intval($__anno_scolastico_corrente_id);

// 🔹 Date esame
$query = "
    SELECT e.id AS esame_id, e.id_corso, e.data_esame, e.aula, e.firmato
    FROM corso_esami_date e
    WHERE e.id_corso = $corso_id
    ORDER BY e.data_esame ASC
";
$esami = dbGetAll($query);

// 🔹 Studenti iscritti al corso con eventuali esiti
$query = "
    SELECT 
        i.id AS iscrizione_id,
        s.id AS stud_id,
        s.cognome,
        s.nome,
        c.classe,
        es.id AS esito_id,
        es.id_esame_data,
        es.presente,
        es.tipo_prova,
        es.voto,
        es.argomenti,
        es.inviato_registro
    FROM corso_iscritti i
    INNER JOIN studente s ON s.id = i.id_studente
    INNER JOIN studente_frequenta f ON f.id_studente = s.id AND f.id_anno_scolastico = $anno_corrente
    INNER JOIN classi c ON c.id = f.id_classe
    LEFT JOIN corso_esiti es ON es.id_studente = s.id AND es.id_corso = i.id_corso
    WHERE i.id_corso = $corso_id
    ORDER BY s.cognome, s.nome
";
$studenti = dbGetAll($query);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success'   => true,
    'esami'     => $esami,
    'studenti'  => $studenti
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
