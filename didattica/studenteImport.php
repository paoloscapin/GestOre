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
ruoloRichiesto('dirigente','segreteria-didattica');

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

// la prima istruzione sql rimuove da tutti le classe a cui appartengono
$sqlList[] = "UPDATE studente SET classe='';";

// contatori
$daInserire = 0;
$daModificare = 0;

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

    // ci devono essere nome,cognome,email,classe,anno
    if (count($words) < 5) {
        erroreDiImport("numero di argomenti errato (" . count($words) . ")");
        break;
    }
    $cognome = escapeString($words[0]);
    $nome = escapeString($words[1]);
    $email = escapeString($words[2]);
    $classe = escapeString($words[3]);
    $anno = escapeString($words[4]);

    // controlla se l'indirizzo di email e' gia' presente sul database
    $id = dbGetValue("SELECT id FROM studente WHERE email = '$email';");

    if ($id == null) {
        // se non lo trova, lo deve inserire
        $daInserire ++;
        // calcola lo username (precede @ nell'email)
        $username = strstr($email, '@', true);
        $sqlList[] = "INSERT INTO studente (cognome,nome,email,classe,anno) VALUES ('$cognome','$nome','$email','$classe','$anno')";
    } else {
        // se c'era gia', lo aggiorna con i valori trovati della classe
        $daModificare++;
        $sqlList[] = "UPDATE studente SET classe='$classe', anno='$anno' WHERE id=$id";
    }
}

if (empty($data)) {
    $data = 'nuovi studenti=' . $daInserire . ' modificati=' . $daModificare;
    debug($data);
}

// esegue la query se non vuota
if (!empty($sqlList)) {
    foreach($sqlList as $sql) {
        dbExec($sql);
        // debug($sql);
    }
    info('Import studenti effettuato: ' . $data);
}

echo '<strong>' . $data .'</strong>';
?>
