<?php
declare(strict_types=1);

const GS_BIN = 'gs';

function ensureOutputDir(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

function checkGhostscript(): void
{
    exec(GS_BIN . ' --version 2>&1', $out, $ret);
    if ($ret !== 0 || empty($out)) {
        throw new RuntimeException('Ghostscript non disponibile come "' . GS_BIN . '"');
    }
}

function extractPdfRangeWithGhostscript(string $inputPdf, int $firstPage, int $lastPage, string $outputPdf): bool
{
    $cmd = sprintf(
        '%s -q -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -dSAFER -dFirstPage=%d -dLastPage=%d -sOutputFile=%s %s 2>&1',
        escapeshellcmd(GS_BIN),
        $firstPage,
        $lastPage,
        escapeshellarg($outputPdf),
        escapeshellarg($inputPdf)
    );

    exec($cmd, $out, $ret);

    return $ret === 0 && is_file($outputPdf) && filesize($outputPdf) > 0;
}

function createPdfForPages(string $sourcePdf, array $pages, string $outputPdf, string $tempDir): void
{
    $pages = array_values(array_unique(array_map('intval', $pages)));
    sort($pages);

    if (!$pages) {
        throw new RuntimeException('Nessuna pagina da esportare');
    }

    $isConsecutive = true;
    for ($i = 1; $i < count($pages); $i++) {
        if ($pages[$i] !== $pages[$i - 1] + 1) {
            $isConsecutive = false;
            break;
        }
    }

    if ($isConsecutive) {
        if (!extractPdfRangeWithGhostscript($sourcePdf, $pages[0], $pages[count($pages) - 1], $outputPdf)) {
            throw new RuntimeException('Ghostscript non è riuscito a creare il PDF finale');
        }
        return;
    }

    ensureOutputDir($tempDir);
    $tempFiles = [];

    foreach ($pages as $i => $page) {
        $tmp = $tempDir . '/tmp_' . uniqid('', true) . '_' . $i . '.pdf';

        if (!extractPdfRangeWithGhostscript($sourcePdf, $page, $page, $tmp)) {
            throw new RuntimeException('Ghostscript non è riuscito a estrarre la pagina ' . $page);
        }

        $tempFiles[] = $tmp;
    }

    $cmd = escapeshellcmd(GS_BIN)
        . ' -q -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -dSAFER -sOutputFile='
        . escapeshellarg($outputPdf);

    foreach ($tempFiles as $tmp) {
        $cmd .= ' ' . escapeshellarg($tmp);
    }

    $cmd .= ' 2>&1';

    exec($cmd, $out, $ret);

    foreach ($tempFiles as $tmp) {
        if (is_file($tmp)) {
            @unlink($tmp);
        }
    }

    if ($ret !== 0 || !is_file($outputPdf) || filesize($outputPdf) <= 0) {
        throw new RuntimeException('Ghostscript non è riuscito a unire le pagine');
    }
}

function createPdfsPerEmail(string $sourcePdf, array $assignments, string $pdfOutDir, string $tempDir): array
{
    ensureOutputDir($pdfOutDir);

    $files = [];

    foreach ($assignments as $row) {
        $safeEmail = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $row['email']) ?: 'utente';
        $dest = $pdfOutDir . '/' . $safeEmail . '.pdf';

        createPdfForPages($sourcePdf, $row['pages'], $dest, $tempDir);

        $files[] = [
            'email' => $row['email'],
            'path' => $dest,
        ];
    }MAIL_TEST_OVERRIDE

    return $files;
}