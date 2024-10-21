<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

?>

<!DOCTYPE html>
<html lang="it">
<head>
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.3.1/css/all.css" integrity="sha384-mzrmE5qonljUremFsqc01SB46JvROS7bZs3IO2EmfFsd15uHvIt+Y8vEf7N7fWAU" crossorigin="anonymous">

<?php
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-select.php';
require_once '../common/_include_bootstrap-notify.php';
require_once '../common/_include_summernote.php';
ruoloRichiesto('admin');

if(! isset($_GET)) {
	return;
} else {
	$template_id = $_GET['template_id'];
}

// recupera dal db i dati di questo template
$template = dbGetFirst("SELECT * FROM modulistica_template WHERE modulistica_template.id = $template_id;");

// controllo lo stato
$statoMarker = '';
if (! $template['valido']) {
	$statoMarker = '<span class="label label-danger">disattivato</span>';
} else {
	$statoMarker = '<span class="label label-success">si</span>';
}

echo '<title>Modulo ' . $template['nome']. '</title>';

$tipoOptionList = '';
$tipoOptionList .= '<optgroup><option data-icon="glyphicon glyphicon-option-horizontal" value="1" >testo</option></optgroup>';
$tipoOptionList .= '<optgroup><option data-icon="glyphicon glyphicon-list" value="2" >combo</option></optgroup>';
$tipoOptionList .= '<optgroup><option data-icon="glyphicon glyphicon-check" value="3" >checkbox</option></optgroup>';
$tipoOptionList .= '<optgroup><option data-icon="glyphicon glyphicon-record" value="4" >radio</option></optgroup>';
$tipoOptionList .= '<optgroup><option data-icon="glyphicon glyphicon-font" value="5" >text area</option></optgroup>';

?>

</head>

<body >
<?php
	require_once '../common/header-admin.php';
?>

<!-- esterno: questo pannello contiene gli altri due -->
<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-lightblue4">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-5">
			<span class="glyphicon glyphicon-education"></span>&ensp;Modulo: <strong><?php echo $template['nome']; ?></strong>
		</div>
        <div class="col-md-2">
            Stato: <?php echo ($statoMarker); ?>
        </div>
        <div class="col-md-5">
        </div>
	</div>
</div>
<div class="panel-body">
    <div class="row">
        <div class="col-md-12">
            <div class="records_contenta"></div>
        </div>
    </div>
</div>

<!-- documento Content Section -->
<div class="container-fluid" style="margin-top:0px">
<div class="panel panel-teal4">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-4">
			<span class="glyphicon glyphicon-file"></span>&ensp;Documento
		</div>
		<div class="col-md-2 text-left">
		</div>
		<div class="col-md-2 text-center">
		</div>
		<div class="col-md-2 text-center">
		</div>
		<div class="col-md-1 text-center">
		<div class="pull-right">
        </div>
		</div>
		<div class="col-md-1 text-right">
		</div>
	</div>
</div>
<div class="panel-body">
    <div class="row">
        <div class="col-md-12">
        <div class="form-group">
<!--                <label for="template">template del documento</label> -->
                <div class="summernote" rows="6" id="template" placeholder="template" ></div>
        </div>
        </div>
    </div>
</div>
<div class="panel-footer text-center">
    <button type="button" class="btn btn-default" onclick="readTemplate()">Ricarica</button>
    <button type="button" class="btn btn-primary" onclick="modulisticaTemplateSave()" >Salva</button>
</div>
</div>
</div>

<!-- campi Content Section -->
<div class="container-fluid" style="margin-top:20px">
<div class="panel panel-teal4">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-4">
			<span class="glyphicon glyphicon-list-alt"></span>&ensp;Campi
		</div>
		<div class="col-md-2 text-left">
		</div>
		<div class="col-md-2 text-center">
		</div>
		<div class="col-md-2 text-center">
		</div>
		<div class="col-md-1 text-center">
		<div class="pull-right">
        </div>
		</div>
		<div class="col-md-1 text-right">
            <div class="pull-right">
				<button class="btn btn-xs btn-teal4" onclick="modulisticaCampoGetDetails(-1)" ><span class="glyphicon glyphicon-plus"></span></button>
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
</div>
</div>

<!-- <div class="panel-footer"></div> del primo -->
</div>

<div class="modal fade" id="modulistica_campo_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-teal4">
			<div class="panel-heading">
			    <h5 class="modal-title" id="myModalLabel">Campo</h5>
			</div>
			<div class="panel-body">
			<form class="form-horizontal">
			    <div class="form-group">
                    <label class="col-sm-2 control-label" for="nome">nome</label>
                    <div class="col-sm-4"><input type="text" id="nome" placeholder="nome" class="form-control"/></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="etichetta">etichetta</label>
                    <div class="col-sm-4"><input type="text" id="etichetta" placeholder="etichetta che appare di fianco al il campo" class="form-control"/></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="tip">aiuto</label>
                    <div class="col-sm-8"><input type="text" id="tip" placeholder="aiuto che appare dentro al campo" class="form-control"/></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="tipo">tipo</label>
                    <div class="col-sm-3"><select id="tipo" name="ipo" class="tipo selectpicker" data-live-search="false" data-width="70%" > <?php echo $tipoOptionList ?></select></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="lista_valori">valori</label>
                    <div class="col-sm-8"><input type="text" id="lista_valori" placeholder="lisa dei possibili valori separati da ::" class="form-control"/></div>
                </div>
				<div class="form-group">
                    <label class="col-sm-2 control-label" for="valore_default">default</label>
                    <div class="col-sm-8"><input type="text" id="valore_default" placeholder="eventuale valore di default (o vuoto)" class="form-control"/></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="salva_valore" class="col-sm-2 control-label">salva valore</label>
                    <div class="col-sm-1 "><input type="checkbox" id="salva_valore" ></div>
                </div>
                <hr>
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="obbligatorio" class="col-sm-2 control-label">obbligatorio</label>
                    <div class="col-sm-1 "><input type="checkbox" id="obbligatorio" ></div>
                </div>

			</div>
			<div class="panel-footer text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				<button type="button" class="btn btn-primary" onclick="modulisticaCampoSave()" >Salva</button>
				<input type="hidden" id="hidden_template_campo_id">
				<input type="hidden" id="hidden_template_id" value="<?php echo $template_id; ?>">
            </div>
            </div>
            </div>
        </div>
    </div>
</div>
<!-- // Modal - Update docente details -->

<!-- Custom JS file -->
<script type="text/javascript" src="js/modulisticaCampo.js?v=<?php echo $__software_version; ?>"></script>

</body>
</html>
