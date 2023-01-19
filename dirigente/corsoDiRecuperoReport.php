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
require_once '../common/style.php';
require_once '../common/_include_bootstrap-select.php';
ruoloRichiesto('segreteria','segreteria-didattica','dirigente');
?>
	<title>Corsi di Recupero</title>
</head>

<body >
<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">

<?php
require_once '../common/header-dirigente.php';
require_once '../common/connect.php';

// prepara l'elenco di corsi
$corso_di_recuperoOptionList = '				<option value="0"></option>';
$query = "	SELECT
				corso_di_recupero.id AS corso_di_recupero_id,
				corso_di_recupero.codice AS corso_di_recupero_codice,
				docente.nome AS docente_nome,
				docente.cognome AS docente_cognome
			FROM
				corso_di_recupero corso_di_recupero
			INNER JOIN docente docente
			ON corso_di_recupero.docente_id = docente.id
			WHERE
				corso_di_recupero.anno_scolastico_id = '$__anno_scolastico_corrente_id'
			ORDER BY
				corso_di_recupero.codice ASC;
		";

foreach(dbGetAll($query) as $row) {
	$corso_di_recuperoOptionList .= '
		<option value="'.$row['corso_di_recupero_id'].'" data-subtext="'.$row['docente_cognome'].' '.$row['docente_nome'].'">'.$row['corso_di_recupero_codice'].'</option>';
}

// prepara l'elenco di docenti
$docenteOptionList = '				<option value="0"></option>';
$query = "	SELECT DISTINCT
				docente.id AS docente_id,
				docente.cognome AS docente_cognome,
				docente.nome AS docente_nome
			FROM
				docente docente
			INNER JOIN corso_di_recupero corso_di_recupero
			ON corso_di_recupero.docente_id = docente.id
			WHERE
				corso_di_recupero.anno_scolastico_id = '$__anno_scolastico_corrente_id'
			ORDER BY
				docente.cognome ASC;
		";

foreach(dbGetAll($query) as $row) {
    $docenteOptionList .= '
				<option value="'.$row['docente_id'].'" >'.$row['docente_cognome'].' '.$row['docente_nome'].'</option>';
}

?>
<div class="row">
	<div class="col-md-1">
	</div>
	<div class="col-md-5">
	   <div class="form-group corso_di_recupero_selector text-center">
			<label for="corso_di_recupero">Corso di Recupero</label>
			<select id="corso_di_recupero" name="corso_di_recupero" class="corso_di_recupero selectpicker" data-style="btn-info" data-live-search="true"
			data-noneSelectedText="seleziona..." data-width="50%" >
	<?php echo $corso_di_recuperoOptionList ?>
			</select>
		</div>
	</div>
	<div class="col-md-5">
	   <div class="form-group docente_selector text-center">
			<label for="docente">Docente</label>
			<select id="docente" name="docente" class="docente selectpicker" data-style="btn-success" data-live-search="true"
			data-noneSelectedText="seleziona..." data-width="50%" >
	<?php echo $docenteOptionList ?>
			</select>
		</div>
	</div>
	<div class="col-md-1">
		<button class="btn btn-xs btn-teal4" onclick="esporta()" ><span class="glyphicon glyphicon-download">&emsp;Esporta Risultati</span></button>
	</div>
</div>

<div class="panel panel-primary">
	<div class="records_content"></div>
</div>
</div>

<!-- Bootstrap, jquery etc (css + js) -->
<?php
	require_once '../common/style.php';
?>

<!-- boostrap-select -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/bootstrap-select/js/bootstrap-select.min.js"></script>
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/bootstrap-select/js/i18n/defaults-it_IT.min.js"></script>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green.css">

<!-- Custom JS file -->
<script type="text/javascript" src="js/scriptCorsoDiRecuperoReport.js"></script>

</body>
</html>
