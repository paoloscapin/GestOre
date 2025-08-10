<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
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
    require_once '../common/_include_bootstrap-toggle.php';
    require_once '../common/_include_bootstrap-select.php';
    require_once '../common/_include_flatpickr.php';
    ruoloRichiesto('segreteria-didattica', 'dirigente');
    ?>
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
    <title>Genitori</title>
</head>

<body>
    <?php
    require_once '../common/header-didattica.php';
    require_once '../common/connect.php';

    // prepara l'elenco per il filtro
    $classiOptionList = '<option value="0">scegli classe</option>';
    $classiFiltroOptionList = '<option value="0">Tutte</option>';
    foreach (dbGetAll("SELECT * FROM classi WHERE attiva = '1' ORDER BY classe ASC") as $classi) {
        $classiOptionList .= ' <option value="' . $classi['id'] . '" >' . $classi['classe'] . '</option> ';
        $classiFiltroOptionList .= ' <option value="' . $classi['id'] . '" >' . $classi['classe'] . '</option> ';
    }

    ?>

    <div class="container-fluid" style="margin-top:60px">
        <div class="panel panel-orange4">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-md-2">
                        <span class="glyphicon glyphicon-pawn"></span>&ensp;Genitori
                    </div>
                    <div class="col-md-2">
                        <div class="text-right">
                            <label class="col-sm-2 control-label" for="classe"
                                style="margin:5px 0px 0px 0px;">Classe</label>
                            <div class="col-sm-auto"><select id="classe_filtro" name="classe_filtro"
                                    class="classe_filtro selectpicker" data-style="btn-yellow4" data-live-search="true"
                                    data-noneSelectedText="seleziona..." data-width="50%">
                                    <?php echo $classiFiltroOptionList ?>
                                </select></div>
                        </div>
                    </div>
                    <div class="col-md-2 text-center">
                        <label id="import_btn" class="btn btn-xs btn-lima4 btn-file"><span class="glyphicon glyphicon-upload"></span>&emsp;Importa<input type="file" id="file_select_id" style="display: none;"></label>
                    </div>
                    <div class="col-md-2">
                        <div class="text-center" style="margin:5px 0px 0px 0px;">
                            <label class="checkbox-inline">
                                <input type="checkbox" checked data-toggle="toggle" data-size="mini"
                                    data-onstyle="primary" id="ancheSenzaStudentiCheckBox">Anche senza studenti
                            </label>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-center" style="margin:5px 0px 0px 0px;">
                            <label class="checkbox-inline">
                                <input type="checkbox" checked data-toggle="toggle" data-size="mini"
                                    data-onstyle="primary" id="soloAttiviCheckBox">Solo attivi
                            </label>
                        </div>
                    </div>
                    <div class="col-md-2 text-right">
                        <div class="pull-right">
                            <button class="btn btn-xs btn-orange4" onclick="genitoreGetDetails(-1)"><span class="glyphicon glyphicon-plus"></span></button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="panel-body">
                <div class="row" style="margin-bottom:10px;">
                    <div class="col-md-12 text-center" id='result_text'>
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
        <div class="modal fade" id="genitore_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
            <div class="modal-dialog modal-lg" style="width:500px" role="document">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="panel panel-lima4">
                            <div class="panel-heading">
                                <h5 class="modal-title" id="myModalLabel">genitore</h5>
                            </div>
                            <div class="panel-body">
                                <form class="form-horizontal">


                                    <div class="form-group">
                                        <label class="col-sm-2 control-label" for="cognome">Cognome</label>
                                        <div class="col-sm-10"><input type="text" id="cognome" placeholder="cognome" class="form-control" /></div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-2 control-label" for="nome">Nome</label>
                                        <div class="col-sm-10"><input type="text" id="nome" placeholder="nome" class="form-control" /></div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-2 control-label" for="email">Email</label>
                                        <div class="col-sm-10"><input type="text" id="email" placeholder="email" class="form-control" /></div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-2 control-label" for="codice_fiscale">Codice Fiscale</label>
                                        <div class="col-sm-10"><input type="text" id="codice_fiscale" placeholder="codice_fiscale" class="form-control" /></div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-2 control-label" for="userId">UserID MasterCom</label>
                                        <div class="col-sm-10"><input type="text" id="userId" placeholder="userId" class="form-control" /></div>
                                    </div>

                                    <div class="form-group">
                                        <label for="attivo" class="col-sm-2 control-label">Attivo</label>
                                        <div class="col-sm-1">
                                            <input type="checkbox" id="attivo">
                                        </div>
                                    </div>
                                    <div class="form-group text-center" id="relazioni-part">
                                        <hr>
                                        <label for="relazioni_table">Studenti collegati</label>
                                        <div class="table-wrapper">
                                            <table class="table table-bordered table-striped" id="relazioni_table">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center">Studente</th>
                                                        <th class="text-center">Relazione</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                </tbody>
                                            </table>
                                        </div>


                                        <div class="form-group" id="_error-classe-part"><strong>
                                                <hr>
                                                <div class="col-sm-3 text-right text-danger ">Attenzione</div>
                                                <div class="col-sm-9" id="_error-classe"></div>
                                            </strong></div>

                                        <input type="hidden" id="hidden_genitore_id">
                                        <input type="hidden" id="hidden_attivo">
                                        <input type="hidden" id="hidden_anno_id">
                                </form>

                            </div>
                            <div class="panel-footer text-center">
                                <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                                <button id="btn-save" type="button" class="btn btn-primary" onclick="genitoreSave()">Salva</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- // Modal - Add/Update Record -->

    </div>

    <!-- Custom JS file -->
    <script type="text/javascript" src="js/genitore.js"></script>
</body>

</html>