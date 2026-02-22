<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/connectMBApp.php';

ruoloRichiesto('personale-ata', 'segreteria-ata', 'docente', 'dirigente');
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <title>Orario (MBApp)</title>
    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    require_once '../common/_include_bootstrap-notify.php';
    require_once '../common/_include_bootstrap-select.php';
    ?>
    <link rel="stylesheet" href="./css/orario.css">
</head>

<body>
    <?php
    // header area
    require_once '../common/header-segrata.php';
    ?>

    <div class="container-fluid">
        <div class="panel panel-teal4">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-md-2">
                        <span class="glyphicon glyphicon-calendar"></span>&ensp;Orario (MBApp)
                    </div>
                    <div class="col-md-10">
                        <div class="pull-right orario-toolbar">

                            <!-- select reali (nascosti) per compatibilità col JS -->
                            <select id="v_scope" class="selectpicker sr-only" data-width="150px" data-style="btn-default btn-sm">
                                <option value="AULA">AULA</option>
                                <option value="CLASSE">CLASSE</option>
                                <option value="DOCENTE">DOCENTE</option>
                            </select>

                            <select id="v_period" class="selectpicker sr-only" data-width="150px" data-style="btn-default btn-sm">
                                <option value="GIORNO">GIORNO</option>
                                <option value="SETTIMANA" selected>SETTIMANA</option>
                            </select>

                            <!-- Segmented: SCOPE -->
                            <div class="seg seg-scope" id="seg_scope" role="group" aria-label="Vista">
                                <button type="button" class="seg-btn" data-target="#v_scope" data-value="AULA">Aula</button>
                                <button type="button" class="seg-btn" data-target="#v_scope" data-value="CLASSE">Classe</button>
                                <button type="button" class="seg-btn" data-target="#v_scope" data-value="DOCENTE">Docente</button>
                            </div>

                            <!-- Segmented: PERIOD (lo nasconderai via JS in GIORNO+AULA) -->
                            <div class="seg seg-period" id="seg_period" role="group" aria-label="Periodo">
                                <button type="button" class="seg-btn" data-target="#v_period" data-value="GIORNO">Giorno</button>
                                <button type="button" class="seg-btn" data-target="#v_period" data-value="SETTIMANA">Settimana</button>
                            </div>

                            <!-- =====================================================
                                 NAV SETTIMANA (wrap)
                                 - visibile in modalità SETTIMANA
                                 ===================================================== -->
                            <div id="wrap_week" class="toolbar-item nav-group nav-week">
                                <button class="btn btn-default btn-sm" id="btn_prev_week" title="Settimana precedente">
                                    <span class="glyphicon glyphicon-chevron-left"></span>
                                </button>

                                <select id="v_week" class="selectpicker"
                                    data-width="230px" data-style="btn-default btn-sm"
                                    data-live-search="true" title="Vai alla settimana...">
                                </select>

                                <button class="btn btn-default btn-sm" id="btn_next_week" title="Settimana successiva">
                                    <span class="glyphicon glyphicon-chevron-right"></span>
                                </button>
                            </div>

                            <!-- =====================================================
                                 NAV GIORNO (wrap)
                                 - visibile in modalità GIORNO
                                 - in GIORNO+AULA resta visibile (coppia frecce + data)
                                 ===================================================== -->
                            <div id="wrap_date" class="toolbar-item nav-group nav-date">
                                <button class="btn btn-default btn-sm" id="btn_prev_day" title="Giorno precedente">
                                    <span class="glyphicon glyphicon-chevron-left"></span>
                                </button>

                                <input type="date" id="v_date" class="form-control input-sm" style="width:160px;">

                                <button class="btn btn-default btn-sm" id="btn_next_day" title="Giorno successivo">
                                    <span class="glyphicon glyphicon-chevron-right"></span>
                                </button>
                            </div>

                            <!-- =====================================================
                                 TARGET + NAV AULA (wrap)
                                 - sempre serve il target (aula/classe/docente)
                                 - le frecce aula le mostri SOLO in GIORNO+AULA (via JS)
                                 ===================================================== -->
                            <div id="wrap_target" class="toolbar-item nav-group nav-target">
                                <button class="btn btn-default btn-sm" id="btn_prev_aula" title="Aula precedente">
                                    <span class="glyphicon glyphicon-chevron-left"></span>
                                </button>

                                <select id="v_target" class="selectpicker"
                                    data-width="300px" data-style="btn-default btn-sm"
                                    data-live-search="true" title="Seleziona...">
                                    <option value="">Seleziona...</option>
                                </select>

                                <button class="btn btn-default btn-sm" id="btn_next_aula" title="Aula successiva">
                                    <span class="glyphicon glyphicon-chevron-right"></span>
                                </button>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <div class="panel-body">
                <div id="orario_title" style="margin-bottom:10px;font-weight:600; font-size:24px"></div>
                <div id="orario_content"></div>
            </div>
        </div>
    </div>

    <script src="js/scriptOrario.js"></script>
</body>

</html>
