<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
require_once '../common/checkSession.php';
ruoloRichiesto('dirigente','segreteria-didattica');

function startsWith($haystack, $needle) {
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

$terminatoCorrettamente = true;

function erroreDiImport($messaggio) {
    global $dataHtml;
    global $linePos;
    global $sqlList;
    global $terminatoCorrettamente;

    $terminatoCorrettamente = false;
    warning("Errore di import linea $linePos: " . $messaggio);
    $dataHtml = $dataHtml . "<strong>Errore di import linea $linePos:</strong> " . $messaggio .'<br>';

    // azzera le istruzioni sql
   // $sqlList = array();
}

function getWordContent($words, $pos) {
    if (count($words) < $pos) {
        return '';
    }
    return escapeString($words[$pos]);
}

// setup del src e del risultato (dataHtml) e delle istruzioni (sql[])
$dataHtml = '';
$sqlList = array();

$src = '';
if(isset($_POST)) {
	$src = trim($_POST['contenuto']);
}
$lines_array = explode("\n", $src);
$lines = array_filter($lines_array, 'trim');
$linePos = 0;

// contatore import
$carenze = 0;
$ngiapresenti = 0;

// scorre tutte le linee del csv
foreach($lines as $line) {
    $linePos ++;
    if ($linePos==1)
    {
        debug('Skip line: ' . $line);
        continue;
    }
    // scompone i csv
    $words = str_getcsv($line);
    if (startswith( $line, "#") || empty($line)) {
        debug('Skip line: ' . $line);
        continue;
    }

    // trim delle parole
    $words = array_map('trim', $words);

    // ci devono essere numero, studente, classe, materia
    if (count($words) < 4) {
        erroreDiImport("numero di argomenti errato (" . count($words) . ")");
        continue;
    }
    $numero = getWordContent($words, 0);
    $studente = getWordContent($words,1 );
    $classe = getWordContent($words, 2);
    $materia = getWordContent($words, 3);

    // cerco la materia
    $materia_id = dbGetValue("SELECT materia.id FROM materia WHERE materia.codice = '$materia'");
    if ($materia_id == null) {
        erroreDiImport("materia non trovata nome=$materia");
        continue;
    }

    // classe
    $classe_id = dbGetValue("SELECT classi.id FROM classi WHERE classi.classe = '$classe'");
    if ($classe_id == null) {
        erroreDiImport("classe non trovata nome=$classe");
        continue;
    }

    // studente
    // rimuovi gli spazi
    $studente_low = strtolower(preg_replace('/\s+/', '', $studente));
    $studente_id = dbGetValue("
    SELECT studente.id,LOWER(studente.cognome),LOWER(studente.nome),studente.classe 
    FROM studente 
    INNER JOIN classi classi 
    ON studente.classe = classi.classe WHERE CONCAT(REPLACE(studente.cognome,' ',''),REPLACE(studente.nome,' ','')) = '$studente_low'
    AND studente.classe = '$classe'");

    if ( $studente_id == null) {
        erroreDiImport("studente non trovato nome = $studente nomecercato = $studente_low");
        continue;
    }

    // prima di accettare controlla che non ci sia già questa carenza
    $carenzaEsistente = dbGetFirst("SELECT * FROM carenze WHERE id_materia = $materia_id AND id_studente = $studente_id AND id_classe = $classe_id AND id_anno_scolastico=$__anno_scolastico_corrente_id;");
        if ($carenzaEsistente != null) {
            //erroreDiImport("la carenza di $studente della classe $classe e materia $materia risulta già presente");
            $ngiapresenti++;
            info("la carenza di $studente della classe $classe e materia $materia risulta già presente");
            continue;
        }
    
    $insertCarenzeSql = "INSERT INTO carenze(id_studente, id_materia, id_classe, id_docente, id_anno_scolastico, stato, data_inserimento, data_validazione, data_invio) VALUES('$studente_id','$materia_id','$classe_id','0','$__anno_scolastico_corrente_id','0',NOW(),'','')";
    info("CARENZE SQL : " . $insertCarenzeSql);
    $sqlList[] = $insertCarenzeSql;
    $carenze++ ;
}

// esegue la query se non vuota
if (!empty($sqlList)) {
    foreach($sqlList as $sql) {
        dbExec($sql);
    }

    info('Import effettuato: inserite ' . $carenze . ' nuove carenze');
}
echo $dataHtml;
if ($ngiapresenti>0)
{
    echo '<strong>Righe già presenti saltate ' . $ngiapresenti . '</strong><br>';
}
echo '<strong>Import effettuato: inserite ' . $carenze . ' nuove carenze</strong>';
?>
