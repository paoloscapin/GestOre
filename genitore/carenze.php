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

    if ((!getSettingsValue('config', 'carenzeObiettiviMinimi', false)) || (!getSettingsValue('carenzeObiettiviMinimi', 'visibile_studenti', false))) {
        redirect("/error/unauthorized.php");
    }

    ?>

    <!-- bootbox notificator -->
    <script type="text/javascript"
        src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">

    <title>Carenze</title>
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

$studenti = dbGetAll("SELECT * FROM studente WHERE attivo=1 AND id IN (
    SELECT id_studente FROM genitori_studenti WHERE id_genitore = " . intval($__genitore_id) . "
)");
$firstId = null; // inizializziamo
foreach ($studenti as $studente) {
    if ($firstId === null) {
        $firstId = $studente['id'];
    }
    $studenteFiltroOptionList .= '<option value="' . $studente['id'] . '">'
        . $studente['cognome'] . ' ' . $studente['nome'] . '</option>';
}

$query = "SELECT COUNT(id) FROM carenze WHERE id_anno_scolastico=" . $__anno_scolastico_corrente_id;
$count = dbGetValue($query);
if ($count == 0) {
    $anno_carenze = $__anno_scolastico_scorso_id;
} else {
    $anno_carenze = $__anno_scolastico_corrente_id;
}
// anni
$anniFiltroOptionList = '<option value="0">Tutti</option>';
$anniOptionList      = '<option value="0">Selezionare anno</option>';

foreach (dbGetAll("SELECT * FROM anno_scolastico ORDER BY id DESC;") as $anno) {
    $selected = ($anno['id'] == $anno_carenze) ? ' selected' : '';
    $option   = '<option value="' . htmlspecialchars($anno['id']) . '"' . $selected . '>' . htmlspecialchars($anno['anno']) . '</option>';

    $anniFiltroOptionList .= $option;
    $anniOptionList      .= $option;
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
                    <div class="col-md-1" style="padding:10px">
                        <span class="glyphicon glyphicon-blackboard"></span>&ensp;Carenze
                    </div>
                    <div class="col-md-4">
                    </div>
                    <div class="col-md-2">
                    </div>
                    <div class="col-md-2" style="padding:0px">
                        <div class="text-center">
                            <label class="col-sm-2 control-label" for="studente"
                                style="margin:10px 0px 0px 0px; text-align:right">Studente</label>
                            <div class="col-sm-10" style="padding:0px;text-align:right"><select id="studente_filtro" name="studente_filtro"
                                    class="studente_filtro selectpicker" data-style="btn-yellow4" data-live-search="true"
                                    data-noneSelectedText="seleziona..." data-width="85%">
                                    <?php echo $studenteFiltroOptionList ?>
                                </select></div>
                        </div>
                    </div>
                    <div class="col-md-3" style="padding:0px">
                        <div class="text-center">
                            <label class="col-sm-4 control-label" for="anni"
                                style="margin:10px 0px 0px 0px; text-align:right">Anno Scolastico</label>
                            <div class="col-sm-4" style="padding:0px;text-align:right"><select id="anni_filtro" name="anni_filtro"
                                    class="anni_filtro selectpicker" data-style="btn-yellow4" data-live-search="true"
                                    data-noneSelectedText="seleziona..." data-width="85%">
                                    <?php echo $anniFiltroOptionList ?>
                                </select></div>
                        </div>
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

    <!-- Custom JS file -->
    <script type="text/javascript" src="js/carenze.js?v=<?php echo $__software_version; ?>&d=desktop&id=<?php echo $firstId ?>&a=<?php echo $anno_carenze; ?>"></script>
</body>

</html>