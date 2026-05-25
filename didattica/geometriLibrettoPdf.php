<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('segreteria-didattica', 'dirigente');

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

function gl_split_lines(TCPDF $pdf, $text, $width)
{
    $text = trim(preg_replace('/\s+/', ' ', (string)$text));
    if ($text === '') return [''];
    $words = explode(' ', $text);
    $lines = [];
    $line = '';
    foreach ($words as $word) {
        $candidate = $line === '' ? $word : $line . ' ' . $word;
        if ($pdf->GetStringWidth($candidate) <= $width || $line === '') {
            $line = $candidate;
        } else {
            $lines[] = $line;
            $line = $word;
        }
    }
    if ($line !== '') $lines[] = $line;
    return $lines;
}

function gl_panel(TCPDF $pdf, $x, $title, $subtitle = '')
{
    $pdf->SetFillColor(41, 174, 186);
    $pdf->Rect($x, 0, 148.5, 28, 'F');
    $pdf->SetFillColor(146, 218, 224);
    $pdf->Rect($x, 28, 18, 182, 'F');

    $logo = realpath(__DIR__ . '/../img/logoB_google.png');
    if (!$logo) $logo = realpath(__DIR__ . '/../img/logo_Buonarroti.png');
    if ($logo) $pdf->Image($logo, $x + 126, 6, 17, 0, '', '', '', false, 300);

    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('dejavusans', 'B', 22);
    $pdf->SetXY($x + 28, 6);
    $pdf->Cell(92, 9, $title, 0, 1, 'L');
    if ($subtitle !== '') {
        $pdf->SetFont('dejavusans', 'B', 13);
        $pdf->SetXY($x + 28, 18);
        $pdf->Cell(92, 7, $subtitle, 0, 1, 'L');
    }

    $pdf->SetDrawColor(58, 178, 187);
    $pdf->Line($x, 28, $x + 148.5, 28);
}

function gl_footer(TCPDF $pdf, $x)
{
    $pdf->SetDrawColor(20, 155, 78);
    $pdf->SetLineWidth(0.5);
    $pdf->Line($x + 24, 184, $x + 132, 184);
    $pdf->SetTextColor(190, 44, 120);
    $pdf->SetFont('dejavusans', '', 6.5);
    $pdf->SetXY($x + 24, 188);
    $pdf->Cell(108, 4, 'Collegio Geometri e Geometri Laureati della Provincia di Trento', 0, 1, 'C');
    $pdf->SetTextColor(20, 30, 45);
    $pdf->SetXY($x + 24, 192);
    $pdf->Cell(108, 4, 'Via Brennero, 52 - Tel. 0461 826796 / 0461 420477', 0, 1, 'C');
    $pdf->SetTextColor(35, 74, 135);
    $pdf->SetXY($x + 24, 196);
    $pdf->Cell(108, 4, 'sede@collegio.geometri.tn.it  |  collegio.trento@geopec.it', 0, 1, 'C');
}

function gl_body_text(TCPDF $pdf, $x, $text)
{
    $pdf->SetTextColor(20, 20, 20);
    $pdf->SetFont('dejavusans', '', 9.2);
    $pdf->SetXY($x + 24, 42);
    $pdf->MultiCell(110, 5.6, $text, 0, 'J');
    gl_footer($pdf, $x);
}

function gl_exam_block(TCPDF $pdf, $x, $y, $w, $h, $side, array $exam, $fill)
{
    [$r, $g, $b] = $fill;
    $pdf->SetFillColor($r, $g, $b);
    $pdf->Rect($x + 24, $y, $w - 24, $h, 'F');
    $pdf->SetFillColor(120, 126, 136);
    $pdf->Rect($x + 8, $y, 16, $h, 'F');

    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('dejavusans', 'B', 16);
    $pdf->StartTransform();
    $pdf->Rotate(90, $x + 14, $y + ($h / 2));
    $pdf->Text($x + 14 - ($pdf->GetStringWidth($side) / 2), $y + ($h / 2), $side);
    $pdf->StopTransform();

    $desc = trim((string)($exam['descrizione'] ?? ''));
    if ($desc === '') $desc = trim((string)($exam['titolo'] ?? ''));

    $pdf->SetTextColor(18, 18, 18);
    $pdf->SetFont('dejavusans', '', 8.5);
    $pdf->SetXY($x + 30, $y + 5);
    $pdf->MultiCell($w - 38, 4.6, $desc, 0, 'J');

    $date = gl_date($exam['data_superamento'] ?? '');
    $yy = $y + $h - 16;
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->SetXY($x + 31, $yy);
    $pdf->Cell(8, 6, 'X', 1, 0, 'C');
    $pdf->Cell(33, 6, 'SUPERATO', 0, 0, 'L');
    $pdf->Cell(35, 6, $date, 1, 0, 'C');
    $pdf->Cell(18, 6, 'DATA', 0, 1, 'L');
    $pdf->SetX($x + 31);
    $pdf->Cell(8, 6, '', 1, 0, 'C');
    $pdf->Cell(33, 6, 'NON SUPERATO', 0, 0, 'L');
}

function gl_results_panel(TCPDF $pdf, $x, $classe, array $exams)
{
    gl_panel($pdf, $x, 'ITT Buonarroti', 'CLASSE ' . $classe . '^');
    $fills = [[242, 226, 236], [244, 226, 236], [236, 230, 240], [232, 236, 242]];
    $availableH = 158;
    $blockH = max(38, min(54, $availableH / max(1, count($exams))));
    $y = 38;
    if (count($exams) === 0) {
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->SetXY($x + 28, 62);
        $pdf->MultiCell(105, 6, 'Nessun esame superato registrato per la classe ' . $classe . '.', 0, 'L');
        return;
    }
    foreach (array_values($exams) as $i => $exam) {
        $side = trim((string)($exam['codice'] ?? ''));
        if ($side === '') $side = trim((string)($exam['titolo'] ?? 'ESAME'));
        gl_exam_block($pdf, $x, $y, 130, $blockH - 3, strtoupper($side), $exam, $fills[$i % count($fills)]);
        $y += $blockH;
    }
}

$student = dbGetFirst("SELECT * FROM studente WHERE id=" . dbI($studente_id) . " LIMIT 1");
if (!$student) {
    http_response_code(404);
    echo 'Studente non trovato';
    exit;
}

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

$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('GestOre');
$pdf->SetAuthor('GestOre');
$pdf->SetTitle('Libretto formativo');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false, 0);

$logo = realpath(__DIR__ . '/../img/logoB_google.png');
if (!$logo) $logo = realpath(__DIR__ . '/../img/logo_Buonarroti.png');

// 1: retro copertina vuoto + copertina
$pdf->AddPage();
$pdf->SetFillColor(255, 255, 255);
$pdf->Rect(0, 0, 148.5, 210, 'F');
$pdf->SetFillColor(41, 174, 186);
$pdf->Rect(148.5, 0, 148.5, 210, 'F');
$pdf->SetFillColor(74, 90, 96);
$pdf->Rect(186, 44, 86, 128, 'F');
$pdf->SetFillColor(68, 169, 181);
$pdf->Rect(148.5, 172, 148.5, 38, 'F');
if ($logo) $pdf->Image($logo, 276, 8, 17, 0, '', '', '', false, 300);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('dejavusans', '', 22);
$pdf->SetXY(187, 9);
$pdf->Cell(75, 12, 'libretto', 0, 1, 'L');
$pdf->SetFont('dejavusans', 'B', 17);
$pdf->SetXY(187, 23);
$pdf->Cell(75, 10, 'FORMATIVO', 0, 1, 'L');
$pdf->SetFont('dejavusans', '', 10);
$pdf->SetXY(187, 42);
$pdf->Cell(75, 8, trim((string)($latestClass['classe'] ?? '')) . '  ' . trim((string)($latestClass['anno'] ?? '')), 0, 1, 'L');
$pdf->SetFont('dejavusans', 'B', 17);
$pdf->SetXY(158, 62);
$pdf->MultiCell(128, 11, strtoupper(trim((string)$student['cognome'] . ' ' . (string)$student['nome'])), 0, 'L');
$pdf->SetFont('dejavusans', '', 9);
$pdf->SetXY(158, 83);
$pdf->Cell(128, 6, 'Codice fiscale: ' . strtoupper(trim((string)($student['codice_fiscale'] ?? ''))), 0, 1, 'L');
$pdf->SetFont('dejavusans', 'B', 11);
$pdf->SetXY(158, 116);
$pdf->MultiCell(42, 6, "ISTITUTO\nTECNICO\nBUONARROTI", 0, 'C');
$pdf->SetFont('dejavusans', '', 8.5);
$pdf->SetXY(158, 146);
$pdf->MultiCell(42, 5, "in collaborazione\ncon\nCollegio Geometri\ndi Trento", 0, 'C');
$pdf->SetFont('dejavusans', '', 9);
$pdf->SetXY(162, 176);
$pdf->MultiCell(55, 5, "Collegio Geometri e\nGeometri Laureati di Trento\nIl Presidente:", 0, 'C');
$pdf->SetFont('dejavusans', 'B', 16);
$pdf->SetXY(224, 181);
$pdf->Cell(45, 9, 'Buonarroti', 0, 1, 'L');
$pdf->SetFont('dejavusans', '', 9);
$pdf->SetXY(250, 196);
$pdf->Cell(40, 5, 'Il dirigente:', 0, 1, 'C');

$chi = "La parola \"geometra\" deriva dal greco \"geo\" e \"metros\" che indicava l'attivita di agrimensore, ossia \"misuratore della terra\". Il geometra e una figura professionale che opera prevalentemente nel settore edilizio, topografico, estimativo e della sicurezza del cantiere. In generale l'attivita del geometra puo riguardare:\n- progetto e direzione lavori di costruzioni civili e rurali;\n- contabilita lavori edili e stradali;\n- pratiche catastali e tavolari;\n- operazioni topografiche di rilevamento, misurazione e tracciamento;\n- successioni e divisioni ereditarie;\n- stima di aree e fondi rustici;\n- stima di aree urbane e di costruzioni civili;\n- coordinatore della sicurezza in fase progettuale ed esecutiva;\n- consulente tecnico d'Ufficio e di parte;\n- amministratore di condominio;\n- certificazione energetica.";
$come = "L'iscrizione all'albo e subordinata al superamento dell'esame di abilitazione per l'esercizio della professione al quale si accede attraverso diversi percorsi: tirocinio professionale, attivita tecnica subordinata, corsi di formazione tecnica superiore, percorsi didattico-formativi degli Istituti Tecnici Superiori, diplomi universitari triennali e lauree comprensive di tirocinio. Il percorso formativo valorizza competenze tecniche, operative e professionali coerenti con le attivita previste dall'Albo.";
$presentazione = "Il Collegio dei Geometri istituito con il Regio Decreto n. 274, dell'11 febbraio 1929 e un ente pubblico non economico che prevede l'iscrizione obbligatoria per esercitare la professione. Le sue principali funzioni sono:\n- la tutela della professione e la sua promozione nella societa;\n- il rafforzamento della cultura professionale;\n- la cura dell'osservanza delle leggi e delle disposizioni concernenti la professione;\n- la vigilanza sulla correttezza dell'esercizio professionale;\n- il presidio delle elezioni dei rappresentanti e la gestione dell'amministrazione;\n- il coordinamento di corsi per la preparazione dei praticanti all'esame di Stato e per l'aggiornamento continuo.";

// 2: chi e il geometra + come si diventa
$pdf->AddPage();
gl_panel($pdf, 0, 'chi e il', 'GEOMETRA');
gl_body_text($pdf, 0, $chi);
gl_panel($pdf, 148.5, 'come si diventa', 'GEOMETRA');
gl_body_text($pdf, 148.5, $come);

// 3: presentazione + classe 3
$pdf->AddPage();
gl_panel($pdf, 0, 'presentazione');
gl_body_text($pdf, 0, $presentazione);
gl_results_panel($pdf, 148.5, 3, $byYear[3] ?? []);

// 4: classe 4 + classe 5
$pdf->AddPage();
gl_results_panel($pdf, 0, 4, $byYear[4] ?? []);
gl_results_panel($pdf, 148.5, 5, $byYear[5] ?? []);

$safeName = preg_replace('/[^A-Za-z0-9_\\-]+/', '_', trim((string)$student['cognome'] . '_' . (string)$student['nome']));
while (ob_get_level()) ob_end_clean();
$pdf->Output('libretto_formativo_' . $safeName . '.pdf', 'I');
