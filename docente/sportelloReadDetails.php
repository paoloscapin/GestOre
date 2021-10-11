<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST['sportello_id']) && isset($_POST['sportello_id']) != "") {
	$sportello_id = $_POST['sportello_id'];

    $query = "SELECT
            sportello.id as sportello_id,
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
            sportello.note as sportello_note,

            docente.cognome AS docente_cognome,
            docente.nome AS docente_nome,
            docente.id AS docente_id,

            materia.nome AS materia_nome,
            materia.id AS materia_id

        FROM
            sportello
        INNER JOIN docente
        ON sportello.docente_id = docente.id
        INNER JOIN materia
        ON sportello.materia_id = materia.id
        WHERE sportello.id = '$sportello_id';";

    $sportello = dbGetFirst($query);

    $studenti_list = array();

    $query = "SELECT
            sportello_studente.id AS sportello_studente_id,
            sportello_studente.iscritto AS sportello_studente_iscritto,
            sportello_studente.presente AS sportello_studente_presente,
            sportello_studente.argomento AS sportello_studente_argomento,
            sportello_studente.note AS sportello_studente_note,

            studente.cognome AS studente_cognome,
            studente.nome AS studente_nome,
            studente.id AS studente_id

        FROM
            sportello_studente
        INNER JOIN studente
        ON sportello_studente.studente_id = studente.id
        WHERE sportello_studente.sportello_id = '$sportello_id';";

    $studenti = dbGetAll($query);

    // $sportello += ["studenti" => $studenti];
    $sportello['studenti'] = $studenti;

	echo json_encode($sportello);
}
?>