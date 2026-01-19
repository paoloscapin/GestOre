<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
require_once '../common/checkSession.php';
require_once '../common/connect.php';

if (isset($_POST['sportello_id']) && $_POST['sportello_id'] !== "") {

    $sportello_id = intval($_POST['sportello_id']);

    $query = "SELECT
            sportello.id as sportello_id,
            sportello.attivo as sportello_attivo,
            sportello.data as sportello_data,
            sportello.ora as sportello_ora,
            sportello.numero_ore as sportello_numero_ore,
            sportello.argomento as sportello_argomento,
            sportello.luogo as sportello_luogo,
            sportello.classe as sportello_classe,
            sportello.max_iscrizioni as sportello_max_iscrizioni,
            sportello.firmato as sportello_firmato,
            sportello.cancellato as sportello_cancellato,
            sportello.online as sportello_online,
            sportello.clil AS sportello_clil,
            sportello.orientamento AS sportello_orientamento,
            sportello.note as sportello_note,
            sportello.docente_id as sportello_docente_id,
            docente.cognome AS docente_cognome,
            docente.nome AS docente_nome,
            COALESCE(docente.id, 0) AS docente_id,

            classe.id AS classe_id,
            materia.nome AS materia_nome,
            materia.id AS materia_id,
            sportello_categoria.id AS categoria_id,
            sportello_categoria.nome AS categoria_nome

        FROM sportello
        LEFT JOIN docente
            ON sportello.docente_id = docente.id
        INNER JOIN materia
            ON sportello.materia_id = materia.id
        INNER JOIN classe
            ON sportello.classe_id = classe.id
        INNER JOIN sportello_categoria
            ON sportello.categoria = sportello_categoria.nome
        WHERE sportello.id = $sportello_id
        LIMIT 1";

    $sportello = dbGetFirst($query);

    // se non trovato, rispondi comunque JSON valido
    if (!$sportello) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Sportello non trovato']);
        exit;
    }

    $query = "SELECT
            sportello_studente.id AS sportello_studente_id,
            sportello_studente.iscritto AS sportello_studente_iscritto,
            sportello_studente.presente AS sportello_studente_presente,
            sportello_studente.argomento AS sportello_studente_argomento,
            sportello_studente.note AS sportello_studente_note,

            studente.cognome AS studente_cognome,
            studente.nome AS studente_nome,
            studente.id AS studente_id

        FROM sportello_studente
        INNER JOIN studente
            ON sportello_studente.studente_id = studente.id
        WHERE sportello_studente.sportello_id = $sportello_id";

    $sportello['studenti'] = dbGetAll($query) ?: [];

    $struct_json = json_encode($sportello);
    info($struct_json);

    header('Content-Type: application/json');
    echo $struct_json;
}
?>
