<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
require_once '../common/checkSession.php';
require_once '../common/__Util.php';
ruoloRichiesto('dirigente','segreteria-didattica');

function startsWith($haystack, $needle) {
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

function erroreDiImport($messaggio) {
    global $data;
    global $linePos;

    warning("Errore di import linea $linePos: " . $messaggio);
    $data = $data . "<strong>Errore di import linea $linePos:</strong> " . $messaggio;

    // azzera le istruzioni sql
    $sqlList = '';
}

// setup del src e del risultato (data) e delle istruzioni (sql[])
$data = '';

$src = '';
if(isset($_POST)) {
	$src = trim($_POST['contenuto']);
}
$lines_array = explode("\n", $src);
$lines = array_filter($lines_array, 'trim');
$linePos = 0;

// la prima istruzione sql disattiva tutti gli studenti
$query = "UPDATE studente SET attivo='0'";
dbExec($query);

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
    if (count($words) < 7 ) {
        erroreDiImport("numero di argomenti errato (" . count($words) . ")");
        break;
    }
    $cognome = camelCase(escapeString($words[0]));
    $nome = camelCase(escapeString($words[1]));
    $email = strtolower(escapeString($words[2]));
    $username = escapeString($words[3]);
    $codice_fiscale = escapeString($words[4]);
    $attivo = escapeString($words[5]);
    $classe = escapeString($words[6]);

    // controlla se l'indirizzo di email e' gia' presente sul database
    $id = dbGetValue("SELECT id FROM studente WHERE email = '$email';");
    $id_classe = dbGetValue("SELECT id FROM classi WHERE classe = '$classe';");
        if ($id_classe == null) {
            erroreDiImport("classe $classe non trovata");
            continue;
        }

    if ($id == null) {
        // se non lo trova, lo deve inserire
        $daInserire ++;
        $query = "INSERT INTO studente (cognome,nome,email,username,codice_fiscale,attivo) VALUES ('$cognome','$nome','$email','$username','$codice_fiscale','$attivo');";
        dbExec($query);
        $id = dblastId();
        $query = "INSERT INTO studente_frequenta(id_studente,id_anno_scolastico,id_classe) VALUES('$id', '$__anno_scolastico_corrente_id', '$id_classe')";
    } else {
        // se c'era gia', lo aggiorna con i valori trovati della classe
        $daModificare++;
        $query = "UPDATE studente SET cognome='$cognome', nome='$nome', username='$username', codice_fiscale='$codice_fiscale', email='$email', attivo='$attivo' WHERE id='$id'";
        dbExec($query);
        $query = "SELECT * from studente_frequenta WHERE id_studente = '$id' AND id_anno_scolastico = '$__anno_scolastico_corrente_id'";
        $result = dbGetFirst($query);
        if ($result == null) {
            $query = "INSERT INTO studente_frequenta(id_studente,id_anno_scolastico,id_classe) VALUES('$id', '$__anno_scolastico_corrente_id', '$id_classe')";
        } else {
            $query = "UPDATE studente_frequenta SET id_classe = '$id_classe' WHERE id_studente = '$id' AND id_anno_scolastico = '$__anno_scolastico_corrente_id'";
        }
        dbExec($query);
    }
}

if (empty($data)) {
    $data = 'nuovi studenti=' . $daInserire . ' modificati=' . $daModificare;
    debug($data);
}

    info('Import studenti effettuato: ' . $data);

echo '<strong>' . $data .'</strong>';
?>
