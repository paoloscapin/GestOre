<?php
/**
 * Permessi - Segreteria ATA
 */
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('dirigente', 'segreteria-ata');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Segreteria ATA - Permessi</title>
    <meta charset="UTF-8">
    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    require_once '../common/_include_bootstrap-notify.php';
    require_once '../common/_include_bootstrap-select.php';
    ?>
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">

    <style>
        /* Toolbar filtri: allineamento e spaziatura */
        .permessi-filters{
            display:flex;
            align-items:center;
            gap:8px;
            flex-wrap:wrap;
            justify-content:flex-end;
        }

        /* bootstrap-select tende a mettere width:100% -> lo blocchiamo */
        .permessi-filters .bootstrap-select{
            width:auto !important;
            min-width:unset !important;
        }

        /* input ricerca + bottone */
        .permessi-search{
            width:380px;
            max-width:100%;
        }

        /* altezza coerente bottoni/select (Bootstrap 3) */
        .permessi-filters .btn-sm,
        .permessi-filters .bootstrap-select > .dropdown-toggle{
            height:30px;
            padding:4px 10px;
            line-height:20px;
        }

        /* spazio per la freccia nei select bootstrap-select */
        .bootstrap-select > .dropdown-toggle{
            padding-right:34px !important; /* spazio testo → caret */
        }

        /* posizione corretta della freccia */
        .bootstrap-select > .dropdown-toggle .caret{
            right:10px;
            margin-top:-2px;
            position:absolute;
            top:50%;
        }

        /* ==========================
           DASHBOARD compatta + click
           ========================== */
        .dash-bar{
            display:flex;
            align-items:center;
            gap:8px;
            padding:6px 10px;
            border:1px solid rgba(0,0,0,.12);
            border-radius:6px;
            background:#fff;
            min-height:34px; /* compatto */
        }

        .dash-title{
            font-weight:600;
            margin-right:6px;
            white-space:nowrap;
        }

        .dash-item{
            display:inline-flex;
            align-items:center;
            gap:6px;
            padding:4px 8px;
            border-radius:16px;
            font-size:12px;
            line-height:1;
            border:1px solid rgba(0,0,0,.08);
            white-space:nowrap;
            cursor:pointer;           /* ✅ cliccabile */
            user-select:none;
        }

        .dash-item .badge{
            margin-left:2px;
            font-size:11px;
        }

        .dash-item:hover{
            filter:brightness(0.97);
        }

        .dash-item.active{
            box-shadow:0 0 0 2px rgba(0,0,0,0.10) inset; /* ✅ evidenza */
            transform:scale(1.03);
        }

        .dash-right{
            margin-left:auto;
            display:flex;
            align-items:center;
            gap:8px;
        }

        .dash-mini{
            font-family:monospace;
            font-size:12px;
            opacity:.85;
        }

        /* colori “soft” */
        .dash-inviato{  background:rgb(219, 248, 5); }
        .dash-approvato{background:rgb(4, 241, 95); }
        .dash-respinto{ background:rgba(217,83,79,.35); }
        .dash-annullato{background:rgba(240,173,78,.35); }
        .dash-bozza{    background:rgba(103, 155, 211, 0.83); }

        /* responsive */
        @media (max-width:768px){
            .dash-bar{ flex-wrap:wrap; }
            .dash-right{
                width:100%;
                margin-left:0;
                justify-content:flex-end;
            }
        }

        .trend-wrap{ display:inline-block; vertical-align:middle; margin:0 6px; white-space:nowrap; }
        .trend-dot{ font-size:18px; margin:0 1px; line-height:1; cursor:default; }
        .trend-low{ color:#9aa0a6; }
        .trend-mid{ color:#f0ad4e; }
        .trend-high{ color:#5cb85c; }
        .trend-labels{ color:#777; font-size:12px; vertical-align:middle; }
    </style>
</head>

<body>
<?php require_once '../common/header-segrata.php'; ?>

<div class="container-fluid">
    <div class="panel panel-teal4">
        <div class="panel-heading container-fluid">
            <div class="row">
                <div class="col-md-4">
                    <span class="glyphicon glyphicon-th-list"></span>&ensp;Permessi ATA (Segreteria)
                </div>

                <div class="col-md-8">
                    <div class="pull-right permessi-filters">

                        <select id="f_stato" class="selectpicker" data-width="160px" data-style="btn-default btn-sm">
                            <option value="" selected>Tutti gli stati</option>
                            <option value="INVIATO">INVIATO</option>
                            <option value="BOZZA">BOZZA</option>
                            <option value="APPROVATO">APPROVATO</option>
                            <option value="RESPINTO">RESPINTO</option>
                            <option value="ANNULLATO">ANNULLATO</option>
                        </select>

                        <select id="f_tipo" class="selectpicker" data-width="260px" data-style="btn-default btn-sm" data-live-search="true">
                            <option value="">Tutti i tipi</option>
                            <?php
                            foreach (dbGetAll("SELECT id,codice,descrizione FROM permesso_ata_tipo WHERE (valido IS NULL OR valido=1) ORDER BY codice") as $t) {
                                echo '<option value="'.intval($t['id']).'">'.htmlspecialchars($t['codice'].' - '.$t['descrizione']).'</option>';
                            }
                            ?>
                        </select>

                        <div class="input-group input-group-sm permessi-search">
                            <input type="text" id="f_search" class="form-control" placeholder="Cerca (cognome, nome, matricola, email)">
                            <span class="input-group-btn">
                                <button class="btn btn-default" id="btn_refresh" type="button">
                                    <span class="glyphicon glyphicon-refresh"></span>&ensp;Aggiorna
                                </button>
                            </span>
                        </div>

                    </div>
                </div>

            </div>
        </div>

        <div class="panel-body">
            <div class="row" style="margin-bottom:10px;">
                <div class="col-md-12 text-center" id="result_text"></div>
            </div>

            <div class="row" style="margin-bottom:8px;">
                <div class="col-md-12">
                    <div id="dash_bar" class="dash-bar">
                        <span class="dash-title">
                            <span class="glyphicon glyphicon-dashboard"></span>&ensp;Cruscotto
                        </span>

                        <!-- ✅ data-stato + classe dash-item già presente -->
                        <span class="dash-item dash-inviato"   id="d_inviato"   data-stato="INVIATO"><span class="glyphicon glyphicon-send"></span> INVIATI <span class="badge">0</span></span>
                        <span class="dash-item dash-approvato" id="d_approvato" data-stato="APPROVATO"><span class="glyphicon glyphicon-ok"></span> APPROVATI <span class="badge">0</span></span>
                        <span class="dash-item dash-respinto"  id="d_respinto"  data-stato="RESPINTO"><span class="glyphicon glyphicon-remove"></span> RESPINTI <span class="badge">0</span></span>
                        <span class="dash-item dash-annullato" id="d_annullato" data-stato="ANNULLATO"><span class="glyphicon glyphicon-ban-circle"></span> ANNULLATI <span class="badge">0</span></span>
                        <span class="dash-item dash-bozza"     id="d_bozza"     data-stato="BOZZA"><span class="glyphicon glyphicon-edit"></span> BOZZE <span class="badge">0</span></span>

                        <span class="dash-right">
                            <span class="dash-mini" id="d_trend" title="Trend ultimi mesi"></span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="records_content"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal gestione richiesta -->
    <div class="modal fade" id="permesso_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="permessoModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="panel panel-teal4">
                        <div class="panel-heading">
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            <h5 class="modal-title" id="permessoModalLabel">Gestione permesso</h5>
                        </div>

                        <div class="panel-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="well well-sm">
                                        <div><strong>Dipendente:</strong> <span id="d_nome"></span></div>
                                        <div><strong>Email:</strong> <span id="d_email"></span></div>
                                        <div><strong>Matricola:</strong> <span id="d_matricola"></span></div>
                                        <div><strong>Contratto:</strong> <span id="d_contratto"></span></div>
                                        <div><strong>Ruolo:</strong> <span id="d_ruolo"></span></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="well well-sm">
                                        <div><strong>Tipo:</strong> <span id="p_tipo"></span></div>
                                        <div><strong>Stato:</strong> <span id="p_stato"></span></div>
                                        <div><strong>Inviato il:</strong> <span id="p_created"></span></div>
                                        <div><strong>Ultimo aggiornamento:</strong> <span id="p_updated"></span></div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Note del richiedente</label>
                                <textarea class="form-control" rows="3" id="p_note_richiedente" readonly></textarea>
                            </div>

                            <div class="form-group">
                                <label>Note Segreteria</label>
                                <textarea class="form-control" rows="3" id="p_note_segreteria" placeholder="Note interne / motivazione esito..."></textarea>
                            </div>

                            <div class="form-group">
                                <label>Intervalli / righe</label>
                                <div id="righe_list"></div>
                            </div>

                            <div class="form-group">
                                <label>Stato</label>
                                <select id="p_stato_edit" class="form-control" style="width:auto;">
                                    <option value="INVIATO">INVIATO</option>
                                    <option value="APPROVATO">APPROVATO</option>
                                    <option value="RESPINTO">RESPINTO</option>
                                    <option value="ANNULLATO">ANNULLATO</option>
                                </select>
                            </div>

                            <input type="hidden" id="hidden_permesso_id" value="">
                        </div>

                        <div class="panel-footer text-center">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>
                            <button type="button" class="btn btn-primary" id="btn_save_permesso">
                                <span class="glyphicon glyphicon-floppy-disk"></span>&ensp;Salva
                            </button>
                            <button type="button" class="btn btn-success" id="btn_approve">
                                <span class="glyphicon glyphicon-ok"></span>&ensp;Approva
                            </button>
                            <button type="button" class="btn btn-danger" id="btn_reject">
                                <span class="glyphicon glyphicon-remove"></span>&ensp;Respingi
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript" src="js/scriptPermessi.js"></script>
</body>
</html>
