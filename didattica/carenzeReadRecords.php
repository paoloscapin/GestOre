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
 
// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th class="text-center col-md-2">Studente</th>
						<th class="text-center col-md-2">Docente</th>
						<th class="text-center col-md-3">Materia</th>
						<th class="text-center col-md-1">Grave</th>
						<th class="text-center col-md-1">Generata</th>
						<th class="text-center col-md-1">Inviata</th>
						<th class="text-center col-md-2">Azioni</th>
					</tr>
					</thead>';

$query = "	SELECT
					carenze.id AS carenza_id,
					carenze.id_docente AS carenza_id_docente,
					carenze.id_studente AS carenza_id_studente,
					carenze.id_materia AS carenza_id_materia,
					carenze.id_anno_scolastico AS carenza_id_anno_scolastico,
					carenze.grave AS carenza_grave,
					carenze.generata AS carenza_generata,
					carenze.inviata AS carenza_inviata,
					carenze.data_invio AS datainvio,
					studente.cognome AS stud_cognome,
					studente.nome AS stud_nome,
					classi.classe AS classe,
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
				ON studente.id_classe = classi.id
				WHERE carenze.id_anno_scolastico=$__anno_scolastico_corrente_id";

if ($docente_id>0)
{
	$query .= " AND carenze.id_docente=".$docente_id;
}
if ($classe_id>0)
{
	$query .= " AND carenze.id_docente=".$classe_id;
}
if ($materia_id>0)
{
	$query .= " AND carenze.id_docente=".$materia_id;
}
if ($studente_id>0)
{
	$query .= " AND carenze.id_studente=".$studente_id;
}

$query .= " ORDER BY studente.cognome, studente.nome ASC";

$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}

$ncarenze = 0;
foreach ($resultArray as $row) { {
		$ncarenze++;
		$idcarenza = $row['carenza_id'];
		$studente = $row['stud_cognome'] . ' ' . $row['stud_nome'];
		$docente = $row['doc_cognome'] . ' ' . $row['doc_nome'];
		$materia = $row['materia'];
		$classe = $row['classe'];
		$grave = $row['carenza_grave'];
		$generata = $row['carenza_generata'];
		$inviata = $row['carenza_inviata'];
		if ($inviata==1)
		{
			$data_invio = $row['datainvio'];
		}
		else
		{
			$data_invio = 'da inviare';
		}
		$data .= '<tr>
		<td align="center">' . $studente . '</td>
		<td align="center">' . $docente . '</td>
		<td align="center">' . $materia . '</td>';
		if ($grave==1)
		{
		  $data .= '<td align="center"><button class="btn btn-danger btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Carenza grave"><span class="glyphicon glyphicon-minus-sign"></button></td>';
		}
		else
		{
		  $data .= '<td align="center"><button class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Carenza non grave"><span class="glyphicon glyphicon-minus-sign"></button></td>';
		}

		if ($generata==1)
		{
		  $data .= '<td align="center"><button class="btn btn-success btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Generata"><span class="glyphicon glyphicon-thumbs-up"></button></td>';
		}
		else
		{
		  $data .= '<td align="center"><button class="btn btn-danger btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Non generata"><span class="glyphicon glyphicon-thumbs-down"></button></td>';
		}
		$data .= '
		<td align="center">' . $data_invio . '</td>
		';
		$data .= '
		<td class="text-center">';

		if ((haRuolo('dirigente')) || (haRuolo('segreteria-didattica'))) {
			$data .= '
			<button onclick="moduloGetDetails(' . $idmodulo . ')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica il modulo"><span class="glyphicon glyphicon-pencil"></button>
			<button onclick="moduloDelete(' . $idmodulo . ',\'' . $id_programma . '\',\'' . $titolo . '\')" class="btn btn-danger btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Cancella il modulo"><span class="glyphicon glyphicon-trash"></button>
			';
		} else
			if (haRuolo('docente')) {
				if (getSettingsValue('programmiMaterie', 'visibile_docenti', false)) {
					if (getSettingsValue('programmiMaterie', 'docente_puo_modificare', false)) {
						$data .= '
  						<button onclick="moduloGetDetails(' . $idmodulo . ')" class="btn btn-warning btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Modifica la materia"><span class="glyphicon glyphicon-pencil"></button>';
					} else {
						$data .= '
						<button onclick="moduloGetDetails(' . $idmodulo . ')" class="btn btn-info btn-xs" data-toggle="tooltip" data-trigger="hover" data-placement="top" title="Vedi il dettaglio del modulo"><span class="glyphicon glyphicon-search"></button>';
					}
				}
			}

		$data .= '
		</td>
		</tr>';
	}
}

$data .= '</table></div>';
$data .= '<input type="hidden" id="hidden_nmoduli" value=' . $ncarenze . '>';

echo $data;
?>