<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// include Database connection file
require_once '../common/checkSession.php';
require_once '../common/connect.php';

$docente_id = intval($_GET["id_docente"] ?? 0);
$classe_id = intval($_GET["id_classe"] ?? 0);
$materia_id = intval($_GET["id_materia"] ?? 0);
$studente_id = intval($_GET["id_studente"] ?? 0);
$anno = intval($_GET["id_anno"] ?? 0);
$da_validare = intval($_GET["da_validare"] ?? 0);
$vistaDocente = intval($_GET["vista_docente"] ?? 0) === 1;
$docente_scope_filtro = intval($_GET["docente_scope_filtro"] ?? 1);
$isDocenteView = $vistaDocente || (($__utente_ruolo ?? '') === 'docente');
$docenteVedeSoloLeSue = (getSettingsValue('config', 'carenzeObiettiviMinimi', false))
	&& (getSettingsValue('carenzeObiettiviMinimi', 'visibile_docenti', false))
	&& (getSettingsValue('carenzeObiettiviMinimi', 'docente_vede_solo_le_sue', false));

$query = "	SELECT
					carenze.id AS carenza_id,
					carenze.id_studente AS carenza_id_studente,
					carenze.id_materia AS carenza_id_materia,
					carenze.id_classe AS carenza_id_classe,
					carenze.id_docente AS carenza_id_docente,
					carenze.id_anno_scolastico AS carenza_id_anno_scolastico,
					carenze.stato AS carenza_stato,
					carenze.data_inserimento AS carenza_inserimento,
					carenze.data_validazione AS carenza_validazione,
					carenze.data_invio AS carenza_invio,
					studente.cognome AS stud_cognome,
					studente.nome AS stud_nome,
					classi.classe AS classe,
					docente.cognome AS doc_cognome,
					docente.nome AS doc_nome,
					TRIM(CONCAT(COALESCE(docente.cognome, ''), ' ', COALESCE(docente.nome, ''))) AS docente,
					materia.nome AS materia
				FROM carenze
				LEFT JOIN docente docente
				ON carenze.id_docente = docente.id
				INNER JOIN studente studente
				ON carenze.id_studente = studente.id
				INNER JOIN materia materia
				ON carenze.id_materia = materia.id
				INNER JOIN classi classi
				ON carenze.id_classe = classi.id
				WHERE carenze.id_anno_scolastico=$__anno_scolastico_corrente_id";

if ($isDocenteView && $docente_id > 0 && ($docente_scope_filtro > 0 || $docenteVedeSoloLeSue)) {
	$query .= " AND EXISTS (
		SELECT 1
		FROM docente_insegna di
		WHERE di.id_docente = " . intval($docente_id) . "
		  AND di.id_classe = carenze.id_classe
		  AND di.id_materia = carenze.id_materia
		  AND di.id_anno_scolastico = carenze.id_anno_scolastico
	)";
} else if (!$isDocenteView && $docente_id > 0) {
	$query .= " AND carenze.id_docente=" . $docente_id;
}
if ($classe_id > 0) {
	$query .= " AND carenze.id_classe=" . $classe_id;
}
if ($materia_id > 0) {
	$query .= " AND carenze.id_materia=" . $materia_id;
}
if ($studente_id > 0) {
	$query .= " AND carenze.id_studente=" . $studente_id;
}
if ($anno > 0) {
	$query .= " AND classi.classe LIKE '" . $anno . "%' ";
}
if ($da_validare > 0) {
	$query .= " AND carenze.stato='0' ";
}

$query .= " ORDER BY studente.cognome, studente.nome ASC";

$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}

// Nome del file da scaricare
$filename = "report_carenze_" . date("Ymd_His") . ".csv";

// Mappa dei campi database => etichette CSV personalizzate
$columns = [
    "carenza_stato" => "Stato Carenza",
    "stud_cognome" => "Cognome Studente",
    "stud_nome" => "Nome Studente",
    "classe" => "Classe",
    "docente" => "Docente",
    "materia" => "Materia"
];

// Header HTTP per forzare il download del CSV
header('Content-Type: text/csv; charset=utf-8');
header("Content-Disposition: attachment; filename=\"$filename\"");

// Scrittura su output
$output = fopen('php://output', 'w');

// Scrivi intestazione CSV (etichette personalizzate)
fputcsv($output, array_values($columns));

// Scrivi i dati
foreach ($resultArray as $row) 
{
    $csvRow = [];
    foreach ($columns as $field => $label) 
	{
		if ($field === 'doc_cognome' && $row[$field] === 'Tutti') 
		{
            // Sostituisci "Tutti" con stringa vuota
            $csvRow[] = '';
        } 
		else 
		{
			if ($field === 'carenza_stato' && $row[$field] === '0') 
			{
				// Sostituisci "0" con "da validare"
				$csvRow[] = 'da validare';
			} 
			else  
			{
				if ($field === 'carenza_stato' && $row[$field] === '1') 
				{
					// Sostituisci "1" con "validato"
					$csvRow[] = 'validato';
				} 
				else  
				{
					if ($field === 'carenza_stato' && $row[$field] === '2') 
					{
						// Sostituisci "2" con "inviato"
						$csvRow[] = 'inviato';
					} 
					else 
					{
						// Valore normale
						$docente = trim(($row['doc_cognome'] ?? '') . ' ' . ($row['doc_nome'] ?? ''));
						$csvRow[] = $row[$field] ?? '';
					}
				}
			}
		}
	}

    fputcsv($output, $csvRow);
}
fclose($output);
