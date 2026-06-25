<?php

require_once '../common/checkSession.php';
require_once '../common/programmiSvoltiCompletezzaLib.php';
ruoloRichiesto('admin', 'dirigente', 'segreteria-didattica');

$filters = [
    'anno_id' => intval($_GET['anni_id'] ?? 0),
    'classe_id' => intval($_GET['classi_id'] ?? 0),
    'materia_id' => intval($_GET['materia_id'] ?? 0),
    'docente_id' => intval($_GET['docenti_id'] ?? 0),
];
$format = strtolower(trim((string)($_GET['format'] ?? 'xls')));
$rows = programmiSvoltiCompletezzaRighe($filters);

function psm_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

ob_start();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <style>
        table { border-collapse: collapse; }
        th { background: #1f4e79; color: #fff; font-weight: bold; }
        th, td { border: 1px solid #9eacb8; padding: 6px; vertical-align: top; }
    </style>
</head>
<body>
<h2>Programmi svolti mancanti o non compilati</h2>
<p>Generato il <?php echo date('d/m/Y H:i'); ?>. Totale righe: <?php echo count($rows); ?></p>
<table>
    <thead>
    <tr>
        <th>Classe</th>
        <th>Docente</th>
        <th>Email docente</th>
        <th>Materia</th>
        <th>Stato</th>
        <th>Programmi presenti</th>
        <th>Moduli</th>
        <th>Moduli compilati</th>
        <th>Ultimo aggiornamento</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $row) : ?>
        <tr>
            <td><?php echo psm_h($row['classe']); ?></td>
            <td><?php echo psm_h($row['docente']); ?></td>
            <td><?php echo psm_h($row['email']); ?></td>
            <td><?php echo psm_h($row['materia']); ?></td>
            <td><?php echo psm_h($row['stato']); ?></td>
            <td><?php echo intval($row['programmi']); ?></td>
            <td><?php echo intval($row['moduli']); ?></td>
            <td><?php echo intval($row['moduli_compilati']); ?></td>
            <td><?php echo psm_h($row['ultimo_aggiornamento']); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>
<?php
$html = ob_get_clean();

if ($format === 'pdf') {
    require_once '../common/dompdf/vendor/autoload.php';
    $dompdf = new Dompdf\Dompdf(['isRemoteEnabled' => true]);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->render();
    $dompdf->stream('programmi_svolti_mancanti_' . date('Ymd_His') . '.pdf', ['Attachment' => true]);
    exit;
}

$filename = 'programmi_svolti_mancanti_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');
echo "\xEF\xBB\xBF";
echo $html;
