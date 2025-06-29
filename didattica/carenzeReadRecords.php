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

$docente_id = $_GET["docente_id"];
$classe_id = $_GET["classe_id"];
$materia_id = $_GET["materia_id"];
$studente_id = $_GET["studente_id"];
$anno = $_GET["anno"];
$da_validare_filtro = $_GET["da_validare_filtro"];

// Design initial table header
$data = '<style>
  .col-md-2-custom {
    width: 20%;
  }
  .col-md-1-custom {
    width: 10%;
  }
  .col-md-1-2-custom {
    width: 12%;
  }
  .col-md-1-5-custom {
    width: 15%;
  }
  .col-md-0-5-custom {
    width: 5%;
  }

</style>
<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th class="text-center col-md-1-2-custom">Studente</th>
						<th class="text-center col-md-2">Materia</th>
						<th class="text-center col-md-0-5-custom">Classe</th>
						<th class="text-center col-md-1-2-custom">Docente</th>
						<th class="text-center col-md-0-5-custom">Stato</th>
						<th class="text-center col-md-1-custom">Data inserimento</th>
						<th class="text-center col-md-1-custom">Data validazione</th>
						<th class="text-center col-md-1-custom">Data invio</th>
						<th class="text-center col-md-1">Azioni</th>
					</tr>
					</thead>';

$query = " SELECT utente.id AS utente_id,
			docente.cognome AS doc_cognome,
			docente.nome AS doc_nome,
			docente.id AS doc_id
			FROM utente
			INNER JOIN docente docente
			ON ((docente.cognome = utente.cognome) AND (docente.nome = utente.nome))
			WHERE utente.id = '$__utente_id'";
$result = dbGetFirst($query);
$id_docente_attuale = $result['doc_id'];

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
					carenze.nota_docente AS carenza_nota,
					studente.cognome AS stud_cognome,
					studente.nome AS stud_nome,
					classi.classe AS classe,
					docente.id AS doc_id,
					docente.cognome AS doc_cognome,
					docente.nome AS doc_nome,
					materia.nome AS materia
				FROM carenze
				INNER JOIN docente docente
				ON carenze.id_docente = docente.id
				INNER JOIN studente studente
				ON carenze.id_studente = studente.id
				INNER JOIN materia materia
				ON carenze.id_materia = materia.id
				INNER JOIN classi classi
				ON carenze.id_classe = classi.id
				WHERE carenze.id_anno_scolastico=$__anno_scolastico_corrente_id";

if ($docente_id > 0) {
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
if ($da_validare_filtro > 0) {
	$query .= " AND carenze.stato='0' ";
}

$query .= " ORDER BY studente.cognome, studente.nome, materia.nome ASC";

$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}

$ncarenze = 0;
foreach ($resultArray as $row) {
	$docente_riga_id = $row['doc_id'];
	$ncarenze++;
	$idcarenza = $row['carenza_id'];
	$nota = $row['carenza_nota'];
	$studente = $row['stud_cognome'] . ' ' . $row['stud_nome'];
	if ($row['carenza_id_docente'] == 0) {
		$docente = '';
	} else {
		$docente = $row['doc_cognome'] . ' ' . $row['doc_nome'];
	}
	$materia = $row['materia'];
	$classe = $row['classe'];
	$stato = $row['carenza_stato'];

	$data_inserimento = $row['carenza_inserimento'];
	$phpdate = strtotime($data_inserimento);
	$data_inserimento = date('d-m-Y', $phpdate) . " alle ore " . date('H:i:s', $phpdate);
	if ($stato > 0) {
		$data_validazione = $row['carenza_validazione'];
		$phpdate = strtotime($data_validazione);
		$data_validazione = date('d-m-Y', $phpdate) . " alle ore " . date('H:i:s', $phpdate);
	} else {
		$data_validazione = 'da validare';
	}

	if ($stato > 1) {
		$data_invio = $row['carenza_invio'];
		$phpdate = strtotime($data_invio);
		$data_invio = date('d-m-Y', $phpdate) . " alle ore " . date('H:i:s', $phpdate);
	} else {
		$data_invio = 'da inviare';
	}
	$data .= '<tr>
		<td align="center">' . $studente . '</td>
		<td align="center">' . $materia . '</td>
		<td align="center">' . $classe . '</td>
		<td align="center">' . $docente . '</td>';

	$statoMarker = '';
	if ($stato == 0) {
		$statoMarker .= '<span class="label label-primary">inserito</span>';
	} else {
		if ($stato > 1) {
			$statoMarker .= '<span class="label label-warning">inviato</span>';
		} else {
			if ($stato > 0) {
				$statoMarker .= '<span class="label label-success">validato</span>';
			}
		}
	}

	$data .= '<td align="center">' . $statoMarker . '</td>
		<td align="center">' . $data_inserimento . '</td>
		<td align="center">' . $data_validazione . '</td>
		<td align="center">' . $data_invio . '</td>
		';
	$data .= '
		<td class="text-center">';

	if ((haRuolo('dirigente')) || (haRuolo('segreteria-didattica'))) {
		$data .= '
			<button onclick="carenzeGetDetails(\'' . $idcarenza . '\')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica la carenza"><span class="glyphicon glyphicon-pencil"></button>
			<button onclick="carenzaDelete(\'' . $idcarenza . '\',\'' . $materia . '\',\'' . $studente . '\')" class="btn btn-danger btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Cancella la carenza"><span class="glyphicon glyphicon-trash"></button>';
		if ($stato == 0) {
			$data .= '
				<button onclick="carenzaValida(\'' . $idcarenza . '\',\'' . $__utente_id . '\',\'' . $stato . '\')" class="btn btn-primary btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Conferma la carenza"><span class="glyphicon glyphicon-warning-sign"></button>';
		} else {
			if ($stato == 1) {
				$data .= '
				<button onclick="carenzaValida(\'' . $idcarenza . '\',\'' . $__utente_id . '\',\'' . $stato . '\')" class="btn btn-success btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Rimuovi la conferma della carenza - Nota attualmente inserita - ' . $nota . '"><span class="glyphicon glyphicon-ok"></button>';
			}
			$data .= '
			<button onclick="carenzaPrint(\'' . $idcarenza . '\')" class="btn btn-info btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Genera il PDF della carenza che arriva alla studente"><span class="glyphicon glyphicon-print"></button>
			<button onclick="carenzaSend(\'' . $idcarenza . '\')" class="btn btn-primary btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Invia la mail della carenza allo studente"><span class="glyphicon glyphicon-send"></button>';
		}
	} else
		if (haRuolo('docente')) {
			if (getSettingsValue('config', 'carenzeObiettiviMinimi', false)) {
				if (getSettingsValue('carenzeObiettiviMinimi', 'visibile_docenti', false)) {
					if (getSettingsValue('carenzeObiettiviMinimi', 'docente_puo_modificare', false)) {
						if ($stato == 0) {
							$data .= '
								<button onclick="hideTooltip(this); carenzaValida(\'' . $idcarenza . '\',\'' . $__utente_id . '\',\'' . $stato . '\')" class="btn btn-primary btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Conferma la carenza"><span class="glyphicon glyphicon-warning-sign"></button>';
						} else {
							if ($stato == 1) {
								if ($docente_riga_id == $id_docente_attuale) {
									$data .= '
								<button onclick="hideTooltip(this); carenzaValida(\'' . $idcarenza . '\',\'' . $__utente_id . '\',\'' . $stato . '\')" class="btn btn-success btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Rimuovi la conferma della carenza - Nota attualmente inserita - ' . $nota . '"><span class="glyphicon glyphicon-ok"></button>';
								} else {
									$data .= '
								<button onclick="hideTooltip(this)" class="btn btn-danger btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Non puoi modificare la carenza confermata da un altro docente"><span class="glyphicon glyphicon-ok"></button>';
								}
							}
							// $data .= '
							// <button onclick="carenzaPrint(\'' . $idcarenza . '\')" class="btn btn-info btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Genera il PDF della carenza che arriva alla studente"><span class="glyphicon glyphicon-print"></button>';
							
						}
					}
				}
			}
		}

	$data .= '</td></tr>';
}


$data .= '</table></div>';
$data .= '<input type="hidden" id="hidden_nmoduli" value=' . $ncarenze . '>';

echo $data;
