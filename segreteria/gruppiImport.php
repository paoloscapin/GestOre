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
    debug('words[5]='.$words[5]);

    $gruppo_nome = escapeString($words[0]);
    $gruppo_commento = escapeString($words[1]);
    $gruppo_max_ore = escapeString($words[2]);

    $gruppo_responsabile_cognome = escapeString(titlecase($words[3]));
    $gruppo_responsabile_nome = escapeString(titlecase($words[4]));
    $query = "SELECT docente.id FROM docente WHERE docente.cognome LIKE '$gruppo_responsabile_cognome' COLLATE utf8_general_ci AND docente.nome LIKE '$gruppo_responsabile_nome' COLLATE utf8_general_ci AND docente.attivo = true";
    $gruppo_docente_id_list = dbGetAll($query);
    // controlla di avere trovato almeno un docente
    if (count($gruppo_docente_id_list) == 0) {
        erroreDiImport("gruppo $gruppo_nome: docente non trovato cognome=$gruppo_responsabile_cognome nome=$gruppo_responsabile_nome");
        break;
    }
    // controlla che non ce ne siano piu' di uno
    if (count($gruppo_docente_id_list) > 1) {
        $messaggio = "gruppo $gruppo_nome: piu' docenti corrispondono alla ricerca cognome=$gruppo_responsabile_cognome nome=$gruppo_responsabile_nome: utilizzato il primo";
        warning("Errore di import linea $linePos: " . $messaggio);
        $data = $data . "<strong>Errore di import linea $linePos:</strong> " . $messaggio;
    }
    // se tutto va bene c'e' un solo valore
    $gruppo_responsabile_docente_id = $gruppo_docente_id_list[0]['id'];

    // controlla se e' un gruppo clil
    $clil = false;
    $gruppo_clil = strtolower(escapeString($words[5]));
    if ($gruppo_clil == 'si' || $gruppo_clil == 'clil') {
        $clil = true;
    }

    // controlla se e' un gruppo orientamento
    $orientamento = false;
    $gruppo_orientamento = strtolower(escapeString($words[5]));
    if ($gruppo_orientamento == 'si' || $gruppo_orientamento == 'orientamento') {
        $orientamento = true;
    }

    $sql .= "INSERT INTO gruppo (nome, dipartimento, commento, max_ore, clil, orientamento, anno_scolastico_id, responsabile_docente_id) VALUES ('$gruppo_nome', 0, '$gruppo_commento', '$gruppo_max_ore', $gruppo_clil, '$__anno_scolastico_corrente_id', '$gruppo_responsabile_docente_id');
        SET @last_id_gruppo = LAST_INSERT_ID(); ";

    $data = $data . 'Gruppo=' . $gruppo_nome . ' commento=' . $gruppo_commento . ' ore=' . $gruppo_max_ore . ' responsabile=' . $gruppo_responsabile_cognome . " " . $gruppo_responsabile_nome . ' clil=' . $clil . ' orientamento=' . $orientamento;
    $data .= '</br>';
    debug('Gruppo=' . $gruppo_nome . ' commento=' . $gruppo_commento . ' ore=' . $gruppo_max_ore . ' responsabilente=' . $gruppo_responsabile_cognome . " " . $gruppo_responsabile_nome . ' clil=' . $clil . ' orientamento=' . $orientamento);
}

// esegue la query se non vuota
if (!empty($sql)) {
    // debug($sql);
    dbExecMulti($sql);
    info('Import gruppi effettuato');
}

echo $data;
?>
