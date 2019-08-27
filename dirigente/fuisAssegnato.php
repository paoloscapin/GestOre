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
	<title>Fuis e Bonus</title>
<?php
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
//require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-select.php';
ruoloRichiesto('dirigente');
?>

<!-- timejs -->
<script type="text/javascript" src="<?php echo $__application_base_path; ?>/common/timejs/date-it-IT.js"></script>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
<script type="text/javascript" src="js/scriptFuisAssegnato.js"></script>

</head>

<body >
<?php
require_once '../common/header-dirigente.php';
require_once '../common/connect.php';
?>

<div class="container-fluid" style="margin-top:60px">

<!-- pannello Fuis Assegnato (un pannello per ciascun tipo) -->
<?php
// prepara l'elenco dei tipi
$query = "	SELECT * FROM `fuis_assegnato_tipo`;
			";
$resultArrayFuisAssegnatoTipo = dbGetAll($query);

$data = '';
foreach($resultArrayFuisAssegnatoTipo as $fuisAssegnatoTipo) {
    $fuisAssegnatoTipoId = $fuisAssegnatoTipo['id'];
    $nome = $fuisAssegnatoTipo['nome'];
	$data .= '
		<div class="panel panel-warning">
		<div class="panel-heading container-fluid">
			<div class="row">
				<div class="col-md-4">
                    <span class="glyphicon glyphicon-list-alt"></span>&ensp;<strong>'.$nome.'</strong>
				</div>
				<div class="col-md-4 text-center" id="totale_'.$fuisAssegnatoTipoId.'">
				</div>
				<div class="col-md-4 text-right">
					<button onclick="editFuisAssegnato(-1,'.$fuisAssegnatoTipoId.',\''.$nome.'\',0,0)" class="btn btn-xs btn-info"><span class="glyphicon glyphicon-plus"></span></button>
				</div>
			</div>
		</div>
		<div class="panel-body">
		';
	$data .= '
		<div class="table-wrapper">
			<table class="table table-bordered table-striped" id="table_'.$fuisAssegnatoTipoId.'" >
				<thead>
					<th style="display:none;">id</th>
					<th>Docente</th>
					<th class="text-center">Importo</th>
					<th></th>
				</thead>
				<tbody>
			';
	
	$query = "	SELECT
					fuis_assegnato.id AS fuis_assegnato_id,
					fuis_assegnato.importo AS fuis_assegnato_importo,
					fuis_assegnato.fuis_assegnato_tipo_id AS fuis_assegnato_fuis_assegnato_tipo_id,
					docente.id AS docente_id,
					docente.nome AS docente_nome,
					docente.cognome AS docente_cognome
				FROM
					fuis_assegnato
				INNER JOIN docente docente
				ON fuis_assegnato.docente_id = docente.id
				WHERE
					fuis_assegnato.anno_scolastico_id = '$__anno_scolastico_corrente_id'
				AND
					fuis_assegnato.fuis_assegnato_tipo_id = '$fuisAssegnatoTipoId'
				ORDER BY
					docente.cognome ASC,
					docente.nome ASC
				;
		";
	debug($query);
	
	$resultArrayFuisAssegnato = dbGetAll($query);
	$classname = "";
	foreach($resultArrayFuisAssegnato as $fuis) {
	    $classname = ($classname==="even_row") ? "odd_row" : "even_row";
	    $data .= '
							<tr class="'.$classname.'">
								<td style="display:none;">'.$fuis['fuis_assegnato_id'].'</td>
								<td>'.$fuis['docente_cognome'].' '.$fuis['docente_nome'].'</td>
								<td class="col-md-2 text-right">'.$fuis['fuis_assegnato_importo'].'</td>
								<td class="col-md-2 text-center">
									<div onclick="editFuisAssegnato('.$fuis['fuis_assegnato_id'].','.$fuis['fuis_assegnato_fuis_assegnato_tipo_id'].',\''.$nome.'\','.$fuis['fuis_assegnato_importo'].','.$fuis['docente_id'].')" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-pencil"></div>&nbsp
									<div onclick="deleteFuisAssegnato('.$fuis['fuis_assegnato_id'].','.$fuis['fuis_assegnato_fuis_assegnato_tipo_id'].')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></div>&nbsp
								</td>
							</tr>
					';
	}
	$data .= '
				</tbody>
				';
	$data .= '
			</table>
			<div style="page-break-after: always;">
		</div>
	</div>
';
	$data .= '
</div>
</div>
';
}
echo $data;

?>


<?php
// prepara l'elenco dei docenti
$docenteOptionList = '				<option value="0"></option>';
$query = "	SELECT * FROM docente
			WHERE docente.attivo = true
			ORDER BY docente.cognome, docente.nome ASC
			;";
$resultArray = dbGetAll($query);
foreach($resultArray as $row) {
	$docenteOptionList .= '
		<option value="'.$row['id'].'" >'.$row['cognome'].' '.$row['nome'].'</option>
	';
}
?>

<!-- Bootstrap Modals -->
<!-- Modal - Add New Record -->
<div class="modal fade" id="add_new_record_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-warning">
			<div class="panel-heading">
				<h5 class="modal-title" id="myModalLabel">Nuovo Assegnamento</h5>
			</div>
			<div class="panel-body">
			<form class="form-horizontal">

                <div class="form-group docente_incaricato_selector">
                    <label class="col-sm-2 control-label" for="docente_incaricato">Docente</label>
					<div class="col-sm-8"><select id="docente_incaricato" name="docente_incaricato" class="docente_incaricato selectpicker" data-style="btn-success" data-live-search="true"
					data-noneSelectedText="seleziona..." data-width="70%" >
<?php echo $docenteOptionList ?>
					</select></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="importo" id="importoLabel" >Importo</label>
                    <div class="col-sm-8"><input type="text" id="importo" placeholder="importo" class="form-control"/></div>
                </div>
			</form>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" onclick="fuisAssegnatoSaveRecord()">Salva</button>
				<input type="hidden" id="hidden_fuis_assegnato_tipo_id">
				<input type="hidden" id="hidden_fuis_assegnato_id">
            </div>
			</div>
			</div>
        </div>
    </div>
</div>
<!-- // Modal - Add New Record -->

</div>
</body>
</html>
