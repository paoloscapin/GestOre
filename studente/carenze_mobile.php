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

	if((!getSettingsValue('config','carenzeObiettiviMinimi', false))||(!getSettingsValue('carenzeObiettiviMinimi','visibile_studenti', false)))
    {
      redirect("/error/unauthorized.php");  
    }
    ?>

    <!-- bootbox notificator -->
    <script type="text/javascript"
        src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css"> 

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
                    <div class="col-md-12">
                        <div class="records_content"></div>
                    </div>
                </div>
            </div>

            <!-- <div class="panel-footer"></div> -->
        </div>

    </div>
  
    <!-- Custom JS file -->
    <script type="text/javascript" src="js/carenze.js?v=<?php echo $__software_version; ?>&d=mobile"></script>
</body>

</html>