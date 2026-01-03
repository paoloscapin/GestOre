<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

if(isset($_POST['bonus_docente_id']) && $_POST['bonus_docente_id'] != "") {
	$bonus_docente_id = intval($_POST['bonus_docente_id']);

	// leggi solo se appartiene al docente loggato (o dirigente)
	$query = "SELECT * FROM bonus_docente WHERE id = $bonus_docente_id";
	$response = dbGetFirst($query);

	echo json_encode($response);
}
?>
