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
	<title>Tipi di Attività</title>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
<?php


require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-select.php';
ruoloRichiesto('dirigente');
?>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-3.css">
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/header-style.css">

<script type="text/javascript" src="js/attivita.js?v=<?php echo $__software_version; ?>"></script>
</head>

<body >
<?php require_once '../common/header-admin.php'; ?>

<!-- Content Section -->
<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-lima4">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-6">
			<span class="glyphicon glyphicon-education"></span>&emsp;Tipi di Attività
		</div>
        <div class="col-md-6">
            <div class="pull-right">
                <button class="btn btn-xs btn-lima4" onclick="attivitaGetDetails(-1)" ><span class="glyphicon glyphicon-plus"></span></button>
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
<div class="modal fade" id="record_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-lima4">
			<div class="panel-heading">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h5 class="modal-title" id="myModalUpdateLabel">Tipo Attività</h5>
            </div>
            <div class="panel-body">
            <form class="form-horizontal">

                <div class="form-group categoria_selector">
                    <label class="col-sm-2 control-label" for="categoria">Categoria</label>
					<div class="col-sm-8"><select id="categoria" name="categoria" class="categoria selectpicker" data-style="btn-teal4" data-live-search="false" data-noneSelectedText="seleziona..." data-width="70%" >
                    <option value="aggiornamento" >aggiornamento</option>
                    <option value="funzionali" >funzionali</option>
                    <option value="con studenti" >con studenti</option>
                    <option value="CLIL" >CLIL</option>
                    <option value="orientamento" >orientamento</option>
					</select></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-3 control-label" for="nome">Nome</label>
                     <div class="col-sm-8"><input type="text" id="nome" placeholder="nome" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-3 control-label" for="ore">Ore</label>
                     <div class="col-sm-2"><input type="text" id="ore" placeholder="ore" class="form-control"/></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-3 control-label" for="ore_max">Ore max</label>
                     <div class="col-sm-2"><input type="text" id="ore_max" placeholder="max ore" class="form-control"/></div>
                </div>

                <hr>

                <div class="form-group">
                    <label class="col-sm-3 control-label" for="check_funzionali">Funzionali</label>
					<div class="col-sm-2"><input type="checkbox" checked data-toggle="toggle" data-size="mini" data-onstyle="primary" id="check_funzionali" ></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label" for="check_con_studenti">con Studenti</label>
					<div class="col-sm-2"><input type="checkbox" checked data-toggle="toggle" data-size="mini" data-onstyle="primary" id="check_con_studenti" ></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label" for="check_clil">Clil</label>
					<div class="col-sm-2"><input type="checkbox" checked data-toggle="toggle" data-size="mini" data-onstyle="primary" id="check_clil" ></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label" for="check_orientamento">Orientamento</label>
					<div class="col-sm-2"><input type="checkbox" checked data-toggle="toggle" data-size="mini" data-onstyle="primary" id="check_orientamento" ></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label" for="check_valido">Valido</label>
					<div class="col-sm-2"><input type="checkbox" checked data-toggle="toggle" data-size="mini" data-onstyle="primary" id="check_valido" ></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label" for="check_previsto_da_docente">Previsto da Docente</label>
					<div class="col-sm-2"><input type="checkbox" checked data-toggle="toggle" data-size="mini" data-onstyle="primary" id="check_previsto_da_docente" ></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label" for="check_inserito_da_docente">Inserito da Docente</label>
					<div class="col-sm-2"><input type="checkbox" checked data-toggle="toggle" data-size="mini" data-onstyle="primary" id="check_inserito_da_docente" ></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label" for="check_da_rendicontare">Da Rendicontare</label>
					<div class="col-sm-2"><input type="checkbox" checked data-toggle="toggle" data-size="mini" data-onstyle="primary" id="check_da_rendicontare" ></div>
                </div>
            </form>
            </div>

			<div class="panel-footer text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				<button type="button" class="btn btn-primary" onclick="attivitaSave()" >Salva</button>
				<input type="hidden" id="hidden_record_id">
                </div>
			</div>
			</div>
        </div>
    </div>
</div>
<!-- // Modal - Update record details -->
</div>

</body>
</html>
