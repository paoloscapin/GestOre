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

    ruoloRichiesto('studente', 'segreteria-didattica', 'dirigente');
    ?>

    <!-- bootbox notificator -->
    <script type="text/javascript"
        src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">

    <title>Sportelli</title>
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

// prepara l'elenco delle categorie per il filtro
$categoriaFiltroOptionList = '<option value="0">tutti</option>';
$default = "sportello didattico";
foreach (dbGetAll("SELECT * FROM sportello_categoria ORDER BY sportello_categoria.nome ASC ; ") as $categoria) {
    if ($categoria['nome'] == $default)
        $categoriaFiltroOptionList .= ' <option value="' . $categoria['id'] . '" selected >' . $categoria['nome'] . '</option> ';
    else
        $categoriaFiltroOptionList .= ' <option value="' . $categoria['id'] . '" >' . $categoria['nome'] . '</option> ';
}

// prepara l'elenco dei docenti per il filtro
$docenteFiltroOptionList = '<option value="0">tutti</option>';
foreach (dbGetAll("SELECT * FROM docente WHERE docente.attivo = true ORDER BY docente.cognome, docente.nome ASC ; ") as $docente) {
    $docenteFiltroOptionList .= ' <option value="' . $docente['id'] . '" >' . $docente['cognome'] . ' ' . $docente['nome'] . '</option> ';
}

// prepara l'elenco delle materie per il filtro e per le materie del dialog
$materiaFiltroOptionList = '<option value="0">tutte</option>';

foreach (dbGetAll("SELECT * FROM materia ORDER BY materia.nome ASC ; ") as $materia) {
    $materiaFiltroOptionList .= ' <option value="' . $materia['id'] . '" >' . $materia['nome'] . '</option> ';
}

// prepara l'elenco delle materie per il filtro e per le materie del dialog
$classeFiltroOptionList = '<option value="0">tutte</option>';
foreach (dbGetAll("SELECT * FROM classe ORDER BY classe.nome ASC ; ") as $classe) {
    $classeFiltroOptionList .= ' <option value="' . $classe['id'] . '" >' . $classe['nome'] . '</option> ';
}

?>

<body>
    <?php
    require_once '../common/header-studente.php';
    require_once '../common/connect.php';
    ?>

    <div class="container-fluid" style="margin-top:60px">
        <div class="panel panel-orange4">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-md-1" style="padding:10px">
                        <span class="glyphicon glyphicon-blackboard"></span>&ensp;Sportelli
                    </div>
                                        <div class="col-md-2" style="padding:0px">
                        <div class="text-center">
                            <label class="col-sm-2 control-label" for="categoria"
                                style="margin:10px 0px 0px 0px; text-align:right">Categoria</label>
                            <div class="col-sm-10" style="padding:0px;text-align:right"><select id="categoria_filtro"
                                    name="categoria_filtro" class="categoria_filtro selectpicker"
                                    data-style="btn-yellow4" data-live-search="true"
                                    data-noneSelectedText="seleziona..." data-width="80%">
                                    <?php echo $categoriaFiltroOptionList ?>
                                </select></div>
                        </div>
                    </div>
                    <div class="col-md-2" style="padding:0px">
                        <div class="text-center">
                            <label class="col-sm-2 control-label" for="docente"
                                style="margin:10px 0px 0px 0px; text-align:right">Docente</label>
                            <div class="col-sm-10" style="padding:0px;text-align:right"><select id="docente_filtro" name="docente_filtro"
                                    class="docente_filtro selectpicker" data-style="btn-yellow4" data-live-search="true"
                                    data-noneSelectedText="seleziona..." data-width="85%">
                                    <?php echo $docenteFiltroOptionList ?>
                                </select></div>
                        </div>
                    </div>
                    <div class="col-md-2" style="padding:0px">
                        <div class="text-center">
                            <label class="col-sm-2 control-label" for="materia"
                                style="margin:10px 0px 0px 0px; text-align:right">Materia</label>
                            <div class="col-sm-10" style="padding:0px;text-align:right"><select id="materia_filtro" name="materia_filtro"
                                    class="materia_filtro selectpicker" data-style="btn-yellow4" data-live-search="true"
                                    data-noneSelectedText="seleziona..." data-width="90%">
                                    <?php echo $materiaFiltroOptionList ?>
                                </select></div>
                        </div>
                    </div>
                    <div class="col-md-2" style="padding:0px">
                        <div class="text-center">
                            <label class="col-sm-2 control-label" for="classe"
                                style="margin:10px 0px 0px 0px; text-align:right">Classe</label>
                            <div class="col-sm-10" style="padding:0px;text-align:right"><select id="classe_filtro" name="classe_filtro"
                                    class="classe_filtro selectpicker" data-style="btn-yellow4" data-live-search="true"
                                    data-noneSelectedText="seleziona..." data-width="90%">
                                    <?php echo $classeFiltroOptionList ?>
                                </select></div>
                        </div>
                    </div>
                    <div class="col-md-3" style="padding:0px">
                        <div class="text-center">
                            <label class="checkbox-inline">
                                <input type="checkbox" checked data-toggle="toggle" data-size="mini"
                                    data-onstyle="primary" id="soloNuoviCheckBox">Solo Nuovi
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" data-toggle="toggle" data-size="mini" data-onstyle="primary"
                                    id="soloIscrittoCheckBox">Iscritto
                            </label>
                            <label class="checkbox-inline">
                                <input type="checkbox" data-toggle="toggle" data-size="mini" data-onstyle="primary"
                                    id="ancheCancellatiCheckBox">Cancellati
                            </label>
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
    <input type="hidden" id="hidden_unSoloArgomento"
        value="<?php echo getSettingsValue("sportelli", "unSoloArgomento", true) ? 1 : 0; ?>">

    <!-- Custom JS file -->
    <script type="text/javascript" src="js/sportello.js?v=<?php echo $__software_version; ?>&d=desktop></script>
</body>

</html>