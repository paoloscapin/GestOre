<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';

ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

$tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($_GET['tipo_iscrizione'] ?? 'prime');
$format = strtolower(trim((string)($_GET['format'] ?? 'xls')));
if (!in_array($format, ['xls', 'pdf'], true)) {
    $format = 'xls';
}

function ire_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$report = iscrizioniPrimeSummary($tipoIscrizione);
$title = $tipoIscrizione === 'terze'
    ? 'Riepilogo iscrizioni future terze per indirizzo'
    : 'Riepilogo iscrizioni future prime';

ob_start();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #17202f; }
        h2 { margin: 0 0 8px; }
        h3 { margin: 18px 0 8px; }
        .meta { margin-bottom: 12px; color: #526173; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th { background: #1f4e79; color: #fff; font-weight: bold; }
        th, td { border: 1px solid #9eacb8; padding: 5px; vertical-align: top; }
        .number { text-align: right; }
        .muted { color: #526173; }
    </style>
</head>
<body>
<h2><?php echo ire_h($title); ?></h2>
<div class="meta">
    Generato il <?php echo date('d/m/Y H:i'); ?>.
    Studenti considerati: <?php echo count($report['rows'] ?? []); ?>.
    Per le terze sono inclusi anche i bocciati di seconda con cambio scuola o senza pratica attiva.
</div>

<?php if ($tipoIscrizione === 'prime'): ?>
    <?php $summary = $report['summary'] ?? []; ?>
    <table>
        <thead>
        <tr>
            <th>Totale iscritti</th>
            <th>Bocciati interni</th>
            <th>Arrivi esterni</th>
            <th>DSA</th>
            <th>104</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td class="number"><?php echo intval($summary['totale_iscritti'] ?? 0); ?></td>
            <td class="number"><?php echo intval($summary['bocciati_interni'] ?? 0); ?></td>
            <td class="number"><?php echo intval($summary['arrivi_esterni'] ?? 0); ?></td>
            <td class="number"><?php echo intval($summary['dsa'] ?? 0); ?></td>
            <td class="number"><?php echo intval($summary['legge_104'] ?? 0); ?></td>
        </tr>
        </tbody>
    </table>
<?php else: ?>
    <table>
        <thead>
        <tr>
            <th>Indirizzo</th>
            <th>Promossi dalla seconda reali</th>
            <th>Bocciati seconde</th>
            <th>Bocciati in terza</th>
            <th>Esterni in entrata</th>
            <th>DSA</th>
            <th>104</th>
            <th>Totale studenti</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach (($report['summary'] ?? []) as $row): ?>
            <tr>
                <td><?php echo ire_h($row['indirizzo'] ?? ''); ?></td>
                <td class="number"><?php echo intval($row['promossi_seconda_reali'] ?? 0); ?></td>
                <td class="number"><?php echo intval($row['bocciati_seconda'] ?? 0); ?></td>
                <td class="number"><?php echo intval($row['bocciati_terza'] ?? 0); ?></td>
                <td class="number"><?php echo intval($row['esterni_entrata'] ?? 0); ?></td>
                <td class="number"><?php echo intval($row['dsa'] ?? 0); ?></td>
                <td class="number"><?php echo intval($row['legge_104'] ?? 0); ?></td>
                <td class="number"><?php echo intval($row['totale'] ?? 0); ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($report['summary'])): ?>
            <tr><td colspan="8">Nessuno studente trovato.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
<?php endif; ?>

<h3>Dettaglio studenti considerati</h3>
<table>
    <thead>
    <tr>
        <th>Studente</th>
        <th>Codice fiscale</th>
        <th>Indirizzo / corso</th>
        <th>Tipo</th>
        <th>DSA</th>
        <th>104</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach (($report['rows'] ?? []) as $row): ?>
        <?php
        $name = trim((string)($row['cognome'] ?? '') . ' ' . (string)($row['nome'] ?? ''));
        $movementYear = intval($row['movimento_reiscrizione_anno_corso'] ?? 0);
        $type = intval($row['movimento_reiscrizione_id'] ?? 0) > 0
            ? 'Bocciato/reiscrizione' . ($movementYear > 0 ? ' classe ' . $movementYear : '')
            : (intval($row['studente_interno_effettivo'] ?? 0) === 1 ? 'Interno' : 'Esterno in entrata');
        ?>
        <tr>
            <td><?php echo ire_h(function_exists('mb_strtoupper') ? mb_strtoupper($name, 'UTF-8') : strtoupper($name)); ?></td>
            <td><?php echo ire_h($row['codice_fiscale'] ?? ''); ?></td>
            <td><?php echo ire_h(iscrizioniPrimeSummaryAddressLabel($row)); ?></td>
            <td><?php echo ire_h($type); ?></td>
            <td class="number"><?php echo !empty($row['dsa']) ? '1' : '0'; ?></td>
            <td class="number"><?php echo !empty($row['legge_104']) ? '1' : '0'; ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($report['rows'])): ?>
        <tr><td colspan="6">Nessuno studente trovato.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</body>
</html>
<?php
$html = ob_get_clean();
$basename = 'iscrizioni_' . $tipoIscrizione . '_riepilogo_' . date('Ymd_His');

if ($format === 'pdf') {
    require_once '../common/dompdf/vendor/autoload.php';
    $dompdf = new Dompdf\Dompdf(['isRemoteEnabled' => true]);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->render();
    $dompdf->stream($basename . '.pdf', ['Attachment' => true]);
    exit;
}

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $basename . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');
echo "\xEF\xBB\xBF";
echo $html;
