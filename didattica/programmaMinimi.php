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
    <title>Programmi Obiettivi Minimi</title>

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
if (((haRuolo('dirigente')) || (haRuolo('segreteria-didattica')))  || ((haRuolo('docente')) && (getSettingsValue('programmiMaterie', 'visibile_docenti', false)) && (getSettingsValue('programmiMaterie', 'docente_puo_modificare', false))) )
{
    $modificheDisabilitate = '';
} else {
    $modificheDisabilitate = ' disabled ';
}
// prepara l'elenco delle materie per il filtro e per le materie del dialog
$materiaFiltroOptionList = '<option value="0">Tutte</option>';
$materiaOptionList = '<option value="0"></option>';
foreach (dbGetAll("SELECT * FROM materia ORDER BY materia.nome ASC ; ") as $materia) {
    $materiaFiltroOptionList .= '<option value="' . $materia['id'] . '" >' . $materia['nome'] . '</option> ';
    $materiaOptionList .= '<option value="' . $materia['id'] . '" >' . $materia['nome'] . '</option> ';
}

// classi da 1 a 5
$annoCorsoFiltroOptionList = '<option value="0">T</option>';
$annoCorsoOptionList = '<option value="0">selezionare anno</option>';
for ($i = 1; $i <= 5; $i++) {
    $annoCorsoFiltroOptionList .= '<option value="' . $i . '" >' . $i . '</option> ';
    $annoCorsoOptionList .= '<option value="' . $i . '" >' . $i . '</option> ';
}

// prepara l'elenco degli indirizzi per il filtro e per gli indirizi del dialog
$indirizzoCorsoFiltroOptionList = '<option value="0">Tutti</option>';
$indirizzoCorsoOptionList = '<option value="0">selezionare indirizzo</option>';
foreach (dbGetAll("SELECT * FROM indirizzo ORDER BY indirizzo.nome_breve ASC ; ") as $indirizzo) {
    $indirizzoCorsoFiltroOptionList .= '<option value="' . $indirizzo['id'] . '" >' . $indirizzo['nome'] . '</option> ';
    $indirizzoCorsoOptionList .= '<option value="' . $indirizzo['id'] . '" >' . $indirizzo['nome'] . '</option> ';
}

?>

<body>
    <?php
    if (haRuolo('segreteria didattica'))
    {
        require_once '../common/header-didattica.php';
    }
    else
    if (haRuolo('docente'))
    {
        require_once '../common/header-docente.php';
    }
    else
    if (haRuolo('studente'))
    {
        require_once '../common/header-studente.php';
    }
    ?>

    <div class="container-fluid" style="margin-top:60px">
        <div class="panel panel-lima4">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-md-1 text-center">
                        <span class="glyphicon glyphicon-list-alt"
                            style="margin:5px"></span><br><b>Programma<br>Obiettivi Minimi</b>
                    </div>
                    <div class="col-md-1 text-center">
                        <label class="col-sm-8 control-label" for="annoCorso">Anno</label>
                        <div class="text-center">
                            <div class="col-sm-8"><select id="annoCorso_filtro" name="annoCorso_filtro"
                                    class="annoCorso_filtro selectpicker" data-style="btn-salmon"
                                    data-live-search="true" data-noneSelectedText="seleziona..."
                                    data-width="100%"><?php echo $annoCorsoFiltroOptionList ?></select></div>
                        </div>
                    </div>
                    <div class="col-md-2 text-center">
                        <label class="col-sm-12 control-label" for="indirizzoCorso">Indirizzo</label>
                        <div class="text-center">
                            <div class="col-sm-12"><select id="indirizzoCorso_filtro" name="indirizzoCorso_filtro"
                                    class="indirizzoCorso_filtro selectpicker" data-style="btn-salmon"
                                    data-live-search="true" data-noneSelectedText="seleziona..."
                                    data-width="100%"><?php echo $indirizzoCorsoFiltroOptionList ?></select></div>
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
                    <!-- <div class="col-md-1">
            <div class="text-center">
                <label class="checkbox-inline">
                <strong>
                    <input type="checkbox" data-toggle="toggle" data-size="mini" data-onstyle="primary" id="soloTemplateCheckBox" ><?php echoLabel('Template'); ?>
                </strong>
                </label>
            </div>
        </div>-->
                    <?php
                    if ((haRuolo('dirigente')) || (haRuolo('segreteria-didattica'))) {
                        echo '
                    <div>
                        <div>

                            <div class="col-md-2 text-right">
                                <div class="text-center">
                                    <label class="col-sm-12 control-label" for="materia">Aggiungi Programma</label>
                                    <button class="btn btn-xs btn-lima4" onclick="programmaGetDetails(-1)"><span
                                            style="font-size:20px" class="glyphicon glyphicon-plus"></span></button>
                                </div>
                            </div>
                        </div>
                    </div>
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
                    aria-labelledby="myModalLabel">
                    <div class="modal-dialog modal-lg" style="margin:auto;width:%40" role="document">
                        <div class="modal-content">
                            <div class="modal-body">
                                <div class="panel panel-orange4">
                                    <div class="panel-heading">
                                        <h3 class="modal-title" style="text-align:center" id="myModalLabel">Programma
                                            Materia
                                        </h3>
                                    </div>
                                    <div class="panel-body">
                                        <form class="form-horizontal">

                                            <div class="form-group anno_selector">
                                                <label class="col-sm-2 control-label" style="text-align:center"
                                                    for="docente">Anno</label>
                                                <div class="col-sm-10"><select id="anno" name="anno"
                                                        class="anno selectpicker" data-style="btn-success"
                                                        data-live-search="true" data-noneSelectedText="seleziona..."
                                                        <?php echo $modificheDisabilitate ?> data-width="100%">
                                                        <?php echo $annoCorsoOptionList ?>
                                                    </select></div>
                                            </div>

                                            <div class="form-group indirizzo_selector">
                                                <label class="col-sm-2 control-label" style="text-align:center"
                                                    for="categoria">Indirizzo</label>
                                                <div class="col-sm-10"><select id="indirizzo" name="indirizzo"
                                                        class="indirizzo selectpicker" data-style="btn-yellow4"
                                                        data-live-search="true" data-noneSelectedText="seleziona..."
                                                        <?php echo $modificheDisabilitate ?> data-width="100%">
                                                        <?php echo $indirizzoCorsoOptionList ?>
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

                                            <div class="form-group" id="_error-programma-part">
                                                <strong>

                                                    <div class="col-sm-3 text-right text-danger ">Attenzione</div>
                                                    <div class="col-sm-9" id="_error-programma"></div>
                                                </strong>
                                            </div>

                                            <input type="hidden" id="hidden_programma_id">

                                        </form>

                                    </div>
                                    <div class="container-fluid"">
                                <div class=" panel panel-lima4">
                                        <div class="panel-body" style="padding:0px">
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <h3 style="text-align:center">Elenco Moduli
                                                        <?php
                                                        if (haRuolo('dirigente') || haRuolo('segreteria-didattica')) {
                                                            echo '
                                                        <button class="btn btn-xs btn-lima4"
                                                            onclick="moduloGetDetails(-1)"><span style="font-size:14px"
                                                                class="glyphicon glyphicon-plus"></span></button>
                                                        ';
                                                        }
                                                        ?>
                                                    </h3>
                                                </div>
                                                <div class="moduli_content"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="panel-footer text-center">
                                <?php
                                if (haRuolo('docente')) {
                                    echo '
                                <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>
                                ';
                                }
                                if ((haRuolo('dirigente')) || (haRuolo('segreteria-didattica'))) {
                                    echo '
                                <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                                <button type="button" class="btn btn-primary" onclick="programmaSave()">Salva</button>
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
                                                class="form-control" data-toggle="tooltip" data-placement="top" <?php echo $modificheDisabilitate ?>
                                                title="Inserisci il numero del modulo" />
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-2 control-label" for="titolo">Titolo</label>
                                        <div class="col-sm-10"><input type="text" id="titolo" placeholder="titolo"
                                                class="form-control" data-toggle="tooltip" data-placement="top" <?php echo $modificheDisabilitate ?>
                                                title="Inserisci il titolo del modulo" /></div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-2 control-label" for="conoscenze">Conoscenze</label>
                                        <div class="col-sm-10"><textarea id="conoscenze" rows="5"
                                                placeholder="conoscenze" class="form-control" data-toggle="tooltip"
                                                data-placement="top" <?php echo $modificheDisabilitate ?>
                                                title="Inserisci le conoscenze relative a questo modulo"></textarea>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-2 control-label" for="abilita">Abilità</label>
                                        <div class="col-sm-10"><textarea id="abilita" rows="5" placeholder="abilita"
                                                class="form-control" data-toggle="tooltip" data-placement="top" <?php echo $modificheDisabilitate ?>
                                                title="Inserisci le abilità relative a questo modulo"></textarea></div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-2 control-label" for="periodo">Periodo</label>
                                        <div class="col-sm-10"><input type="text" id="periodo" placeholder="periodo"
                                                class="form-control" data-toggle="tooltip" data-placement="top" <?php echo $modificheDisabilitate ?>
                                                title="Inserisci il periodo di svolgimento del modulo" /></div>
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

                                if (haRuolo('segreteria-didattica'))
                                {
                                    echo '
                                    <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                                    <button type="button" class="btn btn-primary" onclick="moduloSave()">Salva</button>';		
                                }
                                else
                                if (haRuolo('docente')) 
                                {
                                    if (getSettingsValue('programmiMaterie', 'visibile_docenti', false)) 
                                    {
                                        if (getSettingsValue('programmiMaterie', 'docente_puo_modificare', false)) 
                                                                                {
                                                echo '
                                                <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                                                <button type="button" class="btn btn-primary" onclick="moduloSave()">Salva</button>';				
                                        } 
                                    else 
                                        {
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
    <script type="text/javascript" src="js/minimi.js?v=<?php echo $__software_version; ?>"></script>
</body>

</html>