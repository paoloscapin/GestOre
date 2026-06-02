<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
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
    ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');
    ?>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/selfie_segmentation/selfie_segmentation.js"></script>
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
    <title>Studenti</title>
</head>

<body>
    <?php
    require_once '../common/header-didattica.php';
    require_once '../common/connect.php';
    require_once '../common/student_gender.php';
    gestoreEnsureStudenteSessoColumn();

    // prepara l'elenco per il filtro
    $classiOptionList = '<option value="0">scegli classe</option>';
    $classiFiltroOptionList = '<option value="0">Tutte</option>';
    foreach (dbGetAll("SELECT * FROM classi WHERE attiva = '1' ORDER BY classe ASC") as $classi) {
        $classiOptionList .= ' <option value="' . $classi['id'] . '" >' . $classi['classe'] . '</option> ';
        $classiFiltroOptionList .= ' <option value="' . $classi['id'] . '" >' . $classi['classe'] . '</option> ';
    }


    ?>

    <div class="container-fluid">
        <div class="panel panel-orange4">
            <div class="panel-heading">
                <div class="row" style="display:flex;align-items:center;">
                    <div class="col-md-2">
                        <span class="glyphicon glyphicon-pawn"></span>&ensp;Studenti
                    </div>
                    <div class="col-md-2">
                        <div class="text-right">
                            <label class="col-sm-2 control-label" for="classe"
                                style="margin:5px 0px 0px 0px;">Classe</label>
                            <div class="col-sm-auto"><select id="classe_filtro" name="classe_filtro"
                                    class="classe_filtro selectpicker" data-style="btn-yellow4" data-live-search="true"
                                    data-noneSelectedText="seleziona..." data-width="50%">
                                    <?php echo $classiFiltroOptionList ?>
                                </select></div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-addon"><span class="glyphicon glyphicon-search"></span></span>
                            <input type="text" id="records_text_filter" class="form-control" placeholder="Cerca">
                        </div>
                    </div>
                    <div class="col-md-2 text-center">
                        <label id="import_btn" class="btn btn-xs btn-lima4 btn-file" style="margin-bottom:0;"><span class="glyphicon glyphicon-upload"></span>&emsp;Importa<input type="file" id="file_select_id" style="display: none;"></label>
                    </div>
                    <div class="col-md-2">
                        <div class="text-center" style="margin:5px 0px 0px 0px;">
                            <label class="checkbox-inline">
                                <input type="checkbox" checked data-toggle="toggle" data-size="mini"
                                    data-onstyle="primary" id="soloAttiviCheckBox">Solo attivi
                            </label>
                        </div>
                    </div>
                    <div class="col-md-2 text-right">
                        <div class="pull-right">
                            <button class="btn btn-xs btn-orange4" onclick="studenteGetDetails(-1,<?php echo $__anno_scolastico_corrente_id ?>)"><span class="glyphicon glyphicon-plus"></span></button>
                        </div>
                    </div>
                </div>
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
        <div class="modal fade" id="studente_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
            <div class="modal-dialog modal-lg" style="width:760px" role="document">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="panel panel-lima4">
                            <div class="panel-heading">
                                <h5 class="modal-title" id="myModalLabel">Studente</h5>
                            </div>
                            <div class="panel-body">
                                <form class="form-horizontal">

                                    <div class="form-group" id="foto_mastercom_part" style="display:none;">
                                        <label class="col-sm-2 control-label">Foto</label>
                                        <div class="col-sm-10">
                                            <img id="foto_mastercom" src="" alt="Foto studente MasterCom" style="width:165px;height:220px;object-fit:contain;border-radius:6px;border:1px solid #aaa;background:#f7f7f7;box-shadow:0 1px 4px rgba(0,0,0,.18);">
                                            <div style="margin-top:6px;">
                                                <button type="button" class="btn btn-xs btn-info" onclick="studenteFotoApriCamera()">
                                                    <span class="glyphicon glyphicon-camera"></span> Scatta foto
                                                </button>
                                                <button type="button" class="btn btn-xs btn-danger" onclick="studenteFotoElimina()">
                                                    <span class="glyphicon glyphicon-trash"></span> Elimina foto
                                                </button>
                                            </div>
                                            <div id="foto_studente_msg" class="text-muted" style="margin-top:6px;"></div>
                                        </div>
                                    </div>

                                    <div class="form-group" id="foto_studente_camera_part" style="display:none;">
                                        <label class="col-sm-2 control-label">Webcam</label>
                                        <div class="col-sm-10">
                                            <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-start;">
                                                <div>
                                                    <div style="position:relative;width:320px;height:240px;">
                                                        <video id="foto_studente_video" autoplay playsinline muted style="width:320px;height:240px;background:#222;border-radius:4px;border:1px solid #999;object-fit:cover;"></video>
                                                        <div style="position:absolute;left:0;top:0;width:320px;height:240px;pointer-events:none;">
                                                            <div style="position:absolute;left:108px;top:30px;width:104px;height:128px;border:2px solid rgba(255,255,255,.9);border-radius:52px 52px 44px 44px;box-shadow:0 0 0 1px rgba(0,0,0,.35);"></div>
                                                            <div style="position:absolute;left:78px;top:92px;width:164px;border-top:2px dashed rgba(35,175,255,.95);box-shadow:0 1px 0 rgba(0,0,0,.35);"></div>
                                                        </div>
                                                    </div>
                                                    <div style="margin-top:6px;">
                                                        <button type="button" class="btn btn-xs btn-primary" onclick="studenteFotoScatta()">
                                                            <span class="glyphicon glyphicon-camera"></span> Scatta
                                                        </button>
                                                        <button type="button" class="btn btn-xs btn-default" onclick="studenteFotoChiudiCamera()">Chiudi webcam</button>
                                                    </div>
                                                </div>
                                                <div>
                                                    <canvas id="foto_studente_canvas" width="900" height="1200" style="width:180px;height:240px;background:#fff;border-radius:4px;border:1px solid #999;"></canvas>
                                                    <div style="margin-top:6px;">
                                                        <button type="button" class="btn btn-xs btn-success" id="foto_studente_salva_btn" onclick="studenteFotoSalva()" disabled>
                                                            <span class="glyphicon glyphicon-floppy-disk"></span> Salva foto
                                                        </button>
                                                        <button type="button" class="btn btn-xs btn-danger" onclick="studenteFotoElimina()">
                                                            <span class="glyphicon glyphicon-trash"></span> Elimina foto
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>


                                    <div class="form-group">
                                        <label class="col-sm-2 control-label" for="cognome">Cognome</label>
                                        <div class="col-sm-10"><input type="text" id="cognome" placeholder="cognome" class="form-control" /></div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-2 control-label" for="nome">Nome</label>
                                        <div class="col-sm-10"><input type="text" id="nome" placeholder="nome" class="form-control" /></div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-2 control-label" for="email">Email</label>
                                        <div class="col-sm-10"><input type="text" id="email" placeholder="email" class="form-control" /></div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-2 control-label" for="codice_fiscale">Codice Fiscale</label>
                                        <div class="col-sm-10"><input type="text" id="codice_fiscale" placeholder="codice_fiscale" class="form-control" /></div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-2 control-label" for="sesso">Sesso</label>
                                        <div class="col-sm-10">
                                            <select id="sesso" class="form-control">
                                                <option value="">Calcolato da codice fiscale</option>
                                                <option value="M">Maschio</option>
                                                <option value="F">Femmina</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-2 control-label" for="userId">UserID MasterCom</label>
                                        <div class="col-sm-10"><input type="text" id="userId" placeholder="userId" class="form-control" /></div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-2 control-label" for="classe"
                                            style="margin:5px 0px 0px 0px;">Classe attuale</label>
                                        <div class="col-sm-5"><select id="classe_filtro_stud" name="classe_filtro_stud"
                                                class="classe_filtro_stud selectpicker" data-style="btn-teal4" data-live-search="true"
                                                data-noneSelectedText="seleziona..." data-width="90%">
                                                <?php echo $classiOptionList ?>
                                            </select></div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-2 control-label" for="genitore_select">Genitore</label>

                                        <div class="col-sm-10">
                                            <!-- Riga 1: select -->
                                            <div class="row">
                                                <div class="col-xs-12">
                                                    <select id="genitore_select" name="genitore_select"
                                                        class="selectpicker"
                                                        data-style="btn-info"
                                                        data-live-search="true"
                                                        data-noneSelectedText="Seleziona genitore..."
                                                        data-width="100%">
                                                        <!-- Riempito via AJAX -->
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Riga 2: bottoni (a destra) -->
                                            <div class="row" style="margin-top:6px;">
                                                <div class="col-xs-12 text-right">

                                                    <button type="button" class="btn btn-xs btn-primary" id="btn-passa-genitore" style="margin-left:6px;">
                                                        <span class="glyphicon glyphicon-circle-arrow-right"></span> Passa a
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>



                                    <div class="form-group">
                                        <label for="attivo" class="col-sm-2 control-label">Attivo</label>
                                        <div class="col-sm-1">
                                            <input type="checkbox" id="attivo">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="esterno" class="col-sm-2 control-label">Esterno</label>
                                        <div class="col-sm-1">
                                            <input type="checkbox" id="esterno">
                                        </div>
                                    </div>
                                    <div class="form-group text-center" id="frequenta-part">
                                        <hr>
                                        <label for="frequenta_table">Ha frequentato</label>
                                        <div class="table-wrapper">
                                            <table class="table table-bordered table-striped" id="frequenta_table">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center">Anno scolastico</th>
                                                        <th class="text-center">Classe</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                </tbody>
                                            </table>
                                        </div>


                                        <div class="form-group" id="_error-classe-part"><strong>
                                                <hr>
                                                <div class="col-sm-3 text-right text-danger ">Attenzione</div>
                                                <div class="col-sm-9" id="_error-classe"></div>
                                            </strong></div>

                                        <input type="hidden" id="hidden_studente_id">
                                        <input type="hidden" id="hidden_attivo">
                                        <input type="hidden" id="hidden_anno_id">
                                </form>

                            </div>
                            <div class="panel-footer text-center">
                                <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                                <button id="btn-save" type="button" class="btn btn-primary" onclick="studenteSave()">Salva</button>
                                <button type="button" class="btn btn-info" id="btn-collega-genitore">
                                    <span class="glyphicon glyphicon-link"></span> Collega genitore
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- // Modal - Add/Update Record -->

        <div class="modal fade" id="collega_genitore_modal" data-backdrop="static" tabindex="-1" role="dialog">
            <div class="modal-dialog" style="width:520px" role="document">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="panel panel-info">
                            <div class="panel-heading">
                                <h5 class="modal-title">Collega genitore</h5>
                            </div>

                            <div class="panel-body">
                                <div class="form-horizontal">

                                    <div class="form-group">
                                        <label class="col-sm-3 control-label">Genitore</label>
                                        <div class="col-sm-9">
                                            <select id="genitore_select_link" class="selectpicker"
                                                data-live-search="true"
                                                data-noneSelectedText="Seleziona genitore..."
                                                data-width="100%"></select>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="col-sm-3 control-label">Relazione</label>
                                        <div class="col-sm-9">
                                            <select id="relazione_select_link" class="selectpicker"
                                                data-live-search="true"
                                                data-noneSelectedText="Seleziona relazione..."
                                                data-width="100%"></select>
                                        </div>
                                    </div>

                                    <div class="form-group" id="collega_genitore_error" style="display:none;">
                                        <div class="col-sm-12 text-danger text-center"></div>
                                    </div>

                                </div>
                            </div>

                            <div class="panel-footer text-center">
                                <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                                <button type="button" class="btn btn-primary" id="btn-conferma-collega-genitore">
                                    Collega
                                </button>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Custom JS file -->
<script>
  window.anno_id_corrente = <?php echo (int)$__anno_scolastico_corrente_id; ?>;
</script>
<script type="text/javascript" src="js/studente.js"></script>
</body>

</html>
