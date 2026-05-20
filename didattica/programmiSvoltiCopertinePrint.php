<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/vendor/autoload.php';
require_once '../common/programmiSvoltiCopertineLib.php';
require_once 'programmiSvoltiCopertinePdfLib.php';

ruoloRichiesto('segreteria-didattica', 'dirigente');

if (!programmiSvoltiCopertineTableExists()) {
    http_response_code(500);
    echo 'Tabella copertine non configurata.';
    exit;
}

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    $rows = dbGetAll("SELECT * FROM programmi_svolti_copertine WHERE id=$id AND stato IN ('GENERATA', 'STAMPATA') LIMIT 1");
} else {
    $rows = dbGetAll("SELECT * FROM programmi_svolti_copertine WHERE stato='GENERATA' ORDER BY generated_at ASC, id ASC");
}

if (!$rows) {
    http_response_code(404);
    echo 'Nessuna copertina generata da stampare.';
    exit;
}

$pdf = copertinePdfCreateDocument();
$printedIds = [];

foreach ($rows as $row) {
    $programma = programmiSvoltiCopertinaLoadProgramma(intval($row['id_programma_svolto'] ?? 0));
    if (!$programma) {
        continue;
    }

    $annoFine = intval($row['fascicolo_anno'] ?? 0);
    if ($annoFine <= 0) {
        $annoFine = programmiSvoltiCopertineAnnoFine((string)($programma['anno_scolastico_label'] ?? ''));
    }

    $codice = trim((string)($row['fascicolo_codice'] ?? ''));
    if ($codice === '') {
        $next = programmiSvoltiCopertinaNextCode($annoFine);
        $codice = $next['codice'];
    }

    copertinePdfAddTemplatePage($pdf, $programma, $codice, $annoFine);
    $printedIds[] = intval($row['id']);
}

if (empty($printedIds)) {
    http_response_code(500);
    echo 'Nessuna copertina valida da stampare.';
    exit;
}

$set = "stato='STAMPATA', updated_at=NOW()";
if (programmiSvoltiCopertinaColumnExists('printed_by_user_id')) {
    $set .= ", printed_by_user_id=" . intval($__utente_id ?? 0);
}
if (programmiSvoltiCopertinaColumnExists('printed_at')) {
    $set .= ", printed_at=NOW()";
}

dbExec("UPDATE programmi_svolti_copertine SET $set WHERE id IN (" . implode(',', $printedIds) . ")");

$pdf->SetTitle($id > 0 ? 'Ristampa copertina verifiche' : 'Copertine verifiche generate');
$fileName = $id > 0 ? 'ristampa-copertina-verifiche.pdf' : 'copertine-verifiche-da-stampare.pdf';
if (method_exists($pdf, 'IncludeJS')) {
    $pdf->IncludeJS('print(true);');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $fileName . '"');
$pdf->Output($fileName, 'I');
