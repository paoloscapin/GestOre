<?php

require_once '../common/checkSession.php';
require_once '../common/studentiMovimentiLib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

studentiMovimentiEnsureTables();

$canOpenColloqui = (string)($__utente_ruolo ?? '') === 'admin';
$message = '';
$error = '';
$syncResult = null;
$autoSyncResult = null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = trim((string)($_POST['action'] ?? 'save'));
        if ($action === 'sync_bocciati') {
            $syncResult = studentiMovimentiSyncBocciatiFromTabelloni(studentiMovimentiCurrentYearId());
            $message = 'Bocciati aggiornati dai tabelloni.';
        } elseif ($action === 'sync_iscrizioni') {
            $syncResult = studentiMovimentiSyncCambioScuolaDaIscrizioni();
            $message = 'Cambi scuola aggiornati dalle iscrizioni.';
        } elseif ($action === 'delete') {
            $deleted = studentiMovimentiDeletePractice((int)($_POST['id'] ?? 0));
            $message = $deleted ? 'Pratica eliminata.' : 'Pratica non trovata.';
        } elseif ($action === 'delete_event') {
            $deleted = studentiMovimentiDeleteEvent((int)($_POST['event_id'] ?? 0));
            $message = $deleted ? 'Riga storico eliminata.' : 'Riga storico non trovata.';
        } elseif ($action === 'update_event') {
            $updated = studentiMovimentiUpdateEvent(
                (int)($_POST['event_id'] ?? 0),
                (string)($_POST['event_descrizione'] ?? ''),
                (string)($_POST['event_note'] ?? '')
            );
            $message = $updated ? 'Riga storico aggiornata.' : 'Riga storico non trovata.';
        } elseif ($action === 'add_event_attachment') {
            $uploaded = studentiMovimentiAttachFileToEvent(
                (int)($_POST['event_id'] ?? 0),
                $_FILES['event_allegato'] ?? [],
                (string)($_POST['tipo_allegato'] ?? 'documento')
            );
            $message = $uploaded ? 'Allegato aggiunto alla riga storico.' : 'Allegato non caricato.';
        } elseif ($action === 'delete_attachment') {
            $deleted = studentiMovimentiDeleteAttachment((int)($_POST['attachment_id'] ?? 0));
            $message = $deleted ? 'Allegato eliminato.' : 'Allegato non trovato.';
        } else {
            $practiceId = studentiMovimentiSavePractice($_POST);
            if (!empty($_FILES['allegato'])) {
                studentiMovimentiAttachFiles($practiceId, $_FILES['allegato'], trim((string)($_POST['tipo_allegato'] ?? 'documento')));
            }
            $message = 'Pratica salvata.';
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && ($_GET['autosync'] ?? '1') !== '0') {
    try {
        $autoSyncResult = studentiMovimentiSyncBocciatiFromTabelloni(studentiMovimentiCurrentYearId());
        $syncResult = $autoSyncResult;
        $iscrizioniSync = studentiMovimentiSyncCambioScuolaDaIscrizioni();
        if (intval($autoSyncResult['created'] ?? 0) > 0) {
            $message = 'Bocciati aggiornati automaticamente dai tabelloni: creati ' . intval($autoSyncResult['created']) . '.';
        } elseif (intval($iscrizioniSync['created'] ?? 0) > 0 || intval($iscrizioniSync['updated'] ?? 0) > 0) {
            $message = 'Cambi scuola iscrizioni sincronizzati: creati ' . intval($iscrizioniSync['created'] ?? 0) . ', aggiornati ' . intval($iscrizioniSync['updated'] ?? 0) . '.';
        }
    } catch (Throwable $e) {
        if ($error === '') {
            $error = 'Aggiornamento automatico bocciati non riuscito: ' . $e->getMessage();
        }
    }
}

$tipi = studentiMovimentiTipi();
$stati = studentiMovimentiStati();
$istitutiScuole = scuoleIstitutiAll();
$materieGestore = dbGetAll("
    SELECT id, nome
    FROM materia
    ORDER BY nome ASC
") ?: [];
$indirizziGestore = iscrizioniPrimeGestoreAddressOptions();
try {
    $entrateIscrizioniSync = studentiMovimentiEnsureIscrizioniForEntrate();
    if (intval($entrateIscrizioniSync['linked'] ?? 0) > 0 && $message === '') {
        $message = 'Pratiche iscrizione collegate/create per entrate prime-terze: ' . intval($entrateIscrizioniSync['linked']) . '.';
    }
    if (!empty($entrateIscrizioniSync['errors']) && $error === '') {
        $error = 'Alcune entrate prime-terze non hanno ancora la pratica iscrizione: ' . implode(' | ', $entrateIscrizioniSync['errors']);
    }
} catch (Throwable $e) {
    if ($error === '') {
        $error = 'Controllo pratiche iscrizione entrate non riuscito: ' . $e->getMessage();
    }
}
$activeSection = trim((string)($_GET['sezione'] ?? 'uscite'));
if (!in_array($activeSection, ['uscite', 'entrate'], true)) {
    $activeSection = 'uscite';
}
$activeYear = intval($_GET['anno'] ?? 0);
if ($activeYear < 1 || $activeYear > 5) {
    $activeYear = 1;
}
$openMovementId = intval($_GET['open_movimento_id'] ?? 0);
$openMovementFound = false;
if ($openMovementId > 0) {
    $openMovement = dbGetFirst("
        SELECT tipo_pratica, anno_corso
        FROM studenti_movimenti_pratiche
        WHERE id = " . dbI($openMovementId) . "
        LIMIT 1
    ");
    if ($openMovement) {
        $openMovementFound = true;
        $activeSection = (($openMovement['tipo_pratica'] ?? '') === 'entrata') ? 'entrate' : 'uscite';
        $movementYear = intval($openMovement['anno_corso'] ?? 0);
        $activeYear = ($movementYear >= 1 && $movementYear <= 5) ? $movementYear : 1;
    }
}

$pratiche = dbGetAll("
    SELECT p.*,
           s.cognome AS studente_cognome,
           s.nome AS studente_nome,
           s.codice_fiscale AS studente_cf,
           s.id AS studente_id,
           c.classe AS classe_corrente,
           ind_gestore.nome AS indirizzo_gestore_nome,
           COUNT(a.id) AS allegati_count
    FROM studenti_movimenti_pratiche p
    LEFT JOIN studente s ON s.id = p.id_studente
    LEFT JOIN studente_frequenta sf ON sf.id = (
        SELECT sf2.id
        FROM studente_frequenta sf2
        WHERE sf2.id_studente = s.id
          AND sf2.id_anno_scolastico = " . dbI(studentiMovimentiCurrentYearId()) . "
        ORDER BY sf2.id DESC
        LIMIT 1
    )
    LEFT JOIN classi c ON c.id = sf.id_classe
    LEFT JOIN indirizzo ind_gestore ON ind_gestore.id = p.id_indirizzo_gestore
    LEFT JOIN studenti_movimenti_allegati a ON a.id_pratica = p.id
    GROUP BY p.id
    ORDER BY COALESCE(p.anno_corso, 99) ASC,
             COALESCE(p.cognome, s.cognome, '') ASC,
             COALESCE(p.nome, s.nome, '') ASC,
             p.updated_at DESC
") ?: [];

$allegati = [];
if (!empty($pratiche)) {
    $ids = array_map(static function ($row) { return intval($row['id']); }, $pratiche);
    $rows = dbGetAll("
        SELECT *
        FROM studenti_movimenti_allegati
        WHERE id_pratica IN (" . implode(',', $ids) . ")
        ORDER BY created_at DESC, id DESC
    ") ?: [];
    foreach ($rows as $row) {
        $allegati[intval($row['id_pratica'] ?? 0)][] = $row;
    }
}

$storico = [];
if (!empty($pratiche)) {
    $storico = studentiMovimentiHistoryForPractices(array_map(static function ($row) { return intval($row['id']); }, $pratiche));
}

$colloquiCounts = [];
if (!empty($pratiche) && dbGetValue("SHOW TABLES LIKE 'genitori_colloqui'")) {
    $ids = array_map(static function ($row) { return intval($row['id']); }, $pratiche);
    $rows = dbGetAll("
        SELECT id_movimento, COUNT(*) AS totale
        FROM genitori_colloqui
        WHERE id_movimento IN (" . implode(',', $ids) . ")
        GROUP BY id_movimento
    ") ?: [];
    foreach ($rows as $row) {
        $colloquiCounts[intval($row['id_movimento'] ?? 0)] = intval($row['totale'] ?? 0);
    }
}

$currentStudents = dbGetAll("
    SELECT s.id, s.cognome, s.nome, s.codice_fiscale, c.classe
    FROM studente s
    INNER JOIN studente_frequenta sf ON sf.id_studente = s.id
      AND sf.id_anno_scolastico = " . dbI(studentiMovimentiCurrentYearId()) . "
    INNER JOIN classi c ON c.id = sf.id_classe
    WHERE COALESCE(s.attivo, 1) = 1
    ORDER BY c.classe ASC, s.cognome ASC, s.nome ASC
") ?: [];

$grouped = [
    'uscite' => [1 => [], 2 => [], 3 => [], 4 => [], 5 => []],
    'entrate' => [1 => [], 2 => [], 3 => [], 4 => [], 5 => []],
];
foreach ($pratiche as $pratica) {
    $section = ($pratica['tipo_pratica'] ?? '') === 'entrata' ? 'entrate' : 'uscite';
    $year = intval($pratica['anno_corso'] ?? 0);
    if ($year < 1 || $year > 5) {
        continue;
    }
    $grouped[$section][$year][] = $pratica;
}
if ($activeYear === 0 && !$openMovementFound) {
    foreach ([1, 2, 3, 4, 5] as $year) {
        if (!empty($grouped[$activeSection][$year])) {
            $activeYear = $year;
            break;
        }
    }
    if ($activeYear === 0) {
        $activeYear = 1;
    }
}

function ms_selected($a, $b): string
{
    return (string)$a === (string)$b ? 'selected' : '';
}

function ms_active($a, $b): string
{
    return (string)$a === (string)$b ? 'active' : '';
}

function ms_label_class(string $state): string
{
    if (in_array($state, ['reiscrizione_confermata', 'nulla_osta_inviato', 'idoneo_iscrizione', 'chiusa'], true)) {
        return 'success';
    }
    if (in_array($state, ['cambia_scuola', 'si_ritira', 'esami_integrativi', 'documenti_in_verifica'], true)) {
        return 'warning';
    }
    if (in_array($state, ['non_idoneo', 'annullata'], true)) {
        return 'danger';
    }
    return 'default';
}

function ms_year_label(int $year): string
{
    $labels = [1 => 'Prime', 2 => 'Seconde', 3 => 'Terze', 4 => 'Quarte', 5 => 'Quinte'];
    return $labels[$year] ?? 'Prime';
}

function ms_practice_name(array $row): string
{
    $name = trim((string)($row['cognome'] ?: $row['studente_cognome']) . ' ' . (string)($row['nome'] ?: $row['studente_nome']));
    return $name !== '' ? $name : 'Studente provvisorio';
}

function ms_data_attr($value): string
{
    return studentiMovimentiH($value);
}

function ms_iscrizione_pratica_url(array $row): string
{
    $praticaId = intval($row['id_pratica_iscrizione'] ?? 0);
    if ($praticaId <= 0) {
        return '';
    }
    $tipoIscrizione = intval($row['anno_corso'] ?? 0) === 3 ? 'terze' : 'prime';
    return 'iscrizioniPrimeDomande.php?tipo_iscrizione=' . rawurlencode($tipoIscrizione)
        . '&stato=tutte&open_pratica_id=' . $praticaId
        . '#pratica-' . $praticaId;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Entrate e uscite studenti</title>
    <meta charset="UTF-8">
    <?php require_once '../common/header-common.php'; ?>
    <?php require_once '../common/style.php'; ?>
    <style>
        .ms-topbar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 10px;
            align-items: center;
            margin-bottom: 12px;
        }
        .ms-tabs {
            margin-bottom: 10px;
        }
        .ms-year-tabs {
            margin-bottom: 12px;
        }
        .ms-table-wrap {
            border: 1px solid #d8dee8;
            border-radius: 4px;
            background: #fff;
            overflow-x: auto;
        }
        .ms-table {
            margin-bottom: 0;
            min-width: 980px;
        }
        .ms-table th {
            background: #f8fafc;
            color: #17202f;
            white-space: nowrap;
        }
        .ms-name {
            font-weight: 700;
            color: #102a43;
        }
        .ms-muted {
            color: #60718a;
            font-size: 12px;
        }
        .ms-attachment-link {
            display: inline-block;
            margin-right: 7px;
            margin-bottom: 3px;
        }
        .ms-modal-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        #msPracticeModal .modal-dialog {
            width: 95%;
            max-width: 1180px;
        }
        .ms-modal-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(330px, .9fr);
            gap: 14px;
            align-items: start;
        }
        .ms-history {
            border: 1px solid #d8dee8;
            border-radius: 4px;
            background: #f8fafc;
            padding: 10px;
            max-height: calc(100vh - 240px);
            overflow: auto;
        }
        .ms-history-event {
            border: 1px solid #dbe4ef;
            border-left: 5px solid #2f80ed;
            border-radius: 4px;
            background: #fff;
            padding: 8px 10px;
            margin-bottom: 8px;
        }
        .ms-history-head {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            flex-wrap: wrap;
            font-weight: 700;
        }
        .ms-history-meta { color: #60718a; font-size: 12px; margin-top: 3px; }
        .ms-history-note { margin-top: 6px; white-space: pre-wrap; }
        .ms-history-attachment-link {
            max-width: 100%;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: break-word;
            text-align: left;
        }
        .ms-history-attachment-row {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            align-items: flex-start;
            margin-top: 4px;
        }
        .ms-history-attachment-row .ms-history-attachment-link {
            flex: 1 1 220px;
        }
        .ms-modal-wide {
            grid-column: span 2;
        }
        .ms-section-title {
            grid-column: span 2;
            margin: 6px 0 -2px;
            padding: 7px 9px;
            border-left: 4px solid #2f80ed;
            background: #f4f8fc;
            color: #17202f;
            font-weight: 700;
        }
        .ms-section-title.ms-nullosta {
            border-left-color: #b7791f;
            background: #fff8ec;
        }
        .ms-subject-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 8px;
        }
        .ms-subject-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #f8fafc;
            color: #17202f;
        }
        .ms-subject-chip button {
            border: 0;
            background: transparent;
            padding: 0;
            color: #8a1f1f;
            font-weight: 700;
            line-height: 1;
        }
        .ms-subject-add {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 8px;
        }
        .ms-empty {
            padding: 28px;
            text-align: center;
            color: #60718a;
        }
        @media (max-width: 800px) {
            #msPracticeModal .modal-dialog {
                width: auto;
                max-width: none;
            }
            .ms-modal-grid {
                grid-template-columns: 1fr;
            }
            .ms-modal-layout {
                grid-template-columns: 1fr;
            }
            .ms-history {
                max-height: none;
            }
            .ms-modal-wide {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>
<?php require_once '../common/header-didattica.php'; ?>
<div class="container-fluid">
    <div class="panel panel-lightblue4">
        <div class="panel-heading"><span class="glyphicon glyphicon-transfer"></span>&emsp;Entrate e uscite studenti</div>
        <div class="panel-body">
            <?php if ($message !== ''): ?>
                <div class="alert alert-success">
                    <?php echo studentiMovimentiH($message); ?>
                    <?php if (is_array($syncResult)): ?>
                        Letti <?php echo intval($syncResult['read'] ?? 0); ?> record,
                        creati <?php echo intval($syncResult['created'] ?? 0); ?>,
                        gia presenti <?php echo intval($syncResult['existing'] ?? 0); ?>,
                        aggiornati <?php echo intval(($syncResult['updated'] ?? 0) + ($syncResult['updated_existing'] ?? 0)); ?>.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo studentiMovimentiH($error); ?></div><?php endif; ?>
            <?php if (is_array($syncResult)): ?>
                <div class="alert alert-warning">
                    <strong>Controllo tabelloni:</strong>
                    trovati <?php echo intval($syncResult['read'] ?? 0); ?> bocciati/non ammessi.
                    <?php $byYear = (array)($syncResult['by_year'] ?? []); ?>
                    Prime <?php echo intval($byYear[1] ?? 0); ?>,
                    Seconde <?php echo intval($byYear[2] ?? 0); ?>,
                    Terze <?php echo intval($byYear[3] ?? 0); ?>,
                    Quarte <?php echo intval($byYear[4] ?? 0); ?>,
                    Quinte <?php echo intval($byYear[5] ?? 0); ?>.
                    <?php if (intval($syncResult['without_gestore_id'] ?? 0) > 0): ?>
                        <br>
                        <strong><?php echo intval($syncResult['without_gestore_id']); ?> righe non importate</strong>
                        perche non hanno l'aggancio allo studente GestOre.
                        <?php if (!empty($syncResult['without_gestore_examples'])): ?>
                            Esempi: <?php echo studentiMovimentiH(implode(', ', (array)$syncResult['without_gestore_examples'])); ?>.
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="ms-topbar">
                <div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="msOpenNew('uscita')">
                        <span class="glyphicon glyphicon-plus"></span>&ensp;Aggiungi uscita
                    </button>
                    <button type="button" class="btn btn-success btn-sm" onclick="msOpenNew('entrata')">
                        <span class="glyphicon glyphicon-plus"></span>&ensp;Aggiungi entrata
                    </button>
                </div>
                <form method="post" style="margin:0;" onsubmit="return confirm('Importare nelle uscite tutti i bocciati presenti nei tabelloni finali?');">
                    <input type="hidden" name="action" value="sync_bocciati">
                    <button type="submit" class="btn btn-warning btn-sm">
                        <span class="glyphicon glyphicon-refresh"></span>&ensp;Aggiorna bocciati da tabelloni
                    </button>
                </form>
                <form method="post" style="margin:0;" onsubmit="return confirm('Sincronizzare nelle uscite i cambi scuola registrati nelle iscrizioni prime/terze?');">
                    <input type="hidden" name="action" value="sync_iscrizioni">
                    <button type="submit" class="btn btn-info btn-sm">
                        <span class="glyphicon glyphicon-transfer"></span>&ensp;Aggiorna cambi scuola da iscrizioni
                    </button>
                </form>
            </div>

            <ul class="nav nav-tabs ms-tabs">
                <li class="<?php echo ms_active($activeSection, 'uscite'); ?>">
                    <a href="movimentiStudenti.php?sezione=uscite">Uscite</a>
                </li>
                <li class="<?php echo ms_active($activeSection, 'entrate'); ?>">
                    <a href="movimentiStudenti.php?sezione=entrate">Entrate</a>
                </li>
            </ul>

            <ul class="nav nav-pills ms-year-tabs">
                <?php foreach ([1, 2, 3, 4, 5] as $year): ?>
                    <li class="<?php echo ms_active($activeYear, $year); ?>">
                        <a href="movimentiStudenti.php?sezione=<?php echo studentiMovimentiH($activeSection); ?>&anno=<?php echo $year; ?>">
                            <?php echo studentiMovimentiH(ms_year_label($year)); ?>
                            <span class="badge"><?php echo count($grouped[$activeSection][$year] ?? []); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php $rows = $grouped[$activeSection][$activeYear] ?? []; ?>
            <div class="ms-table-wrap">
                <table class="table table-striped table-hover ms-table">
                    <thead>
                    <tr>
                        <th>Studente</th>
                        <th>Classe</th>
                        <th>Tipo</th>
                        <th>Stato</th>
                        <th>Scuola</th>
                        <th>Esami integrativi</th>
                        <th>Carenze</th>
                        <th>Allegati</th>
                        <th>Aggiornata</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $id = intval($row['id'] ?? 0); ?>
                        <tr>
                            <td>
                                <div class="ms-name"><?php echo studentiMovimentiH(studentiMovimentiUpperName(ms_practice_name($row))); ?></div>
                                <div class="ms-muted"><?php echo studentiMovimentiH($row['codice_fiscale'] ?: $row['studente_cf'] ?: ''); ?></div>
                            </td>
                            <td>
                                <?php $classeBase = trim((string)($row['classe_origine'] ?: $row['classe_corrente'] ?: '')); ?>
                                <?php if ($classeBase !== ''): ?>
                                    <strong><?php echo studentiMovimentiH($classeBase); ?></strong>
                                <?php endif; ?>
                                <?php if (($row['classe_richiesta'] ?? '') !== ''): ?>
                                    <div class="ms-muted">richiesta <?php echo studentiMovimentiH($row['classe_richiesta']); ?></div>
                                <?php elseif ($classeBase === ''): ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo studentiMovimentiH($tipi[$row['tipo_pratica']] ?? $row['tipo_pratica']); ?></td>
                            <td>
                                <span class="label label-<?php echo ms_label_class((string)$row['stato_pratica']); ?>">
                                    <?php echo studentiMovimentiH($stati[$row['stato_pratica']] ?? $row['stato_pratica']); ?>
                                </span>
                                <?php if (!empty($row['doppio_bocciato'])): ?>
                                    <div><span class="label label-danger">Doppio bocciato</span></div>
                                <?php endif; ?>
                                <?php if (!empty($row['doppio_bocciato_non_consecutivo'])): ?>
                                    <div><span class="label label-info">Doppio non consecutivo</span></div>
                                <?php endif; ?>
                                <?php if (!empty($row['bocciato_altra_scuola'])): ?>
                                    <div><span class="label label-warning">Bocciato altra scuola</span></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($activeSection === 'entrate'): ?>
                                    <?php echo studentiMovimentiH($row['scuola_provenienza'] ?: '-'); ?>
                                <?php else: ?>
                                    <?php echo studentiMovimentiH($row['scuola_destinazione'] ?: '-'); ?>
                                    <?php if (($row['indirizzo_destinazione'] ?? '') !== ''): ?>
                                        <div class="ms-muted"><?php echo studentiMovimentiH($row['indirizzo_destinazione']); ?></div>
                                    <?php endif; ?>
                                    <?php if (intval($row['id_indirizzo_gestore'] ?? 0) > 0 && trim((string)($row['indirizzo_gestore_nome'] ?? '')) !== ''): ?>
                                        <div class="ms-muted">GestOre: <?php echo studentiMovimentiH($row['indirizzo_gestore_nome']); ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo intval($row['esami_integrativi'] ?? 0) ? '<span class="label label-warning">Si</span>' : '<span class="text-muted">No</span>'; ?></td>
                            <td><?php echo intval($row['carenze_presenti'] ?? 0) ? '<span class="label label-warning">Si</span>' : '<span class="text-muted">No</span>'; ?></td>
                            <td>
                                <?php if (empty($allegati[$id])): ?>
                                    <span class="text-muted">nessuno</span>
                                <?php else: ?>
                                    <?php foreach ($allegati[$id] as $allegato): ?>
                                        <a class="ms-attachment-link" target="_blank" href="<?php echo studentiMovimentiH(studentiMovimentiPublicPath((string)$allegato['path_file'])); ?>">
                                            <span class="glyphicon glyphicon-file"></span>
                                            <?php echo studentiMovimentiH($allegato['nome_file']); ?>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo studentiMovimentiH(studentiMovimentiFormatDateTimeIt((string)($row['updated_at'] ?? ''))); ?></td>
                            <td class="text-right">
                                <button type="button"
                                        class="btn btn-default btn-xs ms-edit"
                                        data-id="<?php echo $id; ?>"
                                        data-fonte="<?php echo ms_data_attr($row['fonte'] ?? 'manuale'); ?>"
                                        data-id_pratica_iscrizione="<?php echo intval($row['id_pratica_iscrizione'] ?? 0); ?>"
                                        data-id_cambio_scuola_iscrizione="<?php echo intval($row['id_cambio_scuola_iscrizione'] ?? 0); ?>"
                                        data-tipo_pratica="<?php echo ms_data_attr($row['tipo_pratica'] ?? ''); ?>"
                                        data-stato_pratica="<?php echo ms_data_attr($row['stato_pratica'] ?? ''); ?>"
                                        data-id_studente="<?php echo intval($row['id_studente'] ?? 0); ?>"
                                        data-cognome="<?php echo ms_data_attr($row['cognome'] ?: $row['studente_cognome']); ?>"
                                        data-nome="<?php echo ms_data_attr($row['nome'] ?: $row['studente_nome']); ?>"
                                        data-codice_fiscale="<?php echo ms_data_attr($row['codice_fiscale'] ?: $row['studente_cf']); ?>"
                                        data-anno_corso="<?php echo intval($row['anno_corso'] ?? 0); ?>"
                                        data-classe_origine="<?php echo ms_data_attr($row['classe_origine'] ?: $row['classe_corrente']); ?>"
                                        data-classe_richiesta="<?php echo ms_data_attr($row['classe_richiesta'] ?? ''); ?>"
                                        data-id_istituto_provenienza="<?php echo intval($row['id_istituto_provenienza'] ?? 0); ?>"
                                        data-scuola_provenienza="<?php echo ms_data_attr($row['scuola_provenienza'] ?? ''); ?>"
                                        data-indirizzo_provenienza="<?php echo ms_data_attr($row['indirizzo_provenienza'] ?? ''); ?>"
                                        data-bocciato_altra_scuola="<?php echo intval($row['bocciato_altra_scuola'] ?? 0); ?>"
                                        data-id_istituto_destinazione="<?php echo intval($row['id_istituto_destinazione'] ?? 0); ?>"
                                        data-scuola_destinazione="<?php echo ms_data_attr($row['scuola_destinazione'] ?? ''); ?>"
                                        data-indirizzo_destinazione="<?php echo ms_data_attr($row['indirizzo_destinazione'] ?? ''); ?>"
                                        data-id_indirizzo_gestore="<?php echo intval($row['id_indirizzo_gestore'] ?? 0); ?>"
                                        data-doppio_bocciato="<?php echo intval($row['doppio_bocciato'] ?? 0); ?>"
                                        data-doppio_bocciato_non_consecutivo="<?php echo intval($row['doppio_bocciato_non_consecutivo'] ?? 0); ?>"
                                        data-esami_integrativi="<?php echo intval($row['esami_integrativi'] ?? 0); ?>"
                                        data-esami_integrativi_note="<?php echo ms_data_attr($row['esami_integrativi_note'] ?? ''); ?>"
                                        data-carenze_presenti="<?php echo intval($row['carenze_presenti'] ?? 0); ?>"
                                        data-carenze_note="<?php echo ms_data_attr($row['carenze_note'] ?? ''); ?>"
                                        data-note="<?php echo ms_data_attr($row['note'] ?? ''); ?>">
                                    Dettaglio
                                </button>
                                <?php $iscrizioneUrl = ms_iscrizione_pratica_url($row); ?>
                                <?php if ($activeSection === 'entrate' && $iscrizioneUrl !== ''): ?>
                                    <a class="btn btn-success btn-xs" href="<?php echo studentiMovimentiH($iscrizioneUrl); ?>">
                                        <span class="glyphicon glyphicon-folder-open"></span> Pratica iscrizione
                                    </a>
                                <?php elseif ($activeSection === 'entrate' && in_array(intval($row['anno_corso'] ?? 0), [1, 3], true)): ?>
                                    <span class="label label-warning">Pratica iscrizione mancante</span>
                                <?php endif; ?>
                                <?php if ($canOpenColloqui && !empty($colloquiCounts[$id])): ?>
                                    <a class="btn btn-info btn-xs" href="colloquiGenitori.php?movimento=<?php echo $id; ?>">Colloqui <?php echo intval($colloquiCounts[$id]); ?></a>
                                <?php endif; ?>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Eliminare definitivamente questa pratica?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                                    <button type="submit" class="btn btn-danger btn-xs">Elimina</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="10"><div class="ms-empty">Nessuna pratica in questa sezione.</div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="msPracticeModal" tabindex="-1" role="dialog" aria-labelledby="msPracticeTitle">
    <div class="modal-dialog modal-lg" role="document">
        <form method="post" enctype="multipart/form-data" class="modal-content" id="msPracticeForm">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="ms_id" value="">
            <input type="hidden" name="fonte" id="ms_fonte" value="manuale">
            <input type="hidden" name="id_pratica_iscrizione" id="ms_id_pratica_iscrizione" value="">
            <input type="hidden" name="id_cambio_scuola_iscrizione" id="ms_id_cambio_scuola_iscrizione" value="">
            <input type="hidden" name="note" id="ms_note" value="">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Chiudi"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="msPracticeTitle">Pratica studente</h4>
            </div>
            <div class="modal-body">
                <div class="ms-modal-layout">
                    <div>
                <div class="ms-modal-grid">
                    <div class="form-group">
                        <label>Tipo pratica</label>
                        <select name="tipo_pratica" id="ms_tipo_pratica" class="form-control input-sm">
                            <?php foreach ($tipi as $key => $label): ?>
                                <option value="<?php echo studentiMovimentiH($key); ?>"><?php echo studentiMovimentiH($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Stato</label>
                        <select name="stato_pratica" id="ms_stato_pratica" class="form-control input-sm">
                            <?php foreach ($stati as $key => $label): ?>
                                <option value="<?php echo studentiMovimentiH($key); ?>" data-state-key="<?php echo studentiMovimentiH($key); ?>"><?php echo studentiMovimentiH($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group ms-only-uscita">
                        <label style="display:block;">Doppio bocciato</label>
                        <label class="checkbox-inline">
                            <input type="checkbox" name="doppio_bocciato" id="ms_doppio_bocciato" value="1">
                            deve cambiare scuola o ritirarsi
                        </label>
                        <label class="checkbox-inline">
                            <input type="checkbox" name="doppio_bocciato_non_consecutivo" id="ms_doppio_bocciato_non_consecutivo" value="1">
                            non consecutivo, puo reiscriversi
                        </label>
                    </div>
                    <div class="form-group ms-modal-wide">
                        <label>Studente gia presente in GestOre</label>
                        <select name="id_studente" id="ms_id_studente" class="form-control input-sm">
                            <option value="">Studente provvisorio / non ancora presente</option>
                            <?php foreach ($currentStudents as $student): ?>
                                <option value="<?php echo intval($student['id']); ?>"
                                        data-cognome="<?php echo ms_data_attr($student['cognome'] ?? ''); ?>"
                                        data-nome="<?php echo ms_data_attr($student['nome'] ?? ''); ?>"
                                        data-cf="<?php echo ms_data_attr($student['codice_fiscale'] ?? ''); ?>"
                                        data-classe="<?php echo ms_data_attr($student['classe'] ?? ''); ?>"
                                        data-anno="<?php echo intval(studentiMovimentiClassYear((string)($student['classe'] ?? '')) ?? 0); ?>">
                                    <?php echo studentiMovimentiH(($student['classe'] ?? '') . ' - ' . ($student['cognome'] ?? '') . ' ' . ($student['nome'] ?? '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Cognome</label>
                        <input type="text" name="cognome" id="ms_cognome" class="form-control input-sm">
                    </div>
                    <div class="form-group">
                        <label>Nome</label>
                        <input type="text" name="nome" id="ms_nome" class="form-control input-sm">
                    </div>
                    <div class="form-group">
                        <label>Codice fiscale</label>
                        <input type="text" name="codice_fiscale" id="ms_codice_fiscale" class="form-control input-sm">
                    </div>
                    <div class="form-group">
                        <label>Anno</label>
                        <select name="anno_corso" id="ms_anno_corso" class="form-control input-sm">
                            <option value="">Seleziona anno</option>
                            <?php foreach ([1, 2, 3, 4, 5] as $year): ?>
                                <option value="<?php echo $year; ?>"><?php echo studentiMovimentiH(ms_year_label($year)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Classe origine</label>
                        <input type="text" name="classe_origine" id="ms_classe_origine" class="form-control input-sm">
                    </div>
                    <div class="form-group">
                        <label>Classe richiesta</label>
                        <input type="text" name="classe_richiesta" id="ms_classe_richiesta" class="form-control input-sm">
                    </div>
                    <div class="form-group ms-only-entrata">
                        <label>Scuola provenienza</label>
                        <input type="hidden" name="scuola_provenienza" id="ms_scuola_provenienza">
                        <select name="id_istituto_provenienza" id="ms_id_istituto_provenienza" class="form-control input-sm">
                            <option value="">Seleziona istituto</option>
                            <option value="__altro__">ALTRO - scuola non presente</option>
                            <?php foreach ($istitutiScuole as $istituto): ?>
                                <option value="<?php echo intval($istituto['id']); ?>"><?php echo studentiMovimentiH($istituto['nome'] ?? ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" id="ms_scuola_provenienza_altro" class="form-control input-sm" style="display:none;margin-top:6px;" placeholder="Scrivi scuola provenienza">
                        <div id="ms_scuola_provenienza_libera" class="help-block" style="display:none;"></div>
                    </div>
                    <input type="hidden" name="indirizzo_provenienza" id="ms_indirizzo_provenienza" value="">
                    <div class="form-group ms-only-entrata">
                        <label style="display:block;">Esito anno precedente</label>
                        <label class="checkbox-inline">
                            <input type="checkbox" name="bocciato_altra_scuola" id="ms_bocciato_altra_scuola" value="1">
                            bocciato in altra scuola
                        </label>
                        <span class="help-block">Valore sincronizzato con domanda di iscrizione e colloquio di entrata.</span>
                    </div>
                    <div class="form-group ms-only-uscita">
                        <label>Scuola destinazione</label>
                        <input type="hidden" name="scuola_destinazione" id="ms_scuola_destinazione">
                        <select name="id_istituto_destinazione" id="ms_id_istituto_destinazione" class="form-control input-sm">
                            <option value="">Seleziona istituto</option>
                            <option value="__altro__">ALTRO - scuola non presente</option>
                            <?php foreach ($istitutiScuole as $istituto): ?>
                                <option value="<?php echo intval($istituto['id']); ?>"><?php echo studentiMovimentiH($istituto['nome'] ?? ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" id="ms_scuola_destinazione_altro" class="form-control input-sm" style="display:none;margin-top:6px;" placeholder="Scrivi scuola destinazione">
                        <div id="ms_scuola_destinazione_libera" class="help-block" style="display:none;"></div>
                    </div>
                    <div class="form-group ms-only-uscita">
                        <label>Indirizzo di studio di destinazione</label>
                        <input type="text" name="indirizzo_destinazione" id="ms_indirizzo_destinazione" class="form-control input-sm" placeholder="Es. informatica, liceo scientifico...">
                    </div>
                    <div class="form-group ms-address-gestore">
                        <label>Indirizzo GestOre</label>
                        <select name="id_indirizzo_gestore" id="ms_id_indirizzo_gestore" class="form-control input-sm">
                            <option value="">Da ricavare dal testo</option>
                            <?php foreach ($indirizziGestore as $indirizzoRow): ?>
                                <option value="<?php echo intval($indirizzoRow['id']); ?>"><?php echo studentiMovimentiH($indirizzoRow['nome'] ?? ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="help-block">Usato nella formazione classi per filtrare bocciati, entrate e reiscrizioni.</span>
                    </div>
                    <div class="ms-section-title ms-only-uscita">Colloqui</div>
                    <div class="form-group ms-only-uscita ms-modal-wide">
                        <label>Note colloqui / comunicazioni</label>
                        <textarea id="ms_note_uscita" class="form-control input-sm ms-note-field" rows="4"></textarea>
                    </div>
                    <div class="ms-section-title ms-nullosta ms-needs-nullosta">Nulla osta</div>
                    <div class="form-group ms-only-entrata">
                        <label>Esami integrativi</label>
                        <select name="esami_integrativi" id="ms_esami_integrativi" class="form-control input-sm">
                            <option value="0">No</option>
                            <option value="1">Si</option>
                        </select>
                    </div>
                    <div class="form-group ms-only-entrata ms-modal-wide" id="ms_esami_materie_box" style="display:none;">
                        <label>Materie esami integrativi</label>
                        <div id="ms_esami_materie_list" class="ms-subject-list"></div>
                        <div class="ms-subject-add">
                            <select id="ms_esami_materie" class="form-control input-sm">
                                <option value="">Aggiungi materia...</option>
                                <?php foreach ($materieGestore as $materia): ?>
                                    <option value="<?php echo studentiMovimentiH($materia['nome'] ?? ''); ?>"><?php echo studentiMovimentiH($materia['nome'] ?? ''); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-default btn-sm" id="ms_add_esame_materia">Aggiungi</button>
                        </div>
                        <input type="hidden" name="esami_integrativi_note" id="ms_esami_integrativi_note" value="">
                    </div>
                    <div class="form-group ms-only-entrata">
                        <label>Carenze da recuperare</label>
                        <select name="carenze_presenti" id="ms_carenze_presenti" class="form-control input-sm">
                            <option value="0">No</option>
                            <option value="1">Si</option>
                        </select>
                    </div>
                    <div class="form-group ms-only-entrata ms-modal-wide" id="ms_carenze_materie_box" style="display:none;">
                        <label>Materie carenze da recuperare</label>
                        <div id="ms_carenze_materie_list" class="ms-subject-list"></div>
                        <div class="ms-subject-add">
                            <select id="ms_carenze_materie" class="form-control input-sm">
                                <option value="">Aggiungi materia...</option>
                                <?php foreach ($materieGestore as $materia): ?>
                                    <option value="<?php echo studentiMovimentiH($materia['nome'] ?? ''); ?>"><?php echo studentiMovimentiH($materia['nome'] ?? ''); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-default btn-sm" id="ms_add_carenza_materia">Aggiungi</button>
                        </div>
                        <input type="hidden" name="carenze_note" id="ms_carenze_note" value="">
                    </div>
                    <div class="form-group">
                        <label>Tipo allegato</label>
                        <select name="tipo_allegato" id="ms_tipo_allegato" class="form-control input-sm">
                            <option value="mail_genitori">PDF mail genitori</option>
                            <option value="richiesta_nulla_osta">Richiesta nulla osta</option>
                            <option value="nulla_osta_entrata">Nulla osta in entrata</option>
                            <option value="nulla_osta_uscita">Nulla osta in uscita</option>
                            <option value="modulo_ritiro">Modulo ritiro</option>
                            <option value="documenti_entrata">Documenti entrata</option>
                            <option value="altro">Altro</option>
                        </select>
                    </div>
                    <div class="form-group ms-modal-wide">
                        <label>Aggiungi allegato</label>
                        <input type="file" name="allegato[]" class="form-control input-sm" accept="application/pdf,image/jpeg,image/png" multiple>
                    </div>
                    <div class="form-group ms-modal-wide ms-only-entrata">
                        <label>Note colloqui / comunicazioni</label>
                        <textarea id="ms_note_entrata" class="form-control input-sm ms-note-field" rows="5"></textarea>
                    </div>
                </div>
                    </div>
                    <div class="ms-history">
                        <h4 style="margin-top:0;">Storico pratica</h4>
                        <div id="ms_history_content" class="ms-muted">Nessuno storico disponibile.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Chiudi</button>
                <button type="submit" class="btn btn-primary"><span class="glyphicon glyphicon-floppy-disk"></span>&ensp;Salva</button>
            </div>
        </form>
    </div>
</div>

<script>
const msHistory = <?php echo json_encode($storico, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const msAttachments = <?php echo json_encode($allegati, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const msOpenMovementId = <?php echo intval($openMovementId); ?>;
const msStatesByType = <?php echo json_encode(studentiMovimentiStatiPerTipo(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const msStateLabels = <?php echo json_encode($stati, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

function msSetField(id, value) {
    const element = document.getElementById(id);
    if (element) element.value = value || '';
}

function msSetChecked(id, value) {
    const element = document.getElementById(id);
    if (element) element.checked = String(value || '') === '1';
}

function msSplitList(value) {
    const text = String(value || '').replace(/\r?\n/g, ' ').trim();
    if (!text) return [];
    const parts = text.indexOf('|') !== -1 ? text.split('|') : text.split(';');
    return parts.map(function (item) {
        return item.replace(/\s+/g, ' ').trim();
    }).filter(Boolean);
}

function msSubjectsHiddenId(kind) {
    return kind === 'esami' ? 'ms_esami_integrativi_note' : 'ms_carenze_note';
}

function msSubjectsListId(kind) {
    return kind === 'esami' ? 'ms_esami_materie_list' : 'ms_carenze_materie_list';
}

function msSubjectsSelectId(kind) {
    return kind === 'esami' ? 'ms_esami_materie' : 'ms_carenze_materie';
}

function msSubjectValues(kind) {
    return msSplitList(document.getElementById(msSubjectsHiddenId(kind)).value);
}

function msSetSubjectValues(kind, values) {
    const normalized = [];
    values.forEach(function (value) {
        const text = String(value || '').replace(/\s+/g, ' ').trim();
        if (!text) return;
        if (!normalized.some(function (item) { return item.toUpperCase() === text.toUpperCase(); })) {
            normalized.push(text);
        }
    });
    msSetField(msSubjectsHiddenId(kind), normalized.join(' | '));
    msRenderSubjectList(kind);
}

function msRenderSubjectList(kind) {
    const box = document.getElementById(msSubjectsListId(kind));
    if (!box) return;
    const values = msSubjectValues(kind);
    if (!values.length) {
        box.innerHTML = '<span class="ms-muted">Nessuna materia selezionata.</span>';
        return;
    }
    box.innerHTML = '';
    values.forEach(function (value, index) {
        const chip = document.createElement('span');
        chip.className = 'ms-subject-chip';
        const text = document.createElement('span');
        text.textContent = value;
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.textContent = 'x';
        remove.title = 'Rimuovi materia';
        remove.addEventListener('click', function () {
            const next = msSubjectValues(kind);
            next.splice(index, 1);
            msSetSubjectValues(kind, next);
        });
        chip.appendChild(text);
        chip.appendChild(remove);
        box.appendChild(chip);
    });
}

function msAddSubject(kind) {
    const select = document.getElementById(msSubjectsSelectId(kind));
    if (!select || !select.value) return;
    const values = msSubjectValues(kind);
    values.push(select.value);
    msSetSubjectValues(kind, values);
    select.value = '';
}

function msUpdateSubjectBoxes() {
    const esamiActive = document.getElementById('ms_esami_integrativi').value === '1';
    const carenzeActive = document.getElementById('ms_carenze_presenti').value === '1';
    document.getElementById('ms_esami_materie_box').style.display = esamiActive ? '' : 'none';
    document.getElementById('ms_carenze_materie_box').style.display = carenzeActive ? '' : 'none';
    if (!esamiActive) {
        msSetSubjectValues('esami', []);
    }
    if (!carenzeActive) {
        msSetSubjectValues('carenze', []);
    }
    msRenderSubjectList('esami');
    msRenderSubjectList('carenze');
}

function msSyncSubjectNotes() {
    msSetSubjectValues('esami', document.getElementById('ms_esami_integrativi').value === '1' ? msSubjectValues('esami') : []);
    msSetSubjectValues('carenze', document.getElementById('ms_carenze_presenti').value === '1' ? msSubjectValues('carenze') : []);
}

function msSetNote(value) {
    msSetField('ms_note', value || '');
    msSetField('ms_note_entrata', value || '');
    msSetField('ms_note_uscita', value || '');
}

function msSyncVisibleNote() {
    const kind = document.getElementById('ms_tipo_pratica').value === 'entrata' ? 'entrata' : 'uscita';
    const source = document.getElementById(kind === 'entrata' ? 'ms_note_entrata' : 'ms_note_uscita');
    msSetField('ms_note', source ? source.value : '');
}

function msFilterStateOptions(preferredValue) {
    const typeSelect = document.getElementById('ms_tipo_pratica');
    const stateSelect = document.getElementById('ms_stato_pratica');
    if (!typeSelect || !stateSelect) return;
    const type = typeSelect.value || 'uscita';
    const allowed = msStatesByType[type] || msStatesByType.uscita || [];
    const current = preferredValue || stateSelect.value || '';
    stateSelect.innerHTML = '';
    allowed.forEach(function (key) {
        const option = document.createElement('option');
        option.value = key;
        option.textContent = msStateLabels[key] || key;
        option.setAttribute('data-state-key', key);
        stateSelect.appendChild(option);
    });
    if (allowed.includes(current)) {
        stateSelect.value = current;
    } else if (allowed.length) {
        stateSelect.value = allowed[0];
    }
}

function msEscape(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (char) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
    });
}

function msFormatDateTimeIt(value) {
    const text = String(value || '').trim();
    const match = text.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/);
    if (!match) return text;
    return match[3] + '/' + match[2] + '/' + match[1] + (match[4] ? ' ' + match[4] + ':' + match[5] : '');
}

function msAttachmentHref(path) {
    const normalized = String(path || '').replace(/\\/g, '/');
    if (!normalized) return '';
    const dataIndex = normalized.indexOf('/data/');
    if (dataIndex >= 0) {
        return '../' + normalized.substring(dataIndex + 1);
    }
    if (normalized.indexOf('data/') === 0) {
        return '../' + normalized;
    }
    return normalized;
}

function msNormalizeAttachmentName(value) {
    return String(value || '').replace(/\s+/g, ' ').trim().toUpperCase();
}

function msEventAttachments(practiceId, row) {
    const attachments = [];
    const seen = {};
    function addAttachment(path, name, type, id, linkedEventId) {
        path = String(path || '').trim();
        name = String(name || '').trim();
        if (!path) return;
        const key = path + '|' + name;
        if (seen[key]) {
            if (id && !seen[key].id) {
                seen[key].id = id;
            }
            if (linkedEventId && !seen[key].id_evento) {
                seen[key].id_evento = linkedEventId;
            }
            return;
        }
        const item = {path: path, name: name || 'Allegato', type: type || '', id: id || '', id_evento: linkedEventId || ''};
        seen[key] = item;
        attachments.push(item);
    }

    const eventId = Number(row.id || 0);
    addAttachment(row.allegato_path, row.allegato_original_name, row.tipo_allegato, '', eventId);

    const practiceAttachments = msAttachments[String(practiceId)] || msAttachments[Number(practiceId)] || [];
    if (!practiceAttachments.length) {
        return attachments;
    }

    const description = msNormalizeAttachmentName(row.descrizione || '');
    const rowName = msNormalizeAttachmentName(row.allegato_original_name || '');
    practiceAttachments.forEach(function (attachment) {
        const name = String(attachment.nome_file || attachment.allegato_original_name || '').trim();
        const normalizedName = msNormalizeAttachmentName(name);
        const linkedEventId = Number(attachment.id_evento || 0);
        const matchesCurrentEvent = normalizedName && (
            linkedEventId === eventId ||
            normalizedName === rowName ||
            description.indexOf(normalizedName) !== -1
        );
        if (matchesCurrentEvent) {
            addAttachment(attachment.path_file || attachment.allegato_path, name, attachment.tipo_allegato || '', attachment.id || '', linkedEventId);
        }
    });

    if (!attachments.length && String(row.tipo_evento || '') === 'allegato') {
        practiceAttachments.forEach(function (attachment) {
            addAttachment(
                attachment.path_file || attachment.allegato_path,
                attachment.nome_file || attachment.allegato_original_name,
                attachment.tipo_allegato || '',
                attachment.id || '',
                attachment.id_evento || ''
            );
        });
    }

    return attachments;
}

function msAttachmentLinks(attachments, allowDelete) {
    if (!attachments.length) return '';
    return '<div style="margin-top:6px;">' + attachments.map(function (attachment) {
        const label = attachment.type ? attachment.type + ': ' + attachment.name : attachment.name;
        const deleteButton = allowDelete && attachment.id
            ? ' <button type="button" class="btn btn-xs btn-danger" onclick="msDeleteAttachment(' + Number(attachment.id) + ')">Elimina</button>'
            : '';
        return '<div class="ms-history-attachment-row"><a class="btn btn-xs btn-default ms-history-attachment-link" target="_blank" download href="' + msEscape(msAttachmentHref(attachment.path)) + '"><span class="glyphicon glyphicon-paperclip"></span> Scarica allegato: ' + msEscape(label) + '</a>' + deleteButton + '</div>';
    }).join('') + '</div>';
}

function msSubmitHiddenPost(fields) {
    const form = document.createElement('form');
    form.method = 'post';
    form.style.display = 'none';
    Object.keys(fields).forEach(function (name) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = fields[name];
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
}

function msToggleHistoryEdit(id, show) {
    const box = document.getElementById('ms_history_edit_' + id);
    if (box) box.style.display = show ? 'block' : 'none';
}

function msDeleteHistoryEvent(id) {
    if (!window.confirm('Eliminare questa riga dello storico?')) return;
    msSubmitHiddenPost({action: 'delete_event', event_id: id});
}

function msDeleteAttachment(id) {
    if (!window.confirm('Eliminare questo allegato?')) return;
    msSubmitHiddenPost({action: 'delete_attachment', attachment_id: id});
}

function msAttachmentTypeOptions() {
    const select = document.getElementById('ms_tipo_allegato');
    return select ? select.innerHTML : '<option value="documento">Documento</option>';
}

function msUploadHistoryAttachment(eventId) {
    const fileInput = document.getElementById('ms_history_file_' + eventId);
    if (!fileInput || !fileInput.files || !fileInput.files.length) {
        window.alert('Seleziona un allegato da caricare.');
        return;
    }
    const typeSelect = document.getElementById('ms_history_tipo_allegato_' + eventId);
    const data = new FormData();
    data.append('action', 'add_event_attachment');
    data.append('event_id', eventId);
    data.append('tipo_allegato', typeSelect ? typeSelect.value : 'documento');
    data.append('event_allegato', fileInput.files[0]);
    fetch(window.location.href, {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    }).then(function () {
        window.location.reload();
    }).catch(function () {
        window.alert('Caricamento allegato non riuscito.');
    });
}

function msSubmitHistoryUpdate(id) {
    msSubmitHiddenPost({
        action: 'update_event',
        event_id: id,
        event_descrizione: document.getElementById('ms_history_desc_' + id).value || '',
        event_note: document.getElementById('ms_history_note_' + id).value || ''
    });
}

function msRenderHistory(practiceId) {
    const box = document.getElementById('ms_history_content');
    if (!box) return;
    const rows = msHistory[String(practiceId)] || msHistory[Number(practiceId)] || [];
    if (!practiceId || !rows.length) {
        box.innerHTML = '<span class="ms-muted">Nessuno storico disponibile.</span>';
        return;
    }
    box.innerHTML = rows.map(row => {
        const eventId = Number(row.id || 0);
        const attachments = msEventAttachments(practiceId, row);
        const meta = [
            row.tipo_pratica ? 'Tipo: ' + row.tipo_pratica : '',
            row.stato_pratica ? 'Stato: ' + row.stato_pratica : '',
            row.scuola_destinazione ? 'Destinazione: ' + row.scuola_destinazione : '',
            row.indirizzo_destinazione ? 'Indirizzo destinazione: ' + row.indirizzo_destinazione : '',
            row.scuola_provenienza ? 'Provenienza: ' + row.scuola_provenienza : '',
            row.tipo_allegato ? 'Tipo allegato: ' + row.tipo_allegato : '',
        ].filter(Boolean).join(' - ');
        const attachment = msAttachmentLinks(attachments, false);
        const editableAttachments = msAttachmentLinks(attachments, true);
        const uploadBox = '<div style="margin-top:8px;">' +
            '<label>Aggiungi allegato a questa riga</label>' +
            '<div class="row" style="margin-left:-4px;margin-right:-4px;">' +
                '<div class="col-sm-5" style="padding-left:4px;padding-right:4px;"><select class="form-control input-sm" id="ms_history_tipo_allegato_' + eventId + '">' + msAttachmentTypeOptions() + '</select></div>' +
                '<div class="col-sm-5" style="padding-left:4px;padding-right:4px;"><input type="file" class="form-control input-sm" id="ms_history_file_' + eventId + '" accept="application/pdf,image/jpeg,image/png"></div>' +
                '<div class="col-sm-2" style="padding-left:4px;padding-right:4px;"><button type="button" class="btn btn-default btn-xs" onclick="msUploadHistoryAttachment(' + eventId + ')">Aggiungi</button></div>' +
            '</div>' +
        '</div>';
        const editBox = '<div id="ms_history_edit_' + eventId + '" style="display:none;margin-top:8px;">' +
            '<label>Descrizione</label><input class="form-control input-sm" id="ms_history_desc_' + eventId + '" value="' + msEscape(row.descrizione || row.tipo_evento || 'Aggiornamento') + '">' +
            '<label style="margin-top:6px;">Note</label><textarea class="form-control input-sm" rows="3" id="ms_history_note_' + eventId + '">' + msEscape(row.note || '') + '</textarea>' +
            '<label style="margin-top:6px;">Allegati</label>' +
            (editableAttachments || '<div class="ms-muted">Nessun allegato collegato a questa riga.</div>') +
            uploadBox +
            '<div style="margin-top:6px;"><button type="button" class="btn btn-primary btn-xs" onclick="msSubmitHistoryUpdate(' + eventId + ')">Salva correzione</button> ' +
            '<button type="button" class="btn btn-default btn-xs" onclick="msToggleHistoryEdit(' + eventId + ', false)">Annulla</button></div>' +
        '</div>';
        return '<div class="ms-history-event">' +
            '<div class="ms-history-head">' +
                '<span>' + msEscape(row.descrizione || row.tipo_evento || 'Aggiornamento') + '</span>' +
                '<span>' + msEscape(msFormatDateTimeIt(row.created_at || '')) + '</span>' +
            '</div>' +
            (row.created_by ? '<div class="ms-history-meta">' + msEscape(row.created_by) + '</div>' : '') +
            (meta ? '<div class="ms-history-meta">' + msEscape(meta) + '</div>' : '') +
            (row.note ? '<div class="ms-history-note">' + msEscape(row.note) + '</div>' : '') +
            attachment +
            '<div style="margin-top:6px;"><button type="button" class="btn btn-default btn-xs" onclick="msToggleHistoryEdit(' + eventId + ', true)">Modifica</button> ' +
            '<button type="button" class="btn btn-danger btn-xs" onclick="msDeleteHistoryEvent(' + eventId + ')">Elimina</button></div>' +
            editBox +
        '</div>';
    }).join('');
}

function msSyncSchoolHidden(selectId, hiddenId, otherInputId) {
    const select = document.getElementById(selectId);
    const hidden = document.getElementById(hiddenId);
    if (!select || !hidden) return;
    const otherInput = document.getElementById(otherInputId);
    if (select.value === '__altro__') {
        hidden.value = otherInput ? otherInput.value.trim() : '';
        return;
    }
    const option = select.options[select.selectedIndex];
    if (select.value && option) {
        hidden.value = (option.textContent || '').trim();
    } else {
        hidden.value = '';
    }
}

function msUpdateSchoolOther(selectId, hiddenId, otherInputId, boxId, value) {
    const select = document.getElementById(selectId);
    const hidden = document.getElementById(hiddenId);
    const otherInput = document.getElementById(otherInputId);
    const box = document.getElementById(boxId);
    if (!select || !hidden || !otherInput) return;
    const text = String(value || '').trim();
    let matched = false;
    if (text !== '') {
        for (let i = 0; i < select.options.length; i++) {
            const option = select.options[i];
            if (option.value && option.value !== '__altro__' && (option.textContent || '').trim().toUpperCase() === text.toUpperCase()) {
                select.value = option.value;
                matched = true;
                break;
            }
        }
    }
    if (text !== '' && !matched && !select.value) {
        select.value = '__altro__';
        otherInput.value = text;
    } else if (select.value !== '__altro__') {
        otherInput.value = '';
    }
    otherInput.style.display = select.value === '__altro__' ? 'block' : 'none';
    if (box) {
        box.style.display = 'none';
        box.textContent = '';
    }
    msSyncSchoolHidden(selectId, hiddenId, otherInputId);
}

function msOpenNew(kind) {
    msSetField('ms_id', '');
    msSetField('ms_fonte', 'manuale');
    msSetField('ms_id_pratica_iscrizione', '');
    msSetField('ms_id_cambio_scuola_iscrizione', '');
    msSetField('ms_tipo_pratica', kind === 'entrata' ? 'entrata' : 'uscita');
    msSetField('ms_stato_pratica', kind === 'entrata' ? 'contatto_ricevuto' : 'da_verificare');
    msSetField('ms_id_studente', '');
    msSetField('ms_cognome', '');
    msSetField('ms_nome', '');
    msSetField('ms_codice_fiscale', '');
    msSetField('ms_anno_corso', '');
    msSetField('ms_classe_origine', '');
    msSetField('ms_classe_richiesta', '');
    msSetField('ms_id_istituto_provenienza', '');
    msSetField('ms_scuola_provenienza', '');
    msSetField('ms_indirizzo_provenienza', '');
    msSetField('ms_id_istituto_destinazione', '');
    msSetField('ms_scuola_destinazione', '');
    msSetField('ms_indirizzo_destinazione', '');
    msSetField('ms_id_indirizzo_gestore', '');
    msSetChecked('ms_doppio_bocciato', '0');
    msSetChecked('ms_doppio_bocciato_non_consecutivo', '0');
    msSetChecked('ms_bocciato_altra_scuola', '0');
    msUpdateSchoolOther('ms_id_istituto_provenienza', 'ms_scuola_provenienza', 'ms_scuola_provenienza_altro', 'ms_scuola_provenienza_libera', '');
    msUpdateSchoolOther('ms_id_istituto_destinazione', 'ms_scuola_destinazione', 'ms_scuola_destinazione_altro', 'ms_scuola_destinazione_libera', '');
    msSetField('ms_esami_integrativi', '0');
    msSetSubjectValues('esami', []);
    msSetField('ms_carenze_presenti', '0');
    msSetSubjectValues('carenze', []);
    msSetNote('');
    msRenderHistory(0);
    document.getElementById('msPracticeTitle').textContent = kind === 'entrata' ? 'Nuova entrata' : 'Nuova uscita';
    msUpdatePracticeKindFields();
    $('#msPracticeModal').modal('show');
}

function msOpenPracticeFromButton(button) {
    if (!button) return;
    msSetField('ms_id', button.dataset.id || '');
    msSetField('ms_fonte', button.dataset.fonte || 'manuale');
    msSetField('ms_id_pratica_iscrizione', button.dataset.id_pratica_iscrizione || '');
    msSetField('ms_id_cambio_scuola_iscrizione', button.dataset.id_cambio_scuola_iscrizione || '');
    msSetField('ms_tipo_pratica', button.dataset.tipo_pratica || 'uscita');
    msSetField('ms_stato_pratica', button.dataset.stato_pratica || 'da_verificare');
    msSetField('ms_id_studente', button.dataset.id_studente || '');
    msSetField('ms_cognome', button.dataset.cognome || '');
    msSetField('ms_nome', button.dataset.nome || '');
    msSetField('ms_codice_fiscale', button.dataset.codice_fiscale || '');
    msSetField('ms_anno_corso', button.dataset.anno_corso || '');
    msSetField('ms_classe_origine', button.dataset.classe_origine || '');
    msSetField('ms_classe_richiesta', button.dataset.classe_richiesta || '');
    msSetField('ms_id_istituto_provenienza', button.dataset.id_istituto_provenienza || '');
    msSetField('ms_scuola_provenienza', button.dataset.scuola_provenienza || '');
    msSetField('ms_indirizzo_provenienza', '');
    msSetField('ms_id_istituto_destinazione', button.dataset.id_istituto_destinazione || '');
    msSetField('ms_scuola_destinazione', button.dataset.scuola_destinazione || '');
    msSetField('ms_indirizzo_destinazione', button.dataset.indirizzo_destinazione || '');
    msSetField('ms_id_indirizzo_gestore', button.dataset.id_indirizzo_gestore || '');
    msSetChecked('ms_doppio_bocciato', button.dataset.doppio_bocciato || '0');
    msSetChecked('ms_doppio_bocciato_non_consecutivo', button.dataset.doppio_bocciato_non_consecutivo || '0');
    msSetChecked('ms_bocciato_altra_scuola', button.dataset.bocciato_altra_scuola || '0');
    msUpdateSchoolOther('ms_id_istituto_provenienza', 'ms_scuola_provenienza', 'ms_scuola_provenienza_altro', 'ms_scuola_provenienza_libera', button.dataset.scuola_provenienza || '');
    msUpdateSchoolOther('ms_id_istituto_destinazione', 'ms_scuola_destinazione', 'ms_scuola_destinazione_altro', 'ms_scuola_destinazione_libera', button.dataset.scuola_destinazione || '');
    msSetField('ms_esami_integrativi', button.dataset.esami_integrativi || '0');
    msSetSubjectValues('esami', msSplitList(button.dataset.esami_integrativi_note || ''));
    msSetField('ms_carenze_presenti', button.dataset.carenze_presenti || '0');
    msSetSubjectValues('carenze', msSplitList(button.dataset.carenze_note || ''));
    msSetNote(button.dataset.note || '');
    msRenderHistory(button.dataset.id || 0);
    document.getElementById('msPracticeTitle').textContent = 'Dettaglio pratica';
    msUpdatePracticeKindFields();
    $('#msPracticeModal').modal('show');
}

document.querySelectorAll('.ms-edit').forEach(function (button) {
    button.addEventListener('click', function () {
        msOpenPracticeFromButton(button);
    });
});

if (msOpenMovementId > 0) {
    window.setTimeout(function () {
        const button = document.querySelector('.ms-edit[data-id="' + msOpenMovementId + '"]');
        if (!button) return;
        const row = button.closest('tr');
        if (row) {
            row.scrollIntoView({block: 'center'});
        }
        msOpenPracticeFromButton(button);
    }, 100);
}

function msUpdatePracticeKindFields() {
    const kind = document.getElementById('ms_tipo_pratica').value === 'entrata' ? 'entrata' : 'uscita';
    msFilterStateOptions();
    document.querySelectorAll('.ms-only-entrata').forEach(function (element) {
        element.style.display = kind === 'entrata' ? '' : 'none';
    });
    document.querySelectorAll('.ms-only-uscita').forEach(function (element) {
        element.style.display = kind === 'uscita' ? '' : 'none';
    });
    document.querySelectorAll('.ms-address-gestore').forEach(function (element) {
        const type = document.getElementById('ms_tipo_pratica').value || '';
        element.style.display = (kind === 'entrata' || type === 'bocciato_reiscrizione' || document.getElementById('ms_doppio_bocciato').checked) ? '' : 'none';
    });
    const type = document.getElementById('ms_tipo_pratica').value || '';
    document.querySelectorAll('.ms-needs-nullosta').forEach(function (element) {
        element.style.display = (type === 'entrata' || type === 'uscita') ? '' : 'none';
    });
    if (kind === 'entrata') {
        msSetField('ms_id_istituto_destinazione', '');
        msSetField('ms_scuola_destinazione', '');
        msSetField('ms_indirizzo_destinazione', '');
        msUpdateSchoolOther('ms_id_istituto_destinazione', 'ms_scuola_destinazione', 'ms_scuola_destinazione_altro', 'ms_scuola_destinazione_libera', '');
    } else {
        msSetField('ms_id_istituto_provenienza', '');
        msSetField('ms_scuola_provenienza', '');
        msSetField('ms_indirizzo_provenienza', '');
        msSetField('ms_esami_integrativi', '0');
        msSetSubjectValues('esami', []);
        msSetField('ms_carenze_presenti', '0');
        msSetSubjectValues('carenze', []);
        msUpdateSchoolOther('ms_id_istituto_provenienza', 'ms_scuola_provenienza', 'ms_scuola_provenienza_altro', 'ms_scuola_provenienza_libera', '');
    }
    msUpdateSubjectBoxes();
}

document.getElementById('ms_tipo_pratica').addEventListener('change', function () {
    msFilterStateOptions();
    msUpdatePracticeKindFields();
});

document.getElementById('ms_doppio_bocciato').addEventListener('change', function () {
    if (!this.checked) return;
    if (document.getElementById('ms_tipo_pratica').value === 'bocciato_reiscrizione') {
        msSetField('ms_tipo_pratica', 'uscita');
    }
    if (['da_verificare', 'reiscrizione_confermata', 'chiusa'].includes(document.getElementById('ms_stato_pratica').value)) {
        msSetField('ms_stato_pratica', 'cambia_scuola');
    }
    msUpdatePracticeKindFields();
});

document.getElementById('ms_id_istituto_provenienza').addEventListener('change', function () {
    msUpdateSchoolOther('ms_id_istituto_provenienza', 'ms_scuola_provenienza', 'ms_scuola_provenienza_altro', 'ms_scuola_provenienza_libera', document.getElementById('ms_scuola_provenienza').value);
});

document.getElementById('ms_id_istituto_destinazione').addEventListener('change', function () {
    msUpdateSchoolOther('ms_id_istituto_destinazione', 'ms_scuola_destinazione', 'ms_scuola_destinazione_altro', 'ms_scuola_destinazione_libera', document.getElementById('ms_scuola_destinazione').value);
});

document.getElementById('ms_esami_integrativi').addEventListener('change', msUpdateSubjectBoxes);
document.getElementById('ms_carenze_presenti').addEventListener('change', msUpdateSubjectBoxes);
document.getElementById('ms_add_esame_materia').addEventListener('click', function () {
    msAddSubject('esami');
});
document.getElementById('ms_add_carenza_materia').addEventListener('click', function () {
    msAddSubject('carenze');
});

document.getElementById('ms_scuola_provenienza_altro').addEventListener('input', function () {
    msSyncSchoolHidden('ms_id_istituto_provenienza', 'ms_scuola_provenienza', 'ms_scuola_provenienza_altro');
});

document.getElementById('ms_scuola_destinazione_altro').addEventListener('input', function () {
    msSyncSchoolHidden('ms_id_istituto_destinazione', 'ms_scuola_destinazione', 'ms_scuola_destinazione_altro');
});

document.querySelectorAll('.ms-note-field').forEach(function (element) {
    element.addEventListener('input', msSyncVisibleNote);
});

document.getElementById('msPracticeForm').addEventListener('submit', function () {
    msSyncVisibleNote();
    msSyncSubjectNotes();
    msSyncSchoolHidden('ms_id_istituto_provenienza', 'ms_scuola_provenienza', 'ms_scuola_provenienza_altro');
    msSyncSchoolHidden('ms_id_istituto_destinazione', 'ms_scuola_destinazione', 'ms_scuola_destinazione_altro');
});

document.getElementById('ms_id_studente').addEventListener('change', function () {
    const option = this.options[this.selectedIndex];
    if (!option || !this.value) return;
    msSetField('ms_cognome', option.dataset.cognome || '');
    msSetField('ms_nome', option.dataset.nome || '');
    msSetField('ms_codice_fiscale', option.dataset.cf || '');
    msSetField('ms_classe_origine', option.dataset.classe || '');
    msSetField('ms_anno_corso', option.dataset.anno || '');
});
</script>
</body>
</html>
