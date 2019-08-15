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
//require_once '../common/_include_bootstrap-select.php';
require_once '../common/_include_bootstrap-notify.php';
ruoloRichiesto('dirigente');
require_once '../common/connect.php';
if(isset($_GET)) {
    // get values
    $docente_id = $_GET['id'];
    $query = "SELECT * FROM docente WHERE docente.id = $docente_id; ";
    debug($query);
    $docente = dbGetFirst($query);
    $docenteCognomeNome = $docente['cognome'].' '.$docente['nome'];
    // ultimo controllo fuis
    $ultimo_controllo = dbGetValue("SELECT ultimo_controllo FROM fuis_docente WHERE docente_id = $docente_id AND anno_scolastico_id = $__anno_scolastico_corrente_id;");
    debug('ultimo_controllo=' . $ultimo_controllo);
}
?>
	<title><?php echo $docenteCognomeNome; ?></title>

<!-- timejs -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/timejs/date-it-IT.js"></script>

<!-- bootbox notificator -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-vcolor-index.css">
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">

<script type="text/javascript" src="js/scriptQuadroOreDovute.js"></script>

</head>

<body >
<?php
require_once '../common/header-dirigente.php';
?>

<div class="container-fluid" style="margin-top:60px">
<?php

$warning = '<span class="glyphicon glyphicon-warning-sign text-error"></span>';
$okSymbol = '&ensp;<span class="glyphicon glyphicon-ok text-success"></span>';

function getHtmlNum($value) {
	return '&emsp;' . (($value >= 10) ? $value : '&ensp;' . $value);
}

function getHtmlNumAndPrevisteVisual($value, $total) {
	global $okSymbol;
	global $warning;

	$numString = ($value >= 10) ? $value : '&ensp;' . $value;
	$diff = $total - $value;
	if ($diff > 0) {
		$numString .= '&ensp;<span class="label label-warning">- '. $diff .'</span>';
	} else if ($diff < 0) {
		$numString .= '&ensp;<span class="label label-danger">+ '. (-$diff) .'</span>';
	} else {
		$numString .= $okSymbol;
	}
	return '&emsp;' . $numString;
}

function getHtmlNumAndFatteVisual($value, $total) {
    global $okSymbol;
    global $warning;
    
    $numString = ($value >= 10) ? $value : '&ensp;' . $value;
    $diff = $total - $value;
    if ($diff > 0) {
        $numString .= '&ensp;<span class="label label-warning">- '. $diff .'</span>';
    } else if ($diff < 0) {
        $numString .= '&ensp;<span class="label label-danger">+ '. (-$diff) .'</span>';
    } else {
        $numString .= $okSymbol;
    }
    return '&emsp;' . $numString;
}

$docenteCognomeNome = $docente['cognome'].' '.$docente['nome'];
$data = '';


// disegna il pannello del FUIS
$data .= '
    <div class="panel panel-danger">
        <div class="panel-heading">
        	<div class="row">
        		<div class="col-md-4">
                    <span class="glyphicon glyphicon-list-alt"></span>
                    <a data-toggle="collapse" href="#collapse_fuis">&ensp;FUIS</a>
        		</div>
        		<div class="col-md-4 text-center">
                	'.$docenteCognomeNome.'
        		</div>
        		<div class="col-md-4 text-center" id="fuis_totale_da_pagare_id"></div>
        	</div>
        </div>
        <div id="collapse_fuis" class="panel-collapse collapse  collapse in">
            <div class="panel-body">
    ';
$data .= '
	<div class="table-wrapper">
	<table class="table table-vnocolor-index">
		<thead>
			<tr>
				<th class="col-md-1 text-left"></th>
				<th class="col-md-1 text-left">Sostituzioni</th>
				<th class="col-md-1 text-left">Funzionali</th>
				<th class="col-md-1 text-left">con Studenti</th>
				<th class="col-md-1 text-left">CLIL Funzionali</th>
				<th class="col-md-1 text-left">CLIL con Studenti</th>
				<th class="col-md-1 text-left">FUIS Ore</th>
				<th class="col-md-1 text-left">FUIS CLIL</th>
				<th class="col-md-1 text-left">FUIS Assegnato</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td class="col-md-1">Ore</td>
				<td class="text-left" id="sostituzioni_ore"></td>
				<td class="text-left" id="funzionale_ore"></td>
				<td class="text-left" id="con_studenti_ore"></td>
				<td class="text-left" id="clil_funzionale_ore"></td>
				<td class="text-left" id="clil_con_studenti_ore"></td>
				<td class="text-left"></td>
				<td class="text-left"></td>
				<td class="text-center"></td>
			</tr>
			<tr>
				<td class="col-md-1">Importo Proposto</td>
				<td class="text-left" id="sostituzioni_proposto"></td>
				<td class="text-left" id="funzionale_proposto"></td>
				<td class="text-left" id="con_studenti_proposto"></td>
				<td class="text-left" id="clil_funzionale_proposto"></td>
				<td class="text-left" id="clil_con_studenti_proposto"></td>
				<td class="text-left" id="totale_proposto"></td>
				<td class="text-left" id="clil_totale_proposto"></td>
				<td class="text-left" id="assegnato_proposto"></td>
			</tr>
			<tr>
				<td class="col-md-1">Importo Approvato</td>
				<td class="text-left" id="sostituzioni_approvato"></td>
				<td class="text-left" id="funzionale_approvato"></td>
				<td class="text-left" id="con_studenti_approvato"></td>
				<td class="text-left" id="clil_funzionale_approvato"></td>
				<td class="text-left" id="clil_con_studenti_approvato"></td>
				<td class="text-left" id="totale_approvato"></td>
				<td class="text-left" id="clil_totale_approvato"></td>
				<td class="text-left" id="assegnato_approvato"></td>
			</tr>
		</tbody>
	</table>
	</div>
    
';

// chiude il pannello del FUIS
$data .= '
                </div>
            </div>
            <div class="panel-footer text-center">
        	<div class="row">
        		<div class="col-md-4 text-center">
                    <button onclick="fuisChiudi()" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-off"> Chiudi</button>
        		</div>
        		<div class="col-md-4 text-center">
                    <button onclick="fuisRivisto()" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-ok"> Rivisto</button>
        		</div>
           		<div class="col-md-4 text-center">
                    <button onclick="fuisEmail()" class="btn btn-info btn-xs"><span class="glyphicon glyphicon-envelope"> Notifica Docente</button>
        		</div>
            </div>
            </div>
        </div>
				    
    ';

// disegna il pannello delle ORE
$data .= '
    <div class="panel panel-warning">
        <div class="panel-heading">
        	<div class="row">
        		<div class="col-md-4">
                    <span class="glyphicon glyphicon-list-alt"></span>
                    <a data-toggle="collapse" href="#collapse_ore">&ensp;Quadro Orario</a>
        		</div>
        		<div class="col-md-4 text-center">
                	'.$docenteCognomeNome.'
        		</div>
        		<div class="col-md-4 text-right">
         		</div>
        	</div>
        </div>
        <div id="collapse_ore" class="panel-collapse collapse  collapse in">
            <div class="panel-body">
    ';

// disegna la tabella delle ore
$query = "
SELECT
	docente.*,
	
	ore_dovute.ore_40_sostituzioni_di_ufficio AS ore_dovute_ore_40_sostituzioni_di_ufficio,
	ore_dovute.	ore_40_con_studenti AS ore_dovute_ore_40_con_studenti,
	ore_dovute.ore_40_aggiornamento AS ore_dovute_ore_40_aggiornamento,
	ore_dovute.	ore_70_funzionali AS ore_dovute_ore_70_funzionali,
	ore_dovute.ore_70_con_studenti AS ore_dovute_ore_70_con_studenti,
	
	ore_previste.ore_40_sostituzioni_di_ufficio AS ore_previste_ore_40_sostituzioni_di_ufficio,
	ore_previste.	ore_40_con_studenti AS ore_previste_ore_40_con_studenti,
	ore_previste.ore_40_aggiornamento AS ore_previste_ore_40_aggiornamento,
	ore_previste.	ore_70_funzionali AS ore_previste_ore_70_funzionali,
	ore_previste.ore_70_con_studenti AS ore_previste_ore_70_con_studenti,
	
	ore_fatte.ore_40_sostituzioni_di_ufficio AS ore_fatte_ore_40_sostituzioni_di_ufficio,
	ore_fatte.	ore_40_con_studenti AS ore_fatte_ore_40_con_studenti,
	ore_fatte.ore_40_aggiornamento AS ore_fatte_ore_40_aggiornamento,
	ore_fatte.	ore_70_funzionali AS ore_fatte_ore_70_funzionali,
	ore_fatte.ore_70_con_studenti AS ore_fatte_ore_70_con_studenti
	
	
FROM docente

INNER JOIN ore_dovute
ON ore_dovute.docente_id = docente.id

INNER JOIN ore_previste
ON ore_previste.docente_id = docente.id

INNER JOIN ore_fatte
ON ore_fatte.docente_id = docente.id

WHERE
	docente.id = ".$docente['id']."
AND
	ore_dovute.anno_scolastico_id = $__anno_scolastico_corrente_id
AND
	ore_previste.anno_scolastico_id = $__anno_scolastico_corrente_id
AND
	ore_fatte.anno_scolastico_id = $__anno_scolastico_corrente_id
";
debug($query);
$ore = dbGetFirst($query);
// aggiornamento non genera extra
if ($ore['ore_fatte_ore_40_aggiornamento'] >  $ore['ore_dovute_ore_40_aggiornamento']) {
    $ore['ore_fatte_ore_40_aggiornamento'] =  $ore['ore_dovute_ore_40_aggiornamento'];
}
$data .= '
	<div class="table-wrapper">
	<table class="table table-vnocolor-index">
		<thead>
			<tr>
				<th class="col-md-2 text-left"></th>
				<th class="col-md-1 text-left">40 Sostituzioni</th>
				<th class="col-md-1 text-left">40 con Studenti</th>
				<th class="col-md-1 text-left">40 Aggiornamento</th>
				<th class="col-md-1 text-left">70 Funzionali</th>
				<th class="col-md-1 text-left">70 con Studenti</th>
				<th class="col-md-1 text-left"></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td class="col-md-2">dovute</td>
				<td class="text-left">'.getHtmlNum($ore['ore_dovute_ore_40_sostituzioni_di_ufficio']).'</td>
				<td class="text-left">'.getHtmlNum($ore['ore_dovute_ore_40_con_studenti']).'</td>
				<td class="text-left">'.getHtmlNum($ore['ore_dovute_ore_40_aggiornamento']).'</td>
				<td class="text-left">'.getHtmlNum($ore['ore_dovute_ore_70_funzionali']).'</td>
				<td class="text-left">'.getHtmlNum($ore['ore_dovute_ore_70_con_studenti']).'</td>
				<td class="text-center"></td>
			</tr>
			<tr>
				<td class="col-md-2">previste</td>
				<td class="text-left">'.getHtmlNumAndPrevisteVisual($ore['ore_previste_ore_40_sostituzioni_di_ufficio'],$ore['ore_dovute_ore_40_sostituzioni_di_ufficio']).'</td>
				<td class="text-left">'.getHtmlNumAndPrevisteVisual($ore['ore_previste_ore_40_con_studenti'],$ore['ore_dovute_ore_40_con_studenti']).'</td>
				<td class="text-left">'.getHtmlNumAndPrevisteVisual($ore['ore_previste_ore_40_aggiornamento'],$ore['ore_dovute_ore_40_aggiornamento']).'</td>
				<td class="text-left">'.getHtmlNumAndPrevisteVisual($ore['ore_previste_ore_70_funzionali'],$ore['ore_dovute_ore_70_funzionali']).'</td>
				<td class="text-left">'.getHtmlNumAndPrevisteVisual($ore['ore_previste_ore_70_con_studenti'],$ore['ore_dovute_ore_70_con_studenti']).'</td>
				<td class="text-center"><button onclick="viewAttivitaPreviste(\''.$ore['id'].'\',\''.$docenteCognomeNome.'\')" class="btn btn-info btn-xs" ><span class="glyphicon glyphicon-indent-left"></button></td>
			</tr>
			<tr>
				<td class="col-md-2">fatte</td>
				<td class="text-left" id="fatte_ore_40_sostituzioni_di_ufficio">'.getHtmlNumAndFatteVisual($ore['ore_fatte_ore_40_sostituzioni_di_ufficio'],$ore['ore_dovute_ore_40_sostituzioni_di_ufficio']).'</td>
				<td class="text-left" id="fatte_ore_40_con_studenti">'.getHtmlNumAndFatteVisual($ore['ore_fatte_ore_40_con_studenti'],$ore['ore_dovute_ore_40_con_studenti']).'</td>
				<td class="text-left" id="fatte_ore_40_aggiornamento">'.getHtmlNumAndFatteVisual($ore['ore_fatte_ore_40_aggiornamento'],$ore['ore_dovute_ore_40_aggiornamento']).'</td>
				<td class="text-left" id="fatte_ore_70_funzionali">'.getHtmlNumAndFatteVisual($ore['ore_fatte_ore_70_funzionali'],$ore['ore_dovute_ore_70_funzionali']).'</td>
				<td class="text-left" id="fatte_ore_70_con_studenti">'.getHtmlNumAndFatteVisual($ore['ore_fatte_ore_70_con_studenti'],$ore['ore_dovute_ore_70_con_studenti']).'</td>
				<td class="text-center"></td>
			</tr>
		</tbody>
	</table>
	</div>
';

// chiude il pannello delle ORE
$data .= '
                </div>
            </div>
            <!-- <div class="panel-footer"></div> -->
        </div>
				    
    ';

echo $data;
?>

<input type="hidden" id="hidden_docente_id" value="<?php echo $docente_id; ?>">
<input type="hidden" id="hidden_docente_nome" value="<?php echo $docenteCognomeNome; ?>">
<input type="hidden" id="hidden_ultimo_controllo" value="<?php echo $ultimo_controllo; ?>">

    <div class="panel panel-success">
        <div class="panel-heading">
        	<div class="row">
        		<div class="col-md-4">
                    <span class="glyphicon glyphicon-list-alt"></span>
                    <a data-toggle="collapse" href="#collapse_attivita">&ensp;Attività</a>
        		</div>
        		<div class="col-md-4 text-center"><?php echo $docenteCognomeNome; ?></div>
        		<div class="col-md-4 text-right">
         		</div>
        	</div>
        </div>
        <div id="collapse_attivita" class="panel-collapse collapse  collapse in">
            <div class="panel-body">
			    <div class="row">
			        <div class="col-md-12 text-center"><h4>Sommario</h4></div>
			    </div>
			    <div class="row">
			        <div class="col-md-12">
			            <div class="sommario_attivita_records_content"></div>
			        </div>
			    </div>
			    <div class="row">
			        <div class="col-md-12 text-center"><h4>Dettaglio</h4></div>
			    </div>
			    <div class="row">
			        <div class="col-md-12">
			            <div class="attivita_fatte_records_content"></div>
			        </div>
			    </div>

			    <div class="row">
			        <div class="col-md-12 text-center"><h4>Sommario Clil</h4></div>
			    </div>
			    <div class="row">
			        <div class="col-md-12">
			            <div class="sommario_attivita_clil_records_content"></div>
			        </div>
			    </div>
			    <div class="row">
			        <div class="col-md-12 text-center"><h4>Dettaglio Clil</h4></div>
			    </div>
			    <div class="row">
			        <div class="col-md-12">
			            <div class="attivita_fatte_clil_records_content"></div>
			        </div>
			    </div>

			    <div class="row">
			        <div class="col-md-12 text-center"><h4>Attribuite</h4></div>
			    </div>
			    <div class="row">
			        <div class="col-md-12">
			            <div class="attribuite_records_content"></div>
			        </div>
			    </div>

			    <div class="row">
			        <div class="col-md-12 text-center"><h4>Viaggi</h4></div>
			    </div>
			    <div class="row">
			        <div class="col-md-12">
			            <div class="viaggi_records_content"></div>
			        </div>
			    </div>

            </div>
        </div>
        <!-- <div class="panel-footer"></div> -->
    </div>
                  


<!-- Modal - previste -->
<div class="modal fade" id="previste_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalPrevisteLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-info">
			<div class="panel-heading">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalPrevisteTitleLabel">Attività Previste</h4>
            </div>
            <div class="panel-body">
			    <div class="row">
			        <div class="col-md-12 text-center"><h4>Sommario</h4></div>
			    </div>
			    <div class="row">
			        <div class="col-md-12">
			            <div class="sommario_attivita_previste_records_content"></div>
			        </div>
			    </div>
			    <div class="row">
			        <div class="col-md-12 text-center"><h4>Dettaglio</h4></div>
			    </div>
			    <div class="row">
			        <div class="col-md-12">
			            <div class="attivita_previste_records_content"></div>
			        </div>
			    </div>
            </div>
			<div class="panel-footer text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
			</div>
			</div>
			</div>
        </div>
    </div>
</div>
<!-- // Modal - previste -->

<!-- Modal - fatte -->
<div class="modal fade" id="fatte_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalFatteLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-success">
			<div class="panel-heading">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalFatteTitleLabel">Attività Fatte</h4>
            </div>
            <div class="panel-body">
			    <div class="row">
			        <div class="col-md-12 text-center"><h4>Sommario</h4></div>
			    </div>
			    <div class="row">
			        <div class="col-md-12">
			            <div class="sommario_attivita_records_content"></div>
			        </div>
			    </div>
			    <div class="row">
			        <div class="col-md-12 text-center"><h4>Dettaglio</h4></div>
			    </div>
			    <div class="row">
			        <div class="col-md-12">
			            <div class="attivita_fatte_records_content"></div>
			        </div>
			    </div>

			    <div class="row">
			        <div class="col-md-12 text-center"><h4>Sommario Clil</h4></div>
			    </div>
			    <div class="row">
			        <div class="col-md-12">
			            <div class="sommario_attivita_clil_records_content"></div>
			        </div>
			    </div>
			    <div class="row">
			        <div class="col-md-12 text-center"><h4>Dettaglio Clil</h4></div>
			    </div>
			    <div class="row">
			        <div class="col-md-12">
			            <div class="attivita_fatte_clil_records_content"></div>
			        </div>
			    </div>

			    <div class="row">
			        <div class="col-md-12 text-center"><h4>Attribuite</h4></div>
			    </div>
			    <div class="row">
			        <div class="col-md-12">
			            <div class="attribuite_records_content"></div>
			        </div>
			    </div>

			    <div class="row">
			        <div class="col-md-12 text-center"><h4>Viaggi</h4></div>
			    </div>
			    <div class="row">
			        <div class="col-md-12">
			            <div class="viaggi_records_content"></div>
			        </div>
			    </div>
            </div>
			<div class="panel-footer text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
			</div>
			</div>
			</div>
        </div>
    </div>
</div>
<!-- // Modal - fatte -->

<!-- Modal - registro details -->
<div class="modal fade" id="docente_registro_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-success">
			<div class="panel-heading">
				<h4 class="modal-title" id="myModalLabel">Registro Attività</h4>
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
                    <div class="col-sm-9" id="registro_descrizione"></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="registro_descrizione">Studenti</label>
                    <div class="col-sm-9" id="registro_studenti"></div>
                </div>
            </div>
            </div>
			<div class="modal-footer">
			<div class="col-sm-12 text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				<button type="button" class="btn btn-primary" onclick="attivitaFattaRegistroUpdateDetails()" >Salva</button>
				<input type="hidden" id="hidden_ore_fatte_registro_id">
			</div>
			</div>
        	</div>
        	</div>
    	</div>
    </div>
</div>
<!-- // Modal - registro details -->

<!-- Modal - rendiconto details -->
<div class="modal fade" id="bonus_docente_rendiconto_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-success">
			<div class="panel-heading">
				<h4 class="modal-title" id="myModalLabel">Rendiconto Evidenze</h4>
			</div>
			<div class="panel-body">
                <div class="form-group">
                    <div class="" id="evidenze_text"></div>
                </div>

                <div class="form-group">
                    <label for="rendiconto_rendiconto">Rendiconto</label>
                    <textarea class="form-control" rows="5" id="rendiconto_rendiconto" placeholder="rendiconto" readonly="readonly"></textarea>
                </div>
            </div>
			<div class="modal-footer">
			<div class="col-sm-12 text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Ok</button>
				<input type="hidden" id="hidden_bonus_docente_id">
			</div>
			</div>
        	</div>
        	</div>
    	</div>
    </div>
</div>
<!-- // Modal - rendiconto details -->

</div>

</body>
</html>

