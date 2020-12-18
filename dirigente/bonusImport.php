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

    warning("Errore di import linea $linePos: " . $messaggio);
    $data = $data . "<strong>Errore di import linea $linePos:</strong> " . $messaggio;

    // azzera le istruzioni sql
    $sqlList = '';
}

// setup del src e del risultato (data) e delle istruzioni (sql[])
$data = '';
$sqlList = array();

$src = '';
if(isset($_POST)) {
	$src = trim($_POST['contenuto']);
}
$lines_array = explode("\n", $src);
$lines = array_filter($lines_array, 'trim');
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

// scorre tutte le linee del csv
foreach($lines as $line) {
    $linePos ++;

    // scompone i csv
    $words = str_getcsv($line);
    if (startswith( $line, "#") || empty($line)) {
        debug('Skip line: ' . $line);
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
        // dbExec($sql);
        debug($sql);
    }
    info('Import criteri bonus effettuato: ' . $data);
}

echo $data;
?>
