<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

 require_once '../common/checkSession.php';
 ruoloRichiesto('segreteria-didattica');

 $tableName = "sportello";
 if(isset($_POST)) {
	$id = $_POST['id'];
	$data = $_POST['data'];
	$ora = $_POST['ora'];
	$docente_id = $_POST['docente_id'];
	$materia_id = $_POST['materia_id'];
	$numero_ore = $_POST['numero_ore'];
	$argomento = escapePost('argomento');
	$luogo = escapePost('luogo');
	$classe = escapePost('classe');
	$cancellato = $_POST['cancellato'];
	$firmato = $_POST['firmato'];

	if ($id > 0) {
        $query = "UPDATE $tableName SET data = '$data', ora = '$ora', docente_id = '$docente_id', materia_id = '$materia_id', numero_ore = '$numero_ore', argomento = '$argomento', luogo = '$luogo', classe = '$classe', cancellato = $cancellato, firmato = $firmato WHERE id = '$id'";
        dbExec($query);
        info("aggiornato $tableName id=$id data=$data ora=$ora docente_id=$docente_id materia_id=$materia_id numero_ore=$numero_ore argomento=$argomento luogo=$luogo classe=$classe");
    } else {
        $query = "INSERT INTO $tableName(data, ora, docente_id, materia_id, numero_ore, argomento, luogo, classe, anno_scolastico_id) VALUES('$data', '$ora', '$docente_id', '$materia_id', '$numero_ore', '$argomento', '$luogo', '$classe', $__anno_scolastico_corrente_id)";
        dbExec($query);
        $id = dblastId();
		info("aggiunto sportello id=$id data=$data ora=$ora docente_id=$docente_id materia_id=$materia_id numero_ore=$numero_ore argomento=$argomento luogo=$luogo classe=$classe");
    }
}
?>
