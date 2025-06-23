<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-select.php';
require_once '../common/_include_bootstrap-notify.php';
ruoloRichiesto('docente', 'segreteria-didattica', 'dirigente');
	
if (!getSettingsValue('config','carenzeObiettiviMinimi', false))
{
    redirect("/error/unauthorized.php");
}

if (!getSettingsValue('carenzeObiettiviMinimi','visibile_docenti', false))
{
    ruoloRichiesto('segreteria-didattica');
}
?>

<!DOCTYPE html>
<html>

<head>
    <script type="text/javascript"
        src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
    <title>Carenze Studenti</title>

    <style>
        .icon-play {
            background-image: url('../img/pdf-256.png');
            background-size: cover;
            display: inline-block;
            height: 16px;
            width: 16px;
        }
    </style>
</head>

<?php

$modificheDisabilitate = "";

$id_docente_utente = 0;
if ($__utente_ruolo=='docente')
{
$query = "SELECT * from docente WHERE docente.username='".$__username."'";
$result = dbGetFirst($query);
if ($result != null)
{
    $id_docente_utente = $result['id'];
}
}

// prepara l'elenco delle materie per il filtro e per le materie del dialog
$materiaFiltroOptionList = '<option value="0">Tutte</option>';
$materiaOptionList = '<option value="0"></option>';
foreach (dbGetAll("SELECT * FROM materia ORDER BY materia.nome ASC ; ") as $materia) {
    $materiaFiltroOptionList .= '<option value="' . $materia['id'] . '" >' . $materia['nome'] . '</option> ';
    $materiaOptionList .= '<option value="' . $materia['id'] . '" >' . $materia['nome'] . '</option> ';
}

// classi 
$classiFiltroOptionList = '<option value="0">T</option>';
$classiOptionList = '<option value="0">selezionare classe</option>';
foreach (dbGetAll("SELECT * FROM classi WHERE attiva=1 ORDER BY classi.classe ASC ; ") as $classe) {
    $classiFiltroOptionList .= '<option value="' . $classe['id'] . '" >' . $classe['classe'] . '</option> ';
    $classiOptionList .= '<option value="' . $classe['id'] . '" >' . $classe['classe'] . '</option> ';
}

// anno 
$annoFiltroOptionList = '<option value="0">T</option>';
$annoOptionList = '<option value="0">selezionare anno</option>';
$annoFiltroOptionList .= '<option value="1">1</option> ';
$annoOptionList .= '<option value="1">Classi prime</option>';
$annoFiltroOptionList .= '<option value="2">2</option> ';
$annoOptionList .= '<option value="2">Classi seconde</option>';
$annoFiltroOptionList .= '<option value="3">3</option> ';
$annoOptionList .= '<option value="3">Classi terze/option>';
$annoFiltroOptionList .= '<option value="4">4</option> ';
$annoOptionList .= '<option value="4">Classi quarte</option>';
$annoFiltroOptionList .= '<option value="5">5</option> ';
$annoOptionList .= '<option value="5">Classi quinte</option>';


// prepara l'elenco dei docenti
$docentiFiltroOptionList = '<option value="0">Tutti</option>';
$docentiOptionList = '<option value="0"></option>';
foreach (dbGetAll("SELECT * FROM docente WHERE docente.attivo=1 ORDER BY docente.cognome ASC ; ") as $docente) {
    if (($docente['id'])==$id_docente_utente)
    {
        $docentiFiltroOptionList .= '<option value="' . $docente['id'] . '" selected>' . $docente['cognome'] . ' ' . $docente['nome'] . '</option> ';
        $docentiOptionList .= '<option value="' . $docente['id'] . '" selected>' . $docente['cognome'] . ' ' . $docente['nome'] . '</option> ';
    }
    else
    {
        $docentiFiltroOptionList .= '<option value="' . $docente['id'] . '" >' . $docente['cognome'] . ' ' . $docente['nome'] . '</option> ';
        $docentiOptionList .= '<option value="' . $docente['id'] . '" >' . $docente['cognome'] . ' ' . $docente['nome'] . '</option> ';
    }
}

// studenti
$studentiFiltroOptionList = '<option value="0">T</option>';
$studentiOptionList = '<option value="0">selezionare studente</option>';
foreach (dbGetAll("SELECT * FROM studente WHERE attivo=1 AND id_anno_scolastico=$__anno_scolastico_corrente_id ORDER BY studente.cognome, studente.nome ASC") as $studente) {
    $studentiFiltroOptionList .= '<option value="' . $studente['id'] . '" >' . $studente['cognome'] . ' ' . $studente['nome'] . ' - ' . $studente['classe'] . '</option> ';
    $studentiOptionList .= '<option value="' . $studente['id'] . '" >' . $studente['cognome'] . ' ' . $studente['nome'] . ' - ' . $studente['classe'] . '</option> ';
}

?>

<body>
    <?php
    if (haRuolo('segreteria didattica'))
    {
        require_once '../common/header-didattica.php';
    }
    else if (haRuolo('docente'))
    {
        require_once '../common/header-docente.php';
    }
    ?>
<style>
  .col-md-2-custom {
    width: 20%;
  }
  .col-md-1-custom {
    width: 10%;
  }
  .col-md-1-2-custom {
    width: 12%;
  }
  .col-md-1-5-custom {
    width: 15%;
  }
  .col-md-0-5-custom {
    width: 5%;
  }

</style>
    <div class="container-fluid" style="margin-top:60px">
        <div class="panel panel-lima4">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-md-1 text-center">
                        <span class="glyphicon glyphicon-list-alt"
                            style="margin:5px"></span><br><b>Elenco<br>Carenze</b>
                    </div>
                    <div class="col-md-1 text-center">
                        <label class="col-sm-12 control-label" for="classe">Classe</label>
                        <div class="text-center">
                            <div class="col-sm-12"><select id="classe_filtro" name="classe_filtro"
                                    class="classe_filtro selectpicker" data-style="btn-salmon"
                                    data-live-search="true" data-noneSelectedText="seleziona..."
                                    data-width="100%"><?php echo $classiFiltroOptionList ?></select></div>
                        </div>
                    </div>
                    <div class="col-md-1 text-center">
                        <label class="col-sm-8 control-label" for="anno">Anno</label>
                        <div class="text-center">
                            <div class="col-sm-8"><select id="anno_filtro" name="anno_filtro"
                                    class="anno_filtro selectpicker" data-style="btn-salmon"
                                    data-live-search="true" data-noneSelectedText="seleziona..."
                                    data-width="100%"><?php echo $annoFiltroOptionList ?></select></div>
                        </div>
                    </div>
                    <div class="col-md-3 text-center">
                        <label class="col-sm-12 control-label" for="materia">Materia</label>
                        <div class="text-center">
                            <div class="col-sm-12"><select id="materia_filtro" name="materia_filtro"
                                    class="mamteria_filtro selectpicker" data-style="btn-salmon"
                                    data-live-search="true" data-noneSelectedText="seleziona..."
                                    data-width="100%"><?php echo $materiaFiltroOptionList ?></select></div>
                        </div>
                    </div>
                    <?php
                    if (haRuolo('segreteria-didattica'))
                    {
                        echo '
                    <div class="col-md-2">
                        <div class="text-center">
                            <label class="col-sm-12 control-label" for="docente">Docente</label>
                            <div class="col-sm-12"><select id="docente_filtro" name="docente_filtro"
                                    class="docente_filtro selectpicker" data-style="btn-yellow4" data-live-search="true"
                                    data-noneSelectedText="seleziona..." data-width="100%">
                                    <?php echo $docentiFiltroOptionList ?>
                                </select></div>
                        </div>
                    </div>
                    ';
                    }
                    ?>
                    <div class="col-md-2">
                        <div class="text-center">
                            <label class="col-sm-12 control-label" for="materia">Studente</label>
                            <div class="col-sm-12"><select id="studente_filtro" name="studente_filtro"
                                    class="studente_filtro selectpicker" data-style="btn-yellow4" data-live-search="true"
                                    data-noneSelectedText="seleziona..." data-width="100%">
                                    <?php echo $studentiFiltroOptionList ?>
                                </select></div>
                        </div>
                    </div>

                    <?php
                    if ((haRuolo('dirigente')) || (haRuolo('segreteria-didattica'))) {
                        echo '
                    <div>
                        <div>

                            <div class="col-md-1 text-center">
                                <div class="text-center">
                                    <label class="control-label" for="materia">Aggiungi Carenza</label>
                                    <button class="btn btn-xs btn-lima4" onclick="carenzeGetDetails(-1)"><span
                                            style="font-size:30px" class="glyphicon glyphicon-plus"></span></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-auto text-center">
                        <label id="import_btn" class="btn btn-xs btn-lima4 btn-file"><span
                                class="glyphicon glyphicon-upload"></span>&emsp;Importa<input type="file"
                                id="file_select_id" style="display: none;"></label>
                    ';
                    }
                    ?>
                    <div class="col-md-auto text-center"><br>
                        <label id="export_btn" class="btn btn-xs btn-lima4 btn-file"><span
                                id="file_export_id" class="glyphicon glyphicon-download"></span>&emsp;Esporta</label>
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
                <div class="modal fade" id="carenza_modal" data-backdrop="static" tabindex="-1" role="dialog"
                    aria-labelledby="myModalLabel">
                    <div class="modal-dialog modal-lg" style="margin:auto;width:%30" role="document">
                        <div class="modal-content">
                            <div class="modal-body">
                                <div class="panel panel-orange4">
                                    <div class="panel-heading">
                                        <h3 class="modal-title" style="text-align:center" id="myModalLabel">Carenza Studente
                                        </h3>
                                    </div>
                                    <div class="panel-body">
                                        <form class="form-horizontal">

                                            <div class="form-group studente_selector">
                                                <label class="col-sm-2 control-label" style="text-align:center"
                                                    for="categoria">Studente</label>
                                                <div class="col-sm-10"><select id="studente" name="studente"
                                                        class="studente selectpicker" data-style="btn-yellow4"
                                                        data-live-search="true" data-noneSelectedText="seleziona..."
                                                        <?php echo $modificheDisabilitate ?> data-width="100%">
                                                        <?php echo $studentiFiltroOptionList ?>
                                                    </select></div>
                                            </div>

                                            <div class="form-group materia_selector">
                                                <label class="col-sm-2 control-label" style="text-align:center"
                                                    accesskey="" for="materia">Materia</label>
                                                <div class="col-sm-10"><select id="materia" name="materia"
                                                        class="materia selectpicker" data-style="btn-yellow4"
                                                        data-live-search="true" data-noneSelectedText="seleziona..."
                                                        <?php echo $modificheDisabilitate ?> data-width="100%">
                                                        <?php echo $materiaOptionList ?>
                                                    </select></div>
                                            </div>

                                            <div class="form-group" id="_error-carenza-part">
                                                <strong>
                                                    <div class="col-sm-3 text-right text-danger ">Attenzione</div>
                                                    <div class="col-sm-9" id="error-carenza"></div>
                                                </strong>
                                            </div>
                                            <input type="hidden" id="hidden_carenza_id">
                                        </form>
                                    </div>
                            </div>
                            <div class="panel-footer text-center">
                                <?php

                                if ((haRuolo('dirigente')) || (haRuolo('segreteria-didattica'))) 
                                {
                                    echo '
                                    <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                                    <button type="button" class="btn btn-primary" onclick="carenzaSave()">Salva</button>
                                    ';
                                }
                                else
                                if (haRuolo('docente')) 
                                {
                                    echo '
                                    <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>
                                    ';
                                }

                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- // Modal - Add/Update Record -->

    </div>

    <!-- Custom JS file -->
    <script type="text/javascript" src="js/carenze.js?v=<?php echo $__software_version; ?>"></script>
</body>

</html>