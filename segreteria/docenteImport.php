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
ruoloRichiesto('dirigente','segreteria-docenti');

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
$sqlList[] = "UPDATE docente SET attivo='0';";

// contatori
$totaleDocentiAttuali = dbGetValue("SELECT count(id) FROM docente;");
$totaleDocentiAttivi = dbGetValue("SELECT count(id) FROM docente;");
$totaleDocentiDaInserire = 0;
$totaleDocentiDaModificare = 0;

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

    // ci devono essere almeno cognome,nome,email
    if (count($words) < 3) {
        erroreDiImport("numero di argomenti errato (" . count($words) . ")");
        break;
    }
    $cognome = escapeString($words[0]);
    $nome = escapeString($words[1]);
    $email = escapeString($words[2]);

    // il numero di matricola opzionale
    $matricola = '';
    if (count($words) >= 4) {
        $matricola = escapeString($words[3]);
    }

    // controlla se l'indirizzo di email e' gia' presente sul database
    $docente_id = dbGetValue("SELECT id FROM docente WHERE email = '$email';");

    if ($docente_id == null) {
        // se non lo trova, lo deve inserire
        $totaleDocentiDaInserire ++;
        // calcola lo username (precede @ nell'email)
        $username = strstr($email, '@', true);
        $sqlList[] = "INSERT INTO utente (username,cognome,nome,ruolo,email) VALUES ('$username','$cognome','$nome','docente','$email');";
        $sqlList[] = "INSERT INTO docente (username,cognome,nome,email,matricola,attivo) VALUES ('$username','$cognome','$nome','$email','$matricola',1);";
        $sqlList[] = "SET @docente_id = LAST_INSERT_ID();";
    } else {
        // se c'era gia', lo aggiorna con i valori trovati della classe
        $totaleDocentiDaModificare++;
        if (empty($matricola)) {
            $sqlList[] = "UPDATE docente SET attivo=1 WHERE id=$docente_id;";
        } else {
            $sqlList[] = "UPDATE docente SET attivo=1, matricola='$matricola' WHERE id=$docente_id;";
        }
        $sqlList[] = "SET @docente_id = $docente_id;";
    }
    $sqlList[] = "SET @anno_id = $__anno_scolastico_corrente_id;";
    $sqlList[] = "INSERT INTO profilo_docente(anno_scolastico_id, docente_id) SELECT @anno_id, @docente_id FROM DUAL WHERE NOT EXISTS( SELECT 1 FROM profilo_docente WHERE anno_scolastico_id = @anno_id AND docente_id = @docente_id) LIMIT 1;";
}

if (empty($data)) {
    $data = 'docenti presenti prima di import=' . $totaleDocentiAttuali . ' di cui attivi=' . $totaleDocentiAttivi;
    $data = 'nuovi docenti=' . $totaleDocentiDaInserire . ' docenti che rimangono=' . $totaleDocentiDaModificare . ' docenti che Vanno via=' . ($totaleDocentiAttivi - $totaleDocentiDaModificare);
    debug($data);
}

// esegue la query se non vuota
if (!empty($sqlList)) {
    foreach($sqlList as $sql) {
        dbExec($sql);
        // debug($sql);
    }
    info('Import docenti effettuato: ' . $data);
}

echo '<strong>' . $data .'</strong>';
?>
