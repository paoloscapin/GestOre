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
    $sqlList = array();
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
    if (count($words) < 7) {
        erroreDiImport("numero di argomenti errato (" . count($words) . ")");
        break;
    }
    $categoria = getWordContent($words, 0);
    $dataCsv = getWordContent($words,1 );
    $ora = getWordContent($words, 2);
    $docente_cognome = getWordContent($words, 3);
    $docente_nome = getWordContent($words, 4);
    $materia = getWordContent($words, 5);
    $numero_ore = getWordContent($words, 6);
    $luogo = getWordContent($words, 7);
    $classe = getWordContent($words, 8);
    $max_iscrizioni = getWordContent($words, 9);
    $argomento_opzionale = getWordContent($words, 10);
    $online = getWordContent($words, 11);
    $clil = getWordContent($words, 12);
    $orientamento = getWordContent($words, 13);

    // data
    $data = DateTime::createFromFormat('d/m/Y', $dataCsv)->format('Y-m-d');

    // docente
    $query = "SELECT docente.id FROM docente WHERE docente.cognome LIKE '$docente_cognome' COLLATE utf8_general_ci ";
    if (!empty($docente_nome)) {
        $query .= " AND docente.nome LIKE '$docente_nome' COLLATE utf8_general_ci ";
    }
    $query .=" AND docente.attivo = true ";
    $docente_id_list = dbGetAll($query);
    // controlla di avere trovato almeno un docente
    if (count($docente_id_list) == 0) {
        erroreDiImport("docente non trovato cognome=$docente_cognome nome=$docente_nome");
        break;
    }
    // controlla che non ce ne siano piu' di uno
    if (count($docente_id_list) > 1) {
        erroreDiImport("piu' docenti corrispondono alla ricerca cognome=$docente_cognome nome=$docente_nome");
        break;
    }
    // se tutto va bene c'e' un solo valore
    $docente_id = $docente_id_list[0]['id'];

    // materia
    $materia_id = dbGetValue("SELECT materia.id FROM materia WHERE materia.nome = '$materia'");
    if ($materia_id == null) {
        erroreDiImport("materia non trovata nome=$materia");
        break;
    }

    // categoria
    $categoria_id = dbGetValue("SELECT sportello_categoria.id FROM sportello_categoria WHERE sportello_categoria.nome = '$categoria'");
    if ($categoria_id == null) {
        erroreDiImport("categoria non trovata nome=$categoria");
        break;
    }

    // classe
    $classe_id = dbGetValue("SELECT classe.id FROM classe WHERE classe.nome = '$classe'");
    if ($classe_id == null) {
        erroreDiImport("classe non trovata nome=$classe");
        break;
    }

    // max iscrizioni (potrebbe usare il default se non presente)
    if (empty($max_iscrizioni)) {
        $max_iscrizioni = getSettingsValue("sportelli", "numero_max_prenotazioni", 8);
    }

    // online oppure no?
    $online_value = 0;
    if ($online == 'si') {
        $online_value = 1;
    }

    // clil oppure no?
    $clil_value = 0;
    if ($clil == 'si') {
        $clil_value = 1;
    }

    // orientamento oppure no?
    $orientamento_value = 0;
    if ($orientamento == 'si') {
        $orientamento_value = 1;
    }

    if (!$__settings->sportelli->import_sportelli_docente_stesso_orario) //leggo impostazione file JSON
    {
        // prima di accettare controlla che il docente non abbia già uno sportello a quell'ora
        $sportelloPrecedente = dbGetFirst("SELECT * FROM sportello WHERE docente_id = $docente_id AND data = '$data' AND ora = '$ora' AND anno_scolastico_id=$__anno_scolastico_corrente_id;");
        if ($sportelloPrecedente != null) {
            erroreDiImport("il docente  $docente_cognome $docente_nome ha già uno sportello per il $data alle $ora");
            continue;
        }
    }
    $insertSportelloSql = "INSERT INTO sportello(categoria, data, ora, docente_id, materia_id, classe_id, numero_ore, max_iscrizioni, argomento, luogo, classe, online, clil, orientamento, anno_scolastico_id) VALUES('$categoria', '$data', '$ora', '$docente_id', '$materia_id', '$classe_id', '$numero_ore', '$max_iscrizioni', '$argomento_opzionale', '$luogo', '$classe', '$online_value', '$clil_value', '$orientamento_value', $__anno_scolastico_corrente_id); ";
    info("SPORTELLO SQL : " . $insertSportelloSql);
    $sqlList[] = $insertSportelloSql;
    $sportelli++ ;
}

// esegue la query se non vuota
if (!empty($sqlList)) {
    foreach($sqlList as $sql) {
        dbExec($sql);
        // debug($sql);
    }
    info('Import effettuato: inseriti ' . $sportelli . ' nuovi sportelli');
}

if ($terminatoCorrettamente) {
    echo '<strong>Import effettuato: inseriti ' . $sportelli . ' nuovi sportelli</strong>';
} else {
    echo $dataHtml;
}
?>
