<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once 'geometriCatalogoDefaults.php';

ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

$studente_id = intval($_GET['studente_id'] ?? 0);
if ($studente_id <= 0) {
    http_response_code(400);
    echo 'Studente non valido';
    exit;
}

if (!class_exists('TCPDF')) {
    $tcpdf = __DIR__ . '/../common/vendor/tecnickcom/tcpdf/tcpdf.php';
    if (file_exists($tcpdf)) require_once $tcpdf;
}
if (!class_exists('TCPDF')) {
    http_response_code(500);
    echo 'Errore: libreria TCPDF non trovata';
    exit;
}
if (!class_exists('setasign\\Fpdi\\Tcpdf\\Fpdi')) {
    $fpdiAutoload = __DIR__ . '/../common/vendor/setasign/fpdi/src/autoload.php';
    if (file_exists($fpdiAutoload)) require_once $fpdiAutoload;
}
if (!class_exists('setasign\\Fpdi\\Tcpdf\\Fpdi')) {
    http_response_code(500);
    echo 'Errore: libreria FPDI non trovata';
    exit;
}

function gl_h($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function gl_date($value)
{
    if (!$value) return '';
    try {
        return (new DateTime((string)$value))->format('d/m/Y');
    } catch (Exception $e) {
        return (string)$value;
    }
}

function gl_template_path()
{
    $paths = [
        __DIR__ . '/../img/geometri_libretto_template_7p.pdf',
        __DIR__ . '/../img/geometri_libretto_template_publisher_clean.pdf',
        __DIR__ . '/../img/geometri_libretto_template_publisher.pdf',
        __DIR__ . '/../img/geometri_libretto_template.pdf',
        __DIR__ . '/../img/opuscolo.pdf',
    ];
    foreach ($paths as $path) {
        if (is_file($path)) return $path;
    }
    return '';
}

function gl_template_start_page($path)
{
    $name = basename((string)$path);
    if ($name === 'geometri_libretto_template_7p.pdf') {
        return 1;
    }
    if ($name === 'geometri_libretto_template_publisher_clean.pdf' || $name === 'geometri_libretto_template_publisher.pdf') {
        return 330;
    }
    return 1;
}

function gl_add_template_page(\setasign\Fpdi\Tcpdf\Fpdi $pdf, $pageNo)
{
    static $templatePageCount = null;
    $path = gl_template_path();
    if ($path === '') {
        throw new Exception('Template PDF libretto non trovato.');
    }
    if ($templatePageCount === null) {
        $templatePageCount = $pdf->setSourceFile($path);
    }
    $pageNo = gl_template_start_page($path) + $pageNo - 1;
    if ($pageNo < 1 || $pageNo > $templatePageCount) {
        throw new Exception('Pagina template libretto non valida: ' . $pageNo);
    }

    $tpl = $pdf->importPage($pageNo);
    $size = $pdf->getTemplateSize($tpl);
    $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
    $pdf->AddPage($orientation, [$size['width'], $size['height']]);
    $pdf->useTemplate($tpl, 0, 0, $size['width'], $size['height'], true);
}

function gl_school_year_short($value)
{
    $value = trim((string)$value);
    if (preg_match('/(20)?(\d{2})\D+(20)?(\d{2})/', $value, $m)) {
        return $m[2] . '-' . $m[4];
    }
    return $value;
}

function gl_cover_data(\TCPDF $pdf, array $student, array $latestClass)
{
    $name = strtoupper(trim((string)($student['cognome'] ?? '') . ' ' . (string)($student['nome'] ?? '')));
    $classe = trim((string)($latestClass['classe'] ?? ''));
    $anno = gl_school_year_short($latestClass['anno'] ?? '');
    $classeAnno = trim($classe . '  ' . $anno);

    $pdf->SetTextColor(255, 255, 255);
    if ($classeAnno !== '') {
        $pdf->SetFont('dejavusans', '', 14);
        $pdf->SetXY(40, 35);
        $pdf->Cell(70, 7, $classeAnno, 0, 1, 'L');
    }
    if ($name !== '') {
        $pdf->SetFont('dejavusans', 'B', 22);
        $pdf->SetXY(8, 60);
        $pdf->MultiCell(132, 10, $name, 0, 'L', false, 1);
    }
}

function gl_clear_exam_slot(\TCPDF $pdf, array $slot)
{
    // Il template clean non contiene date fittizie: non serve coprire nulla.
}

function gl_exam_overlay(\TCPDF $pdf, array $slot, $date)
{
    gl_clear_exam_slot($pdf, $slot);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $checkDx = $slot['check_dx'] ?? 0;
    $checkDy = $slot['check_dy'] ?? 0;
    $dateDx = $slot['date_dx'] ?? 0;
    $dateDy = $slot['date_dy'] ?? 0;
    $pdf->SetXY($slot['check_x'] - 1.3 + $checkDx, $slot['check_y'] - 1.8 + $checkDy);
    $pdf->Cell(6, 5, 'X', 0, 0, 'C');
    $pdf->SetFont('helvetica', '', 8.6);
    $pdf->SetXY($slot['date_x'] - 5.6 + $dateDx, $slot['date_y'] - 2.8 + $dateDy);
    $pdf->Cell(32, 5, gl_date($date), 0, 0, 'C');
}

function gl_norm_exam_code($value)
{
    $s = strtoupper(trim((string)$value));
    $s = preg_replace('/[^A-Z0-9]+/', '', $s);
    if ($s === 'CAD2D' || $s === 'CAD2') return 'CAD2D';
    if ($s === 'CAD3D' || $s === 'CAD3') return 'CAD3D';
    if ($s === 'DOCFA4' || $s === 'DOCFA') return 'DOCFA4';
    return $s;
}

function gl_exam_map(array $exams)
{
    $map = [];
    foreach ($exams as $exam) {
        $keys = [
            gl_norm_exam_code($exam['codice'] ?? ''),
            gl_norm_exam_code($exam['titolo'] ?? ''),
        ];
        foreach ($keys as $key) {
            if ($key !== '' && !isset($map[$key])) {
                $map[$key] = $exam;
            }
        }
    }
    return $map;
}

function gl_apply_exam_overlays(\TCPDF $pdf, $x, array $examMap, array $slots)
{
    foreach ($slots as $code => $slot) {
        gl_clear_exam_slot($pdf, $slot);
        $key = gl_norm_exam_code($code);
        if (!isset($examMap[$key])) continue;
        gl_exam_overlay($pdf, $slot, $examMap[$key]['data_superamento'] ?? '');
    }
}

$student = dbGetFirst("SELECT * FROM studente WHERE id=" . dbI($studente_id) . " LIMIT 1");
if (!$student) {
    http_response_code(404);
    echo 'Studente non trovato';
    exit;
}

geometriEnsureDefaultExams();

$latestClass = dbGetFirst("
    SELECT c.classe, a.anno
    FROM studente_frequenta sf
    INNER JOIN classi c ON c.id = sf.id_classe
    INNER JOIN anno_scolastico a ON a.id = sf.id_anno_scolastico
    WHERE sf.id_studente = " . dbI($studente_id) . "
    ORDER BY sf.id_anno_scolastico DESC
    LIMIT 1
");

$passedRows = dbGetAll("
    SELECT
        e.id,
        e.codice,
        e.titolo,
        e.descrizione,
        e.anno_corso,
        MIN(s.data) AS data_superamento
    FROM geometri_esiti ge
    INNER JOIN geometri_sessioni s ON s.id = ge.id_sessione
    INNER JOIN geometri_esami e ON e.id = s.id_esame
    WHERE ge.id_studente = " . dbI($studente_id) . "
      AND ge.esito = 'superato'
    GROUP BY e.id, e.codice, e.titolo, e.descrizione, e.anno_corso, e.ordine
    ORDER BY e.anno_corso ASC, e.ordine ASC, e.titolo ASC
");

$byYear = [3 => [], 4 => [], 5 => []];
foreach ($passedRows ?: [] as $row) {
    $anno = intval($row['anno_corso']);
    if (!isset($byYear[$anno])) $byYear[$anno] = [];
    $byYear[$anno][] = $row;
}
$examMapByYear = [
    3 => gl_exam_map($byYear[3] ?? []),
    4 => gl_exam_map($byYear[4] ?? []),
    5 => gl_exam_map($byYear[5] ?? []),
];

$templatePath = gl_template_path();
if ($templatePath === '') {
    http_response_code(500);
    echo 'Template PDF libretto non trovato in img/geometri_libretto_template.pdf';
    exit;
}

$pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm', [148, 210], true, 'UTF-8', false);
$pdf->SetCreator('GestOre');
$pdf->SetAuthor('GestOre');
$pdf->SetTitle('Libretto formativo');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false, 0);

// 1: copertina
gl_add_template_page($pdf, 1);
gl_cover_data($pdf, $student, $latestClass ?: []);

// 2-4: pagine informative gia presenti nel template
gl_add_template_page($pdf, 2);
gl_add_template_page($pdf, 3);
gl_add_template_page($pdf, 4);

// 5: risultati classe 3
gl_add_template_page($pdf, 5);
gl_apply_exam_overlays($pdf, 0, $examMapByYear[3] ?? [], [
    'CAD2D' => ['check_x' => 37.5, 'check_y' => 74.2, 'check_dx' => -0.4, 'check_dy' => -0.6, 'date_x' => 91.5, 'date_y' => 74.0, 'fill' => [242, 230, 239]],
    'CAD3D' => ['check_x' => 36.5, 'check_y' => 134.8, 'check_dx' => -1.9, 'check_dy' => -0.3, 'date_x' => 91.0, 'date_y' => 135.2, 'fill' => [237, 215, 229]],
    'BIM' => ['check_x' => 34.4, 'check_y' => 192.3, 'check_dy' => -0.6, 'date_x' => 89.1, 'date_y' => 192.1, 'date_dx' => 0.4, 'fill' => [223, 198, 217]],
]);

// 6: risultati classe 4
gl_add_template_page($pdf, 6);
gl_apply_exam_overlays($pdf, 0, $examMapByYear[4] ?? [], [
    'CATASTO' => ['check_x' => 34.3, 'check_y' => 81.5, 'check_dy' => -0.3, 'date_x' => 91.3, 'date_y' => 81.7, 'fill' => [248, 249, 236]],
    'PREGEO' => ['check_x' => 34.3, 'check_y' => 158.1, 'check_dy' => -0.6, 'date_x' => 91.3, 'date_y' => 158.0, 'fill' => [248, 246, 206]],
]);

// 7: risultati classe 5
gl_add_template_page($pdf, 7);
gl_apply_exam_overlays($pdf, 148.5, $examMapByYear[5] ?? [], [
    'DOCFA4' => ['check_x' => 34.9, 'check_y' => 74.1, 'check_dx' => -0.4, 'check_dy' => -0.4, 'date_x' => 88.7, 'date_y' => 74.0, 'date_dy' => 0.4, 'fill' => [230, 235, 240]],
    'PLATAV' => ['check_x' => 35.2, 'check_y' => 142.0, 'check_dx' => -0.7, 'check_dy' => -0.4, 'date_x' => 89.9, 'date_y' => 141.7, 'date_dy' => 0.4, 'fill' => [225, 230, 236]],
]);

$safeName = preg_replace('/[^A-Za-z0-9_\\-]+/', '_', trim((string)$student['cognome'] . '_' . (string)$student['nome']));
while (ob_get_level()) ob_end_clean();
$pdf->Output('libretto_formativo_' . $safeName . '.pdf', 'I');
