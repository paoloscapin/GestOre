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
        } elseif ($action === 'cleanup_fake_movimenti') {
            $cleanup = genitoriColloquiCleanupAutoCreatedMovimenti();
            $message = 'Pulizia pratiche false completata: trovate ' . intval($cleanup['found'] ?? 0)
                . ', eliminate ' . intval($cleanup['deleted'] ?? 0)
                . ', colloqui scollegati ' . intval($cleanup['unlinked'] ?? 0) . '.';
        } elseif ($action === 'cleanup_duplicate_events') {
            $cleanup = genitoriColloquiCleanupDuplicateEvents();
            $message = 'Pulizia doppioni storico completata: lette ' . intval($cleanup['read'] ?? 0)
                . ' righe, eliminati ' . intval($cleanup['deleted'] ?? 0) . ' doppioni.';
        } elseif ($action === 'repropagate_outcomes') {
            $sync = genitoriColloquiRepropagateLinkedOutcomes();
            $message = 'Stati pratiche aggiornati dai colloqui: letti ' . intval($sync['read'] ?? 0)
                . ', riallineati ' . intval($sync['updated'] ?? 0) . '.';
            if (!empty($sync['errors'])) {
                $message .= ' Errori: ' . implode(' | ', array_slice((array)$sync['errors'], 0, 5));
            }
        } elseif ($action === 'delete_event') {
            $deleted = genitoriColloquiDeleteEvent((int)($_POST['event_id'] ?? 0));
            $message = $deleted ? 'Riga storico eliminata.' : 'Riga storico non trovata.';
        } elseif ($action === 'update_event') {
            $updated = genitoriColloquiUpdateEvent(
                (int)($_POST['event_id'] ?? 0),
                (string)($_POST['event_descrizione'] ?? ''),
                (string)($_POST['event_note'] ?? ''),
                (string)($_POST['event_libri_note'] ?? '')
            );
            $message = $updated ? 'Riga storico aggiornata.' : 'Riga storico non trovata.';
        } elseif ($action === 'save_incontro') {
            genitoriColloquiSaveIncontro($_POST, $_FILES['incontro_allegati'] ?? null);
            $message = 'Colloquio/incontro aggiunto alla scheda.';
        } else {
            genitoriColloquiSave($_POST, $_FILES['allegato'] ?? null, $_FILES['ricevuta_libri'] ?? null);
            $message = 'Colloquio salvato.';
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$syncMovimenti = ['created' => 0, 'updated' => 0];
try {
    $syncMovimenti = genitoriColloquiSyncRequestedFromMovimenti();
    if ($message === '' && intval($syncMovimenti['created'] ?? 0) > 0) {
        $message = 'Colloqui richiesti da Entrate / uscite sincronizzati: ' . intval($syncMovimenti['created']) . ' nuovi.';
    }
} catch (Throwable $e) {
    if ($error === '') {
        $error = 'Sincronizzazione colloqui da Entrate / uscite non riuscita: ' . $e->getMessage();
    }
}

$colloqui = genitoriColloquiAll();
$iscrizioniOptions = genitoriColloquiIscrizioniOptions();
$movimentiOptions = genitoriColloquiMovimentiOptions();
$istitutiScuole = scuoleIstitutiAll();
$indirizziArrivo = dbGetAll("
    SELECT id, nome
    FROM indirizzo
    WHERE id BETWEEN 1 AND 10
    ORDER BY id ASC, nome ASC
") ?: [];
$materieGestore = dbGetAll("
    SELECT id, nome
    FROM materia
    ORDER BY nome ASC
") ?: [];
$colloquiHistory = genitoriColloquiHistoryForIds(array_map(static fn($row) => intval($row['id'] ?? 0), $colloqui));
$colloquiIncontri = genitoriColloquiIncontriForIds(array_map(static fn($row) => intval($row['id'] ?? 0), $colloqui));

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
    'integrazione' => 'Deve fare esami integrativi',
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
    if (in_array($stato, ['richiesto','fissato','svolto','da_fissare'], true)) {
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

function cg_destination_summary(array $row): array
{
    $ambito = (string)($row['ambito'] ?? '');
    $pieces = [];
    if ($ambito === 'entrata') {
        $anno = intval($row['anno_corso'] ?? 0);
        if ($anno > 0) {
            $pieces[] = 'Anno ' . $anno;
        }
        foreach (['classe_iscrizione', 'indirizzo_iscrizione', 'gruppo_iscrizione'] as $field) {
            $value = trim((string)($row[$field] ?? ''));
            if ($value !== '') {
                $pieces[] = $value;
            }
        }
        if (!$pieces && trim((string)($row['classe'] ?? '')) !== '') {
            $pieces[] = trim((string)$row['classe']);
        }
        $detail = array_filter([
            trim((string)($row['scuola_provenienza'] ?? '')) !== '' ? 'Da: ' . trim((string)$row['scuola_provenienza']) : '',
            trim((string)($row['indirizzo_provenienza'] ?? '')),
        ]);
        return [$pieces ? implode(' - ', $pieces) : 'Classe non indicata', implode(' - ', $detail)];
    }
    if ($ambito === 'uscita') {
        $main = trim((string)($row['scuola_destinazione'] ?? '')) ?: 'Destinazione non indicata';
        $detail = array_filter([
            trim((string)($row['indirizzo_destinazione'] ?? '')),
            trim((string)($row['referente_scuola_destinazione'] ?? '')) !== '' ? 'Ref: ' . trim((string)$row['referente_scuola_destinazione']) : '',
        ]);
        return [$main, implode(' - ', $detail)];
    }
    return ['-', ''];
}

function cg_parent_contacts(array $row): array
{
    $contacts = [];
    foreach ([1, 2] as $idx) {
        $name = trim((string)($row['responsabile_' . $idx . '_tipo'] ?? '') . ' ' . (string)($row['responsabile_' . $idx . '_cognome'] ?? '') . ' ' . (string)($row['responsabile_' . $idx . '_nome'] ?? ''));
        $email = trim((string)($row['email_genitore_' . $idx] ?? ''));
        $phone = trim((string)($row['telefono_genitore_' . $idx] ?? ''));
        $parts = array_filter([$name, $email, $phone], static fn($value) => trim((string)$value) !== '');
        if ($parts) {
            $contacts[] = implode(' - ', $parts);
        }
    }
    return $contacts;
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
        .cg-table th.cg-sortable {
            cursor: pointer;
            user-select: none;
        }
        .cg-table th.cg-sortable:after {
            content: " \2195";
            color: #94a3b8;
            font-size: 11px;
        }
        .cg-table th.cg-sort-asc:after {
            content: " \2191";
            color: #337ab7;
        }
        .cg-table th.cg-sort-desc:after {
            content: " \2193";
            color: #337ab7;
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
            align-items: stretch;
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
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 190px);
            min-height: 520px;
        }
        .cg-modal-side h4 {
            margin-top: 0;
        }
        .cg-modal-grid .full {
            grid-column: 1 / -1;
        }
        .cg-subject-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin: 6px 0 8px;
        }
        .cg-subject-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #f8fafc;
            color: #17202f;
        }
        .cg-subject-chip button {
            border: 0;
            background: transparent;
            padding: 0;
            color: #8a1f1f;
            font-weight: 700;
            line-height: 1;
        }
        .cg-subject-add {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 8px;
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
            background: #eef3f8;
            max-height: 260px;
            overflow: auto;
        }
        #cg_incontri_summary {
            flex: 0 0 auto;
        }
        #cg_history_box {
            flex: 1 1 auto;
            min-height: 390px;
            max-height: none;
        }
        .cg-history-item {
            border: 1px solid #d8e0ea;
            border-left: 5px solid #64748b;
            background: #fff7ed;
            padding: 10px 12px;
            margin-bottom: 10px;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, .08);
        }
        .cg-history-item:nth-child(5n+1) {
            background: #fff7ed;
            border-color: #fed7aa;
            border-left-color: #f97316;
        }
        .cg-history-item:nth-child(5n+2) {
            background: #ecfeff;
            border-color: #a5f3fc;
            border-left-color: #0891b2;
        }
        .cg-history-item:nth-child(5n+3) {
            background: #f0fdf4;
            border-color: #bbf7d0;
            border-left-color: #16a34a;
        }
        .cg-history-item:nth-child(5n+4) {
            background: #f5f3ff;
            border-color: #ddd6fe;
            border-left-color: #7c3aed;
        }
        .cg-history-item:nth-child(5n) {
            background: #fefce8;
            border-color: #fde68a;
            border-left-color: #ca8a04;
        }
        .cg-history-head {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            font-weight: 700;
            align-items: flex-start;
            margin-bottom: 5px;
        }
        .cg-history-date {
            flex: 0 0 auto;
            border-radius: 999px;
            background: #1d4ed8;
            color: #fff;
            padding: 3px 8px;
            font-size: 12px;
            font-weight: 800;
            box-shadow: 0 1px 2px rgba(29, 78, 216, .25);
        }
        .cg-row-detail {
            color: #475569;
            font-size: 12px;
            margin-top: 3px;
        }
        .cg-row-requested {
            background: #fff7ed !important;
        }
        .cg-alert-badge {
            display: inline-block;
            margin-top: 5px;
            border-radius: 4px;
            background: #f97316;
            color: #fff;
            padding: 3px 7px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .02em;
        }
        .cg-linked-badge {
            display: inline-block;
            margin-top: 5px;
            border-radius: 4px;
            background: #0f766e;
            color: #fff;
            padding: 3px 7px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .02em;
        }
        .cg-filter {
            max-width: 440px;
        }
        .cg-row-hidden {
            display: none !important;
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
        .cg-incontri-table td {
            vertical-align: top !important;
        }
        .cg-incontri-note {
            white-space: pre-wrap;
            min-width: 420px;
        }
        .cg-incontri-files {
            min-width: 190px;
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
                max-height: none;
                min-height: 0;
            }
            #cg_history_box {
                min-height: 260px;
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
            <div class="btn-group">
                <form method="post" style="display:inline;" onsubmit="return confirm('Eliminare le pratiche Entrate/Uscite create automaticamente dai colloqui e scollegarle dai colloqui?');">
                    <input type="hidden" name="action" value="cleanup_fake_movimenti">
                    <button type="submit" class="btn btn-warning btn-lg">
                        <span class="glyphicon glyphicon-erase"></span> Ripulisci pratiche false
                    </button>
                </form>
                <form method="post" style="display:inline;" onsubmit="return confirm('Eliminare le righe duplicate identiche dallo storico colloqui? Verrà mantenuta la prima occorrenza.');">
                    <input type="hidden" name="action" value="cleanup_duplicate_events">
                    <button type="submit" class="btn btn-info btn-lg">
                        <span class="glyphicon glyphicon-filter"></span> Ripulisci doppioni storico
                    </button>
                </form>
                <form method="post" style="display:inline;" onsubmit="return confirm('Riallineare gli stati delle pratiche collegate in base agli esiti dei colloqui gia registrati? Non verranno inviate nuove notifiche.');">
                    <input type="hidden" name="action" value="repropagate_outcomes">
                    <button type="submit" class="btn btn-success btn-lg">
                        <span class="glyphicon glyphicon-refresh"></span> Aggiorna stati pratiche
                    </button>
                </form>
                <button type="button" class="btn btn-primary btn-lg" id="newColloquioBtn">
                    <span class="glyphicon glyphicon-plus"></span> Nuovo colloquio
                </button>
            </div>
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
            <div class="row">
                <div class="col-sm-6"><strong>Colloqui registrati</strong></div>
                <div class="col-sm-6">
                    <div class="input-group input-group-sm cg-filter pull-right">
                        <span class="input-group-addon"><span class="glyphicon glyphicon-search"></span></span>
                        <input type="text" class="form-control" id="cg_table_filter" placeholder="Cerca studente, stato, scuola, note...">
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover cg-table">
                <thead>
                <tr>
                    <th class="cg-sortable cg-sort-asc" data-sort-index="0" data-sort-type="text">Studente</th>
                    <th class="cg-sortable" data-sort-index="1" data-sort-type="text">Ambito</th>
                    <th class="cg-sortable" data-sort-index="2" data-sort-type="text">Classe / destinazione</th>
                    <th class="cg-sortable" data-sort-index="3" data-sort-type="text">Stato</th>
                    <th class="cg-sortable" data-sort-index="4" data-sort-type="text">Esito</th>
                    <th class="cg-sortable" data-sort-index="5" data-sort-type="date">Richiesta</th>
                    <th class="cg-sortable" data-sort-index="6" data-sort-type="date">Appuntamento</th>
                    <th class="cg-sortable" data-sort-index="7" data-sort-type="text">Referente</th>
                    <th class="cg-sortable" data-sort-index="8" data-sort-type="text">Note</th>
                    <th class="cg-sortable" data-sort-index="9" data-sort-type="text">Allegato</th>
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
                    $student = genitoriColloquiUpperName(trim((string)($row['cognome'] ?? '') . ' ' . (string)($row['nome'] ?? '')));
                    $stato = (string)($row['stato'] ?? 'richiesto');
                    $allegato = cg_attachment_link($row);
                    $ricevutaLibri = cg_receipt_link($row);
                    $history = $colloquiHistory[intval($row['id'] ?? 0)] ?? [];
                    $incontri = $colloquiIncontri[intval($row['id'] ?? 0)] ?? [];
                    $lastIncontro = $incontri[0] ?? [];
                    $hasLinkedMovement = intval($row['id_movimento'] ?? 0) > 0;
                    $isMovementRequested = $hasLinkedMovement && $stato === 'richiesto';
                    [$destMain, $destDetail] = cg_destination_summary($row);
                    $parentContacts = cg_parent_contacts($row);
                    $searchText = implode(' ', array_filter([
                        $student,
                        $row['codice_fiscale'] ?? '',
                        implode(' ', $parentContacts),
                        $ambiti[$row['ambito'] ?? 'altro'] ?? ($row['ambito'] ?? ''),
                        $destMain,
                        $destDetail,
                        $stati[$stato] ?? $stato,
                        $esiti[$row['esito'] ?? ''] ?? ($row['esito'] ?? ''),
                        $row['referente'] ?? '',
                        $row['note'] ?? '',
                        $lastIncontro['note'] ?? '',
                        $hasLinkedMovement ? 'pratica movimenti collegata pratica iscrizione presente' : '',
                        !empty($row['studente_bocciato']) ? 'studente bocciato bocciato' : '',
                    ]));
                    ?>
                    <tr class="<?php echo $isMovementRequested ? 'cg-row-requested' : ''; ?>" data-search="<?php echo cg_h(strtolower($searchText)); ?>">
                        <td>
                            <div class="cg-student"><?php echo cg_h($student !== '' ? $student : 'Studente non indicato'); ?></div>
                            <div class="cg-muted">
                                <?php echo cg_h($row['codice_fiscale'] ?? ''); ?>
                                <?php if (trim((string)($row['classe'] ?? '')) !== '') : ?>
                                    · <?php echo cg_h($row['classe']); ?>
                                <?php endif; ?>
                            </div>
                            <?php foreach ($parentContacts as $contact) : ?>
                                <div class="cg-row-detail"><?php echo cg_h($contact); ?></div>
                            <?php endforeach; ?>
                            <?php if (!empty($row['studente_bocciato'])) : ?>
                                <div><span class="label label-danger">Studente bocciato</span></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo cg_h($ambiti[$row['ambito'] ?? 'altro'] ?? ($row['ambito'] ?? '')); ?></td>
                        <td>
                            <?php if (($row['ambito'] ?? '') === 'entrata') : ?>
                                <strong><?php echo cg_h($destMain); ?></strong>
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
                        <td>
                            <span class="label label-<?php echo cg_label_class($stato); ?>"><?php echo cg_h($stati[$stato] ?? $stato); ?></span>
                            <?php if ($isMovementRequested) : ?>
                                <div class="cg-alert-badge">COLLOQUIO RICHIESTO - da rispondere</div>
                            <?php endif; ?>
                            <?php if ($hasLinkedMovement) : ?>
                                <div class="cg-linked-badge">PRATICA ISCRIZIONE / MOVIMENTI PRESENTE</div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo cg_h($esiti[$row['esito'] ?? ''] ?? ($row['esito'] ?? '')); ?></td>
                        <td data-sort="<?php echo cg_h($row['richiesta_data'] ?? ''); ?>"><?php echo cg_h(cg_date_it($row['richiesta_data'] ?? '')); ?></td>
                        <td data-sort="<?php echo cg_h($row['appuntamento_at'] ?? ''); ?>"><?php echo cg_h(cg_date_it($row['appuntamento_at'] ?? '')); ?></td>
                        <td><?php echo cg_h($row['referente'] ?? ''); ?></td>
                        <td class="cg-notes">
                            <?php if ($incontri) : ?>
                                <strong><?php echo count($incontri); ?> colloqui registrati</strong>
                                <?php if (trim((string)($lastIncontro['note'] ?? '')) !== '') : ?>
                                    <div class="cg-row-detail"><?php echo cg_h($lastIncontro['note']); ?></div>
                                <?php endif; ?>
                            <?php else : ?>
                                <?php echo cg_h($row['note'] ?? ''); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $allegato !== '' ? $allegato : '<span class="cg-muted">-</span>'; ?></td>
                        <td>
                            <div class="cg-row-actions">
                                <?php if (intval($row['id_movimento'] ?? 0) > 0) : ?>
                                    <a class="btn btn-xs btn-info" href="movimentiStudenti.php?open_movimento_id=<?php echo intval($row['id_movimento']); ?>">
                                        Pratica
                                    </a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-xs btn-default editColloquioBtn"
                                    data-record='<?php echo cg_h(json_encode($row, JSON_UNESCAPED_UNICODE)); ?>'
                                    data-history='<?php echo cg_h(json_encode($history, JSON_UNESCAPED_UNICODE)); ?>'
                                    data-incontri='<?php echo cg_h(json_encode($incontri, JSON_UNESCAPED_UNICODE)); ?>'>
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
        <form method="post" enctype="multipart/form-data" class="modal-content" id="cg_colloquio_form">
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
                                            data-classe="<?php echo cg_h($opt['corso_studi'] ?? ''); ?>"
                                            data-indirizzo_iscrizione="<?php echo cg_h($opt['corso_studi'] ?? ''); ?>"
                                            data-id_istituto_provenienza=""
                                            data-scuola_provenienza="<?php echo cg_h($opt['scuola_provenienza'] ?? ''); ?>"
                                            data-responsabile_1_tipo="<?php echo cg_h($opt['responsabile_1_tipo'] ?? ''); ?>"
                                            data-responsabile_1_cognome="<?php echo cg_h($opt['responsabile_1_cognome'] ?? ''); ?>"
                                            data-responsabile_1_nome="<?php echo cg_h($opt['responsabile_1_nome'] ?? ''); ?>"
                                            data-email_genitore_1="<?php echo cg_h($opt['email_genitore_1'] ?? ''); ?>"
                                            data-telefono_genitore_1="<?php echo cg_h($opt['telefono_genitore_1'] ?? ''); ?>"
                                            data-responsabile_2_tipo="<?php echo cg_h($opt['responsabile_2_tipo'] ?? ''); ?>"
                                            data-responsabile_2_cognome="<?php echo cg_h($opt['responsabile_2_cognome'] ?? ''); ?>"
                                            data-responsabile_2_nome="<?php echo cg_h($opt['responsabile_2_nome'] ?? ''); ?>"
                                            data-email_genitore_2="<?php echo cg_h($opt['email_genitore_2'] ?? ''); ?>"
                                            data-telefono_genitore_2="<?php echo cg_h($opt['telefono_genitore_2'] ?? ''); ?>">
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
                                            data-classe="<?php echo cg_h(($opt['classe_origine'] ?? '') ?: ($opt['classe_richiesta'] ?? '')); ?>"
                                            data-anno_corso="<?php echo intval($opt['anno_corso'] ?? 0); ?>"
                                            data-classe_iscrizione="<?php echo cg_h($opt['classe_richiesta'] ?? ''); ?>"
                                            data-indirizzo_iscrizione="<?php echo cg_h($opt['indirizzo_destinazione'] ?? ''); ?>"
                                            data-id_istituto_provenienza="<?php echo intval($opt['id_istituto_provenienza'] ?? 0); ?>"
                                            data-scuola_provenienza="<?php echo cg_h($opt['scuola_provenienza'] ?? ''); ?>"
                                            data-indirizzo_provenienza="<?php echo cg_h($opt['indirizzo_provenienza'] ?? ''); ?>"
                                            data-scuola_destinazione="<?php echo cg_h($opt['scuola_destinazione'] ?? ''); ?>"
                                            data-indirizzo_destinazione="<?php echo cg_h($opt['indirizzo_destinazione'] ?? ''); ?>"
                                            data-responsabile_1_tipo="<?php echo cg_h($opt['responsabile_1_tipo'] ?? ''); ?>"
                                            data-responsabile_1_cognome="<?php echo cg_h($opt['responsabile_1_cognome'] ?? ''); ?>"
                                            data-responsabile_1_nome="<?php echo cg_h($opt['responsabile_1_nome'] ?? ''); ?>"
                                            data-email_genitore_1="<?php echo cg_h($opt['email_genitore_1'] ?? ''); ?>"
                                            data-telefono_genitore_1="<?php echo cg_h($opt['telefono_genitore_1'] ?? ''); ?>"
                                            data-responsabile_2_tipo="<?php echo cg_h($opt['responsabile_2_tipo'] ?? ''); ?>"
                                            data-responsabile_2_cognome="<?php echo cg_h($opt['responsabile_2_cognome'] ?? ''); ?>"
                                            data-responsabile_2_nome="<?php echo cg_h($opt['responsabile_2_nome'] ?? ''); ?>"
                                            data-email_genitore_2="<?php echo cg_h($opt['email_genitore_2'] ?? ''); ?>"
                                            data-telefono_genitore_2="<?php echo cg_h($opt['telefono_genitore_2'] ?? ''); ?>">
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
                        <label>Esito anno</label>
                        <div class="checkbox" style="margin-top:6px;">
                            <label>
                                <input type="checkbox" name="studente_bocciato" id="cg_studente_bocciato" value="1">
                                Studente bocciato
                            </label>
                        </div>
                    </div>
                    <div>
                        <label for="cg_richiesta_data">Data richiesta</label>
                        <input type="date" class="form-control" name="richiesta_data" id="cg_richiesta_data">
                    </div>
                    <div class="full">
                        <div class="cg-context-panel">
                            <h4>
                                <button type="button" class="btn btn-default btn-sm" id="cg_toggle_genitori">
                                    <span class="glyphicon glyphicon-user"></span> Dati genitori / responsabili
                                </button>
                            </h4>
                            <div class="row" id="cg_genitori_fields" style="display:none;">
                                <div class="col-sm-2"><label>Tipo 1</label><input class="form-control" name="responsabile_1_tipo" id="cg_responsabile_1_tipo" placeholder="Madre, padre..."></div>
                                <div class="col-sm-3"><label>Cognome 1</label><input class="form-control" name="responsabile_1_cognome" id="cg_responsabile_1_cognome"></div>
                                <div class="col-sm-3"><label>Nome 1</label><input class="form-control" name="responsabile_1_nome" id="cg_responsabile_1_nome"></div>
                                <div class="col-sm-2"><label>Email 1</label><input class="form-control" name="email_genitore_1" id="cg_email_genitore_1"></div>
                                <div class="col-sm-2"><label>Telefono 1</label><input class="form-control" name="telefono_genitore_1" id="cg_telefono_genitore_1"></div>
                                <div class="col-sm-2"><label>Tipo 2</label><input class="form-control" name="responsabile_2_tipo" id="cg_responsabile_2_tipo" placeholder="Madre, padre..."></div>
                                <div class="col-sm-3"><label>Cognome 2</label><input class="form-control" name="responsabile_2_cognome" id="cg_responsabile_2_cognome"></div>
                                <div class="col-sm-3"><label>Nome 2</label><input class="form-control" name="responsabile_2_nome" id="cg_responsabile_2_nome"></div>
                                <div class="col-sm-2"><label>Email 2</label><input class="form-control" name="email_genitore_2" id="cg_email_genitore_2"></div>
                                <div class="col-sm-2"><label>Telefono 2</label><input class="form-control" name="telefono_genitore_2" id="cg_telefono_genitore_2"></div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="appuntamento_data" id="cg_appuntamento_data">
                    <input type="hidden" name="appuntamento_ora" id="cg_appuntamento_ora">
                    <input type="hidden" name="stato" id="cg_stato" value="richiesto">
                    <input type="hidden" name="esito" id="cg_esito" value="">
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
                                <div class="col-sm-4">
                                    <label for="cg_classe">Classe origine</label>
                                    <input class="form-control" name="classe" id="cg_classe">
                                </div>
                                <div class="col-sm-8">
                                    <label for="cg_indirizzo_iscrizione">Indirizzo / percorso</label>
                                    <select class="form-control" name="indirizzo_iscrizione" id="cg_indirizzo_iscrizione">
                                        <option value="">Seleziona indirizzo</option>
                                        <?php foreach ($indirizziArrivo as $indirizzo) : ?>
                                            <?php $label = trim((string)($indirizzo['nome'] ?? '')); ?>
                                            <?php if ($label === '') { continue; } ?>
                                            <option value="<?php echo cg_h($label); ?>"><?php echo cg_h($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-sm-4">
                                    <label for="cg_gruppo_iscrizione">Gruppo</label>
                                    <input class="form-control" name="gruppo_iscrizione" id="cg_gruppo_iscrizione" placeholder="Es. tablet">
                                </div>
                                <div class="col-sm-8">
                                    <label for="cg_istituto_provenienza">Scuola di provenienza</label>
                                    <select class="form-control" name="id_istituto_provenienza" id="cg_istituto_provenienza">
                                        <option value="">Seleziona istituto</option>
                                        <option value="__altro__">ALTRO - scuola non presente</option>
                                        <?php foreach ($istitutiScuole as $istituto) : ?>
                                            <option value="<?php echo intval($istituto['id']); ?>"><?php echo cg_h($istituto['nome'] ?? ''); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input class="form-control" name="scuola_provenienza" id="cg_scuola_provenienza" style="margin-top:6px;" placeholder="Oppure scrivi la scuola se non e' in elenco">
                                </div>
                                <input type="hidden" name="indirizzo_provenienza" id="cg_indirizzo_provenienza">
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
                                <div class="col-sm-12">
                                    <label for="cg_referente_scuola_destinazione">Referente altra scuola</label>
                                    <input class="form-control" name="referente_scuola_destinazione" id="cg_referente_scuola_destinazione" placeholder="Nome, email o telefono referente scuola destinazione">
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
                        <label>
                            <input type="checkbox" id="cg_esami_attivi" value="1">
                            Esami integrativi
                        </label>
                        <div id="cg_esami_materie_list" class="cg-subject-list"></div>
                        <div class="cg-subject-add">
                            <select class="form-control" id="cg_esami_materie" disabled>
                                <option value="">Aggiungi materia...</option>
                                <?php foreach ($materieGestore as $materia): ?>
                                    <option value="<?php echo cg_h($materia['nome'] ?? ''); ?>"><?php echo cg_h($materia['nome'] ?? ''); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-default" id="cg_add_esame_materia" disabled>Aggiungi</button>
                        </div>
                        <input type="hidden" name="esami_integrativi" id="cg_esami">
                    </div>
                    <div class="full cg-entrata-extra">
                        <label>
                            <input type="checkbox" id="cg_carenze_attive" value="1">
                            Carenze da recuperare
                        </label>
                        <div id="cg_carenze_materie_list" class="cg-subject-list"></div>
                        <div class="cg-subject-add">
                            <select class="form-control" id="cg_carenze_materie" disabled>
                                <option value="">Aggiungi materia...</option>
                                <?php foreach ($materieGestore as $materia): ?>
                                    <option value="<?php echo cg_h($materia['nome'] ?? ''); ?>"><?php echo cg_h($materia['nome'] ?? ''); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-default" id="cg_add_carenza_materia" disabled>Aggiungi</button>
                        </div>
                        <input type="hidden" name="carenze_note" id="cg_carenze">
                    </div>
                    <div class="full">
                        <label for="cg_libri">Libri / materiali da prestare o restituire</label>
                        <textarea class="form-control" name="libri_note" id="cg_libri" rows="2"></textarea>
                    </div>
                    <div class="full">
                        <label for="cg_note">Note pratica generale</label>
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
                    <h4>Colloqui della pratica</h4>
                    <button type="button" class="btn btn-primary btn-block" id="cg_open_incontri_modal" disabled>
                        <span class="glyphicon glyphicon-comment"></span> Apri colloqui
                    </button>
                    <div class="cg-history-box" id="cg_incontri_summary" style="margin-top:10px;">
                        <span class="cg-muted">Salva la scheda generale, poi gestisci i colloqui.</span>
                    </div>
                    <hr>
                    <h4>Storico scheda</h4>
                        <div class="cg-history-box" id="cg_history_box">
                            <span class="cg-muted">Nessuno storico registrato.</span>
                        </div>
                    <p class="help-block">Ogni salvataggio del colloquio resta tracciato qui.</p>
                </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>
                <button type="submit" class="btn btn-success" name="action" value="save">Salva scheda generale</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="cgIncontriModal" tabindex="-1" role="dialog" aria-labelledby="cgIncontriModalTitle">
    <div class="modal-dialog cg-wide-modal" role="document">
        <form method="post" enctype="multipart/form-data" class="modal-content" id="cg_incontro_form">
            <input type="hidden" name="action" value="save_incontro">
            <input type="hidden" name="colloquio_id" id="cg_incontro_colloquio_id">
            <input type="hidden" name="incontro_id" id="cg_incontro_id">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Chiudi"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="cgIncontriModalTitle">Colloqui della pratica</h4>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered cg-incontri-table">
                        <thead>
                        <tr>
                            <th style="width:150px;">Data / ora</th>
                            <th>Dettaglio colloquio</th>
                            <th style="width:220px;">Allegati</th>
                        </tr>
                        </thead>
                        <tbody id="cg_incontri_box">
                        <tr><td colspan="3" class="cg-muted">Nessun colloquio registrato per questa scheda.</td></tr>
                        </tbody>
                    </table>
                </div>
                <hr>
                <h4>Nuovo colloquio</h4>
                <div class="row">
                    <div class="col-sm-3 form-group">
                        <label>Data colloquio</label>
                        <input type="date" class="form-control" name="incontro_data" id="cg_incontro_data">
                    </div>
                    <div class="col-sm-2 form-group">
                        <label>Ora</label>
                        <input type="time" class="form-control" name="incontro_ora" id="cg_incontro_ora">
                    </div>
                    <div class="col-sm-3 form-group">
                        <label for="cg_incontro_tipo">Tipo</label>
                        <select class="form-control" name="incontro_tipo" id="cg_incontro_tipo">
                            <option value="colloquio">Colloquio</option>
                            <option value="telefono">Telefonata</option>
                            <option value="mail">Mail</option>
                            <option value="incontro_scuola">Contatto altra scuola</option>
                            <option value="altro">Altro</option>
                        </select>
                    </div>
                    <div class="col-sm-4 form-group">
                        <label for="cg_incontro_esito">Esito colloquio</label>
                        <select class="form-control" name="incontro_esito" id="cg_incontro_esito">
                            <?php foreach ($esiti as $key => $label) : ?>
                                <option value="<?php echo cg_h($key); ?>"><?php echo cg_h($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 form-group">
                        <label for="cg_incontro_referente">Referente</label>
                        <input class="form-control" name="incontro_referente" id="cg_incontro_referente">
                    </div>
                    <div class="col-sm-6 form-group">
                        <label for="cg_incontro_partecipanti">Partecipanti</label>
                        <input class="form-control" name="incontro_partecipanti" id="cg_incontro_partecipanti" placeholder="Genitori, studente, referente scuola...">
                    </div>
                    <div class="col-sm-8 form-group">
                        <label for="cg_incontro_note">Dettagli colloquio</label>
                        <textarea class="form-control" name="incontro_note" id="cg_incontro_note" rows="6"></textarea>
                    </div>
                    <div class="col-sm-4 form-group">
                        <label for="cg_incontro_allegati">Allegati colloquio</label>
                        <input type="file" class="form-control" name="incontro_allegati[]" id="cg_incontro_allegati" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>
                <button type="submit" class="btn btn-primary" id="cg_save_incontro_btn">
                    <span class="glyphicon glyphicon-plus"></span> Aggiungi colloquio
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var cgEsiti = <?php echo json_encode($esiti, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    function setValue(id, value) {
        var el = document.getElementById(id);
        if (el) {
            el.value = value || '';
        }
    }
    function setSelectValueKeepingLegacy(id, value) {
        var el = document.getElementById(id);
        value = String(value || '').trim();
        if (!el) return;
        if (value !== '') {
            var found = Array.prototype.some.call(el.options, function (option) {
                return option.value === value;
            });
            if (!found) {
                var option = document.createElement('option');
                option.value = value;
                option.textContent = value;
                el.appendChild(option);
            }
        }
        el.value = value;
    }
    function setChecked(id, value) {
        var el = document.getElementById(id);
        if (el) {
            el.checked = !!(parseInt(value || 0, 10));
        }
    }
    var cgParentFieldIds = [
        'cg_responsabile_1_tipo', 'cg_responsabile_1_cognome', 'cg_responsabile_1_nome', 'cg_email_genitore_1', 'cg_telefono_genitore_1',
        'cg_responsabile_2_tipo', 'cg_responsabile_2_cognome', 'cg_responsabile_2_nome', 'cg_email_genitore_2', 'cg_telefono_genitore_2'
    ];
    function setParentsVisible(visible) {
        var box = document.getElementById('cg_genitori_fields');
        if (box) box.style.display = visible ? '' : 'none';
    }
    function refreshParentsVisibility() {
        var hasValue = cgParentFieldIds.some(function (id) {
            var field = document.getElementById(id);
            return field && String(field.value || '').trim() !== '';
        });
        setParentsVisible(hasValue);
    }
    function updateConditionalText(checkboxId, textareaId) {
        var checkbox = document.getElementById(checkboxId);
        var hidden = document.getElementById(textareaId);
        var selectId = textareaId === 'cg_esami' ? 'cg_esami_materie' : 'cg_carenze_materie';
        var select = document.getElementById(selectId);
        var addButton = document.getElementById(textareaId === 'cg_esami' ? 'cg_add_esame_materia' : 'cg_add_carenza_materia');
        if (!checkbox || !hidden || !select) return;
        select.disabled = !checkbox.checked;
        if (addButton) addButton.disabled = !checkbox.checked;
        if (!checkbox.checked) {
            setSubjectValues(textareaId === 'cg_esami' ? 'esami' : 'carenze', []);
        } else {
            syncConditionalSubjects(textareaId);
        }
    }
    function setConditionalText(checkboxId, textareaId, value) {
        setValue(textareaId, value || '');
        setChecked(checkboxId, String(value || '').trim() !== '' ? 1 : 0);
        var selectId = textareaId === 'cg_esami' ? 'cg_esami_materie' : 'cg_carenze_materie';
        setSubjectSelectValues(selectId, value || '');
        updateConditionalText(checkboxId, textareaId);
    }
    function splitSubjectList(value) {
        var text = String(value || '').replace(/\r?\n/g, ' ').trim();
        if (!text) return [];
        var parts = text.indexOf('|') !== -1 ? text.split('|') : text.split(';');
        return parts.map(function (item) {
            return item.replace(/\s+/g, ' ').trim();
        }).filter(Boolean);
    }
    function setSubjectSelectValues(selectId, value) {
        setSubjectValues(selectId === 'cg_esami_materie' ? 'esami' : 'carenze', splitSubjectList(value));
    }
    function subjectHiddenId(kind) {
        return kind === 'esami' ? 'cg_esami' : 'cg_carenze';
    }
    function subjectListId(kind) {
        return kind === 'esami' ? 'cg_esami_materie_list' : 'cg_carenze_materie_list';
    }
    function subjectSelectId(kind) {
        return kind === 'esami' ? 'cg_esami_materie' : 'cg_carenze_materie';
    }
    function subjectValues(kind) {
        return splitSubjectList(document.getElementById(subjectHiddenId(kind)).value);
    }
    function setSubjectValues(kind, values) {
        var normalized = [];
        values.forEach(function (value) {
            var text = String(value || '').replace(/\s+/g, ' ').trim();
            if (!text) return;
            if (!normalized.some(function (item) { return item.toUpperCase() === text.toUpperCase(); })) {
                normalized.push(text);
            }
        });
        setValue(subjectHiddenId(kind), normalized.join(' | '));
        renderSubjectList(kind);
    }
    function renderSubjectList(kind) {
        var box = document.getElementById(subjectListId(kind));
        if (!box) return;
        var values = subjectValues(kind);
        if (!values.length) {
            box.innerHTML = '<span class="cg-muted">Nessuna materia selezionata.</span>';
            return;
        }
        box.innerHTML = '';
        values.forEach(function (value, index) {
            var chip = document.createElement('span');
            chip.className = 'cg-subject-chip';
            var text = document.createElement('span');
            text.textContent = value;
            var remove = document.createElement('button');
            remove.type = 'button';
            remove.textContent = 'x';
            remove.title = 'Rimuovi materia';
            remove.addEventListener('click', function () {
                var next = subjectValues(kind);
                next.splice(index, 1);
                setSubjectValues(kind, next);
            });
            chip.appendChild(text);
            chip.appendChild(remove);
            box.appendChild(chip);
        });
    }
    function addSubject(kind) {
        var select = document.getElementById(subjectSelectId(kind));
        if (!select || !select.value) return;
        var values = subjectValues(kind);
        values.push(select.value);
        setSubjectValues(kind, values);
        select.value = '';
    }
    function syncConditionalSubjects(hiddenId) {
        setSubjectValues(hiddenId === 'cg_esami' ? 'esami' : 'carenze', subjectValues(hiddenId === 'cg_esami' ? 'esami' : 'carenze'));
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
                + '<div class="cg-history-head"><span>' + escapeHtml(event.descrizione || event.tipo_evento || 'Evento') + '</span><span class="cg-history-date">' + escapeHtml(formatHistoryDate(event.created_at)) + '</span></div>'
                + '<div class="cg-muted">Operatore: ' + escapeHtml(event.created_by || '') + '</div>'
                + (detail ? '<div class="cg-row-detail">' + escapeHtml(detail) + '</div>' : '')
                + (data.note ? '<div class="cg-notes">' + escapeHtml(data.note) + '</div>' : '')
                + '</div>';
        }).join('');
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
            var eventId = Number(event.id || 0);
            var detail = [
                data.ambito ? 'Ambito: ' + data.ambito : '',
                data.stato ? 'Stato: ' + data.stato : '',
                data.esito ? 'Esito: ' + data.esito : '',
                data.classe_iscrizione ? 'Classe: ' + data.classe_iscrizione : '',
                data.scuola_destinazione ? 'Scuola destinazione: ' + data.scuola_destinazione : '',
                data.esami_integrativi ? 'Esami integrativi: ' + data.esami_integrativi : '',
                data.carenze_note ? 'Carenze: ' + data.carenze_note : ''
            ].filter(Boolean).join(' - ');
            var attachment = event.allegato_path
                ? '<div style="margin-top:6px;"><a class="btn btn-xs btn-default" target="_blank" href="../' + escapeHtml(event.allegato_path) + '"><span class="glyphicon glyphicon-paperclip"></span> ' + escapeHtml(event.allegato_original_name || 'Allegato') + '</a></div>'
                : '';
            var editBox = '<div class="cg-history-edit" id="cg_history_edit_' + eventId + '" style="display:none;margin-top:8px;">'
                + '<label>Descrizione</label><input class="form-control input-sm" id="cg_history_desc_' + eventId + '" value="' + escapeHtml(event.descrizione || event.tipo_evento || 'Evento') + '">'
                + '<label style="margin-top:6px;">Note pratica generale</label><textarea class="form-control input-sm" rows="3" id="cg_history_note_' + eventId + '">' + escapeHtml(data.note || '') + '</textarea>'
                + '<label style="margin-top:6px;">Libri / materiali</label><textarea class="form-control input-sm" rows="2" id="cg_history_libri_' + eventId + '">' + escapeHtml(data.libri_note || '') + '</textarea>'
                + '<div style="margin-top:6px;"><button type="button" class="btn btn-primary btn-xs" onclick="submitHistoryUpdate(' + eventId + ')">Salva correzione</button> '
                + '<button type="button" class="btn btn-default btn-xs" onclick="toggleHistoryEdit(' + eventId + ', false)">Annulla</button></div>'
                + '</div>';
            return '<div class="cg-history-item">'
                + '<div class="cg-history-head"><span>' + escapeHtml(event.descrizione || event.tipo_evento || 'Evento') + '</span><span class="cg-history-date">' + escapeHtml(formatHistoryDate(event.created_at)) + '</span></div>'
                + '<div class="cg-muted">Operatore: ' + escapeHtml(event.created_by || '') + '</div>'
                + (detail ? '<div class="cg-row-detail">' + escapeHtml(detail) + '</div>' : '')
                + (data.note ? '<div class="cg-notes"><strong>Note pratica generale:</strong><br>' + escapeHtml(data.note) + '</div>' : '')
                + (data.libri_note ? '<div class="cg-notes"><strong>Libri / materiali:</strong><br>' + escapeHtml(data.libri_note) + '</div>' : '')
                + attachment
                + '<div style="margin-top:6px;"><button type="button" class="btn btn-default btn-xs" onclick="toggleHistoryEdit(' + eventId + ', true)">Modifica</button> '
                + '<button type="button" class="btn btn-danger btn-xs" onclick="deleteHistoryEvent(' + eventId + ')">Elimina</button></div>'
                + editBox
                + '</div>';
        }).join('');
    }
    function submitHiddenPost(fields) {
        var form = document.createElement('form');
        form.method = 'post';
        form.style.display = 'none';
        Object.keys(fields).forEach(function (name) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = fields[name];
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
    }
    function toggleHistoryEdit(id, show) {
        var box = document.getElementById('cg_history_edit_' + id);
        if (box) box.style.display = show ? 'block' : 'none';
    }
    function deleteHistoryEvent(id) {
        if (!window.confirm('Eliminare questa riga dello storico?')) return;
        submitHiddenPost({action: 'delete_event', event_id: id});
    }
    function submitHistoryUpdate(id) {
        submitHiddenPost({
            action: 'update_event',
            event_id: id,
            event_descrizione: document.getElementById('cg_history_desc_' + id).value || '',
            event_note: document.getElementById('cg_history_note_' + id).value || '',
            event_libri_note: document.getElementById('cg_history_libri_' + id).value || ''
        });
    }
    window.toggleHistoryEdit = toggleHistoryEdit;
    window.deleteHistoryEvent = deleteHistoryEvent;
    window.submitHistoryUpdate = submitHistoryUpdate;
    function renderIncontri(incontri) {
        var box = document.getElementById('cg_incontri_box');
        var summary = document.getElementById('cg_incontri_summary');
        incontri = Array.isArray(incontri) ? incontri : [];
        if (!incontri.length) {
            if (box) box.innerHTML = '<tr><td colspan="3" class="cg-muted">Nessun colloquio registrato per questa scheda.</td></tr>';
            if (summary) summary.innerHTML = '<span class="cg-muted">Nessun colloquio registrato.</span>';
            return;
        }
        if (summary) {
            var last = incontri[0] || {};
            summary.innerHTML = '<strong>' + incontri.length + ' colloqui registrati</strong>'
                + (last.note ? '<div class="cg-row-detail">' + escapeHtml(last.note) + '</div>' : '');
        }
        if (!box) return;
        box.innerHTML = incontri.map(function (item) {
            var allegati = Array.isArray(item.allegati) ? item.allegati : [];
            var links = allegati.map(function (allegato) {
                return '<a class="btn btn-xs btn-default" target="_blank" rel="noopener" href="../' + escapeHtml(allegato.path_file || '') + '"><span class="glyphicon glyphicon-paperclip"></span> ' + escapeHtml(allegato.nome_file || 'Allegato') + '</a>';
            }).join('<br>');
            var details = [
                item.tipo ? 'Tipo: ' + item.tipo : '',
                item.referente ? 'Referente: ' + item.referente : '',
                item.partecipanti ? 'Partecipanti: ' + item.partecipanti : '',
                item.esito ? 'Esito: ' + item.esito : ''
            ].filter(Boolean).join(' - ');
            return '<tr>'
                + '<td>' + escapeHtml(formatHistoryDate(item.incontro_at || item.created_at)) + '</td>'
                + '<td class="cg-incontri-note">'
                    + (details ? '<div class="cg-muted">' + escapeHtml(details) + '</div>' : '')
                    + (item.note ? escapeHtml(item.note) : '<span class="cg-muted">Nessun dettaglio testuale.</span>')
                + '</td>'
                + '<td class="cg-incontri-files">' + (links || '<span class="cg-muted">-</span>') + '</td>'
                + '</tr>';
        }).join('');
    }
    function resetIncontroForm() {
        setValue('cg_incontro_id', '');
        setValue('cg_incontro_data', '');
        setValue('cg_incontro_ora', '');
        setValue('cg_incontro_tipo', 'colloquio');
        setValue('cg_incontro_referente', '');
        setValue('cg_incontro_partecipanti', '');
        setValue('cg_incontro_esito', '');
        setValue('cg_incontro_note', '');
        setValue('cg_incontro_allegati', '');
    }
    function setIncontroEnabled(enabled) {
        document.getElementById('cg_save_incontro_btn').disabled = !enabled;
        document.getElementById('cg_open_incontri_modal').disabled = !enabled;
    }
    function updateAmbitoPanels() {
        var ambito = document.getElementById('cg_ambito').value;
        document.getElementById('cg_entrata_panel').style.display = ambito === 'entrata' ? '' : 'none';
        document.getElementById('cg_uscita_panel').style.display = ambito === 'uscita' ? '' : 'none';
        Array.prototype.forEach.call(document.querySelectorAll('.cg-entrata-extra'), function (el) {
            el.style.display = ambito === 'uscita' ? 'none' : '';
        });
        updateEsitoOptions();
    }
    function updateEsitoOptions() {
        var ambito = document.getElementById('cg_ambito').value;
        ['cg_incontro_esito'].forEach(function (id) {
            var select = document.getElementById(id);
            if (!select) return;
            var current = select.value || '';
            select.innerHTML = '';
            Object.keys(cgEsiti).forEach(function (value) {
                if (ambito === 'entrata' && value === 'uscita_ok') return;
                if (ambito === 'uscita' && value === 'ingresso_ok') return;
                var option = document.createElement('option');
                option.value = value;
                option.textContent = cgEsiti[value];
                select.appendChild(option);
            });
            select.value = Array.prototype.some.call(select.options, function (option) {
                return option.value === current;
            }) ? current : '';
        });
    }
    function syncGeneralOutcomeFromIncontro() {
        var incontroEsito = document.getElementById('cg_incontro_esito');
        if (!incontroEsito) return;
        setValue('cg_esito', incontroEsito.value || '');
        if (incontroEsito.value) {
            setValue('cg_stato', 'svolto');
        }
    }
    function clearLinkedPrefill() {
        setValue('cg_ambito', 'uscita');
        setValue('cg_cognome', '');
        setValue('cg_nome', '');
        setValue('cg_cf', '');
        setChecked('cg_studente_bocciato', 0);
        setValue('cg_classe', '');
        setValue('cg_anno_corso', '');
        setValue('cg_classe_iscrizione', '');
        setValue('cg_indirizzo_iscrizione', '');
        setValue('cg_gruppo_iscrizione', '');
        setValue('cg_istituto_provenienza', '');
        setValue('cg_scuola_provenienza', '');
        setValue('cg_indirizzo_provenienza', '');
        setValue('cg_istituto_destinazione', '');
        setValue('cg_scuola_destinazione', '');
        setValue('cg_indirizzo_destinazione', '');
        setValue('cg_referente_scuola_destinazione', '');
        setValue('cg_responsabile_1_tipo', '');
        setValue('cg_responsabile_1_cognome', '');
        setValue('cg_responsabile_1_nome', '');
        setValue('cg_email_genitore_1', '');
        setValue('cg_telefono_genitore_1', '');
        setValue('cg_responsabile_2_tipo', '');
        setValue('cg_responsabile_2_cognome', '');
        setValue('cg_responsabile_2_nome', '');
        setValue('cg_email_genitore_2', '');
        setValue('cg_telefono_genitore_2', '');
        updateSourceSchoolOther('');
        updateAmbitoPanels();
    }
    function syncSourceSchoolHidden() {
        var select = document.getElementById('cg_istituto_provenienza');
        var input = document.getElementById('cg_scuola_provenienza');
        if (!select || !input) return;
        if (select.value && select.value !== '__altro__') {
            input.value = select.options[select.selectedIndex].textContent || '';
        }
    }
    function updateSourceSchoolOther(value) {
        var select = document.getElementById('cg_istituto_provenienza');
        var input = document.getElementById('cg_scuola_provenienza');
        if (!select || !input) return;
        var text = String(value !== undefined ? value : input.value || '').trim();
        if (!select.value && text !== '') {
            Array.prototype.some.call(select.options, function (option) {
                if (option.value && option.value !== '__altro__' && option.textContent.trim() === text) {
                    select.value = option.value;
                    return true;
                }
                return false;
            });
            if (!select.value) {
                select.value = '__altro__';
                input.value = text;
            }
        }
        if (select.value === '__altro__') {
            input.style.display = 'block';
        } else {
            syncSourceSchoolHidden();
            input.style.display = select.value ? 'none' : 'block';
            if (!select.value && value === '') {
                input.value = '';
            }
        }
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
        setValue('cg_responsabile_1_tipo', '');
        setValue('cg_responsabile_1_cognome', '');
        setValue('cg_responsabile_1_nome', '');
        setValue('cg_email_genitore_1', '');
        setValue('cg_telefono_genitore_1', '');
        setValue('cg_responsabile_2_tipo', '');
        setValue('cg_responsabile_2_cognome', '');
        setValue('cg_responsabile_2_nome', '');
        setValue('cg_email_genitore_2', '');
        setValue('cg_telefono_genitore_2', '');
        setValue('cg_anno_corso', '');
        setValue('cg_classe_iscrizione', '');
        setValue('cg_indirizzo_iscrizione', '');
        setValue('cg_gruppo_iscrizione', '');
        setValue('cg_istituto_provenienza', '');
        setValue('cg_scuola_provenienza', '');
        setValue('cg_indirizzo_provenienza', '');
        setValue('cg_istituto_destinazione', '');
        setValue('cg_scuola_destinazione', '');
        setValue('cg_indirizzo_destinazione', '');
        setValue('cg_referente_scuola_destinazione', '');
        setChecked('cg_libri_da_restituire', 0);
        setValue('cg_libri_restituiti_at', '');
        setValue('cg_ricevuta_libri', '');
        setValue('cg_richiesta_data', '');
        setValue('cg_appuntamento_data', '');
        setValue('cg_appuntamento_ora', '');
        setValue('cg_stato', 'richiesto');
        setValue('cg_esito', '');
        setConditionalText('cg_esami_attivi', 'cg_esami', '');
        setConditionalText('cg_carenze_attive', 'cg_carenze', '');
        setValue('cg_libri', '');
        setValue('cg_note', '');
        setValue('cg_allegato', '');
        document.getElementById('cg_allegato_attuale').textContent = '';
        document.getElementById('cg_ricevuta_libri_attuale').textContent = '';
        setValue('cg_incontro_colloquio_id', '');
        resetIncontroForm();
        setIncontroEnabled(false);
        renderIncontri([]);
        renderHistory([]);
        updateSourceSchoolOther('');
        setParentsVisible(false);
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
        setValue('cg_anno_corso', option.getAttribute('data-anno_corso') || '');
        setValue('cg_classe_iscrizione', option.getAttribute('data-classe_iscrizione') || '');
        setSelectValueKeepingLegacy('cg_indirizzo_iscrizione', option.getAttribute('data-indirizzo_iscrizione') || '');
        setValue('cg_istituto_provenienza', option.getAttribute('data-id_istituto_provenienza') || '');
        setValue('cg_scuola_provenienza', option.getAttribute('data-scuola_provenienza') || '');
        setValue('cg_indirizzo_provenienza', option.getAttribute('data-indirizzo_provenienza') || '');
        setValue('cg_scuola_destinazione', option.getAttribute('data-scuola_destinazione') || '');
        setValue('cg_indirizzo_destinazione', option.getAttribute('data-indirizzo_destinazione') || '');
        setValue('cg_responsabile_1_tipo', option.getAttribute('data-responsabile_1_tipo') || '');
        setValue('cg_responsabile_1_cognome', option.getAttribute('data-responsabile_1_cognome') || '');
        setValue('cg_responsabile_1_nome', option.getAttribute('data-responsabile_1_nome') || '');
        setValue('cg_email_genitore_1', option.getAttribute('data-email_genitore_1') || '');
        setValue('cg_telefono_genitore_1', option.getAttribute('data-telefono_genitore_1') || '');
        setValue('cg_responsabile_2_tipo', option.getAttribute('data-responsabile_2_tipo') || '');
        setValue('cg_responsabile_2_cognome', option.getAttribute('data-responsabile_2_cognome') || '');
        setValue('cg_responsabile_2_nome', option.getAttribute('data-responsabile_2_nome') || '');
        setValue('cg_email_genitore_2', option.getAttribute('data-email_genitore_2') || '');
        setValue('cg_telefono_genitore_2', option.getAttribute('data-telefono_genitore_2') || '');
        refreshParentsVisibility();
        if (option.getAttribute('data-ambito') === 'entrata') {
            setValue('cg_classe_iscrizione', option.getAttribute('data-classe_iscrizione') || option.getAttribute('data-classe') || '');
        }
        updateSourceSchoolOther(option.getAttribute('data-scuola_provenienza') || '');
        updateAmbitoPanels();
    }
    document.getElementById('newColloquioBtn').addEventListener('click', function () {
        resetForm();
        $('#colloquioModal').modal('show');
    });
    document.getElementById('cg_open_incontri_modal').addEventListener('click', function () {
        $('#cgIncontriModal').modal('show');
    });
    document.getElementById('cg_pratica').addEventListener('change', function () {
        if (!this.value) {
            clearLinkedPrefill();
            return;
        }
        fillFromOption(this.options[this.selectedIndex]);
    });
    document.getElementById('cg_movimento').addEventListener('change', function () {
        if (!this.value) {
            clearLinkedPrefill();
            return;
        }
        fillFromOption(this.options[this.selectedIndex]);
    });
    document.getElementById('cg_ambito').addEventListener('change', updateAmbitoPanels);
    document.getElementById('cg_toggle_genitori').addEventListener('click', function () {
        var box = document.getElementById('cg_genitori_fields');
        setParentsVisible(!(box && box.style.display !== 'none'));
    });
    document.getElementById('cg_esami_attivi').addEventListener('change', function () {
        updateConditionalText('cg_esami_attivi', 'cg_esami');
    });
    document.getElementById('cg_carenze_attive').addEventListener('change', function () {
        updateConditionalText('cg_carenze_attive', 'cg_carenze');
    });
    document.getElementById('cg_add_esame_materia').addEventListener('click', function () {
        addSubject('esami');
    });
    document.getElementById('cg_add_carenza_materia').addEventListener('click', function () {
        addSubject('carenze');
    });
    document.getElementById('cg_istituto_provenienza').addEventListener('change', function () {
        updateSourceSchoolOther('');
    });
    function normalizeName(value) {
        value = String(value || '').trim().toLowerCase();
        if (value.normalize) {
            value = value.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
        return value.replace(/\s+/g, ' ');
    }
    function findExistingEntryMovementByName(cognome, nome) {
        cognome = normalizeName(cognome);
        nome = normalizeName(nome);
        if (!cognome || !nome) return null;
        var select = document.getElementById('cg_movimento');
        if (!select) return null;
        for (var i = 0; i < select.options.length; i++) {
            var option = select.options[i];
            if (!option.value || option.getAttribute('data-ambito') !== 'entrata') {
                continue;
            }
            if (normalizeName(option.getAttribute('data-cognome')) === cognome
                && normalizeName(option.getAttribute('data-nome')) === nome) {
                return option;
            }
        }
        return null;
    }
    document.getElementById('cg_colloquio_form').addEventListener('submit', function (event) {
        var submitter = event.submitter || document.activeElement;
        if (submitter && submitter.name === 'action' && submitter.value === 'save_incontro') {
            return;
        }
        syncGeneralOutcomeFromIncontro();
        syncConditionalSubjects('cg_esami');
        syncConditionalSubjects('cg_carenze');
        setValue('cg_cognome', String(document.getElementById('cg_cognome').value || '').toUpperCase());
        setValue('cg_nome', String(document.getElementById('cg_nome').value || '').toUpperCase());
        if (document.getElementById('cg_id').value
            || document.getElementById('cg_pratica').value
            || document.getElementById('cg_movimento').value
            || document.getElementById('cg_ambito').value !== 'entrata') {
            return;
        }
        var match = findExistingEntryMovementByName(
            document.getElementById('cg_cognome').value,
            document.getElementById('cg_nome').value
        );
        if (!match) {
            return;
        }
        var attach = window.confirm(
            'Esiste gia una pratica di entrata per ' + (match.getAttribute('data-cognome') || '') + ' ' + (match.getAttribute('data-nome') || '') + '.\n\n'
            + 'OK = aggancia questo colloquio alla pratica esistente.\n'
            + 'Annulla = crea comunque una nuova pratica.'
        );
        if (attach) {
            document.getElementById('cg_movimento').value = match.value;
        }
    });
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
            setChecked('cg_studente_bocciato', row.studente_bocciato || 0);
            setValue('cg_classe', row.classe || '');
            setValue('cg_responsabile_1_tipo', row.responsabile_1_tipo || '');
            setValue('cg_responsabile_1_cognome', row.responsabile_1_cognome || '');
            setValue('cg_responsabile_1_nome', row.responsabile_1_nome || '');
            setValue('cg_email_genitore_1', row.email_genitore_1 || '');
            setValue('cg_telefono_genitore_1', row.telefono_genitore_1 || '');
            setValue('cg_responsabile_2_tipo', row.responsabile_2_tipo || '');
            setValue('cg_responsabile_2_cognome', row.responsabile_2_cognome || '');
            setValue('cg_responsabile_2_nome', row.responsabile_2_nome || '');
            setValue('cg_email_genitore_2', row.email_genitore_2 || '');
            setValue('cg_telefono_genitore_2', row.telefono_genitore_2 || '');
            setValue('cg_anno_corso', row.anno_corso || '');
            setValue('cg_classe_iscrizione', row.classe_iscrizione || '');
            setSelectValueKeepingLegacy('cg_indirizzo_iscrizione', row.indirizzo_iscrizione || '');
            setValue('cg_gruppo_iscrizione', row.gruppo_iscrizione || '');
            setValue('cg_istituto_provenienza', row.id_istituto_provenienza || '');
            setValue('cg_scuola_provenienza', row.scuola_provenienza || '');
            setValue('cg_indirizzo_provenienza', row.indirizzo_provenienza || '');
            setValue('cg_istituto_destinazione', row.id_istituto_destinazione || '');
            setValue('cg_scuola_destinazione', row.scuola_destinazione || '');
            setValue('cg_indirizzo_destinazione', row.indirizzo_destinazione || '');
            setValue('cg_referente_scuola_destinazione', row.referente_scuola_destinazione || '');
            setChecked('cg_libri_da_restituire', row.libri_da_restituire || 0);
            setValue('cg_libri_restituiti_at', row.libri_restituiti_at || '');
            setValue('cg_richiesta_data', row.richiesta_data || '');
            if (row.appuntamento_at) {
                setValue('cg_appuntamento_data', String(row.appuntamento_at).substring(0, 10));
                setValue('cg_appuntamento_ora', String(row.appuntamento_at).substring(11, 16));
            }
            setValue('cg_stato', (row.stato === 'approvato' || row.stato === 'non_approvato') ? 'svolto' : (row.stato || 'richiesto'));
            setValue('cg_esito', row.esito || '');
            setConditionalText('cg_esami_attivi', 'cg_esami', row.esami_integrativi || '');
            setConditionalText('cg_carenze_attive', 'cg_carenze', row.carenze_note || '');
            setValue('cg_libri', row.libri_note || '');
            setValue('cg_note', row.note || '');
            if (row.allegato_original_name) {
                document.getElementById('cg_allegato_attuale').textContent = 'Allegato attuale: ' + row.allegato_original_name;
            }
            if (row.ricevuta_libri_original_name) {
                document.getElementById('cg_ricevuta_libri_attuale').textContent = 'Ricevuta attuale: ' + row.ricevuta_libri_original_name;
            }
            setValue('cg_incontro_colloquio_id', row.id || '');
            resetIncontroForm();
            setIncontroEnabled(!!row.id);
            renderIncontri(JSON.parse(this.getAttribute('data-incontri') || '[]'));
            renderHistory(JSON.parse(this.getAttribute('data-history') || '[]'));
            updateSourceSchoolOther(row.scuola_provenienza || '');
            refreshParentsVisibility();
            updateAmbitoPanels();
            setValue('cg_incontro_esito', row.esito || '');
            $('#colloquioModal').modal('show');
        });
    });
    document.getElementById('cg_incontro_esito').addEventListener('change', syncGeneralOutcomeFromIncontro);
    var openMovementId = new URLSearchParams(window.location.search).get('movimento');
    if (openMovementId) {
        Array.prototype.some.call(document.querySelectorAll('.editColloquioBtn'), function (btn) {
            var row = JSON.parse(btn.getAttribute('data-record') || '{}');
            if (String(row.id_movimento || '') === String(openMovementId)) {
                btn.click();
                return true;
            }
            return false;
        });
    }
    updateAmbitoPanels();
    updateSourceSchoolOther('');
    var tableFilter = document.getElementById('cg_table_filter');
    if (tableFilter) {
        tableFilter.addEventListener('input', function () {
            var query = String(this.value || '').toLowerCase().trim();
            Array.prototype.forEach.call(document.querySelectorAll('.cg-table tbody tr[data-search]'), function (row) {
                var text = row.getAttribute('data-search') || '';
                row.classList.toggle('cg-row-hidden', query !== '' && text.indexOf(query) === -1);
            });
        });
    }
    function cgSortValue(row, index, type) {
        var cell = row.children[index];
        if (!cell) return '';
        var value = cell.getAttribute('data-sort') || cell.textContent || '';
        value = String(value).trim().toLowerCase();
        if (type === 'date') {
            return value || '0000-00-00 00:00:00';
        }
        return value.normalize ? value.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : value;
    }
    Array.prototype.forEach.call(document.querySelectorAll('.cg-table th.cg-sortable'), function (header) {
        header.addEventListener('click', function () {
            var table = header.closest('table');
            var tbody = table ? table.querySelector('tbody') : null;
            if (!tbody) return;
            var index = parseInt(header.getAttribute('data-sort-index') || '0', 10);
            var type = header.getAttribute('data-sort-type') || 'text';
            var direction = header.classList.contains('cg-sort-asc') ? 'desc' : 'asc';
            Array.prototype.forEach.call(table.querySelectorAll('th.cg-sortable'), function (th) {
                th.classList.remove('cg-sort-asc', 'cg-sort-desc');
            });
            header.classList.add(direction === 'asc' ? 'cg-sort-asc' : 'cg-sort-desc');
            var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr[data-search]'));
            rows.sort(function (a, b) {
                var av = cgSortValue(a, index, type);
                var bv = cgSortValue(b, index, type);
                if (av < bv) return direction === 'asc' ? -1 : 1;
                if (av > bv) return direction === 'asc' ? 1 : -1;
                return 0;
            });
            rows.forEach(function (row) {
                tbody.appendChild(row);
            });
        });
    });
})();
</script>
</body>
</html>
