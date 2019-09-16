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
require_once '../common/__i18n.php';

require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-select.php';
ruoloRichiesto('segreteria-docenti','dirigente','docente');
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

<div class="panel panel-warning">
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
				<th class="col-md-2 text-left">Udienze</th>
				<th class="col-md-2 text-left">Dipartimenti</th>
				<th class="col-md-2 text-left">Aggiornamento</th>
				<th class="col-md-2 text-left">CdC</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td class="text-left" >dovute</td>
				<td class="text-left" id="dovute_ore_80_collegi_docenti"></td>
				<td class="text-left" id="dovute_ore_80_udienze_generali"></td>
				<td class="text-left" id="dovute_ore_80_dipartimenti"></td>
				<td class="text-left" id="dovute_ore_80_aggiornamento_facoltativo"></td>
				<td class="text-left" id="dovute_ore_80_consigli_di_classe"></td>
			</tr>
			<tr bgcolor="#bbbbbb">
				<td class="text-left" >previste</td>
				<td class="text-left" id="previste_ore_80_collegi_docenti"></td>
				<td class="text-left" id="previste_ore_80_udienze_generali"></td>
				<td class="text-left" id="previste_ore_80_dipartimenti"></td>
				<td class="text-left" id="previste_ore_80_aggiornamento_facoltativo"></td>
				<td class="text-left" id="previste_ore_80_consigli_di_classe"></td>
			</tr>
			<tr>
				<td class="text-left" >fatte</td>
				<td class="text-left" id="fatte_ore_80_collegi_docenti"></td>
				<td class="text-left" id="fatte_ore_80_udienze_generali"></td>
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


<div class="panel panel-success">
<div class="panel-heading">
	<span class="glyphicon glyphicon-list-alt"></span>
	<a data-toggle="collapse" href="#collapse_40">&ensp;40 ore </a>
</div>
<div id="collapse_40" class="panel-collapse collapse  collapse in">
<div class="panel-body">

	<div class="table-wrapper">
	<table class="table table-vnocolor-index">
		<thead>
			<tr>
				<th class="col-md-2"></th>
				<th class="col-md-3 text-left">Sostituzioni</th>
				<th class="col-md-3 text-left">Aggiornamento</th>
				<th class="col-md-3 text-left">con Studenti</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>dovute</td>
				<td class="text-left" id="dovute_ore_40_sostituzioni_di_ufficio"></td>
				<td class="text-left" id="dovute_ore_40_aggiornamento"></td>
				<td class="text-left" id="dovute_ore_40_con_studenti"></td>
			</tr>
			<tr bgcolor="#bbbbbb">
				<td>previste</td>
				<td class="text-left" id="previste_ore_40_sostituzioni_di_ufficio"></td>
				<td class="text-left" id="previste_ore_40_aggiornamento"></td>
				<td class="text-left" id="previste_ore_40_con_studenti"></td>
			</tr>
			<tr>
				<td>fatte</td>
				<td class="text-left" id="fatte_ore_40_sostituzioni_di_ufficio"></td>
				<td class="text-left" id="fatte_ore_40_aggiornamento"></td>
				<td class="text-left" id="fatte_ore_40_con_studenti"></td>
			</tr>
		</tbody>
	</table>
	</div>
</div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>


<div class="panel panel-info">
<div class="panel-heading">
	<span class="glyphicon glyphicon-list-alt"></span>
	<a data-toggle="collapse" href="#collapse_70">&ensp;70 ore </a>
</div>
<div id="collapse_70" class="panel-collapse collapse  collapse in">
<div class="panel-body">

	<div class="table-wrapper">
	<table class="table table-vnocolor-index">
		<thead>
			<tr>
				<th class="col-md-5"></th>
				<th class="col-md-3 text-left"><?php echo __("Funzionali"); ?></th>
				<th class="col-md-3 text-left">con Studenti</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>dovute</td>
				<td class="text-left" id="dovute_ore_70_funzionali"></td>
				<td class="text-left" id="dovute_ore_70_con_studenti"></td>
			</tr>
			<tr bgcolor="#bbbbbb">
				<td>previste</td>
				<td class="text-left" id="previste_ore_70_funzionali"></td>
				<td class="text-left" id="previste_ore_70_con_studenti"></td>
			</tr>
			<tr>
				<td>fatte</td>
				<td class="text-left" id="fatte_ore_70_funzionali"></td>
				<td class="text-left" id="fatte_ore_70_con_studenti"></td>
			</tr>
		</tbody>
	</table>
	</div>
</div>
</div>
<!-- <div class="panel-footer"></div> -->
</div>


<div class="panel panel-primary" id="panel-clil">
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
				<th class="col-md-3 text-left"><?php echo __("Funzionali"); ?></th>
				<th class="col-md-3 text-left">con Studenti</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>previste</td>
				<td class="text-left" id="clil_previste_ore_70_funzionali"></td>
				<td class="text-left" id="clil_previste_ore_70_con_studenti"></td>
			</tr>
			<tr>
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

<div class="panel panel-danger">
<div class="panel-heading">
	<span class="glyphicon glyphicon-list-alt"></span>
	<a data-toggle="collapse" href="#collapse_Previste">&ensp;Ore Previste </a>
</div>
<div id="collapse_Previste" class="panel-collapse collapse  collapse in">
<div class="panel-body">
    <div class="row"  style="margin-bottom:10px;">
        <div class="col-md-6">
        </div>
        <div class="col-md-6">
            <div class="pull-right">
            <?php
            if ($__config->getOre_previsioni_aperto()) {
            	echo '
   				<button class="btn btn-success" data-toggle="modal" onclick="attivitaPrevistaAdd()"><span class="glyphicon glyphicon-plus"></span>&ensp;Aggiungi attività </button>
				';
            }
   			?>
   			</div>
        </div>
    </div>

<div id="notificationBlock"></div>

    <div class="row">
        <div class="col-md-12">
            <div class="attivita_previste_records_content"></div>
        </div>
    </div>
</div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>

<?php
// prepara l'elenco dei tipi di attivita
$categoria = '';
$tipoAttivitaOptionList = '				<option value="0"></option>';
$query = "	SELECT * FROM ore_previste_tipo_attivita
			WHERE ore_previste_tipo_attivita.valido = true
			ORDER BY ore_previste_tipo_attivita.categoria DESC, ore_previste_tipo_attivita.nome ASC
			;";
$resultArray = dbGetAll($query);
foreach($resultArray as $row) {
	if ($categoria !== $row['categoria']) {
		if ($categoria !== '') {
			$tipoAttivitaOptionList .= '</optgroup>';
		}
		$categoria = $row['categoria'];
		$tipoAttivitaOptionList .= '<optgroup label="'.$categoria.'">';
	}
	// se ha un numero fisso di ore o un max, lo segnala
	$subtext = '';
	if ($row['ore'] != 0) {
		$subtext = ' data-subtext="'.$row['ore'].' ore"';
	} else if ($row['ore_max'] != 0) {
		$subtext = ' data-subtext="max '.$row['ore_max'].' ore"';
	}
	// se non va inserito dal docente lo disabilito
	$disable = '';
	if (! $row['inserito_da_docente']) {
		$disable = ' disabled ';
	}
	if ($row['inserito_da_docente']) {
		$tipoAttivitaOptionList .= '
			<option value="'.$row['id'].'"'.$subtext.$disable.' >'.$row['nome'].'</option>
			';
	}
}
$tipoAttivitaOptionList .= '</optgroup>';
?>

<!-- Modal - attivita details -->
<div class="modal fade" id="update_attivita_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel">Attività Prevista</h4>
            </div>
            <div class="modal-body">
			<div class="form-horizontal">

                <div class="form-group tipo_attivita_selector">
                    <label class="col-sm-3 control-label" for="tipo_attivita">Tipo attività</label>
					<div class="col-sm-8"><select id="tipo_attivita" name="tipo_attivita" class="tipo_attivita selectpicker" data-live-search="true"
					data-noneSelectedText="seleziona..." data-width="70%" >
<?php echo $tipoAttivitaOptionList ?>
					</select></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-3 control-label" for="update_ore">Ore</label>
                    <div class="col-sm-3"><input type="text" id="update_ore" placeholder="ore" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-3 control-label" for="update_dettaglio">dettaglio</label>
                    <div class="col-sm-9"><input type="text" id="update_dettaglio" placeholder="specificare solo se necessario" class="form-control"/></div>
                </div>
            </div>
            </div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				<button type="button" class="btn btn-primary" onclick="attivitaPrevistaUpdateDetails()" >Salva</button>
				<input type="hidden" id="hidden_ore_previste_attivita_id">
			</div>
        </div>
    </div>
</div>
<!-- // Modal - attivita details -->

</div>

<!-- bootbox notificator -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>

<script type="text/javascript" src="js/scriptIndex.js"></script>
</body>
</html>
