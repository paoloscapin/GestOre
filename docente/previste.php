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
ruoloRichiesto('segreteria-docenti','dirigente','docente');
?>
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-vcolor-index.css">
	<title>Ore Previste</title>
</head>

<body >
<?php
require_once '../common/header-docente.php';
?>

<div class="container-fluid" style="margin-top:60px">

<div class="panel panel-orange4">
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
   				<button class="btn btn-orange4" data-toggle="modal" onclick="attivitaPrevistaAdd()"><span class="glyphicon glyphicon-plus"></span>&ensp;Aggiungi attività </button>
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
	// se non va previsto dal docente lo disabilito
	$disable = '';
	if (! $row['previsto_da_docente']) {
		$disable = ' disabled ';
	}
	if ($row['previsto_da_docente']) {
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
                <h5 class="modal-title text-center" id="myModalLabel">Attività Prevista</h5>
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

<script type="text/javascript" src="js/scriptPreviste.js"></script>
</body>
</html>
