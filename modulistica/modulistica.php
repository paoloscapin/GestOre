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
	<title>Modulistica</title>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
<?php
require_once '../common/checkSession.php';

require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-select.php';

ruoloRichiesto('modulistica');
?>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-4.css">
</head>

<body >
<?php require_once '../common/header-modulistica.php'; ?>

<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-lightblue4">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-5">
			<span class="glyphicon glyphicon-folder-close"></span>&emsp;Modulistica
		</div>
        <div class="col-md-2">
            <div class="text-center">
				<label class="checkbox-inline">
					<input type="checkbox" checked data-toggle="toggle" data-size="mini" data-onstyle="primary" id="soloValidiCheckBox" >Solo Validi
				</label>
            </div>
        </div>
        <div class="col-md-5">
            <div class="pull-right">
				<button class="btn btn-xs btn-lightblue4" onclick="modulisticaGetDetails(-1)" ><span class="glyphicon glyphicon-plus"></span></button>
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

<?php
$categoriaOptionList = '';
foreach(dbGetAll("SELECT * FROM modulistica_categoria ORDER BY posizione;") as $categoria) {
    $categoriaOptionList .= '<option value="'.$categoria["id"].'" data-content="<span class=\'label\'; style=\'color: #008;'.$categoria["colore"].'\';\'>'.$categoria["nome"].'</span>">'.$categoria["nome"].'</option>';
}
?>

<div class="modal fade" id="update_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-lightblue4">
			<div class="panel-heading">
				<h5 class="modal-title" id="myModalLabel">Modulistica</h5>
			</div>
			<div class="panel-body">
			<form class="form-horizontal">

                <div class="form-group">
                    <label class="col-sm-3 control-label" for="nome">Modulo</label>
                    <div class="col-sm-7"><input type="text" id="nome" placeholder="nome" class="form-control"/></div>
                </div>
                <div class="form-group">
                    <label for="valido" class="col-sm-3 control-label">Categoria</label>
                    <div class="col-sm-4">
                        <select id="categoria" name="categoria" class="categoria selectpicker" data-live-search="false" data-width="100%" > <?php echo $categoriaOptionList ?></select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="valido" class="col-sm-3 control-label">Valido</label>
                    <div class="col-sm-1 "><input type="checkbox" id="valido" ></div>
                </div>
                <hr>
                <div class="form-group">
                    <label for="produci_pdf" class="col-sm-3 control-label">produci pdf</label>
                    <div class="col-sm-1 "><input type="checkbox" id="produci_pdf" ></div>
                </div>
                <div class="form-group">
                    <label for="intestazione" class="col-sm-3 control-label">intestazione pdf</label>
                    <div class="col-sm-1 "><input type="checkbox" id="intestazione" ></div>
                </div>
                <hr>
                <div class="form-group">
                    <label class="col-sm-3 control-label" for="email_to">destinatario</label>
                    <div class="col-sm-7"><input type="text" id="email_to" placeholder="email del destinatario finale (ufficio competente)" class="form-control"/></div>
                </div>
                <div class="form-group">
                    <label for="email_di_avviso" class="col-sm-3 control-label">mail di avviso</label>
                    <div class="col-sm-1 "><input type="checkbox" id="email_di_avviso" ></div>
                </div>
                <hr>
                <div class="form-group">
                    <label for="approva" class="col-sm-3 control-label">approvazione</label>
                    <div class="col-sm-1 "><input type="checkbox" id="approva" ></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label" for="email_approva">email approvazione</label>
                    <div class="col-sm-7"><input type="text" id="email_approva" placeholder="email per approvazione" class="form-control"/></div>
                </div>
                <div class="form-group">
                    <label for="messaggio_approvazione" class="col-sm-3 control-label">messaggio approvazione</label>
                    <div class="col-sm-1 "><input type="checkbox" id="messaggio_approvazione" ></div>
                </div>
                <hr>
                <div class="form-group">
                    <label for="firma_forte" class="col-sm-3 control-label">firma forte</label>
                    <div class="col-sm-1 "><input type="checkbox" id="firma_forte" ></div>
                </div>
            </form>
        </div>
        <div class="panel-footer text-center">
            <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
            <button type="button" class="btn btn-primary" onclick="modulisticaSave()">Salva</button>
	    	<input type="hidden" id="hidden_record_id">
            </div>
			</div>
			</div>
        </div>
    </div>
</div>
<!-- // Modal - Add/Update New Record -->

<!-- Custom JS file -->
<script type="text/javascript" src="js/modulistica.js?v=<?php echo $__software_version; ?>"></script>
</body>
</html>
