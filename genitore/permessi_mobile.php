<?php
/**
 *  Versione MOBILE di GestOre - Permessi di uscita
 *  Autore: Massimo Saiani
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('genitore', 'segreteria-didattica', 'dirigente');

// Lista studenti del genitore
$studenti = dbGetAll("SELECT studente.id, studente.nome, studente.cognome
    FROM studente
    INNER JOIN genitori_studenti gs ON studente.id = gs.id_studente
    WHERE studetne.attivo = 1 AND gs.id_genitore = ".intval($__genitore_id)."
    ORDER BY studente.cognome, studente.nome ASC");

// Imposto il primo studente come selezionato di default
$studente_default_id = count($studenti) > 0 ? $studenti[0]['id'] : 0;
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Permessi di uscita</title>

    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    require_once '../common/header-genitore-mobile.php';
    ?>

    <script src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>
    <script src="<?php echo $__application_base_path; ?>/genitore/js/permessi.js?v=<?php echo $__software_version; ?>&d=mobile"></script>

    <style>
        /* Bottone flottante + */
        .fab {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            font-size: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 3px 6px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body style="padding-top:70px;">

    <!-- Titolo + selezione studente -->
    <div class="container-fluid" style="margin-bottom:10px;">
        <div class="row" style="display:flex; align-items:center; justify-content:space-between; padding:10px;">
            <div style="font-weight:bold; font-size:1.2em;">
                <span class="glyphicon glyphicon-log-out"></span> Permessi di uscita
            </div>
            <div>
                <select id="studente_filtro" class="form-control input-sm" style="min-width:150px;">
                    <?php
                    foreach($studenti as $studente){
                        $selected = ($studente['id'] == $studente_default_id) ? "selected" : "";
                        echo '<option value="'.$studente['id'].'" '.$selected.'>'.$studente['cognome'].' '.$studente['nome'].'</option>';
                    }
                    ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Lista permessi -->
    <div class="container-fluid">
        <div class="records_content"></div>
    </div>

    <!-- Campo hidden per memorizzare studente selezionato -->
    <input type="hidden" id="hidden_studente_id" value="<?php echo $studente_default_id; ?>">
    <input type="hidden" id="hidden_permesso_id">
    <input type="hidden" id="hidden_rientro">

    <!-- Modal - Add/Update Record -->
    <div class="modal fade" id="permesso_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="panel panel-lima4">
                        <div class="panel-heading">
                            <h5 class="modal-title text-center" id="myModalLabel">Permesso di uscita</h5>
                        </div>
                        <div class="panel-body">
                            <form class="form-horizontal">

                                <div class="form-group">
                                    <label class="col-xs-3 control-label" for="data">Data</label>
                                    <div class="col-xs-9">
                                        <input type="date" id="data" class="form-control" readonly />
                                        <small id="avvisoData" class="text-danger fw-bold" style="display:none;">
                                            ⚠️ Attenzione: la data del permesso sarà domani.
                                        </small>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="col-xs-3 control-label" for="ora_uscita">Ora uscita</label>
                                    <div class="col-xs-9">
                                        <input type="time" id="ora_uscita" class="form-control" step="60" placeholder="HH:MM" />
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="col-xs-3 control-label" for="motivo">Motivo</label>
                                    <div class="col-xs-9">
                                        <textarea id="motivo" placeholder="motivo" class="form-control" rows="3"></textarea>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="rientro" class="col-xs-3 control-label">Rientro</label>
                                    <div class="col-xs-9">
                                        <input type="checkbox" id="rientro">
                                    </div>
                                </div>

                                <div class="form-group" id="ora_rientro_group" style="display:none;">
                                    <label class="col-xs-3 control-label" for="ora_rientro">Ora rientro</label>
                                    <div class="col-xs-9">
                                        <input type="time" id="ora_rientro" class="form-control" step="60" placeholder="HH:MM" />
                                    </div>
                                </div>

                                <div class="form-group" id="_error-permesso-part">
                                    <strong>
                                        <hr>
                                        <div class="col-xs-12 text-danger" id="_error-permesso"></div>
                                    </strong>
                                </div>

                            </form>
                        </div>
                        <div class="panel-footer text-center">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                            <button id="btn-save" type="button" class="btn btn-primary" onclick="permessoSave()">Salva</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottone flottante + -->
    <button class="btn btn-orange4 fab" onclick="permessiGetDetails(-1)">
        <span class="glyphicon glyphicon-plus"></span>
    </button>

    <!-- JS gestione studente -->
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        // Mostro la lista permessi per lo studente selezionato
        permessiReadRecords();

        // Aggiorno lista quando cambia lo studente
        document.getElementById("studente_filtro").addEventListener("change", function() {
            document.getElementById("hidden_studente_id").value = this.value;
            permessiReadRecords();
        });
    });
    </script>

</body>
</html>
