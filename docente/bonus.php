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
	<title>Bonus Docente</title>
</head>

<body >
<?php
require_once '../common/header-docente.php';
require_once '../common/connect.php';
?>

<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-lima4">
<div class="panel-heading">
	<div class="row">
		<div class="col-md-4">
			<span class="glyphicon glyphicon-list-alt"></span>&ensp;Bonus
		</div>
		<div class="col-md-4 text-center">
		</div>
		<div class="col-md-4 text-right">
            <?php
            if ($__config->getBonus_adesione_aperto()) {
                echo '
				<button onclick="document.location.href=\'bonusSelection.php\'" class="btn btn-xs btn-lima4"><span class="glyphicon glyphicon-cog"></span>&ensp;Adesioni</button>
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
			<div class="table-wrapper">
				<table class="table table-bordered table-striped table-green" id="bonus_docente_table">
					<thead>
						<tr>
						<th class="text-center">Codice</th>
						<th class="text-center">Descrittore</th>
						<th class="text-center">Evidenze</th>
						<th class="text-center">Valore</th>
						<th class="text-center"></th>
						<th class="text-center">Approvato</th>
						</tr>
					</thead>
					<tbody>
<?php

$query = "
SELECT
	bonus_docente.id AS bonus_docente_id,
	bonus_docente.approvato AS bonus_docente_approvato,

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
	bonus_docente.docente_id = $__docente_id
AND
	bonus_docente.anno_scolastico_id = $__anno_scolastico_corrente_id

ORDER BY
	bonus.codice;
";
$resultArray = dbGetAll($query);
foreach($resultArray as $bonus) {
    $data = '
            <tr>
                <td class="text-left">'.$bonus['bonus_codice'].'</td>
                <td class="text-left">'.$bonus['bonus_descrittori'].'</td>
                <td class="text-left">'.$bonus['bonus_evidenze'].'</td>
                <td class="text-left">'.$bonus['bonus_valore_previsto'].'</td>
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
	$data .='
            <td class="text-left">'.$bonus['bonus_docente_approvato'].'</td>
        </tr>
	';
	echo $data;

}
?>
					</tbody>
				</table>
	        </div>
        </div>
    </div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>

<!-- Modal - rendiconto details -->
<div class="modal fade" id="bonus_docente_rendiconto_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-success">
			<div class="panel-heading">
			<h5 class="modal-title text-center" id="myModalLabel">Rendiconto Evidenze</h5>
			</div>
			<div class="panel-body">
                <div class="form-group">
                    <div class="" id="evidenze_text"></div>
                </div>

                <div class="form-group">
                    <label for="rendiconto_rendiconto">Rendiconto</label>
                    <textarea class="form-control" rows="5" id="rendiconto_rendiconto" placeholder="rendiconto" ></textarea>
                </div>
            </div>
			<div class="modal-footer">
			<div class="col-sm-12 text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				
<?php
if ($__config->getBonus_rendiconto_aperto()) {
    echo '
                <button type="button" class="btn btn-primary" onclick="bonusDocenteRendicontoUpdateDetails()" >Salva</button>
    ';
}
?>
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

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">

<!-- Custom JS file -->
<script type="text/javascript" src="js/scriptBonus.js"></script>

</body>
</html>
