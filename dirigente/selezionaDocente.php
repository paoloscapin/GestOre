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
	<title>Seleziona Docente</title>
<?php
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
//require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-select.php';
ruoloRichiesto('dirigente');
?>
</head>

<body >
<?php
require_once '../common/header-dirigente.php';

// prepara l'elenco dei docenti
$docenteOptionList = '				<option value="0"></option>';
$query = "	SELECT * FROM docente
			WHERE docente.attivo = true
			ORDER BY docente.cognome, docente.nome ASC
			;";
foreach(dbGetAll($query) as $row) {
    $docenteOptionList .= '
		<option value="'.$row['id'].'" data-subtext="'.$row['username'].'">'.$row['cognome'].' '.$row['nome'].'</option>';
}
?>

<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-primary">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-4">
			<span class="glyphicon glyphicon-education"></span>&emsp;Seleziona il Docente
		</div>
	</div>
</div>

<div class="panel-body">
    <div class="row">
        <div class="col-md-6">
                <div class="form-group docente_selector text-center">
					<select id="docente" name="docente" class="docente selectpicker open" data-style="btn-warning" data-live-search="true"
					data-noneSelectedText="seleziona..." data-width="80%" >
<?php echo $docenteOptionList ?>
					</select>
                </div>
        </div>
        <div class="col-md-6 text-center">
			<a href="<?php echo $__application_base_path; ?>/segreteria/index.php" class="btn btn-default btn-orange4" role="button"><span class="glyphicon glyphicon-education"></span>&ensp;Segreteria </a>
        </div>
    </div>
</div>

</div>
</div>

<!-- Custom JS file -->
<script type="text/javascript" src="js/scriptDirigente.js"></script>

</body>
</html>