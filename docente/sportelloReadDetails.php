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
            sportello.firmato as sportello_firmato,
            sportello.cancellato as sportello_cancellato,
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
	echo json_encode($sportello);
}
?>