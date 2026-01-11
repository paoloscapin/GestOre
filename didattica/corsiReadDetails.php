<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['corsi_id']) || $_POST['corsi_id'] === "") {
    echo json_encode(['success' => false, 'error' => 'Parametro corsi_id mancante']);
    exit;
}

$corsi_id = intval($_POST['corsi_id']);
$anno_corrente = intval($__anno_scolastico_corrente_id);

// 🔹 Dati del corso
$query = "
    SELECT c.id AS corso_id,
           c.id_materia AS materia_id,
           c.id_docente AS doc_id,
           c.titolo AS titolo,
           c.in_itinere AS in_itinere,
           c.carenza AS carenza,
           c.carenza_sessione AS carenza_sessione,
           c.id_anno_scolastico AS anno_id,
           c.prevede_esami AS prevede_esami,
           c.firma_policy AS firma_policy
    FROM corso c
    WHERE c.id = $corsi_id
";
$corso = dbGetFirst($query);

if (!$corso) {
    echo json_encode(['success' => false, 'error' => 'Corso non trovato']);
    exit;
}

$corso['prevede_esami'] = (intval($corso['carenza']) === 1 || intval($corso['in_itinere']) === 1)
    ? 1
    : intval($corso['prevede_esami'] ?? 0);

// ✅ Docenti associati (tabella corso_docenti)
$docenti = dbGetAll("
    SELECT cdn.id_docente, cdn.principale, d.cognome, d.nome
    FROM corso_docenti cdn
    INNER JOIN docente d ON d.id = cdn.id_docente
    WHERE cdn.id_corso = $corsi_id
    ORDER BY cdn.principale DESC, d.cognome ASC, d.nome ASC
");
if (!$docenti) $docenti = [];

// 🔹 Date del corso
$query = "
    SELECT 
        d.id AS data_id,
        d.id_corso AS corso_id,
        d.data_inizio AS corso_data_inizio,
        d.data_fine AS corso_data_fine,
        d.aula AS corso_aula
    FROM corso_date d
    WHERE d.id_corso = $corsi_id
    ORDER BY d.data_inizio ASC
";
$date = dbGetAll($query);
if (!$date) $date = [];

// 🔹 Studenti iscritti
if (intval($corso['carenza']) === 1) {

    $id_esame_t1 = dbGetValue("
        SELECT id
        FROM corso_esami_date
        WHERE id_corso = $corsi_id AND tentativo = 1
        LIMIT 1
    ");
    $id_esame_t1 = $id_esame_t1 ? intval($id_esame_t1) : 0;

    $query = "
        SELECT 
            i.id AS iscrizione_id,
            s.id AS stud_id,
            s.nome AS stud_nome,
            s.cognome AS stud_cognome,
            c.id AS classe_id,
            c.classe AS classe,

            e1.assenza_giustificata,
            e1.assenza_note,

            IF(e1.id IS NOT NULL, 1, 0) AS ha_esito,
            COALESCE(e1.presente, 0) AS presente,
            COALESCE(e1.recuperato, 0) AS recuperato,

            IF(cc2.id IS NOT NULL, 1, 0) AS secondo_tentativo,
            IF(ced2.firmato = 1, 1, 0) AS secondo_firmato,

            -- compat (vecchio nome)
            cc2.id_corso_secondo AS secondo_corso_id,

            -- ✅ nuovi campi per UI
            cc2.id_corso_secondo AS id_corso_secondo,
            c2.titolo AS titolo_corso_secondo,
            CASE
                WHEN c2.id IS NOT NULL AND LOWER(c2.titolo) LIKE '%recupero assenza%' THEN 1
                ELSE 0
            END AS recupero_assenza

        FROM corso_iscritti i
        INNER JOIN studente s 
            ON s.id = i.id_studente
        INNER JOIN studente_frequenta f 
            ON f.id_studente = s.id 
           AND f.id_anno_scolastico = $anno_corrente
        INNER JOIN classi c 
            ON c.id = f.id_classe

        LEFT JOIN corso_esiti e1
            ON e1.id_studente = s.id
           AND e1.id_corso = i.id_corso
           " . ($id_esame_t1 > 0 ? "AND e1.id_esame_data = $id_esame_t1" : "AND 1=0") . "

        LEFT JOIN corso_carenze_seconda cc2
            ON cc2.id_corso_primo = i.id_corso
           AND cc2.id_studente = s.id

        -- ✅ prendo anche titolo del corso 2
        LEFT JOIN corso c2
            ON c2.id = cc2.id_corso_secondo

        LEFT JOIN corso_esami_date ced2
            ON ced2.id_corso = cc2.id_corso_secondo
           AND ced2.tentativo = 1

        WHERE i.id_corso = $corsi_id
        ORDER BY s.cognome, s.nome
    ";

} else {

    $query = "
        SELECT 
            i.id AS iscrizione_id,
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
}

$studenti = dbGetAll($query);
if ($studenti == null) $studenti = [];

// 🔹 JSON finale
$struct_json = [
    'corso' => $corso,
    'docenti' => $docenti,
    'date' => $date,
    'studenti' => $studenti
];

echo json_encode($struct_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
