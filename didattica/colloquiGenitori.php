<?php

require_once '../common/checkSession.php';
require_once '../common/genitoriColloquiLib.php';

ruoloRichiesto('admin');

genitoriColloquiEnsureTables();

$message = '';
$error = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = trim((string)($_POST['action'] ?? 'save'));
        if ($action === 'delete') {
            $deleted = genitoriColloquiDelete((int)($_POST['id'] ?? 0));
            $message = $deleted ? 'Colloquio eliminato.' : 'Colloquio non trovato.';
        } else {
            genitoriColloquiSave($_POST, $_FILES['allegato'] ?? null, $_FILES['ricevuta_libri'] ?? null);
            $message = 'Colloquio salvato.';
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$colloqui = genitoriColloquiAll();
$iscrizioniOptions = genitoriColloquiIscrizioniOptions();
$movimentiOptions = genitoriColloquiMovimentiOptions();
$istitutiScuole = scuoleIstitutiAll();
$colloquiHistory = genitoriColloquiHistoryForIds(array_map(static fn($row) => intval($row['id'] ?? 0), $colloqui));

$ambiti = [
    'entrata' => 'Entrata',
    'uscita' => 'Uscita',
    'altro' => 'Altro',
];
$stati = [
    'richiesto' => 'Richiesto',
    'da_fissare' => 'Da fissare',
    'fissato' => 'Fissato',
    'svolto' => 'Svolto',
    'approvato' => 'Approvato',
    'non_approvato' => 'Non approvato',
    'annullato' => 'Annullato',
];
$esiti = [
    '' => 'Nessun esito',
    'ingresso_ok' => 'Ingresso approvato',
    'uscita_ok' => 'Uscita approvata',
    'integrazione' => 'Da integrare',
    'non_idoneo' => 'Non idoneo',
    'rinuncia' => 'Rinuncia',
];

function cg_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cg_selected($a, $b): string
{
    return (string)$a === (string)$b ? 'selected' : '';
}

function cg_label_class(string $stato): string
{
    if (in_array($stato, ['approvato'], true)) {
        return 'success';
    }
    if (in_array($stato, ['fissato','svolto','da_fissare'], true)) {
        return 'warning';
    }
    if (in_array($stato, ['non_approvato','annullato'], true)) {
        return 'danger';
    }
    return 'default';
}

function cg_date_it($value): string
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '';
    }
    try {
        $dt = new DateTime($value);
        return strlen($value) > 10 ? $dt->format('d/m/Y H:i') : $dt->format('d/m/Y');
    } catch (Throwable $e) {
        return $value;
    }
}

function cg_attachment_link(array $row): string
{
    $path = trim((string)($row['allegato_path'] ?? ''));
    if ($path === '') {
        return '';
    }
    $name = trim((string)($row['allegato_original_name'] ?? 'Allegato'));
    return '<a class="btn btn-xs btn-default" target="_blank" href="../' . cg_h($path) . '"><span class="glyphicon glyphicon-paperclip"></span> ' . cg_h($name) . '</a>';
}

function cg_receipt_link(array $row): string
{
    $path = trim((string)($row['ricevuta_libri_path'] ?? ''));
    if ($path === '') {
        return '';
    }
    $name = trim((string)($row['ricevuta_libri_original_name'] ?? 'Ricevuta libri'));
    return '<a class="btn btn-xs btn-success" target="_blank" href="../' . cg_h($path) . '"><span class="glyphicon glyphicon-book"></span> ' . cg_h($name) . '</a>';
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Colloqui genitori</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
    <style>
        body {
            padding-top: 42px;
        }
        .container-fluid {
            padding-top: 0;
        }
        .page-header {
            margin: 8px 0 16px;
            padding-bottom: 10px;
        }
        .cg-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }
        .cg-table th {
            white-space: nowrap;
        }
        .cg-student {
            font-weight: 700;
            color: #10233f;
        }
        .cg-muted {
            color: #60708a;
        }
        .cg-notes {
            max-width: 460px;
            white-space: pre-wrap;
        }
        .cg-modal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .modal-dialog.cg-wide-modal {
            width: min(1380px, 96vw);
        }
        .cg-modal-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 360px;
            gap: 16px;
            align-items: start;
        }
        .cg-modal-main {
            min-width: 0;
        }
        .cg-modal-side {
            border: 1px solid #d9e2ef;
            border-radius: 8px;
            background: #f8fafc;
            padding: 12px;
            position: sticky;
            top: 10px;
        }
        .cg-modal-side h4 {
            margin-top: 0;
        }
        .cg-modal-grid .full {
            grid-column: 1 / -1;
        }
        .cg-link-box {
            border: 1px solid #d9e2ef;
            background: #f8fbff;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 12px;
        }
        .cg-context {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .cg-context-panel {
            border: 1px solid #d9e2ef;
            border-left: 5px solid #337ab7;
            border-radius: 6px;
            padding: 10px;
            background: #fbfdff;
        }
        .cg-context-panel h4 {
            margin-top: 0;
        }
        .cg-history-box {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px;
            background: #fff;
            max-height: 260px;
            overflow: auto;
        }
        .cg-history-item {
            border-left: 4px solid #64748b;
            background: #f8fafc;
            padding: 8px 10px;
            margin-bottom: 8px;
            border-radius: 4px;
        }
        .cg-history-head {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            font-weight: 700;
        }
        .cg-row-detail {
            color: #475569;
            font-size: 12px;
            margin-top: 3px;
        }
        .cg-row-actions {
            display: flex;
            gap: 5px;
            align-items: center;
            white-space: nowrap;
        }
        .cg-row-actions form {
            margin: 0;
        }
        @media (max-width: 900px) {
            .cg-toolbar {
                display: block;
            }
            .cg-toolbar .btn {
                margin-top: 8px;
            }
            .cg-modal-grid {
                grid-template-columns: 1fr;
            }
            .cg-context {
                grid-template-columns: 1fr;
            }
            .modal-dialog.cg-wide-modal {
                width: auto;
            }
            .cg-modal-layout {
                grid-template-columns: 1fr;
            }
            .cg-modal-side {
                position: static;
            }
        }
    </style>
</head>
<body>
<?php require_once '../common/header-didattica.php'; ?>

<div class="container-fluid">
    <div class="page-header">
        <div class="cg-toolbar">
            <div>
                <h2>Colloqui genitori</h2>
                <p class="cg-muted">Gestione colloqui per entrate, uscite, cambi scuola e pratiche di iscrizione.</p>
            </div>
            <button type="button" class="btn btn-primary btn-lg" id="newColloquioBtn">
                <span class="glyphicon glyphicon-plus"></span> Nuovo colloquio
            </button>
        </div>
    </div>

    <?php if ($message !== '') : ?>
        <div class="alert alert-success"><?php echo cg_h($message); ?></div>
    <?php endif; ?>
    <?php if ($error !== '') : ?>
        <div class="alert alert-danger"><?php echo cg_h($error); ?></div>
    <?php endif; ?>

    <div class="panel panel-default">
        <div class="panel-heading">
            <strong>Colloqui registrati</strong>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover cg-table">
                <thead>
                <tr>
                    <th>Studente</th>
                    <th>Ambito</th>
                    <th>Classe / destinazione</th>
                    <th>Stato</th>
                    <th>Esito</th>
                    <th>Richiesta</th>
                    <th>Appuntamento</th>
                    <th>Referente</th>
                    <th>Note</th>
                    <th>Allegato</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($colloqui)) : ?>
                    <tr>
                        <td colspan="11" class="text-center cg-muted">Nessun colloquio registrato.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($colloqui as $row) : ?>
                    <?php
                    $student = trim((string)($row['cognome'] ?? '') . ' ' . (string)($row['nome'] ?? ''));
                    $stato = (string)($row['stato'] ?? 'richiesto');
                    $allegato = cg_attachment_link($row);
                    $ricevutaLibri = cg_receipt_link($row);
                    $history = $colloquiHistory[intval($row['id'] ?? 0)] ?? [];
                    ?>
                    <tr>
                        <td>
                            <div class="cg-student"><?php echo cg_h($student !== '' ? $student : 'Studente non indicato'); ?></div>
                            <div class="cg-muted">
                                <?php echo cg_h($row['codice_fiscale'] ?? ''); ?>
                                <?php if (trim((string)($row['classe'] ?? '')) !== '') : ?>
                                    · <?php echo cg_h($row['classe']); ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?php echo cg_h($ambiti[$row['ambito'] ?? 'altro'] ?? ($row['ambito'] ?? '')); ?></td>
                        <td>
                            <?php if (($row['ambito'] ?? '') === 'entrata') : ?>
                                <strong><?php echo cg_h($row['classe_iscrizione'] ?: $row['classe']); ?></strong>
                                <div class="cg-row-detail"><?php echo cg_h(trim(($row['indirizzo_iscrizione'] ?? '') . ' ' . ($row['gruppo_iscrizione'] ? '· ' . $row['gruppo_iscrizione'] : ''))); ?></div>
                            <?php elseif (($row['ambito'] ?? '') === 'uscita') : ?>
                                <strong><?php echo cg_h($row['scuola_destinazione'] ?: 'Destinazione non indicata'); ?></strong>
                                <div class="cg-row-detail"><?php echo cg_h($row['indirizzo_destinazione'] ?? ''); ?></div>
                                <?php if (!empty($row['libri_da_restituire'])) : ?>
                                    <span class="label label-warning">libri da restituire</span>
                                    <?php echo $ricevutaLibri !== '' ? $ricevutaLibri : ''; ?>
                                <?php endif; ?>
                            <?php else : ?>
                                <span class="cg-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="label label-<?php echo cg_label_class($stato); ?>"><?php echo cg_h($stati[$stato] ?? $stato); ?></span></td>
                        <td><?php echo cg_h($esiti[$row['esito'] ?? ''] ?? ($row['esito'] ?? '')); ?></td>
                        <td><?php echo cg_h(cg_date_it($row['richiesta_data'] ?? '')); ?></td>
                        <td><?php echo cg_h(cg_date_it($row['appuntamento_at'] ?? '')); ?></td>
                        <td><?php echo cg_h($row['referente'] ?? ''); ?></td>
                        <td class="cg-notes"><?php echo cg_h($row['note'] ?? ''); ?></td>
                        <td><?php echo $allegato !== '' ? $allegato : '<span class="cg-muted">-</span>'; ?></td>
                        <td>
                            <div class="cg-row-actions">
                                <button type="button" class="btn btn-xs btn-default editColloquioBtn"
                                    data-record='<?php echo cg_h(json_encode($row, JSON_UNESCAPED_UNICODE)); ?>'
                                    data-history='<?php echo cg_h(json_encode($history, JSON_UNESCAPED_UNICODE)); ?>'>
                                    Modifica
                                </button>
                                <form method="post" onsubmit="return confirm('Eliminare definitivamente questo colloquio e il relativo storico?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo intval($row['id']); ?>">
                                    <button type="submit" class="btn btn-xs btn-danger">Elimina</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="colloquioModal" tabindex="-1" role="dialog" aria-labelledby="colloquioModalTitle">
    <div class="modal-dialog modal-lg cg-wide-modal" role="document">
        <form method="post" enctype="multipart/form-data" class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Chiudi"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="colloquioModalTitle">Colloquio genitori</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="cg_id">

                <div class="cg-link-box">
                    <div class="row">
                        <div class="col-sm-6">
                            <label for="cg_pratica">Collega domanda iscrizione</label>
                            <select class="form-control" id="cg_pratica" name="id_pratica_iscrizione">
                                <option value="">Nessuna domanda collegata</option>
                                <?php foreach ($iscrizioniOptions as $opt) : ?>
                                    <?php
                                    $label = trim((string)($opt['cognome'] ?? '') . ' ' . (string)($opt['nome'] ?? '')) . ' · ' . strtoupper((string)($opt['tipo_iscrizione'] ?? ''));
                                    ?>
                                    <option value="<?php echo intval($opt['id']); ?>"
                                            data-ambito="entrata"
                                            data-cognome="<?php echo cg_h($opt['cognome'] ?? ''); ?>"
                                            data-nome="<?php echo cg_h($opt['nome'] ?? ''); ?>"
                                            data-cf="<?php echo cg_h($opt['codice_fiscale'] ?? ''); ?>"
                                            data-classe="<?php echo cg_h($opt['corso_studi'] ?? ''); ?>">
                                        <?php echo cg_h($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label for="cg_movimento">Collega pratica entrata/uscita</label>
                            <select class="form-control" id="cg_movimento" name="id_movimento">
                                <option value="">Nessuna pratica collegata</option>
                                <?php foreach ($movimentiOptions as $opt) : ?>
                                    <?php
                                    $label = trim((string)($opt['cognome'] ?? '') . ' ' . (string)($opt['nome'] ?? '')) . ' · ' . strtoupper((string)($opt['tipo_pratica'] ?? ''));
                                    $ambitoOpt = (string)($opt['tipo_pratica'] ?? '') === 'entrata' ? 'entrata' : 'uscita';
                                    ?>
                                    <option value="<?php echo intval($opt['id']); ?>"
                                            data-ambito="<?php echo cg_h($ambitoOpt); ?>"
                                            data-cognome="<?php echo cg_h($opt['cognome'] ?? ''); ?>"
                                            data-nome="<?php echo cg_h($opt['nome'] ?? ''); ?>"
                                            data-cf="<?php echo cg_h($opt['codice_fiscale'] ?? ''); ?>"
                                            data-classe="<?php echo cg_h(($opt['classe_origine'] ?? '') ?: ($opt['classe_richiesta'] ?? '')); ?>">
                                        <?php echo cg_h($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <p class="help-block">Se colleghi una domanda o una pratica, al salvataggio lo storico collegato verrà aggiornato quando il colloquio risulta svolto o approvato.</p>
                </div>

                <div class="cg-modal-layout">
                <div class="cg-modal-main">
                <div class="cg-modal-grid">
                    <div>
                        <label for="cg_ambito">Ambito</label>
                        <select class="form-control" name="ambito" id="cg_ambito">
                            <?php foreach ($ambiti as $key => $label) : ?>
                                <option value="<?php echo cg_h($key); ?>"><?php echo cg_h($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="cg_referente">Referente colloquio</label>
                        <input class="form-control" name="referente" id="cg_referente" value="prof.ssa Ceschini">
                    </div>
                    <div>
                        <label for="cg_cognome">Cognome</label>
                        <input class="form-control" name="cognome" id="cg_cognome">
                    </div>
                    <div>
                        <label for="cg_nome">Nome</label>
                        <input class="form-control" name="nome" id="cg_nome">
                    </div>
                    <div>
                        <label for="cg_cf">Codice fiscale</label>
                        <input class="form-control" name="codice_fiscale" id="cg_cf">
                    </div>
                    <div>
                        <label for="cg_classe">Classe / indirizzo</label>
                        <input class="form-control" name="classe" id="cg_classe">
                    </div>
                    <div>
                        <label for="cg_richiesta_data">Data richiesta</label>
                        <input type="date" class="form-control" name="richiesta_data" id="cg_richiesta_data">
                    </div>
                    <div>
                        <label>Appuntamento</label>
                        <div class="row">
                            <div class="col-xs-7"><input type="date" class="form-control" name="appuntamento_data" id="cg_appuntamento_data"></div>
                            <div class="col-xs-5"><input type="time" class="form-control" name="appuntamento_ora" id="cg_appuntamento_ora"></div>
                        </div>
                    </div>
                    <div>
                        <label for="cg_stato">Stato colloquio</label>
                        <select class="form-control" name="stato" id="cg_stato">
                            <?php foreach ($stati as $key => $label) : ?>
                                <option value="<?php echo cg_h($key); ?>"><?php echo cg_h($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="cg_esito">Esito</label>
                        <select class="form-control" name="esito" id="cg_esito">
                            <?php foreach ($esiti as $key => $label) : ?>
                                <option value="<?php echo cg_h($key); ?>"><?php echo cg_h($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="full cg-context">
                        <div class="cg-context-panel" id="cg_entrata_panel">
                            <h4>Dati entrata / iscrizione</h4>
                            <div class="row">
                                <div class="col-sm-4">
                                    <label for="cg_anno_corso">Anno / classe</label>
                                    <select class="form-control" name="anno_corso" id="cg_anno_corso">
                                        <option value="">Non indicato</option>
                                        <option value="1">Prima</option>
                                        <option value="2">Seconda</option>
                                        <option value="3">Terza</option>
                                        <option value="4">Quarta</option>
                                        <option value="5">Quinta</option>
                                    </select>
                                </div>
                                <div class="col-sm-8">
                                    <label for="cg_classe_iscrizione">Classe prevista</label>
                                    <input class="form-control" name="classe_iscrizione" id="cg_classe_iscrizione" placeholder="Es. 3 informatica, 1DS, 2...">
                                </div>
                                <div class="col-sm-8">
                                    <label for="cg_indirizzo_iscrizione">Indirizzo / percorso</label>
                                    <input class="form-control" name="indirizzo_iscrizione" id="cg_indirizzo_iscrizione" placeholder="Es. informatica, biotecnologie, Digital Science">
                                </div>
                                <div class="col-sm-4">
                                    <label for="cg_gruppo_iscrizione">Gruppo</label>
                                    <input class="form-control" name="gruppo_iscrizione" id="cg_gruppo_iscrizione" placeholder="Es. tablet">
                                </div>
                            </div>
                        </div>
                        <div class="cg-context-panel" id="cg_uscita_panel" style="border-left-color:#d97706;">
                            <h4>Dati uscita / cambio scuola</h4>
                            <div class="row">
                                <div class="col-sm-12">
                                    <label for="cg_istituto_destinazione">Scuola di destinazione</label>
                                    <select class="form-control" name="id_istituto_destinazione" id="cg_istituto_destinazione">
                                        <option value="">Seleziona istituto</option>
                                        <?php foreach ($istitutiScuole as $istituto) : ?>
                                            <option value="<?php echo intval($istituto['id']); ?>"><?php echo cg_h($istituto['nome'] ?? ''); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input class="form-control" name="scuola_destinazione" id="cg_scuola_destinazione" style="margin-top:6px;" placeholder="Oppure scrivi la scuola se non e' in elenco">
                                </div>
                                <div class="col-sm-12">
                                    <label for="cg_indirizzo_destinazione">Indirizzo nella scuola di destinazione</label>
                                    <input class="form-control" name="indirizzo_destinazione" id="cg_indirizzo_destinazione" placeholder="Es. liceo scientifico, informatica, meccanica...">
                                </div>
                                <div class="col-sm-6">
                                    <label>
                                        <input type="checkbox" name="libri_da_restituire" id="cg_libri_da_restituire" value="1">
                                        Deve restituire libri in comodato
                                    </label>
                                    <p class="help-block">Di norma necessario per studenti in uscita da prima o seconda.</p>
                                </div>
                                <div class="col-sm-6">
                                    <label for="cg_libri_restituiti_at">Data restituzione libri</label>
                                    <input type="date" class="form-control" name="libri_restituiti_at" id="cg_libri_restituiti_at">
                                </div>
                                <div class="col-sm-12">
                                    <label for="cg_ricevuta_libri">Ricevuta consegna libri</label>
                                    <input type="file" class="form-control" name="ricevuta_libri" id="cg_ricevuta_libri" accept=".pdf,.jpg,.jpeg,.png">
                                    <p class="help-block" id="cg_ricevuta_libri_attuale"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="full cg-entrata-extra">
                        <label for="cg_esami">Esami integrativi</label>
                        <textarea class="form-control" name="esami_integrativi" id="cg_esami" rows="2" placeholder="Materie, indicazioni, eventuale domanda esami integrativi"></textarea>
                    </div>
                    <div class="full cg-entrata-extra">
                        <label for="cg_carenze">Carenze da recuperare</label>
                        <textarea class="form-control" name="carenze_note" id="cg_carenze" rows="2"></textarea>
                    </div>
                    <div class="full">
                        <label for="cg_libri">Libri / materiali da prestare o restituire</label>
                        <textarea class="form-control" name="libri_note" id="cg_libri" rows="2"></textarea>
                    </div>
                    <div class="full">
                        <label for="cg_note">Note colloquio</label>
                        <textarea class="form-control" name="note" id="cg_note" rows="4"></textarea>
                    </div>
                    <div class="full">
                        <label for="cg_allegato">Allegato richiesta / mail genitori</label>
                        <input type="file" class="form-control" name="allegato" id="cg_allegato" accept=".pdf,.jpg,.jpeg,.png">
                        <p class="help-block" id="cg_allegato_attuale"></p>
                    </div>
                </div>
                </div>
                <div class="cg-modal-side">
                    <h4>Storico colloquio</h4>
                        <div class="cg-history-box" id="cg_history_box">
                            <span class="cg-muted">Nessuno storico registrato.</span>
                        </div>
                    <p class="help-block">Ogni salvataggio del colloquio resta tracciato qui.</p>
                </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>
                <button type="submit" class="btn btn-success">Salva colloquio</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    function setValue(id, value) {
        var el = document.getElementById(id);
        if (el) {
            el.value = value || '';
        }
    }
    function setChecked(id, value) {
        var el = document.getElementById(id);
        if (el) {
            el.checked = !!(parseInt(value || 0, 10));
        }
    }
    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char];
        });
    }
    function formatHistoryDate(value) {
        if (!value) return '';
        var parts = String(value).split(/[- :]/);
        if (parts.length < 3) return value;
        return parts[2] + '/' + parts[1] + '/' + parts[0] + (parts.length >= 5 ? ' ' + parts[3] + ':' + parts[4] : '');
    }
    function renderHistory(history) {
        var box = document.getElementById('cg_history_box');
        history = Array.isArray(history) ? history : [];
        if (!history.length) {
            box.innerHTML = '<span class="cg-muted">Nessuno storico registrato.</span>';
            return;
        }
        box.innerHTML = history.map(function (event) {
            var data = {};
            try { data = JSON.parse(event.dati_json || '{}'); } catch (e) { data = {}; }
            var detail = [
                data.ambito ? 'Ambito: ' + data.ambito : '',
                data.stato ? 'Stato: ' + data.stato : '',
                data.esito ? 'Esito: ' + data.esito : '',
                data.classe_iscrizione ? 'Classe: ' + data.classe_iscrizione : '',
                data.scuola_destinazione ? 'Scuola destinazione: ' + data.scuola_destinazione : ''
            ].filter(Boolean).join(' · ');
            return '<div class="cg-history-item">'
                + '<div class="cg-history-head"><span>' + escapeHtml(event.descrizione || event.tipo_evento || 'Evento') + '</span><span>' + escapeHtml(formatHistoryDate(event.created_at)) + '</span></div>'
                + '<div class="cg-muted">Operatore: ' + escapeHtml(event.created_by || '') + '</div>'
                + (detail ? '<div class="cg-row-detail">' + escapeHtml(detail) + '</div>' : '')
                + (data.note ? '<div class="cg-notes">' + escapeHtml(data.note) + '</div>' : '')
                + '</div>';
        }).join('');
    }
    function updateAmbitoPanels() {
        var ambito = document.getElementById('cg_ambito').value;
        document.getElementById('cg_entrata_panel').style.display = ambito === 'entrata' ? '' : 'none';
        document.getElementById('cg_uscita_panel').style.display = ambito === 'uscita' ? '' : 'none';
        Array.prototype.forEach.call(document.querySelectorAll('.cg-entrata-extra'), function (el) {
            el.style.display = ambito === 'uscita' ? 'none' : '';
        });
    }
    function resetForm() {
        setValue('cg_id', '');
        setValue('cg_pratica', '');
        setValue('cg_movimento', '');
        setValue('cg_ambito', 'uscita');
        setValue('cg_referente', 'prof.ssa Ceschini');
        setValue('cg_cognome', '');
        setValue('cg_nome', '');
        setValue('cg_cf', '');
        setValue('cg_classe', '');
        setValue('cg_anno_corso', '');
        setValue('cg_classe_iscrizione', '');
        setValue('cg_indirizzo_iscrizione', '');
        setValue('cg_gruppo_iscrizione', '');
        setValue('cg_istituto_destinazione', '');
        setValue('cg_scuola_destinazione', '');
        setValue('cg_indirizzo_destinazione', '');
        setChecked('cg_libri_da_restituire', 0);
        setValue('cg_libri_restituiti_at', '');
        setValue('cg_ricevuta_libri', '');
        setValue('cg_richiesta_data', '');
        setValue('cg_appuntamento_data', '');
        setValue('cg_appuntamento_ora', '');
        setValue('cg_stato', 'richiesto');
        setValue('cg_esito', '');
        setValue('cg_esami', '');
        setValue('cg_carenze', '');
        setValue('cg_libri', '');
        setValue('cg_note', '');
        setValue('cg_allegato', '');
        document.getElementById('cg_allegato_attuale').textContent = '';
        document.getElementById('cg_ricevuta_libri_attuale').textContent = '';
        renderHistory([]);
        updateAmbitoPanels();
    }
    function fillFromOption(option) {
        if (!option || !option.value) {
            return;
        }
        setValue('cg_ambito', option.getAttribute('data-ambito') || 'altro');
        setValue('cg_cognome', option.getAttribute('data-cognome') || '');
        setValue('cg_nome', option.getAttribute('data-nome') || '');
        setValue('cg_cf', option.getAttribute('data-cf') || '');
        setValue('cg_classe', option.getAttribute('data-classe') || '');
        if (option.getAttribute('data-ambito') === 'entrata') {
            setValue('cg_classe_iscrizione', option.getAttribute('data-classe') || '');
        }
        updateAmbitoPanels();
    }
    document.getElementById('newColloquioBtn').addEventListener('click', function () {
        resetForm();
        $('#colloquioModal').modal('show');
    });
    document.getElementById('cg_pratica').addEventListener('change', function () {
        fillFromOption(this.options[this.selectedIndex]);
    });
    document.getElementById('cg_movimento').addEventListener('change', function () {
        fillFromOption(this.options[this.selectedIndex]);
    });
    document.getElementById('cg_ambito').addEventListener('change', updateAmbitoPanels);
    Array.prototype.forEach.call(document.querySelectorAll('.editColloquioBtn'), function (btn) {
        btn.addEventListener('click', function () {
            resetForm();
            var row = JSON.parse(this.getAttribute('data-record') || '{}');
            setValue('cg_id', row.id);
            setValue('cg_pratica', row.id_pratica_iscrizione || '');
            setValue('cg_movimento', row.id_movimento || '');
            setValue('cg_ambito', row.ambito || 'altro');
            setValue('cg_referente', row.referente || 'prof.ssa Ceschini');
            setValue('cg_cognome', row.cognome || '');
            setValue('cg_nome', row.nome || '');
            setValue('cg_cf', row.codice_fiscale || '');
            setValue('cg_classe', row.classe || '');
            setValue('cg_anno_corso', row.anno_corso || '');
            setValue('cg_classe_iscrizione', row.classe_iscrizione || '');
            setValue('cg_indirizzo_iscrizione', row.indirizzo_iscrizione || '');
            setValue('cg_gruppo_iscrizione', row.gruppo_iscrizione || '');
            setValue('cg_istituto_destinazione', row.id_istituto_destinazione || '');
            setValue('cg_scuola_destinazione', row.scuola_destinazione || '');
            setValue('cg_indirizzo_destinazione', row.indirizzo_destinazione || '');
            setChecked('cg_libri_da_restituire', row.libri_da_restituire || 0);
            setValue('cg_libri_restituiti_at', row.libri_restituiti_at || '');
            setValue('cg_richiesta_data', row.richiesta_data || '');
            if (row.appuntamento_at) {
                setValue('cg_appuntamento_data', String(row.appuntamento_at).substring(0, 10));
                setValue('cg_appuntamento_ora', String(row.appuntamento_at).substring(11, 16));
            }
            setValue('cg_stato', row.stato || 'richiesto');
            setValue('cg_esito', row.esito || '');
            setValue('cg_esami', row.esami_integrativi || '');
            setValue('cg_carenze', row.carenze_note || '');
            setValue('cg_libri', row.libri_note || '');
            setValue('cg_note', row.note || '');
            if (row.allegato_original_name) {
                document.getElementById('cg_allegato_attuale').textContent = 'Allegato attuale: ' + row.allegato_original_name;
            }
            if (row.ricevuta_libri_original_name) {
                document.getElementById('cg_ricevuta_libri_attuale').textContent = 'Ricevuta attuale: ' + row.ricevuta_libri_original_name;
            }
            renderHistory(JSON.parse(this.getAttribute('data-history') || '[]'));
            updateAmbitoPanels();
            $('#colloquioModal').modal('show');
        });
    });
    updateAmbitoPanels();
})();
</script>
</body>
</html>
