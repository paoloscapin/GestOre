<?php
/**
 * GestOre - Controllo import pagoPA da ISIREL per viaggi e uscite.
 */

declare(strict_types=1);

require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('admin', 'segreteria-didattica', 'segreteria-docenti', 'dirigente');

function h($s) {
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function statoLabel($state) {
    $state = (string)$state;
    $map = [
        'PAGOPA_PAYMENT_VERIFICATION_OK' => 'Pagato',
        'PAGOPA_PAYMENT_CREATED' => 'Creato',
        'PAGOPA_PAYMENT_SENT' => 'Inviato',
        'PAGOPA_PAYMENT_CANCELLED' => 'Annullato',
        'PAGOPA_PAYMENT_EXPIRED' => 'Scaduto',
    ];
    return $map[$state] ?? ($state !== '' ? $state : '-');
}

function statoClass($state) {
    if ($state === 'PAGOPA_PAYMENT_VERIFICATION_OK') {
        return 'pagopa-status-ok';
    }
    if (stripos((string)$state, 'CANCEL') !== false || stripos((string)$state, 'EXPIRED') !== false) {
        return 'pagopa-status-bad';
    }
    return 'pagopa-status-warn';
}

function euroValue($value) {
    return number_format((float)$value, 2, ',', '.') . ' &euro;';
}

function dateIt($value) {
    if (!$value) {
        return '-';
    }

    $ts = strtotime((string)$value);

    if ($ts === false) {
        return h($value);
    }

    return date('d/m/Y', $ts);
}

function percentValue($part, $total) {
    $total = (int)$total;

    if ($total <= 0) {
        return 0;
    }

    return min(100, max(0, round(((int)$part / $total) * 100)));
}

function getSummary($q) {
    $where = '';
    if ($q !== '') {
        $where = "
            WHERE a.causal LIKE " . dbQ('%' . $q . '%') . "
               OR a.descrizione LIKE " . dbQ('%' . $q . '%') . "
        ";
    }

    return dbGetAll("
        SELECT
            a.id,
            a.id_isirel,
            a.send_date,
            a.due_date,
            a.causal,
            a.descrizione,
            a.importo,
            a.tipologia_descrizione,
            COUNT(p.id) AS num_avvisi,
            SUM(CASE WHEN p.id_studente_gestore IS NOT NULL THEN 1 ELSE 0 END) AS mappati,
            SUM(CASE WHEN p.id_studente_gestore IS NULL THEN 1 ELSE 0 END) AS non_mappati,
            SUM(CASE WHEN p.payment_state = 'PAGOPA_PAYMENT_VERIFICATION_OK' THEN 1 ELSE 0 END) AS pagati,
            SUM(CASE WHEN p.cancelled = 1 THEN 1 ELSE 0 END) AS annullati,
            SUM(COALESCE(p.payment_amount, 0)) AS totale_richiesto,
            SUM(CASE WHEN p.payment_state = 'PAGOPA_PAYMENT_VERIFICATION_OK' THEN COALESCE(p.payment_amount, 0) ELSE 0 END) AS totale_pagato,
            MAX(a.updated_at) AS updated_at
        FROM pagopa_attivita a
        LEFT JOIN pagopa_avvisi_studenti p ON p.id_attivita = a.id
        $where
        GROUP BY
            a.id,
            a.id_isirel,
            a.send_date,
            a.due_date,
            a.causal,
            a.descrizione,
            a.importo,
            a.tipologia_descrizione
        ORDER BY COALESCE(a.due_date, a.send_date) DESC, a.id DESC
        LIMIT 300
    ") ?: [];
}

function getActivity($id) {
    return dbGetFirst("
        SELECT *
        FROM pagopa_attivita
        WHERE id = " . dbI($id) . "
    ");
}

function getRecipients($id, $soloProblemi) {
    $whereProblem = $soloProblemi ? "AND p.id_studente_gestore IS NULL" : "";
    return dbGetAll("
        SELECT
            p.*,
            s.id AS gestore_id,
            s.cognome AS gestore_cognome,
            s.nome AS gestore_nome,
            s.email AS gestore_email,
            s.username AS gestore_username,
            c.classe AS gestore_classe
        FROM pagopa_avvisi_studenti p
        LEFT JOIN studente s ON s.id = p.id_studente_gestore
        LEFT JOIN studente_frequenta sf ON sf.id_studente = s.id
        LEFT JOIN classi c ON c.id = sf.id_classe AND c.attiva = 1
        WHERE p.id_attivita = " . dbI($id) . "
        $whereProblem
        ORDER BY COALESCE(s.cognome, p.cognome), COALESCE(s.nome, p.nome), p.id
    ") ?: [];
}

$idAttivita = isset($_GET['id']) ? intval($_GET['id']) : 0;
$q = trim((string)($_GET['q'] ?? ''));
$soloProblemi = isset($_GET['problemi']) ? 1 : 0;
$repairMsg = null;

if (isset($_POST['repair_mapping'])) {
    dbExec("
        UPDATE pagopa_avvisi_studenti p
        JOIN studente s
          ON UPPER(REPLACE(TRIM(s.codice_fiscale), ' ', '')) = UPPER(REPLACE(TRIM(p.codice_fiscale), ' ', ''))
        SET p.id_studente_gestore = s.id
        WHERE p.codice_fiscale IS NOT NULL
          AND p.codice_fiscale <> ''
          AND (p.id_studente_gestore IS NULL OR p.id_studente_gestore <> s.id)
    ");
    $repairMsg = 'Mapping studenti aggiornato.';
}

$activity = $idAttivita > 0 ? getActivity($idAttivita) : null;
$rows = $activity ? getRecipients($idAttivita, $soloProblemi) : [];
$summary = !$activity ? getSummary($q) : [];

$stats = [
    'attivita' => count($summary),
    'avvisi' => 0,
    'pagati' => 0,
    'non_mappati' => 0,
    'totale_pagato' => 0.0,
];

foreach ($summary as $r) {
    $stats['avvisi'] += intval($r['num_avvisi'] ?? 0);
    $stats['pagati'] += intval($r['pagati'] ?? 0);
    $stats['non_mappati'] += intval($r['non_mappati'] ?? 0);
    $stats['totale_pagato'] += (float)($r['totale_pagato'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    ?>
    <title>Viaggi - pagoPA ISIREL</title>
    <style>
        body {
            background: #f4fafb;
        }
        .pagopa-page {
            margin-top: 58px;
            padding-top: 18px;
            padding-bottom: 28px;
        }
        .pagopa-hero {
            background: linear-gradient(135deg, #0f766e 0%, #0ea5a3 55%, #38bdf8 100%);
            color: #fff;
            border-radius: 10px;
            padding: 22px 28px;
            margin-bottom: 16px;
            box-shadow: 0 14px 32px rgba(15, 118, 110, .18);
        }
        .pagopa-hero h1 { margin: 0 0 6px; font-size: 30px; font-weight: 800; letter-spacing: 0; }
        .pagopa-hero p { margin: 0; color: rgba(255, 255, 255, .9); font-size: 15px; }
        .pagopa-toolbar-box {
            background: #fff;
            border: 1px solid #d3e7ec;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 14px;
            box-shadow: 0 4px 16px rgba(15, 23, 42, .05);
        }
        .pagopa-toolbar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin: 0; }
        .pagopa-search { min-width: 320px; max-width: none; flex: 1 1 560px; }
        .pagopa-search .form-control,
        .pagopa-search .input-group-addon {
            height: 40px;
            font-size: 14px;
        }
        .pagopa-toolbar .btn {
            height: 40px;
            padding-left: 14px;
            padding-right: 14px;
            font-weight: 700;
        }
        .pagopa-toolbar-spacer {
            flex: 1 1 auto;
        }
        .pagopa-card-row {
            display: grid;
            grid-template-columns: repeat(5, minmax(120px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        .pagopa-card {
            background: #fff;
            border: 1px solid #d9e7ea;
            border-radius: 10px;
            padding: 14px 16px;
            min-height: 88px;
            box-shadow: 0 4px 16px rgba(15, 23, 42, .05);
            border-top: 4px solid #0ea5a3;
        }
        .pagopa-card-ok { border-top-color: #16a34a; }
        .pagopa-card-warn { border-top-color: #f59e0b; }
        .pagopa-card-money { border-top-color: #2563eb; }
        .pagopa-card .card-icon {
            float: right;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #ecfeff;
            color: #0f766e;
            text-align: center;
            line-height: 32px;
            font-size: 15px;
        }
        .pagopa-card .label { display: block; color: #64748b; font-size: 12px; padding: 0; text-align: left; white-space: normal; }
        .pagopa-card .value { display: block; margin-top: 8px; color: #0f172a; font-size: 24px; font-weight: 800; line-height: 1.1; }
        .pagopa-panel { border-color: #cfe4e9; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 16px rgba(15, 23, 42, .05); }
        .pagopa-panel .panel-heading { background: #e6f7f9; border-color: #cfe4e9; color: #0f4f5f; font-weight: 800; padding: 14px 16px; font-size: 15px; }
        .pagopa-table { margin-bottom: 0; }
        .pagopa-table th {
            background: #0f4f5f;
            color: #fff;
            border-color: #0b4452 !important;
            vertical-align: middle !important;
            font-size: 13px;
            padding: 12px 10px !important;
            white-space: nowrap;
        }
        .pagopa-table td {
            vertical-align: middle !important;
            padding: 12px 10px !important;
            font-size: 13px;
        }
        .pagopa-table tbody tr:nth-child(even) { background: #f7fcfc; }
        .pagopa-table tbody tr:hover { background: #eaf8fb; }
        .pagopa-activity-title { font-weight: 700; color: #0f4f5f; }
        .pagopa-muted { color: #64748b; font-size: 12px; }
        .pagopa-status { display: inline-block; padding: 3px 8px; border-radius: 999px; font-weight: 700; font-size: 12px; }
        .pagopa-status-ok { background: #dcfce7; color: #166534; }
        .pagopa-status-warn { background: #fef3c7; color: #92400e; }
        .pagopa-status-bad { background: #fee2e2; color: #991b1b; }
        .pagopa-progress { height: 8px; background: #e2e8f0; border-radius: 999px; overflow: hidden; margin-top: 7px; width: 150px; max-width: 100%; }
        .pagopa-progress span { display: block; height: 100%; background: #16a34a; }
        .pagopa-alert { border-left: 4px solid #16a34a; background: #ecfdf5; padding: 10px 12px; margin-bottom: 14px; border-radius: 6px; }
        .pagopa-actions { white-space: nowrap; }
        .pagopa-date {
            display: inline-block;
            min-width: 78px;
            font-weight: 700;
            color: #0f172a;
        }
        .pagopa-detail-btn {
            min-width: 92px;
            font-weight: 700;
        }
        .pagopa-amount {
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
        }
        .pagopa-empty {
            padding: 26px;
            text-align: center;
            color: #64748b;
        }
        @media (max-width: 992px) { .pagopa-card-row { grid-template-columns: repeat(2, minmax(120px, 1fr)); } }
        @media (max-width: 640px) { .pagopa-card-row { grid-template-columns: 1fr; } .pagopa-search { min-width: 100%; } }
    </style>
</head>
<body>
<?php
if (haRuolo('segreteria-docenti') && !haRuolo('segreteria-didattica') && !haRuolo('admin')) {
    require_once '../common/header-segreteria.php';
} else {
    require_once '../common/header-didattica.php';
}
?>

<div class="container-fluid pagopa-page">
    <div class="pagopa-hero">
        <h1>Viaggi e pagamenti pagoPA</h1>
        <p>Controllo degli avvisi importati da ISIREL e collegamento con gli studenti GestOre.</p>
    </div>

    <?php if ($repairMsg): ?>
        <div class="pagopa-alert"><?= h($repairMsg) ?></div>
    <?php endif; ?>

    <?php if (!$activity): ?>
        <div class="pagopa-toolbar-box">
            <form method="get" class="pagopa-toolbar">
                <div class="input-group pagopa-search">
                    <span class="input-group-addon"><span class="glyphicon glyphicon-search"></span></span>
                    <input type="text" name="q" class="form-control" value="<?= h($q) ?>" placeholder="Cerca viaggio, causale o descrizione">
                </div>
                <button type="submit" class="btn btn-primary"><span class="glyphicon glyphicon-search"></span> Cerca</button>
                <a class="btn btn-default" href="index.php"><span class="glyphicon glyphicon-remove"></span> Pulisci</a>
                <span class="pagopa-toolbar-spacer"></span>
                <button type="submit" form="pagopa-repair-form" name="repair_mapping" value="1" class="btn btn-success">
                    <span class="glyphicon glyphicon-refresh"></span> Mapping studenti
                </button>
            </form>
            <form id="pagopa-repair-form" method="post" onsubmit="return confirm('Aggiornare il mapping studenti tramite codice fiscale?');"></form>
        </div>

        <div class="pagopa-card-row">
            <div class="pagopa-card"><span class="card-icon glyphicon glyphicon-road"></span><span class="label">Attivita mostrate</span><span class="value"><?= intval($stats['attivita']) ?></span></div>
            <div class="pagopa-card"><span class="card-icon glyphicon glyphicon-user"></span><span class="label">Avvisi studenti</span><span class="value"><?= intval($stats['avvisi']) ?></span></div>
            <div class="pagopa-card pagopa-card-ok"><span class="card-icon glyphicon glyphicon-ok"></span><span class="label">Pagati</span><span class="value"><?= intval($stats['pagati']) ?></span></div>
            <div class="pagopa-card pagopa-card-warn"><span class="card-icon glyphicon glyphicon-alert"></span><span class="label">Non mappati</span><span class="value"><?= intval($stats['non_mappati']) ?></span></div>
            <div class="pagopa-card pagopa-card-money"><span class="card-icon glyphicon glyphicon-euro"></span><span class="label">Totale pagato</span><span class="value"><?= euroValue($stats['totale_pagato']) ?></span></div>
        </div>

        <div class="panel pagopa-panel">
            <div class="panel-heading"><span class="glyphicon glyphicon-list-alt"></span> Attivita importate da ISIREL</div>
            <div class="table-responsive">
                <table class="table table-bordered pagopa-table">
                    <thead>
                    <tr>
                        <th>Data invio</th><th>Scadenza</th><th>Viaggio / causale</th><th>Tipo</th><th>Importo</th><th>Avvisi</th><th>Mapping</th><th>Pagati</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (count($summary) === 0): ?>
                        <tr><td colspan="9" class="pagopa-empty">Nessuna attivita trovata con i filtri selezionati.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($summary as $r): ?>
                        <?php
                        $numAvvisi = max(0, intval($r['num_avvisi'] ?? 0));
                        $mappati = intval($r['mappati'] ?? 0);
                        $nonMappati = intval($r['non_mappati'] ?? 0);
                        $pagati = intval($r['pagati'] ?? 0);
                        $paidPerc = percentValue($pagati, $numAvvisi);
                        ?>
                        <tr>
                            <td class="text-center"><span class="pagopa-date"><?= dateIt($r['send_date']) ?></span></td>
                            <td class="text-center"><span class="pagopa-date"><?= dateIt($r['due_date']) ?></span></td>
                            <td>
                                <div class="pagopa-activity-title"><?= h($r['causal']) ?></div>
                                <div><?= h($r['descrizione']) ?></div>
                                <div class="pagopa-muted">ISIREL ID: <?= h($r['id_isirel']) ?></div>
                            </td>
                            <td><?= h($r['tipologia_descrizione']) ?></td>
                            <td class="text-right"><span class="pagopa-amount"><?= euroValue($r['importo']) ?></span></td>
                            <td class="text-center"><?= $numAvvisi ?></td>
                            <td>
                                <span class="pagopa-status <?= ($nonMappati === 0 ? 'pagopa-status-ok' : 'pagopa-status-bad') ?>"><?= $mappati ?>/<?= $numAvvisi ?></span>
                                <?php if ($nonMappati > 0): ?><div class="pagopa-muted"><?= $nonMappati ?> non mappati</div><?php endif; ?>
                            </td>
                            <td>
                                <strong><?= $pagati ?>/<?= $numAvvisi ?></strong>
                                <div class="pagopa-progress"><span style="width:<?= intval($paidPerc) ?>%"></span></div>
                            </td>
                            <td class="pagopa-actions">
                                <a class="btn btn-xs btn-primary pagopa-detail-btn" href="index.php?id=<?= intval($r['id']) ?>"><span class="glyphicon glyphicon-folder-open"></span> Dettaglio</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="pagopa-toolbar">
            <a class="btn btn-default" href="index.php"><span class="glyphicon glyphicon-arrow-left"></span> Torna all'elenco</a>
            <a class="btn btn-warning" href="index.php?id=<?= intval($activity['id']) ?>&problemi=1">Solo non mappati</a>
            <a class="btn btn-default" href="index.php?id=<?= intval($activity['id']) ?>">Tutti</a>
            <form method="post" onsubmit="return confirm('Aggiornare il mapping studenti tramite codice fiscale?');" style="margin:0;">
                <button type="submit" name="repair_mapping" value="1" class="btn btn-success"><span class="glyphicon glyphicon-refresh"></span> Aggiorna mapping studenti</button>
            </form>
        </div>

        <div class="panel pagopa-panel">
            <div class="panel-heading"><span class="glyphicon glyphicon-map-marker"></span> <?= h($activity['causal']) ?></div>
            <div class="panel-body">
                <p><?= h($activity['descrizione']) ?></p>
                <div class="pagopa-muted">
                    ISIREL ID: <?= h($activity['id_isirel']) ?> |
                    Invio: <?= dateIt($activity['send_date']) ?> |
                    Scadenza: <?= dateIt($activity['due_date']) ?> |
                    Importo: <?= euroValue($activity['importo']) ?>
                </div>
            </div>
        </div>

        <div class="panel pagopa-panel">
            <div class="panel-heading"><span class="glyphicon glyphicon-user"></span> Avvisi studenti <?= $soloProblemi ? '(solo non mappati)' : '' ?></div>
            <div class="table-responsive">
                <table class="table table-bordered pagopa-table">
                    <thead>
                    <tr>
                        <th>Studente ISIREL</th><th>Codice fiscale</th><th>Studente GestOre</th><th>Classe</th><th>Stato</th><th>Importo</th><th>IUV</th><th>Date</th><th>PDF</th><th>pagoPA</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $mapped = !empty($r['gestore_id']);
                        $stateClass = statoClass($r['payment_state'] ?? '');
                        ?>
                        <tr>
                            <td>
                                <strong><?= h($r['cognome']) ?> <?= h($r['nome']) ?></strong><br>
                                <span class="pagopa-muted">ISIREL student: <?= h($r['id_student_isirel']) ?></span><br>
                                <span class="pagopa-muted">Recipient: <?= h($r['id_recipient_isirel']) ?></span>
                            </td>
                            <td><?= h($r['codice_fiscale']) ?></td>
                            <td>
                                <?php if ($mapped): ?>
                                    <span class="pagopa-status pagopa-status-ok">Trovato</span><br>
                                    <strong><?= h($r['gestore_cognome']) ?> <?= h($r['gestore_nome']) ?></strong><br>
                                    <span class="pagopa-muted">ID GestOre: <?= h($r['gestore_id']) ?></span><br>
                                    <span class="pagopa-muted"><?= h($r['gestore_email']) ?></span>
                                <?php else: ?>
                                    <span class="pagopa-status pagopa-status-bad">Non trovato</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?= h($r['gestore_classe'] ?? '') ?></td>
                            <td>
                                <span class="pagopa-status <?= h($stateClass) ?>"><?= h(statoLabel($r['payment_state'])) ?></span><br>
                                <span class="pagopa-muted"><?= h($r['payment_state']) ?></span>
                                <?php if (intval($r['cancelled']) === 1): ?><br><span class="pagopa-status pagopa-status-bad">Annullato</span><?php endif; ?>
                            </td>
                            <td class="text-right"><span class="pagopa-amount"><?= euroValue($r['payment_amount']) ?></span></td>
                            <td><?= h($r['payment_iuv']) ?></td>
                            <td>
                                Pagamento: <?= dateIt($r['payment_date']) ?><br>
                                <span class="pagopa-muted">Supplier: <?= h($r['supplier_code']) ?></span><br>
                                <span class="pagopa-muted">Assessment: <?= h($r['assessment_code']) ?></span>
                            </td>
                            <td>
                                <?php if (!empty($r['pdf_file'])): ?>
                                    <a class="btn btn-xs btn-success" href="pagopaPdf.php?id=<?= h($r['id']) ?>" target="_blank" rel="noopener">
                                        <span class="glyphicon glyphicon-file"></span> PDF archiviato
                                    </a>
                                    <?php if (!empty($r['pdf_saved_at'])): ?>
                                        <br><span class="pagopa-muted">Salvato: <?= h(date('d/m/Y H:i', strtotime((string)$r['pdf_saved_at']))) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="pagopa-muted">Non archiviato</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($r['payment_link'])): ?>
                                    <a class="btn btn-xs btn-primary" href="<?= h($r['payment_link']) ?>" target="_blank" rel="noopener"><span class="glyphicon glyphicon-new-window"></span> Apri</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
