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

<style>
    .icon-play{
        background-image : url('../img/pdf-256.png');
        background-size: cover;
        display: inline-block;
        height: 16px;
        width: 16px;
    }
</style>
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

$assenteColor = 'DarkRed';
$nonRichiestoColor = 'DarkOrange';
$passatoColor = 'Green';
$nonPassatoColor = 'FireBrick';

function printableVoto($voto) {
	global $assenteColor, $nonRichiestoColor, $passatoColor, $nonPassatoColor;

	// rosso se non passato
	if ($voto == 1) {
		$bgColor = $assenteColor;
	} elseif ($voto == 2) {
		$bgColor = $nonRichiestoColor;
	} else {
		$bgColor = ($voto <= 5) ? $nonPassatoColor : $passatoColor;
	}

	if ($voto != 0) {
		// 1 vuole dire che non c'era
		if ($voto == 1) {
			$voto = 'Assente';
		}
		// 2 che non ha voluto farlo (eg non ha presentato la richiesta)
		if ($voto == 2) {
			$voto = 'Non Richiesto';
		}
		$result = '<span class=\'label label-info\' style=\'background-color: '.$bgColor.';\'>'.$voto.'</span>';
		return $result;
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
				corso_di_recupero.anno_scolastico_id = '$__anno_scolastico_corrente_id' AND NOT in_itinere
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
						studente_per_corso_di_recupero.email AS studente_per_corso_di_recupero_email,
						studente_per_corso_di_recupero.classe AS studente_per_corso_di_recupero_classe,
						studente_per_corso_di_recupero.voto_settembre AS studente_per_corso_di_recupero_voto_settembre,
						studente_per_corso_di_recupero.voto_settembre_notificato AS studente_per_corso_di_recupero_voto_settembre_notificato,
						studente_per_corso_di_recupero.data_voto_settembre AS studente_per_corso_di_recupero_data_voto_settembre,
						studente_per_corso_di_recupero.voto_novembre AS studente_per_corso_di_recupero_voto_novembre,
						studente_per_corso_di_recupero.voto_novembre_notificato AS studente_per_corso_di_recupero_voto_novembre_notificato,
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
					$passatoMarker = '<span class=\'label label-info\' style=\'background-color: '.$passatoColor.';\'>passato</span>';
				} else if (isset($row_studente['studente_per_corso_di_recupero_passato']) && $row_studente['studente_per_corso_di_recupero_passato'] == 0){
					$passatoMarker = '<span class=\'label label-info\' style=\'background-color: '.$nonPassatoColor.';\'>non passato</span>';
				}
				$esente = (!is_null($row_studente['studente_per_corso_di_recupero_serve_voto'])) && $row_studente['studente_per_corso_di_recupero_serve_voto'] == 0;
				if ($esente) {
					$passatoMarker = '<span class=\'label label-info\'>esente</span>';
				}

				$classname = ($classname==="even_row") ? "odd_row" : "even_row";
				$data .= '
								<tr class="'.$classname.'">
									<td>'.$row_studente['studente_per_corso_di_recupero_id'].'</td>
									<td>'.$row_studente['studente_per_corso_di_recupero_cognome'].' '.$row_studente['studente_per_corso_di_recupero_nome'].'</td>
									<td><small>'.$row_studente['materia_nome'].'</small></td>';

				if (getSettingsValue('corsiDiRecupero', 'corsiDiRecuperoVotoSettembreTuttiIDocenti', true) && $__config->getVoti_recupero_settembre_aperto() && !$esente) {
					$votoSettembre = $row_studente['studente_per_corso_di_recupero_voto_settembre'];
					$votoSettembreOptionList = '				<select  class="votoSettembre selectpicker" data-noneSelectedText="seleziona..." data-width="50%" '.($__config->getVoti_recupero_settembre_aperto() ? '':' disabled').'><option value="0"></option>';
					// opzione per Assente
					$bgColor = $assenteColor;
					$votoSettembreOptionList .= '<option value="'.'1'.'" data-content="<span class=\'label label-info\' style=\'background-color: '.$bgColor.';\'>'.'Assente'.'</span>"';
					if ($votoSettembre == 1) {
						$votoSettembreOptionList .= ' selected ';
					}
					$votoSettembreOptionList .= '>'.'Assente'.'</option>';

					// opzione per Non Richiesto
					$bgColor = $nonRichiestoColor;
					$votoSettembreOptionList .= '<option value="'.'2'.'" data-content="<span class=\'label label-info\' style=\'background-color: '.$bgColor.';\'>'.'Non Richiesto'.'</span>"';
					if ($votoSettembre == 2) {
						$votoSettembreOptionList .= ' selected ';
					}
					$votoSettembreOptionList .= '>'.'Non Richiesto'.'</option>';
					
					// voti da 4 a 10
					for($i = 4; $i<=10; $i++) {
						$bgColor = ($i <= 5) ? $nonPassatoColor : $passatoColor;
						$votoSettembreOptionList .= '<option value="'.$i.'" data-content="<span class=\'label label-info\' style=\'background-color: '.$bgColor.';\'>'.$i.'</span>"';

						// seleziona il voto corrente di settembre se esiste (se e' gia' stato dato)
						if ($votoSettembre == $i) {
							$votoSettembreOptionList .= ' selected ';
						}
						$votoSettembreOptionList .= '>'.$i.'</option>';
					}
					$votoSettembreOptionList .= '</select>';
					$data .= '<td>'.$votoSettembreOptionList.'</td>';

					// la data del voto di settembre
					$dataSettembre = strftime("%d/%m/%Y", strtotime($row_studente['studente_per_corso_di_recupero_data_voto_settembre']));
					if (empty($row_studente['studente_per_corso_di_recupero_data_voto_settembre'])) {
						$dataSettembre = date('d/m/Y');
					}
					$data .= '
    							<td><input type="text" placeholder="Data" class="form-control dataVotoSettembre" value="'.$dataSettembre.'" '.($__config->getVoti_recupero_settembre_aperto() ? '':' disabled').' /></td>';

					$data .= '
									<td><small>'.$row_studente['docente_set_cognome'].' '.$row_studente['docente_set_nome'].'</small></td>
						';
				} else {
					$data .= '
					<td style="text-align: center;">'.printableVoto($row_studente['studente_per_corso_di_recupero_voto_settembre']).'</td>
					<td style="text-align: center;">'.printableDate($row_studente['studente_per_corso_di_recupero_data_voto_settembre']).'</td>
					<td><small>'.$row_studente['docente_set_cognome'].' '.$row_studente['docente_set_nome'].'</small></td>';
				}

				// se i voti di novembre sono aperti, apre l'inserimento per quelli che non sono esenti e non hanno preso almeno 6 a settembre
				if ($__config->getVoti_recupero_novembre_aperto() && ! $esente && $row_studente['studente_per_corso_di_recupero_voto_settembre'] < 6) {

					// prepara la lista dei voti possibili
					$votoNovembre = $row_studente['studente_per_corso_di_recupero_voto_novembre'];
					$votoNovembreOptionList = '				<select  class="votoNovembre selectpicker" data-noneSelectedText="seleziona..." data-width="50%" ><option value="0"></option>';
					// opzione per Assente
					$bgColor = $assenteColor;
					$votoNovembreOptionList .= '<option value="'.'1'.'" data-content="<span class=\'label label-info\' style=\'background-color: '.$bgColor.';\'>'.'Assente'.'</span>"';
					if ($votoNovembre == 1) {
						$votoNovembreOptionList .= ' selected ';
					}
					$votoNovembreOptionList .= '>'.'Assente'.'</option>';

					// opzione per Non Richiesto
					$bgColor = $nonRichiestoColor;
					$votoNovembreOptionList .= '<option value="'.'2'.'" data-content="<span class=\'label label-info\' style=\'background-color: '.$bgColor.';\'>'.'Non Richiesto'.'</span>"';
					if ($votoNovembre == 2) {
						$votoNovembreOptionList .= ' selected ';
					}
					$votoNovembreOptionList .= '>'.'Non Richiesto'.'</option>';

					// voti da 4 a 10
					for($i = 4; $i<=10; $i++) {
						$bgColor = ($i <= 5) ? $nonPassatoColor : $passatoColor;
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
					$data .= '<td><input type="text" placeholder="Data" class="form-control dataVotoNovembre" value="'.$dataNovembre.'" /></td>';

					$data .= '<td><small>'.$row_studente['docente_nov_cognome'].' '.$row_studente['docente_nov_nome'].'</small></td>';
				} else {
					$data .= '	<td style="text-align: center;">'.printableVoto($row_studente['studente_per_corso_di_recupero_voto_novembre']).'</td>
								<td style="text-align: center;">'.printableDate($row_studente['studente_per_corso_di_recupero_data_voto_novembre']).'</td>
								<td><small>'.$row_studente['docente_nov_cognome'].' '.$row_studente['docente_nov_nome'].'</small></td>';
				}
				$data .= '<td class="col-sm-1 text-center">'.$passatoMarker.'</td>';

				// controlla se e' aperta la generazione delle lettere
				if ($__config->getEmail_carenze_aperto()) {
					// se ha il voto solo a settembre, deve generare la lettera di settembre
					if (($row_studente['studente_per_corso_di_recupero_voto_settembre'] > 0) && ($row_studente['studente_per_corso_di_recupero_voto_novembre'] <= 0)) {
						$data .= '<td class="text-center"><button onclick="letteraCarenzeSettembre('.$row_studente['studente_per_corso_di_recupero_id'].')" class="btn btn-orange4 btn-xs" style="display: inline-flex;align-items: center;"><i class="icon-play"></i>&nbsp;Pdf</button>';

						// controlla se configurato per inviare le email in automatico
						if (getSettingsValue("corsiDiRecupero", "corsiDiRecuperoEmailRisulato", false)) {
							// questo solo se lo studente ha l'indirizzo di email inserito
							if (! empty($row_studente['studente_per_corso_di_recupero_email'])) {
								if ($row_studente['studente_per_corso_di_recupero_voto_settembre_notificato']) {
									$data .= '&nbsp;&nbsp;<span class="label label-success">notificato Sett.</span>';
								} else {
									$data .= '&nbsp;&nbsp;<button onclick="emailCarenzeSettembre('.$row_studente['studente_per_corso_di_recupero_id'].')" class="btn btn-deeporange4 btn-xs notificaEmailBtn"><span class="glyphicon glyphicon-envelope"></span>&nbsp;email</button>';
								}
							} else {
								$data .= '&nbsp;&nbsp;<span class="label label-warning">no email</span>';
							}
						}
						$data .= '</td>';
					}
					// se ha il voto a novembre, deve generare la lettera di novembre
					else if (($row_studente['studente_per_corso_di_recupero_voto_novembre'] > 0) ) {
						$data .= '<td class="text-center"><button onclick="letteraCarenzeNovembre('.$row_studente['studente_per_corso_di_recupero_id'].')" class="btn btn-orange4 btn-xs" style="display: inline-flex;align-items: center;"><i class="icon-play"></i>&nbsp;Pdf</button>';

						// controlla se configurato per inviare le email in automatico
						if (getSettingsValue("corsiDiRecupero", "corsiDiRecuperoEmailRisulato", false)) {
							// questo solo se lo studente ha l'indirizzo di email inserito
							if (! empty($row_studente['studente_per_corso_di_recupero_email'])) {
								if ($row_studente['studente_per_corso_di_recupero_voto_novembre_notificato']) {
									$data .= '&nbsp;&nbsp;<span class="label label-success">notificato Nov.</span>';
								} else {
									$data .= '&nbsp;&nbsp;<button onclick="emailCarenzeNovembre('.$row_studente['studente_per_corso_di_recupero_id'].')" class="btn btn-deeporange4 btn-xs notificaEmailBtn"><span class="glyphicon glyphicon-envelope"></span>&nbsp;email Nov.</button>';
								}
							} else {
								$data .= '&nbsp;&nbsp;<span class="label label-warning">no email</span>';
							}
						}
						$data .= '</td>';
					}
					// altrimenti non deve generare niente
					else {
						$data .= '<td></td>';
					}
				}
				// altrimenti non deve generare niente
				else {
					$data .= '<td></td>';
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
<script type="text/javascript" src="js/scriptCorsoDiRecuperoReportStudenti.js?v=<?php echo $__software_version; ?>"></script>

</body>
</html>