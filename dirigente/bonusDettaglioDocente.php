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
//require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-select.php';
require_once '../common/_include_bootstrap-notify.php';
ruoloRichiesto('dirigente');
require_once '../common/connect.php';
if(isset($_GET)) {
    // get values
    $docente_id = $_GET['id'];
    $query = "SELECT * FROM docente WHERE docente.id = $docente_id; ";
    $docente = dbGetFirst($query);
    $docenteCognomeNome = $docente['cognome'].' '.$docente['nome'];
}
?>
	<title><?php echo $docenteCognomeNome; ?></title>

<!-- timejs -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/timejs/date-it-IT.js"></script>

<!-- bootbox notificator -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-vcolor-index.css">
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">

<script type="text/javascript" src="js/scriptBonusDettaglio.js"></script>

</head>

<body >
<?php
require_once '../common/header-dirigente.php';
?>

<div class="container-fluid" style="margin-top:60px">

<!-- prima il pannello del bonus assegnato -->
<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-lima4">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-6">
			<span class="glyphicon glyphicon-education"></span>&emsp;Bonus Assegnato
		</div>
        <div class="col-md-6">
            <div class="pull-right">
                <button class="btn btn-xs btn-lima4" onclick="bonusAssegnatoGetDetails(-1)" ><span class="glyphicon glyphicon-plus"></span></button>
            </div>
        </div>
	</div>
</div>
<div class="panel-body">
    <div class="row">
        <div class="col-md-12">
            <div class="records_content"></div>
        </div>
    </div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>

<!-- Bootstrap Modals -->
<!-- Modal - Add/Update Record -->
<div class="modal fade" id="update_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h5 class="modal-title" id="myModalLabel">Bonus Assegnato</h5>
            </div>
            <div class="modal-body">

                <div class="form-group">
                    <label for="commento">Commento</label>
                    <input type="text" id="commento" placeholder="commento" class="form-control"/>
                </div>

                <div class="form-group">
                    <label for="importo">Importo</label>
                    <input type="text" id="importo" placeholder="importo" class="form-control"/>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" onclick="bonusAssegnatoSave()">Salva</button>
				<input type="hidden" id="hidden_record_id">
				<input type="hidden" id="hidden_docente_id" value="<?php echo $docente_id; ?>">
            </div>
        </div>
    </div>
</div>
<!-- // Modal - Add/Update Record -->

<?php

$docenteCognomeNome = $docente['cognome'].' '.$docente['nome'];
$data = '';

// disegna il pannello del bonus
$data .= '
    <div class="panel panel-lima4">
        <div class="panel-heading">
            <div class="row">
                <div class="col-md-4">
                    <span class="glyphicon glyphicon-list-alt"></span>
                    <a data-toggle="collapse" href="#collapse_bonus">&ensp;Bonus</a>
                </div>
                <div class="col-md-4 text-center">
                    '.$docenteCognomeNome.'
                </div>
                <div class="col-md-4 text-right">
                </div>
            </div>
        </div>
        <div id="collapse_bonus" class="panel-collapse collapse  collapse in">
            <div class="panel-body">
                <div class="row"  style="margin-bottom:10px;">
                    <div class="col-md-6">
                    </div>
                    <div class="col-md-6">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="table-wrapper">
                            <table class="table table-bordered table-striped table-green" id="table-docente-bonus">
                                <thead>
                                    <tr>
                                    <th class="text-center">Codice</th>
                                    <th class="text-center">Descrittore</th>
                                    <th class="text-center">Valore</th>
                                    <th class="text-center"></th>
                                    <th class="text-center">Approvato</th>
                                    </tr>
                                </thead>
                                <tbody>				    
    ';

    // disegna la tabella del bonus     
$query = "
SELECT
    bonus_docente.id AS bonus_docente_id,
    bonus_docente.approvato AS bonus_docente_approvato,
    bonus_docente.ultimo_controllo AS bonus_docente_ultimo_controllo,
    bonus_docente.ultima_modifica AS bonus_docente_ultima_modifica,
    
    bonus_area.codice AS bonus_area_codice,
    bonus_area.descrizione AS bonus_area_descrizione,
    bonus_area.valore_massimo AS bonus_area_valore_massimo,
    bonus_area.peso_percentuale AS bonus_area_peso_percentuale,
    
    bonus_indicatore.codice AS bonus_indicatore_codice,
    bonus_indicatore.descrizione AS bonus_indicatore_descrizione,
    bonus_indicatore.valore_massimo AS bonus_indicatore_valore_massimo,
    
    bonus.codice AS bonus_codice,
    bonus.descrittori AS bonus_descrittori,
    bonus.evidenze AS bonus_evidenze,
    bonus.valore_previsto AS bonus_valore_previsto
    
FROM bonus_docente

INNER JOIN bonus
ON bonus_docente.bonus_id = bonus.id

INNER JOIN bonus_indicatore
ON bonus.bonus_indicatore_id = bonus_indicatore.id

INNER JOIN bonus_area
ON bonus_indicatore.bonus_area_id = bonus_area.id

WHERE
    bonus_docente.docente_id = ".$docente['id']."
AND
    bonus_docente.anno_scolastico_id = $__anno_scolastico_corrente_id
    
ORDER BY
    bonus.codice;
";
$resultArray2 = dbGetAll($query);
foreach($resultArray2 as $bonus) {
    $marker = ($bonus['bonus_docente_ultima_modifica'] > $bonus['bonus_docente_ultimo_controllo']) ? '&ensp;<span class="label label-danger glyphicon glyphicon-star" style="color:yellow">.'. '' .'</span>': '';
    $data .= '
        <tr>
            <td class="text-left">'.$bonus['bonus_docente_id'].' </td>
            <td class="text-left">'.$bonus['bonus_codice'].' '.$marker.'</td>
            <td class="text-left">'.$bonus['bonus_descrittori'].'</td>
            <td class="text-center">'.$bonus['bonus_valore_previsto'].'</td>
        ';
        
        $data .='
            <td class="text-center">
        ';
        $data .='
            <button onclick="bonusRendiconto('.$bonus['bonus_docente_id'].', \''.$bonus['bonus_codice'].'\', \''.$bonus['bonus_descrittori'].'\', \''.$bonus['bonus_evidenze'].'\')" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-list-alt"></button>
        ';
        $data .='
            </td>
        ';
        if (getSettingsValue('bonus','punteggio_variabile', false)) {
            

            $maxValue = $bonus['bonus_valore_previsto'];

            $punteggioBonusOptionList = '<select class="punteggioBonus selectpicker" data-noneSelectedText="seleziona..." data-width="50%" ><option value="0"></option>';


            for($i = 0; $i <= $maxValue; $i++) {
				$punteggioBonusOptionList .= '<option value="'.$i.'" data-content="<span class=\'label label-info\'\'>'.$i.'</span>"';
				if ($bonus['bonus_docente_approvato'] !== NULL && $bonus['bonus_docente_approvato'] == $i) {
					$punteggioBonusOptionList .= ' selected ';
				}
				$punteggioBonusOptionList .= '>'.$i.'</option>';
			}
			$punteggioBonusOptionList .= '</select>';

			$data .= '<td>'.$punteggioBonusOptionList.'</td>';


        } else {
            $data .= '<td class="text-center"><input type="checkbox" data-toggle="toggle" data-onstyle="primary" id="approvato'.$bonus['bonus_docente_id'].'" ';
            if ($bonus['bonus_docente_approvato']) {
                $data .= 'checked ';
            }
            $data .= '></td>';
        }

        $data .='</tr>';
            
}

// chiude il pannello del bonus
$data .= '
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
            
<div class="panel-footer">
    <div class="panel-footer text-center">
    <div class="row">
        <div class="col-md-4 text-center">
            <button onclick="bonusChiudi()" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-off"> Chiudi</button>
        </div>
        <div class="col-md-4 text-center">
            <button onclick="bonusRivisto()" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-ok"> Rivisto</button>
        </div>
        <div class="col-md-4 text-center">
        </div>
    </div>
    </div>


</div>
</div>
</div>
    ';

echo $data;
?>

<input type="hidden" id="hidden_docente_id" value="<?php echo $docente_id; ?>">
<input type="hidden" id="hidden_docente_nome" value="<?php echo $docenteCognomeNome; ?>">

<!-- Modal - rendiconto details -->
<div class="modal fade" id="bonus_docente_rendiconto_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-success">
			<div class="panel-heading">
				<h5 class="modal-title" id="myModalLabel">Rendiconto Evidenze</h5>
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
