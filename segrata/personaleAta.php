<?php
require_once '../common/checkSession.php';
require_once '../common/__Settings.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';

ruoloRichiesto('dirigente','segreteria-ata');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Segreteria ATA - Personale ATA</title>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />

    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green.css">
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/header-style.css">
</head>

<body>
<?php require_once '../common/header-segrata.php'; ?>

<div class="container-fluid">
    <div class="panel panel-lightblue4">
        <div class="panel-heading container-fluid">
            <div class="row">
                <div class="col-md-4">
                    <span class="glyphicon glyphicon-user"></span>&emsp;Elenco Personale ATA
                </div>

                <div class="col-md-4 text-center">
                    <label class="checkbox-inline">
                        <input type="checkbox" checked data-toggle="toggle" data-size="mini"
                               data-onstyle="primary" id="testCheckBox">Solo Attivi
                    </label>
                </div>

                <div class="col-md-4">
                    <div class="pull-right">
                        <button class="btn btn-xs btn-lightblue4" data-toggle="modal"
                                data-target="#add_new_record_modal">
                            <span class="glyphicon glyphicon-plus"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel-body">
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
                            <div class="form-group">
                                <label for="nome">Nome</label>
                                <input type="text" id="nome" class="form-control" />
                            </div>

                            <div class="form-group">
                                <label for="cognome">Cognome</label>
                                <input type="text" id="cognome" class="form-control" />
                            </div>

                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="text" id="email" class="form-control" />
                            </div>

                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" id="username" class="form-control" />
                            </div>

                            <div class="form-group">
                                <label for="matricola">Matricola</label>
                                <input type="text" id="matricola" class="form-control" />
                            </div>

                            <div class="form-group">
                                <label for="codice_fiscale">Codice Fiscale</label>
                                <input type="text" id="codice_fiscale" class="form-control" />
                            </div>

                            <div class="form-group">
                                <label for="ruolo">Ruolo</label>
                                <input type="text" id="ruolo" class="form-control" placeholder="es. Collaboratore scolastico, Assistente amm..." />
                            </div>

                            <div class="form-group">
                                <label for="attivo">Attivo</label>
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
                            <h5 class="modal-title">Modifica Personale ATA</h5>
                        </div>

                        <div class="panel-body">
                            <div class="form-group">
                                <label for="update_nome">Nome</label>
                                <input type="text" id="update_nome" class="form-control" />
                            </div>

                            <div class="form-group">
                                <label for="update_cognome">Cognome</label>
                                <input type="text" id="update_cognome" class="form-control" />
                            </div>

                            <div class="form-group">
                                <label for="update_email">Email</label>
                                <input type="text" id="update_email" class="form-control" />
                            </div>

                            <div class="form-group">
                                <label for="update_username">Username</label>
                                <input type="text" id="update_username" class="form-control" />
                            </div>

                            <div class="form-group">
                                <label for="update_matricola">Matricola</label>
                                <input type="text" id="update_matricola" class="form-control" />
                            </div>

                            <div class="form-group">
                                <label for="update_codice_fiscale">Codice Fiscale</label>
                                <input type="text" id="update_codice_fiscale" class="form-control" />
                            </div>

                            <div class="form-group">
                                <label for="update_ruolo">Ruolo</label>
                                <input type="text" id="update_ruolo" class="form-control" />
                            </div>

                            <div class="form-group">
                                <label for="update_attivo">Attivo</label>
                                <input type="checkbox" data-toggle="toggle" data-size="mini"
                                       data-onstyle="primary" id="update_attivo">
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                            <button type="button" class="btn btn-primary" onclick="personaleAtaUpdateDetails()">Salva</button>
                            <input type="hidden" id="hidden_id">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script type="text/javascript" src="js/scriptPersonaleAta.js"></script>
</body>
</html>
