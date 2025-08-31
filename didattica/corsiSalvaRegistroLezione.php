<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// include Database connection file
require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('docente', 'admin', 'segreteria-didattica');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// Recupero parametri dal POST
	$data_id   = isset($_POST['data_id'])   ? intval($_POST['data_id']) : 0;
	$corso_id  = isset($_POST['corso_id'])  ? intval($_POST['corso_id']) : 0;
	$argomenti = isset($_POST['argomenti']) ? trim($_POST['argomenti']) : "";
	$presenze  = isset($_POST['presenze'])  ? $_POST['presenze'] : [];
	$firmato = isset($_POST['firmato']) ? intval($_POST['firmato']) : 0;

	// Se presenze è JSON string, decodifichiamo
	if (is_string($presenze)) {
		$presenze = json_decode($presenze, true);
	}

	if (!$data_id || !$corso_id) {
		echo json_encode(["success" => false, "error" => "Parametri mancanti"]);
		exit;
	}

	// Qui puoi creare un record "lezione svolta" oppure flag in tabella corso_date
	$query = "UPDATE corso_date SET firmato = $firmato WHERE id = $data_id";
	dbExec($query);

	// cancello le presenze esistenti per questa data
	$query = "DELETE FROM corso_presenti WHERE id_data_corso = $data_id";
	dbExec($query);
	info("Cancellate presenze esistenti per id_data_corso=$data_id");
	// aggiorno i presenti
	foreach ($presenze as $presenza) {
		$id_studente = isset($presenza['id_studente']) ? intval($presenza['id_studente']) : 0;
		$presente = isset($presenza['presente']) && $presenza['presente'] ? 1 : 0;
		if ($presente) {
			$query = "INSERT INTO corso_presenti (id_data_corso, id_studente) VALUES ($data_id, $id_studente)";
			dbExec($query);
			info("Studente_id=$id_studente marcato come presente per id_data_corso=$data_id");
		} else {
			info("Studente_id=$id_studente marcato come assente per id_data_corso=$data_id");
		}
	}

	$query = "SELECT * FROM corso_argomenti WHERE id_data_corso = $data_id";
	$existing = dbGetFirst($query);

	if ($existing) {
		// aggiorno
		$query = "UPDATE corso_argomenti SET argomento = '" . $argomenti . "' WHERE id_data_corso = $data_id";
		dbExec($query);
		info("Aggiornati argomenti per id_data_corso=$data_id");
	} else {
		// inserisco
		$query = "INSERT INTO corso_argomenti (id_data_corso, argomento) VALUES ($data_id, '" . $argomenti . "')";
		dbExec($query);
		info("Inseriti argomenti per id_data_corso=$data_id");
	}
	// Risposta JSON compatibile con il JS
	echo json_encode([
		"success" => true,
		"id" => $corso_id
	]);
}
