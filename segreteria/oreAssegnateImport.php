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

function endsWith($haystack, $needle) {
    $length = strlen($needle);
    return $length === 0 ||  (substr($haystack, -$length) === $needle);
}

function titlecase($str) {
    return ucwords(strtolower($str));
}

function nextWords() {
    global $linePos;
    global $numberOfLines;
    global $lines;
    global $line;
    global $words;

    if ($linePos >= $numberOfLines) {
        return null;
    }
    $line = trim($lines[$linePos]);
    $linePos++;
    // debug('linea letta=' . $line);

    // salta commenti e linee vuote
    if (startswith( $line, "#") || startswith( $line, "\"#") || empty($line)) {
        return nextWords();
    }

    // scompone i csv
    $words = str_getcsv($line);

    // trim delle parole
    $words = array_map('trim', $words);
    return $words;
}

function checkWord($keyword) {
    global $words;

    if (trim($words[0]) != $keyword) {
        erroreDiImport("atteso $keyword e trovato $words[0]");
        return false;
    }
   return true;
}

function erroreDiImport($messaggio) {
    global $data;
    global $linePos;
    global $sql;

    warning("Errore di import linea $linePos: " . $messaggio);
    $data = $data . "<strong>Errore di import linea $linePos:</strong> " . $messaggio;

    // azzera le istruzioni sql
    $sql = '';
}

// setup del src e del risultato (data)
$data = '';

$src = '';
if(isset($_POST)) {
	$src = trim($_POST['contenuto']);
}
$lines_array = explode("\n", $src);
$lines = array_filter($lines_array, 'trim');
$numberOfLines = count($lines);
$linePos = 0;
$completato = false;
$sql = '';

while (($words = nextWords()) != null) {
    debug('words[0]='.$words[0]);
    debug('words[1]='.$words[1]);
    debug('words[2]='.$words[2]);
    debug('words[3]='.$words[3]);
    debug('words[4]='.$words[4]);

    $attivita_nome = escapeString($words[0]);
    $attivita_dettaglio = escapeString($words[1]);
    $attivita_ore = escapeString($words[2]);
    $docente_cognome = escapeString(titlecase($words[3]));
    $docente_nome = escapeString(titlecase($words[4]));

    $query = "SELECT docente.id FROM docente WHERE docente.cognome LIKE '$docente_cognome' COLLATE utf8_general_ci AND docente.nome LIKE '$docente_nome' COLLATE utf8_general_ci ";
    $id_list = dbGetAll($query);
    // controlla di avere trovato almeno un docente
    if (count($id_list) == 0) {
        erroreDiImport("attivita $attivita_nome: docente non trovato cognome=$docente_cognome nome=$docente_nome");
        break;
    }
    // controlla che non ce ne siano piu' di uno
    if (count($id_list) > 1) {
        $messaggio = "attivita $attivita_nome: piu' docenti corrispondono alla ricerca cognome=$docente_cognome nome=$docente_nome: utilizzato il primo";
        warning("Errore di import linea $linePos: " . $messaggio);
        $data = $data . "<strong>Errore di import linea $linePos:</strong> " . $messaggio;
    }
    // se tutto va bene c'e' un solo valore
    $docente_id = $id_list[0]['id'];

    // ora deve trovare il tipo di attivita
    $query = "SELECT ore_previste_tipo_attivita.id FROM ore_previste_tipo_attivita WHERE ore_previste_tipo_attivita.nome LIKE '$attivita_nome' COLLATE utf8_general_ci";
    $id_list = dbGetAll($query);
    // controlla di avere trovato almeno una attivita
    if (count($id_list) == 0) {
        erroreDiImport("attivita $attivita_nome: tipo attivita non trovato nome=$attivita_nome");
        break;
    }
    // controlla che non ce ne siano piu' di uno
    if (count($id_list) > 1) {
        erroreDiImport("attivita $attivita_nome: piu' tipo attivita corrispondono alla ricerca nome=$attivita_nome");
        break;
    }
    // se tutto va bene c'e' un solo valore
    $attivita_tipo_id = $id_list[0]['id'];

    $sql .= "INSERT INTO ore_previste_attivita(dettaglio, ore, docente_id, anno_scolastico_id, ore_previste_tipo_attivita_id) VALUES('$attivita_dettaglio', '$attivita_ore', $docente_id, $__anno_scolastico_corrente_id, $attivita_tipo_id) ;
        SET @last_id_attivita = LAST_INSERT_ID();
        ";

    $data = $data . 'attivita=' . $attivita_nome . ' dettaglio=' . $attivita_dettaglio . ' ore=' . $attivita_ore . ' docente=' . $docente_cognome . " " . $docente_nome;
    $data .= '</br>';
    debug('attivita=' . $attivita_nome . ' dettaglio=' . $attivita_dettaglio . ' ore=' . $attivita_ore . ' docente=' . $docente_cognome . " " . $docente_nome);
}

// esegue la query se non vuota
if (!empty($sql)) {
    // debug($sql);
    dbExecMulti($sql);
    info('Import attivita effettuato');
}

echo $data;
?>
