<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';

ruoloRichiesto('segreteria-didattica','dirigente');

$query = "	SELECT
					carenze.id AS carenza_id,
					carenze.id_studente AS carenza_id_studente,
					carenze.id_materia AS carenza_id_materia,
					carenze.id_classe AS carenza_id_classe,
					carenze.id_docente AS carenza_id_docente,
					carenze.id_anno_scolastico AS carenza_id_anno_scolastico,
					carenze.stato AS carenza_stato,
					carenze.data_inserimento AS carenza_inserimento,
					carenze.data_validazione AS carenza_validazione,
					carenze.data_invio AS carenza_invio,
					carenze.nota_docente AS carenza_nota,
					studente.cognome AS stud_cognome,
					studente.nome AS stud_nome,
					classi.classe AS classe,
					docente.id AS doc_id,
					docente.cognome AS doc_cognome,
					docente.nome AS doc_nome,
					materia.nome AS materia
				FROM carenze
				INNER JOIN docente docente
				ON carenze.id_docente = docente.id
				INNER JOIN studente studente
				ON carenze.id_studente = studente.id
				INNER JOIN materia materia
				ON carenze.id_materia = materia.id
				INNER JOIN classi classi
				ON carenze.id_classe = classi.id
				WHERE carenze.id_anno_scolastico=$__anno_scolastico_corrente_id AND carenze.stato='1'";

$resultArray = dbGetAll($query);
if ($resultArray == null) {
	$resultArray = [];
}


foreach ($resultArray as $row) {

$id_carenza = $row['carenza_id'];
info("invio la mail per la carenza id " . $id_carenza);
// Dati da inviare
$data = array(
    'id' => $id_carenza, // Assicurati che $id_carenza sia definito
    'print' => 1,
    'mail' => 1,
    'titolo' => 'Programma carenza formativa'
);

// Inizializza cURL
$ch = curl_init();

// Imposta l'URL della chiamata
curl_setopt($ch, CURLOPT_URL, 'stampaCarenza.php');

// Specifica che è una POST
curl_setopt($ch, CURLOPT_POST, true);

// Passa i dati in POST
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

// Ottieni la risposta
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Esegui la richiesta
$response = curl_exec($ch);

// Chiudi la connessione
curl_close($ch);

// Eventualmente, usa la risposta
if ($response != 'sent')
{
    info("errore invio carenza con id " . $id_carenza);
    echo 'errore';
    exit;
}

}

echo 'sent';

