<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../common/checkSession.php';
require_once '../common/__Settings.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/connect.php';

ruoloRichiesto('dirigente', 'segreteria-ata');

$uffici = dbGetAll("
    SELECT id, nome
    FROM personale_ata_uffici
    WHERE (attivo IS NULL OR attivo = 1)
    ORDER BY ordine, nome
");
if (!is_array($uffici)) $uffici = [];

$profili = dbGetAll("
    SELECT id, nome, codice, richiede_ufficio
    FROM personale_ata_profili
    WHERE (attivo IS NULL OR attivo = 1)
    ORDER BY ordine, nome
");
if (!is_array($profili)) $profili = [];
?>
<!DOCTYPE html>
<html>

<head>
    <title>Segreteria ATA - Personale ATA</title>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />

    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green.css">
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/header-style.css">

    <style>
        .toolbar-card {
            background: #f8fbff;
            border: 1px solid #d9e8f5;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 15px;
        }

        .toolbar-card .form-group {
            margin-bottom: 8px;
        }

        .stats-box {
            background: #ffffff;
            border: 1px solid #d9e8f5;
            border-radius: 8px;
            padding: 10px 12px;
            text-align: center;
            margin-bottom: 10px;
            min-height: 76px;
        }

        .stats-box .stats-value {
            font-size: 24px;
            font-weight: bold;
            line-height: 1.1;
        }

        .stats-box .stats-label {
            color: #4c6275;
            font-size: 12px;
            text-transform: uppercase;
        }

        .modal-history-box {
            max-height: 280px;
            overflow-y: auto;
            border: 1px solid #e5e5e5;
            border-radius: 6px;
            padding: 10px;
            background: #fcfcfc;
        }

        .history-item {
            border-bottom: 1px solid #ececec;
            padding: 8px 0;
        }

        .history-item:last-child {
            border-bottom: 0;
        }

        .history-title {
            font-weight: 600;
        }

        .history-dates {
            color: #666;
            font-size: 12px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .empty-box {
            color: #777;
            font-style: italic;
        }

        .inline-muted {
            color: #777;
            font-size: 12px;
        }

        .modal-section-title {
            margin-top: 10px;
            margin-bottom: 10px;
            font-weight: 700;
            color: #2c4b63;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: .4px;
        }
    </style>
</head>

<body>
    <?php require_once '../common/header-segrata.php'; ?>

    <div class="container-fluid">
        <div class="panel panel-lightblue4">
            <div class="panel-heading container-fluid">
                <div class="row">
                    <div class="col-md-5">
                        <span class="glyphicon glyphicon-user"></span>&emsp;Elenco Personale ATA
                    </div>

                    <div class="col-md-4 text-center">
                        <label class="checkbox-inline">
                            <input type="checkbox" checked data-toggle="toggle" data-size="mini"
                                data-onstyle="primary" id="testCheckBox">Solo Attivi
                        </label>
                    </div>

                    <div class="col-md-3">
                        <div class="pull-right">
                            <button class="btn btn-xs btn-lightblue4" data-toggle="modal"
                                data-target="#add_new_record_modal">
                                <span class="glyphicon glyphicon-plus"></span> Nuovo dipendente
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel-body">
                <div class="toolbar-card">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="filter_text">Ricerca</label>
                                <input type="text" id="filter_text" class="form-control"
                                    placeholder="Cerca per cognome, nome, email..." />
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="filter_ufficio">Ufficio</label>
                                <select id="filter_ufficio" class="form-control">
                                    <option value="">Tutti gli uffici</option>
                                    <?php foreach ($uffici as $u): ?>
                                        <option value="<?php echo intval($u['id']); ?>">
                                            <?php echo htmlspecialchars($u['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="filter_profilo">Profilo</label>
                                <select id="filter_profilo" class="form-control">
                                    <option value="">Tutti i profili</option>
                                    <?php foreach ($profili as $p): ?>
                                        <option value="<?php echo intval($p['id']); ?>">
                                            <?php echo htmlspecialchars($p['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="filter_tipo_contratto">Tipo contratto</label>
                                <select id="filter_tipo_contratto" class="form-control">
                                    <option value="">Tutti</option>
                                    <option value="INDETERMINATO">INDETERMINATO</option>
                                    <option value="DETERMINATO ANNUALE">DETERMINATO ANNUALE</option>
                                    <option value="DETERMINATO BREVE">DETERMINATO BREVE</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <label>&nbsp;</label>
                            <div>
                                <button class="btn btn-default btn-block" type="button" onclick="personaleAtaResetFilters()">
                                    Reset
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row" style="margin-bottom:10px;">
                    <div class="col-md-3">
                        <div class="stats-box">
                            <div class="stats-value" id="stats_totale">0</div>
                            <div class="stats-label">Totale</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-box">
                            <div class="stats-value" id="stats_attivi">0</div>
                            <div class="stats-label">Attivi</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-box">
                            <div class="stats-value" id="stats_con_ufficio">0</div>
                            <div class="stats-label">Con ufficio</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-box">
                            <div class="stats-value" id="stats_con_profilo">0</div>
                            <div class="stats-label">Con profilo</div>
                        </div>
                    </div>
                </div>

                <div class="row" style="margin-bottom:10px;">
                    <div class="col-md-12 text-center" id="result_text"></div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="records_content"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal - Add -->
        <div class="modal fade" id="add_new_record_modal" data-backdrop="static" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="panel panel-lightblue4">
                            <div class="panel-heading">
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span></button>
                                <h5 class="modal-title">Nuovo Personale ATA</h5>
                            </div>

                            <div class="panel-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="nome">Nome</label>
                                            <input type="text" id="nome" class="form-control" />
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="cognome">Cognome</label>
                                            <input type="text" id="cognome" class="form-control" />
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="email">Email</label>
                                            <input type="text" id="email" class="form-control" />
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="username">Username</label>
                                            <input type="text" id="username" class="form-control" />
                                            <div class="inline-muted">Usato anche per lo storico assegnazioni.</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="matricola">Matricola</label>
                                            <input type="text" id="matricola" class="form-control" />
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="tipo_contratto">Tipo contratto</label>
                                            <select id="tipo_contratto" class="form-control">
                                                <option value="">-- Seleziona --</option>
                                                <option value="INDETERMINATO">INDETERMINATO</option>
                                                <option value="DETERMINATO ANNUALE">DETERMINATO ANNUALE</option>
                                                <option value="DETERMINATO BREVE">DETERMINATO BREVE</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="codice_fiscale">Codice Fiscale</label>
                                            <input type="text" id="codice_fiscale" class="form-control" />
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="ruolo">Ruolo</label>
                                            <input type="text" id="ruolo" class="form-control" readonly />
                                        </div>
                                    </div>
                                </div>

                                <div class="modal-section-title">Profilo e ufficio</div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="id_profilo">Profilo</label>
                                            <select id="id_profilo" class="form-control">
                                                <option value="">-- Nessun profilo --</option>
                                                <?php foreach ($profili as $p): ?>
                                                    <option value="<?php echo intval($p['id']); ?>">
                                                        <?php echo htmlspecialchars($p['nome']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="id_ufficio">Ufficio</label>
                                            <select id="id_ufficio" class="form-control">
                                                <option value="">-- Nessun ufficio --</option>
                                                <?php foreach ($uffici as $u): ?>
                                                    <option value="<?php echo intval($u['id']); ?>">
                                                        <?php echo htmlspecialchars($u['nome']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="inline-muted">Lo storico registra i passaggi di ufficio.</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="data_inizio_assegnazione">Data inizio assegnazione</label>
                                            <input type="date" id="data_inizio_assegnazione" class="form-control" value="<?php echo date('Y-m-d'); ?>" />
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="attivo">Attivo</label><br>
                                    <input type="checkbox" checked data-toggle="toggle" data-size="mini"
                                        data-onstyle="primary" id="attivo">
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                                <button type="button" class="btn btn-primary" onclick="personaleAtaAddRecord()">Salva</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal - Update -->
        <div class="modal fade" id="update_modal" data-backdrop="static" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="panel panel-lightblue4">
                            <div class="panel-heading">
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span></button>
                                <h5 class="modal-title">Scheda Personale ATA</h5>
                            </div>

                            <div class="panel-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="update_nome">Nome</label>
                                            <input type="text" id="update_nome" class="form-control" />
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="update_cognome">Cognome</label>
                                            <input type="text" id="update_cognome" class="form-control" />
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="update_email">Email</label>
                                            <input type="text" id="update_email" class="form-control" />
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="update_username">Username</label>
                                            <input type="text" id="update_username" class="form-control" />
                                            <div class="inline-muted">Se cambia, anche lo storico uffici viene riallineato.</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="update_matricola">Matricola</label>
                                            <input type="text" id="update_matricola" class="form-control" />
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="update_tipo_contratto">Tipo contratto</label>
                                            <select id="update_tipo_contratto" class="form-control">
                                                <option value="">-- Seleziona --</option>
                                                <option value="INDETERMINATO">INDETERMINATO</option>
                                                <option value="DETERMINATO ANNUALE">DETERMINATO ANNUALE</option>
                                                <option value="DETERMINATO BREVE">DETERMINATO BREVE</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="update_codice_fiscale">Codice Fiscale</label>
                                            <input type="text" id="update_codice_fiscale" class="form-control" />
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="update_ruolo">Ruolo</label>
                                            <input type="text" id="update_ruolo" class="form-control" readonly />
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="update_attivo">Attivo</label><br>
                                    <input type="checkbox" data-toggle="toggle" data-size="mini"
                                        data-onstyle="primary" id="update_attivo">
                                </div>

                                <div class="modal-section-title">Profilo e ufficio corrente</div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="update_id_profilo">Profilo</label>
                                            <select id="update_id_profilo" class="form-control">
                                                <option value="">-- Nessun profilo --</option>
                                                <?php foreach ($profili as $p): ?>
                                                    <option value="<?php echo intval($p['id']); ?>">
                                                        <?php echo htmlspecialchars($p['nome']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="update_id_ufficio">Ufficio</label>
                                            <select id="update_id_ufficio" class="form-control">
                                                <option value="">-- Nessun ufficio --</option>
                                                <?php foreach ($uffici as $u): ?>
                                                    <option value="<?php echo intval($u['id']); ?>">
                                                        <?php echo htmlspecialchars($u['nome']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="update_data_inizio_assegnazione">Data decorrenza cambio ufficio</label>
                                            <input type="date" id="update_data_inizio_assegnazione" class="form-control" value="<?php echo date('Y-m-d'); ?>" />
                                        </div>
                                    </div>
                                </div>

                                <div class="modal-section-title">Storico uffici</div>
                                <div id="storico_assegnazioni" class="modal-history-box">
                                    <div class="empty-box">Nessuno storico disponibile.</div>
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>
                                <button type="button" class="btn btn-primary" onclick="personaleAtaUpdateDetails()">Salva</button>
                                <input type="hidden" id="hidden_id">
                                <input type="hidden" id="current_assignment_id">
                                <input type="hidden" id="current_assignment_ufficio_id">
                                <input type="hidden" id="current_username_originale">
                                <input type="hidden" id="current_assignment_data_inizio">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <script>
        var PERSONALE_ATA_PROFILI = <?php echo json_encode($profili, JSON_UNESCAPED_UNICODE); ?>;
    </script>

    <script type="text/javascript" src="js/scriptPersonaleAta.js"></script>
</body>

</html>