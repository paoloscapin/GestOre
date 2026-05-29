<?php

function copertinePdfFitFont(TCPDF $pdf, string $text, float $maxWidth, int $startSize, int $minSize = 12): int
{
    $size = $startSize;
    $lines = preg_split('/\R/u', trim($text)) ?: [''];
    while ($size > $minSize) {
        $pdf->SetFont('times', 'B', $size);
        $ok = true;
        foreach ($lines as $line) {
            if ($pdf->GetStringWidth((string)$line) > $maxWidth) {
                $ok = false;
                break;
            }
        }
        if ($ok) {
            return $size;
        }
        $size--;
    }
    return $minSize;
}

function copertinePdfCell(
    TCPDF $pdf,
    float $x,
    float $y,
    float $w,
    float $h,
    string $text,
    int $fontSize,
    bool $fill = false,
    string $align = 'C',
    string $valign = 'M',
    int $minFontSize = 12
): void {
    $pdf->SetXY($x, $y);
    $pdf->SetFillColor($fill ? 204 : 255, $fill ? 204 : 255, $fill ? 204 : 255);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.25);
    $pdf->Rect($x, $y, $w, $h, $fill ? 'DF' : 'D');
    $fit = copertinePdfFitFont($pdf, $text, $w - 6, $fontSize, $minFontSize);
    $pdf->SetFont('times', 'B', $fit);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY($x + 2, $y + 1.5);
    $pdf->MultiCell($w - 4, $h - 3, $text, 0, $align, false, 1, '', '', true, 0, false, true, $h - 3, $valign);
}

function copertinePdfWrapText(TCPDF $pdf, string $text, float $maxWidth, int $fontSize, int $maxLines = 2): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    if ($text === '') {
        return '';
    }

    $pdf->SetFont('times', 'B', $fontSize);
    if ($pdf->GetStringWidth($text) <= $maxWidth) {
        return $text;
    }

    $words = preg_split('/\s+/u', $text) ?: [];
    $lines = [];
    $line = '';
    $consumed = 0;

    foreach ($words as $word) {
        $candidate = trim($line . ' ' . $word);
        if ($line !== '' && $pdf->GetStringWidth($candidate) > $maxWidth) {
            $lines[] = $line;
            $line = $word;
            $consumed++;
            if (count($lines) >= $maxLines - 1) {
                break;
            }
        } else {
            $line = $candidate;
            $consumed++;
        }
    }

    $remainingWords = array_slice($words, $consumed);
    if (count($lines) >= $maxLines - 1 && !empty($remainingWords)) {
        $line = trim($line . ' ' . implode(' ', $remainingWords));
    }

    $lines[] = $line;
    return implode("\n", array_slice($lines, 0, $maxLines));
}

function copertinePdfMateriaCell(TCPDF $pdf, float $x, float $y, float $w, float $h, string $materia): void
{
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.25);
    $pdf->Rect($x, $y, $w, $h, 'D');

    $labelSize = 25;
    $valueSize = 29;
    $materia = copertinePdfWrapText($pdf, $materia, $w - 14, $valueSize, 2);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('times', 'B', $labelSize);
    $pdf->SetXY($x + 4, $y + 5);
    $pdf->Cell($w - 8, 8, 'materia', 0, 1, 'C');

    $pdf->SetFont('times', 'B', $valueSize);
    $pdf->SetXY($x + 7, $y + 16);
    $pdf->MultiCell($w - 14, 13, $materia, 0, 'C', false, 1, '', '', true, 0, false, true, 33, 'M');
}

function copertinePdfDocenteCell(TCPDF $pdf, float $x, float $y, float $w, float $h, string $docente): void
{
    $docente = mb_strtoupper($docente, 'UTF-8');

    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.25);
    $pdf->Rect($x, $y, $w, $h, 'D');

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('times', 'B', 24);
    $pdf->SetXY($x + 4, $y + 5);
    $pdf->Cell($w - 8, 8, 'docente', 0, 1, 'C');

    $docente = copertinePdfWrapText($pdf, $docente, $w - 14, 38, 2);
    $docenteLines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $docente) ?: []), 'strlen'));
    if (empty($docenteLines)) {
        $docenteLines = [''];
    }

    $fit = copertinePdfFitFont($pdf, implode("\n", $docenteLines), $w - 14, 38, 20);
    $pdf->SetFont('times', 'B', $fit);

    $lineHeight = count($docenteLines) > 1 ? 10.5 : 13.0;
    $blockHeight = count($docenteLines) * $lineHeight;
    $startY = $y + 17 + max(0, (27 - $blockHeight) / 2);
    foreach ($docenteLines as $index => $line) {
        $pdf->SetXY($x + 7, $startY + ($index * $lineHeight));
        $pdf->Cell($w - 14, $lineHeight, $line, 0, 1, 'C');
    }
}

function copertinePdfPercorsoCell(TCPDF $pdf, float $x, float $y, float $w, float $h, string $percorso): void
{
    $pdf->SetFillColor(204, 204, 204);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.25);
    $pdf->Rect($x, $y, $w, $h, 'DF');

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('times', 'B', 28);
    $pdf->SetXY($x + 4, $y + 9);
    $pdf->Cell($w - 8, 9, 'PERCORSO', 0, 1, 'C');

    $fit = copertinePdfFitFont($pdf, $percorso, $w - 14, 32, 18);
    $pdf->SetFont('times', 'B', $fit);
    $pdf->SetXY($x + 7, $y + 22);
    $pdf->MultiCell($w - 14, 12, $percorso, 0, 'C', false, 1, '', '', true, 0, false, true, 18, 'M');
}

function copertinePdfScartoCell(TCPDF $pdf, float $x, float $y, float $w, float $h, string $scadenza): void
{
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.25);
    $pdf->Rect($x, $y, $w, $h, 'D');

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('times', 'B', 20);
    $pdf->SetXY($x + 4, $y + 4);
    $pdf->Cell($w - 8, 8, 'Da proporre per scarto il', 0, 1, 'C');

    $pdf->SetFont('times', 'B', 30);
    $pdf->SetXY($x + 4, $y + 14);
    $pdf->Cell($w - 8, 12, $scadenza, 0, 1, 'C');
}

function copertinePdfEstremiCell(TCPDF $pdf, float $x, float $y, float $w, float $h, string $annoLabel): void
{
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.25);
    $pdf->Rect($x, $y, $w, $h, 'D');

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('times', 'B', 23);
    $pdf->SetXY($x + 4, $y + 4);
    $pdf->Cell($w - 8, 8, 'estremi cronologici', 0, 1, 'C');

    $pdf->SetFont('times', 'B', 28);
    $pdf->SetXY($x + 4, $y + 14);
    $pdf->Cell($w - 8, 10, 'A.S. ' . $annoLabel, 0, 1, 'C');
}

function copertinePdfAddTemplatePage(TCPDF $pdf, array $programma, string $codice, int $annoFine): void
{
    $pdf->AddPage('L', 'A3');
    $pdf->SetAutoPageBreak(false, 0);
    $pdf->SetMargins(0, 0, 0);

    // Il modello Word usa un A3 orizzontale a due colonne: la copertina va nella colonna destra.
    $x = 217.5;
    $y = 18.0;
    $w = 197.0;
    $col1 = 56.0;
    $col2 = $w - $col1;

    $classe = trim((string)($programma['classe_label'] ?? ''));
    $materia = trim((string)($programma['materia_nome'] ?? ''));
    $docente = trim((string)($programma['docente_label'] ?? ''));
    $percorso = trim((string)($programma['percorso_label'] ?? ''));
    $annoLabel = trim((string)($programma['anno_scolastico_label'] ?? ''));
    $scadenza = '30/06/' . ($annoFine + 5);

    $fascicoloNumero = $codice;
    if (preg_match('/A(\d+)-\d{4}/i', $codice, $m)) {
        $fascicoloNumero = 'A' . $m[1] . '-' . $annoFine;
    }

    copertinePdfCell($pdf, $x, $y, $w, 19.0, 'ITT BUONARROTI - TRENTO', 27, false, 'C', 'M', 20);
    $y += 19.0;

    copertinePdfCell($pdf, $x, $y, $col1, 22.0, "FASCICOLO N.\n" . $fascicoloNumero, 19, false, 'C', 'M', 15);
    copertinePdfCell($pdf, $x + $col1, $y, $col2, 22.0, 'ELABORATI STUDENTI', 22, false);
    $y += 22.0;

    copertinePdfPercorsoCell($pdf, $x, $y, $w, 46.0, $percorso);
    $y += 46.0;

    copertinePdfMateriaCell($pdf, $x, $y, $w, 56.0, $materia);
    $y += 56.0;

    copertinePdfDocenteCell($pdf, $x, $y, $w, 51.0, $docente);
    $y += 51.0;

    copertinePdfCell($pdf, $x, $y, $col1, 34.0, "CLASSE\n" . $classe, 27, true, 'C', 'M', 13);
    copertinePdfEstremiCell($pdf, $x + $col1, $y, $col2, 34.0, $annoLabel);
    $y += 34.0;

    copertinePdfCell($pdf, $x, $y, $col1, 34.0, '5 ANNI', 30, true);
    copertinePdfScartoCell($pdf, $x + $col1, $y, $col2, 34.0, $scadenza);
}

function copertinePdfCreateDocument(): TCPDF
{
    $pdf = new TCPDF('L', 'mm', 'A3', true, 'UTF-8', false);
    $pdf->SetCreator('GestOre');
    $pdf->SetAuthor('GestOre');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetAutoPageBreak(false, 0);
    return $pdf;
}

function copertineBuildPdf(string $path, array $programma, string $codice, int $annoFine): void
{
    $pdf = copertinePdfCreateDocument();
    $pdf->SetTitle($codice);
    copertinePdfAddTemplatePage($pdf, $programma, $codice, $annoFine);
    $pdf->Output($path, 'F');
}
