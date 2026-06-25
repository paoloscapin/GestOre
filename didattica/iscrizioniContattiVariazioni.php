<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

iscrizioniPrimeEnsureSchema();

function icv_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$tipo = iscrizioniPrimeNormalizeTipoIscrizione($_GET['tipo_iscrizione'] ?? 'prime');
$stato = trim((string)($_GET['stato'] ?? 'da_lavorare'));
if (!in_array($stato, ['da_lavorare', 'lavorata', 'tutte'], true)) {
    $stato = 'da_lavorare';
}

$where = "v.tipo_iscrizione = " . dbQ($tipo);
if ($stato !== 'tutte') {
    $where .= " AND v.stato = " . dbQ($stato);
}

$stats = dbGetFirst("
    SELECT
        SUM(stato = 'da_lavorare' AND tipo_iscrizione = " . dbQ($tipo) . ") AS da_lavorare,
        SUM(stato = 'lavorata' AND tipo_iscrizione = " . dbQ($tipo) . ") AS lavorate
    FROM iscrizioni_contatti_variazioni
");

$rows = dbGetAll("
    SELECT
        v.*,
        p.cognome,
        p.nome,
        p.codice_fiscale,
        p.corso_studi,
        p.stato AS pratica_stato
    FROM iscrizioni_contatti_variazioni v
    INNER JOIN iscrizioni_prime_pratiche p ON p.id = v.pratica_id
    WHERE $where
    ORDER BY
        CASE WHEN v.stato = 'da_lavorare' THEN 0 ELSE 1 END,
        v.created_at DESC,
        p.cognome ASC,
        p.nome ASC
");

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <title>Variazioni contatti iscrizioni</title>
    <?php
    require_once '../common/header-common.php';
    require_once '../common/style.php';
    ?>
    <style>
        .icv-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 14px; }
        .icv-filter { width: min(620px, 100%); border: 1px solid #cbd5e1; border-radius: 6px; padding: 9px 11px; }
        .icv-old { color: #991b1b; text-decoration: line-through; overflow-wrap: anywhere; }
        .icv-new { color: #166534; font-weight: 800; overflow-wrap: anywhere; }
        .icv-muted { color: #64748b; }
        .icv-badge { display: inline-block; border-radius: 999px; padding: 3px 8px; font-size: 12px; font-weight: 800; }
        .icv-badge-open { background: #fef3c7; color: #92400e; }
        .icv-badge-done { background: #dcfce7; color: #166534; }
        .icv-counter { color: #64748b; }
    </style>
</head>
<body>
<?php require_once '../common/header-didattica.php'; ?>

<div class="container-fluid">
    <div class="panel panel-lightblue4">
        <div class="panel-heading">
            <span class="glyphicon glyphicon-transfer"></span>&ensp;Variazioni contatti iscrizioni
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-md-3"><strong>Da lavorare:</strong> <?php echo intval($stats['da_lavorare'] ?? 0); ?></div>
                <div class="col-md-3"><strong>Lavorate:</strong> <?php echo intval($stats['lavorate'] ?? 0); ?></div>
                <div class="col-md-6 text-right">
                    <a class="btn btn-default btn-sm" href="iscrizioniPrime.php">Prime</a>
                    <a class="btn btn-default btn-sm" href="iscrizioniTerze.php">Terze</a>
                </div>
            </div>
            <hr>
            <div class="icv-toolbar">
                <div class="btn-group">
                    <a class="btn btn-<?php echo $tipo === 'prime' ? 'primary' : 'default'; ?>" href="?tipo_iscrizione=prime&stato=<?php echo urlencode($stato); ?>">Prime</a>
                    <a class="btn btn-<?php echo $tipo === 'terze' ? 'primary' : 'default'; ?>" href="?tipo_iscrizione=terze&stato=<?php echo urlencode($stato); ?>">Terze</a>
                </div>
                <div class="btn-group">
                    <?php foreach (['da_lavorare' => 'Da lavorare', 'lavorata' => 'Lavorate', 'tutte' => 'Tutte'] as $key => $label) : ?>
                        <a class="btn btn-<?php echo $stato === $key ? 'warning' : 'default'; ?>" href="?tipo_iscrizione=<?php echo urlencode($tipo); ?>&stato=<?php echo urlencode($key); ?>"><?php echo icv_h($label); ?></a>
                    <?php endforeach; ?>
                </div>
                <input type="search" id="icvFilter" class="icv-filter" placeholder="Cerca studente, codice fiscale, campo, valore...">
                <span id="icvCounter" class="icv-counter"></span>
            </div>
            <div class="alert alert-info">
                Ogni riga rappresenta una modifica fatta dal genitore ai contatti durante la conferma iscrizione. Dopo aver aggiornato gli altri portali, premi <strong>Segna lavorata</strong>.
            </div>
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-body table-responsive">
            <table class="table table-striped table-condensed" id="icvTable">
                <thead>
                    <tr>
                        <th>Studente</th>
                        <th>Campo</th>
                        <th>Valore precedente</th>
                        <th>Nuovo valore</th>
                        <th>Stato</th>
                        <th>Data</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows) : ?>
                        <tr><td colspan="7" class="text-muted">Nessuna variazione nel filtro selezionato.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $row) :
                        $student = trim((string)(($row['cognome'] ?? '') . ' ' . ($row['nome'] ?? '')));
                        $isDone = (string)$row['stato'] === 'lavorata';
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo icv_h($student); ?></strong><br>
                                <span class="icv-muted"><?php echo icv_h($row['codice_fiscale'] ?? ''); ?> - <?php echo icv_h($row['corso_studi'] ?? ''); ?></span>
                            </td>
                            <td><?php echo icv_h($row['etichetta'] ?? $row['campo']); ?></td>
                            <td class="icv-old"><?php echo icv_h($row['valore_precedente'] ?? ''); ?></td>
                            <td class="icv-new"><?php echo icv_h($row['valore_nuovo'] ?? ''); ?></td>
                            <td>
                                <span class="icv-badge <?php echo $isDone ? 'icv-badge-done' : 'icv-badge-open'; ?>">
                                    <?php echo $isDone ? 'lavorata' : 'da lavorare'; ?>
                                </span>
                                <?php if ($isDone && !empty($row['processed_at'])) : ?>
                                    <br><small class="icv-muted"><?php echo icv_h(iscrizioniPrimeFormatDateIt($row['processed_at'])); ?> <?php echo icv_h($row['processed_by'] ?? ''); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo icv_h(iscrizioniPrimeFormatDateIt($row['created_at'] ?? '')); ?></td>
                            <td>
                                <?php if ($isDone) : ?>
                                    <button type="button" class="btn btn-xs btn-default" onclick="icvSetStato(<?php echo intval($row['id']); ?>, 'da_lavorare')">Riapri</button>
                                <?php else : ?>
                                    <button type="button" class="btn btn-xs btn-success" onclick="icvSetStato(<?php echo intval($row['id']); ?>, 'lavorata')">
                                        <span class="glyphicon glyphicon-ok"></span> Segna lavorata
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function icvNormalize(value) {
    return String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function icvApplyFilter() {
    const input = document.getElementById('icvFilter');
    const counter = document.getElementById('icvCounter');
    const terms = icvNormalize(input ? input.value : '').trim().split(/\s+/).filter(Boolean);
    const rows = Array.from(document.querySelectorAll('#icvTable tbody tr'));
    let visible = 0;
    rows.forEach(row => {
        const match = !terms.length || terms.every(term => icvNormalize(row.textContent).includes(term));
        row.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    if (counter) {
        counter.textContent = terms.length ? visible + ' righe trovate' : '';
    }
}

function icvSetStato(id, stato) {
    const data = new FormData();
    data.append('id', id);
    data.append('stato', stato);

    fetch('iscrizioniContattiVariazioneStato.php', {
        method: 'POST',
        body: data,
        credentials: 'same-origin'
    })
    .then(response => response.json().then(result => ({ok: response.ok, result})))
    .then(payload => {
        if (!payload.ok || !payload.result.ok) {
            throw new Error(payload.result.message || 'Aggiornamento non riuscito.');
        }
        window.location.reload();
    })
    .catch(error => alert(error.message));
}

document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('icvFilter');
    if (input) input.addEventListener('input', icvApplyFilter);
});
</script>
</body>
</html>
