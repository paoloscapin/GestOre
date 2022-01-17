<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

?>

<?php
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

// setup del src e del risultato (data) e delle istruzioni (sql[])
$lines_array = [];
$data = '';
$sqlList = array();

$src = '';
if(isset($_POST)) {
	$src = trim($_POST['contenuto']);
}

// estrae l'array di linee
my_str_getcsv($src);

// traccia il risultato dell'estrazione per debug
// $res = var_export($the_big_array, true);
// debug($res);

$linePos = 0;

// la prima istruzione sql disabilita tutti i bonus (area, descrittore e bonus)
$sqlList[] = "UPDATE bonus_area SET valido='0';";
$sqlList[] = "UPDATE bonus_indicatore SET valido='0';";
$sqlList[] = "UPDATE bonus SET valido='0';";

// codice corrente di area, indicatore, descrittore
$areaNumber = 0;
$areaCode = '';
$indicatoreNumber = 0;
$descrittoreNumber = 0;

// scorre tutte le linee del csv: i valori csv sono contenuti nella linea che e' un array lei stessa
foreach($lines_array as $words) {
    $linePos ++;

    // ricostruisce la linea intera
    $line = join(",",$words);

    if (empty($words) || startswith( $words[0], "#")) {
        debug('Skip line ' . $linePos . ': ' . $line);
        continue;
    }

    // trim delle parole
    $words = array_map('trim', $words);

    // sono sempre previste 5 colonne: Area,Indicatore,Descrittore,Evidenze,Valore
    if (count($words) < 5) {
        erroreDiImport("numero di argomenti errato (" . count($words) . ")");
        break;
    }

    // se c'e' qualcosa in area, bisogna inserire area
    $area = escapeString($words[0]);
    if (!empty($area)) {
        ++ $areaNumber;

        // azzera gli altri contatori interni all'area
        $indicatoreNumber = 0;
        $descrittoreNumber = 0;
        
        // calcola il codice: area parte da 'A' ed e' letterale
        $areaCode = chr(ord('A') - 1 + $areaNumber);

        // inserisce l'area nel db e prende il suo codice
        $sqlList[] = "INSERT INTO bonus_area (codice,descrizione,valido) VALUES ('$areaCode','$area',1);";
        $sqlList[] = "SET @area_id = LAST_INSERT_ID();";

        // quando trova un'area, la riga e' completa
        continue;
    }

    // controlla che ci sia almeno un'area
    if ($areaNumber <= 0) {
        erroreDiImport("bisogna che sia definita almeno un'area");
        break;
    }

    // se c'e' qualcosa in indicatore, bisogna inserire indicatore
    $indicatore = escapeString($words[1]);
    if (!empty($indicatore)) {
        ++ $indicatoreNumber;

        // azzera il contatore interno dei descrittori
        $descrittoreNumber = 0;
        
        // calcola il codice
        $indicatoreCode = $areaCode . '.' . $indicatoreNumber;

        // inserisce l'indicatore nel db e prende il suo codice
        $sqlList[] = "INSERT INTO bonus_indicatore (codice,descrizione,valido, bonus_area_id) VALUES ('$indicatoreCode','$indicatore',1, @area_id);";
        $sqlList[] = "SET @indicatore_id = LAST_INSERT_ID();";

        // quando trova un indicatore, la riga e' completa
        continue;
    }

    // controlla che ci sia almeno un indicatore
    if ($indicatoreNumber <= 0) {
        erroreDiImport("bisogna che sia definito almeno un indicatore");
        break;
    }

    // controlla se c'e' qualcosa in descrittore,Evidenze,Valore
    $descrittore = escapeString($words[2]);
    $evidenze = escapeString($words[3]);
    $valore = escapeString($words[4]);
    if (!empty($descrittore)) {
        ++ $descrittoreNumber;

        // controlla che ci sia un valore (altrimenti mette 1)
        if (empty($valore)) {
            $valore = 1;
        }
        
        // calcola il codice
        $descrittoreCode = $indicatoreCode . '.' . $descrittoreNumber;

        // inserisce l'indicatore nel db e prende il suo codice
        $sqlList[] = "INSERT INTO bonus (codice,descrittori,evidenze,valore_previsto,valido,bonus_indicatore_id) VALUES ('$descrittoreCode','$descrittore','$evidenze','$valore',1, @indicatore_id);";

        // quando trova un descrittore, la riga e' completa
        continue;
    }

    // se non c'era nessuna di queste cose, e' un errore
    if ($indicatoreNumber <= 0) {
        erroreDiImport("istruzione non riconosciuta");
        break;
    }
}

if (empty($data)) {
}

// esegue la query se non vuota
if (!empty($sqlList)) {
    foreach($sqlList as $sql) {
        dbExec($sql);
        // debug($sql);
    }
    info('Import criteri bonus effettuato: ' . $data);
}

echo $data;
?>
