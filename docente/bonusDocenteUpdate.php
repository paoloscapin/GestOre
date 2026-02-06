<?php
/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
require_once '../common/checkSession.php';
require_once '../common/connect.php';

// ruoli ammessi: docente + segreteria-docenti + dirigente
ruoloRichiesto('segreteria-docenti', 'dirigente', 'docente');

header('Content-Type: application/json; charset=utf-8');

function json_out($success, $message) {
	echo json_encode(['success' => $success, 'message' => $message]);
	exit;
}

if (!isset($_POST['bonus_docente_id']) || !isset($_POST['rendiconto'])) {
	json_out(false, 'Parametri mancanti');
}

if (!$__config->getBonus_rendiconto_aperto()) {
	json_out(false, 'Rendiconto chiuso: non è possibile modificare');
}

$bonus_docente_id = intval($_POST['bonus_docente_id']);
$rendiconto = mysqli_real_escape_string($con, $_POST['rendiconto']);

// verifica anno scolastico della riga (deve essere quello corrente)
$row = dbGetFirst("SELECT id, docente_id, anno_scolastico_id FROM bonus_docente WHERE id = $bonus_docente_id;");
if (!$row) {
	json_out(false, 'Record non trovato');
}

if (intval($row['anno_scolastico_id']) !== intval($__anno_scolastico_corrente_id)) {
	json_out(false, 'Puoi modificare solo l’anno scolastico corrente');
}

// se non sei dirigente, puoi modificare solo le tue righe
// (segreteria-docenti e docente: vincolo; dirigente: può modificare tutto)
$ownerClause = "";
if (!haRuolo('dirigente')) {
	$ownerClause = " AND docente_id = " . intval($__docente_id) . " ";
}

// update
$query = "
	UPDATE bonus_docente
	SET rendiconto_evidenze = '$rendiconto'
	WHERE id = $bonus_docente_id
	$ownerClause
	LIMIT 1
";
dbExec($query);

json_out(true, 'Salvato');
