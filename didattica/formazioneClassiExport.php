<?php

require_once '../common/checkSession.php';
require_once '../common/formazioneClassiLib.php';
require_once __DIR__ . '/../common/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

ruoloRichiesto('admin', 'segreteria-didattica');

formazioneClassiEnsureTables();

$tipiFormazione = formazioneClassiTipi();
$scope = strtolower(trim((string)($_GET['scope'] ?? 'all')));
if (!in_array($scope, ['all', 'current'], true)) {
    $scope = 'all';
}

$sourceYearId = intval($_GET['anno_origine_id'] ?? 0);
if ($sourceYearId <= 0) {
    $sourceYearId = formazioneClassiCurrentYearId();
}
$targetYearId = intval($_GET['anno_target_id'] ?? 0);
if ($targetYearId <= 0) {
    $targetYearId = formazioneClassiDefaultTargetYear($sourceYearId);
}

$selectedTipo = trim((string)($_GET['tipo_formazione'] ?? ''));
if (!isset($tipiFormazione[$selectedTipo])) {
    $selectedTipo = 'prime';
}
$selectedAddress = trim((string)($_GET['indirizzo'] ?? ''));
$tabletFilter = formazioneClassiNormalizeTabletFilter((string)($_GET['tablet_filter'] ?? 'all'));

$sourceYearLabel = fce_school_year_label($sourceYearId);
$targetYearLabel = fce_school_year_label($targetYearId);
$exportBlocks = [];

$tipiToExport = $scope === 'current' ? [$selectedTipo => $tipiFormazione[$selectedTipo]] : $tipiFormazione;
foreach ($tipiToExport as $tipo => $tipoData) {
    $targetClassYear = intval($tipoData['anno'] ?? 0);
    if ($targetClassYear <= 0) {
        continue;
    }
    $addresses = formazioneClassiAddressOptionsForFormation($sourceYearId, $targetClassYear, $targetYearId);
    if ($scope === 'current') {
        if ($selectedAddress === '' || !array_key_exists($selectedAddress, $addresses)) {
            $selectedAddress = !empty($addresses) ? (string)array_key_first($addresses) : '';
        }
        $addresses = $selectedAddress !== '' ? [$selectedAddress => ($addresses[$selectedAddress] ?? $selectedAddress)] : [];
    }
    foreach ($addresses as $addressKey => $addressLabel) {
        $state = formazioneClassiState($sourceYearId, $targetYearId, $tipo, (string)$addressKey, $scope === 'current' ? $tabletFilter : 'all');
        $exportBlocks[] = [
            'tipo' => $tipo,
            'tipo_label' => (string)($tipoData['label'] ?? $tipo),
            'target_year' => $targetClassYear,
            'address_key' => (string)$addressKey,
            'address_label' => (string)$addressLabel,
            'state' => $state,
        ];
    }
}

$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator('GestOre')
    ->setTitle('Formazione classi ' . $targetYearLabel);

$usedSheetNames = [];
$summary = $spreadsheet->getActiveSheet();
$summary->setTitle('Riepilogo');
fce_write_summary_sheet($summary, $exportBlocks, $sourceYearLabel, $targetYearLabel);
$usedSheetNames[$summary->getTitle()] = true;

foreach ($exportBlocks as $block) {
    foreach (($block['state']['classes'] ?? []) as $class) {
        $students = array_values((array)($class['students'] ?? []));
        $title = fce_unique_sheet_title(fce_short_year_label($block['target_year']) . ' ' . (string)($class['label'] ?? ''), $usedSheetNames);
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($title);
        fce_write_class_sheet($sheet, $block, (string)($class['label'] ?? ''), $students, false);
    }

    $unassigned = array_values((array)($block['state']['unassigned'] ?? []));
    if (!empty($unassigned)) {
        $title = fce_unique_sheet_title(fce_short_year_label($block['target_year']) . ' da piazzare ' . (string)($block['address_label'] ?? ''), $usedSheetNames);
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($title);
        fce_write_class_sheet($sheet, $block, 'Da piazzare', $unassigned, true);
    }
}

foreach ($spreadsheet->getAllSheets() as $sheet) {
    $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
}

$spreadsheet->setActiveSheetIndex(0);

$fileName = 'formazione_classi_' . fce_clean_filename($targetYearLabel ?: 'anno') . '_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');
(new Xlsx($spreadsheet))->save('php://output');
exit;

function fce_school_year_label(int $yearId): string
{
    if ($yearId <= 0) {
        return '';
    }
    return (string)dbGetValue("SELECT anno FROM anno_scolastico WHERE id = " . dbI($yearId) . " LIMIT 1");
}

function fce_short_year_label(int $targetClassYear): string
{
    return ((string)$targetClassYear) . '^';
}

function fce_columns(int $targetClassYear): array
{
    $primary = $targetClassYear === 1 ? 'Voto medie' : 'Media';
    return [
        'N.',
        'Studente',
        'Sesso',
        'Classe',
        'Anno',
        'Indirizzo',
        'Origine',
        'Gruppo',
        'Codice fiscale',
        'Tablet',
        $primary,
        'Matematica',
        'Italiano',
        'Cap. relazionale',
        'DSA',
        'Fascia C',
        '104',
        'Bloccato',
        'Uscita',
        'Note',
    ];
}

function fce_student_values(array $student, array $block, string $classLabel, int $index): array
{
    $attrs = [];
    foreach (($student['attributi_riservati'] ?? []) as $attr) {
        $label = trim((string)($attr['label'] ?? $attr['codice'] ?? ''));
        if ($label !== '') {
            $attrs[] = $label;
        }
    }
    $tablet = formazioneClassiStudentTabletInfo($student);
    return [
        $index,
        (string)($student['nome'] ?? ''),
        (string)($student['sesso'] ?? ''),
        $classLabel,
        (string)($block['tipo_label'] ?? ''),
        (string)($block['address_label'] ?? $block['address_key'] ?? ''),
        (string)($student['classe_origine'] ?? ''),
        str_replace('_', ' ', (string)($student['gruppo_origine'] ?? '')),
        (string)($student['codice_fiscale'] ?? ''),
        !empty($tablet['is_tablet']) ? 'SI' : 'NO',
        fce_number_or_blank($student['media_generale'] ?? null),
        fce_number_or_blank($student['voto_matematica'] ?? null),
        fce_number_or_blank($student['voto_italiano'] ?? null),
        fce_number_or_blank($student['voto_capacita_relazionale'] ?? null),
        formazioneClassiStudentHasAttr($student, STUD_ATTR_R7A2) ? 'SI' : '',
        formazioneClassiStudentHasAttr($student, STUD_ATTR_Z8C3) ? 'SI' : '',
        formazioneClassiStudentHasAttr($student, STUD_ATTR_Q4M9) ? 'SI' : '',
        !empty($student['bloccato']) ? 'SI' : '',
        !empty($student['in_uscita']) ? (!empty($student['uscita_confermata']) ? 'Uscita confermata' : 'Uscita/ritiro segnalato') : '',
        trim((string)($student['note_formazione'] ?? '') . (!empty($attrs) ? "\nAttributi: " . implode(', ', $attrs) : '')),
    ];
}

function fce_write_class_sheet($sheet, array $block, string $classLabel, array $students, bool $unassigned): void
{
    $targetClassYear = intval($block['target_year'] ?? 0);
    $columns = fce_columns($targetClassYear);
    $lastCol = Coordinate::stringFromColumnIndex(count($columns));
    $title = ($unassigned ? 'Studenti da piazzare' : 'Classe ' . $classLabel) . ' - ' . ($block['tipo_label'] ?? '');
    $sheet->setCellValue('A1', $title);
    $sheet->setCellValue('A2', 'Indirizzo: ' . (string)($block['address_label'] ?? '') . ' - studenti: ' . count($students) . ' - generato il ' . date('d/m/Y H:i'));
    $sheet->fromArray($columns, null, 'A4');

    $rowNum = 5;
    $i = 1;
    foreach ($students as $student) {
        $sheet->fromArray(fce_student_values($student, $block, $classLabel, $i++), null, 'A' . $rowNum);
        $rowNum++;
    }
    fce_style_table($sheet, $lastCol, $rowNum - 1);
}

function fce_write_summary_sheet($sheet, array $blocks, string $sourceYearLabel, string $targetYearLabel): void
{
    $columns = ['Anno', 'Indirizzo', 'Classe', 'Studenti', 'M', 'F', 'Media/Voto medie', 'Matematica', 'Italiano', 'DSA', 'Fascia C', '104'];
    $lastCol = Coordinate::stringFromColumnIndex(count($columns));
    $sheet->setCellValue('A1', 'Formazione classi - riepilogo');
    $sheet->setCellValue('A2', 'Origine ' . $sourceYearLabel . ' - classi ' . $targetYearLabel . ' - generato il ' . date('d/m/Y H:i'));
    $sheet->fromArray($columns, null, 'A4');
    $rowNum = 5;
    foreach ($blocks as $block) {
        foreach (($block['state']['classes'] ?? []) as $class) {
            $students = array_values((array)($class['students'] ?? []));
            $stats = formazioneClassiStats($students);
            $sheet->fromArray([
                (string)($block['tipo_label'] ?? ''),
                (string)($block['address_label'] ?? ''),
                (string)($class['label'] ?? ''),
                intval($stats['count'] ?? 0),
                intval($stats['maschi'] ?? 0),
                intval($stats['femmine'] ?? 0),
                fce_number_or_blank($stats['media_generale'] ?? null),
                fce_number_or_blank($stats['voto_matematica'] ?? null),
                fce_number_or_blank($stats['voto_italiano'] ?? null),
                intval($stats['dsa'] ?? 0),
                intval($stats['fascia_c'] ?? 0),
                intval($stats['legge_104'] ?? 0),
            ], null, 'A' . $rowNum);
            $rowNum++;
        }
    }
    fce_style_table($sheet, $lastCol, $rowNum - 1);
}

function fce_style_table($sheet, string $lastCol, int $lastRow): void
{
    $lastRow = max(4, $lastRow);
    $sheet->mergeCells('A1:' . $lastCol . '1');
    $sheet->mergeCells('A2:' . $lastCol . '2');
    $sheet->freezePane('A5');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('0B4F71');
    $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('667085');
    $sheet->getStyle('A4:' . $lastCol . '4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $sheet->getStyle('A4:' . $lastCol . '4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0B79A5');
    $sheet->getStyle('A4:' . $lastCol . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('C7D2DA');
    $sheet->getStyle('A4:' . $lastCol . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
    $sheet->getStyle('A4:' . $lastCol . '4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    for ($i = 1; $i <= Coordinate::columnIndexFromString($lastCol); $i++) {
        $col = Coordinate::stringFromColumnIndex($i);
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
}

function fce_unique_sheet_title(string $title, array &$used): string
{
    $base = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', ' ', trim($title));
    $base = trim(preg_replace('/\\s+/', ' ', $base));
    if ($base === '') {
        $base = 'Foglio';
    }
    $base = substr($base, 0, 31);
    $candidate = $base;
    $n = 2;
    while (isset($used[$candidate])) {
        $suffix = ' ' . $n++;
        $candidate = substr($base, 0, 31 - strlen($suffix)) . $suffix;
    }
    $used[$candidate] = true;
    return $candidate;
}

function fce_number_or_blank($value)
{
    return $value === null || $value === '' ? '' : (float)$value;
}

function fce_clean_filename(string $value): string
{
    $value = preg_replace('/[^A-Za-z0-9_\\-]+/', '_', $value);
    return trim((string)$value, '_') ?: 'formazione_classi';
}
