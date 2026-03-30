<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('studente');

if (!getSettingsValue('config', 'carenzeObiettiviMinimi', false) ||
    !getSettingsValue('carenzeObiettiviMinimi', 'visibile_studenti', false)) {
    redirect("/error/unauthorized.php");
}

function eh($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$query = "SELECT COUNT(id) FROM carenze WHERE id_anno_scolastico = " . intval($__anno_scolastico_corrente_id);
$count = (int) dbGetValue($query);
$anno_carenze = ($count == 0) ? intval($__anno_scolastico_scorso_id) : intval($__anno_scolastico_corrente_id);

$anniFiltroOptionList = '<option value="0">Tutti</option>';
foreach (dbGetAll("SELECT * FROM anno_scolastico ORDER BY id DESC;") as $anno) {
    $selected = ((int)$anno['id'] === (int)$anno_carenze) ? ' selected' : '';
    $anniFiltroOptionList .= '<option value="' . (int)$anno['id'] . '"' . $selected . '>' . eh($anno['anno']) . '</option>';
}
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
    ?>

    <script type="text/javascript"
        src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        #carenze_mobile_container {
            padding: 0 5px;
        }

        #carenze_mobile_container .card {
            padding: 10px 10px !important;
            margin: 10px 0 !important;
            border-radius: 12px;
        }

        #carenze_mobile_container .card>div {
            margin: 4px 0;
        }

        #carenze_mobile_container .label {
            display: inline-block;
            margin: 2px 4px 2px 0;
        }

        .panel.panel-orange4 .panel-body {
            padding-top: 5px;
            padding-left: 5px !important;
            padding-right: 5px !important;
        }

        .filter-label {
            display: block;
            margin: 8px 0 4px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <?php require_once '../common/header-studente-mobile.php'; ?>

    <div class="container-fluid">
        <div class="panel panel-orange4">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-12" style="padding:10px">
                        <span class="glyphicon glyphicon-blackboard"></span>&ensp;Carenze
                    </div>

                    <div class="col-xs-12">
                        <label class="filter-label" for="anni_filtro">Anno scolastico</label>
                        <select id="anni_filtro" name="anni_filtro"
                            class="anni_filtro selectpicker"
                            data-style="btn-yellow4"
                            data-live-search="true"
                            data-noneSelectedText="seleziona..."
                            data-width="100%">
                            <?php echo $anniFiltroOptionList; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="panel-body">
                <div id="carenze_mobile_container" class="cards-container"></div>
            </div>
        </div>
    </div>

    <script type="text/javascript"
        src="js/carenze.js?v=<?php echo $__software_version; ?>&t=<?php echo time(); ?>&d=mobile&a=<?php echo (int)$anno_carenze; ?>">
    </script>
</body>
</html>