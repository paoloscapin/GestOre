<?php
/**
 *  This file is part of GestOre
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('segreteria-docenti', 'dirigente', 'docente');

// anno selezionato dal client (fallback: corrente)
$anno = isset($_POST['anno_scolastico_id']) ? intval($_POST['anno_scolastico_id']) : intval($__anno_scolastico_corrente_id);

// 🔒 Regola: il docente può modificare SOLO anno corrente e solo se adesioni aperte
if (!$__config->getBonus_adesione_aperto()) {
	http_response_code(403);
	exit;
}
if ($anno !== intval($__anno_scolastico_corrente_id)) {
	http_response_code(403);
	exit;
}

if (!isset($_POST['adesione_id']) || !isset($_POST['bonus_id'])) {
	http_response_code(400);
	exit;
}

$adesione_id = intval($_POST['adesione_id']);
$bonus_id = intval($_POST['bonus_id']);

if ($bonus_id <= 0) {
	http_response_code(400);
	exit;
}

// INSERT nuova adesione
if ($adesione_id < 0) {

	// evita duplicati accidentali (stesso docente+anno+bonus)
	$exists = dbGetValue("
		SELECT COUNT(*)
		FROM bonus_docente
		WHERE docente_id = $__docente_id
		  AND anno_scolastico_id = $anno
		  AND bonus_id = $bonus_id
	");
	if (intval($exists) > 0) {
		// se già esiste, ritorna l'id esistente così la UI si allinea
		$existingId = dbGetValue("
			SELECT id
			FROM bonus_docente
			WHERE docente_id = $__docente_id
			  AND anno_scolastico_id = $anno
			  AND bonus_id = $bonus_id
			LIMIT 1
		");
		echo intval($existingId);
		exit;
	}

	$query = "
		INSERT INTO bonus_docente (approvato, docente_id, anno_scolastico_id, bonus_id)
		VALUES (NULL, $__docente_id, $anno, $bonus_id);
	";
	dbExec($query);
	echo dblastId();
	exit;
}

// DELETE adesione esistente (solo se dell'utente e dell'anno corrente)
$query = "
	DELETE FROM bonus_docente
	WHERE id = $adesione_id
	  AND docente_id = $__docente_id
	  AND anno_scolastico_id = $anno
	LIMIT 1;
";
dbExec($query);
exit;
