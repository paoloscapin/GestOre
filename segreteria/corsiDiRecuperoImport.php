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

// cerca la didattica per controllare a chi assegnare i corsi senza docente
$didattica_id = dbGetValue("SELECT id FROM docente WHERE username = 'didattica';");

// prima riga: l'anno
$words = nextWords();
checkWord('ANNO');
$anno = $words[1];
// debug('anno=' . $anno);

// si posiziona sul primo codice
while ($words[0] != 'CODICE') {
    $words = nextWords();
}

while ($words[0] == 'CODICE') {
    if (!checkWord('CODICE')) {
        break;
    }
    $corso_codice = $words[1];
//    debug("codice=" . $codice);

    // segue materia
    nextWords();
    if (!checkWord('Materia')) {
        erroreDiImport("Materia non specificata per il corso $corso_codice");
        break;
    }
    $corso_materia = escapeString($words[1]);
    // se vuoto, lo ignora per ora
    if (empty($corso_materia)) {
        $corso_materia_id = null;
    } else {
        $corso_materia_id = dbGetValue("SELECT materia.id FROM materia WHERE materia.nome = '$corso_materia'");
        if ($corso_materia_id == null) {
            erroreDiImport("materia non trovata nome=$corso_materia");
            break;
        }
    }

    // segue Aula
    nextWords();
    if (!checkWord('Aula')) {
        erroreDiImport("Aula non specificata per il corso $corso_codice");
        break;
    }
    $corso_aula = escapeString($words[1]);

    // segue Docente
    nextWords();
    if (!checkWord('Docente')) {
        erroreDiImport("Docente non specificato per il corso $corso_codice");
        break;
    }
    $corso_docente_cognome = escapeString(titlecase($words[1]));
    $corso_docente_nome = escapeString(titlecase($words[2]));
    // se vuoto, lo assegna alla didattica (deve esserci un docente)
    if (empty($corso_docente_cognome)) {
        $corso_docente_id = $didattica_id;

        $messaggio = "docente non assegnato per il corso $corso_codice: utilizzato docente 'didattica'";
        warning("Linea $linePos: " . $messaggio);
        $data = $data . "<strong>Warning linea $linePos:</strong> " . $messaggio;
} else {
        $query = "SELECT docente.id FROM docente WHERE docente.cognome LIKE '$corso_docente_cognome' COLLATE utf8_general_ci ";
        if (!empty($corso_docente_nome)) {
            $query .= " AND docente.nome LIKE '$corso_docente_nome' COLLATE utf8_general_ci ";
        }
        $corso_docente_id_list = dbGetAll($query);
        // controlla di avere trovato almeno un docente
        if (count($corso_docente_id_list) == 0) {
            erroreDiImport("docente non trovato cognome=$corso_docente_cognome nome=$corso_docente_nome");
            break;
        }
        // controlla che non ce ne siano piu' di uno
        if (count($corso_docente_id_list) > 1) {
            $messaggio = "piu' docenti corrispondono alla ricerca cognome=$corso_docente_cognome nome=$corso_docente_nome: utilizzato il primo";
            warning("Errore di import linea $linePos: " . $messaggio);
            $data = $data . "<strong>Errore di import linea $linePos:</strong> " . $messaggio;
        }
        // se tutto va bene c'e' un solo valore
        $corso_docente_id = $corso_docente_id_list[0]['id'];
    }

    $sql .= "INSERT INTO corso_di_recupero (codice, aula, docente_id, anno_scolastico_id, materia_id) VALUES ('$corso_codice', '$corso_aula', '$corso_docente_id', '$__anno_scolastico_corrente_id', '$corso_materia_id');
        SET @last_id_corso_di_recupero = LAST_INSERT_ID();
        ";

    // segue Lezioni
    nextWords();
    // numero ore totali
    $numero_ore_recupero = 0;
    if (!checkWord('Lezioni')) {
        erroreDiImport("Lezioni non specificate per il corso $corso_codice");
        break;
    }
    while($words[0] == 'Lezioni' || empty(trim($words[0]))) {
        $lezioni_data = $words[1];
        $lezioni_inizio = $words[2];
        $lezioni_fine = $words[3];
        // prende solo il numero del giorno dal primo campo e lo usa per settembre
        // $numeroGiorno = (int) filter_var($lezioni_data, FILTER_SANITIZE_NUMBER_INT);
        // $dateMySql = $anno."-09-".sprintf('%02d', $numeroGiorno);
        $oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
        $dataLezione = DateTime::createFromFormat('d/m/Y', $lezioni_data);
        if ($dataLezione == null) {
            erroreDiImport("data non riconosciuta (formato richiesto=d/m/Y): " . $lezioni_data);
            break;
        }
        $dataLezioneSql = $dataLezione->format('Y-m-d');
        setlocale(LC_TIME, $oldLocale);

        // prende l'ora di inizio dal secondo campo
        $timeStart = strtotime ($lezioni_inizio);
        $timeEnd = strtotime ($lezioni_fine);
        // ora di inizio
        $inizia_alle = date("H:i:s", $timeStart);
        // durata (in ore da 50 minuti)
        $numero_ore = ($timeEnd - $timeStart) / (50 * 60);
        $numero_ore_recupero += 2;
        // orario in formato stringa
        $orario = date("H:i", $timeStart) . " - " . date("H:i", $timeEnd);

        $sql .= "INSERT INTO lezione_corso_di_recupero (data, inizia_alle, numero_ore, orario, corso_di_recupero_id) VALUES ('$dataLezioneSql', '$inizia_alle', $numero_ore, '$orario', @last_id_corso_di_recupero);
            ";
        nextWords();
    }

    // aggiorna le ore totali
    $sql .= "UPDATE corso_di_recupero SET numero_ore=$numero_ore_recupero WHERE corso_di_recupero.id=@last_id_corso_di_recupero;
        ";

    // segue Studenti
    // numero studenti totali
    $numero_studenti = 0;
    if (!checkWord('Studenti')) {
        erroreDiImport("Studenti non specificati per il corso $corso_codice");
        break;
    }
    while($words[0] == 'Studenti' || empty(trim($words[0]))) {
        $classe = escapeString($words[1]);
        $nome_cognome = titlecase($words[2]);
        $arr = explode(' ', $nome_cognome);
        $cognome = escapeString($arr[0]);
        $nome = escapeString(implode(' ', array_slice($arr, 1)));
        $numero_studenti++;

        // inserisce lo studente se non esiste
		$sql .= "INSERT INTO studente_per_corso_di_recupero (cognome, nome, classe, corso_di_recupero_id) VALUES ('$cognome', '$nome', '$classe', @last_id_corso_di_recupero);
            ";

        nextWords();
        if ($linePos >= $numberOfLines) {
            $completato = true;
            break;
        }
    }
	// studente_partecipa_lezione_corso_di_recupero
    $sql .= "INSERT INTO studente_partecipa_lezione_corso_di_recupero (lezione_corso_di_recupero_id, studente_per_corso_di_recupero_id)
                    SELECT lezione_corso_di_recupero.id, studente_per_corso_di_recupero.id FROM lezione_corso_di_recupero, studente_per_corso_di_recupero
                    WHERE lezione_corso_di_recupero.corso_di_recupero_id = @last_id_corso_di_recupero AND studente_per_corso_di_recupero.corso_di_recupero_id = @last_id_corso_di_recupero;
        
                    ";

    $data = $data . 'codice=' . $corso_codice . ' materia=' . $corso_materia . ' aula=' . $corso_aula . ' docente=' . $corso_docente_cognome . " " . $corso_docente_nome . ' numero ore=' . $numero_ore_recupero . ' numero studenti=' . $numero_studenti;
    $data .= '</br>';
    debug('codice=' . $corso_codice . ' materia=' . $corso_materia . ' aula=' . $corso_aula . ' docente=' . $corso_docente_cognome . " " . $corso_docente_nome);
}

// esegue la query se non vuota
if (!empty($sql)) {
    dbExecMulti($sql);
    info('Import corsi di recupero effettuato');
}

echo $data;
?>
