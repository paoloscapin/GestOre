<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once 'buoniPastoCreateQuery.php';

$docente_id = $_GET["docente_id"];
$mese_da = $_GET["mese_da"];
$mese_a = $_GET["mese_a"];

$mesi_scolastici = [
    9  => 'settembre',
    10 => 'ottobre',
    11 => 'novembre',
    12 => 'dicembre',
    1  => 'gennaio',
    2  => 'febbraio',
    3  => 'marzo',
    4  => 'aprile',
    5  => 'maggio',
    6  => 'giugno',
    7  => 'luglio'
];
$parti = explode('/', $__anno_scolastico_corrente_anno);
$anno_da = ($mese_da >= 9) ? $parti[0] : $parti[1];
$anno_a = ($mese_a >= 9) ? $parti[0] : $parti[1];

// file per il download
$nomeFile = "attivita dal 1 " . $mesi_scolastici[$mese_da] . " " . $anno_da . " al 31 " . $mesi_scolastici[$mese_a] . " " . $anno_a . ".csv";

// Invia gli header HTTP per forzare il download del file CSV nel browser
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nomeFile . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Apri il puntatore di scrittura direttamente sul flusso di output del browser
$fp = fopen('php://output', 'w');

// BOM UTF-8 per far leggere correttamente le lettere accentate a Excel
fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));

// Scrivi l'intestazione delle colonne (opzionale, ma consigliata)
$intestazione = ['Tipo', 'Cognome', 'Nome', 'Data', 'Ora', 'Numero Ore', 'Dettaglio'];
fputcsv($fp, $intestazione, ';');

$query = buoniPastoCreateQuery($mese_da, $mese_a, 0);

foreach(dbGetAll($query) as $riga) {
    $dati_riga = [
        $riga['tipo'],
        $riga['cognomeDocente'],
        $riga['nomeDocente'],
        $riga['data_attivita'],
        $riga['ora'],
        $riga['numero_ore'],
        $riga['dettaglio']
    ];
    fputcsv($fp, $dati_riga, ';');
}

fclose($fp);
exit; // Interrompe l'esecuzione per evitare che sporcizia HTML venga stampata nel CSV
?>
