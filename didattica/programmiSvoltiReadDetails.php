<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

if(isset($_POST['programma_id']) && isset($_POST['programma_id']) != "") {
	$programma_id = $_POST['programma_id'];

    $query = "SELECT
            programmi_svolti.id as programma_id,
            programmi_svolti.id_classe as programma_classe,
            programmi_svolti.id_docente as programma_iddocente,
            programmi_svolti.id_materia as programma_idmateria,
            programmi_svolti.id_utente as programma_idutente,
            programmi_svolti.updated as programma_updated,
    		utente.id,
			utente.nome AS utente_nome,
			utente.cognome AS utente_cognome
        FROM
            programmi_svolti
		INNER JOIN utente utente
    	ON programmi_svolti.id_utente = utente.id
        WHERE programmi_svolti.id = '$programma_id'";

    $programma = dbGetFirst($query);

    $struct_json = json_encode($programma);
   echo json_encode($programma);
}
?>