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
require_once '../common/__Minuti.php';
ruoloRichiesto('segreteria-docenti','dirigente','docente','segreteria-didattica');
?>
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-vcolor-index.css">
	<title>Piano Orario</title>
</head>

<body >
<?php
require_once '../common/header-docente.php';
require_once '../common/connect.php';
?>

<div class="container-fluid" style="margin-top:60px">

<div class="panel panel-lima4">
<div class="panel-heading">
	<span class="glyphicon glyphicon-list-alt"></span>
	<a data-toggle="collapse" href="#collapse_40">&ensp;40+70 ore </a>
</div>
<div id="collapse_40" class="panel-collapse collapse  collapse in">
<div class="panel-body">

	<div class="table-wrapper">
	<table class="table table-vnocolor-index">
		<thead>
			<tr>
				<th class="col-md-2"></th>
				<th class="col-md-2 text-left">
				<?php if(getSettingsValue('interfaccia','visualizzaSostituzioniDocente', true)) : ?>
				Sostituzioni
				<?php endif; ?>
				</th>
				<th class="col-md-2 text-left">Aggiornamento</th>
				<th class="col-md-2 text-left"><?php echoLabel('Funzionali');?></th>
				<th class="col-md-2 text-left">con Studenti</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><?php echoLabel('dovute');?></td>
				<?php if(getSettingsValue('interfaccia','visualizzaSostituzioniDocente', true)) : ?>
				<td class="text-left" id="dovute_ore_40_sostituzioni_di_ufficio"></td>
				<?php else : ?>
				<td class="text-left" id="dovute_ore_40_sostituzioni_di_ufficio_no"></td>
				<?php endif; ?>
				<td class="text-left" id="dovute_ore_40_aggiornamento"></td>
				<td class="text-left" id="dovute_ore_70_funzionali"></td>
				<td class="text-left" id="dovute_totale_con_studenti"></td>
			</tr>
			<tr class="orange5">
				<td>previste</td>
				<?php if(getSettingsValue('interfaccia','visualizzaSostituzioniDocente', true)) : ?>
				<td class="text-left" id="previste_ore_40_sostituzioni_di_ufficio"></td>
				<?php else : ?>
				<td class="text-left" id="previste_ore_40_sostituzioni_di_ufficio_no"></td>
				<?php endif; ?>
				<td class="text-left" id="previste_ore_40_aggiornamento"></td>
				<td class="text-left" id="previste_ore_70_funzionali"></td>
				<td class="text-left" id="previste_totale_con_studenti"></td>
			</tr>
			<tr class="teal5">
				<td>fatte</td>
				<?php if(getSettingsValue('interfaccia','visualizzaSostituzioniDocente', true)) : ?>
				<td class="text-left" id="fatte_ore_40_sostituzioni_di_ufficio"></td>
				<?php else : ?>
				<td class="text-left" id="fatte_ore_40_sostituzioni_di_ufficio_no"></td>
				<?php endif; ?>
				<td class="text-left" id="fatte_ore_40_aggiornamento"></td>
				<td class="text-left" id="fatte_ore_70_funzionali"></td>
				<td class="text-left" id="fatte_totale_con_studenti"></td>
			</tr>
		</tbody>
	</table>
	</div>
</div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>

<?php if($__settings->config->gestioneClil) : ?>

<div class="panel panel-lightblue4 hidden" id="panel-clil">
<div class="panel-heading">
	<span class="glyphicon glyphicon-list-alt"></span>&ensp;Clil
</div>
<div id="collapse_clil" class="panel-collapse collapse  collapse in">
<div class="panel-body">

	<div class="table-wrapper">
	<table class="table table-vnocolor-index">
		<thead>
			<tr>
				<th class="col-md-5"></th>
				<th class="col-md-3 text-left"><?php echoLabel('Incontri CLIL');?></th>
				<th class="col-md-3 text-left"><?php echoLabel('Preparazione Materiali CLIL');?></th>
			</tr>
		</thead>
		<tbody>
			<tr class="orange5">
				<td>previste</td>
				<td class="text-left" id="clil_previste_funzionali"></td>
				<td class="text-left" id="clil_previste_con_studenti"></td>
			</tr>
			<tr>
			<tr class="teal5">
				<td>fatte</td>
				<td class="text-left" id="clil_fatte_funzionali"></td>
				<td class="text-left" id="clil_fatte_con_studenti"></td>
			</tr>
		</tbody>
	</table>
	</div>
</div>
</div>
<!-- <div class="panel-footer"></div> -->
</div>

<?php else : ?>

<?php endif; ?>

<?php if(getSettingsValue('interfaccia','docenteVisualizza80Ore', true)) : ?>

<div class="panel panel-yellow4" id="panel-80-ore" >
<div class="panel-heading">
	<span class="glyphicon glyphicon-list-alt"></span>
	<a data-toggle="collapse" href="#collapse_80">&ensp;80 ore </a>
</div>
<div id="collapse_80" class="panel-collapse collapse  collapse in">
<div class="panel-body">

	<div class="table-wrapper">
	<table class="table table-vnocolor-index">
		<thead>
			<tr>
				<th class="col-md-2 text-left"></th>
				<th class="col-md-2 text-left">Collegio Doc.</th>
				<?php if($__settings->ore80->ore_max_udienze_generali > 0) : ?>
				<th class="col-md-2 text-left">Udienze</th>
				<?php endif; ?>
				<th class="col-md-2 text-left">Dipartimenti</th>
				<th class="col-md-2 text-left">Aggiornamento</th>
				<th class="col-md-2 text-left">CdC</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td class="text-left" ><?php echoLabel('dovute');?></td>
				<td class="text-left" id="dovute_ore_80_collegi_docenti"></td>
				<?php if($__settings->ore80->ore_max_udienze_generali > 0) : ?>
				<td class="text-left" id="dovute_ore_80_udienze_generali"></td>
				<?php endif; ?>
				<td class="text-left" id="dovute_ore_80_dipartimenti"></td>
				<td class="text-left" id="dovute_ore_80_aggiornamento_facoltativo"></td>
				<td class="text-left" id="dovute_ore_80_consigli_di_classe"></td>
			</tr>
<?php if(false) : ?>
			<tr class="orange5">
				<td class="text-left" >previste</td>
				<td class="text-left" id="previste_ore_80_collegi_docenti"></td>
				<td class="text-left" id="previste_ore_80_udienze_generali"></td>
				<td class="text-left" id="previste_ore_80_dipartimenti"></td>
				<td class="text-left" id="previste_ore_80_aggiornamento_facoltativo"></td>
				<td class="text-left" id="previste_ore_80_consigli_di_classe"></td>
			</tr>
<?php endif; ?>
			<tr class="teal5">
				<td class="text-left" >fatte</td>
				<td class="text-left" id="fatte_ore_80_collegi_docenti"></td>
				<?php if($__settings->ore80->ore_max_udienze_generali > 0) : ?>
				<td class="text-left" id="fatte_ore_80_udienze_generali"></td>
				<?php endif; ?>
				<td class="text-left" id="fatte_ore_80_dipartimenti"></td>
				<td class="text-left" id="fatte_ore_80_aggiornamento_facoltativo"></td>
				<td class="text-left" id="fatte_ore_80_consigli_di_classe"></td>
			</tr>
		</tbody>
	</table>
	</div>
</div>
</div>
<!-- <div class="panel-footer"></div> -->
</div>

<?php endif; ?>

<?php if(getSettingsValue('interfaccia','docenteVisualizzaFuis', false)) : ?>

	<div class="panel panel-deeporange4" id="panel-fuis">
<div class="panel-heading">
	<span class="glyphicon glyphicon-list-alt"></span>&ensp;FUIS
</div>
<div id="collapse_fuis" class="panel-collapse collapse  collapse in">
<div class="panel-body">
    <div class="row"  style="margin-bottom:10px;">
        <div class="col-md-6">
        </div>
        <div class="col-md-6">
        </div>
    </div>
	<div id="notificationBlock"></div>
    <div class="row">
        <div class="col-md-12">
            <div class="fuis_docente_records_content"></div>
        </div>
    </div>
</div>
</div>
<!-- <div class="panel-footer"></div> -->
</div>

<?php endif; ?>

</div>

<!-- bootbox notificator -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>

<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/js/_util.js"></script>
<script type="text/javascript" src="js/scriptIndex.js"></script>
</body>
</html>