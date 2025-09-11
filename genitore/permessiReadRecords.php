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
$__studente_id = $studente_filtro_id;

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th class="text-center col-md-1">Data</th>						
						<th class="text-center col-md-1">Ora uscita</th>
						<th class="text-center col-md-1">Ora rientro</th>
						<th class="text-center col-md-2">Studente</th>
						<th class="text-center col-md-2">Genitore</th>
						<th class="text-center col-md-3">Motivo</th>
						<th class="text-center col-md-1">Segreteria</th>
						<th class="text-center col-md-1">Azioni</th>
					</tr>
					</thead>';

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
				WHERE studente.id='$__studente_id'";


$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}
foreach ($resultArray as $row) {
	$id_permesso = $row['id'];
	$id_genitore = $row['id_genitore'];
	$genitore_nome = $row['genitore_nome'] . ' ' . $row['genitore_cognome'];
	$studente_nome = $row['studente_nome'] . ' ' . $row['studente_cognome'];
	$id_studente = $row['id_studente'];
	// Formattazione data e ora
	$data_it = date('d/m/Y', strtotime($row['data']));
	$ora_uscita = date('H:i', strtotime($row['ora_uscita']));
	$ora_rientro = date('H:i', strtotime($row['ora_rientro']));

	// Badge per lo stato
	switch ($row['stato']) {
		case 1:
			$badge = '<span class="badge bg-warning" style="background-color: yellow; color: black;">Richiesto</span>';
			break;
		case 2:
			$badge = '<span class="badge bg-success" style="background-color: green; color: white;">Confermato</span>';
			break;
		case 3:
			$badge = '<span class="badge bg-danger" style="background-color: red; color: white;">Rifiutato</span>';
			break;
		case 4:
			$badge = '<span class="badge bg-danger" style="background-color: red; color: white;">Assente</span>';
			break;
		default:
			$badge = '<span class="badge bg-secondary">Sconosciuto</span>';
	}
	$motivo = $row['motivo'];
	$stato = $row['stato'];

	$data .= '<tr>
		<td align="center">' . $data_it . '</td>
		<td align="center">' . $ora_uscita . '</td>
		<td align="center">' . $ora_rientro . '</td>
		<td align="center">' . $studente_nome . '</td>
		<td align="center">' . $genitore_nome . '</td>
		<td align="center">' . $motivo . '</td>
		<td align="center">' . $badge . '</td>
		<td align="center">';
		if ($stato == 1) { 
			$data .= '
			<button onclick="permessiGetDetails(\'' . $id_permesso . '\')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica la richiesta"><span class="glyphicon glyphicon-pencil"></span></button>
			<button onclick="permessiDelete(\'' . $id_permesso . '\')" class="btn btn-danger btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Cancella la richiesta"><span class="glyphicon glyphicon-trash"></span></button>
		</td>';
		}
		else {
			$data .= '-</td>';
		}

	$data .= '</tr>';
}

$data .= '</table></div>';

echo $data;
