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
	<title>Tipi di Attività</title>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
<?php
require_once '../common/checkSession.php';

require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';
ruoloRichiesto('dirigente');
?>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-3.css">
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/header-style.css">

<script type="text/javascript" src="js/attivita.js"></script>
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
				<button class="btn btn-xs btn-lima4" data-toggle="modal" data-target="#add_record_modal"><span class="glyphicon glyphicon-plus"></span></button>
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
<!-- Modal - Add New Record -->
<div class="modal fade" id="add_record_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-lima4">
			<div class="panel-heading">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h5 class="modal-title" id="myModalLabel">Tipo Attività</h5>
            </div>
            <div class="panel-body">

                <div class="form-group">
                    <label for="categoria">Categoria</label>
                    <input type="text" id="categoria" placeholder="categoria" class="form-control"/>
                </div>

                <div class="form-group">
                    <label for="nome">Nome</label>
                    <input type="text" id="nome" placeholder="nome" class="form-control"/>
                </div>

                <div class="form-group">
                    <label for="ore">Ore</label>
                    <input type="text" id="ore" placeholder="ore" class="form-control"/>
                </div>

                <div class="form-group">
                    <label for="ore_max">Ore max</label>
                    <input type="text" id="ore_max" placeholder="max ore" class="form-control"/>
                </div>

                <div class="form-group">
                    <label for="valido">Valido</label>
					<input type="checkbox" checked data-toggle="toggle" data-size="mini" data-onstyle="primary" id="valido" >
                </div>

                <div class="form-group">
                    <label for="previsto_da_docente">Previsto da Docente</label>
					<input type="checkbox" checked data-toggle="toggle" data-size="mini" data-onstyle="primary" id="previsto_da_docente" >
                </div>

                <div class="form-group">
                    <label for="inserito_da_docente">Inserito da Docente</label>
					<input type="checkbox" checked data-toggle="toggle" data-size="mini" data-onstyle="primary" id="inserito_da_docente" >
                </div>

                <div class="form-group">
                    <label for="da_rendicontare">Da Rendicontare</label>
					<input type="checkbox" checked data-toggle="toggle" data-size="mini" data-onstyle="primary" id="da_rendicontare" >
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-primary" onclick="attivitaAddRecord()">Salva</button>
            </div>
			</div>
			</div>
        </div>
    </div>
</div>
<!-- // Modal - Add New Record -->

<!-- Modal - Update record details -->
<div class="modal fade" id="update_record_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalUpdateLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-lima4">
			<div class="panel-heading">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h5 class="modal-title" id="myModalUpdateLabel">Tipo Attività</h5>
            </div>
            <div class="panel-body">

            <div class="form-group">
                    <label for="update_categoria">Categoria</label>
                    <input type="text" id="update_categoria" placeholder="categoria" class="form-control"/>
                </div>

                <div class="form-group">
                    <label for="update_nome">Nome</label>
                    <input type="text" id="update_nome" placeholder="nome" class="form-control"/>
                </div>

                <div class="form-group">
                    <label for="update_ore">Ore</label>
                    <input type="text" id="update_ore" placeholder="ore" class="form-control"/>
                </div>

                <div class="form-group">
                    <label for="update_ore_max">Ore max</label>
                    <input type="text" id="update_ore_max" placeholder="max ore" class="form-control"/>
                </div>

                <div class="form-group">
                    <label for="update_valido">Valido</label>
					<input type="checkbox" checked data-toggle="toggle" data-size="mini" data-onstyle="primary" id="update_valido" >
                </div>

                <div class="form-group">
                    <label for="update_previsto_da_docente">Previsto da Docente</label>
					<input type="checkbox" checked data-toggle="toggle" data-size="mini" data-onstyle="primary" id="update_previsto_da_docente" >
                </div>

                <div class="form-group">
                    <label for="update_inserito_da_docente">Inserito da Docente</label>
					<input type="checkbox" checked data-toggle="toggle" data-size="mini" data-onstyle="primary" id="update_inserito_da_docente" >
                </div>

                <div class="form-group">
                    <label for="update_da_rendicontare">Da Rendicontare</label>
					<input type="checkbox" checked data-toggle="toggle" data-size="mini" data-onstyle="primary" id="update_da_rendicontare" >
                </div>
            </div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				<button type="button" class="btn btn-primary" onclick="attivitaUpdateDetails()" >Salva</button>
				<input type="hidden" id="hidden_attivita_id">
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
