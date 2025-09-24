<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// include Database connection file
require_once '../common/checkSession.php';
require_once '../common/connect.php';

$ancheCancellati = $_GET["ancheCancellati"];
$soloNuovi = $_GET["soloNuovi"];

$direzioneOrdinamento="ASC";

// Design initial table header
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
					<thead>
					<tr>
						<th class="text-center col-md-1">Categoria</th>
						<th class="text-center col-md-1">Data</th>
						<th class="text-center col-md-1">Ora</th>';
if ($__settings->sportelli->unSoloArgomento == true)
{
	$data .= '			<th class="text-center col-md-2">Materia</th>
						<th class="text-center col-md-2">Argomento</th>
						';
}
else
{
	$data .= '			<th class="text-center col-md-3">Materia</th>
						';
}

$data .= '				<th class="text-center col-md-1">Ore</th>
						<th class="text-center col-md-1">Classe</th>
						<th class="text-center col-md-1">Luogo</th>
						<th class="text-center col-md-1">Stato</th>
						<th class="text-center col-md-1">Studenti Prenotati</th>
						<th class="text-center col-md-1">Max prenotazioni</th>
						<th class="text-center col-md-1"></th>
					</tr>
					</thead>';
					
$query = "	SELECT
				sportello.id AS sportello_id,
				sportello.data AS sportello_data,
				sportello.ora AS sportello_ora,
				sportello.numero_ore AS sportello_numero_ore,
				sportello.argomento as sportello_argomento,
				sportello.luogo AS sportello_luogo,
				sportello.classe AS sportello_classe,
				sportello.firmato AS sportello_firmato,
				sportello.cancellato AS sportello_cancellato,
				sportello.categoria AS sportello_categoria,
				sportello.online AS sportello_online,
				sportello.clil AS sportello_clil,
				sportello.orientamento AS sportello_orientamento,
				sportello.max_iscrizioni AS sportello_max_iscrizioni,
				materia.nome AS materia_nome,
				docente.cognome AS docente_cognome,
				docente.nome AS docente_nome,
				(	SELECT COUNT(*) FROM sportello_studente WHERE sportello_studente.sportello_id = sportello.id) AS numero_studenti
			FROM sportello sportello
			INNER JOIN docente docente
			ON sportello.docente_id = docente.id
			INNER JOIN materia materia
			ON sportello.materia_id = materia.id
			INNER JOIN classe classe
			ON sportello.classe_id = classe.id			
			WHERE 
				sportello.docente_id = $__docente_id
			AND
				sportello.anno_scolastico_id = $__anno_scolastico_corrente_id
			";

if( !$ancheCancellati) {
	$query .= "AND NOT sportello.cancellato ";
}
if( $soloNuovi) {
	$query .= "AND sportello.data >= CURDATE() ";
}
$query .= "ORDER BY sportello.data $direzioneOrdinamento, docente_cognome ASC,docente_nome ASC";

$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}
foreach($resultArray as $row) 
{
	$sportello_id = $row['sportello_id'];
	$sportello_categoria = $row['sportello_categoria'];
	$sportello_firmato = $row['sportello_firmato'];
	$sportello_cancellato = $row['sportello_cancellato'];
	$sportello_nstudenti = $row['numero_studenti'];
	$statoMarker = '';
	if ($sportello_cancellato) {
		$statoMarker .= '<span class="label label-default">cancellato</span>';
	}
	else
	{
		if ($sportello_firmato) 
		{
			$statoMarker .= '<span class="label label-primary">firmato</span>';
		}
		else
		{
			if (($row['sportello_max_iscrizioni']) == ($row['numero_studenti'])) {
				$statoMarker .= '<span class="label label-danger">posti esauriti</span>';
			}
			else
			{
				$statoMarker .= '<span class="label label-success">posti disponibili</span>';
			}
		}
	}
	
	$dt_sportello = $row['sportello_data'];
	$dt_oggi = date("Y-m-d");
	$vecchio = 0;
	if (strtotime($dt_sportello) < strtotime($dt_oggi))
	{
		$vecchio = 1;
	}
	
	if ($row['sportello_data'])
	{
	$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
	$dataSportello = utf8_encode( strftime("%d %B %Y", strtotime($row['sportello_data'])));
	setlocale(LC_TIME, $oldLocale);
	}

	// se ci sono prenotazioni, cerca la lista di studenti che sono prenotati
	$studenteTip = '';
	if ($sportello_nstudenti > 0) {
		$query2 = "SELECT
				sportello_studente.id AS sportello_studente_id,
				sportello_studente.iscritto AS sportello_studente_iscritto,
				sportello_studente.presente AS sportello_studente_presente,
				sportello_studente.note AS sportello_studente_note,

				studente.cognome AS studente_cognome,
				studente.nome AS studente_nome,
				c.classe AS studente_classe,
				studente.id AS studente_id

			FROM
				sportello_studente
			INNER JOIN studente	ON sportello_studente.studente_id = studente.id
			INNER JOIN studente_frequenta sf ON sf.id_studente = studente.id AND sf.id_anno_scolastico = $__anno_scolastico_corrente_id
			INNER JOIN classi c ON sf.id_classe = c.id

			WHERE sportello_studente.sportello_id = '$sportello_id'";

		$studenti = dbGetAll($query2);
		foreach($studenti as $studente) {
			$studenteTip = $studenteTip . $studente['studente_cognome'] . " " . $studente['studente_nome'] ." " . $studente['studente_classe'] . "</br>";
		}
	}

	// marker per eventuali sportelli online
	$luogo_or_onine_marker = $row['sportello_luogo'];
	if ($row['sportello_online']) {
		$luogo_or_onine_marker = '<span class="label label-danger">online</span>';
	} else {
		debug("online=".$row['sportello_online']);
	}

	$barrato = '';
	if ($sportello_cancellato)
	{
		$barrato='<s>';
	}

	$data .= '
		<td align="center">'.$barrato.$sportello_categoria.'</td>
		<td align="center">'.$barrato.$dataSportello.'</td>
		<td align="center">'.$barrato.$row['sportello_ora'].'</td>
		<td>'.$barrato.$row['materia_nome'].'</td>';
	if ($__settings->sportelli->unSoloArgomento == true)
	{
		$data .= '<td>'.$barrato.$row['sportello_argomento'].'</td>';
	}
	
	$data .= '
		<td align="center">'.$barrato.$row['sportello_numero_ore'].'</td>
		<td align="center">'.$barrato.$row['sportello_classe'].'</td>
		<td align="center">'.$barrato.$luogo_or_onine_marker.'</td>
		<td class="text-center">'.$statoMarker.'</td>
		<td align="center" data-toggle="tooltip" data-placement="left" data-html="true" title="'.$studenteTip.'">'.$barrato.$row['numero_studenti'].'</td>
		<td class="text-center">'.$barrato.$row['sportello_max_iscrizioni'].'</td>
		';
	if ((!$sportello_cancellato)&&(!$sportello_firmato)&&(!$vecchio))
	{
		$data .='
		<td class="text-center" data-toggle="tooltip" data-placement="left" data-html="true" title="Clicca qui per gestire lo sportello">
		<button onclick="sportelloGetDetails('.$row["sportello_id"].',true,'.$sportello_nstudenti.',\''.$sportello_categoria.'\')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></span></button>
		</td>
		</tr>';
	}
	else
	{
		$data .='
		<td class="text-center" data-toggle="tooltip" data-placement="left" data-html="true" title="Sportello non modificabile">
		<button style="padding: 0;border: none;background: none;" onclick="sportelloGetDetails('.$row["sportello_id"].',false,'.$sportello_nstudenti.',\''.$sportello_categoria.'\')"><span class="btn btn-danger btn-xs glyphicon glyphicon-lock"></span></button>
		</td>
		</tr>';
	}
	if ($__settings->sportelli->docente_puo_eliminare)
		{
		$data .='
			<button onclick="sportelloDelete('.$row['sportello_id'].', \''.$row['materia_nome'].'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></button>
		';
	}
}

$data .= '</table></div>';
echo $data;
?>

