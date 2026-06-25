<?php

require_once '../common/checkSession.php';
require_once '../common/studentiMovimentiLib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

studentiMovimentiEnsureTables();

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
        } else {
            $practiceId = studentiMovimentiSavePractice($_POST);
            if (!empty($_FILES['allegato']) && intval($_FILES['allegato']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                studentiMovimentiAttachFile($practiceId, $_FILES['allegato'], trim((string)($_POST['tipo_allegato'] ?? 'documento')));
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
$activeSection = trim((string)($_GET['sezione'] ?? 'uscite'));
if (!in_array($activeSection, ['uscite', 'entrate'], true)) {
    $activeSection = 'uscite';
}
$activeYear = intval($_GET['anno'] ?? 0);

$pratiche = dbGetAll("
    SELECT p.*,
           s.cognome AS studente_cognome,
           s.nome AS studente_nome,
           s.codice_fiscale AS studente_cf,
           s.id AS studente_id,
           c.classe AS classe_corrente,
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
    'uscite' => [1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 0 => []],
    'entrate' => [1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 0 => []],
];
foreach ($pratiche as $pratica) {
    $section = ($pratica['tipo_pratica'] ?? '') === 'entrata' ? 'entrate' : 'uscite';
    $year = intval($pratica['anno_corso'] ?? 0);
    if ($year < 1 || $year > 5) {
        $year = 0;
    }
    $grouped[$section][$year][] = $pratica;
}
if ($activeYear === 0) {
    foreach ([1, 2, 3, 4, 5, 0] as $year) {
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
    $labels = [1 => 'Prime', 2 => 'Seconde', 3 => 'Terze', 4 => 'Quarte', 5 => 'Quinte', 0 => 'Senza anno'];
    return $labels[$year] ?? 'Senza anno';
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
        .ms-modal-wide {
            grid-column: span 2;
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
                    Quinte <?php echo intval($byYear[5] ?? 0); ?>,
                    senza anno <?php echo intval($byYear[0] ?? 0); ?>.
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
                <?php foreach ([1, 2, 3, 4, 5, 0] as $year): ?>
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
                        <th>Esami</th>
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
                                <div class="ms-name"><?php echo studentiMovimentiH(ms_practice_name($row)); ?></div>
                                <div class="ms-muted"><?php echo studentiMovimentiH($row['codice_fiscale'] ?: $row['studente_cf'] ?: ''); ?></div>
                            </td>
                            <td>
                                <strong><?php echo studentiMovimentiH($row['classe_origine'] ?: $row['classe_corrente'] ?: '-'); ?></strong>
                                <?php if (($row['classe_richiesta'] ?? '') !== ''): ?>
                                    <div class="ms-muted">richiesta <?php echo studentiMovimentiH($row['classe_richiesta']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo studentiMovimentiH($tipi[$row['tipo_pratica']] ?? $row['tipo_pratica']); ?></td>
                            <td>
                                <span class="label label-<?php echo ms_label_class((string)$row['stato_pratica']); ?>">
                                    <?php echo studentiMovimentiH($stati[$row['stato_pratica']] ?? $row['stato_pratica']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($activeSection === 'entrate'): ?>
                                    <?php echo studentiMovimentiH($row['scuola_provenienza'] ?: '-'); ?>
                                    <?php if (($row['indirizzo_provenienza'] ?? '') !== ''): ?>
                                        <div class="ms-muted"><?php echo studentiMovimentiH($row['indirizzo_provenienza']); ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php echo studentiMovimentiH($row['scuola_destinazione'] ?: '-'); ?>
                                    <?php if (($row['indirizzo_destinazione'] ?? '') !== ''): ?>
                                        <div class="ms-muted"><?php echo studentiMovimentiH($row['indirizzo_destinazione']); ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo intval($row['esami_integrativi'] ?? 0) ? '<span class="label label-warning">Si</span>' : '<span class="text-muted">No</span>'; ?></td>
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
                            <td><?php echo studentiMovimentiH(substr((string)($row['updated_at'] ?? ''), 0, 16)); ?></td>
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
                                        data-id_istituto_destinazione="<?php echo intval($row['id_istituto_destinazione'] ?? 0); ?>"
                                        data-scuola_destinazione="<?php echo ms_data_attr($row['scuola_destinazione'] ?? ''); ?>"
                                        data-indirizzo_destinazione="<?php echo ms_data_attr($row['indirizzo_destinazione'] ?? ''); ?>"
                                        data-esami_integrativi="<?php echo intval($row['esami_integrativi'] ?? 0); ?>"
                                        data-note="<?php echo ms_data_attr($row['note'] ?? ''); ?>">
                                    Dettaglio
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="9"><div class="ms-empty">Nessuna pratica in questa sezione.</div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="msPracticeModal" tabindex="-1" role="dialog" aria-labelledby="msPracticeTitle">
    <div class="modal-dialog modal-lg" role="document">
        <form method="post" enctype="multipart/form-data" class="modal-content">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="ms_id" value="">
            <input type="hidden" name="fonte" id="ms_fonte" value="manuale">
            <input type="hidden" name="id_pratica_iscrizione" id="ms_id_pratica_iscrizione" value="">
            <input type="hidden" name="id_cambio_scuola_iscrizione" id="ms_id_cambio_scuola_iscrizione" value="">
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
                                <option value="<?php echo studentiMovimentiH($key); ?>"><?php echo studentiMovimentiH($label); ?></option>
                            <?php endforeach; ?>
                        </select>
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
                            <option value="">Senza anno</option>
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
                            <?php foreach ($istitutiScuole as $istituto): ?>
                                <option value="<?php echo intval($istituto['id']); ?>"><?php echo studentiMovimentiH($istituto['nome'] ?? ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div id="ms_scuola_provenienza_libera" class="help-block" style="display:none;"></div>
                    </div>
                    <div class="form-group ms-only-entrata">
                        <label>Indirizzo di studio di provenienza</label>
                        <input type="text" name="indirizzo_provenienza" id="ms_indirizzo_provenienza" class="form-control input-sm" placeholder="Es. informatica, liceo scientifico...">
                    </div>
                    <div class="form-group ms-only-uscita">
                        <label>Scuola destinazione</label>
                        <input type="hidden" name="scuola_destinazione" id="ms_scuola_destinazione">
                        <select name="id_istituto_destinazione" id="ms_id_istituto_destinazione" class="form-control input-sm">
                            <option value="">Seleziona istituto</option>
                            <?php foreach ($istitutiScuole as $istituto): ?>
                                <option value="<?php echo intval($istituto['id']); ?>"><?php echo studentiMovimentiH($istituto['nome'] ?? ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div id="ms_scuola_destinazione_libera" class="help-block" style="display:none;"></div>
                    </div>
                    <div class="form-group ms-only-uscita">
                        <label>Indirizzo di studio di destinazione</label>
                        <input type="text" name="indirizzo_destinazione" id="ms_indirizzo_destinazione" class="form-control input-sm" placeholder="Es. informatica, liceo scientifico...">
                    </div>
                    <div class="form-group ms-only-entrata">
                        <label>Esami integrativi</label>
                        <select name="esami_integrativi" id="ms_esami_integrativi" class="form-control input-sm">
                            <option value="0">No</option>
                            <option value="1">Si</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tipo allegato</label>
                        <select name="tipo_allegato" id="ms_tipo_allegato" class="form-control input-sm">
                            <option value="mail_genitori">PDF mail genitori</option>
                            <option value="richiesta_nulla_osta">Richiesta nulla osta</option>
                            <option value="modulo_ritiro">Modulo ritiro</option>
                            <option value="documenti_entrata">Documenti entrata</option>
                            <option value="altro">Altro</option>
                        </select>
                    </div>
                    <div class="form-group ms-modal-wide">
                        <label>Aggiungi allegato</label>
                        <input type="file" name="allegato" class="form-control input-sm" accept="application/pdf,image/jpeg,image/png">
                    </div>
                    <div class="form-group ms-modal-wide">
                        <label>Note colloqui / comunicazioni</label>
                        <textarea name="note" id="ms_note" class="form-control input-sm" rows="5"></textarea>
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

function msSetField(id, value) {
    const element = document.getElementById(id);
    if (element) element.value = value || '';
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

function msRenderHistory(practiceId) {
    const box = document.getElementById('ms_history_content');
    if (!box) return;
    const rows = msHistory[String(practiceId)] || msHistory[Number(practiceId)] || [];
    if (!practiceId || !rows.length) {
        box.innerHTML = '<span class="ms-muted">Nessuno storico disponibile.</span>';
        return;
    }
    box.innerHTML = rows.map(row => {
        const meta = [
            row.tipo_pratica ? 'Tipo: ' + row.tipo_pratica : '',
            row.stato_pratica ? 'Stato: ' + row.stato_pratica : '',
            row.scuola_destinazione ? 'Destinazione: ' + row.scuola_destinazione : '',
            row.indirizzo_destinazione ? 'Indirizzo destinazione: ' + row.indirizzo_destinazione : '',
            row.scuola_provenienza ? 'Provenienza: ' + row.scuola_provenienza : '',
            row.indirizzo_provenienza ? 'Indirizzo provenienza: ' + row.indirizzo_provenienza : ''
        ].filter(Boolean).join(' - ');
        return '<div class="ms-history-event">' +
            '<div class="ms-history-head">' +
                '<span>' + msEscape(row.descrizione || row.tipo_evento || 'Aggiornamento') + '</span>' +
                '<span>' + msEscape(msFormatDateTimeIt(row.created_at || '')) + '</span>' +
            '</div>' +
            (row.created_by ? '<div class="ms-history-meta">' + msEscape(row.created_by) + '</div>' : '') +
            (meta ? '<div class="ms-history-meta">' + msEscape(meta) + '</div>' : '') +
            (row.note ? '<div class="ms-history-note">' + msEscape(row.note) + '</div>' : '') +
        '</div>';
    }).join('');
}

function msSyncSchoolHidden(selectId, hiddenId) {
    const select = document.getElementById(selectId);
    const hidden = document.getElementById(hiddenId);
    if (!select || !hidden) return;
    const option = select.options[select.selectedIndex];
    if (select.value && option) {
        hidden.value = option.textContent || '';
    }
}

function msShowLegacySchool(selectId, boxId, value) {
    const select = document.getElementById(selectId);
    const box = document.getElementById(boxId);
    if (!select || !box) return;
    const show = !select.value && String(value || '').trim() !== '';
    box.style.display = show ? 'block' : 'none';
    box.textContent = show ? 'Valore gia presente: ' + value : '';
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
    msShowLegacySchool('ms_id_istituto_provenienza', 'ms_scuola_provenienza_libera', '');
    msShowLegacySchool('ms_id_istituto_destinazione', 'ms_scuola_destinazione_libera', '');
    msSetField('ms_esami_integrativi', '0');
    msSetField('ms_note', '');
    msRenderHistory(0);
    document.getElementById('msPracticeTitle').textContent = kind === 'entrata' ? 'Nuova entrata' : 'Nuova uscita';
    msUpdatePracticeKindFields();
    $('#msPracticeModal').modal('show');
}

document.querySelectorAll('.ms-edit').forEach(function (button) {
    button.addEventListener('click', function () {
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
        msSetField('ms_indirizzo_provenienza', button.dataset.indirizzo_provenienza || '');
        msSetField('ms_id_istituto_destinazione', button.dataset.id_istituto_destinazione || '');
        msSetField('ms_scuola_destinazione', button.dataset.scuola_destinazione || '');
        msSetField('ms_indirizzo_destinazione', button.dataset.indirizzo_destinazione || '');
        msShowLegacySchool('ms_id_istituto_provenienza', 'ms_scuola_provenienza_libera', button.dataset.scuola_provenienza || '');
        msShowLegacySchool('ms_id_istituto_destinazione', 'ms_scuola_destinazione_libera', button.dataset.scuola_destinazione || '');
        msSetField('ms_esami_integrativi', button.dataset.esami_integrativi || '0');
        msSetField('ms_note', button.dataset.note || '');
        msRenderHistory(button.dataset.id || 0);
        document.getElementById('msPracticeTitle').textContent = 'Dettaglio pratica';
        msUpdatePracticeKindFields();
        $('#msPracticeModal').modal('show');
    });
});

function msUpdatePracticeKindFields() {
    const kind = document.getElementById('ms_tipo_pratica').value === 'entrata' ? 'entrata' : 'uscita';
    document.querySelectorAll('.ms-only-entrata').forEach(function (element) {
        element.style.display = kind === 'entrata' ? '' : 'none';
    });
    document.querySelectorAll('.ms-only-uscita').forEach(function (element) {
        element.style.display = kind === 'uscita' ? '' : 'none';
    });
    if (kind === 'entrata') {
        msSetField('ms_id_istituto_destinazione', '');
        msSetField('ms_scuola_destinazione', '');
        msSetField('ms_indirizzo_destinazione', '');
    } else {
        msSetField('ms_id_istituto_provenienza', '');
        msSetField('ms_scuola_provenienza', '');
        msSetField('ms_indirizzo_provenienza', '');
        msSetField('ms_esami_integrativi', '0');
    }
}

document.getElementById('ms_tipo_pratica').addEventListener('change', msUpdatePracticeKindFields);

document.getElementById('ms_id_istituto_provenienza').addEventListener('change', function () {
    msSyncSchoolHidden('ms_id_istituto_provenienza', 'ms_scuola_provenienza');
    msShowLegacySchool('ms_id_istituto_provenienza', 'ms_scuola_provenienza_libera', '');
});

document.getElementById('ms_id_istituto_destinazione').addEventListener('change', function () {
    msSyncSchoolHidden('ms_id_istituto_destinazione', 'ms_scuola_destinazione');
    msShowLegacySchool('ms_id_istituto_destinazione', 'ms_scuola_destinazione_libera', '');
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
