<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('dirigente');

function startsWith($haystack, $needle) {
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

function erroreDiImport($messaggio) {
    global $data;
    global $linePos;
    global $sqlList;
    global $line;

    warning("Errore di import linea $linePos: " . $messaggio);
    warning("Linea: " . $line);
    $data = $data . "<strong>Errore di import linea $linePos:</strong> " . $messaggio;

    // azzera le istruzioni sql
    $sqlList = '';
}

// necessario per estrarre correttamente il csv bisogna operare su file virtuale
function my_str_getcsv($input, $delimiter = ",", $enclosure = '"', $escape = "\\") {
    global $lines_array;

    $fiveMBs = 5 * 1024 * 1024;
    $fp = fopen("php://temp/maxmemory:$fiveMBs", 'r+');
    fputs($fp, $input);
    rewind($fp);

    while (($line = fgetcsv($fp, 1000, ",")) !== false) {
        $lines_array[] = $line;
    }

    fclose($fp);
}

// setup
$lines_array = [];
$data = '';
$sqlList = array();

$src = '';
$anno_scolastico_id = $__anno_scolastico_corrente_id;

if (isset($_POST)) {
    $src = trim($_POST['contenuto']);

    // anno selezionato (se passato), altrimenti anno corrente
    if (isset($_POST['anno_scolastico_id']) && $_POST['anno_scolastico_id'] !== '') {
        $anno_scolastico_id = intval($_POST['anno_scolastico_id']);
    }
}

// estrae linee CSV
my_str_getcsv($src);

$linePos = 0;

// 1) Aree: NON hanno anno, quindi reset globale valido=0 (come vuoi tu)
$sqlList[] = "UPDATE bonus_area SET valido='0';";

// 2) Indicatori e bonus: per anno selezionato
$sqlList[] = "UPDATE bonus_indicatore SET valido='0' WHERE anno_scolastico_id = $anno_scolastico_id;";
$sqlList[] = "UPDATE bonus SET valido='0' WHERE anno_scolastico_id = $anno_scolastico_id;";

// contatori
$areaNumber = 0;
$areaCode = '';
$indicatoreNumber = 0;
$descrittoreNumber = 0;

// per comporre codici
$indicatoreCode = '';

foreach ($lines_array as $words) {
    $linePos++;

    $line = join(",", $words);

    if (empty($words) || startsWith($words[0], "#")) {
        debug('Skip line ' . $linePos . ': ' . $line);
        continue;
    }

    $words = array_map('trim', $words);

    // 5 colonne: Area,Indicatore,Descrittore,Evidenze,Valore
    if (count($words) < 5) {
        erroreDiImport("numero di argomenti errato (" . count($words) . ")");
        break;
    }

    // AREA: NON si inserisce. Si riattiva e aggiorna descrizione sull'area esistente (A,B,C...)
    $area = escapeString($words[0]);
    if (!empty($area)) {
        ++$areaNumber;

        $indicatoreNumber = 0;
        $descrittoreNumber = 0;

        $areaCode = chr(ord('A') - 1 + $areaNumber);

        // recupera area esistente
        $areaRow = dbGetFirst("SELECT id FROM bonus_area WHERE codice = '$areaCode' LIMIT 1;");
        if (!$areaRow || !isset($areaRow['id'])) {
            erroreDiImport("Area non trovata in bonus_area per codice '$areaCode'. Crea prima le aree A,B,C in tabella.");
            break;
        }
        $areaId = intval($areaRow['id']);

        // riattiva e aggiorna descrizione in base al CSV
        $sqlList[] = "UPDATE bonus_area
                      SET descrizione = '$area', valido = 1
                      WHERE id = $areaId;";

        // set variabile per insert successive
        $sqlList[] = "SET @area_id = $areaId;";

        continue;
    }

    if ($areaNumber <= 0) {
        erroreDiImport("bisogna che sia definita almeno un'area");
        break;
    }

    // INDICATORE: si inserisce per l'anno selezionato
    $indicatore = escapeString($words[1]);
    if (!empty($indicatore)) {
        ++$indicatoreNumber;
        $descrittoreNumber = 0;

        $indicatoreCode = $areaCode . '.' . $indicatoreNumber;

        $sqlList[] = "INSERT INTO bonus_indicatore (codice, descrizione, valido, bonus_area_id, anno_scolastico_id)
                      VALUES ('$indicatoreCode', '$indicatore', 1, @area_id, $anno_scolastico_id);";
        $sqlList[] = "SET @indicatore_id = LAST_INSERT_ID();";

        continue;
    }

    if ($indicatoreNumber <= 0) {
        erroreDiImport("bisogna che sia definito almeno un indicatore");
        break;
    }

    // BONUS (descrittore): si inserisce per l'anno selezionato
    $descrittore = escapeString($words[2]);
    $evidenze = escapeString($words[3]);
    $valore = escapeString($words[4]);

    if (!empty($descrittore)) {
        ++$descrittoreNumber;

        if (empty($valore)) {
            $valore = 1;
        }

        $descrittoreCode = $indicatoreCode . '.' . $descrittoreNumber;

        $sqlList[] = "INSERT INTO bonus (codice, descrittori, evidenze, valore_previsto, valido, bonus_indicatore_id, anno_scolastico_id)
                      VALUES ('$descrittoreCode', '$descrittore', '$evidenze', '$valore', 1, @indicatore_id, $anno_scolastico_id);";

        continue;
    }

    erroreDiImport("istruzione non riconosciuta");
    break;
}

// esegue le query solo se non ci sono errori
if (!empty($sqlList) && empty($data)) {
    foreach ($sqlList as $sql) {
        dbExec($sql);
        // debug($sql);
    }
    info("Import criteri bonus effettuato anno_scolastico_id=$anno_scolastico_id");
}

echo $data;
?>
