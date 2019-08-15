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
	<title>Fuis</title>
<?php
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';
//require_once '../common/_include_bootstrap-select.php';
ruoloRichiesto('dirigente');
?>

<!-- timejs -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/timejs/date-it-IT.js"></script>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
<script type="text/javascript" src="js/scriptFuis.js"></script>

</head>

<body >
<?php
require_once '../common/header-dirigente.php';
require_once '../common/connect.php';
?>

<div class="container-fluid" style="margin-top:60px">
<div class="row">
<div class="col-md-offset-4 col-md-4">

<div class="panel panel-danger">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-4">
			<span class="glyphicon glyphicon-euro"></span>&emsp;<strong>Fuis</strong>
		</div>
		<div class="col-md-4">
		</div>
		<div class="col-md-4 text-right" id="fuis_totale">
		</div>
	</div>
</div>

<div class="panel-body">
	<table class="table" >
		<tbody>
		<!-- 
    		<thead>
    			<tr>
    				<th></th>
    				<th class="col-md-4 text-right">Fatte</th>
    			</tr>
    		</thead>
    	 -->
			<tr>
				<td>Viaggi</td>
				<td class="col-md-4 text-right" id="fuis_viaggi"></td>
			</tr>
			<tr>
				<td>Assegnato</td>
				<td class="col-md-4 text-right" id="fuis_assegnato"></td>
			</tr>
			<tr>
				<td>Ore</td>
				<td class="col-md-4 text-right" id="fuis_ore"></td>
			</tr>
		</tbody>
	</table>
</div>

<!-- <div class="panel-footer"></div> -->
</div>
</div>
</div>

<div class="row">
<div class="col-md-offset-4 col-md-4">

<div class="panel panel-danger">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-4">
			<span class="glyphicon glyphicon-euro"></span>&emsp;<strong>Fuis CLIL</strong>
		</div>
		<div class="col-md-4">
		</div>
		<div class="col-md-4 text-right" id="fuis_clil_totale">
		</div>
	</div>
</div>

<div class="panel-body">
	<table class="table" >
		<tbody>
			<tr>
				<td>Clil Funzionale</td>
				<td class="col-md-4 text-right" id="fuis_clil_funzionale"></td>
			</tr>
			<tr>
				<td>Clil Con Studenti</td>
				<td class="col-md-4 text-right" id="fuis_clil_con_studenti"></td>
			</tr>
		</tbody>
	</table>
</div>

<!-- <div class="panel-footer"></div> -->
</div>
</div>
</div>

</div>
</body>
</html>
