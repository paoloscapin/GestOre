<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
?>

<!-- Modal odg -->
<div class="modal fade" id="odg_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabelodg">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-deeporange4">
			<div class="panel-heading">
				<h5 class="modal-title" id="updateMyModalLabelodg">Ordine del Giorno Assemblea di Classe</h5>
			</div>
			<div class="panel-body">
			<form class="form-horizontal">
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="classeodg">Classe</label>
                    <div class="col-sm-3"><input type="text" id="classeodg" placeholder="classeodg" class="form-control" readonly /></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="data_assembleaodg">Data</label>
					<div class="col-sm-4"><input type="text" value="21/8/2018" id="data_assembleaodg" placeholder="data" class="form-control" readonly /></div>
                </div>

                <hr>
                <div class="form-group ordine_del_giorno_odg">
                    <label class="" for="ordine_del_giorno_odg">Ordine del giorno</label>
                    <div id="ordine_del_giorno_odg" class="well" style="background-color: #fff; max-height: 500px; overflow-y: auto; border-color: #ddd;">
				<!-- Il testo HTML del DB verrà inserito qui -->
                </div>
				</div>
			</form>
            </div>
			<div class="panel-footer text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
			</div>
			</div>
			</div>
        </div>
    </div>
</div>

<!-- Modal verbale -->
<div class="modal fade" id="verbale_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabelverbale">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-deeporange4">
			<div class="panel-heading">
				<h5 class="modal-title" id="updateMyModalLabelverbale">Verbale Assemblea di Classe</h5>
			</div>
			<div class="panel-body">
			<form class="form-horizontal">
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="classeverbale">Classe</label>
                    <div class="col-sm-3"><input type="text" id="classeverbale" placeholder="classeverbale" class="form-control" readonly /></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="data_assembleaverbale">Data</label>
					<div class="col-sm-4"><input type="text" value="21/8/2018" id="data_assembleaverbale" placeholder="data" class="form-control" readonly /></div>
                </div>

				<hr>
                <div class="form-group ordine_del_giorno_verbale2">
                    <label class="" for="ordine_del_giorno_verbale2">Ordine del giorno</label>
                    <div id="ordine_del_giorno_verbale2" class="well" style="background-color: #fff; max-height: 500px; overflow-y: auto; border-color: #ddd;"></div>
                </div>
<!--                <hr>
				<div id="ordine_del_giorno_verbale2" class="well" style="background-color: #fff; max-height: 500px; overflow-y: auto; border-color: #ddd;">
				</div>
-->

                <hr>
                <div class="form-group verbale">
                    <label class="" for="verbale">Verbale</label>
                    <div class="summernote" rows="5" id="verbale" placeholder="verbale" ></div>
                </div>

			</form>
            </div>
			<div class="panel-footer text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
				<button type="button" id="salvaVerbaleBtnId" class="btn btn-primary" onclick="richiestaAssembleaSalvaVerbale()" >Salva</button>
				<button type="button" id="inviaVerbaleBtnId" class="btn btn-success" onclick="richiestaAssembleaInviaVerbale()" >Invia</button>
			</div>
			</div>
			</div>
        </div>
    </div>
</div>

<!-- Modal verbale readonly -->
<div class="modal fade" id="verbalero_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabelverbalero">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-body">
			<div class="panel panel-deeporange4">
			<div class="panel-heading">
				<h5 class="modal-title" id="updateMyModalLabelverbalero">Verbale Assemblea di Classe</h5>
			</div>
			<div class="panel-body">
			<form class="form-horizontal">
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="classeverbalero">Classe</label>
                    <div class="col-sm-3"><input type="text" id="classeverbalero" placeholder="classeverbalero" class="form-control" readonly /></div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="data_assembleaverbalero">Data</label>
					<div class="col-sm-4"><input type="text" value="21/8/2018" id="data_assembleaverbalero" placeholder="data" class="form-control" readonly /></div>
                </div>

                <hr>
				<div id="ordine_del_giorno_verbalero" class="well" style="background-color: #fff; max-height: 500px; overflow-y: auto; border-color: #ddd;">
				<!-- Il testo HTML del DB verrà inserito qui -->
				</div>

                <hr>
				<div id="verbale_verbalero" class="well" style="background-color: #fff; max-height: 500px; overflow-y: auto; border-color: #ddd;">
				<!-- Il testo HTML del DB verrà inserito qui -->
				</div>

			</form>
            </div>
			<div class="panel-footer text-center">
				<button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
			</div>
			</div>
			</div>
        </div>
    </div>
</div>
