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

$docente_id = intval($_GET["docente_id"] ?? 0);
$classe_id = intval($_GET["classe_id"] ?? 0);
$materia_id = intval($_GET["materia_id"] ?? 0);
$studente_id = intval($_GET["studente_id"] ?? 0);
$da_validare_filtro = intval($_GET["da_validare_filtro"] ?? 0);
$anno = intval($_GET["anno"] ?? 0);
$anni_filtro_id = intval($_GET["anni_id"] ?? 0);
$vistaDocente = intval($_GET["vista_docente"] ?? 0) === 1;
$docente_scope_filtro = intval($_GET["docente_scope_filtro"] ?? 1);
$isAdminView = !$vistaDocente && (haRuolo('dirigente') || haRuolo('segreteria-didattica'));
$isDocenteView = $vistaDocente || (($__utente_ruolo ?? '') === 'docente');


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
$id_docente_attuale = $vistaDocente && $docente_id > 0 ? $docente_id : intval($result['doc_id'] ?? 0);

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
				LEFT JOIN docente docente
				ON carenze.id_docente = docente.id
				INNER JOIN studente studente
				ON carenze.id_studente = studente.id
				INNER JOIN materia materia
				ON carenze.id_materia = materia.id
				INNER JOIN classi classi
				ON carenze.id_classe = classi.id
				WHERE 1=1";

if ($anni_filtro_id > 0) {
			$query .= " AND carenze.id_anno_scolastico=" . $anni_filtro_id;
}

if ($isDocenteView && $docente_scope_filtro > 0)
{
	$query .= " AND (carenze.stato='0' OR carenze.id_docente=" . intval($id_docente_attuale) . ")";
}
else if ($isDocenteView && (getSettingsValue('config', 'carenzeObiettiviMinimi', false)) && (getSettingsValue('carenzeObiettiviMinimi', 'visibile_docenti', false)) && (getSettingsValue('carenzeObiettiviMinimi', 'docente_vede_solo_le_sue', false)))
{
	$query .= " AND (carenze.id_docente=" . intval($id_docente_attuale) . " OR carenze.id_docente=0)";
}
else if (!$isDocenteView && $docente_id > 0)
{
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

$query .= " ORDER BY studente.cognome ASC, studente.nome ASC";

$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}

$ncarenze = 0;
$array_carenze_genera = '';
$array_carenze_mail = '';

foreach ($resultArray as $row) {
	$idcarenza = $row['carenza_id'];
	$anno_carenza = $row['carenza_id_anno_scolastico'];
	$ncarenze++;
	$docente_riga_id = $row['doc_id'];
	if ($row['carenza_stato']==1)
	{
		if ($array_carenze_genera == '')
		{
			$array_carenze_genera .= $idcarenza;
		}
		else
		{
			$array_carenze_genera .= ', ' . $idcarenza;
		}
	}
	if ($row['carenza_stato']==2)
	{
		if ($array_carenze_mail == '')
		{
			$array_carenze_mail .= $idcarenza;
		}
		else
		{
			$array_carenze_mail .= ', ' . $idcarenza;
		}
	}

	$nota = $row['carenza_nota'];
	$nota_tooltip = htmlspecialchars((string)$nota, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$nota_js = htmlspecialchars(str2js((string)$nota), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

	if ($stato > 2) {
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
		$statoMarker .= '<span class="label label-danger">inserito</span>';
	} else {
		if ($stato == 3) {
			$statoMarker .= '<span class="label label-warning">inviato</span>';
		} else {
			if ($stato == 1) {
				$statoMarker .= '<span class="label label-success">validato</span>';
			}
			else
 			{
				$statoMarker .= '<span class="label label-primary">generato</span>';
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

	if ($isAdminView) {
		$data .= '
			<button onclick="carenzeGetDetails(\'' . $idcarenza . '\')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica la carenza"><span class="glyphicon glyphicon-pencil"></button>
			<button onclick="carenzaDelete(\'' . $idcarenza . '\',\'' . $materia . '\',\'' . $studente . '\')" class="btn btn-danger btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Cancella la carenza"><span class="glyphicon glyphicon-trash"></button>';
		if ($stato == 0) {
			$data .= '
				<button onclick="carenzaValida(\'' . $idcarenza . '\',\'' . $__utente_id . '\',\'' . $stato . '\')" class="btn btn-primary btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Conferma la carenza"><span class="glyphicon glyphicon-warning-sign"></button>';
		} else {
			if ($stato == 1) {
				$data .= '
				<button onclick="carenzaValida(\'' . $idcarenza . '\',\'' . $__utente_id . '\',\'' . $stato . '\')" class="btn btn-success btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Rimuovi la conferma della carenza - Nota attualmente inserita - ' . $nota_tooltip . '"><span class="glyphicon glyphicon-ok"></button>
				<button onclick="carenzaModificaNota(\'' . $idcarenza . '\',\'' . $nota_js . '\')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica la nota per lo studente"><span class="glyphicon glyphicon-pencil"></span></button>';
			}
			$data .= '
			<button onclick="carenzaPrint(\'' . $idcarenza . '\',\'' . $anno_carenza . '\')" class="btn btn-info btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Apri l\'anteprima della carenza: da lì puoi scaricare il PDF"><span class="glyphicon glyphicon-print"></button>
			<button onclick="carenzaGenera(\'' . $idcarenza . '\',\'' . $anno_carenza . '\')" class="btn btn-success btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Genera sul server il PDF della carenza"><span class="glyphicon glyphicon-fire"></button>
			<button onclick="carenzaSend(\'' . $idcarenza . '\')" class="btn btn-primary btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Invia la mail della carenza allo studente"><span class="glyphicon glyphicon-send"></button>';
		}	
	} else
		if ($isDocenteView) {
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
								<button onclick="hideTooltip(this); carenzaValida(\'' . $idcarenza . '\',\'' . $__utente_id . '\',\'' . $stato . '\')" class="btn btn-success btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Rimuovi la conferma della carenza - Nota attualmente inserita - ' . $nota_tooltip . '"><span class="glyphicon glyphicon-ok"></button>
								<button onclick="hideTooltip(this); carenzaModificaNota(\'' . $idcarenza . '\',\'' . $nota_js . '\')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica la nota per lo studente"><span class="glyphicon glyphicon-pencil"></span></button>';
								} else {
									$data .= '
								<button onclick="hideTooltip(this)" class="btn btn-danger btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Non puoi modificare la carenza confermata da un altro docente"><span class="glyphicon glyphicon-ok"></button>';
								}
							}
							$data .= '
							<button onclick="carenzaPrint(\'' . $idcarenza . '\')" class="btn btn-info btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Apri l\'anteprima della carenza: da lì puoi scaricare il PDF"><span class="glyphicon glyphicon-print"></button>';
							
						}
					}
				}
			}
		}

	$data .= '</td></tr>';
}


$data .= '</table></div>';
$data .= '<input type="hidden" id="hidden_nmoduli" value=' . $ncarenze . '>';
$data .= '<input type="hidden" id="hidden_arraycarenzegenera" value="' . htmlspecialchars($array_carenze_genera) . '">';
$data .= '<input type="hidden" id="hidden_arraycarenzemail" value="' . htmlspecialchars($array_carenze_mail) . '">';

echo $data;
