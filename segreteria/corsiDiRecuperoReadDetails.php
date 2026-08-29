<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST['id']) && isset($_POST['id']) != "") {
	$id = $_POST['id'];

    $query = "SELECT
				corso_di_recupero.id AS corso_di_recupero_id,
				corso_di_recupero.codice AS corso_di_recupero_codice,
				corso_di_recupero.aula AS corso_di_recupero_aula,
				corso_di_recupero.numero_ore AS corso_di_recupero_numero_ore,
				materia.id AS materia_id,
				materia.nome AS materia_nome,
                docente.id AS docente_id,
                docente.cognome AS docente_cognome,
				docente.nome AS docente_nome
			FROM corso_di_recupero
			INNER JOIN materia materia
			ON corso_di_recupero.materia_id = materia.id
			INNER JOIN docente docente
			ON corso_di_recupero.docente_id = docente.id
            WHERE corso_di_recupero.id = $id
            ";
    $corsoDiRecupero = dbGetFirst($query);
	echo json_encode($corsoDiRecupero);
}
?>