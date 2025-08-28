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
    ruoloRichiesto('genitore', 'segreteria-didattica', 'dirigente');
    
    if (!(getSettingsValue('config', 'permessi', false)))
    {
        redirect("/error/unauthorized.php");
    }

    ?>

    <!-- bootbox notificator -->
    <script type="text/javascript"
        src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">

    <title>Permessi di uscita</title>
    <style>
        /* Tooltip */
        .tooltip>.tooltip-inner {
            background-color: #73AD21;
            color: #FFFFFF;
            border: 1px solid green;
            padding: 15px;
            font-size: 20px;
        }

        .tooltip.top>.tooltip-arrow {
            border-top: 5px solid green;
        }

        .tooltip.bottom>.tooltip-arrow {
            border-bottom: 5px solid blue;
        }

        .tooltip.left>.tooltip-arrow {
            border-left: 5px solid red;
        }

        .tooltip.right>.tooltip-arrow {
            border-right: 5px solid black;
        }

        .tooltip-inner {
            max-width: 450px;
            /* If max-width does not work, try using width instead */
            width: 450px;
            text-align: left;
        }
    </style>

</head>

<?php
// prepara l'elenco degli studenti per il filtro
$studenteFiltroOptionList = '';

$studenti = dbGetAll("SELECT * FROM studente WHERE id IN (
    SELECT id_studente FROM genitori_studenti WHERE id_genitore = " . intval($__genitore_id) . "
)");
$firstId="";
foreach ($studenti as $studente) {
    if ($firstId=="")
    {
        $firstId=$studente['id'];
    }
    $studenteFiltroOptionList .= '<option value="' . $studente['id'] . '">'
        . $studente['cognome'] . ' ' . $studente['nome'] . '</option>';
}
?>

<body>
    <?php
    require_once '../common/header-genitore.php';
    require_once '../common/connect.php';
    ?>

    <div class="container-fluid" style="margin-top:60px">
        <div class="panel panel-orange4">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-md-2" style="padding:10px">
                        <span class="glyphicon glyphicon-blackboard"></span>&ensp;Permessi di uscita
                    </div>
                    <div class="col-md-4">
                    </div>
                    <div class="col-md-3">
                    </div>
                    <div class=" col-md-1">
                        <div class="text-center" style="margin:10px 0px 0px 0px; text-align:right">
                            <button class="btn btn-xs btn-orange4" onclick="permessiGetDetails(-1)"><span
                                    class="glyphicon glyphicon-plus"></span></button>
                        </div>
                    </div>
                    <div class="col-md-2" style="padding:0px">
                        <div class="text-center">
                            <label class="col-sm-2 control-label" for="studente"
                                style="margin:10px 0px 0px 0px; text-align:right">Studente</label>
                            <div class="col-sm-10" style="padding:0px 10px 0px 10px;text-align:right"><select id="studente_filtro" name="studente_filtro"
                                    class="studente_filtro selectpicker" data-style="btn-yellow4" data-live-search="true"
                                    data-noneSelectedText="seleziona..." data-width="85%">
                                    <?php echo $studenteFiltroOptionList ?>
                                </select></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="panel-body">
                <div class="row" style="margin-bottom:10px;">
                    <div class="col-md-6">
                    </div>

                    <div class="col-md-6">
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

    </div>

    <!-- Modal - Add/Update Record -->
    <!-- Modale Permesso -->
    <div class="modal fade" id="permesso_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog modal-lg" style="width:500px" role="document">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="panel panel-lima4">
                        <div class="panel-heading">
                            <h5 class="modal-title" style="text-align:center" id="myModalLabel">Permesso di uscita</h5>
                        </div>
                        <div class="panel-body">
                            <form class="form-horizontal">

                                <div class="form-group">
                                    <label class="col-sm-2 control-label" for="data">Data</label>
                                    <div class="col-sm-10">
                                        <input type="date" id="data" class="form-control" />
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="col-sm-2 control-label" for="ora_uscita">Ora uscita</label>
                                    <div class="col-sm-10">
                                        <input type="time" id="ora_uscita" class="form-control step="60" placeholder="HH:MM" />
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="col-sm-2 control-label" for="motivo">Motivo</label>
                                    <div class="col-sm-10">
                                        <textarea id="motivo" placeholder="motivo" class="form-control" rows="3"></textarea>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="rientro" class="col-sm-2 control-label">Rientro</label>
                                    <div class="col-sm-1">
                                        <input type="checkbox" id="rientro">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="col-sm-2 control-label" for="ora_rientro">Ora rientro</label>
                                    <div class="col-sm-10">
                                        <input type="time" id="ora_rientro" class="form-control step="60" placeholder="HH:MM"  />
                                    </div>
                                </div>

                                <div class="form-group" id="_error-permesso-part">
                                    <strong>
                                        <hr>
                                        <div class="col-sm-3 text-right text-danger ">Attenzione</div>
                                        <div class="col-sm-9" id="_error-permesso"></div>
                                    </strong>
                                </div>

                                <input type="hidden" id="hidden_permesso_id">
                                <input type="hidden" id="hidden_studente_id" value="<?php echo $firstId?>">
                                <input type="hidden" id="hidden_rientro">
                            </form>
                        </div>
                        <div class="panel-footer text-center">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                            <button id="btn-save" type="button" class="btn btn-primary" onclick="permessoSave()">Salva</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JS per Timepicker -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-timepicker/0.5.2/css/bootstrap-timepicker.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-timepicker/0.5.2/js/bootstrap-timepicker.min.js"></script>
    <script>
        $('.timepicker').timepicker({
            showMeridian: false, // 24h
            showSeconds: false, // nessun secondo
            defaultTime: false
        });
    </script>

    <!-- // Modal - Add/Update Record -->

    <!-- Custom JS file -->
    <script type="text/javascript" src="js/permessi.js?v=<?php echo $__software_version; ?>&d=desktop"></script>
</body>

</html>