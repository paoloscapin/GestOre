<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
require_once '../common/checkSession.php';
require_once '../common/__Util.php';
ruoloRichiesto('dirigente', 'segreteria-didattica');

function startsWith($haystack, $needle)
{
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

function erroreDiImport($messaggio)
{
    global $data;
    global $linePos;

    warning("Errore di import linea $linePos: " . $messaggio);
    $data = $data . "<strong>Errore di import linea $linePos:</strong> " . $messaggio . "<br>";
}

// setup del src e del risultato (data) 
$data = '';

$src = '';
if (isset($_POST)) {
    $src = trim($_POST['contenuto']);
}
$lines_array = explode("\n", $src);
$lines = array_filter($lines_array, 'trim');
$linePos = 0;

// la prima istruzione sql disattiva tutti i genitori
// in modo da poterli riattivare se sono presenti nel csv
$query = "UPDATE genitori SET attivo='0'";
dbExec($query);

// contatori
$daInserire = 0;
$daModificare = 0;

// ricavo gli id attuali delle relazioni
$query = "SELECT id FROM genitori_relazioni WHERE relazione = 'padre'";
$id_rel_padre = dbGetValue($query);
$query = "SELECT id FROM genitori_relazioni WHERE relazione = 'madre'";
$id_rel_madre = dbGetValue($query);
$query = "SELECT id FROM genitori_relazioni WHERE relazione = 'tutore'";
$id_rel_tutore = dbGetValue($query);
$query = "SELECT id FROM genitori_relazioni WHERE relazione = 'affidatario'";
$id_rel_affidatario = dbGetValue($query);
$query = "SELECT id FROM genitori_relazioni WHERE relazione = 'affidataria'";
$id_rel_affidataria = dbGetValue($query);

// scorre tutte le linee del csv
foreach ($lines as $line) {
    $linePos++;

    // scompone i csv
    $words = str_getcsv($line);
    if (startswith($line, "#") || empty($line)) {
        debug('Skip line: ' . $line);
        continue;
    }

    // trim delle parole
    $words = array_map('trim', $words);
    // campi MasterCom -> STAMPA - B03 - tutte le classi 
    // STUDENTE
    // cognome; nome; email; username; codice_fiscale; attivo; classe;
    // PADRE
    // cognome; nome; codice_fiscale; email; username;
    // MADRE
    // cognome; nome; codice_fiscale; email; username;
    // TUTORE
    // cognome; nome; codice_fiscale; email; username;
    // AFFIDATARIO
    // cognome; nome; codice_fiscale; email; username;
    // AFFIDATARIA
    // cognome; nome; codice_fiscale; email; username;

    // ho già fatto importo elenco studenti da mastercom + email di dominio
    // STUDENTE
    // cognome; nome; email; username; codice_fiscale; attivo; classe;

    if (count($words) < 32) {
        erroreDiImport("numero di argomenti errato (" . count($words) . ")");
        break;
    }

    // per abbinare genitore a studente uso la mail di dominio
    $email_studente = escapeString($words[2]);
    if ($email_studente == '') {
        erroreDiImport("Riga vuota od incompleta");
        continue;
    }
    // controlla se l'indirizzo di email e' gia' presente nel database
    $id_studente = dbGetValue("SELECT id FROM studente WHERE email = '$email_studente' AND attivo='1';");
    if ($id_studente == null) {
        erroreDiImport("Errore import per studente $email_studente. Studente non attivo, email errata oppure genitore non associato a studente.");
        continue;
    }

    // PADRE
    $cognome = escapeString($words[7]);
    $nome = escapeString($words[8]);
    $codice_fiscale = escapeString($words[9]);
    $email = escapeString($words[10]);
    $username = escapeString($words[11]);

    if ($cognome == '' || $nome == '' || $codice_fiscale == '' || $email == '' || $username == '') {
        continue;
    }
    // controlla se il padre e' gia' presente nel database
    $id_padre = dbGetValue("SELECT id FROM genitori WHERE codice_fiscale = '$codice_fiscale'");
    if ($id_padre == null) {
        // se non lo trova, lo deve inserire
        $daInserire++;
        $query = "INSERT INTO genitori (cognome,nome,email,username,codice_fiscale,attivo,last_login,last_IP) VALUES ('$cognome','$nome','$email','$username','$codice_fiscale','1','','');";
        dbExec($query);
        $id_padre = dblastId();
        $query = "INSERT INTO genitori_studenti(id_studente,id_genitore,id_relazione) VALUES('$id_studente', '$id_padre', '$id_rel_padre')";
        dbExec($query);
        info("aggiunto genitore id=$id_padre cognome=$cognome nome=$nome email=$email id_studente=$id_studente id_relazione=$id_rel_padre");
    } else {
        $query = "SELECT id FROM genitori_studenti WHERE id_studente = '$id_studente' AND id_genitore = '$id_padre'";
        $id_genitore_studente = dbGetValue($query);
        if ($id_genitore_studente == null) {
            // se non lo trova, lo deve inserire
            $daInserire++;
            $query = "INSERT INTO genitori_studenti(id_studente,id_genitore,id_relazione) VALUES('$id_studente', '$id_padre', '$id_rel_padre')";
            dbExec($query);
            info("aggiunto genitore id=$id_padre cognome=$cognome nome=$nome email=$email id_studente=$id_studente id_relazione=$id_rel_padre");
        } else {
            // se c'era gia', lo aggiorna con i valori trovati della classe
            $daModificare++;
            $query = "UPDATE genitori_studenti SET id_relazione='$id_rel_padre' WHERE id_studente = '$id_studente' AND id_genitore = '$id_padre'";
            dbExec($query);
            info("aggiornato genitore id=$id_padre cognome=$cognome nome=$nome email=$email id_studente=$id_studente id_relazione=$id_rel_padre");
        }
        $query = "UPDATE genitori SET attivo='1' WHERE id='$id_padre'";
        dbExec($query);
    }

    // MADRE
    $cognome = escapeString($words[12]);
    $nome = escapeString($words[13]);
    $codice_fiscale = escapeString($words[14]);
    $email = escapeString($words[15]);
    $username = escapeString($words[16]);

    if ($cognome == '' || $nome == '' || $codice_fiscale == '' || $email == '' || $username == '') {
        continue;
    }
    // controlla se la madre e' gia' presente nel database
    $id_madre = dbGetValue("SELECT id FROM genitori WHERE codice_fiscale = '$codice_fiscale'");
    if ($id_madre == null) {
        // se non lo trova, lo deve inserire
        $daInserire++;
        $query = "INSERT INTO genitori (cognome,nome,email,username,codice_fiscale,attivo,last_login,last_IP) VALUES ('$cognome','$nome','$email','$username','$codice_fiscale','1','','');";
        dbExec($query);
        $id_madre = dblastId();
        $query = "INSERT INTO genitori_studenti(id_studente,id_genitore,id_relazione) VALUES('$id_studente', '$id_madre', '$id_rel_madre')";
        dbExec($query);
        info("aggiunto genitore id=$id_madre cognome=$cognome nome=$nome email=$email id_studente=$id_studente id_relazione=$id_rel_madre");
    } else {
        $query = "SELECT id FROM genitori_studenti WHERE id_studente = '$id_studente' AND id_genitore = '$id_madre'";
        $id_genitore_studente = dbGetValue($query);
        if ($id_genitore_studente == null) {
            // se non lo trova, lo deve inserire
            $daInserire++;
            $query = "INSERT INTO genitori_studenti(id_studente,id_genitore,id_relazione) VALUES('$id_studente', '$id_madre', '$id_rel_madre')";
            dbExec($query);
            info("aggiunto genitore id=$id_madre cognome=$cognome nome=$nome email=$email id_studente=$id_studente id_relazione=$id_rel_madre");
        } else {
            // se c'era gia', lo aggiorna con i valori trovati della classe
            $daModificare++;
            $query = "UPDATE genitori_studenti SET id_relazione='$id_rel_madre' WHERE id_studente = '$id_studente' AND id_genitore = '$id_madre'";
            dbExec($query);
            info("aggiornato genitore id=$id_madre cognome=$cognome nome=$nome email=$email id_studente=$id_studente id_relazione=$id_rel_madre");
        }
        $query = "UPDATE genitori SET attivo='1' WHERE id='$id_madre'";
        dbExec($query);
    }

    // TUTORE
    $cognome = escapeString($words[17]);
    $nome = escapeString($words[18]);
    $codice_fiscale = escapeString($words[19]);
    $email = escapeString($words[20]);
    $username = escapeString($words[21]);

    if ($cognome == '' || $nome == '' || $codice_fiscale == '' || $email == '' || $username == '') {
        continue;
    }
    // controlla se il tutore e' gia' presente nel database
    $id_tutore = dbGetValue("SELECT id FROM genitori WHERE codice_fiscale = '$codice_fiscale'");
    if ($id_tutore == null) {
        // se non lo trova, lo deve inserire
        $daInserire++;
        $query = "INSERT INTO genitori (cognome,nome,email,username,codice_fiscale,attivo,last_login,last_IP) VALUES ('$cognome','$nome','$email','$username','$codice_fiscale','1','','');";
        dbExec($query);
        $id_tutore = dblastId();
        $query = "INSERT INTO genitori_studenti(id_studente,id_genitore,id_relazione) VALUES('$id_studente', '$id_tutore', '$id_rel_tutore')";
        dbExec($query);
        info("aggiunto genitore id=$id_tutore cognome=$cognome nome=$nome email=$email id_studente=$id_studente id_relazione=$id_rel_tutore");
    } else {
        $query = "SELECT id FROM genitori_studenti WHERE id_studente = '$id_studente' AND id_genitore = '$id_tutore'";
        $id_genitore_studente = dbGetValue($query);
        if ($id_genitore_studente == null) {
            // se non lo trova, lo deve inserire
            $daInserire++;
            $query = "INSERT INTO genitori_studenti(id_studente,id_genitore,id_relazione) VALUES('$id_studente', '$id_tutore', '$id_rel_tutore')";
            dbExec($query);
            info("aggiunto genitore id=$id_tutore cognome=$cognome nome=$nome email=$email id_studente=$id_studente id_relazione=$id_rel_tutore");
        } else {
            // se c'era gia', lo aggiorna con i valori trovati della classe
            $daModificare++;
            $query = "UPDATE genitori_studenti SET id_relazione='$id_rel_tutore' WHERE id_studente = '$id_studente' AND id_genitore = '$id_tutore'";
            dbExec($query);
            info("aggiornato genitore id=$id_tutore cognome=$cognome nome=$nome email=$email id_studente=$id_studente id_relazione=$id_rel_tutore");
        }
        $query = "UPDATE genitori SET attivo='1' WHERE id='$id_tutore'";
        dbExec($query);
    }

    // AFFIDATARIO
    $cognome = escapeString($words[22]);
    $nome = escapeString($words[23]);
    $codice_fiscale = escapeString($words[24]);
    $email = escapeString($words[25]);
    $username = escapeString($words[26]);

    if ($cognome == '' || $nome == '' || $codice_fiscale == '' || $email == '' || $username == '') {
        continue;
    }
    // controlla se l'affidatario e' gia' presente nel database
    $id_affidatario = dbGetValue("SELECT id FROM genitori WHERE codice_fiscale = '$codice_fiscale'");
    if ($id_affidatario == null) {
        // se non lo trova, lo deve inserire
        $daInserire++;
        $query = "INSERT INTO genitori (cognome,nome,email,username,codice_fiscale,attivo,last_login,last_IP) VALUES ('$cognome','$nome','$email','$username','$codice_fiscale','1','','');";
        dbExec($query);
        $id_affidatario = dblastId();
        $query = "INSERT INTO genitori_studenti(id_studente,id_genitore,id_relazione) VALUES('$id_studente', '$id_affidatario', '$id_rel_affidatario')";
        dbExec($query);
        info("aggiunto genitore id=$id_affidatario cognome=$cognome nome=$nome email=$email id_studente=$id_studente id_relazione=$id_rel_affidatario");
    } else {
        $query = "SELECT id FROM genitori_studenti WHERE id_studente = '$id_studente' AND id_genitore = '$id_affidatario'";
        $id_genitore_studente = dbGetValue($query);
        if ($id_genitore_studente == null) {
            // se non lo trova, lo deve inserire
            $daInserire++;
            $query = "INSERT INTO genitori_studenti(id_studente,id_genitore,id_relazione) VALUES('$id_studente', '$id_affidatario', '$id_rel_affidatario')";
            dbExec($query);
            info("aggiunto genitore id=$id_affidatario cognome=$cognome nome=$nome email=$email id_studente=$id_studente id_relazione=$id_rel_affidatario");
        } else {
            // se c'era gia', lo aggiorna con i valori trovati della classe
            $daModificare++;
            $query = "UPDATE genitori_studenti SET id_relazione='$id_rel_affidatario' WHERE id_studente = '$id_studente' AND id_genitore = '$id_affidatario'";
            dbExec($query);
            info("aggiornato genitore id=$id_affidatario cognome=$cognome nome=$nome email=$email id_studente=$id_studente id_relazione=$id_rel_affidatario");
        }
        $query = "UPDATE genitori SET attivo='1' WHERE id='$id_affidatario'";
        dbExec($query);
    }

    // AFFIDATARIA
    $cognome = escapeString($words[27]);
    $nome = escapeString($words[28]);
    $codice_fiscale = escapeString($words[29]);
    $email = escapeString($words[30]);
    $username = escapeString($words[31]);

    if ($cognome == '' || $nome == '' || $codice_fiscale == '' || $email == '' || $username == '') {
        continue;
    }
    // controlla se l'affidataria e' gia' presente nel database
    $id_affidataria = dbGetValue("SELECT id FROM genitori WHERE codice_fiscale = '$codice_fiscale'");
    if ($id_affidataria == null) {
        // se non lo trova, lo deve inserire
        $daInserire++;
        $query = "INSERT INTO genitori (cognome,nome,email,username,codice_fiscale,attivo,last_login,last_IP) VALUES ('$cognome','$nome','$email','$username','$codice_fiscale','1','','');";
        dbExec($query);
        $id_affidataria = dblastId();
        $query = "INSERT INTO genitori_studenti(id_studente,id_genitore,id_relazione) VALUES('$id_studente', '$id_affidataria', '$id_rel_affidataria')";
        dbExec($query);
        info("aggiunto genitore id=$id_affidataria cognome=$cognome nome=$nome email=$email id_studente=$id_studente id_relazione=$id_rel_affidataria");
    } else {
        $query = "SELECT id FROM genitori_studenti WHERE id_studente = '$id_studente' AND id_genitore = '$id_affidataria'";
        $id_genitore_studente = dbGetValue($query);
        if ($id_genitore_studente == null) {
            // se non lo trova, lo deve inserire
            $daInserire++;
            $query = "INSERT INTO genitori_studenti(id_studente,id_genitore,id_relazione) VALUES('$id_studente', '$id_affidataria', '$id_rel_affidataria')";
            dbExec($query);
            info("aggiunto genitore id=$id_affidataria cognome=$cognome nome=$nome email=$email id_studente=$id_studente id_relazione=$id_rel_affidataria");
        } else {
            // se c'era gia', lo aggiorna con i valori trovati della classe
            $daModificare++;
            $query = "UPDATE genitori_studenti SET id_relazione='$id_rel_affidataria' WHERE id_studente = '$id_studente' AND id_genitore = '$id_affidataria'";
            dbExec($query);
            info("aggiornato genitore id=$id_affidataria cognome=$cognome nome=$nome email=$email id_studente=$id_studente id_relazione=$id_rel_affidataria");
        }
        $query = "UPDATE genitori SET attivo='1' WHERE id='$id_affidataria'";
        dbExec($query);
    }
}

$data = $data . '<br>Import genitori effettuato: nuovi=' . $daInserire . ' modificati=' . $daModificare;
debug($data);

info('Import studenti effettuato: ' . $data);

echo '<strong>' . $data . '</strong>';
