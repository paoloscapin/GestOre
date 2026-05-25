<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-select.php';
require_once '../common/_include_bootstrap-notify.php';

ruoloRichiesto('esterno', 'docente', 'segreteria-didattica', 'dirigente');

$anno_geometri = intval($__anno_scolastico_corrente_id);

$anniOptionList = '';
foreach (dbGetAll("SELECT * FROM anno_scolastico ORDER BY id DESC") as $anno) {
    $selected = (intval($anno['id']) === $anno_geometri) ? ' selected' : '';
    $anniOptionList .= '<option value="' . intval($anno['id']) . '"' . $selected . '>' . htmlspecialchars($anno['anno']) . '</option>';
}

$esamiOptionList = '<option value="0">Seleziona esame</option>';
foreach (dbGetAll("SELECT * FROM geometri_esami WHERE attivo=1 ORDER BY anno_corso ASC, ordine ASC, titolo ASC") as $esame) {
    $label = 'Classe ' . intval($esame['anno_corso']) . ' - ' . $esame['titolo'];
    $esamiOptionList .= '<option value="' . intval($esame['id']) . '">' . htmlspecialchars($label) . '</option>';
}

$classiOptionList = '';
$classiSql = "
    SELECT c.id, c.classe, c.anno, i1.nome_breve AS ind1, i2.nome_breve AS ind2
    FROM classi c
    LEFT JOIN indirizzo i1 ON i1.id = c.id_primo_indirizzo
    LEFT JOIN indirizzo i2 ON i2.id = c.id_secondo_indirizzo
    WHERE COALESCE(c.attiva, 1) = 1
      AND (
        c.anno IN (3,4,5)
        OR c.classe LIKE '3%'
        OR c.classe LIKE '4%'
        OR c.classe LIKE '5%'
      )
      AND (
        UPPER(COALESCE(i1.nome_breve,'')) = 'CAT'
        OR UPPER(COALESCE(i2.nome_breve,'')) = 'CAT'
        OR UPPER(COALESCE(i1.nome,'')) LIKE '%COSTRU%'
        OR UPPER(COALESCE(i2.nome,'')) LIKE '%COSTRU%'
        OR UPPER(c.classe) LIKE '%CAT%'
      )
    ORDER BY c.classe ASC
";
foreach (dbGetAll($classiSql) as $classe) {
    $indirizzo = trim(($classe['ind1'] ?? '') . (($classe['ind2'] ?? '') ? '/' . $classe['ind2'] : ''));
    $label = $classe['classe'] . ($indirizzo ? ' - ' . $indirizzo : '');
    $classiOptionList .= '<option value="' . intval($classe['id']) . '">' . htmlspecialchars($label) . '</option>';
}

$docentiOptionList = '';
foreach (dbGetAll("SELECT id, cognome, nome FROM docente WHERE attivo=1 ORDER BY cognome ASC, nome ASC") as $docente) {
    $docentiOptionList .= '<option value="' . intval($docente['id']) . '">' . htmlspecialchars($docente['cognome'] . ' ' . $docente['nome']) . '</option>';
}

$esterniOptionList = '';
foreach (dbGetAll("SELECT id, cognome, nome, username FROM utente WHERE ruolo='esterno' ORDER BY cognome ASC, nome ASC, username ASC") as $utente) {
    $nome = trim(($utente['cognome'] ?? '') . ' ' . ($utente['nome'] ?? ''));
    if ($nome === '') $nome = $utente['username'];
    $esterniOptionList .= '<option value="' . intval($utente['id']) . '">' . htmlspecialchars($nome) . '</option>';
}

$ruolo_eff = $__utente_ruolo ?? '';
if (impersonaRuolo('docente')) $ruolo_eff = 'docente';
if (impersonaRuolo('esterno')) $ruolo_eff = 'esterno';
?>

<!DOCTYPE html>
<html>

<head>
    <title>Esami CAT Geometri</title>
    <script type="text/javascript"
        src="<?php echo $__application_base_path; ?>/common/bootbox-4.4.0/js/bootbox.min.js"></script>
    <link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
    <style>
        .geometri-toolbar {
            padding: 14px 10px;
        }

        .geometri-toolbar .control-label {
            display: block;
            text-align: center;
        }

        .geometri-actions .btn {
            margin: 0 2px 3px 0;
        }

        .geometri-modal .modal-dialog {
            width: 900px;
            max-width: 96%;
        }

        #geometri_esiti_modal .modal-dialog {
            width: 1100px;
            max-width: 98%;
        }

        .geometri-esiti-table th,
        .geometri-esiti-table td {
            vertical-align: middle;
        }

        #geometri_esiti_tabs {
            margin-bottom: 10px;
        }

        #toastMessage {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            padding: 12px 20px;
            color: white;
            border-radius: 5px;
            display: none;
            z-index: 9999;
            text-align: center;
            white-space: nowrap;
        }
    </style>
</head>

<body>
    <?php
    if ($ruolo_eff === 'esterno') {
        require_once '../common/header-esterno.php';
    } elseif ($ruolo_eff === 'docente') {
        require_once '../common/header-docente.php';
    } else {
        require_once '../common/header-didattica.php';
    }
    ?>

    <div class="container-fluid">
        <div class="panel panel-orange4">
            <div class="panel-heading geometri-toolbar">
                <div class="row">
                    <div class="col-md-2 text-center">
                        <span class="glyphicon glyphicon-education" style="margin:5px"></span><br>
                        <b>Esami<br>CAT Geometri</b>
                    </div>
                    <div class="col-md-3">
                        <label class="control-label" for="anno_filtro">Anno scolastico</label>
                        <select id="anno_filtro" class="selectpicker" data-style="btn-yellow4" data-width="100%">
                            <?php echo $anniOptionList; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="control-label" for="esame_filtro">Esame</label>
                        <select id="esame_filtro" class="selectpicker" data-style="btn-salmon" data-live-search="true" data-width="100%">
                            <option value="0">Tutti</option>
                            <?php echo str_replace('<option value="0">Seleziona esame</option>', '', $esamiOptionList); ?>
                        </select>
                    </div>
                    <?php if (haRuolo('segreteria-didattica') || haRuolo('dirigente')) { ?>
                        <div class="col-md-2 text-center" style="padding-top:22px;">
                            <button class="btn btn-xs btn-lima4" onclick="geometriGetDetails(-1)" data-toggle="tooltip" title="Crea sessione esame">
                                <span class="glyphicon glyphicon-plus"></span>&emsp;Sessione
                            </button>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <div class="panel-body">
                <ul class="nav nav-tabs" role="tablist">
                    <li role="presentation" class="active"><a href="#tab-sessioni" aria-controls="tab-sessioni" role="tab" data-toggle="tab">Sessioni</a></li>
                    <?php if (haRuolo('segreteria-didattica') || haRuolo('dirigente')) { ?>
                        <li role="presentation"><a href="#tab-catalogo" aria-controls="tab-catalogo" role="tab" data-toggle="tab">Catalogo esami</a></li>
                        <li role="presentation"><a href="#tab-libretti" aria-controls="tab-libretti" role="tab" data-toggle="tab">Libretti formativi</a></li>
                    <?php } ?>
                </ul>

                <div class="tab-content" style="padding-top:12px;">
                    <div role="tabpanel" class="tab-pane active" id="tab-sessioni">
                        <div class="records_content"></div>
                    </div>
                    <?php if (haRuolo('segreteria-didattica') || haRuolo('dirigente')) { ?>
                        <div role="tabpanel" class="tab-pane" id="tab-catalogo">
                            <div class="text-right" style="margin-bottom:10px;">
                                <button class="btn btn-xs btn-lima4" onclick="geometriEsameGetDetails(-1)">
                                    <span class="glyphicon glyphicon-plus"></span>&emsp;Esame
                                </button>
                            </div>
                            <div class="catalogo_content"></div>
                        </div>
                        <div role="tabpanel" class="tab-pane" id="tab-libretti">
                            <div class="row" style="margin-bottom:12px;">
                                <div class="col-md-6">
                                    <label for="libretto_studente">Studente</label>
                                    <select id="libretto_studente" class="selectpicker form-control" data-live-search="true" data-width="100%"></select>
                                </div>
                                <div class="col-md-3" style="padding-top:24px;">
                                    <button class="btn btn-xs btn-lima4" onclick="geometriLibrettoPrint()">
                                        <span class="glyphicon glyphicon-print"></span>&emsp;Stampa libretto
                                    </button>
                                </div>
                            </div>
                            <div class="alert alert-info">
                                Il libretto viene generato in formato A4 orizzontale con due facciate A5 per pagina.
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade geometri-modal" id="geometri_modal" tabindex="-1" role="dialog" data-backdrop="static">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header modal-header-orange4">
                    <h4 class="modal-title text-center">Sessione esame CAT/Geometri</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="sessione_id" value="-1">

                    <div class="row">
                        <div class="col-md-6">
                            <label>Esame</label>
                            <select id="id_esame" class="selectpicker form-control" data-live-search="true" data-width="100%">
                                <?php echo $esamiOptionList; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Data</label>
                            <input type="datetime-local" id="data_inizio" class="form-control">
                        </div>
                    </div>

                    <div class="row" style="margin-top:10px;">
                        <div class="col-md-4">
                            <label>Stato</label>
                            <select id="stato" class="form-control">
                                <option value="bozza">Bozza</option>
                                <option value="programmata">Programmata</option>
                                <option value="chiusa">Chiusa</option>
                            </select>
                        </div>
                    </div>

                    <div class="row" style="margin-top:10px;">
                        <div class="col-md-12">
                            <label>Classi CAT coinvolte</label>
                            <select id="classi" class="selectpicker form-control" multiple data-live-search="true" data-width="100%">
                                <?php echo $classiOptionList; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row" style="margin-top:10px;">
                        <div class="col-md-12">
                            <label>Studenti recupero / sessione ad hoc</label>
                            <select id="studenti_recupero" class="selectpicker form-control" multiple data-live-search="true" data-width="100%"></select>
                        </div>
                    </div>

                    <div class="row" style="margin-top:10px;">
                        <div class="col-md-6">
                            <label>Docenti interni abilitati agli esiti</label>
                            <select id="docenti" class="selectpicker form-control" multiple data-live-search="true" data-width="100%">
                                <?php echo $docentiOptionList; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label>Utenti esterni abilitati agli esiti</label>
                            <select id="esterni" class="selectpicker form-control" multiple data-live-search="true" data-width="100%">
                                <?php echo $esterniOptionList; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row" style="margin-top:10px;">
                        <div class="col-md-12">
                            <label>Note</label>
                            <textarea id="note" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-default" data-dismiss="modal">Chiudi</button>
                    <?php if (haRuolo('segreteria-didattica') || haRuolo('dirigente')) { ?>
                        <button class="btn btn-primary" onclick="geometriSave()">Salva</button>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade geometri-modal" id="geometri_esiti_modal" tabindex="-1" role="dialog" data-backdrop="static">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header modal-header-orange4">
                    <h4 class="modal-title text-center">Esiti sessione esame CAT/Geometri</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="esiti_sessione_id" value="-1">
                    <div id="geometri_esiti_info" class="alert alert-info" style="margin-bottom:12px;"></div>
                    <ul class="nav nav-tabs" id="geometri_esiti_tabs" role="tablist"></ul>
                    <div class="tab-content" id="geometri_esiti_tab_content"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-default" data-dismiss="modal">Chiudi</button>
                    <button class="btn btn-primary" onclick="geometriEsitiSave()">Salva esiti</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade geometri-modal" id="geometri_esame_modal" tabindex="-1" role="dialog" data-backdrop="static">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header modal-header-orange4">
                    <h4 class="modal-title text-center">Catalogo esame CAT/Geometri</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="esame_id" value="-1">
                    <div class="row">
                        <div class="col-md-3">
                            <label>Codice</label>
                            <input type="text" id="esame_codice" class="form-control" placeholder="CAT3_01">
                        </div>
                        <div class="col-md-6">
                            <label>Titolo</label>
                            <input type="text" id="esame_titolo" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label>Anno corso</label>
                            <select id="esame_anno_corso" class="form-control">
                                <option value="3">Terza</option>
                                <option value="4">Quarta</option>
                                <option value="5">Quinta</option>
                            </select>
                        </div>
                    </div>
                    <div class="row" style="margin-top:10px;">
                        <div class="col-md-3">
                            <label>Ordine</label>
                            <input type="number" id="esame_ordine" class="form-control" min="0" step="1" value="0">
                        </div>
                        <div class="col-md-3">
                            <label>Attivo</label><br>
                            <input type="checkbox" id="esame_attivo" checked>
                        </div>
                        <div class="col-md-6">
                            <label>Descrizione</label>
                            <textarea id="esame_descrizione" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-default" data-dismiss="modal">Chiudi</button>
                    <button class="btn btn-primary" onclick="geometriEsameSave()">Salva</button>
                </div>
            </div>
        </div>
    </div>

    <div id="toastMessage"></div>

    <script>
        var GEOMETRI_CAN_EDIT = <?php echo (haRuolo('segreteria-didattica') || haRuolo('dirigente')) ? 'true' : 'false'; ?>;

        function geometriToast(message, isError) {
            var $t = $('#toastMessage');
            $t.css('background', isError ? '#dc3545' : '#28a745');
            $t.text(message).fadeIn(200).delay(2200).fadeOut(300);
        }

        function geometriReadRecords() {
            $.get('geometriReadRecords.php', {
                anno_id: $('#anno_filtro').val(),
                esame_id: $('#esame_filtro').val()
            }, function(html) {
                $('.records_content').html(html);
                $('[data-toggle="tooltip"]').tooltip({
                    container: 'body'
                });
            });
        }

        function geometriCatalogoReadRecords() {
            if (!GEOMETRI_CAN_EDIT) return;
            $.get('geometriEsamiReadRecords.php', {}, function(html) {
                $('.catalogo_content').html(html);
                $('[data-toggle="tooltip"]').tooltip({
                    container: 'body'
                });
            });
        }

        function geometriLibrettoStudentiRead() {
            if (!GEOMETRI_CAN_EDIT) return;
            $.getJSON('geometriLibrettoStudentiOptions.php', {}, function(res) {
                if (!res || !res.success) return;
                var options = '<option value="0">Seleziona studente</option>';
                (res.studenti || []).forEach(function(s) {
                    options += '<option value="' + s.id + '">' + $('<div>').text(s.label).html() + '</option>';
                });
                $('#libretto_studente').html(options).selectpicker('refresh');
            });
        }

        function geometriLibrettoPrint() {
            var studenteId = parseInt($('#libretto_studente').val() || '0', 10);
            if (studenteId <= 0) {
                geometriToast('Seleziona uno studente', true);
                return;
            }
            window.open('geometriLibrettoPdf.php?studente_id=' + encodeURIComponent(studenteId), '_blank');
        }

        function geometriReloadEsamiSelects() {
            $.getJSON('geometriEsamiOptions.php', {}, function(res) {
                if (!res || !res.success) return;

                var optionsFiltro = '<option value="0">Tutti</option>';
                var optionsModal = '<option value="0">Seleziona esame</option>';
                (res.esami || []).forEach(function(e) {
                    var label = 'Classe ' + e.anno_corso + ' - ' + e.titolo;
                    optionsFiltro += '<option value="' + e.id + '">' + $('<div>').text(label).html() + '</option>';
                    optionsModal += '<option value="' + e.id + '">' + $('<div>').text(label).html() + '</option>';
                });

                $('#esame_filtro').html(optionsFiltro).selectpicker('refresh');
                $('#id_esame').html(optionsModal).selectpicker('refresh');
            });
        }

        function geometriReloadClassiSelect(annoId, selectedValues, callback) {
            $.getJSON('geometriClassiOptions.php', {
                anno_id: annoId || $('#anno_filtro').val()
            }, function(res) {
                if (!res || !res.success) {
                    if (typeof callback === 'function') callback(false);
                    return;
                }

                var options = '';
                (res.classi || []).forEach(function(c) {
                    options += '<option value="' + c.id + '">' + $('<div>').text(c.label).html() + '</option>';
                });

                $('#classi').html(options).selectpicker('refresh');
                $('#classi').selectpicker('val', (selectedValues || []).map(String));
                $('#classi').selectpicker('refresh');
                if (typeof callback === 'function') callback(true);
            }).fail(function() {
                if (typeof callback === 'function') callback(false);
            });
        }

        function geometriReloadStudentiRecuperoSelect(annoId, esameId, selectedValues, callback) {
            $.getJSON('geometriStudentiOptions.php', {
                anno_id: annoId || $('#anno_filtro').val(),
                esame_id: esameId || $('#id_esame').val()
            }, function(res) {
                if (!res || !res.success) {
                    if (typeof callback === 'function') callback(false);
                    return;
                }

                var options = '';
                (res.studenti || []).forEach(function(s) {
                    options += '<option value="' + s.id + '">' + $('<div>').text(s.label).html() + '</option>';
                });

                $('#studenti_recupero').html(options).selectpicker('refresh');
                $('#studenti_recupero').selectpicker('val', (selectedValues || []).map(String));
                $('#studenti_recupero').selectpicker('refresh');
                if (typeof callback === 'function') callback(true);
            }).fail(function() {
                if (typeof callback === 'function') callback(false);
            });
        }

        function geometriResetModal() {
            $('#sessione_id').val(-1);
            $('#id_esame').selectpicker('val', '0');
            $('#data_inizio').val('');
            $('#stato').val('bozza');
            $('#classi').selectpicker('val', []);
            $('#studenti_recupero').selectpicker('val', []);
            $('#docenti').selectpicker('val', []);
            $('#esterni').selectpicker('val', []);
            $('#note').val('');
            geometriApplyPermissions();
        }

        function geometriApplyPermissions() {
            var readonly = !GEOMETRI_CAN_EDIT;
            $('#geometri_modal input, #geometri_modal textarea, #geometri_modal select').prop('disabled', readonly);
            $('#geometri_modal .selectpicker').selectpicker('refresh');
        }

        function geometriGetDetails(id) {
            geometriResetModal();
            if (parseInt(id, 10) <= 0) {
                geometriReloadClassiSelect($('#anno_filtro').val(), [], function() {
                    geometriReloadStudentiRecuperoSelect($('#anno_filtro').val(), $('#id_esame').val(), [], function() {
                        geometriApplyPermissions();
                        $('#geometri_modal').modal('show');
                    });
                });
                return;
            }

            $.post('geometriReadDetails.php', {
                id: id
            }, function(res) {
                if (!res || !res.success) {
                    geometriToast((res && res.error) ? res.error : 'Errore caricamento sessione', true);
                    return;
                }
                var s = res.sessione;
                $('#sessione_id').val(s.id);
                $('#id_esame').selectpicker('val', String(s.id_esame));
                $('#data_inizio').val(s.data_inizio_local || '');
                $('#stato').val(s.stato || 'bozza');
                $('#note').val(s.note || '');
                $('#docenti').selectpicker('val', (res.docenti || []).map(String));
                $('#esterni').selectpicker('val', (res.esterni || []).map(String));
                geometriReloadClassiSelect(s.id_anno_scolastico || $('#anno_filtro').val(), res.classi || [], function() {
                    geometriReloadStudentiRecuperoSelect(s.id_anno_scolastico || $('#anno_filtro').val(), s.id_esame, res.studenti_recupero || [], function() {
                        geometriApplyPermissions();
                        $('#geometri_modal').modal('show');
                    });
                });
            }, 'json');
        }

        function geometriSave() {
            var payload = {
                id: $('#sessione_id').val(),
                anno_id: $('#anno_filtro').val(),
                id_esame: $('#id_esame').val(),
                data_inizio: $('#data_inizio').val(),
                stato: $('#stato').val(),
                note: $('#note').val(),
                classi: JSON.stringify($('#classi').val() || []),
                studenti_recupero: JSON.stringify($('#studenti_recupero').val() || []),
                docenti: JSON.stringify($('#docenti').val() || []),
                esterni: JSON.stringify($('#esterni').val() || [])
            };

            $.post('geometriSave.php', payload, function(res) {
                if (res && res.success) {
                    $('#geometri_modal').modal('hide');
                    geometriToast('Sessione salvata');
                    geometriReadRecords();
                } else {
                    geometriToast((res && res.error) ? res.error : 'Errore salvataggio', true);
                }
            }, 'json').fail(function() {
                geometriToast('Errore comunicazione server', true);
            });
        }

        function geometriDelete(id) {
            bootbox.confirm('Cancellare la sessione selezionata?', function(ok) {
                if (!ok) return;
                $.post('geometriDelete.php', {
                    id: id
                }, function(res) {
                    if (res && res.success) {
                        geometriToast('Sessione cancellata');
                        geometriReadRecords();
                    } else {
                        geometriToast((res && res.error) ? res.error : 'Errore cancellazione', true);
                    }
                }, 'json');
            });
        }

        function geometriEsitiGet(id) {
            $('#esiti_sessione_id').val(id);
            $('#geometri_esiti_info').text('Caricamento studenti...');
            $('#geometri_esiti_tabs').html('');
            $('#geometri_esiti_tab_content').html('');
            $('#geometri_esiti_modal').modal('show');

            $.post('geometriEsitiRead.php', { id: id }, function(res) {
                if (!res || !res.success) {
                    $('#geometri_esiti_modal').modal('hide');
                    geometriToast((res && res.error) ? res.error : 'Errore caricamento esiti', true);
                    return;
                }

                var sessione = res.sessione || {};
                var info = (sessione.esame_titolo || 'Esame') + ' - ' + (sessione.data_label || '');
                $('#geometri_esiti_info').text(info);

                var studenti = res.studenti || [];
                var gruppi = {};
                var ordineClassi = [];

                studenti.forEach(function(studente) {
                    var classe = studente.classe || 'Senza classe';
                    if (!gruppi[classe]) {
                        gruppi[classe] = [];
                        ordineClassi.push(classe);
                    }
                    gruppi[classe].push(studente);
                });

                if (ordineClassi.length === 0) {
                    $('#geometri_esiti_tabs').html('');
                    $('#geometri_esiti_tab_content').html('<div class="alert alert-warning text-center">Nessuno studente trovato per le classi della sessione</div>');
                    return;
                }

                var tabsHtml = '';
                var panelsHtml = '';
                ordineClassi.forEach(function(classe, index) {
                    var tabId = 'geometri-esiti-classe-' + index;
                    var active = index === 0 ? ' active' : '';
                    var classeSafe = $('<div>').text(classe).html();
                    tabsHtml += '<li role="presentation" class="' + active + '"><a href="#' + tabId + '" aria-controls="' + tabId + '" role="tab" data-toggle="tab">' + classeSafe + ' <span class="badge">' + gruppi[classe].length + '</span></a></li>';
                    panelsHtml += '<div role="tabpanel" class="tab-pane' + active + '" id="' + tabId + '">';
                    panelsHtml += '<div class="table-responsive">';
                    panelsHtml += '<table class="table table-bordered table-striped table-green geometri-esiti-table">';
                    panelsHtml += '<thead><tr>';
                    panelsHtml += '<th style="width:40%;">Studente</th>';
                    panelsHtml += '<th class="text-center" style="width:12%;">Presente</th>';
                    panelsHtml += '<th class="text-center" style="width:22%;">Esito</th>';
                    panelsHtml += '<th style="width:26%;">Note</th>';
                    panelsHtml += '</tr></thead><tbody>';

                    gruppi[classe].forEach(function(studente) {
                        var presente = parseInt(studente.presente || 1, 10) === 1;
                        panelsHtml += '<tr data-studente-id="' + studente.id + '">';
                        panelsHtml += '<td>' + $('<div>').text((studente.cognome || '') + ' ' + (studente.nome || '')).html() + '</td>';
                        panelsHtml += '<td class="text-center"><input type="checkbox" class="esito-presente" ' + (presente ? 'checked' : '') + '></td>';
                        panelsHtml += '<td><select class="form-control input-sm esito-esito">';
                        panelsHtml += '<option value="da_valutare">Da valutare</option>';
                        panelsHtml += '<option value="superato">Superato</option>';
                        panelsHtml += '<option value="non_superato">Non superato</option>';
                        panelsHtml += '<option value="assente">Assente</option>';
                        panelsHtml += '<option value="ritirato">Ritirato</option>';
                        panelsHtml += '</select></td>';
                        panelsHtml += '<td><input type="text" class="form-control input-sm esito-note" value="' + $('<div>').text(studente.note || '').html() + '"></td>';
                        panelsHtml += '</tr>';
                    });

                    panelsHtml += '</tbody></table></div></div>';
                });

                $('#geometri_esiti_tabs').html(tabsHtml);
                $('#geometri_esiti_tab_content').html(panelsHtml);
                studenti.forEach(function(studente) {
                    $('#geometri_esiti_tab_content tr[data-studente-id="' + studente.id + '"] .esito-esito').val(studente.esito || 'da_valutare');
                });
            }, 'json').fail(function() {
                $('#geometri_esiti_modal').modal('hide');
                geometriToast('Errore comunicazione server', true);
            });
        }

        function geometriEsitiSave() {
            var rows = [];
            $('#geometri_esiti_tab_content tr[data-studente-id]').each(function() {
                var $r = $(this);
                rows.push({
                    id_studente: $r.data('studente-id'),
                    presente: $r.find('.esito-presente').is(':checked') ? 1 : 0,
                    esito: $r.find('.esito-esito').val(),
                    note: $r.find('.esito-note').val()
                });
            });

            $.post('geometriEsitiSave.php', {
                id: $('#esiti_sessione_id').val(),
                esiti: JSON.stringify(rows)
            }, function(res) {
                if (res && res.success) {
                    $('#geometri_esiti_modal').modal('hide');
                    geometriToast('Esiti salvati');
                    geometriReadRecords();
                } else {
                    geometriToast((res && res.error) ? res.error : 'Errore salvataggio esiti', true);
                }
            }, 'json').fail(function() {
                geometriToast('Errore comunicazione server', true);
            });
        }

        function geometriEsameResetModal() {
            $('#esame_id').val(-1);
            $('#esame_codice').val('');
            $('#esame_titolo').val('');
            $('#esame_anno_corso').val('3');
            $('#esame_ordine').val('0');
            $('#esame_attivo').prop('checked', true);
            $('#esame_descrizione').val('');
        }

        function geometriEsameGetDetails(id) {
            geometriEsameResetModal();
            if (parseInt(id, 10) <= 0) {
                $('#geometri_esame_modal').modal('show');
                return;
            }

            $.post('geometriEsameReadDetails.php', { id: id }, function(res) {
                if (!res || !res.success) {
                    geometriToast((res && res.error) ? res.error : 'Errore caricamento esame', true);
                    return;
                }
                var e = res.esame;
                $('#esame_id').val(e.id);
                $('#esame_codice').val(e.codice || '');
                $('#esame_titolo').val(e.titolo || '');
                $('#esame_anno_corso').val(String(e.anno_corso || 3));
                $('#esame_ordine').val(e.ordine || 0);
                $('#esame_attivo').prop('checked', parseInt(e.attivo || 0, 10) === 1);
                $('#esame_descrizione').val(e.descrizione || '');
                $('#geometri_esame_modal').modal('show');
            }, 'json');
        }

        function geometriEsameSave() {
            $.post('geometriEsameSave.php', {
                id: $('#esame_id').val(),
                codice: $('#esame_codice').val(),
                titolo: $('#esame_titolo').val(),
                anno_corso: $('#esame_anno_corso').val(),
                ordine: $('#esame_ordine').val(),
                attivo: $('#esame_attivo').is(':checked') ? 1 : 0,
                descrizione: $('#esame_descrizione').val()
            }, function(res) {
                if (res && res.success) {
                    $('#geometri_esame_modal').modal('hide');
                    geometriToast('Esame salvato');
                    geometriCatalogoReadRecords();
                    geometriReloadEsamiSelects();
                } else {
                    geometriToast((res && res.error) ? res.error : 'Errore salvataggio esame', true);
                }
            }, 'json').fail(function() {
                geometriToast('Errore comunicazione server', true);
            });
        }

        function geometriEsameDelete(id) {
            bootbox.confirm('Cancellare l\'esame dal catalogo?', function(ok) {
                if (!ok) return;
                $.post('geometriEsameDelete.php', { id: id }, function(res) {
                    if (res && res.success) {
                        geometriToast('Esame cancellato');
                        geometriCatalogoReadRecords();
                        geometriReloadEsamiSelects();
                    } else {
                        geometriToast((res && res.error) ? res.error : 'Errore cancellazione esame', true);
                    }
                }, 'json');
            });
        }

        $(document).ready(function() {
            $('.selectpicker').selectpicker();
            $('#anno_filtro').on('changed.bs.select', function() {
                geometriReloadClassiSelect($('#anno_filtro').val(), []);
                geometriReloadStudentiRecuperoSelect($('#anno_filtro').val(), $('#id_esame').val(), []);
                geometriReadRecords();
            });
            $('#id_esame').on('changed.bs.select', function() {
                geometriReloadStudentiRecuperoSelect($('#anno_filtro').val(), $('#id_esame').val(), $('#studenti_recupero').val() || []);
            });
            $('#esame_filtro').on('changed.bs.select', geometriReadRecords);
            geometriReloadClassiSelect($('#anno_filtro').val(), []);
            geometriReloadStudentiRecuperoSelect($('#anno_filtro').val(), $('#id_esame').val(), []);
            geometriReadRecords();
            geometriCatalogoReadRecords();
            geometriLibrettoStudentiRead();
        });
    </script>
</body>

</html>
