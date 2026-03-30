<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('studente');

if (!(getSettingsValue('config', 'sportelli', false))) {
    redirect("/error/unauthorized.php");
}
if (!(getSettingsValue('sportelli', 'visibile_studenti', false))) {
    redirect("/error/unauthorized.php");
}

function eh($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
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
    require_once '../common/header-studente-mobile.php';
    ?>
    <script type="text/javascript"
        src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>
</head>

<?php
$categoriaFiltroOptionList = '<option value="0">tutti</option>';
$default = "sportello didattico";
foreach (dbGetAll("SELECT * FROM sportello_categoria ORDER BY sportello_categoria.nome ASC") as $categoria) {
    $selected = ($categoria['nome'] == $default) ? ' selected' : '';
    $categoriaFiltroOptionList .= '<option value="' . (int)$categoria['id'] . '"' . $selected . '>' . eh($categoria['nome']) . '</option>';
}

$docenteFiltroOptionList = '<option value="0">tutti</option>';
foreach (dbGetAll("SELECT * FROM docente WHERE docente.attivo = true ORDER BY docente.cognome, docente.nome ASC") as $docente) {
    $docenteFiltroOptionList .= '<option value="' . (int)$docente['id'] . '">' . eh($docente['cognome'] . ' ' . $docente['nome']) . '</option>';
}

$materiaFiltroOptionList = '<option value="0">tutte</option>';
foreach (dbGetAll("SELECT * FROM materia ORDER BY materia.nome ASC") as $materia) {
    $materiaFiltroOptionList .= '<option value="' . (int)$materia['id'] . '">' . eh($materia['nome']) . '</option>';
}
?>

<body>
    <div class="container-fluid" style="margin-top:70px;">
        <div class="row">
            <div class="col-xs-12" style="padding:5px; font-weight:bold; text-align:center;">
                <span class="glyphicon glyphicon-blackboard"></span> Sportelli
            </div>

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

            <div class="col-xs-12 col-sm-12 col-md-4" style="padding:5px; text-align:center;">
                <label class="checkbox-inline">
                    <input type="checkbox" checked data-toggle="toggle" data-size="mini" data-onstyle="primary" id="soloNuoviCheckBox"> Solo Nuovi
                </label>
                <label class="checkbox-inline">
                    <input type="checkbox" data-toggle="toggle" data-size="mini" data-onstyle="primary" id="soloIscrittoCheckBox"> Iscritto
                </label>
                <label class="checkbox-inline">
                    <input type="checkbox" data-toggle="toggle" data-size="mini" data-onstyle="primary" id="ancheCancellatiCheckBox"> Cancellati
                </label>
            </div>
        </div>
    </div>

    <div class="card-body">
        <div class="records_content"></div>
    </div>

    <input type="hidden" id="hidden_unSoloArgomento"
        value="<?php echo getSettingsValue("sportelli", "unSoloArgomento", true) ? 1 : 0; ?>">

    <script type="text/javascript" src="js/sportello.js?v=<?php echo $__software_version; ?>&t=<?php echo time(); ?>&d=mobile"></script>
</body>
</html>