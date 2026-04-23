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

function studenteFrequentaCorsoSerale(): bool
{
    global $__studente_id, $__anno_scolastico_corrente_id;

    $row = dbGetFirst("
        SELECT c.id
        FROM studente_frequenta sf
        INNER JOIN classi c ON c.id = sf.id_classe
        WHERE sf.id_studente = " . dbI($__studente_id) . "
          AND sf.id_anno_scolastico = " . dbI($__anno_scolastico_corrente_id) . "
          AND FIND_IN_SET('30', REPLACE(COALESCE(c.gruppi_classe, ''), ' ', '')) > 0
        LIMIT 1
    ");

    return $row != null;
}

function categoriaSportelloExists(array $categorie, string $nome): bool
{
    foreach ($categorie as $categoria) {
        if (strcasecmp(trim((string)($categoria['nome'] ?? '')), $nome) === 0) {
            return true;
        }
    }
    return false;
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
$categorieSportello = dbGetAll("SELECT * FROM sportello_categoria ORDER BY sportello_categoria.nome ASC");
if (!is_array($categorieSportello)) $categorieSportello = [];

$default = studenteFrequentaCorsoSerale() ? "PreOra corsi serali" : "sportello didattico";
if (!categoriaSportelloExists($categorieSportello, $default)) {
    $default = "sportello didattico";
}

foreach ($categorieSportello as $categoria) {
    $selected = (strcasecmp(trim((string)$categoria['nome']), $default) === 0) ? ' selected' : '';
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
