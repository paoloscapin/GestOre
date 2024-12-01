<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
require_once '../common/checkSession.php';
?>

<!DOCTYPE html>
<html>
<head>
<?php

require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-select.php';
require_once '../common/_include_flatpickr.php';
ruoloRichiesto('segreteria-docenti','dirigente');
?>
	<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
	<title>Viaggi e Uscite</title>
</head>

<body >
<?php
require_once '../common/header-segreteria.php';
require_once '../common/connect.php';
?>

<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-deeporange4">
<div class="panel-heading">
	<div class="row">
		<div class="col-md-4">
			<span class="glyphicon glyphicon-picture"></span>&ensp;Viaggi e Uscite
		</div>
		<div class="col-md-4 text-center">
<!--
            <label class="checkbox-inline">
                <input type="checkbox" checked data-toggle="toggle" data-size="mini" data-onstyle="primary" id="ancheChiusiCheckBox" >Anche Chiusi
            </label>
-->
		</div>
		<div class="col-md-4 text-right">
            <div class="pull-right">
				<button class="btn btn-xs btn-deeporange4" onclick="viaggioNuovo()" ><span class="glyphicon glyphicon-plus"></span></button>
            </div>
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
            <div class="records_content"></div>
        </div>
    </div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>

<?php
// prepara l'elenco dei docenti
$docenteOptionList = '				<option value="0"></option>';
$query = "	SELECT * FROM docente
            WHERE docente.attivo = true
            ORDER BY docente.cognome, docente.nome ASC
            ;";
if (!$result = mysqli_query($con, $query)) {
    exit(mysqli_error($con));
}
if(mysqli_num_rows($result) > 0) {
    $resultArray = $result->fetch_all(MYSQLI_ASSOC);
    foreach($resultArray as $row) {
        $docenteOptionList .= '
            <option value="'.$row['id'].'" >'.$row['cognome'].' '.$row['nome'].'</option>
        ';
    }
}
?>

<!-- Modal - Add New Record -->
<div class="modal fade" id="add_new_record_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-deeporange4">
			<div class="panel-heading">
				<h5 class="modal-title" id="myModalLabel">Viaggio / Uscita</h5>
			</div>
			<div class="panel-body">
			<form class="form-horizontal">

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="data_nomina">Data</label>
					<div class="col-sm-4"><input type="text" value="21/8/2018" id="data_nomina" placeholder="data" class="form-control" /></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="protocollo">Protocollo</label>
                    <div class="col-sm-4"><input type="text" id="protocollo" placeholder="protocollo" class="form-control"/></div>

                    <label class="col-sm-2 control-label" for="tipo_viaggio">Tipo</label>
					<div class="col-sm-4">
						<select id="tipo_viaggio" name="tipo_viaggio" class="tipo_viaggio selectpicker" data-live-search="true" data-noneSelectedText="seleziona..." >
						<option value="Visita Guidata" selected >Visita Guidata</option>
						<option value="Uscita Formativa" >Uscita Formativa</option>
						<option value="Viaggio di Istruzione" >Viaggio di Istruzione</option>
						</select>
					</div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="data_partenza">Dal</label>
					<div class="col-sm-4"><input type="text" value="21/8/2018" id="data_partenza" placeholder="data partenza" class="form-control" /></div>

                    <label class="col-sm-2 control-label" for="data_rientro">Al</label>
					<div class="col-sm-4"><input type="text" value="21/8/2018" id="data_rientro" placeholder="data rientro" class="form-control" /></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="ora_partenza">Partenza</label>
                    <div class="col-sm-4"><input type="text" id="ora_partenza" placeholder="ora partenza" class="form-control"/></div>

                    <label class="col-sm-2 control-label" for="ora_rientro">Rientro</label>
                    <div class="col-sm-4"><input type="text" id="ora_rientro" placeholder="ora rientro" class="form-control"/></div>
                </div>

                <div class="form-group docente_incaricato_selector">
                    <label class="col-sm-2 control-label" for="docente_incaricato">Docente</label>
					<div class="col-sm-8"><select id="docente_incaricato" name="docente_incaricato" class="docente_incaricato selectpicker" data-style="btn-success" data-live-search="true"
					data-noneSelectedText="seleziona..." data-width="70%" >
<?php echo $docenteOptionList ?>
					</select></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="destinazione">Destinazione</label>
                    <div class="col-sm-8"><input type="text" id="destinazione" placeholder="destinazione" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="classe">Classe</label>
                    <div class="col-sm-8"><input type="text" id="classe" placeholder="classe" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="note">Note</label>
                    <div class="col-sm-8"><input type="text" id="note" placeholder="note" class="form-control"/></div>
                </div>
			</form>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" onclick="viaggioAddRecord()">Salva</button>
            </div>
			</div>
			</div>
        </div>
    </div>
</div>
<!-- // Modal - Add New Record/viaggio -->

<!-- Modal - Update viaggio details -->
<div class="modal fade" id="update_record_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="updateMyModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-deeporange4">
			<div class="panel-heading">
				<h5 class="modal-title" id="updateMyModalLabel">Viaggio / Uscita</h5>
			</div>
			<div class="panel-body">
			<form class="form-horizontal">

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="update_data_nomina">Data</label>
					<div class="col-sm-4"><input type="text" value="21/8/2018" id="update_data_nomina" placeholder="data" class="form-control" /></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="update_protocollo">Protocollo</label>
                    <div class="col-sm-4"><input type="text" id="update_protocollo" placeholder="protocollo" class="form-control"/></div>

                    <label class="col-sm-2 control-label" for="update_tipo_viaggio">Tipo</label>
					<div class="col-sm-4">
						<select id="update_tipo_viaggio" name="update_tipo_viaggio" class="update_tipo_viaggio selectpicker" data-live-search="true" data-noneSelectedText="seleziona..." >
						<option value="Visita Guidata" selected >Visita Guidata</option>
						<option value="Uscita Formativa" >Uscita Formativa</option>
						<option value="Viaggio di Istruzione" >Viaggio di Istruzione</option>
						</select>
					</div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="update_data_partenza">Dal</label>
					<div class="col-sm-4"><input type="text" value="21/8/2018" id="update_data_partenza" placeholder="data" class="form-control" /></div>

                    <label class="col-sm-2 control-label" for="update_data_rientro">Al</label>
					<div class="col-sm-4"><input type="text" value="21/8/2018" id="update_data_rientro" placeholder="data" class="form-control" /></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="update_ora_partenza">Partenza</label>
                    <div class="col-sm-4"><input type="text" id="update_ora_partenza" placeholder="ora_partenza" class="form-control"/></div>

                    <label class="col-sm-2 control-label" for="update_ora_rientro">Rientro</label>
                    <div class="col-sm-4"><input type="text" id="update_ora_rientro" placeholder="ora_rientro" class="form-control"/></div>
                </div>

                <div class="form-group docente_incaricato_selector">
                    <label class="col-sm-2 control-label" for="update_docente_incaricato">Docente</label>
					<div class="col-sm-8"><select id="update_docente_incaricato" name="update_docente_incaricato" class="update_docente_incaricato selectpicker" data-style="btn-success" data-live-search="true"
					data-noneSelectedText="seleziona..." data-width="70%" >
<?php echo $docenteOptionList ?>
					</select></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="update_destinazione">Destinazione</label>
                    <div class="col-sm-8"><input type="text" id="update_destinazione" placeholder="destinazione" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="update_classe">Classe</label>
                    <div class="col-sm-8"><input type="text" id="update_classe" placeholder="classe" class="form-control"/></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="update_note">Note</label>
                    <div class="col-sm-8"><input type="text" id="update_note" placeholder="note" class="form-control"/></div>
                </div>

                <div class="form-group stato_selector">
                    <label class="col-sm-2 control-label" for="update_stato">Stato</label></br>
					<div class="col-sm-8"><select id="update_stato" name="update_stato" class="update_stato selectpicker" data-live-search="true"
					data-noneSelectedText="seleziona..." data-width="50%" >
						<option data-content="<span class='label label-info'>assegnato</span>">assegnato</option>
						<option data-content="<span class='label label-success'>accettato</span>">accettato</option>
						<option data-content="<span class='label label-warning'>effettuato</span>">effettuato</option>
						<option data-content="<span class='label label-primary'>evaso</span>">evaso</option>
						<option data-content="<span class='label label-danger'>chiuso</span>">chiuso</option>
						<option data-content="<span class='label label-danger'>annullato</span>">annullato</option>
					</select></div>
                </div>
			</form>
            </div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				<button type="button" class="btn btn-primary" onclick="viaggioUpdateDetails()" >Salva</button>
				<input type="hidden" id="hidden_viaggio_id">
			</div>
			</div>
			</div>
        </div>
    </div>
</div>

<!-- // Modal - RIMBORSO -->
<div class="modal fade" id="rimborso_viaggio_modal" data-backdrop="static" tabindex="3" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-lima4">
			<div class="panel-heading">
            <div class="row">
                <div class="col-md-4">
                    <span class="glyphicon glyphicon-euro"></span>&ensp;Rimborso spese
                </div>
                <div class="col-md-4 text-center" id="rimborso_label_docente"></div>
                <div class="col-md-4 text-right" id="rimborso_label_data"></div>
                </div>
            </div>
			<div class="panel-body">
			<form class="form-horizontal">
                <div class="form-group">
                    <label for="rimborso_destinazione" class="col-sm-2 control-label">Destinazione</label>
                    <div class="col-sm-4"><p class="form-control-static" id="rimborso_destinazione" ></p></div>

                    <label for="rimborso_classe" class="col-sm-2 control-label">Classe</label>
                    <div class="col-sm-4"><p class="form-control-static" id="rimborso_classe" ></p></div>
                </div>

                <div class="form-group">
                    <label for="rimborso_data_partenza" class="col-sm-2 control-label">Dal</label>
                    <div class="col-sm-4"><p class="form-control-static" id="rimborso_data_partenza" ></p></div>

                    <label for="rimborso_data_rientro" class="col-sm-2 control-label">Al</label>
                    <div class="col-sm-4"><p class="form-control-static" id="rimborso_data_rientro" ></p></div>
                </div>

                <div class="form-group">
                    <label for="rimborso_ora_partenza" class="col-sm-2 control-label">Partenza</label>
                    <div class="col-sm-4"><p class="form-control-static" id="rimborso_ora_partenza" ></p></div>

                    <label for="rimborso_ora_rientro" class="col-sm-2 control-label">Rientro</label>
                    <div class="col-sm-4"><p class="form-control-static" id="rimborso_ora_rientro" ></p></div>
                </div>

<hr>
                <div class="form-group">
                    <label for="rimborso_spese_table">Spese</label>
					<div class="table-wrapper">
					<table class="table table-bordered table-striped" id="rimborso_spese_table">
						<thead>
						<tr>
							<th>id</th>
							<th>data</th>
							<th>tipo</th>
							<th>note</th>
							<th class="text-right">importo</th>
							<th class="text-center"></th>
						</tr>
						</thead>
						<tbody>
						</tbody>
					</table>
					</div>
                </div>

			</form>
            </div>
			<div class="panel-footer text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				<button type="button" class="btn btn-success" id="btnRimborsato" onclick="viaggioRimborsato()" >Rimborsato</button>
				<input type="hidden" id="hidden_rimborso_viaggio_id">
				<input type="hidden" id="hidden_rimborso_viaggio_docente_id">
				<input type="hidden" id="hidden_rimborso_viaggio_docente_cognome_e_nome">
			</div>
            </div>
            </div>
        </div>
    </div>
</div>

<!-- // Modal - Rchiesta Chiusura -->
<div class="modal fade" id="chiusura_viaggio_modal" data-backdrop="static" tabindex="3" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-deeporange4">
			<div class="panel-heading">
            <div class="row">
                <div class="col-md-4">
                    <span class="glyphicon glyphicon-picture"></span>&ensp;Chiusura viaggio
                </div>
                <div class="col-md-4 text-center" id="chiusura_label_docente"></div>
                <div class="col-md-4 text-right" id="chiusura_label_data"></div>
                </div>
            </div>
			<div class="panel-body">
			<form class="form-horizontal">
                <div class="form-group">
                    <label for="chiusura_destinazione" class="col-sm-2 control-label">Destinazione</label>
                    <div class="col-sm-4"><p class="form-control-static" id="chiusura_destinazione" ></p></div>

                    <label for="chiusura_classe" class="col-sm-2 control-label">Classe</label>
                    <div class="col-sm-4"><p class="form-control-static" id="chiusura_classe" ></p></div>
                </div>

                <div class="form-group">
                    <label for="chiusura_data_partenza" class="col-sm-2 control-label">Dal</label>
                    <div class="col-sm-4"><p class="form-control-static" id="chiusura_data_partenza" ></p></div>

                    <label for="chiusura_data_rientro" class="col-sm-2 control-label">Al</label>
                    <div class="col-sm-4"><p class="form-control-static" id="chiusura_data_rientro" ></p></div>
                </div>

                <div class="form-group">
                    <label for="chiusura_ora_partenza" class="col-sm-2 control-label">Partenza</label>
                    <div class="col-sm-4"><p class="form-control-static" id="chiusura_ora_partenza" ></p></div>

                    <label for="chiusura_ora_rientro" class="col-sm-2 control-label">Rientro</label>
                    <div class="col-sm-4"><p class="form-control-static" id="chiusura_ora_rientro" ></p></div>
                </div>
                <hr>
                <hr>
                <div class="form-group">
                    <label class="col-sm-3 control-label" for="chiusura_ore_richieste">Ore con Studenti</label>
                    <div class="col-sm-8"><input type="text" id="chiusura_ore_richieste" placeholder="0" class="form-control"/></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3" for="chiusura_idennita_forfettaria">Indennità forfettaria</label>
                    <div class="col-sm-8"><input type="text" id="chiusura_idennita_forfettaria" placeholder="0" class="form-control"/></div>
                </div>
                <?php if(getSettingsValue('viaggi','richiesta_diaria', true)) : ?>
                    <hr>
                    <label for="chiusura_richiesta_fuis" class="col-sm-3 control-label">Richiesta Diaria</label>
                    <div class="col-sm-1 "><input type="checkbox" id="chiusura_richiesta_fuis" ></div>
				<?php endif; ?>
                </div>
			</form>
            
			<div class="panel-footer text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				<button type="button" class="btn btn-success" id="btnChiudi" onclick="viaggioChiudi()" >Chiudi</button>
				<input type="hidden" id="hidden_chiusura_viaggio_id">
				<input type="hidden" id="hidden_chiusura_viaggio_docente_id">
				<input type="hidden" id="hidden_chiusura_viaggio_docente_cognome_e_nome">
			</div>
            </div>
            </div>
        </div>
    </div>
</div>

</div>

<!-- Custom JS file -->
<script type="text/javascript" src="js/scriptViaggio.js"></script>
</body>
</html>