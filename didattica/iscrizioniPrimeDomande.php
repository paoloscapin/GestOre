<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

iscrizioniPrimeEnsureSchema();
$tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($_GET['tipo_iscrizione'] ?? 'prime');
$pageTitle = $tipoIscrizione === 'terze' ? 'Domande iscrizioni terze' : 'Domande iscrizioni prime';
$returnPage = $tipoIscrizione === 'terze' ? 'iscrizioniTerze.php' : 'iscrizioniPrime.php';
$istitutiScuole = scuoleIstitutiAll();

function ipd_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ipd_confirmed(array $pratica): array
{
    $confirmed = [];
    if (!empty($pratica['dati_confermati_json'])) {
        $decoded = json_decode((string)$pratica['dati_confermati_json'], true);
        if (is_array($decoded)) {
            $confirmed = $decoded;
        }
    }
    return $confirmed;
}

function ipd_value(array $pratica, array $confirmed, string $field): string
{
    return trim((string)($confirmed[$field] ?? $pratica[$field] ?? ''));
}

function ipd_decode_json_field(array $pratica, string $field): array
{
    if (empty($pratica[$field])) {
        return [];
    }

    $decoded = json_decode((string)$pratica[$field], true);
    return is_array($decoded) ? $decoded : [];
}

function ipd_raw_value(array $sources, array $exactKeys, array $keywordSets = []): string
{
    foreach ($sources as $source) {
        foreach ($exactKeys as $key) {
            if (isset($source[$key]) && trim((string)$source[$key]) !== '') {
                return trim((string)$source[$key]);
            }
        }
    }

    foreach ($sources as $source) {
        foreach ($source as $key => $value) {
            $value = trim((string)$value);
            if ($value === '') {
                continue;
            }
            $normalizedKey = strtolower((string)$key);
            foreach ($keywordSets as $keywords) {
                $matches = true;
                foreach ($keywords as $keyword) {
                    if (strpos($normalizedKey, strtolower($keyword)) === false) {
                        $matches = false;
                        break;
                    }
                }
                if ($matches) {
                    return $value;
                }
            }
        }
    }

    return '';
}

function ipd_extra_info(array $pratica): array
{
    $sources = [
        ipd_decode_json_field($pratica, 'raw_anagrafica_json'),
        ipd_decode_json_field($pratica, 'raw_prime_json'),
        ipd_decode_json_field($pratica, 'raw_dsa_json'),
    ];

    $indirizzo = ipd_raw_value($sources, [
        'INDIRIZZO RESIDENZA',
        'INDIRIZZO_RESIDENZA',
        'RESIDENZA_INDIRIZZO',
        'VIA RESIDENZA',
        'INDIRIZZO',
    ], [['indirizzo', 'residenza'], ['via', 'residenza']]);
    $comune = ipd_raw_value($sources, [
        'COMUNE RESIDENZA',
        'COMUNE_RESIDENZA',
        'RESIDENZA_COMUNE',
        'LOCALITA RESIDENZA',
        'CITTA RESIDENZA',
        'COMUNE',
    ], [['comune', 'residenza'], ['citta', 'residenza'], ['localita', 'residenza']]);
    $provincia = ipd_raw_value($sources, [
        'PROVINCIA RESIDENZA',
        'PROVINCIA_RESIDENZA',
        'RESIDENZA_PROVINCIA',
        'PROV',
    ], [['provincia', 'residenza'], ['prov', 'residenza']]);
    $cap = ipd_raw_value($sources, [
        'CAP RESIDENZA',
        'CAP_RESIDENZA',
        'RESIDENZA_CAP',
        'CAP',
    ], [['cap', 'residenza']]);
    $scuola = ipd_raw_value($sources, [
        'SCUOLA DI PROVENIENZA',
        'SCUOLA PROVENIENZA',
        'DENOMINAZIONE SCUOLA PROVENIENZA',
        'SCUOLA MEDIA DI PROVENIENZA',
        'ISTITUTO DI PROVENIENZA',
        'SCUOLA UTENZA',
        'SCUOLA',
    ], [['scuola', 'provenienza'], ['istituto', 'provenienza'], ['scuola', 'media'], ['scuola', 'utenza']]);

    $residenzaParts = array_filter([$indirizzo, trim($cap . ' ' . $comune), $provincia], fn($value) => trim((string)$value) !== '');

    return [
        'residenza' => implode(' - ', $residenzaParts),
        'scuola_provenienza' => $scuola,
    ];
}

function ipd_badge_class(string $stato): string
{
    if ($stato === 'verificata') return 'label-success';
    if ($stato === 'da_integrare') return 'label-warning';
    if ($stato === 'inviata') return 'label-primary';
    if ($stato === 'annullata') return 'label-danger';
    return 'label-default';
}

$filtroStato = trim((string)($_GET['stato'] ?? 'inviata'));
$allowedFilters = ['tutte', 'inviata', 'verificata', 'da_integrare', 'annullata'];
if (!in_array($filtroStato, $allowedFilters, true)) {
    $filtroStato = 'inviata';
}

$where = "tipo_iscrizione = " . dbQ($tipoIscrizione) . " AND stato IN ('inviata', 'verificata', 'da_integrare', 'annullata')";
if ($filtroStato !== 'tutte') {
    $where = "tipo_iscrizione = " . dbQ($tipoIscrizione) . " AND stato = " . dbQ($filtroStato);
}

$pratiche = dbGetAll("
    SELECT *
    FROM iscrizioni_prime_pratiche
    WHERE $where
    ORDER BY updated_at DESC, cognome ASC, nome ASC
");

$stats = dbGetFirst("
    SELECT
        SUM(stato = 'inviata') AS inviate,
        SUM(stato = 'verificata') AS verificate,
        SUM(stato = 'da_integrare') AS da_integrare,
        SUM(stato = 'annullata') AS annullate
    FROM iscrizioni_prime_pratiche
    WHERE tipo_iscrizione = " . dbQ($tipoIscrizione) . "
");

$labels = array_merge(iscrizioniPrimeDocumentTypes($tipoIscrizione), iscrizioniPrimeSecretaryDocumentTypes($tipoIscrizione));
$eventiPratiche = [];
foreach ($pratiche as $praticaEvento) {
    $eventiPratiche[intval($praticaEvento['id'] ?? 0)] = iscrizioniPrimeEventsForPratica($praticaEvento);
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <title><?php echo ipd_h($pageTitle); ?></title>
    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    ?>
    <style>
        .ipd-toolbar { margin-bottom: 14px; }
        .ipd-card { border: 1px solid #d9e0ea; border-radius: 6px; margin-bottom: 14px; background: #fff; box-shadow: 0 3px 14px rgba(0,0,0,.06); }
        .ipd-filter-box { margin: 12px 0 0; }
        .ipd-filter-box input { width: 100%; max-width: 620px; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px 12px; font-size: 15px; }
        .ipd-filter-count { display: inline-block; margin-left: 10px; color: #64748b; }
        .ipd-bulk-actions { margin-top: 12px; }
        .ipd-progress { width: 100%; height: 14px; border-radius: 999px; background: #e2e8f0; overflow: hidden; margin-top: 8px; }
        .ipd-progress > span { display: block; height: 100%; width: 0; background: #1d4ed8; transition: width .25s ease; }
        .ipd-card-head { padding: 12px 14px; border-bottom: 1px solid #e8edf4; background: #f8fafc; display: flex; gap: 12px; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; }
        .ipd-card-body { padding: 14px; }
        .ipd-name { font-size: 18px; font-weight: 700; }
        .ipd-meta { color: #64748b; margin-top: 3px; }
        .ipd-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px 14px; margin-bottom: 12px; }
        .ipd-field { border-bottom: 1px solid #edf1f6; padding-bottom: 6px; }
        .ipd-label { color: #64748b; font-size: 12px; }
        .ipd-value { font-weight: 650; overflow-wrap: anywhere; }
        .ipd-doc-status { font-weight: 700; }
        .ipd-doc-status.ok { color: #166534; }
        .ipd-doc-status.paper { color: #92400e; }
        .ipd-doc-status.missing { color: #b91c1c; }
        .ipd-empty { padding: 18px; color: #64748b; }
        .ipd-status-help { margin-top: 10px; color: #64748b; line-height: 1.45; }
        .ipd-status-actions { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; justify-content: flex-end; }
        .ipd-card-body { display: none; }
        .ipd-card.open .ipd-card-body { display: block; }
        .ipd-summary-line { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 5px; color: #64748b; }
        .ipd-pill { display: inline-block; border-radius: 999px; background: #e2e8f0; color: #334155; padding: 2px 8px; font-size: 12px; font-weight: 700; }
        .ipd-pill.ok { background: #dcfce7; color: #166534; }
        .ipd-pill.paper { background: #fef3c7; color: #92400e; }
        .ipd-pill.missing { background: #fee2e2; color: #991b1b; }
        .ipd-pill.news { background: #f97316; color: #fff; box-shadow: 0 0 0 2px rgba(249,115,22,.18); }
        .ipd-news-box { border: 2px solid #fb923c; border-left-width: 7px; background: #fff7ed; color: #7c2d12; border-radius: 8px; padding: 10px 12px; margin-bottom: 12px; font-weight: 750; }
        .ipd-news-box small { display: block; margin-top: 4px; color: #9a3412; font-weight: 650; }
        .ipd-toggle { min-width: 92px; }
        .ipd-secretary-docs { margin-top: 18px; padding: 12px; border: 1px solid #bfdbfe; border-radius: 6px; background: #eff6ff; }
        .ipd-secretary-docs h4 { margin-top: 0; }
        .ipd-secretary-upload { display: inline-flex; gap: 6px; align-items: center; flex-wrap: wrap; margin: 3px 0 0 0; }
        .ipd-secretary-upload input[type="file"] { max-width: 260px; }
        .ipd-modal-backdrop { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(15,23,42,.62); z-index: 4000; padding: 16px; }
        .ipd-modal-backdrop.open { display: flex; }
        .ipd-modal-box { width: min(620px, 100%); background: #fff; border-radius: 8px; box-shadow: 0 22px 56px rgba(0,0,0,.28); overflow: hidden; }
        .ipd-modal-head { padding: 14px 16px; background: #92400e; color: #fff; font-weight: 800; font-size: 18px; }
        .ipd-modal-body { padding: 16px; }
        .ipd-modal-body input[type="text"] { width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 9px 10px; }
        .ipd-modal-body select { width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 9px 10px; background: #fff; }
        .ipd-modal-body textarea { width: 100%; min-height: 150px; resize: vertical; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; }
        .ipd-rich-tools { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 6px; }
        .ipd-rich-tools .btn { font-weight: 700; }
        .ipd-modal-field { margin-bottom: 12px; }
        .ipd-modal-field label { display: block; margin-bottom: 5px; font-weight: 700; }
        .ipd-modal-field .help-block { margin: 4px 0 0; }
        .ipd-modal-actions { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 16px; border-top: 1px solid #e5e7eb; background: #f8fafc; }
        .ipd-cambio-layout { display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(360px, .95fr); gap: 16px; align-items: start; }
        .ipd-cambio-history { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; background: #fff; max-height: calc(100vh - 210px); overflow: auto; }
        .ipd-cambio-event { border: 1px solid #dbe4ef; border-left: 5px solid #7f1d1d; border-radius: 6px; padding: 10px 12px; margin-bottom: 8px; background: #f8fafc; }
        .ipd-cambio-event-head { display: flex; justify-content: space-between; gap: 8px; flex-wrap: wrap; font-weight: 800; }
        .ipd-cambio-event-meta { color: #64748b; margin-top: 4px; }
        .ipd-cambio-event-note { margin-top: 6px; white-space: pre-wrap; }
        @media (max-width: 900px) {
            .ipd-grid { grid-template-columns: 1fr; }
            .ipd-status-actions { justify-content: flex-start; }
            .ipd-cambio-layout { grid-template-columns: 1fr; }
            .ipd-cambio-history { max-height: none; }
        }
    </style>
</head>
<body>
<?php require_once '../common/header-didattica.php'; ?>

<div class="container-fluid">
    <div class="panel panel-lightblue4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-inbox"></span>&ensp;<?php echo ipd_h($pageTitle); ?> inviate
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-md-2"><strong>Inviate:</strong> <?php echo intval($stats['inviate'] ?? 0); ?></div>
                <div class="col-md-2"><strong>Verificate:</strong> <?php echo intval($stats['verificate'] ?? 0); ?></div>
                <div class="col-md-2"><strong>Da integrare:</strong> <?php echo intval($stats['da_integrare'] ?? 0); ?></div>
                <div class="col-md-2"><strong>Cambio scuola:</strong> <?php echo intval($stats['annullate'] ?? 0); ?></div>
                <div class="col-md-4 text-right">
                    <a class="btn btn-default btn-sm" href="iscrizioniContattiVariazioni.php?tipo_iscrizione=<?php echo urlencode($tipoIscrizione); ?>">
                        <span class="glyphicon glyphicon-transfer"></span> Variazioni contatti
                    </a>
                    <a class="btn btn-default btn-sm" href="<?php echo ipd_h($returnPage); ?>">
                        <span class="glyphicon glyphicon-arrow-left"></span> Torna a import/invio link
                    </a>
                </div>
            </div>
            <hr>
            <div class="ipd-toolbar">
                <div class="btn-group">
                    <?php foreach (['inviata' => 'Inviate', 'verificata' => 'Verificate', 'da_integrare' => 'Da integrare', 'annullata' => 'Cambio scuola', 'tutte' => 'Tutte'] as $key => $label) : ?>
                        <a class="btn btn-<?php echo $filtroStato === $key ? 'primary' : 'default'; ?>" href="?tipo_iscrizione=<?php echo urlencode($tipoIscrizione); ?>&stato=<?php echo urlencode($key); ?>"><?php echo ipd_h($label); ?></a>
                    <?php endforeach; ?>
                </div>
                <div class="ipd-filter-box">
                    <label for="ipdLiveFilter">Cerca pratica</label><br>
                    <input type="search" id="ipdLiveFilter" placeholder="Scrivi nome, cognome, codice fiscale, corso, email, scuola...">
                    <span id="ipdFilterCount" class="ipd-filter-count"></span>
                </div>
                <div class="ipd-bulk-actions">
                    <button type="button" class="btn btn-primary" onclick="ipdOpenBulkMailModal()">
                        <span class="glyphicon glyphicon-envelope"></span> Scrivi a tutti i genitori
                    </button>
                    <span class="text-muted">Invia ai genitori di tutte le pratiche <?php echo $tipoIscrizione === 'terze' ? 'esterne delle terze' : 'delle prime'; ?>, indipendentemente dallo stato.</span>
                </div>
                <div class="ipd-status-help">
                    <strong>Uso degli stati:</strong> "Segna verificata" chiude il controllo della segreteria, "Richiedi integrazione" riapre la pratica, invia una mail ai genitori con le indicazioni della segreteria e permette di correggere/reinviare, "Rimetti in inviata" riporta una domanda allo stato ricevuto senza inviare mail. "Cambio scuola" mantiene la pratica archiviata ma la esclude dagli invii massivi.
                </div>
            </div>
        </div>
    </div>

    <?php if (!$pratiche) : ?>
        <div class="panel panel-default"><div class="ipd-empty">Nessuna domanda nel filtro selezionato.</div></div>
    <?php endif; ?>

    <?php foreach ($pratiche as $pratica) :
        $confirmed = ipd_confirmed($pratica);
        $documents = iscrizioniPrimeDocumentsForPratica((int)$pratica['id']);
        $secretaryDocuments = $tipoIscrizione === 'terze' ? iscrizioniPrimeSecretaryDocumentsForPratica((int)$pratica['id']) : [];
        $nome = trim((string)(($pratica['cognome'] ?? '') . ' ' . ($pratica['nome'] ?? '')));
        $extraInfo = ipd_extra_info($pratica);
        $docCounts = ['ok' => 0, 'paper' => 0, 'missing' => 0];
        foreach ($documents as $documentCountRow) {
            $tipoCount = (string)$documentCountRow['tipo_documento'];
            if ($tipoCount === 'altro' && (string)$documentCountRow['stato'] === 'mancante') {
                continue;
            }
            if (in_array($tipoCount, ['documento_identita_genitore_2', 'codice_fiscale_genitore_2', 'documento_cf_genitore_2'], true) && !hasSecondResponsibleForIscrizioniPrime($pratica, $confirmed)) {
                continue;
            }
            $statoCount = (string)$documentCountRow['stato'];
            if ($statoCount === 'consegna_cartacea') {
                $docCounts['paper']++;
            } elseif (in_array($statoCount, ['caricato', 'estratto', 'verificato'], true)) {
                $docCounts['ok']++;
            } else {
                $docCounts['missing']++;
            }
        }
    ?>
        <div class="ipd-card" id="pratica-<?php echo intval($pratica['id']); ?>">
            <div class="ipd-card-head">
                <div>
                    <div class="ipd-name"><?php echo ipd_h($nome); ?></div>
                    <div class="ipd-meta">
                        <?php echo ipd_h($pratica['codice_fiscale'] ?? ''); ?> ·
                        <?php echo ipd_h($pratica['corso_studi'] ?? ''); ?> ·
                        aggiornata <?php echo ipd_h(iscrizioniPrimeFormatDateIt($pratica['updated_at'] ?? '')); ?>
                    </div>
                    <div class="ipd-summary-line">
                        <span class="ipd-pill ok"><?php echo intval($docCounts['ok']); ?> caricati</span>
                        <span class="ipd-pill paper"><?php echo intval($docCounts['paper']); ?> cartacei</span>
                        <?php if ($docCounts['missing'] > 0) : ?>
                            <span class="ipd-pill missing"><?php echo intval($docCounts['missing']); ?> mancanti</span>
                        <?php endif; ?>
                        <?php if (!empty($pratica['novita_segreteria_at'])) : ?>
                            <span class="ipd-pill news">Novita' per segreteria</span>
                        <?php endif; ?>
                        <?php if ($extraInfo['scuola_provenienza'] !== '') : ?>
                            <span class="ipd-pill">Scuola: <?php echo ipd_h($extraInfo['scuola_provenienza']); ?></span>
                        <?php endif; ?>
                        <?php if ($extraInfo['residenza'] !== '') : ?>
                            <span class="ipd-pill">Residenza: <?php echo ipd_h($extraInfo['residenza']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="ipd-status-actions">
                    <span class="label <?php echo ipd_badge_class((string)$pratica['stato']); ?>"><?php echo ipd_h($pratica['stato']); ?></span>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-info ipd-toggle" onclick="ipdToggleDettagli(<?php echo intval($pratica['id']); ?>, this)">Dettagli</button>
                        <button type="button" class="btn btn-primary" title="Invia una comunicazione personalizzata ai genitori collegati alla pratica." onclick="ipdOpenCustomMailModal(<?php echo intval($pratica['id']); ?>, <?php echo ipd_h(json_encode($nome, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)">Scrivi ai genitori</button>
                        <button type="button" class="btn btn-success" title="La pratica e stata controllata ed e completa." onclick="ipdSetStato(<?php echo intval($pratica['id']); ?>, 'verificata')">Segna verificata</button>
                        <button type="button" class="btn btn-warning" title="Riapre la pratica e invia una mail ai genitori." onclick="ipdSetStato(<?php echo intval($pratica['id']); ?>, 'da_integrare')">Richiedi integrazione</button>
                        <button type="button" class="btn btn-default" title="Riporta la pratica allo stato ricevuto/inviata." onclick="ipdSetStato(<?php echo intval($pratica['id']); ?>, 'inviata')">Rimetti in inviata</button>
                        <button type="button" class="btn btn-danger" title="La famiglia ha cambiato scuola: la pratica resta archiviata ma non riceve piu comunicazioni automatiche." onclick="ipdOpenCambioScuolaModal(<?php echo intval($pratica['id']); ?>)">Cambio scuola</button>
                    </div>
                </div>
            </div>
            <div class="ipd-card-body">
                <?php if (!empty($pratica['novita_segreteria_at'])) : ?>
                    <div class="ipd-news-box">
                        Ci sono novita' da controllare: <?php echo ipd_h($pratica['novita_segreteria_messaggio'] ?: 'la pratica e stata aggiornata dalla famiglia.'); ?>
                        <small>Aggiornamento registrato il <?php echo ipd_h(iscrizioniPrimeFormatDateTimeIt($pratica['novita_segreteria_at'])); ?>. Il flag viene tolto quando segni la pratica come verificata.</small>
                    </div>
                <?php endif; ?>
                <div class="ipd-grid">
                    <div class="ipd-field"><div class="ipd-label">Data nascita</div><div class="ipd-value"><?php echo ipd_h(iscrizioniPrimeFormatDateIt($pratica['data_nascita'] ?? '')); ?></div></div>
                    <div class="ipd-field"><div class="ipd-label">Email studente</div><div class="ipd-value"><?php echo ipd_h(ipd_value($pratica, $confirmed, 'email_studente')); ?></div></div>
                    <div class="ipd-field"><div class="ipd-label">Telefono studente</div><div class="ipd-value"><?php echo ipd_h(ipd_value($pratica, $confirmed, 'telefono_studente')); ?></div></div>
                    <div class="ipd-field"><div class="ipd-label"><?php echo ipd_h($pratica['responsabile_1_tipo'] ?: 'Responsabile 1'); ?></div><div class="ipd-value"><?php echo ipd_h(trim(($pratica['responsabile_1_cognome'] ?? '') . ' ' . ($pratica['responsabile_1_nome'] ?? ''))); ?></div></div>
                    <div class="ipd-field"><div class="ipd-label">Email responsabile 1</div><div class="ipd-value"><?php echo ipd_h(ipd_value($pratica, $confirmed, 'email_genitore_1')); ?></div></div>
                    <div class="ipd-field"><div class="ipd-label">Telefono responsabile 1</div><div class="ipd-value"><?php echo ipd_h(ipd_value($pratica, $confirmed, 'telefono_genitore_1')); ?></div></div>
                    <div class="ipd-field"><div class="ipd-label"><?php echo ipd_h($pratica['responsabile_2_tipo'] ?: 'Responsabile 2'); ?></div><div class="ipd-value"><?php echo ipd_h(trim(($pratica['responsabile_2_cognome'] ?? '') . ' ' . ($pratica['responsabile_2_nome'] ?? ''))); ?></div></div>
                    <div class="ipd-field"><div class="ipd-label">Email responsabile 2</div><div class="ipd-value"><?php echo ipd_h(ipd_value($pratica, $confirmed, 'email_genitore_2')); ?></div></div>
                    <div class="ipd-field"><div class="ipd-label">Telefono responsabile 2</div><div class="ipd-value"><?php echo ipd_h(ipd_value($pratica, $confirmed, 'telefono_genitore_2')); ?></div></div>
                    <div class="ipd-field"><div class="ipd-label">Residenza</div><div class="ipd-value"><?php echo ipd_h($extraInfo['residenza'] ?: 'Non disponibile nei dati importati'); ?></div></div>
                    <div class="ipd-field"><div class="ipd-label">Scuola di provenienza</div><div class="ipd-value"><?php echo ipd_h($extraInfo['scuola_provenienza'] ?: 'Non disponibile nei dati importati'); ?></div></div>
                </div>

                <h4>Documenti</h4>
                <div class="table-responsive">
                    <table class="table table-condensed table-bordered">
                        <thead>
                            <tr>
                                <th>Documento</th>
                                <th>Stato</th>
                                <th>File</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $document) :
                                $tipo = (string)$document['tipo_documento'];
                                if ($tipo === 'altro' && (string)$document['stato'] === 'mancante') {
                                    continue;
                                }
                                if (in_array($tipo, ['documento_identita_genitore_2', 'codice_fiscale_genitore_2', 'documento_cf_genitore_2'], true) && !hasSecondResponsibleForIscrizioniPrime($pratica, $confirmed)) {
                                    continue;
                                }
                                $statoDoc = (string)$document['stato'];
                                $statusClass = $statoDoc === 'consegna_cartacea' ? 'paper' : (in_array($statoDoc, ['caricato', 'estratto', 'verificato'], true) ? 'ok' : 'missing');
                            ?>
                                <tr>
                                    <td><?php echo ipd_h($labels[$tipo] ?? $tipo); ?></td>
                                    <td class="ipd-doc-status <?php echo $statusClass; ?>"><?php echo ipd_h($statoDoc === 'consegna_cartacea' ? 'consegna cartacea' : $statoDoc); ?></td>
                                    <td>
                                        <?php if ($statusClass === 'ok') : ?>
                                            <a class="btn btn-xs btn-primary" target="_blank" rel="noopener" href="iscrizioniPrimeDocumento.php?pratica_id=<?php echo intval($pratica['id']); ?>&tipo=<?php echo rawurlencode($tipo); ?>">
                                                <span class="glyphicon glyphicon-file"></span> Apri PDF
                                            </a>
                                            <span class="text-muted"><?php echo ipd_h($document['original_name'] ?? ''); ?></span>
                                        <?php elseif ($statoDoc === 'consegna_cartacea') : ?>
                                            <span class="text-muted">Consegna in segreteria didattica</span>
                                            <form class="ipd-secretary-upload" onsubmit="return ipdUploadSegreteriaDocumento(event, <?php echo intval($pratica['id']); ?>, '<?php echo ipd_h($tipo); ?>');" enctype="multipart/form-data">
                                                <input type="file" name="pdf" accept="application/pdf,.pdf" required>
                                                <button type="submit" class="btn btn-xs btn-success">
                                                    <span class="glyphicon glyphicon-upload"></span> Carica scansione PDF
                                                </button>
                                            </form>
                                        <?php else : ?>
                                            <span class="text-danger">Mancante</span>
                                            <form class="ipd-secretary-upload" onsubmit="return ipdUploadSegreteriaDocumento(event, <?php echo intval($pratica['id']); ?>, '<?php echo ipd_h($tipo); ?>');" enctype="multipart/form-data">
                                                <input type="file" name="pdf" accept="application/pdf,.pdf" required>
                                                <button type="submit" class="btn btn-xs btn-default">
                                                    <span class="glyphicon glyphicon-upload"></span> Carica PDF
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php $eventi = $eventiPratiche[intval($pratica['id'])] ?? []; ?>
                <h4>Storico pratica</h4>
                <?php if (!$eventi) : ?>
                    <div class="ipd-empty">Nessun evento registrato.</div>
                <?php else : ?>
                    <div class="ipd-cambio-history" style="max-height:none;">
                        <?php foreach ($eventi as $evento) :
                            $dettagli = [];
                            if (!empty($evento['dettagli_json'])) {
                                $decodedDetails = json_decode((string)$evento['dettagli_json'], true);
                                if (is_array($decodedDetails)) {
                                    $dettagli = $decodedDetails;
                                }
                            }
                        ?>
                            <div class="ipd-cambio-event">
                                <div class="ipd-cambio-event-head">
                                    <span><?php echo ipd_h($evento['titolo'] ?? $evento['tipo_evento'] ?? 'Evento'); ?></span>
                                    <span><?php echo ipd_h(iscrizioniPrimeFormatDateTimeIt($evento['created_at'] ?? '')); ?></span>
                                </div>
                                <div class="ipd-cambio-event-meta">
                                    <?php if (!empty($evento['created_by'])) : ?>
                                        Operatore: <?php echo ipd_h($evento['created_by']); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($evento['stato_precedente']) || !empty($evento['stato_nuovo'])) : ?>
                                        &middot; Stato: <?php echo ipd_h(($evento['stato_precedente'] ?? '-') . ' -> ' . ($evento['stato_nuovo'] ?? '-')); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($evento['oggetto'])) : ?>
                                        &middot; Oggetto: <?php echo ipd_h($evento['oggetto']); ?>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($evento['messaggio'])) : ?>
                                    <div class="ipd-cambio-event-note"><?php echo ipd_h($evento['messaggio']); ?></div>
                                <?php endif; ?>
                                <?php if ($dettagli) : ?>
                                    <div class="ipd-cambio-event-meta">
                                        <?php foreach ($dettagli as $key => $value) :
                                            if (is_array($value)) {
                                                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                            }
                                            if (trim((string)$value) === '') {
                                                continue;
                                            }
                                        ?>
                                            <span><?php echo ipd_h(str_replace('_', ' ', (string)$key)); ?>: <?php echo ipd_h($value); ?></span>&nbsp;
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($secretaryDocuments) : ?>
                    <div class="ipd-secretary-docs">
                        <h4>Documenti ricevuti da altra segreteria</h4>
                        <div class="text-muted" style="margin-bottom: 8px;">
                            Questi PDF non sono richiesti al genitore: arrivano via mail dalla scuola di provenienza e vengono archiviati qui dalla segreteria didattica.
                        </div>
                        <div class="table-responsive">
                            <table class="table table-condensed table-bordered">
                                <thead>
                                    <tr>
                                        <th>Documento</th>
                                        <th>Stato</th>
                                        <th>File</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($secretaryDocuments as $document) :
                                        $tipo = (string)$document['tipo_documento'];
                                        $statoDoc = (string)($document['stato'] ?? 'mancante');
                                        $isUploaded = in_array($statoDoc, ['caricato', 'estratto', 'verificato'], true);
                                        $statusClass = $isUploaded ? 'ok' : 'missing';
                                    ?>
                                        <tr>
                                            <td><?php echo ipd_h($labels[$tipo] ?? $tipo); ?></td>
                                            <td class="ipd-doc-status <?php echo $statusClass; ?>"><?php echo $isUploaded ? 'caricato' : 'mancante'; ?></td>
                                            <td>
                                                <?php if ($isUploaded) : ?>
                                                    <a class="btn btn-xs btn-primary" target="_blank" rel="noopener" href="iscrizioniPrimeDocumento.php?pratica_id=<?php echo intval($pratica['id']); ?>&tipo=<?php echo rawurlencode($tipo); ?>">
                                                        <span class="glyphicon glyphicon-file"></span> Apri PDF
                                                    </a>
                                                    <span class="text-muted"><?php echo ipd_h($document['original_name'] ?? ''); ?></span>
                                                <?php else : ?>
                                                    <span class="text-danger">Mancante</span>
                                                <?php endif; ?>
                                                <form class="ipd-secretary-upload" onsubmit="return ipdUploadSegreteriaDocumento(event, <?php echo intval($pratica['id']); ?>, '<?php echo ipd_h($tipo); ?>');" enctype="multipart/form-data">
                                                    <input type="file" name="pdf" accept="application/pdf,.pdf" required>
                                                    <button type="submit" class="btn btn-xs <?php echo $isUploaded ? 'btn-default' : 'btn-success'; ?>">
                                                        <span class="glyphicon glyphicon-upload"></span> <?php echo $isUploaded ? 'Sostituisci PDF' : 'Carica PDF'; ?>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div id="ipdCambioScuolaModal" class="ipd-modal-backdrop" aria-hidden="true">
    <div class="ipd-modal-box" role="dialog" aria-modal="true" aria-labelledby="ipdCambioScuolaTitle" style="width:min(1180px,100%);">
        <div id="ipdCambioScuolaTitle" class="ipd-modal-head" style="background:#7f1d1d;">Cambio scuola</div>
        <form id="ipdCambioScuolaForm" enctype="multipart/form-data">
            <div class="ipd-modal-body">
                <input type="hidden" name="id" id="ipdCambioScuolaId">
                <p id="ipdCambioScuolaStudent" class="text-muted"></p>
                <div class="alert alert-warning">
                    Questa pratica verra' segnata come cambio scuola e non ricevera' piu comunicazioni automatiche per completare l'iscrizione.
                </div>
                <div class="ipd-cambio-layout">
                <div>
                    <h4>Nuovo aggiornamento</h4>
                    <div class="row">
                    <div class="col-sm-6 ipd-modal-field">
                        <label for="ipdCambioScuolaData">Data richiesta</label>
                        <input type="date" name="richiesta_data" id="ipdCambioScuolaData" class="form-control">
                    </div>
                    <div class="col-sm-6 ipd-modal-field">
                        <label for="ipdCambioScuolaCanale">Richiesta arrivata via</label>
                        <select name="canale" id="ipdCambioScuolaCanale" class="form-control">
                            <option value="mail">Mail</option>
                            <option value="telefono">Telefono</option>
                            <option value="presenza">Di persona</option>
                            <option value="altro">Altro</option>
                        </select>
                    </div>
                    <div class="col-sm-12 ipd-modal-field">
                        <label for="ipdCambioScuolaScuolaDestinazione">Scuola di destinazione</label>
                        <input type="hidden" name="scuola_destinazione" id="ipdCambioScuolaScuolaDestinazione">
                        <select name="id_istituto_destinazione" id="ipdCambioScuolaIstitutoDestinazione" class="form-control">
                            <?php echo scuoleIstitutiSelectOptionsHtml(null); ?>
                        </select>
                        <input type="text" id="ipdCambioScuolaScuolaDestinazioneManuale" class="form-control" style="margin-top:8px;" placeholder="Se non trovi la scuola nell'elenco, scrivila qui">
                        <div id="ipdCambioScuolaScuolaDestinazioneLibera" class="help-block" style="display:none;"></div>
                    </div>
                    <div class="col-sm-12 ipd-modal-field">
                        <label for="ipdCambioScuolaIndirizzoDestinazione">Indirizzo di studio nella scuola di destinazione</label>
                        <input type="text" name="indirizzo_destinazione" id="ipdCambioScuolaIndirizzoDestinazione" class="form-control" placeholder="Es. informatica, liceo scientifico, meccanica...">
                    </div>
                    <div class="col-sm-6 ipd-modal-field">
                        <label for="ipdCambioScuolaColloquio">Colloquio uscita</label>
                        <select name="colloquio_stato" id="ipdCambioScuolaColloquio" class="form-control">
                            <option value="da_valutare">Da valutare</option>
                            <option value="da_fare">Da fare</option>
                            <option value="fatto">Fatto</option>
                            <option value="non_necessario">Non necessario</option>
                        </select>
                    </div>
                    <div class="col-sm-6 ipd-modal-field">
                        <label for="ipdCambioScuolaNullaOsta">Nulla osta</label>
                        <select name="nulla_osta_stato" id="ipdCambioScuolaNullaOsta" class="form-control">
                            <option value="da_richiedere">Da richiedere</option>
                            <option value="richiesto">Richiesto dalla famiglia</option>
                            <option value="ricevuto">Ricevuto/in lavorazione</option>
                            <option value="evaso_inviato">Evaso / inviato</option>
                            <option value="non_necessario">Non necessario</option>
                        </select>
                    </div>
                    <div class="col-sm-6 ipd-modal-field">
                        <label for="ipdCambioScuolaDocumenti">Documenti pratica</label>
                        <select name="documenti_stato" id="ipdCambioScuolaDocumenti" class="form-control">
                            <option value="da_verificare">Da verificare</option>
                            <option value="manca_qualcosa">Manca qualcosa</option>
                            <option value="completi">Completi</option>
                        </select>
                    </div>
                    <div class="col-sm-6 ipd-modal-field">
                        <label for="ipdCambioScuolaPraticaStato">Stato pratica cambio scuola</label>
                        <select name="pratica_stato" id="ipdCambioScuolaPraticaStato" class="form-control">
                            <option value="aperta">Aperta</option>
                            <option value="in_attesa">In attesa</option>
                            <option value="completata">Completata</option>
                        </select>
                    </div>
                    <div class="col-sm-12 ipd-modal-field">
                        <label for="ipdCambioScuolaAllegato">PDF collegato a questo aggiornamento</label>
                        <input type="file" name="allegato" id="ipdCambioScuolaAllegato" accept="application/pdf,.pdf" class="form-control">
                        <div class="help-block">Puoi allegare, per esempio, la stampa PDF della mail ricevuta o inviata. Ogni salvataggio resta nello storico.</div>
                    </div>
                    <div class="col-sm-12 ipd-modal-field">
                        <label for="ipdCambioScuolaNote">Note segreteria</label>
                        <textarea name="note" id="ipdCambioScuolaNote" placeholder="Annota cosa e' stato comunicato, eventuali documenti mancanti, contatti con la famiglia o con la scuola di destinazione."></textarea>
                    </div>
                    </div>
                </div>
                    <div class="ipd-cambio-history">
                        <h4>Storico aggiornamenti</h4>
                        <div id="ipdCambioScuolaStorico" class="text-muted">Nessun aggiornamento registrato.</div>
                    </div>
                </div>
                <div id="ipdCambioScuolaError" class="text-danger" style="margin-top:8px;" hidden></div>
            </div>
            <div class="ipd-modal-actions">
                <button type="button" class="btn btn-default" onclick="ipdCloseCambioScuolaModal()">Annulla</button>
                <button type="submit" class="btn btn-danger">Salva cambio scuola</button>
            </div>
        </form>
    </div>
</div>

<div id="ipdBulkMailModal" class="ipd-modal-backdrop" aria-hidden="true">
    <div class="ipd-modal-box" role="dialog" aria-modal="true" aria-labelledby="ipdBulkMailTitle">
        <div id="ipdBulkMailTitle" class="ipd-modal-head" style="background:#1d4ed8;">Scrivi a tutti i genitori</div>
        <div class="ipd-modal-body">
            <p class="text-muted">
                La comunicazione verra' inviata a lotti. Le pratiche segnate come cambio scuola non ricevono questa mail.
            </p>
            <div class="ipd-modal-field">
                <label for="ipdBulkMailAudience">Destinatari</label>
                <select id="ipdBulkMailAudience">
                    <option value="esterni">Famiglie esterne da seguire</option>
                    <option value="interni">Studenti gia nostri / ripetenti</option>
                    <option value="interni_bocciati">Solo interni bocciati da tabellone</option>
                    <option value="tutte">Tutte le pratiche attive</option>
                </select>
                <div class="help-block text-muted">Usa "Solo interni bocciati da tabellone" per rettifiche mirate agli studenti gia nostri che risultano non ammessi.</div>
            </div>
            <div class="ipd-modal-field">
                <label for="ipdBulkMailSubject">Oggetto</label>
                <input type="text" id="ipdBulkMailSubject" value="Comunicazione iscrizione">
            </div>
            <div class="ipd-modal-field">
                <label for="ipdBulkMailMessage">Messaggio</label>
                <div class="ipd-rich-tools">
                    <button type="button" class="btn btn-default btn-xs" onclick="ipdFormatTextarea('ipdBulkMailMessage', 'bold')"><strong>B</strong></button>
                    <button type="button" class="btn btn-default btn-xs" onclick="ipdFormatTextarea('ipdBulkMailMessage', 'ul')">Elenco puntato</button>
                    <button type="button" class="btn btn-default btn-xs" onclick="ipdFormatTextarea('ipdBulkMailMessage', 'ol')">Elenco numerato</button>
                </div>
                <textarea id="ipdBulkMailMessage" placeholder="Scrivi qui il testo da inviare a tutte le famiglie."></textarea>
                <div class="help-block text-muted">Puoi usare **testo** per il grassetto, righe che iniziano con "- " per elenco puntato e "1. " per elenco numerato.</div>
            </div>
            <div class="ipd-modal-field">
                <label for="ipdBulkMailSignature">Firma</label>
                <textarea id="ipdBulkMailSignature" style="min-height:90px;">Segreteria didattica
ITT Buonarroti - Trento</textarea>
            </div>
            <div id="ipdBulkMailStatus" class="text-muted" aria-live="polite"></div>
            <div class="ipd-progress" id="ipdBulkMailProgress" hidden><span></span></div>
            <div id="ipdBulkMailError" class="text-danger" style="margin-top:8px;" hidden></div>
        </div>
        <div class="ipd-modal-actions">
            <button type="button" class="btn btn-default" id="ipdBulkMailCancelButton" onclick="ipdCloseBulkMailModal()">Annulla</button>
            <button type="button" class="btn btn-primary" id="ipdBulkMailSendButton" onclick="ipdStartBulkMail()">Invia a tutti</button>
        </div>
    </div>
</div>

<div id="ipdCustomMailModal" class="ipd-modal-backdrop" aria-hidden="true">
    <div class="ipd-modal-box" role="dialog" aria-modal="true" aria-labelledby="ipdCustomMailTitle">
        <div id="ipdCustomMailTitle" class="ipd-modal-head" style="background:#1d4ed8;">Scrivi ai genitori</div>
        <div class="ipd-modal-body">
            <p id="ipdCustomMailStudent" class="text-muted"></p>
            <div class="ipd-modal-field">
                <label for="ipdCustomMailSubject">Oggetto</label>
                <input type="text" id="ipdCustomMailSubject" value="Comunicazione pratica iscrizione">
            </div>
            <div class="ipd-modal-field">
                <label for="ipdCustomMailMessage">Messaggio</label>
                <div class="ipd-rich-tools">
                    <button type="button" class="btn btn-default btn-xs" onclick="ipdFormatTextarea('ipdCustomMailMessage', 'bold')"><strong>B</strong></button>
                    <button type="button" class="btn btn-default btn-xs" onclick="ipdFormatTextarea('ipdCustomMailMessage', 'ul')">Elenco puntato</button>
                    <button type="button" class="btn btn-default btn-xs" onclick="ipdFormatTextarea('ipdCustomMailMessage', 'ol')">Elenco numerato</button>
                </div>
                <textarea id="ipdCustomMailMessage" placeholder="Scrivi qui il testo da inviare ai genitori."></textarea>
                <div class="help-block text-muted">Puoi usare **testo** per il grassetto, righe che iniziano con "- " per elenco puntato e "1. " per elenco numerato.</div>
            </div>
            <div class="ipd-modal-field">
                <label for="ipdCustomMailSignature">Firma</label>
                <textarea id="ipdCustomMailSignature" style="min-height:90px;">Segreteria didattica
ITT Buonarroti - Trento</textarea>
                <div class="help-block text-muted">La firma viene inserita in fondo alla mail e puo' essere personalizzata per questa comunicazione.</div>
            </div>
            <div id="ipdCustomMailError" class="text-danger" style="margin-top:8px;" hidden></div>
        </div>
        <div class="ipd-modal-actions">
            <button type="button" class="btn btn-default" onclick="ipdCloseCustomMailModal()">Annulla</button>
            <button type="button" class="btn btn-primary" onclick="ipdSendCustomMail()">Invia mail</button>
        </div>
    </div>
</div>

<div id="ipdIntegrationModal" class="ipd-modal-backdrop" aria-hidden="true">
    <div class="ipd-modal-box" role="dialog" aria-modal="true" aria-labelledby="ipdIntegrationTitle">
        <div id="ipdIntegrationTitle" class="ipd-modal-head">Richiedi integrazione ai genitori</div>
        <div class="ipd-modal-body">
            <p class="text-muted">
                Scrivi cosa deve correggere la famiglia. La pratica verra' riaperta e il testo verra' inserito nella mail inviata ai genitori.
            </p>
            <textarea id="ipdIntegrationNote" placeholder="Esempio: Sono stati caricati gli stessi PDF per tutti i documenti. Cancellare gli allegati non corretti e caricare ogni documento nella voce corrispondente."></textarea>
            <div id="ipdIntegrationError" class="text-danger" style="margin-top:8px;" hidden></div>
        </div>
        <div class="ipd-modal-actions">
            <button type="button" class="btn btn-default" onclick="ipdCloseIntegrationModal()">Annulla</button>
            <button type="button" class="btn btn-warning" onclick="ipdSubmitIntegrationRequest()">Riapri pratica e invia mail</button>
        </div>
    </div>
</div>

<div id="ipdStatusNoteModal" class="ipd-modal-backdrop" aria-hidden="true">
    <div class="ipd-modal-box" role="dialog" aria-modal="true" aria-labelledby="ipdStatusNoteTitle">
        <div id="ipdStatusNoteTitle" class="ipd-modal-head" style="background:#334155;">Aggiorna stato pratica</div>
        <div class="ipd-modal-body">
            <p id="ipdStatusNoteHelp" class="text-muted"></p>
            <div class="ipd-modal-field">
                <label for="ipdStatusNoteText">Motivo / nota interna</label>
                <textarea id="ipdStatusNoteText" placeholder="Scrivi il motivo dell'aggiornamento dello stato."></textarea>
            </div>
            <div id="ipdStatusNoteError" class="text-danger" style="margin-top:8px;" hidden></div>
        </div>
        <div class="ipd-modal-actions">
            <button type="button" class="btn btn-default" onclick="ipdCloseStatusNoteModal()">Annulla</button>
            <button type="button" class="btn btn-primary" onclick="ipdSubmitStatusNote()">Salva stato</button>
        </div>
    </div>
</div>

<script>
let ipdIntegrationPraticaId = 0;
let ipdCustomMailPraticaId = 0;
let ipdStatusNotePraticaId = 0;
let ipdStatusNoteStato = '';
const ipdTipoIscrizione = <?php echo json_encode($tipoIscrizione); ?>;
let ipdBulkMailRunning = false;
let ipdBulkMailSent = 0;
let ipdBulkMailInitialRemaining = 0;

function ipdEscape(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (char) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
    });
}

function ipdFormatDateIt(value) {
    const text = String(value || '').trim();
    const match = text.match(/^(\d{4})-(\d{2})-(\d{2})/);
    return match ? (match[3] + '/' + match[2] + '/' + match[1]) : text;
}

function ipdFormatDateTimeIt(value) {
    const text = String(value || '').trim();
    const match = text.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/);
    if (!match) return text;
    return match[3] + '/' + match[2] + '/' + match[1] + (match[4] ? ' ' + match[4] + ':' + match[5] : '');
}

function ipdToggleDettagli(id, button) {
    const card = document.getElementById('pratica-' + id);
    if (!card) {
        return;
    }
    const open = card.classList.toggle('open');
    button.textContent = open ? 'Nascondi' : 'Dettagli';
}

function ipdNormalizeFilterText(value) {
    return String(value || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

function ipdApplyLiveFilter() {
    const input = document.getElementById('ipdLiveFilter');
    const counter = document.getElementById('ipdFilterCount');
    const query = ipdNormalizeFilterText(input ? input.value : '').trim();
    const terms = query.split(/\s+/).filter(Boolean);
    const cards = Array.from(document.querySelectorAll('.ipd-card[id^="pratica-"]'));
    let visible = 0;

    cards.forEach(card => {
        const haystack = ipdNormalizeFilterText(card.textContent || '');
        const match = terms.length === 0 || terms.every(term => haystack.includes(term));
        card.style.display = match ? '' : 'none';
        if (match) {
            visible++;
        }
    });

    if (counter) {
        counter.textContent = terms.length === 0 ? '' : visible + ' pratiche trovate';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('ipdLiveFilter');
    if (input) {
        input.addEventListener('input', ipdApplyLiveFilter);
    }
});

function ipdSetStato(id, stato) {
    if (stato === 'da_integrare') {
        ipdOpenIntegrationModal(id);
        return;
    }
    if (stato === 'annullata') {
        ipdOpenCambioScuolaModal(id);
        return;
    }
    if (stato === 'inviata') {
        ipdOpenStatusNoteModal(id, stato, 'Riporta la pratica allo stato inviata e indica il motivo.');
        return;
    }

    const labels = {
        verificata: 'segnare la pratica come verificata',
        da_integrare: 'segnare la pratica come da integrare',
        inviata: 'riportare la pratica allo stato inviata',
        annullata: 'segnare la pratica come cambio scuola/non prosegue'
    };
    if (!confirm('Vuoi ' + (labels[stato] || 'aggiornare lo stato della pratica') + '?')) {
        return;
    }
    ipdSendStato(id, stato, '');
}

function ipdOpenStatusNoteModal(id, stato, help) {
    ipdStatusNotePraticaId = id;
    ipdStatusNoteStato = stato;
    document.getElementById('ipdStatusNoteHelp').textContent = help || '';
    document.getElementById('ipdStatusNoteText').value = '';
    document.getElementById('ipdStatusNoteError').hidden = true;
    document.getElementById('ipdStatusNoteError').textContent = '';
    const modal = document.getElementById('ipdStatusNoteModal');
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
}

function ipdCloseStatusNoteModal() {
    const modal = document.getElementById('ipdStatusNoteModal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    ipdStatusNotePraticaId = 0;
    ipdStatusNoteStato = '';
}

function ipdSubmitStatusNote() {
    const note = document.getElementById('ipdStatusNoteText').value.trim();
    if (note.length < 3) {
        const error = document.getElementById('ipdStatusNoteError');
        error.textContent = 'Scrivi una breve nota per lo storico.';
        error.hidden = false;
        return;
    }
    const id = ipdStatusNotePraticaId;
    const stato = ipdStatusNoteStato;
    ipdCloseStatusNoteModal();
    ipdSendStato(id, stato, note);
}

function ipdOpenCambioScuolaModal(id) {
    const modal = document.getElementById('ipdCambioScuolaModal');
    const form = document.getElementById('ipdCambioScuolaForm');
    const error = document.getElementById('ipdCambioScuolaError');
    if (form) form.reset();
    if (error) {
        error.hidden = true;
        error.textContent = '';
    }
    document.getElementById('ipdCambioScuolaId').value = id;
    document.getElementById('ipdCambioScuolaStudent').textContent = 'Caricamento dati pratica...';
    ipdRenderCambioScuolaStorico(id, []);
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');

    fetch('iscrizioniPrimeCambioScuolaRead.php?id=' + encodeURIComponent(id), {credentials: 'same-origin'})
        .then(response => response.json())
        .then(data => {
            if (!data.ok) {
                throw new Error(data.message || 'Errore lettura cambio scuola.');
            }
            const pratica = data.pratica || {};
            const record = data.record || {};
            document.getElementById('ipdCambioScuolaStudent').textContent = 'Pratica di ' + (pratica.cognome || '') + ' ' + (pratica.nome || '') + ' - stato attuale: ' + (pratica.stato || '');
            document.getElementById('ipdCambioScuolaData').value = record.richiesta_data || '';
            document.getElementById('ipdCambioScuolaCanale').value = record.canale || 'mail';
            document.getElementById('ipdCambioScuolaScuolaDestinazione').value = record.scuola_destinazione || '';
            document.getElementById('ipdCambioScuolaIstitutoDestinazione').value = record.id_istituto_destinazione || '';
            document.getElementById('ipdCambioScuolaScuolaDestinazioneManuale').value = record.id_istituto_destinazione ? '' : (record.scuola_destinazione || '');
            document.getElementById('ipdCambioScuolaIndirizzoDestinazione').value = record.indirizzo_destinazione || '';
            ipdCambioScuolaUpdateSchoolName();
            const libera = document.getElementById('ipdCambioScuolaScuolaDestinazioneLibera');
            if (libera) {
                libera.style.display = (!record.id_istituto_destinazione && record.scuola_destinazione) ? 'block' : 'none';
                libera.textContent = (!record.id_istituto_destinazione && record.scuola_destinazione) ? 'Valore gia presente: ' + record.scuola_destinazione : '';
            }
            document.getElementById('ipdCambioScuolaColloquio').value = record.colloquio_stato || 'da_valutare';
            document.getElementById('ipdCambioScuolaNullaOsta').value = record.nulla_osta_stato || 'da_richiedere';
            document.getElementById('ipdCambioScuolaDocumenti').value = record.documenti_stato || 'da_verificare';
            document.getElementById('ipdCambioScuolaPraticaStato').value = record.pratica_stato || 'aperta';
            document.getElementById('ipdCambioScuolaNote').value = '';
            ipdRenderCambioScuolaStorico(id, data.eventi || []);
        })
        .catch(error => {
            const box = document.getElementById('ipdCambioScuolaError');
            if (box) {
                box.textContent = error.message;
                box.hidden = false;
            }
        });
}

function ipdCambioScuolaLabel(value) {
    const labels = {
        mail: 'Mail',
        telefono: 'Telefono',
        presenza: 'Di persona',
        altro: 'Altro',
        da_valutare: 'Da valutare',
        da_fare: 'Da fare',
        fatto: 'Fatto',
        non_necessario: 'Non necessario',
        da_richiedere: 'Da richiedere',
        richiesto: 'Richiesto',
        ricevuto: 'Ricevuto/in lavorazione',
        evaso_inviato: 'Evaso / inviato',
        da_verificare: 'Da verificare',
        manca_qualcosa: 'Manca qualcosa',
        completi: 'Completi',
        aperta: 'Aperta',
        in_attesa: 'In attesa',
        completata: 'Completata'
    };
    return labels[value] || value || '-';
}

function ipdCambioScuolaUpdateSchoolName() {
    const select = document.getElementById('ipdCambioScuolaIstitutoDestinazione');
    const hidden = document.getElementById('ipdCambioScuolaScuolaDestinazione');
    const manual = document.getElementById('ipdCambioScuolaScuolaDestinazioneManuale');
    if (!select || !hidden) return;
    const option = select.options[select.selectedIndex];
    if (select.value && option) {
        hidden.value = option.textContent || '';
        if (manual) {
            manual.value = '';
        }
    } else if (manual) {
        hidden.value = manual.value || '';
    }
}

document.getElementById('ipdCambioScuolaIstitutoDestinazione').addEventListener('change', function () {
    ipdCambioScuolaUpdateSchoolName();
    const libera = document.getElementById('ipdCambioScuolaScuolaDestinazioneLibera');
    if (libera) {
        libera.style.display = 'none';
        libera.textContent = '';
    }
});
document.getElementById('ipdCambioScuolaScuolaDestinazioneManuale').addEventListener('input', function () {
    if (this.value.trim() !== '') {
        document.getElementById('ipdCambioScuolaIstitutoDestinazione').value = '';
    }
    ipdCambioScuolaUpdateSchoolName();
});

function ipdRenderCambioScuolaStorico(praticaId, eventi) {
    const box = document.getElementById('ipdCambioScuolaStorico');
    if (!box) return;
    if (!eventi || !eventi.length) {
        box.innerHTML = '<span class="text-muted">Nessun aggiornamento registrato.</span>';
        return;
    }
    box.innerHTML = eventi.map((evento, index) => {
        const allegato = evento.allegato_path
            ? '<a class="btn btn-xs btn-primary" target="_blank" rel="noopener" href="iscrizioniPrimeCambioScuolaAllegato.php?id=' + encodeURIComponent(praticaId) + '&evento_id=' + encodeURIComponent(evento.id) + '"><span class="glyphicon glyphicon-file"></span> Apri PDF</a> <span class="text-muted">' + ipdEscape(evento.allegato_original_name || '') + '</span>'
            : '<span class="text-muted">Nessun PDF allegato a questo aggiornamento</span>';
        const undo = index === 0 && Number(evento.id || 0) > 0
            ? '<button type="button" class="btn btn-xs btn-danger pull-right" onclick="ipdUndoCambioScuolaLast(' + Number(praticaId) + ')"><span class="glyphicon glyphicon-repeat"></span> Annulla ultimo aggiornamento</button>'
            : '';
        return '<div class="ipd-cambio-event">' +
            '<div class="ipd-cambio-event-head">' +
                '<span>' + ipdEscape(ipdFormatDateTimeIt(evento.created_at || '')) + '</span>' +
                '<span>' + ipdEscape(evento.created_by || '') + '</span>' +
            '</div>' +
            '<div class="ipd-cambio-event-meta">' +
                'Richiesta: ' + ipdEscape(evento.richiesta_data ? ipdFormatDateIt(evento.richiesta_data) : '-') +
                ' &middot; Canale: ' + ipdEscape(ipdCambioScuolaLabel(evento.canale)) +
                ' &middot; Destinazione: ' + ipdEscape(evento.scuola_destinazione || '-') +
                (evento.indirizzo_destinazione ? ' &middot; Indirizzo: ' + ipdEscape(evento.indirizzo_destinazione) : '') +
                ' &middot; Colloquio: ' + ipdEscape(ipdCambioScuolaLabel(evento.colloquio_stato)) +
                ' &middot; Nulla osta: ' + ipdEscape(ipdCambioScuolaLabel(evento.nulla_osta_stato)) +
                ' &middot; Documenti: ' + ipdEscape(ipdCambioScuolaLabel(evento.documenti_stato)) +
                ' &middot; Stato: ' + ipdEscape(ipdCambioScuolaLabel(evento.pratica_stato)) +
            '</div>' +
            (evento.note ? '<div class="ipd-cambio-event-note">' + ipdEscape(evento.note) + '</div>' : '') +
            '<div style="margin-top:8px;">' + allegato + undo + '<div style="clear:both;"></div></div>' +
        '</div>';
    }).join('');
}

function ipdUndoCambioScuolaLast(praticaId) {
    if (!confirm("Vuoi annullare l'ultimo aggiornamento del cambio scuola? L'eventuale PDF collegato a quell'aggiornamento verra cancellato.")) {
        return;
    }
    const error = document.getElementById('ipdCambioScuolaError');
    const data = new FormData();
    data.append('id', praticaId);
    if (error) {
        error.hidden = true;
        error.textContent = '';
    }
    fetch('iscrizioniPrimeCambioScuolaUndoLast.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    })
    .then(response => response.json().then(result => ({ok: response.ok, result})))
    .then(payload => {
        if (!payload.ok || !payload.result.ok) {
            throw new Error(payload.result.message || 'Annullamento non riuscito.');
        }
        ipdOpenCambioScuolaModal(praticaId);
    })
    .catch(err => {
        if (error) {
            error.textContent = err.message;
            error.hidden = false;
        } else {
            alert(err.message);
        }
    });
}

function ipdCloseCambioScuolaModal() {
    const modal = document.getElementById('ipdCambioScuolaModal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
}

document.getElementById('ipdCambioScuolaForm').addEventListener('submit', function (event) {
    event.preventDefault();
    const error = document.getElementById('ipdCambioScuolaError');
    const button = this.querySelector('button[type="submit"]');
    const data = new FormData(this);
    ipdCambioScuolaUpdateSchoolName();
    data.set('scuola_destinazione', document.getElementById('ipdCambioScuolaScuolaDestinazione').value || '');
    if (button) {
        button.disabled = true;
        button.textContent = 'Salvataggio...';
    }
    if (error) {
        error.hidden = true;
        error.textContent = '';
    }

    fetch('iscrizioniPrimeCambioScuolaSave.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    })
    .then(response => response.json().then(result => ({ok: response.ok, result})))
    .then(payload => {
        if (!payload.ok || !payload.result.ok) {
            throw new Error(payload.result.message || 'Salvataggio non riuscito.');
        }
        window.location.href = '?tipo_iscrizione=' + encodeURIComponent(ipdTipoIscrizione) + '&stato=annullata';
    })
    .catch(err => {
        if (error) {
            error.textContent = err.message;
            error.hidden = false;
        } else {
            alert(err.message);
        }
    })
    .finally(() => {
        if (button) {
            button.disabled = false;
            button.textContent = 'Salva cambio scuola';
        }
    });
});

function ipdOpenCustomMailModal(id, studentName) {
    ipdCustomMailPraticaId = id;
    const modal = document.getElementById('ipdCustomMailModal');
    const student = document.getElementById('ipdCustomMailStudent');
    const subject = document.getElementById('ipdCustomMailSubject');
    const message = document.getElementById('ipdCustomMailMessage');
    const error = document.getElementById('ipdCustomMailError');
    if (student) {
        student.textContent = studentName ? 'Pratica di ' + studentName : 'Pratica selezionata';
    }
    if (subject && subject.value.trim() === '') {
        subject.value = 'Comunicazione pratica iscrizione';
    }
    if (message) {
        message.value = '';
    }
    if (error) {
        error.hidden = true;
        error.textContent = '';
    }
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    setTimeout(() => message && message.focus(), 50);
}

function ipdCloseCustomMailModal() {
    const modal = document.getElementById('ipdCustomMailModal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    ipdCustomMailPraticaId = 0;
}

function ipdOpenBulkMailModal() {
    const modal = document.getElementById('ipdBulkMailModal');
    const message = document.getElementById('ipdBulkMailMessage');
    const error = document.getElementById('ipdBulkMailError');
    const status = document.getElementById('ipdBulkMailStatus');
    const progress = document.getElementById('ipdBulkMailProgress');
    const cancelButton = document.getElementById('ipdBulkMailCancelButton');
    const sendButton = document.getElementById('ipdBulkMailSendButton');
    ipdBulkMailRunning = false;
    ipdBulkMailSent = 0;
    ipdBulkMailInitialRemaining = 0;
    if (message) message.value = '';
    if (error) {
        error.hidden = true;
        error.textContent = '';
    }
    if (status) status.textContent = '';
    if (progress) {
        progress.hidden = true;
        progress.querySelector('span').style.width = '0';
    }
    if (cancelButton) {
        cancelButton.textContent = 'Annulla';
        cancelButton.className = 'btn btn-default';
    }
    if (sendButton) {
        sendButton.disabled = false;
        sendButton.textContent = 'Invia a tutti';
        sendButton.className = 'btn btn-primary';
        sendButton.onclick = ipdStartBulkMail;
    }
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    setTimeout(() => message && message.focus(), 50);
}

function ipdCloseBulkMailModal() {
    if (ipdBulkMailRunning) {
        return;
    }
    const modal = document.getElementById('ipdBulkMailModal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
}

function ipdBulkMailFormData(dryRun) {
    const data = new FormData();
    data.append('tipo_iscrizione', ipdTipoIscrizione);
    data.append('subject', (document.getElementById('ipdBulkMailSubject')?.value || '').trim());
    data.append('message', (document.getElementById('ipdBulkMailMessage')?.value || '').trim());
    data.append('signature', (document.getElementById('ipdBulkMailSignature')?.value || '').trim());
    data.append('audience', (document.getElementById('ipdBulkMailAudience')?.value || 'esterni'));
    if (dryRun) {
        data.append('dry_run', '1');
    }
    return data;
}

function ipdFormatTextarea(id, mode) {
    const field = document.getElementById(id);
    if (!field) {
        return;
    }
    const start = field.selectionStart || 0;
    const end = field.selectionEnd || 0;
    const selected = field.value.substring(start, end);
    let replacement = selected;

    if (mode === 'bold') {
        replacement = selected ? '**' + selected + '**' : '**testo in grassetto**';
    } else if (mode === 'ul') {
        const source = selected || 'prima voce\nseconda voce';
        replacement = source.split(/\r?\n/).map(line => {
            line = line.replace(/^\s*[-*]\s+/, '').trim();
            return line ? '- ' + line : '';
        }).join('\n');
    } else if (mode === 'ol') {
        const source = selected || 'prima voce\nseconda voce';
        replacement = source.split(/\r?\n/).map((line, index) => {
            line = line.replace(/^\s*\d+[.)]\s+/, '').trim();
            return line ? (index + 1) + '. ' + line : '';
        }).join('\n');
    }

    field.value = field.value.substring(0, start) + replacement + field.value.substring(end);
    field.focus();
    field.selectionStart = start;
    field.selectionEnd = start + replacement.length;
}

function ipdSetBulkMailProgress(remaining) {
    const status = document.getElementById('ipdBulkMailStatus');
    const progress = document.getElementById('ipdBulkMailProgress');
    if (!progress) return;
    progress.hidden = false;
    const total = Math.max(ipdBulkMailInitialRemaining, ipdBulkMailSent + remaining, 1);
    const percent = Math.max(0, Math.min(100, Math.round((ipdBulkMailSent / total) * 100)));
    progress.querySelector('span').style.width = percent + '%';
    if (status) {
        status.textContent = 'Invio in corso: ' + ipdBulkMailSent + ' inviati, ' + remaining + ' restanti.';
    }
}

function ipdBulkMailRequest(dryRun) {
    return fetch('iscrizioniPrimeMailTutti.php', {
        method: 'POST',
        body: ipdBulkMailFormData(dryRun),
        credentials: 'same-origin'
    }).then(response => response.json().then(result => ({ok: response.ok, result})));
}

async function ipdStartBulkMail() {
    const error = document.getElementById('ipdBulkMailError');
    const button = document.getElementById('ipdBulkMailSendButton');
    const cancelButton = document.getElementById('ipdBulkMailCancelButton');
    const subject = (document.getElementById('ipdBulkMailSubject')?.value || '').trim();
    const message = (document.getElementById('ipdBulkMailMessage')?.value || '').trim();
    let completed = false;
    if (subject === '' || message.length < 4) {
        if (error) {
            error.textContent = 'Inserire oggetto e testo della comunicazione.';
            error.hidden = false;
        }
        return;
    }
    const audienceLabel = document.getElementById('ipdBulkMailAudience')?.selectedOptions?.[0]?.textContent || 'destinatari selezionati';
    if (!confirm('Inviare questa comunicazione a: ' + audienceLabel + '?')) {
        return;
    }

    ipdBulkMailRunning = true;
    if (button) {
        button.disabled = true;
        button.textContent = 'Invio in corso...';
    }
    if (cancelButton) {
        cancelButton.disabled = true;
        cancelButton.textContent = 'Attendere...';
    }
    if (error) {
        error.hidden = true;
        error.textContent = '';
    }

    try {
        const preview = await ipdBulkMailRequest(true);
        if (!preview.ok || !preview.result.ok) {
            throw new Error(preview.result.message || 'Impossibile preparare l\'invio.');
        }
        ipdBulkMailInitialRemaining = Number(preview.result.remaining || preview.result.sent || 0);
        ipdBulkMailSent = 0;
        ipdSetBulkMailProgress(ipdBulkMailInitialRemaining);

        let safety = 0;
        while (safety < 200) {
            safety++;
            const payload = await ipdBulkMailRequest(false);
            if (!payload.ok || !payload.result.ok) {
                throw new Error(payload.result.message || 'Invio interrotto.');
            }
            ipdBulkMailSent += Number(payload.result.sent || 0);
            ipdSetBulkMailProgress(Number(payload.result.remaining || 0));
            if (payload.result.last_batch || Number(payload.result.remaining || 0) <= 0 || Number(payload.result.sent || 0) <= 0) {
                document.getElementById('ipdBulkMailStatus').textContent = payload.result.message || 'Invio completato.';
                completed = true;
                break;
            }
        }
    } catch (err) {
        if (error) {
            error.textContent = err.message;
            error.hidden = false;
        }
    } finally {
        ipdBulkMailRunning = false;
        if (cancelButton) {
            cancelButton.disabled = false;
            cancelButton.style.display = completed ? 'none' : '';
            cancelButton.textContent = 'Annulla';
            cancelButton.className = 'btn btn-default';
        }
        if (button) {
            if (completed) {
                button.disabled = false;
                button.textContent = 'Chiudi';
                button.className = 'btn btn-success';
                button.onclick = ipdCloseBulkMailModal;
            } else {
                button.disabled = false;
                button.textContent = 'Riprova invio';
                button.className = 'btn btn-warning';
                button.onclick = ipdStartBulkMail;
            }
        }
    }
}

function ipdSendCustomMail() {
    const subject = document.getElementById('ipdCustomMailSubject');
    const message = document.getElementById('ipdCustomMailMessage');
    const signature = document.getElementById('ipdCustomMailSignature');
    const error = document.getElementById('ipdCustomMailError');
    const subjectValue = (subject && subject.value ? subject.value : '').trim();
    const messageValue = (message && message.value ? message.value : '').trim();
    const signatureValue = (signature && signature.value ? signature.value : '').trim();

    if (subjectValue === '' || messageValue.length < 4) {
        if (error) {
            error.textContent = 'Inserire oggetto e testo della comunicazione.';
            error.hidden = false;
        }
        return;
    }
    if (!confirm('Inviare questa comunicazione ai genitori collegati alla pratica?')) {
        return;
    }

    const data = new FormData();
    data.append('id', ipdCustomMailPraticaId);
    data.append('subject', subjectValue);
    data.append('message', messageValue);
    data.append('signature', signatureValue);

    fetch('iscrizioniPrimeMailPratica.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    })
    .then(response => response.json().then(result => ({ok: response.ok, result})))
    .then(payload => {
        if (!payload.ok || !payload.result.ok) {
            throw new Error(payload.result.message || 'Invio non riuscito.');
        }
        alert(payload.result.message || 'Comunicazione inviata.');
        ipdCloseCustomMailModal();
        window.location.reload();
    })
    .catch(err => {
        if (error) {
            error.textContent = err.message;
            error.hidden = false;
        } else {
            alert(err.message);
        }
    });
}

function ipdOpenIntegrationModal(id) {
    ipdIntegrationPraticaId = id;
    const modal = document.getElementById('ipdIntegrationModal');
    const note = document.getElementById('ipdIntegrationNote');
    const error = document.getElementById('ipdIntegrationError');
    if (note) {
        note.value = '';
    }
    if (error) {
        error.hidden = true;
        error.textContent = '';
    }
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    setTimeout(() => note && note.focus(), 50);
}

function ipdCloseIntegrationModal() {
    const modal = document.getElementById('ipdIntegrationModal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    ipdIntegrationPraticaId = 0;
}

function ipdSubmitIntegrationRequest() {
    const note = document.getElementById('ipdIntegrationNote');
    const error = document.getElementById('ipdIntegrationError');
    const value = (note && note.value ? note.value : '').trim();
    if (value.length < 8) {
        if (error) {
            error.textContent = 'Scrivi una nota piu dettagliata per il genitore.';
            error.hidden = false;
        }
        return;
    }
    ipdSendStato(ipdIntegrationPraticaId, 'da_integrare', value);
}

function ipdSendStato(id, stato, note) {
    const data = new FormData();
    data.append('id', id);
    data.append('stato', stato);
    data.append('note', note || '');

    fetch('iscrizioniPrimeStato.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(result => {
        if (!result.ok) {
            throw new Error(result.message || 'Aggiornamento non riuscito.');
        }
        if (result.warning) {
            alert(result.message);
        }
        window.location.reload();
    })
    .catch(error => alert(error.message));
}

function ipdUploadSegreteriaDocumento(event, praticaId, tipo) {
    event.preventDefault();
    const form = event.target;
    const button = form.querySelector('button[type="submit"]');
    const data = new FormData(form);
    data.append('pratica_id', praticaId);
    data.append('tipo', tipo);

    if (button) {
        button.disabled = true;
        button.textContent = 'Caricamento...';
    }

    fetch('iscrizioniPrimeSegreteriaDocumentoUpload.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    })
    .then(response => response.json().then(data => ({ok: response.ok, data})))
    .then(result => {
        if (!result.ok || !result.data.ok) {
            throw new Error(result.data.message || 'Errore caricamento PDF');
        }
        alert(result.data.message || 'Documento caricato.');
        window.location.reload();
    })
    .catch(error => {
        alert(error.message);
        if (button) {
            button.disabled = false;
            button.innerHTML = '<span class="glyphicon glyphicon-upload"></span> Carica PDF';
        }
    });

    return false;
}
</script>

</body>
</html>
