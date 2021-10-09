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

// contatori
$sportelli = 0;

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
    $categoria = escapeString($words[0]);
    $dataCsv = escapeString($words[1]);
    $ora = escapeString($words[2]);
    $docente_cognome = escapeString($words[3]);
    $docente_nome = escapeString($words[4]);
    $materia = escapeString($words[5]);
    $numero_ore = escapeString($words[6]);
    $luogo = escapeString($words[7]);
    $classe = escapeString($words[8]);
    $max_iscrizioni = escapeString($words[9]);
    $argomento_opzionale = escapeString($words[10]);
    $online = escapeString($words[11]);

    // data
    $data = DateTime::createFromFormat('d/m/Y', $dataCsv)->format('Y-m-d');

    // docente
    $query = "SELECT docente.id FROM docente WHERE docente.cognome LIKE '$docente_cognome' COLLATE utf8_general_ci ";
    if (!empty($docente_nome)) {
        $query .= " AND docente.nome LIKE '$docente_nome' COLLATE utf8_general_ci ";
    }
    $docente_id_list = dbGetAll($query);
    // controlla di avere trovato almeno un docente
    if (count($docente_id_list) == 0) {
        erroreDiImport("docente non trovato cognome=$docente_cognome nome=$docente_nome");
        break;
    }
    // controlla che non ce ne siano piu' di uno
    if (count($docente_id_list) > 1) {
        $messaggio = "piu' docenti corrispondono alla ricerca cognome=$docente_cognome nome=$docente_nome: utilizzato il primo";
        warning("Errore di import linea $linePos: " . $messaggio);
        $data = $data . "<strong>Errore di import linea $linePos:</strong> " . $messaggio;
    }
    // se tutto va bene c'e' un solo valore
    $docente_id = $docente_id_list[0]['id'];

    // materia
    $materia_id = dbGetValue("SELECT materia.id FROM materia WHERE materia.nome = '$materia'");
    if ($materia_id == null) {
        erroreDiImport("materia non trovata nome=$materia");
        break;
    }

    // max iscrizioni (potrebbe usare il default se non presente)
    if ($max_iscrizioni == null) {
        $max_iscrizioni = getSettingsValue("sportelli", "numero_max_prenotazioni", 8);
    }

    // online oppure no?
    $online_value = 0;
    if ($online == 'si') {
        $online_value = 1;
    }

    // prima di accettare controlla che il docente non abbia già uno sportello a quell'ora
    $sportelloPrecedente = dbGetFirst("SELECT * FROM sportello WHERE docente_id = $docente_id AND data = '$data' AND ora = '$ora' AND anno_scolastico_id=$__anno_scolastico_corrente_id;");
    if ($sportelloPrecedente != null) {
        erroreDiImport("il docente  $docente_cognome $docente_nome ha già uno sportello per il $data alle $ora");
        break;
    }
    $insertSportelloSql = "INSERT INTO sportello(data, ora, docente_id, materia_id, numero_ore, max_iscrizioni, argomento, luogo, classe, online, anno_scolastico_id) VALUES('$data', '$ora', '$docente_id', '$materia_id', '$numero_ore', '$max_iscrizioni', '$argomento_opzionale', '$luogo', '$classe', '$online_value', $__anno_scolastico_corrente_id); ";
    $sqlList[] = $insertSportelloSql;
}

// esegue la query se non vuota
if (!empty($sqlList)) {
    foreach($sqlList as $sql) {
        dbExec($sql);
        // debug($sql);
    }
    info('Import sportelli effettuato: ' . $data);
}

echo '<strong>' . $data .'</strong>';
?>
