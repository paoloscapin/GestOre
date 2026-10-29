<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('studente','segreteria-didattica','dirigente');

if(isset($_POST)) {
	$richiesta_id = $_POST['richiesta_id'];
	$uuid = escapePost('uuid');
	$data_assemblea = $_POST['data_assemblea'];
	$ora_assemblea = $_POST['ora_assemblea'];
	$classe = $_POST['classe'];
	$stato = $_POST['stato'];
	$docente_id = $_POST['docente_ora_id'];
	$coordinatore_id = $_POST['docente_coordinatore_id'];
	$ordine_del_giorno = escapePost('ordine_del_giorno');

	if ($richiesta_id > 0) {
		$query = "UPDATE richiesta_assemblea SET data_assemblea = '$data_assemblea', ora_assemblea = '$ora_assemblea', classe = '$classe', stato = '$stato', docente_id = '$docente_id', coordinatore_id = '$coordinatore_id', `ordine_del_giorno` = '$ordine_del_giorno', `ultima_modifica_richiesta` = CURRENT_TIMESTAMP ;";
		dbExec($query);
		info("aggiornato richiesta_assemblea id=$richiesta_id data_assemblea=$data_assemblea ora_assemblea=$ora_assemblea classe=$classe stato=$stato docente_id=$docente_id coordinatore_id=$coordinatore_id");
	} else {
		$query = "INSERT INTO `richiesta_assemblea`(`uuid`, `data_assemblea`, `ora_assemblea`, `classe`, `stato`, `approvata_docente`, `messaggio_docente`, `approvata_coordinatore`, `messaggio_coordinatore`, `ordine_del_giorno`, `verbale`, `inoltro_richiesta`, `ultima_modifica_richiesta`, `ultima_modifica_verbale`, `studente_id`, `docente_id`, `coordinatore_id`, `anno_scolastico_id`)
				VALUES('$uuid', '$data_assemblea', '$ora_assemblea', '$classe', 'in attesa', 0, '', 0, '', '$ordine_del_giorno', '', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, '$__studente_id', '$docente_id', '$coordinatore_id', '$__anno_scolastico_corrente_id')";
		dbExec($query);
		$last_id = dblastId();
		info("aggiunto richiesta_assemblea id=$last_id docente_id=$docente_id data_assemblea=$data_assemblea ora_assemblea=$ora_assemblea classe=$classe stato=$stato");
	}
}
?>
