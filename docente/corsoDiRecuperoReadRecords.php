<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

$soloNonFirmati = $_GET["soloNonFirmati"];
$soloCorsiDiOggi = $_GET["soloCorsiDiOggi"];

// se non sono un docente non sa cosa fare
if ( empty($__docente_id) ) {
	return;
}

$query = "	SELECT
				lezione_corso_di_recupero.id AS lezione_corso_di_recupero_id,
				lezione_corso_di_recupero.data AS lezione_corso_di_recupero_data,
				lezione_corso_di_recupero.orario AS lezione_corso_di_recupero_orario,
				lezione_corso_di_recupero.firmato AS lezione_corso_di_recupero_firmato,
				lezione_corso_di_recupero.argomento AS lezione_corso_di_recupero_argomento,
				lezione_corso_di_recupero.note AS lezione_corso_di_recupero_note,
				corso_di_recupero.id AS corso_di_recupero_id,
				corso_di_recupero.codice AS corso_di_recupero_codice,
				corso_di_recupero.numero_ore AS corso_di_recupero_numero_ore,
				materia.nome AS materia_nome
			FROM lezione_corso_di_recupero
			INNER JOIN corso_di_recupero corso_di_recupero
			ON lezione_corso_di_recupero.corso_di_recupero_id = corso_di_recupero.id
			INNER JOIN materia materia
			ON corso_di_recupero.materia_id = materia.id
			WHERE corso_di_recupero.anno_scolastico_id = $__anno_scolastico_corrente_id
			AND corso_di_recupero.docente_id = $__docente_id
			"
			;
if( $soloCorsiDiOggi ) {
	$query .= "
			AND lezione_corso_di_recupero.data = CURDATE()
			";
} else if( $soloNonFirmati ) {
	$query .= "
			AND lezione_corso_di_recupero.firmato = false
			";
}
$query .= "
			ORDER BY
				lezione_corso_di_recupero.data ASC,
				lezione_corso_di_recupero.inizia_alle ASC
			"
			;

$data = '';
$counter = 0;

foreach(dbGetAll($query) as $row) {
	++$counter;
	$data .= '
<div class="panel panel-lightblue4">
<div class="panel-heading container-fluid">
<div class="row">
	<div class="col-md-4">
		'.$row['corso_di_recupero_codice'].': &emsp;'.date('d M', strtotime($row['lezione_corso_di_recupero_data'])).' &emsp; '.strstr($row['lezione_corso_di_recupero_orario'], '-', true).'
	</div>
	<div class="col-md-4 text-center">';
	if ($row['lezione_corso_di_recupero_firmato'] == false) {
		$data .= '
		<button type="button" class="btn btn-xs btn-warning firmaBtnClass" onclick="firma(\''.$row['lezione_corso_di_recupero_id'].'\')"><span class="glyphicon glyphicon-warning-sign
"> Firma il Registro </button>';
	} else {
		$data .= '
		<button type="button" class="btn btn-xs btn-success"'.
// TODO: reinserire il disabled rimosso solo per comodita' nel test
//			'disabled="true"'.
// TODO togliere questa riga che toglie la firma
'onclick="togliFirma(\''.$row['lezione_corso_di_recupero_id'].'\')"'.
		'><span class="glyphicon glyphicon-check
"> Firmato </button>';
	}
	$data .= '
	</div>
	<div class="col-md-4 text-right">
		<a data-toggle="collapse" href="#collapse'.$counter.'"><span class="panelarrow glyphicon glyphicon-resize-small"></span></a>
	</div>
</div>
</div>
<div id="collapse'.$counter.'" class="panel-collapse collapse  collapse in">
<div class="panel-body">
';
	$data .= '
<div class="col-md-8">
	<form class="form-horizontal">
		<div class="form-group">
			<label class="control-label col-sm-2" for="data">Data:</label>
			<div class="col-sm-10">
				<p class="form-control-static">'.$row['lezione_corso_di_recupero_data'].'</p>
			</div>
		</div>
		<div class="form-group">
			<label class="control-label col-sm-2" for="orario">Orario:</label>
			<div class="col-sm-9">
				<p class="form-control-static">'.$row['lezione_corso_di_recupero_orario'].'</p>
			</div>
		</div>
		<div class="form-group">
			<label class="control-label col-sm-2" for="argomento">Argomento:</label>
			<div class="col-sm-9">
				<p class="form-control-static">'.$row['lezione_corso_di_recupero_argomento'].'</p>
			</div>
		</div>
		<div class="form-group">
			<label class="control-label col-sm-2" for="note">Note:</label>
			<div class="col-sm-9">
				<p class="form-control-static" style="white-space: pre-wrap;" >'.$row['lezione_corso_di_recupero_note'].'</p>
			</div>
		</div>
	</form>
</div>
<div class="col-md-4">
';
	$data .= '
	<div class="table-wrapper"><table class="table table-bordered table-striped">
					<tr>
						<th>cognome</th>
						<th>nome</th>
						<th>classe</th>
						<th>presente</th>
					</tr>';

	$query = "	SELECT
					studente_partecipa_lezione_corso_di_recupero.id AS studente_partecipa_lezione_corso_di_recupero_id,
					studente_partecipa_lezione_corso_di_recupero.ha_partecipato AS studente_partecipa_lezione_corso_di_recupero_ha_partecipato,
					studente_per_corso_di_recupero.id AS studente_per_corso_di_recupero_id,
					studente_per_corso_di_recupero.cognome AS studente_per_corso_di_recupero_cognome,
					studente_per_corso_di_recupero.nome AS studente_per_corso_di_recupero_nome,
					studente_per_corso_di_recupero.classe AS studente_per_corso_di_recupero_classe
				FROM studente_partecipa_lezione_corso_di_recupero
				INNER JOIN studente_per_corso_di_recupero studente_per_corso_di_recupero
				ON studente_partecipa_lezione_corso_di_recupero.studente_per_corso_di_recupero_id = studente_per_corso_di_recupero.id
				WHERE
					studente_partecipa_lezione_corso_di_recupero.lezione_corso_di_recupero_id = ".$row['lezione_corso_di_recupero_id']."
				 ORDER BY studente_per_corso_di_recupero.classe ASC, studente_per_corso_di_recupero.cognome ASC, studente_per_corso_di_recupero.nome ASC"
				 ;
	if (!$result = mysqli_query($con, $query)) {
		exit(mysqli_error($con));
	}
	$partecipaArray = $result->fetch_all(MYSQLI_ASSOC);
	foreach($partecipaArray as $partecipaRow) {
		$data .= '
			<tr>
				<td>'.$partecipaRow['studente_per_corso_di_recupero_cognome'].'</td>
				<td>'.$partecipaRow['studente_per_corso_di_recupero_nome'].'</td>
				<td>'.$partecipaRow['studente_per_corso_di_recupero_classe'].'</td>
			';
		$data .= '<td class="text-center"><input type="checkbox" disabled data-toggle="toggle" data-onstyle="primary" id="attivo" ';
		if ($partecipaRow['studente_partecipa_lezione_corso_di_recupero_ha_partecipato']) {
			$data .= 'checked ';
		}
		$data .= '></td>
			</tr>
			';
	}

	$data .= '</table></div>
</div>
</div>
<div class="panel-footer text-center">
<button onclick="lezioneCorsoDiRecuperoGetDetails('.$row['lezione_corso_di_recupero_id'].')" class="btn btn-xs btn-info"';
	if ($row['lezione_corso_di_recupero_firmato'] == false) {
// TODO:			$data .= ' disabled';
	}
	$data .= '><span class="glyphicon glyphicon-pencil"> Modifica</button>

</div>
</div>
</div>
';
}
echo $data;
?>
