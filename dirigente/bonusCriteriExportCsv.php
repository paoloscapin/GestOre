<?php
require_once '../common/checkSession.php';
ruoloRichiesto('dirigente');
require_once '../common/connect.php';

$anno = isset($_GET['anno_scolastico_id']) ? intval($_GET['anno_scolastico_id']) : $__anno_scolastico_corrente_id;

// prendo nome anno per filename leggibile
$annoRow = dbGetFirst("SELECT anno FROM anno_scolastico WHERE id = $anno");
$annoName = $annoRow ? $annoRow['anno'] : ("id_" . $anno);

$query = "
SELECT
  ba.codice AS area_codice,
  ba.descrizione AS area_descrizione,

  bi.codice AS indicatore_codice,
  bi.descrizione AS indicatore_descrizione,
  bi.valore_massimo AS indicatore_valore_massimo,

  b.codice AS bonus_codice,
  b.descrittori AS bonus_descrittori,
  b.evidenze AS bonus_evidenze,
  b.valore_previsto AS bonus_valore_previsto
FROM bonus_area ba
LEFT JOIN bonus_indicatore bi
  ON bi.bonus_area_id = ba.id
 AND bi.anno_scolastico_id = $anno
 AND (bi.valido IS NULL OR bi.valido = 1)
LEFT JOIN bonus b
  ON b.bonus_indicatore_id = bi.id
 AND b.anno_scolastico_id = $anno
 AND (b.valido IS NULL OR b.valido = 1)
WHERE (ba.valido IS NULL OR ba.valido = 1)
ORDER BY ba.codice, bi.codice, b.codice;
";

$rows = dbGetAll($query);

// output CSV (UTF-8 con BOM per Excel)
$filename = "criteri_bonus_" . preg_replace('/[^0-9A-Za-z_\-]/', '_', $annoName) . ".csv";
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
echo "\xEF\xBB\xBF"; // BOM

$fh = fopen('php://output', 'w');

// separatore “;” più comodo in IT/Excel
$sep = ';';

// intestazioni
fwrite($fh, implode($sep, [
    'Area Codice',
    'Area Descrizione',
    'Indicatore Codice',
    'Indicatore Descrizione',
    'Indicatore Max',
    'Bonus Codice',
    'Bonus Descrittori',
    'Bonus Evidenze',
    'Bonus Valore'
]) . "\n");

foreach ($rows as $r) {
    // normalizza newlines/spazi per CSV
    $line = [
        $r['area_codice'],
        $r['area_descrizione'],
        $r['indicatore_codice'],
        $r['indicatore_descrizione'],
        $r['indicatore_valore_massimo'],
        $r['bonus_codice'],
        $r['bonus_descrittori'],
        $r['bonus_evidenze'],
        $r['bonus_valore_previsto'],
    ];

    // escape basilare CSV con ;
    $escaped = array_map(function($v) use ($sep){
        $v = (string)$v;
        $v = str_replace(["\r\n", "\r", "\n"], " ", $v);
        $v = trim($v);
        if (strpos($v, $sep) !== false || strpos($v, '"') !== false) {
            $v = '"' . str_replace('"', '""', $v) . '"';
        }
        return $v;
    }, $line);

    fwrite($fh, implode($sep, $escaped) . "\n");
}

fclose($fh);
exit;
