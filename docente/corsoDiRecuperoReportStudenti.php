<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

?>

<!DOCTYPE html>
<html>
<head>
<?php
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
ruoloRichiesto('segreteria-didattica','dirigente','docente');
?>
	<title>Report Corsi di Recupero</title>

<!-- Bootstrap, jquery etc (css + js) -->
<?php
	require_once '../common/style.php';
?>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/common/bootstrap-toggle-master/css/bootstrap-toggle.min.css">
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/bootstrap-toggle-master/js/bootstrap-toggle.min.js"></script>

<!-- boostrap-select -->
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/common/bootstrap-select/css/bootstrap-select.min.css">
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/bootstrap-select/js/bootstrap-select.min.js"></script>
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/bootstrap-select/js/i18n/defaults-it_IT.min.js"></script>

<!-- timejs -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/timejs/date-it-IT.js"></script>

<!-- flatpickr -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/flatpickr/dist/flatpickr.min.js"></script>
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/flatpickr/dist/l10n/it.js"></script>
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/common/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/common/flatpickr/dist/themes/material_red.css">

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green.css">

</head>

<body >
<?php
	require_once '../common/header-docente.php';
	require_once '../common/connect.php';
?>

<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-primary">

<?php
require_once '../common/connect.php';

function printableVoto($voto) {
	if ($voto != 0) {
		if ($voto == 1) {
			return 'Assente';
		}
		return $voto;
	}
	return null;
}

function printableDate($data) {
	if ($data != null) {
		return strftime("%d/%m/%Y", strtotime($data));
	}
	return null;
}

// prepara l'elenco delle classi
$data = '';
$query = "	SELECT DISTINCT studente_per_corso_di_recupero.classe AS studente_per_corso_di_recupero_classe
			FROM
				studente_per_corso_di_recupero studente_per_corso_di_recupero
			INNER JOIN corso_di_recupero corso_di_recupero
			ON studente_per_corso_di_recupero.corso_di_recupero_id = corso_di_recupero.id
			WHERE
				corso_di_recupero.anno_scolastico_id = '$__anno_scolastico_corrente_id'
			ORDER BY
				studente_per_corso_di_recupero.classe ASC;
			";
$resultArray = dbGetAll($query);
foreach($resultArray as $row_classe) {
	$classe = $row_classe['studente_per_corso_di_recupero_classe'];
	$data .= '
        <div class="panel panel-success">
        <div class="panel-heading container-fluid">
        	<div class="row">
        		<div class="col-md-4">
        		</div>
        		<div class="col-md-4 text-center">
        			'.$classe.'
        		</div>
        		<div class="col-md-4 text-right">
        		</div>
        	</div>
        </div>
        <div class="panel-body">
        ';
	$query = "	SELECT
						studente_per_corso_di_recupero.id AS studente_per_corso_di_recupero_id,
						studente_per_corso_di_recupero.cognome AS studente_per_corso_di_recupero_cognome,
						studente_per_corso_di_recupero.nome AS studente_per_corso_di_recupero_nome,
						studente_per_corso_di_recupero.classe AS studente_per_corso_di_recupero_classe,
						studente_per_corso_di_recupero.voto_settembre AS studente_per_corso_di_recupero_voto_settembre,
						studente_per_corso_di_recupero.data_voto_settembre AS studente_per_corso_di_recupero_data_voto_settembre,
						studente_per_corso_di_recupero.voto_novembre AS studente_per_corso_di_recupero_voto_novembre,
						studente_per_corso_di_recupero.data_voto_novembre AS studente_per_corso_di_recupero_data_voto_novembre,
						studente_per_corso_di_recupero.passato AS studente_per_corso_di_recupero_passato,
						studente_per_corso_di_recupero.serve_voto AS studente_per_corso_di_recupero_serve_voto,
						corso_di_recupero.codice AS corso_di_recupero_codice,
						materia.nome AS materia_nome,
						docente_set.nome AS docente_set_nome,
						docente_set.cognome AS docente_set_cognome,
						docente_nov.nome AS docente_nov_nome,
						docente_nov.cognome AS docente_nov_cognome
					FROM
						studente_per_corso_di_recupero
					INNER JOIN corso_di_recupero corso_di_recupero
					ON studente_per_corso_di_recupero.corso_di_recupero_id = corso_di_recupero.id
					INNER JOIN materia materia
					ON corso_di_recupero.materia_id = materia.id
					LEFT JOIN docente docente_set
					ON studente_per_corso_di_recupero.docente_voto_settembre_id = docente_set.id
					LEFT JOIN docente docente_nov
					ON studente_per_corso_di_recupero.docente_voto_novembre_id = docente_nov.id
					WHERE
						corso_di_recupero.anno_scolastico_id = '$__anno_scolastico_corrente_id'
					AND
						studente_per_corso_di_recupero.classe='$classe'
					ORDER BY
						corso_di_recupero.codice ASC,
						studente_per_corso_di_recupero.cognome ASC,
						studente_per_corso_di_recupero.nome ASC
					;
			";
		$resultArray2 = dbGetAll($query);
		if (count($resultArray2) > 0) {
		    $data .= '
		<div class="table-wrapper">
			<table class="table table-bordered table-striped">
				<thead>
                    <th>id</th>
					<th class="col-sm-2">Studente</th>
					<th class="col-sm-1">Materia</th>
					<th class="col-sm-1">Voto Sett</th>
					<th class="col-sm-1">Data Sett</th>
					<th class="col-sm-1">Docente Set</th>
					<th class="col-sm-1">Voto Nov</th>
					<th class="col-sm-1">Data Nov</th>
					<th class="col-sm-1">Docente Nov</th>
					<th class="col-sm-1">passato</th>
					<th class="col-sm-1"></th>
				</thead>
';
		    $classname = "";
		    foreach($resultArray2 as $row_studente) {
				$passatoMarker = '';
				if ($row_studente['studente_per_corso_di_recupero_passato']) {
					$passatoMarker = '<span class=\'label label-success\'>passato</span>';
				} else if (isset($row_studente['studente_per_corso_di_recupero_passato']) && $row_studente['studente_per_corso_di_recupero_passato'] == 0){
					$passatoMarker = '<span class=\'label label-danger\'>non passato</span>';
				}
				$esente = (!empty($row_studente['studente_per_corso_di_recupero_serve_voto'])) && $row_studente['studente_per_corso_di_recupero_serve_voto'] == 0;
				if ($esente) {
					$passatoMarker = '<span class=\'label label-info\'>esente</span>';
				}

				$classname = ($classname==="even_row") ? "odd_row" : "even_row";
				$data .= '
								<tr class="'.$classname.'">
									<td>'.$row_studente['studente_per_corso_di_recupero_id'].'</td>
									<td>'.$row_studente['studente_per_corso_di_recupero_cognome'].' '.$row_studente['studente_per_corso_di_recupero_nome'].'</td>
									<td><small>'.$row_studente['materia_nome'].'</small></td>
									<td style="text-align: center;">'.printableVoto($row_studente['studente_per_corso_di_recupero_voto_settembre']).'</td>
									<td style="text-align: center;">'.printableDate($row_studente['studente_per_corso_di_recupero_data_voto_settembre']).'</td>
									<td><small>'.$row_studente['docente_set_cognome'].' '.$row_studente['docente_set_nome'].'</small></td>
						';

				// se i voti di novembre sono aperti, apre l'inserimento per quelli che non sono esenti e non hanno preso almeno 6 a settembre
				if ($__config->getVoti_recupero_novembre_aperto() && ! $esente && $row_studente['studente_per_corso_di_recupero_voto_settembre'] < 6) {

					// prepara la lista dei voti possibili
					$votoNovembre = $row_studente['studente_per_corso_di_recupero_voto_novembre'];
					$votoNovembreOptionList = '				<select  class="votoNovembre selectpicker" data-noneSelectedText="seleziona..." data-width="50%" ><option value="0"></option>';
					// opzione per assente
					$bgColor = 'red';
					$votoNovembreOptionList .= '<option value="'.'1'.'" data-content="<span class=\'label label-info\' style=\'background-color: '.$bgColor.';\'>'.'Assente'.'</span>"';
					if ($votoNovembre === 1) {
						$votoNovembreOptionList .= ' selected ';
					}
					$votoNovembreOptionList .= '>'.'assente'.'</option>';

					// voti da 4 a 10
					for($i = 4; $i<=10; $i++) {
						$bgColor = ($i <= 5) ? 'red' : 'green';
						$votoNovembreOptionList .= '<option value="'.$i.'" data-content="<span class=\'label label-info\' style=\'background-color: '.$bgColor.';\'>'.$i.'</span>"';

						// seleziona il voto corrente di novembre se esiste (se e' gia' stato dato)
						if ($votoNovembre == $i) {
							$votoNovembreOptionList .= ' selected ';
						}
						$votoNovembreOptionList .= '>'.$i.'</option>';
					}
					$votoNovembreOptionList .= '</select>';
					$data .= '<td>'.$votoNovembreOptionList.'</td>';

					// la data del voto di novembre
					$dataNovembre = strftime("%d/%m/%Y", strtotime($row_studente['studente_per_corso_di_recupero_data_voto_novembre']));
					if (empty($row_studente['studente_per_corso_di_recupero_data_voto_novembre'])) {
						$dataNovembre = date('d/m/Y');
					}
					$data .= '
    							<td><input type="text" placeholder="Data" class="form-control dataVotoNovembre" value="'.$dataNovembre.'" /></td>';

					$data .= '
									<td><small>'.$row_studente['docente_nov_cognome'].' '.$row_studente['docente_nov_nome'].'</small></td>
						';
				} else {
					$data .= '
									<td style="text-align: center;">'.printableVoto($row_studente['studente_per_corso_di_recupero_voto_novembre']).'</td>
									<td style="text-align: center;">'.printableDate($row_studente['studente_per_corso_di_recupero_data_voto_novembre']).'</td>
									<td><small>'.$row_studente['docente_nov_cognome'].' '.$row_studente['docente_nov_nome'].'</small></td>
						';
				}
				$data .= '
									<td class="col-sm-1 text-center">'.$passatoMarker.'</td>
						';
				// se e' aperto solo settembre, controlla se deve generare la lettera (se c'è il voto)
				if ($__config->getVoti_recupero_settembre_aperto() && ! $__config->getVoti_recupero_novembre_aperto() && ($row_studente['studente_per_corso_di_recupero_voto_settembre'] > 0) ) {
					$data .= '
									<td class="text-center">
									<button onclick="letteraCarenzeSettembre('.$row_studente['studente_per_corso_di_recupero_id'].')" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-envelope"></button>
									</td>
						';
				}

				// se e' aperto novembre, controlla se deve generare la lettera finale (se c'è il voto)
				else if ($__config->getVoti_recupero_novembre_aperto() && ($row_studente['studente_per_corso_di_recupero_voto_novembre'] > 0) ) {
					$data .= '
									<td class="text-center">
									<button onclick="letteraCarenzeNovembre('.$row_studente['studente_per_corso_di_recupero_id'].')" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-envelope"></button>
									</td>
						';
				}
				
				else {
					$data .= '
									<td></td>
						';
				}
				$data .= '
								</tr>
						';
			}
			$data .= '
							</tbody>
						</table>
<div style="page-break-after: always;">
</div>
</div>
</div>
';
		}
		$data .= '
</div>
';
}
$data.='<input type="hidden" id="hidden_docente_id" value="'.$__docente_id.'">';
$data.='<input type="hidden" id="hidden_docente_cognomenome" value="'.$__docente_cognome.' '.$__docente_nome.'">';
echo $data;
?>

</div>
</div>
</div>
<!-- Custom JS file -->
<script type="text/javascript" src="js/scriptCorsoDiRecuperoReportStudenti.js"></script>

</body>
</html>