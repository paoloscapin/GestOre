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
ruoloRichiesto('dirigente', 'segreteria-didattica');

$terminatoCorrettamente = true;
$iscrizioni = 0;
$sqlList = array();

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

if (isset($_POST)) {

    $idstudente = $_POST['id_studente'];
    $lista_sportelli = $_POST['lista_sportelli']; 

    $dbstudente = dbGetFirst("SELECT * FROM studente WHERE studente.id=$idstudente");    
    $iscritto = 1;
    $presente = 0;
    $note = "";
    $studente_nome = $dbstudente["nome"];
    $studente_cognome = $dbstudente["cognome"];

    foreach ($lista_sportelli as $sportello_id) {
        $sportelloArgomento = dbGetFirst("SELECT * FROM sportello WHERE sportello.id = $sportello_id;");
        $argomento = $sportelloArgomento["argomento"];

        // prima di accettare controlla che lo studente non sia già iscritto a questa attività
        $sportelloPrecedente = dbGetFirst("SELECT * FROM sportello_studente WHERE sportello_studente.studente_id  = $idstudente AND sportello_studente.sportello_id = $sportello_id;");
        if ($sportelloPrecedente != null) {
            erroreDiImport("lo studente  $studente_cognome $studente_nome è già iscritto a questa attività");
            continue;
        }
        $insertSportelloSql = "INSERT INTO sportello_studente(iscritto, presente, argomento, note, sportello_id, studente_id) VALUES('$iscritto', '$presente', '$argomento', '$note', '$sportello_id', '$idstudente'); ";
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