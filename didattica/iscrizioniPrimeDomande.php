<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

iscrizioniPrimeEnsureSchema();

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

$where = "stato IN ('inviata', 'verificata', 'da_integrare')";
if ($filtroStato !== 'tutte') {
    $where = "stato = " . dbQ($filtroStato);
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
");

$labels = iscrizioniPrimeDocumentTypes();

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <title>Domande iscrizioni prime</title>
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
            <span class="glyphicon glyphicon-inbox"></span>&ensp;Domande iscrizioni prime inviate
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-md-3"><strong>Inviate:</strong> <?php echo intval($stats['inviate'] ?? 0); ?></div>
                <div class="col-md-3"><strong>Verificate:</strong> <?php echo intval($stats['verificate'] ?? 0); ?></div>
                <div class="col-md-3"><strong>Da integrare:</strong> <?php echo intval($stats['da_integrare'] ?? 0); ?></div>
                <div class="col-md-3 text-right">
                    <a class="btn btn-default btn-sm" href="iscrizioniPrime.php">
                        <span class="glyphicon glyphicon-arrow-left"></span> Torna a import/invio link
                    </a>
                </div>
            </div>
            <hr>
            <div class="ipd-toolbar">
                <div class="btn-group">
                    <?php foreach (['inviata' => 'Inviate', 'verificata' => 'Verificate', 'da_integrare' => 'Da integrare', 'tutte' => 'Tutte'] as $key => $label) : ?>
                        <a class="btn btn-<?php echo $filtroStato === $key ? 'primary' : 'default'; ?>" href="?stato=<?php echo urlencode($key); ?>"><?php echo ipd_h($label); ?></a>
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
                </div>
                <div class="ipd-status-actions">
                    <span class="label <?php echo ipd_badge_class((string)$pratica['stato']); ?>"><?php echo ipd_h($pratica['stato']); ?></span>
                    <div class="btn-group btn-group-sm">
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
                                if (in_array($tipo, ['documento_identita_genitore_2', 'codice_fiscale_genitore_2'], true) && !hasSecondResponsibleForIscrizioniPrime($pratica, $confirmed)) {
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
                                        <?php else : ?>
                                            <span class="text-danger">Mancante</span>
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
</script>

</body>
</html>
