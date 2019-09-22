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
//require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-select.php';
require_once '../common/_include_flatpickr.php';
ruoloRichiesto('segreteria-docenti','dirigente','docente');
?>
	<title>Ore Fatte</title>
</head>

<body >
<?php
require_once '../common/header-docente.php';
require_once '../common/connect.php';
?>

<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-teal4">
<div class="panel-heading">
	<div class="row">
		<div class="col-md-4">
			<span class="glyphicon glyphicon-education"></span>&ensp;Attività Fatte
		</div>
		<div class="col-md-4 text-center">
			<button onclick="oreFatteSommario()" class="btn btn-xs btn-teal4"><span class="glyphicon glyphicon-option-horizontal"></span> Sommario</button>
		</div>
		<div class="col-md-4 text-right">
            <?php
            if ($__config->getOre_fatte_aperto()) {
            	echo '
					<button onclick="oreFatteGetAttivita(0)" class="btn btn-xs btn-teal4"><span class="glyphicon glyphicon-plus"></span></button>
				';
            }
   			?>
		</div>
	</div>
</div>
<div class="panel-body">
    <div class="row"  style="margin-bottom:10px;">
        <div class="col-md-6">
        </div>
        <div class="col-md-6">
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="attivita_fatte_records_content"></div>
        </div>
    </div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>

<?php if($__settings->config->gestioneClil) : ?>
<div class="panel panel-info">
<div class="panel-heading">
	<div class="row">
		<div class="col-md-4">
		<span class="glyphicon glyphicon-education"></span>&ensp;Attività CLIL
		</div>
		<div class="col-md-4 text-center">
			<button onclick="oreFatteClilSommario()" class="btn btn-xs btn-info"><span class="glyphicon glyphicon-option-horizontal"></span> Sommario</button>
		</div>
		<div class="col-md-4 text-right">
            <?php
            if ($__config->getOre_fatte_aperto()) {
            	echo '
					<button onclick="oreFatteClilGetAttivita(0)" class="btn btn-xs btn-info"><span class="glyphicon glyphicon-plus"></span></button>
				';
            }
   			?>
		</div>
	</div>
</div>
<div class="panel-body">
    <div class="row"  style="margin-bottom:10px;">
        <div class="col-md-6">
        </div>
        <div class="col-md-6">
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="attivita_fatte_clil_records_content"></div>
        </div>
    </div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>
<?php endif; ?>

<div class="panel panel-lightblue4">
<div class="panel-heading">
	<div class="row">
		<div class="col-md-4">
		<span class="glyphicon glyphicon-user"></span>&ensp;Gruppi
		</div>
		<div class="col-md-4 text-center">
		</div>
		<div class="col-md-4 text-right">
		</div>
	</div>
</div>
<div class="panel-body">
    <div class="row"  style="margin-bottom:10px;">
        <div class="col-md-6">
        </div>
        <div class="col-md-6">
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="attivita_fatte_gruppi_records_content"></div>
        </div>
    </div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>

<div class="panel panel-yellow4">
<div class="panel-heading">
	<div class="row">
		<div class="col-md-4">
		<span class="glyphicon glyphicon-list-alt"></span>&ensp;Attribuite
		</div>
		<div class="col-md-4 text-center">
		</div>
		<div class="col-md-4 text-right">
		</div>
	</div>
</div>
<div class="panel-body">
    <div class="row"  style="margin-bottom:10px;">
        <div class="col-md-6">
        </div>
        <div class="col-md-6">
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="attribuite_records_content"></div>
        </div>
    </div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>

<div class="panel panel-deeporange4">
<div class="panel-heading">
	<div class="row">
		<div class="col-md-4">
		<span class="glyphicon glyphicon-picture"></span>&ensp;Viaggi
		</div>
		<div class="col-md-4 text-center">
		</div>
		<div class="col-md-4 text-right">
		</div>
	</div>
</div>
<div class="panel-body">
    <div class="row"  style="margin-bottom:10px;">
        <div class="col-md-6">
        </div>
        <div class="col-md-6">
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="viaggi_records_content"></div>
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
			if ($row['da_rendicontare']) {
				$tipoAttivitaOptionList .= '
				<option value="'.$row['id'].'"'.$subtext.$disable.' >'.$row['nome'].'</option>
				';
			}
		}
	}
	$tipoAttivitaOptionList .= '</optgroup>';
?>

<!-- Modal - attivita details -->
<div class="modal fade" id="docente_attivita_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-teal4">
			<div class="panel-heading">
			<h5 class="modal-title text-center" id="myModalLabel">Attività</h5>
			</div>
			<div class="panel-body">
			<div class="form-horizontal">

                <div class="form-group tipo_attivita_selector">
                    <label class="col-sm-2 control-label" for="tipo_attivita">Tipo attività</label>
					<div class="col-sm-6">
						<select id="attivita_tipo_attivita" name="attivita_tipo_attivita" class="attivita_tipo_attivita selectpicker" data-live-search="true"
					data-noneSelectedText="seleziona..." data-width="70%" >
<?php echo $tipoAttivitaOptionList ?>
						</select>
					</div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="attivita_data">Data</label>
					<div class="col-sm-4"><input type="text" value="21/8/2018" id=attivita_data placeholder="data" class="form-control" /></div>

                    <label class="col-sm-2 control-label" for="attivita_ora_inizio">Alle</label>
                    <div class="col-sm-4"><input type="text" id="attivita_ora_inizio" placeholder="ora inizio" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="attivita_ore">Ore</label>
                    <div class="col-sm-3"><input type="text" id="attivita_ore" placeholder="ore" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="attivita_dettaglio">Dettaglio</label>
                    <div class="col-sm-9"><input type="text" id="attivita_dettaglio" placeholder="specificare se necessario" class="form-control"/></div>
                </div>
            </div>
            </div>
			<div class="modal-footer">
			<div class="col-sm-12 text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				<button type="button" class="btn btn-primary" onclick="attivitaFattaUpdateDetails()" >Salva</button>
				<input type="hidden" id="hidden_ore_fatte_attivita_id">
			</div>
			</div>
        	</div>
        	</div>
    	</div>
    </div>
</div>
<!-- // Modal - attivita details -->

<!-- Modal - registro details -->
<div class="modal fade" id="docente_registro_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-teal4">
			<div class="panel-heading">
			<h5 class="modal-title text-center" id="myModalLabel">Registro Attività</h5>
			</div>
			<div class="panel-body">
			<div class="form-horizontal">
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="registro_tipo_attivita">Tipo attività</label>
                    <div class="col-sm-4" id="registro_tipo_attivita"></div>

                    <label class="col-sm-2 control-label" for="registro_attivita_dettaglio">Dettaglio</label>
                    <div class="col-sm-4" id="registro_attivita_dettaglio"></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="registro_attivita_data">Data</label>
                    <div class="col-sm-2" id="registro_attivita_data"></div>

                    <label class="col-sm-2 control-label" for="registro_attivita_ora_inizio">Alle</label>
                    <div class="col-sm-2" id="registro_attivita_ora_inizio"></div>

	                <div class="form-group">
	                    <label class="col-sm-2 control-label" for="registro_attivita_ore">Ore</label>
	                    <div class="col-sm-2" id="registro_attivita_ore"></div>
	                </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="registro_descrizione">Descrizione</label>
                    <div class="col-sm-9"><textarea class="form-control" rows="3" id="registro_descrizione" placeholder="descrizione" ></textarea></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="registro_descrizione">Studenti</label>
                    <div class="col-sm-9"><textarea class="form-control" rows="3" id="registro_studenti" placeholder="studenti" ></textarea></div>
                </div>
            </div>
            </div>
			<div class="modal-footer">
			<div class="col-sm-12 text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				<button type="button" class="btn btn-primary" onclick="attivitaFattaRegistroUpdateDetails()" >Salva</button>
				<input type="hidden" id="hidden_ore_fatte_registro_id">
				<input type="hidden" id="hidden_registro_clil">
			</div>
			</div>
        	</div>
        	</div>
    	</div>
    </div>
</div>
<!-- // Modal - registro details -->

<!-- Modal - rendiconto details -->
<div class="modal fade" id="docente_rendiconto_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-teal4">
			<div class="panel-heading">
				<h5 class="modal-title text-center" id="myModalLabel">Rendiconto Attività</h5>
			</div>
			<div class="panel-body">
			<div class="form-horizontal">
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="rendiconto_tipo_attivita">Tipo attività</label>
                    <div class="col-sm-4" id="rendiconto_tipo_attivita"></div>

                    <label class="col-sm-2 control-label" for="rendiconto_attivita_dettaglio">Dettaglio</label>
                    <div class="col-sm-4" id="rendiconto_attivita_dettaglio"></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="rendiconto_rendiconto">Rendiconto</label>
                    <div class="col-sm-9"><textarea class="form-control" rows="3" id="rendiconto_rendiconto" placeholder="rendiconto" ></textarea></div>
                </div>
            </div>
            </div>
			<div class="modal-footer">
			<div class="col-sm-12 text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				<button type="button" class="btn btn-primary" onclick="attivitaFattaRendicontoUpdateDetails()" >Salva</button>
				<input type="hidden" id="hidden_ore_fatte_rendiconto_id">
			</div>
			</div>
        	</div>
        	</div>
    	</div>
    </div>
</div>
<!-- // Modal - rendiconto details -->

<!-- Modal - sommario details -->
<div class="modal fade" id="docente_sommario_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-teal4">
			<div class="panel-heading">
				<h5 class="modal-title text-center" id="myModalLabel">Sommario Attività</h5>
			</div>
            <div class="panel-body">
                <div class="row"  style="margin-bottom:10px;">
                    <div class="col-md-6">
                    </div>
                    <div class="col-md-6">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="sommario_attivita_records_content"></div>
                    </div>
                </div>
            </div>
			<div class="modal-footer">
			<div class="col-sm-12 text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>
			</div>
			</div>
        	</div>
        	</div>
    	</div>
    </div>
</div>
<!-- // Modal - rendiconto details -->

<!-- Modal - attivita clil details -->
<div class="modal fade" id="docente_attivita_clil_modal" tabindex="-1" role="dialog" aria-labelledby="myModal_clilLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-teal4">
			<div class="panel-heading">
			<h5 class="modal-title text-center" id="myModal_clilLabel">Attività CLIL</h5>
			</div>
			<div class="panel-body">
			<div class="form-horizontal">

                <div class="form-group tipo_attivita_selector">
                    <label class="col-sm-2 control-label" for="tipo_attivita">Con Studenti</label>
					<div class="col-sm-6">
						<input type="checkbox" data-toggle="toggle" data-onstyle="primary" id="clil_con_studenti" checked>
					</div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="attivita_clil_data">Data</label>
					<div class="col-sm-4"><input type="text" value="21/8/2018" id=attivita_clil_data placeholder="data" class="form-control" /></div>

                    <label class="col-sm-2 control-label" for="attivita_clil_ora_inizio">Alle</label>
                    <div class="col-sm-4"><input type="text" id="attivita_clil_ora_inizio" placeholder="ora inizio" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="attivita_clil_ore">Ore</label>
                    <div class="col-sm-3"><input type="text" id="attivita_clil_ore" placeholder="ore" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="attivita_clil_dettaglio">Dettaglio</label>
                    <div class="col-sm-9"><input type="text" id="attivita_clil_dettaglio" placeholder="specificare se necessario" class="form-control"/></div>
                </div>
            </div>
            </div>
			<div class="modal-footer">
			<div class="col-sm-12 text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				<button type="button" class="btn btn-primary" onclick="attivitaFattaClilUpdateDetails()" >Salva</button>
				<input type="hidden" id="hidden_ore_fatte_clil_attivita_id">
			</div>
			</div>
        	</div>
        	</div>
    	</div>
    </div>
</div>
<!-- // Modal - attivita clil details -->

</div>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">

<!-- Custom JS file -->
<script type="text/javascript" src="js/scriptAttivita.js"></script>

</body>
</html>