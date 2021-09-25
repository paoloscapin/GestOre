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
    if (startswith( $line, "#") || empty($line)) {
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

// prima riga: l'anno
$words = nextWords();
checkWord('ANNO');
$anno = $words[1];
// debug('anno=' . $anno);

// si posiziona sul primo codice
while ($words[0] != 'Gruppo') {
    $words = nextWords();
}

while ($words[0] == 'Gruppo') {
    if (!checkWord('Gruppo')) {
        break;
    }
    $gruppo_nome = $words[1];
//    debug("gruppo_nome=" . $gruppo_nome);

    // segue commento
    nextWords();
    if (!checkWord('Commento')) {
        erroreDiImport("Commento non presente (nemmeno vuoto) per il gruppo $gruppo_nome");
        break;
    }
    $gruppo_commento = escapeString($words[1]);

    // segue Responsabile
    nextWords();
    if (!checkWord('Responsabile')) {
        erroreDiImport("Responsabile non specificato per il gruppo $gruppo_nome");
        break;
    }
    $gruppo_responsabile_cognome = escapeString(titlecase($words[1]));
    $gruppo_responsabile_nome = escapeString(titlecase($words[2]));
    $query = "SELECT docente.id FROM docente WHERE docente.cognome LIKE '$gruppo_responsabile_cognome' COLLATE utf8_general_ci ";
    if (!empty($gruppo_responsabile_nome)) {
        $query .= " AND docente.nome LIKE '$gruppo_responsabile_nome' COLLATE utf8_general_ci ";
    }
    $gruppo_docente_id_list = dbGetAll($query);
    // controlla di avere trovato almeno un docente
    if (count($gruppo_docente_id_list) == 0) {
        erroreDiImport("docente non trovato cognome=$gruppo_responsabile_cognome nome=$gruppo_responsabile_nome");
        break;
    }
    // controlla che non ce ne siano piu' di uno
    if (count($gruppo_docente_id_list) > 1) {
        $messaggio = "piu' docenti corrispondono alla ricerca cognome=$gruppo_responsabile_cognome nome=$gruppo_responsabile_nome: utilizzato il primo";
        warning("Errore di import linea $linePos: " . $messaggio);
        $data = $data . "<strong>Errore di import linea $linePos:</strong> " . $messaggio;
    }
    // se tutto va bene c'e' un solo valore
    $gruppo_responsabile_docente_id = $gruppo_docente_id_list[0]['id'];

    // segue max_ore
    nextWords();
    if (!checkWord('max_ore')) {
        erroreDiImport("max_ore non presente (nemmeno vuoto) per il gruppo $gruppo_nome");
        break;
    }
    $gruppo_max_ore = escapeString($words[1]);

    $sql .= "INSERT INTO gruppo (nome, dipartimento, commento, max_ore, anno_scolastico_id, responsabile_docente_id) VALUES ('$gruppo_nome', 0, '$gruppo_commento', '$gruppo_max_ore', '$__anno_scolastico_corrente_id', '$gruppo_responsabile_docente_id');
        SET @last_id_gruppo = LAST_INSERT_ID();
        ";

    // segue Partecipanti
    nextWords();
    // numero ore totali
    if (!checkWord('Partecipanti')) {
        erroreDiImport("Partecipanti non specificate per il gruppo $gruppo_nome");
        break;
    }
    while($words[0] == 'Partecipanti' || empty(trim($words[0]))) {
        $gruppo_partecipante_cognome = escapeString(titlecase($words[1]));
        $gruppo_partecipante_nome = escapeString(titlecase($words[2]));
        $query = "SELECT docente.id FROM docente WHERE docente.cognome LIKE '$gruppo_partecipante_cognome' COLLATE utf8_general_ci ";
        if (!empty($gruppo_partecipante_nome)) {
            $query .= " AND docente.nome LIKE '$gruppo_partecipante_nome' COLLATE utf8_general_ci ";
        }
        $gruppo_partecipante_id_list = dbGetAll($query);
        // controlla di avere trovato almeno un partecipante
        if (count($gruppo_partecipante_id_list) == 0) {
            erroreDiImport("partecipante non trovato cognome=$gruppo_partecipante_cognome nome=$gruppo_partecipante_nome");
            break;
        }
        // controlla che non ce ne siano piu' di uno
        if (count($gruppo_partecipante_id_list) > 1) {
            $messaggio = "piu' docenti corrispondono alla ricerca cognome=$gruppo_partecipante_cognome nome=$gruppo_partecipante_nome: utilizzato il primo";
            warning("Errore di import linea $linePos: " . $messaggio);
            $data = $data . "<strong>Errore di import linea $linePos:</strong> " . $messaggio;
        }
        // se tutto va bene c'e' un solo valore
        $gruppo_partecipante_docente_id = $gruppo_partecipante_id_list[0]['id'];

        $sql .= "INSERT INTO gruppo_partecipante (gruppo_id, docente_id) VALUES(@last_id_gruppo, $gruppo_partecipante_docente_id);
            ";

        // se aveva raggiunto la fine delle linee, interrompi
        if ($completato) {
            break;
        }
        nextWords();
        if ($linePos >= $numberOfLines) {
            $completato = true;
        }
    }

    $data = $data . 'Gruppo=' . $gruppo_nome . ' commento=' . $gruppo_commento . ' ore=' . $gruppo_max_ore . ' responsabile=' . $gruppo_responsabile_cognome . " " . $gruppo_responsabile_nome;
    $data .= '</br>';
    debug('Gruppo=' . $gruppo_nome . ' commento=' . $gruppo_commento . ' ore=' . $gruppo_max_ore . ' responsabilente=' . $gruppo_responsabile_cognome . " " . $gruppo_responsabile_nome);
}

// esegue la query se non vuota
if (!empty($sql)) {
    // debug($sql);
    dbExecMulti($sql);
    info('Import gruppi effettuato');
}

echo $data;
?>
