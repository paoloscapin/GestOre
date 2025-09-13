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

$studente_filtro_id = $_GET["studente_filtro_id"] ?? null;
$data_filtro = $_GET["data_filtro"] ?? null;
$solo_richiesti = $_GET["solo_richiesti"] ?? 0;

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
<thead>
<tr>
    <th class="text-center col-md-1">Data</th>
    <th class="text-center col-md-1 sortable" data-sort="classe">Classe</th>
    <th class="text-center col-md-1 sortable" data-sort="ora_uscita">Ora uscita</th>
    <th class="text-center col-md-2 sortable" data-sort="studente">Studente</th>
    <th class="text-center col-md-1">Ora rientro</th>
    <th class="text-center col-md-2">Genitore</th>
    <th class="text-center col-md-1">Motivo</th>
    <th class="text-center col-md-1">Segreteria</th>
    <th class="text-center col-md-1">Note segreteria</th>
    <th class="text-center col-md-1">Azioni</th>
</tr>
</thead>
<tbody>';

$query = "	SELECT 
					permessi_uscita.id,
					permessi_uscita.id_studente,
					permessi_uscita.id_genitore,
					permessi_uscita.data,
					permessi_uscita.ora_uscita,
					permessi_uscita.ora_rientro,
					permessi_uscita.rientro,
					permessi_uscita.motivo,
					permessi_uscita.stato,
					permessi_uscita.note_segreteria as note_segreteria,
					genitori.nome AS genitore_nome,
					genitori.cognome AS genitore_cognome,
					studente.nome AS studente_nome,
					studente.cognome AS studente_cognome,
					classi.classe AS classe,
					studente_frequenta.id_classe AS id_classe
				FROM permessi_uscita
				INNER JOIN genitori genitori
				ON permessi_uscita.id_genitore = genitori.id
				INNER JOIN studente_frequenta
				ON studente_frequenta.id_studente = permessi_uscita.id_studente AND studente_frequenta.id_anno_scolastico = '$__anno_scolastico_corrente_id'
				INNER JOIN classi classi
				ON classi.id = studente_frequenta.id_classe
				INNER JOIN studente studente
				ON permessi_uscita.id_studente = studente.id
				WHERE 1=1";

if ($studente_filtro_id != 0 && $studente_filtro_id != null) {
	$query .= " AND permessi_uscita.id_studente = '$studente_filtro_id'";
}
if ($data_filtro != null && $data_filtro != "") {
    $query .= " AND permessi_uscita.data = '$data_filtro'";
}
if ($solo_richiesti == 1) {
    $query .= " AND permessi_uscita.stato = 1";
}
$query .= "	ORDER BY permessi_uscita.data ASC, permessi_uscita.ora_uscita ASC, classi.classe ASC, studente.cognome ASC, studente.nome ASC";


$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}
	function formatName($string) {
    $string = strtolower($string); // tutto minuscolo
    return mb_convert_case($string, MB_CASE_TITLE, "UTF-8"); // ogni parola con iniziale maiuscola
}
	foreach ($resultArray as $row) {
	$id_permesso = $row['id'];
	$id_genitore = $row['id_genitore'];
	$genitore_nome = formatName($row['genitore_nome']) . ' ' . formatName($row['genitore_cognome']);
	$studente_nome = formatName($row['studente_nome']) . ' ' . formatName($row['studente_cognome']);
	$id_studente = $row['id_studente'];
	// Formattazione data e ora
	$data_it = date('d/m/Y', strtotime($row['data']));
	$ora_uscita = date('H:i', strtotime($row['ora_uscita']));
	$ora_rientro = date('H:i', strtotime($row['ora_rientro']));
	$note = $row['note_segreteria'] ?? '';
	$classe = $row['classe'] ?? '';
	// Badge per lo stato
	switch ($row['stato']) {
		case 1:
			$badge = '<span class="badge bg-warning" style="background-color: yellow; color: black;">Richiesto</span>';
			break;
		case 2:
			$badge = '<span class="badge bg-success" style="background-color: green; color: white;">Confermato</span>';
			break;
		case 3:
			$badge = '<span class="badge bg-danger" style="background-color: red; color: white;">Assente</span>';
			break;
		case 4:
			$badge = '<span class="badge bg-danger" style="background-color: red; color: white;">Rifiutato</span>';
			break;
		default:
			$badge = '<span class="badge bg-secondary">Sconosciuto</span>';
	}
	$motivo = $row['motivo'];
	$stato = $row['stato'];

	$data .= '<tr>
		<td align="center">' . $data_it . '</td>
		<td align="center">' . $classe . '</td>
		<td align="center">' . $ora_uscita . '</td>
		<td align="center">' . $studente_nome . '</td>
		<td align="center">' . $ora_rientro . '</td>
		<td align="center">' . $genitore_nome . '</td>
		<td align="center">' . $motivo . '</td>
		<td align="center">' . $badge . '</td>
		<td align="center">' . nl2br(htmlspecialchars($note)) . '</td>
		<td align="center">
		<button onclick="permessiGetDetails(\'' . $id_permesso . '\')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-placement="top" title="Modifica la richiesta"><span class="glyphicon glyphicon-pencil"></span></button>
		<button onclick="permessiDelete(\'' . $id_permesso . '\')" class="btn btn-danger btn-xs" data-toggle="tooltip" data-placement="top" title="Cancella la richiesta"><span class="glyphicon glyphicon-trash"></span></button>';
	if ($stato == 1) {
		$data .= '
		<button onclick="permessoConfirm(\'' . $id_permesso . '\')" class="btn btn-primary btn-xs" data-toggle="tooltip" data-placement="top" title="Approva la richiesta"><span class="glyphicon glyphicon-ok"></span></button>';
	}
	$data .= '</td>';

	$data .= '</tr>';
}

$data .= '</tbody></table></div>';

echo $data;
