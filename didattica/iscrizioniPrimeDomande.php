<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

iscrizioniPrimeEnsureSchema();
$tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($_GET['tipo_iscrizione'] ?? 'prime');
$pageTitle = $tipoIscrizione === 'terze' ? 'Domande iscrizioni terze' : 'Domande iscrizioni prime';
$returnPage = $tipoIscrizione === 'terze' ? 'iscrizioniTerze.php' : 'iscrizioniPrime.php';

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
    return 'label-default';
}

$filtroStato = trim((string)($_GET['stato'] ?? 'inviata'));
$allowedFilters = ['tutte', 'inviata', 'verificata', 'da_integrare'];
if (!in_array($filtroStato, $allowedFilters, true)) {
    $filtroStato = 'inviata';
}

$where = "tipo_iscrizione = " . dbQ($tipoIscrizione) . " AND stato IN ('inviata', 'verificata', 'da_integrare')";
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
        SUM(stato = 'da_integrare') AS da_integrare
    FROM iscrizioni_prime_pratiche
    WHERE tipo_iscrizione = " . dbQ($tipoIscrizione) . "
");

$labels = iscrizioniPrimeDocumentTypes($tipoIscrizione);

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
        .ipd-toggle { min-width: 92px; }
        @media (max-width: 900px) {
            .ipd-grid { grid-template-columns: 1fr; }
            .ipd-status-actions { justify-content: flex-start; }
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
                <div class="col-md-3"><strong>Inviate:</strong> <?php echo intval($stats['inviate'] ?? 0); ?></div>
                <div class="col-md-3"><strong>Verificate:</strong> <?php echo intval($stats['verificate'] ?? 0); ?></div>
                <div class="col-md-3"><strong>Da integrare:</strong> <?php echo intval($stats['da_integrare'] ?? 0); ?></div>
                <div class="col-md-3 text-right">
                    <a class="btn btn-default btn-sm" href="<?php echo ipd_h($returnPage); ?>">
                        <span class="glyphicon glyphicon-arrow-left"></span> Torna a import/invio link
                    </a>
                </div>
            </div>
            <hr>
            <div class="ipd-toolbar">
                <div class="btn-group">
                    <?php foreach (['inviata' => 'Inviate', 'verificata' => 'Verificate', 'da_integrare' => 'Da integrare', 'tutte' => 'Tutte'] as $key => $label) : ?>
                        <a class="btn btn-<?php echo $filtroStato === $key ? 'primary' : 'default'; ?>" href="?tipo_iscrizione=<?php echo urlencode($tipoIscrizione); ?>&stato=<?php echo urlencode($key); ?>"><?php echo ipd_h($label); ?></a>
                    <?php endforeach; ?>
                </div>
                <div class="ipd-status-help">
                    <strong>Uso degli stati:</strong> "Segna verificata" chiude il controllo della segreteria, "Richiedi integrazione" evidenzia una domanda da completare, "Rimetti in inviata" riporta una domanda allo stato ricevuto. Al momento questi pulsanti cambiano solo lo stato interno: non inviano automaticamente email ai genitori.
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
                        <button type="button" class="btn btn-success" title="La pratica e stata controllata ed e completa." onclick="ipdSetStato(<?php echo intval($pratica['id']); ?>, 'verificata')">Segna verificata</button>
                        <button type="button" class="btn btn-warning" title="La pratica richiede documenti o dati da integrare." onclick="ipdSetStato(<?php echo intval($pratica['id']); ?>, 'da_integrare')">Richiedi integrazione</button>
                        <button type="button" class="btn btn-default" title="Riporta la pratica allo stato ricevuto/inviata." onclick="ipdSetStato(<?php echo intval($pratica['id']); ?>, 'inviata')">Rimetti in inviata</button>
                    </div>
                </div>
            </div>
            <div class="ipd-card-body">
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
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
function ipdToggleDettagli(id, button) {
    const card = document.getElementById('pratica-' + id);
    if (!card) {
        return;
    }
    const open = card.classList.toggle('open');
    button.textContent = open ? 'Nascondi' : 'Dettagli';
}

function ipdSetStato(id, stato) {
    const labels = {
        verificata: 'segnare la pratica come verificata',
        da_integrare: 'segnare la pratica come da integrare',
        inviata: 'riportare la pratica allo stato inviata'
    };
    if (!confirm('Vuoi ' + (labels[stato] || 'aggiornare lo stato della pratica') + '?')) {
        return;
    }
    const data = new FormData();
    data.append('id', id);
    data.append('stato', stato);

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
