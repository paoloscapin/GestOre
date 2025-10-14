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
    <title>Sportelli</title>
    <?php

    require_once '../common/header-common.php';
    require_once '../common/style.php';
    require_once '../common/_include_bootstrap-toggle.php';
    require_once '../common/_include_bootstrap-select.php';
    require_once '../common/_include_flatpickr.php';

    ruoloRichiesto('studente', 'segreteria-didattica', 'dirigente');
    ?>


    <?php
    require_once '../common/header-studente-mobile.php';
    require_once '../common/connect.php';
    ?>
        <!-- bootbox notificator -->
    <script type="text/javascript"
        src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>
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


    <div class="container-fluid" style="margin-top:70px;"> <!-- margine adeguato navbar -->
        <div class="row">

            <!-- Titolo -->
            <div class="col-xs-12" style="padding:5px; font-weight:bold; text-align:center;">
                <span class="glyphicon glyphicon-blackboard"></span> Sportelli
            </div>

            <!-- Filtri: impilati su xs, affiancati su sm+ -->
            <div class="col-xs-12 col-sm-6 col-md-2" style="padding:5px;">
                <label for="categoria_filtro">Categoria</label>
                <select id="categoria_filtro" name="categoria_filtro" class="categoria_filtro selectpicker form-control"
                    data-style="btn-yellow4" data-live-search="true" data-noneSelectedText="seleziona...">
                    <?php echo $categoriaFiltroOptionList ?>
                </select>
            </div>

            <div class="col-xs-12 col-sm-6 col-md-2" style="padding:5px;">
                <label for="docente_filtro">Docente</label>
                <select id="docente_filtro" name="docente_filtro" class="docente_filtro selectpicker form-control"
                    data-style="btn-yellow4" data-live-search="true" data-noneSelectedText="seleziona...">
                    <?php echo $docenteFiltroOptionList ?>
                </select>
            </div>

            <div class="col-xs-12 col-sm-6 col-md-2" style="padding:5px;">
                <label for="materia_filtro">Materia</label>
                <select id="materia_filtro" name="materia_filtro" class="materia_filtro selectpicker form-control"
                    data-style="btn-yellow4" data-live-search="true" data-noneSelectedText="seleziona...">
                    <?php echo $materiaFiltroOptionList ?>
                </select>
            </div>

            <div class="col-xs-12 col-sm-6 col-md-2" style="padding:5px;">
                <label for="classe_filtro">Classe</label>
                <select id="classe_filtro" name="classe_filtro" class="classe_filtro selectpicker form-control"
                    data-style="btn-yellow4" data-live-search="true" data-noneSelectedText="seleziona...">
                    <?php echo $classeFiltroOptionList ?>
                </select>
            </div>

            <div class="col-xs-12 col-sm-12 col-md-4" style="padding:5px; text-align:center;">
                <label class="checkbox-inline"><input type="checkbox" checked id="soloNuoviCheckBox"> Solo Nuovi</label>
                <label class="checkbox-inline"><input type="checkbox" id="soloIscrittoCheckBox"> Iscritto</label>
                <label class="checkbox-inline"><input type="checkbox" id="ancheCancellatiCheckBox"> Cancellati</label>
            </div>


        </div>
    </div>



    <div class="card-body">
        <div class="records_content"></div>
    </div>

    <input type="hidden" id="hidden_unSoloArgomento"
        value="<?php echo getSettingsValue("sportelli", "unSoloArgomento", true) ? 1 : 0; ?>">

    <!-- Custom JS file -->
    <script type="text/javascript" src="js/sportello.js?v=<?php echo $__software_version; ?>&d=mobile"></script>
</body>

</html>