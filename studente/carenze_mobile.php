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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Carenze</title>

    <?php

    require_once '../common/header-common.php';
    require_once '../common/style.php';
    require_once '../common/_include_bootstrap-toggle.php';
    require_once '../common/_include_bootstrap-select.php';
    require_once '../common/_include_flatpickr.php';
    ruoloRichiesto('studente', 'segreteria-didattica', 'dirigente');

    if ($__studente_cognome == "Iscrizioni") {
        // lo lascio accedere
    } else  
    if ((!getSettingsValue('config', 'carenzeObiettiviMinimi', false)) || (!getSettingsValue('carenzeObiettiviMinimi', 'visibile_studenti', false))) {
        redirect("/error/unauthorized.php");
    }

    $query = "SELECT COUNT(id) FROM carenze WHERE id_anno_scolastico=" . $__anno_scolastico_corrente_id;
    $count = dbGetValue($query);
    if ($count == 0) {
        $anno_carenze = $__anno_scolastico_scorso_id;
    } else {
        $anno_carenze = $__anno_scolastico_corrente_id;
    }
    ?>

    <!-- bootbox notificator -->
    <script type="text/javascript"
        src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <?php
    require_once '../common/header-studente-mobile.php';
    require_once '../common/connect.php';

    ?>

    <div class="container-fluid" style="margin-top:60px">
        <div class="panel panel-orange4">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-md-1" style="padding:10px">
                        <span class="glyphicon glyphicon-blackboard"></span>&ensp;Carenze
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
                    <div class="col-12">
                        <div id="carenze_mobile_container" class="cards-container">
                            <!-- Qui PHP inserirà le cards -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- <div class="panel-footer"></div> -->
        </div>

    </div>

    <!-- Custom JS file -->
    <script type="text/javascript" src="js/carenze.js?v=<?php echo time(); ?>&d=mobile&a=<?php echo $anno_carenze; ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/eruda"></script>
    <script>
        eruda.init();
    </script>
</body>

</html>