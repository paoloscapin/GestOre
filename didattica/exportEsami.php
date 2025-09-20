<?php
require_once '../common/connect.php';
require_once '../common/checkSession.php';

ruoloRichiesto('segreteria-didattica', 'dirigente');

// Carica PhpSpreadsheet
require_once __DIR__ . '/../common/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function formattaDataItaliana($dataSql) {
    if (empty($dataSql)) {
        return "";
    }
    $dt = new DateTime($dataSql);
    return $dt->format('d/m/Y H:i');
}

// Query unica
$query = "
SELECT 
    CONCAT (s.cognome, s.nome) AS studente,
    CONCAT (d.cognome, d.nome) AS docente,
    ced.data_esame,
    ced.aula,
    ce.presente,
    ce.tipo_prova,
    ce.recuperato AS esito,
    m.nome AS materia,
    cl.classe AS classe
FROM corso_esiti ce
INNER JOIN studente s ON s.id = ce.id_studente
INNER JOIN corso c ON c.id = ce.id_corso
INNER JOIN studente_frequenta sf ON s.id = sf.id_studente
INNER JOIN classi cl ON sf.id_classe = cl.id
INNER JOIN docente d ON d.id = c.id_docente
INNER JOIN materia m ON m.id = c.id_materia
INNER JOIN corso_esami_date ced ON ced.id_corso = c.id
WHERE sf.id_anno_scolastico = $__anno_scolastico_corrente_id 
ORDER BY classe, s.cognome, s.nome
";

$records = dbGetAll($query);

// Crea il file Excel
$spreadsheet = new Spreadsheet();

// ---------- FOGLIO 1: Assenti ----------
$sheet1 = $spreadsheet->getActiveSheet();
$sheet1->setTitle('Assenti');

// intestazioni
$sheet1->fromArray(["Studente", "Classe" , "Materia", "Docente", "Data", "Tipo di prova", "Aula"], NULL, "A1");

$rowNum = 2;
foreach ($records as $row) {
    if (empty($row['presente']) || $row['presente'] == 0) {
        $sheet1->fromArray([
            $row['studente'],
            $row['classe'], 
            $row['materia'],
            $row['docente'],
            formattaDataItaliana($row['data_esame']),
            $row['tipo_prova'],
            $row['aula']
        ], NULL, "A$rowNum");
        $rowNum++;
    }
}

// ---------- FOGLIO 2: Non idonei ----------
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Esito negativo');
$sheet2->fromArray(["Studente", "Classe" , "Corso", "Docente", "Data", "Tipo di prova", "Aula"], NULL, "A1");

$rowNum = 2;
foreach ($records as $row) {
    if ($row['presente'] == 1 && $row['esito'] == 0) {
        $sheet2->fromArray([
            $row['studente'],
            $row['classe'], 
            $row['materia'],
            $row['docente'],
            formattaDataItaliana($row['data_esame']),
            $row['tipo_prova'],
            $row['aula']
        ], NULL, "A$rowNum");
        $rowNum++;
    }
}

// ---------- FOGLIO 3: Idonei ----------
$sheet3 = $spreadsheet->createSheet();
$sheet3->setTitle('Esito positivo');
$sheet3->fromArray(["Studente", "Classe", "Corso", "Docente", "Data", "Tipo di prova", "Aula"], NULL, "A1");

$rowNum = 2;
foreach ($records as $row) {
    if ($row['presente'] == 1 && $row['esito'] == 1) {
        $sheet3->fromArray([
            $row['studente'],
            $row['classe'], 
            $row['materia'],
            $row['docente'],
            formattaDataItaliana($row['data_esame']),
            $row['tipo_prova'],
            $row['aula']
        ], NULL, "A$rowNum");
        $rowNum++;
    }
}

// Output del file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="esiti_esami.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
