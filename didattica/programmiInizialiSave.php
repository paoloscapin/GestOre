<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('segreteria-didattica', 'docente');

function ensureProgrammiInizialiClassiTable(): void
{
	dbExec("
		CREATE TABLE IF NOT EXISTS programmi_iniziali_classi (
			id INT NOT NULL AUTO_INCREMENT,
			id_programma_iniziale INT NOT NULL,
			id_classe INT NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_programma_classe (id_programma_iniziale, id_classe)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8
	");
}

function aggiornaClassiProgrammaIniziale(int $idProgramma, array $classiProgramma): void
{
	if ($idProgramma <= 0) {
		return;
	}

	ensureProgrammiInizialiClassiTable();
	dbExec("DELETE FROM programmi_iniziali_classi WHERE id_programma_iniziale=" . intval($idProgramma));

	foreach ($classiProgramma as $idClasse) {
		$idClasse = intval($idClasse);
		if ($idClasse > 0) {
			dbExec("
				INSERT IGNORE INTO programmi_iniziali_classi
					(id_programma_iniziale, id_classe)
				VALUES
					(" . intval($idProgramma) . ", " . $idClasse . ")
			");
		}
	}
}

if (isset($_POST)) {

	$id = $_POST['id'];
	$docente_id = intval($_POST['docente_id']);
	$classe_id_post = $_POST['classe_id'];
	$classe_tipo = $_POST['classe_tipo'] ?? 'classe';
	$classi_collegate_post = $_POST['classi_collegate'] ?? '';
	$materia_id = intval($_POST['materia_id']);
	$classi_programma = [];

	if ($classe_tipo === 'articolata') {
		foreach (explode(',', $classi_collegate_post) as $idc) {
			$idc = intval($idc);
			if ($idc > 0) {
				$classi_programma[] = $idc;
			}
		}
		$classe_id = count($classi_programma) > 0 ? $classi_programma[0] : 0;
	} else {
		$classe_id = intval($classe_id_post);
		if ($classe_id > 0) {
			$classi_programma[] = $classe_id;
		}
	}

	$classi_programma = array_values(array_unique($classi_programma));
	if ($classe_id <= 0 || count($classi_programma) === 0) {
		die('Classe non valida');
	}

	$duplica = $_POST['duplica'];
	$share = $_POST['share'];
	date_default_timezone_set("Europe/Rome");
	$update = date("Y-m-d H-i-s");
	$utente_id = $__utente_id;
	$data = '';
	if (($duplica == 'false') && ($share == 'false')) {
		if ($id > 0) {
			$query = "UPDATE programmi_iniziali SET id_classe = '$classe_id', id_docente = '$docente_id', id_materia = '$materia_id', id_utente = '$utente_id', updated = '$update' WHERE id = '$id'";
			dbExec($query);
			aggiornaClassiProgrammaIniziale(intval($id), $classi_programma);
			info("aggiornato programma iniziale id=$id  id_classe=$classe_id id_docente=$docente_id id_materia=$materia_id id_utente=$utente_id updated=$update");
		} else {
			$query = "INSERT INTO programmi_iniziali(id_classe, id_docente, id_materia, id_anno_scolastico, id_utente, updated) VALUES('$classe_id', '$docente_id', '$materia_id', '$__anno_scolastico_corrente_id', '$utente_id', '$update')";
			dbExec($query);
			$new_id = dblastId();
			aggiornaClassiProgrammaIniziale(intval($new_id), $classi_programma);
			$data = $new_id;
			info("aggiunto programma iniziale id=$new_id  id_classe=$classe_id id_docente=$docente_id id_materia=$materia_id id_anno_scolastico=$__anno_scolastico_corrente_id id_utente=$utente_id updated=$update");
		}
	} else if ($duplica == 'true')
	{

		// verifico se esiste già la classe su cui voglio duplicare il programma
		$query = "SELECT * from programmi_iniziali WHERE id_classe='$classe_id' AND id_docente='$docente_id' AND id_materia='$materia_id'";
		$result = dbGetFirst($query);
		
		if ($result!=null)
		{
		  $data = 'Programma già esistente';	
		}
		else
		{
			// creo il programma vuoto per la nuova classe
			$query = "INSERT INTO programmi_iniziali(id_classe, id_docente, id_materia, id_anno_scolastico, id_utente, updated) VALUES('$classe_id', '$docente_id', '$materia_id', '$__anno_scolastico_corrente_id', '$utente_id', '$update')";
			dbExec($query);
			$new_id = dblastId();
			aggiornaClassiProgrammaIniziale(intval($new_id), $classi_programma);
			info("aggiunto programma iniziale id=$new_id  id_classe=$classe_id id_docente=$docente_id id_materia=$materia_id id_anno_scolastico=$__anno_scolastico_corrente_id id_utente=$utente_id updated=$update");

			// duplico i moduli collegati al programma originale
			$query = "INSERT INTO programmi_iniziali_moduli (id_programma, ordine, nome, conoscenze, abilita, competenze, periodo, id_utente, updated)
			SELECT $new_id AS id_programma, ordine, nome, conoscenze, abilita, competenze, periodo, id_utente, NOW() AS updated FROM programmi_iniziali_moduli WHERE id_programma = $id";
			dbExec($query);
			info("duplicati i moduli del programma iniziale id=$id e li ho collegati al nuovo programma iniziale id=$new_id");
		}
	}
	else if ($share == 'true')
	{
		// verifico se esiste già la classe su cui voglio duplicare il programma
		$query = "SELECT * from programmi_iniziali WHERE id_classe='$classe_id' AND id_docente='$docente_id' AND id_materia='$materia_id'";
		$result = dbGetFirst($query);
		
		if (($result!=null)&&($overwrite!='true'))
		{
		  $data = 'Programma già esistente';	
		}
		else
		{
			// creo il programma vuoto per la nuova classe
			$query = "INSERT INTO programmi_iniziali(id_classe, id_docente, id_materia, id_anno_scolastico, id_utente, updated) VALUES('$classe_id', '$docente_id', '$materia_id', '$__anno_scolastico_corrente_id', '$utente_id', '$update')";
			dbExec($query);
			$new_id = dblastId();
			aggiornaClassiProgrammaIniziale(intval($new_id), $classi_programma);
			info("aggiunto programma iniziale id=$new_id  id_classe=$classe_id id_docente=$docente_id id_materia=$materia_id id_anno_scolastico=$__anno_scolastico_corrente_id id_utente=$utente_id updated=$update");
			// duplico i moduli collegati al programma originale
			$query = "INSERT INTO programmi_iniziali_moduli (id_programma, ordine, nome, conoscenze, abilita, competenze, periodo, id_utente, updated)
			SELECT $new_id AS id_programma, ordine, nome, conoscenze, abilita, competenze, periodo, id_utente, NOW() AS updated FROM programmi_iniziali_moduli WHERE id_programma = $id";
			dbExec($query);
			info("duplicati per il docente id=$docente_id i moduli del programma iniziale id=$id e li ho collegati al nuovo programma iniziale id=$new_id");
		}
	}
	echo $data;
}
?>
