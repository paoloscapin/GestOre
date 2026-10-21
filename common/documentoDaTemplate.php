<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/path.php';
require_once __DIR__ . '/__Session.php';
require_once __DIR__ . '/__Log.php';

function creaDocumento($codice, $sostituzioni) {
    $contenuto = dbGetValue("SELECT contenuto FROM template_documento WHERE codice=$codice;");
    if ($contenuto == null) {
        return '';
    }

    // Separiamo le chiavi (pattern) dai valori
    $pattern = array_keys($sostituzioni);
    $valori = array_values($sostituzioni);

    // Eseguiamo la sostituzione in un colpo solo
    $contenuto = str_replace($pattern, $valori, $contenuto);
    return contenuto;
}

function creaPagina($documento, $title, $print, $email) {
    $pagina = '';
    $pagina .= '<html><head><link rel="icon" href="'.$__application_base_path.'/ore-32.png" />';

    $pagina .= '<title>' .$title . '</title>';

    // load the css file into a string
    ob_start();
    include '../css/documento.css';
    $cssFileContent = ob_get_clean();

    // per il pdf trasformiamo Arial in Helvetica
    $cssFileContent = str_replace('Arial', 'Helvetica', $cssFileContent);

    $pagina .='<meta content="text/html; charset=UTF-8" http-equiv="content-type"><style>' . $cssFileContent . '</style>';

    // lo script non deve entrare nel pdf
    if (! $print && ! $email) {
        $pagina .='<script type="text/javascript">window.onload = (event) => {
            var printBtn = document.querySelector(".btn_print");
            printBtn.onclick = function(event) { event.preventDefault(); window.location.search += "&print=true"; } }; </script>';
    }

    // chiude l'intestazione
    $pagina .='</head><body>';

    // bottone di print solo se in visualizzazione
    if (! $print && ! $email) {
        $pagina .='<div class="text-center noprint" style="text-align: center;padding: 50px;">
            <button class="btn btn-orange4 btn-xs btn_print"><i class="icon-play"></i>&nbsp;Scarica il pdf</button>
            </div>';
    }

    // il resto deve entrare in entrambi i casi, pagina o pdf: copertina, consuntivo, poi tutto il resto
    $pagina .= $data;

    // chiude la pagina
    $pagina .= '</body></html>';

    return $pagina;
}

// mappa delle sostituzioni locali note (sovrescrivibili)
$sostituzioniLocali = [];

// dati del docente
$sostituzioniLocali['{{nome_docente}}'] = $__docente_nome;
$sostituzioniLocali['{{cognome_docente}}'] = $__docente_cognome;
$sostituzioniLocali['{{email_docente}}'] = $__docente_email;
$sostituzioniLocali['{{nome_cognome_docente}}'] = $__docente_nome . ' ' . $__docente_cognome;

// generali
$sostituzioniLocali['{{anno}}'] = date("Y");
$sostituzioniLocali['{{anno_scolastico}}'] = $__anno_scolastico_corrente_anno;

$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
$dataDiOggi = utf8_encode( strftime("%d %B %Y", strtotime('today GMT')));
setlocale(LC_TIME, $oldLocale);
$sostituzioniLocali['{{data_di_oggi}}'] = $dataDiOggi;

// locali istituto
$sostituzioniLocali['{{luogo_istituto}}'] = $__settings->local->luogoIstituto;
$sostituzioniLocali['{{email_istituto}}'] = $__settings->local->luogoIstituto;

// $sostituzioniRichieste e' stato ricevuto e ora lo aggiungo a quelle note
$sostituzioni = array_merge($sostituzioniLocali, $sostituzioniRichieste);

$documento = creaDocumento($codice, $sostituzioni);

$pagina = creaPagina($documento, $title, $print, $email);

// NB: mancano i controlli per le condizioni, tipo se il voto < 6 allora scrivi in rosso, se il voto < 6 allora aggiungi questa sezione...
// beh il colore rosso vedo che si fa prima e si sostituisce tutto il pezzo con dentro anche color
// invece le sezioni condizionate bisogna pensarle

// bisogna invece sostituire __intestazione, __intestazione_breve, __nome_dirigente, __timbro

// potrebbe essere una funzione con parametri tipo produciPdf (default false), nomePdf, inviaEmail (defaul False), emailFromToCC, ...
// l'idea e' quella di produrre un documento html che posso trasformare in pdf se richiesto e inviare per email se richiesto (ma serve anche il subject e testo della email)
