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
	<title>Richieste Studenti</title>
<?php
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-select.php';
require_once '../common/_include_flatpickr.php';
require_once '../common/_include_summernote.php';
ruoloRichiesto('studente','segreteria-didattica','dirigente');
?>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-3.css">
<!-- Custom JS file moved to the end -->

</head>


<?php
// prepara l'elenco dei docenti
$docenteOptionList = '				<option value="0"></option>';
foreach(dbGetAll("SELECT * FROM docente WHERE docente.attivo = true ORDER BY docente.cognome, docente.nome ASC ; ")as $docente) {
    $docenteOptionList .= ' <option value="'.$docente['id'].'" >'.$docente['cognome'].' '.$docente['nome'].'</option>';
}
?>

<body >
<?php
require_once '../common/header-studente.php';
require_once '../common/connect.php';
?>

<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-yellow4">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-11">
			<span class="glyphicon glyphicon-folder-close"></span>&emsp;<strong>Richieste Studenti</strong>
		</div>
		<div class="col-md-1 text-right" id="page_refresh">
		</div>
	</div>
</div>
<div class="panel-body">
    <div class="row">
    <div class="col-md-12">

<?php
$openTabMode = getSettingsValue('interfaccia','apriModuloInNuovoTab', false) ? '_blank' : '_self';
$studente_id = $__studente_id;

// noem e cognome insieme
$studente_nome_cognome = $__studente_nome . ' ' . $__studente_cognome;
$studente_email = $__studente_email; 

// trova la classe a cui lo studente appartiene
$classe = dbGetValue("SELECT classe FROM studente WHERE id = $studente_id");
?>

<div class="table-wrapper">
<table id="manno" class="table table-bordered table-striped table-green">
    <thead> <tr> <th class="text-center col-md-12" style="background-color: #ffb39b;" >Richiesta Assemblee di Classe</th></tr></thead>
    <tbody><tr>
        <td>Rappresentante di classe:

<p>
    nuovo paragrgafo primo
</p>

<p>
    nuovo paragrgafo primo
</p>

<p>
    
</p>
</tr><tr>

        </td>
        <td class="text-center col-md-12" style="background-color: #edfcda;" ><button onclick="richiestaCompila(-1)" class="btn btn-xs btn-teal4"><span class="glyphicon glyphicon-plus"></span>&nbsp;&nbsp;Nuova Richiesta</button></td>
    </tr></tbody>
</table>
</div>
</div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>

<!-- Modal - Add/Update Record -->
<div class="modal fade" id="richiesta_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-deeporange4">
			<div class="panel-heading">
				<h5 class="modal-title" id="updateMyModalLabel">Richiesta Assemblea di Classe</h5>
			</div>
			<div class="panel-body">
			<form class="form-horizontal">

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="richiedente">Richiedente</label>
                    <div class="col-sm-5"><input type="text" id="richiedente" placeholder="richiedente" class="form-control" readonly /></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="classe">Classe</label>
                    <div class="col-sm-3"><input type="text" id="classe" placeholder="classe" class="form-control" readonly /></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="data_assemblea">Data</label>
					<div class="col-sm-4"><input type="text" value="21/8/2018" id="data_assemblea" placeholder="data" class="form-control" /></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="ora_assemblea">Ora</label>
					<div class="col-sm-4">
						<select id="ora_assemblea" name="ora_assemblea" class="ora_assemblea selectpicker" data-live-search="true" data-noneSelectedText="seleziona..." data-show-subtext="true" >
						<option value="1"  data-subtext="Prima ora (8:00 - 8:50)" >1</option>
						<option value="2"  data-subtext="Seconda ora (8:50 - 9:40)" >2</option>
						<option value="3"  data-subtext="Terza ora (9:40 - 10:30)" >3</option>
						<option value="4"  data-subtext="Quarta ora (10:40 - 11:30)" >4</option>
						<option value="5"  data-subtext="Quinta ora (11:30 - 12:20)" >5</option>
						<option value="6"  data-subtext="Sesta ora (12:20 - 13:10)" >6</option>
						<option value="7"  data-subtext="Settima ora (13:10 - 14:00)" >7</option>
						<option value="8"  data-subtext="Ottava ora (14:00 - 14:50)" >8</option>
						<option value="9"  data-subtext="Nona ora (14:50 - 15:40)" >9</option>
						<option value="10"  data-subtext="Decima ora (15:40 - 16:30)" >10</option>
						</select>
					</div>
                </div>

                <div class="form-group docente_coordinatore_selector">
                    <label class="col-sm-2 control-label" for="docente_coordinatore">Coordinatore</label>
					<div class="col-sm-8"><select id="docente_coordinatore" name="docente_coordinatore_selector" class="docente_coordinatore selectpicker" data-style="btn-success" data-live-search="true"
					data-noneSelectedText="seleziona..." data-width="70%" ><?php echo $docenteOptionList ?>
					</select></div>
                </div>

                <div class="form-group docente_ora_selector">
                    <label class="col-sm-2 control-label" for="docente_ora">Docente ora</label>
					<div class="col-sm-8"><select id="docente_ora" name="docente_ora_selector" class="docente_ora selectpicker" data-style="btn-success" data-live-search="true"
					data-noneSelectedText="seleziona..." data-width="70%" ><?php echo $docenteOptionList ?>
					</select></div>
                </div>
                <hr>
                <div class="form-group ordine_del_giorno">
                    <label class="" for="ordine_del_giorno">Ordine del giorno</label>
                    <div class="summernote" rows="5" id="ordine_del_giorno" placeholder="ordine del giorno" ></div>
                </div>

                <div class="form-group" id="_error-part"><strong>
                    <hr>
                    <div class="col-sm-3 text-right text-danger ">Attenzione</div>
                    <div class="col-sm-9" id="_error"></div>
                </strong></div>
			</form>
            </div>
			<div class="panel-footer text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				<button type="button" class="btn btn-primary" onclick="richiestaInoltra()" >Inoltra</button>
				<input type="hidden" id="hidden_richiedente" value="<?php echo $studente_nome_cognome; ?>">
				<input type="hidden" id="hidden_classe" value="<?php echo $classe; ?>">
			</div>
			</div>
			</div>
        </div>
    </div>
</div>
<!-- // Modal - Add New Record -->
<script type="text/javascript" src="js/richiesta.js?v=<?php echo $__software_version; ?>"></script>

</body>
</html>