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
	<title>Filtro Fatte Gruppi</title>
<?php
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-select.php';
ruoloRichiesto('dirigente');
?>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-3.css">
<script type="text/javascript" src="js/scriptReportGruppi.js?v=<?php echo $__software_version; ?>"></script>

</head>

<body >
<div class="container-fluid" style="margin-top:60px">

<?php
require_once '../common/header-dirigente.php';
require_once '../common/connect.php';

// prepara l'elenco dei docenti per il filtro
$docenteFiltroOptionList = '<option value="0">tutti</option>';
foreach(dbGetAll("SELECT * FROM docente WHERE docente.attivo = true ORDER BY docente.cognome, docente.nome ASC ; ")as $docente) {
    $docenteFiltroOptionList .= ' <option value="'.$docente['id'].'" >'.$docente['cognome'].' '.$docente['nome'].'</option> ';
}

// prepara l'elenco dei dogruppi per il filtro
$gruppoFiltroOptionList = '<option value="0">tutti</option>';
foreach(dbGetAll("SELECT * FROM `gruppo` WHERE dipartimento = false AND anno_scolastico_id = $__anno_scolastico_corrente_id ORDER BY nome; ")as $gruppo) {
    $gruppoFiltroOptionList .= ' <option value="'.$gruppo['id'].'" >'.$gruppo['nome'].'</option> ';
}

// elenco dei possibili ordinamenti
$ordinamentoOptionList = '<option value="0">Ore (decrescente)</option>';
$ordinamentoOptionList .= '<option value="1">Ore (crescente)</option>';
$ordinamentoOptionList .= '<option value="2">Alfabetico</option>';
?>

<div class="row">
	<div class="col-md-4">
	   <div class="form-group decente_selector text-center">
			<label for="docente">docente</label>
			<select id="docente" name="docente" class="docente selectpicker" data-style="btn-info" data-live-search="true"
			data-noneSelectedText="seleziona..." data-width="50%" >
	<?php echo $docenteFiltroOptionList ?>
			</select>
		</div>
	</div>
	<div class="col-md-4">
	   <div class="form-group gruppo_selector text-center">
			<label for="gruppo">gruppo</label>
			<select id="gruppo" name="gruppo" class="gruppo selectpicker" data-style="btn-info" data-live-search="true"
			data-noneSelectedText="seleziona..." data-width="50%" >
	<?php echo $gruppoFiltroOptionList ?>
			</select>
		</div>
	</div>
	<div class="col-md-4">
	   <div class="form-group ordinamento_selector text-center">
			<label for="ordinamento">Ordinamento</label>
			<select id="ordinamento" name="ordinamento" class="ordinamento selectpicker" data-style="btn-success" data-live-search="true"
			data-noneSelectedText="seleziona..." data-width="50%" >
	<?php echo $ordinamentoOptionList ?>
			</select>
		</div>
	</div>
</div>

<div class="panel panel-lightblue4">
	<div class="records_content"></div>
</div>
</div>

</body>
</html>
