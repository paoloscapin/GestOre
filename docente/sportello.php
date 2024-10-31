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
require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-select.php';
require_once '../common/_include_flatpickr.php';
require_once '../common/_include_bootstrap-notify.php';
ruoloRichiesto('docente');
?>
	<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
	<title>Sportelli</title>
<style>
/* Tooltip */
.tooltip > .tooltip-inner {
    background-color: #73AD21; 
    color: #FFFFFF; 
    border: 1px solid green; 
    padding: 15px;
    font-size: 20px;
}
.tooltip.top > .tooltip-arrow {
    border-top: 5px solid green;
}
.tooltip.bottom > .tooltip-arrow {
    border-bottom: 5px solid blue;
}
.tooltip.left > .tooltip-arrow {
    border-left: 5px solid red;
}
.tooltip.right > .tooltip-arrow {
    border-right: 5px solid black;
}
.tooltip-inner {
    max-width: 450px;
    /* If max-width does not work, try using width instead */
    width: 450px;
    text-align: left;
}
</style>
</head>

<?php

// prepara l'elenco delle materie per il filtro e per le materie del dialog
$materiaOptionList = '<option value="0"></option>';
foreach(dbGetAll("SELECT * FROM materia ORDER BY materia.nome ASC ; ")as $materia) {
    $materiaOptionList .= ' <option value="'.$materia['id'].'" >'.$materia['nome'].'</option> ';
}

$nclassi = dbGetValue (" SELECT COUNT(*) FROM classe WHERE classe.attiva=1");

$classeOptionList = "";

if ($nclassi>0)
{
    // prepara l'elenco delle materie per il filtro e per le materie del dialog
    $classeOptionList = '<option value="0"></option>';
    foreach(dbGetAll("SELECT * FROM classe ORDER BY classe.nome ASC ; ")as $classe) {
        $classeOptionList .= ' <option value="'.$classe['id'].'" >'.$classe['nome'].'</option> ';
    }
}
else
{
    $classeOptionList .= 'empty';
}

$modifica_sportelli = '<input type="hidden" id="hidden_modifica_sportelli" value=' . !$__settings->sportelli->docente_puo_modificare . '>';
?>

<body >
<?php
require_once '../common/header-docente.php';
require_once '../common/connect.php';

echo $modifica_sportelli;
?>

<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-orange4">
<div class="panel-heading">
	<div class="row">
		<div class="col-md-4">
			<span class="glyphicon glyphicon-object-align-horizontal"></span>&ensp;Sportelli
		</div>
		<div class="col-md-2 text-center">
            <label class="checkbox-inline">
                <input type="checkbox" checked data-toggle="toggle" data-size="mini" data-onstyle="primary" id="soloNuoviCheckBox" >Solo Nuovi
            </label>
		</div>
        <div class="col-md-2">
            <div class="text-center">
				<label class="checkbox-inline">
					<input type="checkbox"  data-toggle="toggle" data-size="mini" data-onstyle="primary" id="ancheCancellatiCheckBox" >Anche cancellati
				</label>
            </div>
        </div>
		<div class="col-md-4 text-right">
<?php if(getSettingsValue("sportelli", "inseriti_da_docente", false)) : ?>
            <div class="pull-right">
                <button class="btn btn-xs btn-orange4" onclick="sportelloGetDetails(-1)" ><span class="glyphicon glyphicon-plus"></span></button>
            </div>
<?php endif; ?>
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

<!-- Modal - Add/Update Record -->
<div class="modal fade" id="sportello_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-orange4">
			<div class="panel-heading">
				<h5 class="modal-title" id="myModalLabel">Sportello</h5>
			</div>
			<div class="panel-body">
			<form class="form-horizontal">

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="data">Data</label>
					<div class="col-sm-4"><input type="text" value="21/8/2018" id="data" class="form-control" /></div>

                    <label class="col-sm-2 control-label" for="ora">Ora</label>
                    <div class="col-sm-4"><input type="text" id="ora" class="form-control" /></div>
                </div>

                <div class="form-group docente_selector">
                    <label class="col-sm-2 control-label" for="docente">Docente</label>
                    <div class="col-sm-4"><input type="text" id="docente" class="form-control" readonly="readonly" /></div>
                </div>

                <div class="form-group materia_selector">
                    <label class="col-sm-2 control-label" for="materia">Materia</label>
					<div class="col-sm-8"><select id="materia" name="materia" class="materia selectpicker" data-style="btn-yellow4" data-live-search="true" data-noneSelectedText="seleziona..." data-width="70%" >
                    <?php echo $materiaOptionList ?>
					</select></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="numero_ore">Numero di ore</label>
                    <div class="col-sm-4"><input type="text" id="numero_ore" class="form-control" /></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="argomento">Argomento</label>
                    <div class="col-sm-8"><input type="text" id="argomento" class="form-control" placeholder="! non inserire se si desidera che siano gli studenti a specificarlo !" /></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="luogo">Luogo</label>
                    <div class="col-sm-8"><input type="text" id="luogo" class="form-control" placeholder="aula o laboratorio in cui si svolge lo sportello" /></div>
                </div>

                <?php

                if ($classeOptionList == "empty") // se la tabella classe è vuota allora metti una casella di testo
                {
                    echo '
                    <input type="hidden" id="hidden_lista_classi" value="testo">

                    <div class="form-group classe_selector">
                    <label class="col-sm-2 control-label" for="classe">Classe</label>
				    <div class="col-sm-8"><input type="text" id="classe" placeholder="classi a cui è rivolto lo sportello" class="form-control"/></div>
                	</select></div>
                </div>';

                }
                else // altrimenti crea e popola una combobox
                {
                    $txt_echo = '
                    <input type="hidden" id="hidden_lista_classi" value="lista">
                    <div class="form-group classe_selector">
                        <label class="col-sm-2 control-label" for="classe">Classe</label>
                        <div class="col-sm-8"><select id="classe" name="classe" class="classe selectpicker" data-style="btn-yellow4" data-live-search="true" data-noneSelectedText="seleziona..." data-width="70%" >';
                    $txt_echo .= $classeOptionList;
                    $txt_echo .= '</select></div>
                    </div>';
                    echo $txt_echo;
                }

                ?>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="max_iscrizioni">Max Iscrizioni</label>
                    <div class="col-sm-8"><input type="text" id="max_iscrizioni" placeholder="<?php echo getSettingsValue("sportelli", "numero_max_prenotazioni", 10); ?>" class="form-control"/></div>
                </div>

                <?php
                if ($__settings->sportelli->sezione_online_clil_orientamento_visibile)
                {
                echo '<div class="form-group">
                    <label for="online" class="col-sm-2 control-label">Online</label>
                    <div class="col-sm-1 "><input type="checkbox" id="online" ></div>
                    <label for="clil" class="col-sm-2 control-label">Clil</label>
                    <div class="col-sm-1 "><input type="checkbox" id="clil" ></div>
                    <label for="orientamento" class="col-sm-2 control-label">Orientamento</label>
                    <div class="col-sm-1 "><input type="checkbox" id="orientamento" ></div>
                    <input type="hidden" id="hidden_sezione_online_clil" value="true">
                </div>';
                }
                else
                {
                    echo '<input type="hidden" id="hidden_sezione_online_clil" value="false">';
                }
                ?>
                <div class="form-group">
                    <label for="cancellato" class="col-sm-2 control-label">Cancellato</label>
                    <div class="col-sm-1 "><input type="checkbox" onclick="confermaCancellato()" id="cancellato"></div>
                </div>

                <div class="form-group">
                    <label for="firmato" class="col-sm-2 control-label">Firmato</label>
                    <div class="col-sm-1 "><input type="checkbox" onclick="confermaFirmato()" id="firmato" ></div>
                    <!-- <div class="col-sm-1 text-center" id="firma_sportello_button_id">
                        <button type="button" class="btn btn-success" onclick="sportelloFirma()">Firma lo Sportello</button>
                    </div> -->
                </div>

                <div class="form-group text-center" id="studenti-part">
                    <hr>
                    <label for="studenti_table">Studenti</label>
					<div class="table-wrapper">
					<table class="table table-bordered table-striped" id="studenti_table">
						<thead>
						<tr>
							<th>id</th>
							<th>studenteId</th>
							<th class="text-center">Studente</th>
							<th class="text-center">Argomento</th>
							<th class="text-center">Presente</th>
						</tr>
						</thead>
						<tbody>
						</tbody>
					</table>
                </div>

                <div class="form-group" id="_error-materia-part"><strong>
                    <hr>
                    <div class="col-sm-3 text-right text-danger ">Attenzione</div>
                    <div class="col-sm-9" id="_error-materia"></div>
				</strong></div>

                <input type="hidden" id="hidden_sportello_id">
                <input type="hidden" id="hidden_categoria">
                <input type="hidden" id="hidden_numero_studenti_iscritti">
                <input type="hidden" id="hidden_max_iscrizioni_default" value="<?php echo getSettingsValue("sportelli", "numero_max_prenotazioni", 10); ?>">
                <input type="hidden" id="hidden_docente_cognome_nome" value="<?php echo "$__docente_cognome $__docente_nome"; ?>">
			</form>

            </div>
            <div class="modal-footer">
			<div class="col-sm-12 text-center">
                <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" onclick="sportelloSave()">Salva</button>
            </div>
            </div>
			</div>
			</div>
        </div>
    </div>
</div>
<!-- // Modal - Add/Update Record -->

</div>

<!-- Custom JS file -->
<script type="text/javascript" src="js/sportello.js?v=<?php echo $__software_version; ?>"></script>
</body>
</html>