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
?>

<!DOCTYPE html>
<html>

<head>
    <script type="text/javascript"
        src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
    <title>Programmi Svolti</title>

    <style>
        .icon-play {
            background-image: url('../img/pdf-256.png');
            background-size: cover;
            display: inline-block;
            height: 16px;
            width: 16px;
        }

        .toggle.btn {
            width: auto !important;
            min-width: 160px;
            /* regola a seconda della lunghezza del testo */
            padding: 0 10px;
            white-space: nowrap;
        }

        .toggle.btn .toggle-on {
            background-color: blue;
            padding-left: 10px;
            padding-right: 10px;
        }

        .toggle.btn .toggle-off {
            background-color: red;
            padding-left: 10px;
            padding-right: 10px;
        }

        #progressOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            /* Sfondo semi-trasparente */
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        #progressContent {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            text-align: center;
            width: 300px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
        }

        #progressBarContainer {
            background: #ddd;
            border-radius: 10px;
            overflow: hidden;
            height: 25px;
            margin-top: 10px;
        }

        #progressBar {
            background: green;
            width: 0%;
            height: 100%;
            color: white;
            text-align: center;
            line-height: 25px;
            transition: width 0.3s;
        }
    </style>
</head>

<?php
// if (((haRuolo('dirigente')) || (haRuolo('segreteria-didattica')))  || ((haRuolo('docente')) && (getSettingsValue('programmiMaterie', 'visibile_docenti', false)) && (getSettingsValue('programmiMaterie', 'docente_puo_modificare', false))) )
// {
//     $modificheDisabilitate = '';
// } else {
//     $modificheDisabilitate = ' disabled ';
// }

$id_docente_utente = 0;
if ($__utente_ruolo == 'docente') {
    $query = "SELECT * from docente WHERE docente.username='" . $__username . "'";
    $result = dbGetFirst($query);
    if ($result != null) {
        $id_docente_utente = $result['id'];
    }
}
// prepara l'elenco delle materie per il filtro e per le materie del dialog
$modificheDisabilitate = 'disabled';
$annoCorsoOptionList = "";
$indirizzoCorsoOptionList = "";
$materiaFiltroOptionList = '<option value="0">Tutte</option>';
$materiaOptionList = '<option value="0"></option>';
foreach (dbGetAll("SELECT * FROM materia ORDER BY materia.nome ASC ; ") as $materia) {
    $materiaFiltroOptionList .= '<option value="' . $materia['id'] . '" >' . $materia['nome'] . '</option> ';
    $materiaOptionList .= '<option value="' . $materia['id'] . '" >' . $materia['nome'] . '</option> ';
}

// anni
$anniFiltroOptionList = '<option value="0">Tutti</option>';
$anniOptionList      = '<option value="0">Selezionare anno</option>';

foreach (dbGetAll("SELECT * FROM anno_scolastico ORDER BY id DESC;") as $anno) {
    $selected = ($anno['id'] == $__anno_scolastico_corrente_id) ? ' selected' : '';
    $option   = '<option value="' . htmlspecialchars($anno['id']) . '"' . $selected . '>' . htmlspecialchars($anno['anno']) . '</option>';

    $anniFiltroOptionList .= $option;
    $anniOptionList      .= $option;
}

// classi 
$classiFiltroOptionList = '<option value="0">T</option>';
$classiOptionList = '<option value="0">selezionare classe</option>';
foreach (dbGetAll("SELECT * FROM classi WHERE attiva=1 ORDER BY classi.classe ASC ; ") as $classe) {
    $classiFiltroOptionList .= '<option value="' . $classe['id'] . '" >' . $classe['classe'] . '</option> ';
    $classiOptionList .= '<option value="' . $classe['id'] . '" >' . $classe['classe'] . '</option> ';
}

// prepara l'elenco dei docenti
$docentiFiltroOptionList = '<option value="0">Tutti</option>';
$docentiOptionList = '<option value="0"></option>';
foreach (dbGetAll("SELECT * FROM docente WHERE docente.attivo=1 ORDER BY docente.cognome ASC ; ") as $docente) {
    if (($docente['id']) == $id_docente_utente) {
        $docentiFiltroOptionList .= '<option value="' . $docente['id'] . '" selected>' . $docente['cognome'] . ' ' . $docente['nome'] . '</option> ';
        $docentiOptionList .= '<option value="' . $docente['id'] . '" selected>' . $docente['cognome'] . ' ' . $docente['nome'] . '</option> ';
    } else {
        $docentiFiltroOptionList .= '<option value="' . $docente['id'] . '" >' . $docente['cognome'] . ' ' . $docente['nome'] . '</option> ';
        $docentiOptionList .= '<option value="' . $docente['id'] . '" >' . $docente['cognome'] . ' ' . $docente['nome'] . '</option> ';
    }
}

?>

<body>
    <!-- OVERLAY con progress bar -->
    <div id="progressOverlay" style="display: none;">
        <div id="progressContent">
            <p>Invio email in corso...</p>
            <div id="progressBarContainer">
                <div id="progressBar">0%</div>
            </div>
        </div>
    </div>
    <?php
    if (haRuolo('segreteria-didattica')) {
        require_once '../common/header-didattica.php';
    } else
    if (haRuolo('docente')) {
        require_once '../common/header-docente.php';
    } else
    if (haRuolo('studente')) {
        require_once '../common/header-studente.php';
    }

    ?>
    <input type="hidden" id="hidden_docente_id" value="<?php echo $id_docente_utente ?>">
    <div class="container-fluid" style="margin-top:60px">
        <div class="panel panel-lima4">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-md-1 text-center">
                        <span class="glyphicon glyphicon-list-alt"
                            style="margin:5px"></span><br><b>Programmi<br>Svolti</b>
                    </div>
                    <div class="col-md-1 text-center">
                        <label class="col-sm-12 control-label" for="classi">Classe</label>
                        <div class="text-center">
                            <div class="col-sm-12"><select id="classi_filtro" name="classi_filtro"
                                    class="classi_filtro selectpicker" data-style="btn-salmon" data-live-search="true"
                                    data-noneSelectedText="seleziona..."
                                    data-width="100%"><?php echo $classiFiltroOptionList ?></select></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <label class="col-sm-12 control-label" for="materia">Materia</label>
                            <div class="col-sm-12"><select id="materia_filtro" name="materia_filtro"
                                    class="materia_filtro selectpicker" data-style="btn-yellow4" data-live-search="true"
                                    data-noneSelectedText="seleziona..." data-width="100%">
                                    <?php echo $materiaFiltroOptionList ?>
                                </select></div>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <div class="text-center">
                            <label class="col-sm-12 control-label" for="docente">Docente</label>
                            <div class="col-sm-12"><select id="docente_filtro" name="docente_filtro"
                                    class="docente_filtro selectpicker" data-style="btn-yellow4" data-live-search="true"
                                    data-noneSelectedText="seleziona..."
                                    <?php if (!(haRuolo("segreteria-didattica"))) echo ' disabled '; ?>
                                    data-width="100%">
                                    <?php echo $docentiFiltroOptionList ?>
                                </select></div>
                        </div>
                    </div>
                    <!-- <div class="col-md-1">
            <div class="text-center">
                <label class="checkbox-inline">
                <strong>
                    <input type="checkbox" data-toggle="toggle" data-size="mini" data-onstyle="primary" id="soloTemplateCheckBox" ><?php echoLabel('Template'); ?>
                </strong>
                </label>
            </div>
        </div>-->
                    <div>
                        <div>

                            <div class="col-md-2 text-right">
                                <div class="text-center">
                                    <?php 
                                                                        if (getSettingsValue('programmiSvolti', 'docente_puo_inserire', false) || (haRuolo('segreteria-didattica')) || (haRuolo('dirigente'))) 
                                                                        {
                                                                            echo '
                                                                        <label class="col-sm-12 control-label" for="materia">Aggiungi Programma</label>
                                                                        <button class="btn btn-xs btn-lima4" onclick="programmiSvoltiGetDetails(-1,&#39;false&#39;,&#39;false&#39;)"><span
                                                                                style="font-size:20px" class="glyphicon glyphicon-plus"></span></button>';
                                                                        } ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-2" style="margin:0;">
                        <div class="text-center">
                            <label class="col-sm-10 control-label" style="margin:0;" for="anni_filtro">Anno scolastico</label>
                            <div class="col-sm-10">
                                <select id="anni_filtro" style="margin:0;" name="anni_filtro"
                                    class="anni_filtro selectpicker"
                                    data-style="btn-yellow4"
                                    data-live-search="true"
                                    data-noneSelectedText="Seleziona..."
                                    data-width="60%">
                                    <?php echo $anniFiltroOptionList ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <?php
                    if ((haRuolo('dirigente')) || (haRuolo('segreteria-didattica'))) {
                        echo '                    
                                    <div class="col-md-auto text-center">
                                                                <label class="checkbox-inline">
                                                <input type="checkbox" data-toggle="toggle" data-size="mini" data-onstyle="primary"
                                                    id="daCompletareCheckBox" data-on="Tutti" data-off="Chi non ha completato">
                                            </label>
                                    </div>
                                    <div class="col-md-auto text-center">
                                        <label id="send_btn" class="btn btn-xs btn-lima4 btn-file" data-toggle="tooltip" title="Invia mail sollecito"><span
                                        class="glyphicon glyphicon-send" ></span>&emsp;Mail Sollecito</label></div>
                                    <div class="col-md-auto text-center"></div>
                                        ';
                    }
                    ?>

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
                <div class="modal fade" id="programma_modal" data-backdrop="static" tabindex="-1" role="dialog"
                    aria-labelledby="myModalLabel1">
                    <div class="modal-dialog modal-lg" style="margin:auto;width:%40" role="document">
                        <div class="modal-content">
                            <div class="modal-body">
                                <div class="panel panel-orange4">
                                    <div class="panel-heading">
                                        <h3 class="modal-title" style="text-align:center" id="myModalLabel1">Programma
                                            Svolto
                                        </h3>
                                    </div>
                                    <div class="panel-body">
                                        <form class="form-horizontal">

                                            <div class="form-group classe_selector">
                                                <label class="col-sm-2 control-label" style="text-align:center"
                                                    for="classe">Classe</label>
                                                <div class="col-sm-10"><select id="classe" name="classe"
                                                        class="classe selectpicker" data-style="btn-success"
                                                        data-live-search="true" data-noneSelectedText="seleziona..."
                                                        data-width="100%">
                                                        <?php echo $classiOptionList ?>
                                                    </select></div>
                                            </div>

                                            <div class="form-group docente_selector">
                                                <label class="col-sm-2 control-label" style="text-align:center"
                                                    for="docente">Docente</label>
                                                <div class="col-sm-10"><select id="docente" name="docente"
                                                        class="indirizzo selectpicker" data-style="btn-yellow4"
                                                        data-live-search="true" data-noneSelectedText="seleziona..."
                                                        data-width="100%">
                                                        <?php echo $docentiOptionList ?>
                                                    </select></div>
                                            </div>

                                            <div class="form-group materia_selector">
                                                <label class="col-sm-2 control-label" style="text-align:center"
                                                    accesskey="" for="materia">Materia</label>
                                                <div class="col-sm-10"><select id="materia" name="materia"
                                                        class="materia selectpicker" data-style="btn-yellow4"
                                                        data-live-search="true" data-noneSelectedText="seleziona..."
                                                        data-width="100%">
                                                        <?php echo $materiaOptionList ?>
                                                    </select></div>
                                            </div>

                                            <div class="form-group" id="_error-programma-part"><strong>

                                                    <div class="col-sm-3 text-right text-danger ">Attenzione</div>
                                                    <div class="col-sm-9" id="_error-programma"></div>
                                                </strong></div>

                                            <input type="hidden" id="hidden_programma_id">
                                            <input type="hidden" id="hidden_duplica">
                                            <input type="hidden" id="hidden_share">
                                        </form>

                                    </div>
                                    <div class="container-fluid"">
                                <div class=" panel panel-lima4">
                                        <div class="panel-body" style="padding:0px">
                                            <div class="row">
                                                <div class="col-md-2"></div>
                                                <div class="col-md-4">
                                                    <h3 style="text-align:center">Elenco Moduli
                                                        <?php
                                                        if (haRuolo('dirigente') || haRuolo('segreteria-didattica')) {
                                                            echo '
                                                        <button class="btn btn-xs btn-lima4"
                                                            onclick="moduloSvoltiGetDetails(-1)"><span style="font-size:14px"
                                                                class="glyphicon glyphicon-plus"></span></button>
                                                        ';
                                                        } else if (haRuolo('docente')) {
                                                            if (getSettingsValue('programmiSvolti', 'docente_puo_modificare', false)) {
                                                                echo '
                                                                <button class="btn btn-xs btn-lima4"
                                                                onclick="moduloSvoltiGetDetails(-1)"><span style="font-size:14px"
                                                                class="glyphicon glyphicon-plus"></span></button>
                                                        ';
                                                            }
                                                        }
                                                        ?>
                                                    </h3>
                                                </div>
                                                <div class="col-md-4">
                                                    <h3 style="text-align:center">Importa Moduli
                                                        <?php
                                                        if (haRuolo('dirigente') || haRuolo('segreteria-didattica')) {
                                                            echo '
                                                        <button class="btn btn-xs btn-lima4"
                                                            onclick="moduliSvoltiImport()"><span style="font-size:14px"
                                                                class="glyphicon glyphicon-cloud-upload"></span></button>
                                                        ';
                                                        } else if (haRuolo('docente')) {
                                                            if (getSettingsValue('programmiSvolti', 'docente_puo_modificare', false)) {
                                                                echo '
                                                                <button class="btn btn-xs btn-lima4"
                                                                onclick="moduliSvoltiImport()"><span style="font-size:14px"
                                                                class="glyphicon glyphicon-cloud-upload"></span></button>
                                                                ';
                                                            }
                                                        }
                                                        ?>
                                                    </h3>
                                                </div>
                                                <div class="col-md-2"></div>
                                                <div class="moduli_content"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="panel-footer text-center">
                                <?php
                                if (haRuolo('docente')) {
                                    if (getSettingsValue('programmiSvolti', 'docente_puo_modificare', false)) {
                                        echo '
                                        <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                                        <button type="button" class="btn btn-primary" onclick="programmiSvoltiSave()">Salva</button>
                                ';
                                    } else {
                                        echo '
                                        <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>
                                ';
                                    }
                                } else
                                if ((haRuolo('dirigente')) || (haRuolo('segreteria-didattica'))) {
                                    echo '
                                <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                                <button type="button" class="btn btn-primary" onclick="programmiSvoltiSave()">Salva</button>
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

        <!-- Modal - Add/Update Record -->
        <div class="modal fade" id="modulo_modal" data-backdrop="static" tabindex="-1" role="dialog"
            aria-labelledby="myModalLabel">
            <div class="modal-dialog modal-lg" style="margin:auto;width:%100" role="document">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="panel panel-orange4">
                            <div class="panel-heading">
                                <h3 class="modal-title" style="text-align:center" id="myModalLabel">Dati del modulo
                                </h3>
                            </div>
                            <div class="panel-body">
                                <form class="form-horizontal">

                                    <div class="form-group">
                                        <label class="col-sm-2 control-label" for="ordine">Ordine</label>
                                        <div class="col-sm-10"><input type="text" id="ordine" placeholder="ordine"
                                                class="form-control" data-toggle="tooltip" data-placement="top"
                                                title="Inserisci il numero del modulo" />
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-2 control-label" for="titolo">Titolo</label>
                                        <div class="col-sm-10"><input type="text" id="titolo" placeholder="titolo"
                                                class="form-control" data-toggle="tooltip" data-placement="top"
                                                title="Inserisci il titolo del modulo" /></div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-2 control-label" for="contenuto">Contenuto</label>
                                        <div class="col-sm-10"><textarea id="contenuto" rows="5" placeholder="contenuto"
                                                class="form-control" data-toggle="tooltip" data-placement="top"
                                                title="Inserisci il contenuto relativo a questo modulo"></textarea>
                                        </div>
                                    </div>

                                    <div class="form-group" id="_error-modulo-part"><strong>

                                            <div class="col-sm-3 text-right text-danger ">Attenzione</div>
                                            <div class="col-sm-9" id="_error-modulo"></div>
                                        </strong>
                                    </div>



                                    <input type="hidden" id="hidden_modulo_id">
                                </form>

                            </div>
                            <div class="panel-footer text-center">
                                <?php

                                if (haRuolo('segreteria-didattica')) {
                                    echo '
                                    <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                                    <button type="button" class="btn btn-primary" onclick="moduloSvoltiSave()">Salva</button>';
                                } else
                                    if (haRuolo('docente')) {
                                    if (getSettingsValue('programmiSvolti', 'visibile_docenti', false)) {
                                        if (getSettingsValue('programmiSvolti', 'docente_puo_modificare', false)) {
                                            echo '
                                                <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                                                <button type="button" class="btn btn-primary" onclick="moduloSvoltiSave()">Salva</button>';
                                        } else {
                                            echo '
                                                <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>';
                                        }
                                    }
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
    <script type="text/javascript" src="js/svolti.js?v=<?php echo $__software_version; ?>&a=<?php echo $__anno_scolastico_corrente_id; ?>"></script>
</body>

</html>