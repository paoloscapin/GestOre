<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
require_once '../common/checkSession.php';
ruoloRichiesto('dirigente', 'segreteria-didattica');

function startsWith($haystack, $needle)
{
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

$terminatoCorrettamente = true;

function erroreDiImport($messaggio)
{
    global $dataHtml;
    global $linePos;
    global $sqlList;
    global $terminatoCorrettamente;

    $terminatoCorrettamente = false;
    warning("Errore di import linea $linePos: " . $messaggio);
    $dataHtml = $dataHtml . "<strong>Errore di import linea $linePos:</strong> " . $messaggio . '<br>';

    // azzera le istruzioni sql
    $sqlList = array();
}

function getWordContent($words, $pos)
{
    if (count($words) < $pos) {
        return '';
    }
    return escapeString($words[$pos]);
}

// setup del src e del risultato (dataHtml) e delle istruzioni (sql[])
$dataHtml = '';
$sqlList = array();

$src = '';
if (isset($_POST)) {
    $src = trim($_POST['contenuto']);
    $lista_sportelli = $_POST['lista_sportelli'];
}
$lines_array = explode("\n", $src);
$lines = array_filter($lines_array, 'trim');
$linePos = 0;

// contatore import
$iscrizioni = 0;

// scorre tutte le linee del csv
foreach ($lines as $line) {
    $linePos++;

    // scompone i csv
    $words = str_getcsv($line);
    if (startswith($line, "#") || empty($line) || startswith($line, ",")) {
        debug('Skip line: ' . $line);
        continue;
    }

    // trim delle parole
    $words = array_map('trim', $words);

    // ci devono essere nome,cognome,email,classe,anno
    if (count($words) < 7) {
        erroreDiImport("numero di argomenti errato (" . count($words) . ")" . $line);
        break;
    }
    $iscritto = getWordContent($words, 0);
    $presente = getWordContent($words, 1);
    $argomento = getWordContent($words, 2);
    $note = getWordContent($words, 3);
    $studente_nome = getWordContent($words, 4);
    $studente_cognome = getWordContent($words, 5);
    $studente = getWordContent($words, 6);

    // docente
    $query = "SELECT studente.id FROM studente WHERE studente.cognome LIKE '$studente_cognome' COLLATE utf8_general_ci ";
    if (!empty($studente_nome)) {
        $query .= " AND studente.nome LIKE '$studente_nome' COLLATE utf8_general_ci ";
    }

    $studente_id_list = dbGetAll($query);
    // controlla di avere trovato almeno un docente
    if (count($studente_id_list) == 0) {
        erroreDiImport("studente non trovato cognome=$studente_cognome nome=$studente_nome");
        break;
    }
    // controlla che non ce ne siano piu' di uno
    if (count($studente_id_list) > 1) {
        erroreDiImport("piu' studenti corrispondono alla ricerca cognome=$studente_cognome nome=$studente_nome");
        break;
    }
    // se tutto va bene c'e' un solo valore
    $studente_id = $studente_id_list[0]['id'];

    foreach ($lista_sportelli as $sportello_id) {
        // prima di accettare controlla che lo studente non sia già iscritto a questa attività
        $sportelloPrecedente = dbGetFirst("SELECT * FROM sportello_studente WHERE sportello_studente.studente_id = $studente_id AND sportello_studente.sportello_id = $sportello_id;");
        if ($sportelloPrecedente != null) {
            erroreDiImport("lo studente  $studente_cognome $studente_nome è già iscritto a questa attività");
            continue;
        }
        $insertSportelloSql = "INSERT INTO sportello_studente(iscritto, presente, argomento, note, sportello_id, studente_id) VALUES('$iscritto', '$presente', '$argomento', '$note', '$sportello_id', '$studente_id'); ";
        info("ISCRIZIONE STUDENTE SQL : " . $insertSportelloSql);
        $sqlList[] = $insertSportelloSql;
        $iscrizioni++;
    }
}

// esegue la query se non vuota
if (!empty($sqlList)) {
    foreach ($sqlList as $sql) {
        dbExec($sql);
        // debug($sql);
    }
    info('Import effettuato: inserite ' . $iscrizioni . ' nuove iscrizioni');
}

if ($terminatoCorrettamente) {
    echo '<strong>Import effettuato: inserite ' . $iscrizioni . ' nuove iscrizioni</strong>';
} else {
    echo $dataHtml;
}
?>