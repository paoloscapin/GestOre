<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// ===============================
// DEBUG MODE (attiva con ?dbg=1 oppure POST dbg=1)
// ===============================
$__dbg = false;
if ((isset($_GET['dbg']) && $_GET['dbg'] == '1') || (isset($_POST['dbg']) && $_POST['dbg'] == '1')) {
    $__dbg = true;
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

// ===============================
// Shutdown handler: intercetta fatal error e lo logga con debug()
// ===============================
register_shutdown_function(function () use (&$__dbg) {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // prova a loggare con debug() se disponibile
        if (function_exists('debug')) {
            debug("orePrevisteAggiorna.php FATAL: " . json_encode($err));
        }
        // se in debug mode, prova anche a stampare qualcosa per AJAX
        if ($__dbg) {
            // attenzione: potrebbe essere già stato inviato output/header, quindi stampo solo se possibile
            @header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'where' => 'shutdown_handler',
                'fatal' => $err
            ]);
        }
    }
});

// ===============================
// Require robusti con __DIR__
// ===============================
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/importi_load.php';

debug("orePrevisteAggiorna.php: START (dbg=" . ($__dbg ? "1" : "0") . ")");

function aggiungiMessaggioOrePreviste(&$messaggio, $testo) {
    if (empty($testo)) {
        return;
    }
    if (!empty($messaggio)) {
        $messaggio = $messaggio . "</br>";
    }
    $messaggio = $messaggio . $testo;
}

function formattaOrePreviste($ore, $descrizione = '') {
    $testo = $ore . ' ' . ($ore == 1 ? 'ora' : 'ore');
    if (!empty($descrizione)) {
        if ($ore == 1) {
            $descrizione = str_replace('funzionali mancanti', 'funzionale mancante', $descrizione);
            $descrizione = str_replace('funzionali', 'funzionale', $descrizione);
        }
        $testo = $testo . ' ' . $descrizione;
    }
    return $testo;
}

function formattaMessaggioCompensazionePreviste($testo) {
    return '<div style="font-weight:bold; text-align:center; background-color:#D9EDF7; color:#245269; padding:6px; margin:0;">' . $testo . '</div>';
}

function formattaMessaggioProspettoPreviste($testo, $livello = 'ok') {
    $background = ($livello == 'warning') ? '#FFC6B4' : '#BAEED0';
    return '<div style="font-weight:bold; text-align:center; background-color:' . $background . '; color:#000; padding:6px; margin:0;">' . $testo . '</div>';
}

// Funzione principale
function orePrevisteAggiorna($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile) {
    debug("orePrevisteAggiorna(): ENTER docente_id=$docente_id operatore=$operatore soloTotale=" . json_encode($soloTotale) . " modificabile=" . json_encode($modificabile));

    global $__anno_scolastico_corrente_id;
    global $__docente_id;
    global $__config;
    global $__importi;

    debug("orePrevisteAggiorna(): globals anno=" . json_encode($__anno_scolastico_corrente_id) . " __docente_id=" . json_encode($__docente_id));

    $totale = [];
    $oreCorsoDiRecuperoExtra = 0;
    $oreAggiornamento = 0;
    $diariaGiorniSenzaPernottamento = 0;
    $diariaGiorniConPernottamento = 0;
    $diariaImportoPreviste = 0;
    $messaggio = '';
    $messaggioEccesso = '';
    $messaggioPrevisteDovute = '';
    $messaggioPrevisteDovuteLivello = '';

    $oreAggiornamentoPreviste = 0;
    $oreConStudentiPreviste = 0;
    $oreFunzionaliPreviste = 0;
    $oreClilFunzionaliPreviste = 0;
    $oreClilConStudentiPreviste = 0;
    $oreOrientamentoFunzionaliPreviste = 0;
    $oreOrientamentoConStudentiPreviste = 0;

    $dataCdr = '';
    $dataPreviste = '';
    $dataAttribuite = '';
    $dataDiaria = '';

    // ✅ inizializza dovute a 0 (evita undefined)
    $oreConStudentiDovute = 0;
    $oreFunzionaliDovute = 0;
    $oreAggiornamentoDovute = 0;
    $oreSostituzioniDovute = 0;

    // -------------------------
    // ORE DOVUTE
    // -------------------------
    debug("orePrevisteAggiorna(): require oreDovuteReadDetails.php");
    require_once __DIR__ . '/oreDovuteReadDetails.php';

    debug("orePrevisteAggiorna(): calling oreDovuteReadDetails()");
    $ore_dovute = oreDovuteReadDetails($soloTotale, $docente_id, 'ore_dovute');
    debug("orePrevisteAggiorna(): oreDovuteReadDetails() returned: " . json_encode($ore_dovute));

    if ($ore_dovute != null) {
        $oreConStudentiDovute = $ore_dovute['ore_40_con_studenti'] + $ore_dovute['ore_70_con_studenti'];
        $oreFunzionaliDovute = $ore_dovute['ore_70_funzionali'];
        $oreAggiornamentoDovute = $ore_dovute['ore_40_aggiornamento'];
        $oreSostituzioniDovute = $ore_dovute['ore_40_sostituzioni_di_ufficio'];
    }

    debug("orePrevisteAggiorna(): dovute computed conStudenti=$oreConStudentiDovute funzionali=$oreFunzionaliDovute agg=$oreAggiornamentoDovute sost=$oreSostituzioniDovute");

    $totale = $totale + compact('oreConStudentiDovute', 'oreFunzionaliDovute', 'oreAggiornamentoDovute', 'oreSostituzioniDovute');

    // -------------------------
    // ATTIVITA PREVISTE
    // -------------------------
    debug("orePrevisteAggiorna(): require previsteReadRecords.php");
    require_once __DIR__ . '/previsteReadRecords.php';

    debug("orePrevisteAggiorna(): calling previsteReadRecords()");
    $ore_previste = previsteReadRecords($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile);
    debug("orePrevisteAggiorna(): previsteReadRecords() returned keys: " . json_encode(array_keys((array)$ore_previste)));

    $oreAggiornamentoPreviste = $ore_previste['attivitaAggiornamento'] ?? 0;
    $oreConStudentiPreviste = $ore_previste['attivitaOreConStudenti'] ?? 0;
    $oreFunzionaliPreviste = $ore_previste['attivitaOreFunzionali'] ?? 0;
    $oreClilFunzionaliPreviste = $ore_previste['attivitaClilOreFunzionali'] ?? 0;
    $oreClilConStudentiPreviste = $ore_previste['attivitaClilOreConStudenti'] ?? 0;
    $oreOrientamentoFunzionaliPreviste = $ore_previste['attivitaOrientamentoOreFunzionali'] ?? 0;
    $oreOrientamentoConStudentiPreviste = $ore_previste['attivitaOrientamentoOreConStudenti'] ?? 0;
    $dataPreviste = $ore_previste['dataAttivita'] ?? '';

    debug("orePrevisteAggiorna(): previste ore agg=$oreAggiornamentoPreviste conStud=$oreConStudentiPreviste funz=$oreFunzionaliPreviste");

    $totale = $totale + compact('dataPreviste');

    // -------------------------
    // CORSI DI RECUPERO PREVISTE
    // -------------------------
    debug("orePrevisteAggiorna(): require corsoDiRecuperoPrevisteReadRecords.php");
    require_once __DIR__ . '/corsoDiRecuperoPrevisteReadRecords.php';

    debug("orePrevisteAggiorna(): calling corsoDiRecuperoPrevisteReadRecords()");
    $result = corsoDiRecuperoPrevisteReadRecords($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile, false);
    debug("orePrevisteAggiorna(): corsoDiRecuperoPrevisteReadRecords() returned keys: " . json_encode(array_keys((array)$result)));

    $oreConStudentiPreviste += ($result['corso_di_recupero_ore_recuperate'] ?? 0);
    $oreConStudentiPreviste += ($result['corso_di_recupero_ore_in_itinere'] ?? 0);
    $oreCorsoDiRecuperoExtra += ($result['corso_di_recupero_ore_pagamento_extra'] ?? 0);
    $dataCdr = $result['dataCdr'] ?? '';

    debug("orePrevisteAggiorna(): after CDR conStud=$oreConStudentiPreviste extra=$oreCorsoDiRecuperoExtra");

    $totale = $totale + compact('dataCdr');

    // -------------------------
    // ORE ATTRIBUITE
    // -------------------------
    debug("orePrevisteAggiorna(): require oreFatteReadAttribuite.php");
    require_once __DIR__ . '/oreFatteReadAttribuite.php';

    debug("orePrevisteAggiorna(): calling oreFatteReadAttribuite()");
    $result = oreFatteReadAttribuite($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile);
    debug("orePrevisteAggiorna(): oreFatteReadAttribuite() returned keys: " . json_encode(array_keys((array)$result)));

    $oreConStudentiPreviste += ($result['attribuiteOreConStudenti'] ?? 0);
    $oreFunzionaliPreviste += ($result['attribuiteOreFunzionali'] ?? 0);
    $oreClilConStudentiPreviste += ($result['attribuiteClilOreConStudenti'] ?? 0);
    $oreClilFunzionaliPreviste += ($result['attribuiteClilOreFunzionali'] ?? 0);
    $oreOrientamentoConStudentiPreviste += ($result['attribuiteOrientamentoOreConStudenti'] ?? 0);
    $oreOrientamentoFunzionaliPreviste += ($result['attribuiteOrientamentoOreFunzionali'] ?? 0);
    $dataAttribuite = $result['dataAttribuite'] ?? '';

    debug("orePrevisteAggiorna(): after attribuite conStud=$oreConStudentiPreviste funz=$oreFunzionaliPreviste");

    $totale = $totale + compact('dataAttribuite');

    // -------------------------
    // DIARIA PREVISTA
    // -------------------------
    debug("orePrevisteAggiorna(): require viaggioDiariaPrevistaReadRecords.php");
    require_once __DIR__ . '/viaggioDiariaPrevistaReadRecords.php';

    debug("orePrevisteAggiorna(): calling viaggioDiariaPrevistaReadRecords()");
    $result = viaggioDiariaPrevistaReadRecords($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile);
    debug("orePrevisteAggiorna(): viaggioDiariaPrevistaReadRecords() returned keys: " . json_encode(array_keys((array)$result)));

    $oreConStudentiPreviste += ($result['diariaOre'] ?? 0);
    $diariaGiorniSenzaPernottamento += ($result['diariaGiorniSenzaPernottamento'] ?? 0);
    $diariaGiorniConPernottamento += ($result['diariaGiorniConPernottamento'] ?? 0);
    $diariaImportoPreviste += ($result['diariaImporto'] ?? 0);
    $dataDiaria = $result['dataDiaria'] ?? '';

    debug("orePrevisteAggiorna(): after diaria conStud=$oreConStudentiPreviste diariaImporto=$diariaImportoPreviste");

    $totale = $totale + compact('dataDiaria');
    $totale = $totale + compact('diariaGiorniSenzaPernottamento', 'diariaGiorniConPernottamento', 'diariaImportoPreviste');

    // -------------------------
    // TOTALI PREVISTE
    // -------------------------
    $totale = $totale + compact(
        'oreConStudentiPreviste',
        'oreFunzionaliPreviste',
        'oreClilConStudentiPreviste',
        'oreClilFunzionaliPreviste',
        'oreOrientamentoConStudentiPreviste',
        'oreOrientamentoFunzionaliPreviste',
        'oreAggiornamentoPreviste'
    );

    debug("orePrevisteAggiorna(): totals before FUIS conStudPrev=$oreConStudentiPreviste funzPrev=$oreFunzionaliPreviste");

    // -------------------------
    // FUIS CALCOLO
    // -------------------------
    $bilancioFunzionali = $oreFunzionaliPreviste - $oreFunzionaliDovute;
    $bilancioConStudenti = $oreConStudentiPreviste - $oreConStudentiDovute;

    debug("orePrevisteAggiorna(): bilanci funz=$bilancioFunzionali conStud=$bilancioConStudenti");

    if (getSettingsValue('fuis', 'accetta_con_studenti_per_funzionali', false)) {
        if ($bilancioFunzionali < 0 && $bilancioConStudenti > 0) {
            $daSpostare = -$bilancioFunzionali;
            debug('orePrevisteAggiorna(): daSpostare iniziale=' . $daSpostare);
            if ($bilancioConStudenti < $daSpostare) {
                $daSpostare = $bilancioConStudenti;
                debug('orePrevisteAggiorna(): daSpostare ridotto=' . $daSpostare);
            }
            $bilancioConStudenti = $bilancioConStudenti - $daSpostare;
            $bilancioFunzionali = $bilancioFunzionali + $daSpostare;
            $messaggio .= formattaMessaggioCompensazionePreviste(formattaOrePreviste($daSpostare, "con studenti") . " verranno usate per coprire " . formattaOrePreviste($daSpostare, "funzionali mancanti") . ".");
            debug('orePrevisteAggiorna(): spostate con studenti in funzionali bilancioFunzionali=' . $bilancioFunzionali . ' bilancioConStudenti=' . $bilancioConStudenti);
        }
    }

    if (getSettingsValue('fuis', 'accetta_funzionali_per_con_studenti', false)) {
        if ($bilancioConStudenti < 0 && $bilancioFunzionali > 0) {
            $daSpostare = -$bilancioConStudenti;
            if ($bilancioFunzionali < $daSpostare) {
                $daSpostare = $bilancioFunzionali;
            }
            $bilancioFunzionali = $bilancioFunzionali - $daSpostare;
            $bilancioConStudenti = $bilancioConStudenti + $daSpostare;
            $messaggio .= formattaMessaggioCompensazionePreviste(formattaOrePreviste($daSpostare, "funzionali") . " verranno usate per coprire " . formattaOrePreviste($daSpostare, "con studenti mancanti") . ".");
            debug('orePrevisteAggiorna(): spostate funzionali in con studenti bilancioFunzionali=' . $bilancioFunzionali . ' bilancioConStudenti=' . $bilancioConStudenti);
        }
    }

    $oreFunzionaliPrevisteMancanti = max(-$bilancioFunzionali, 0);
    $oreConStudentiPrevisteMancanti = max(-$bilancioConStudenti, 0);
    $orePrevisteMancanti = $oreFunzionaliPrevisteMancanti + $oreConStudentiPrevisteMancanti;
    $oreFunzionaliPrevisteFuis = max($bilancioFunzionali, 0);
    $oreConStudentiPrevisteFuis = max($bilancioConStudenti, 0);
    $orePrevisteFuis = $oreFunzionaliPrevisteFuis + $oreConStudentiPrevisteFuis;

    if (!empty($messaggio)) {
        aggiungiMessaggioOrePreviste($messaggioPrevisteDovute, $messaggio);
    }
    if ($orePrevisteMancanti > 0) {
        $dettagliPrevisteMancanti = [];
        if ($oreFunzionaliPrevisteMancanti > 0) {
            $dettagliPrevisteMancanti[] = formattaOrePreviste($oreFunzionaliPrevisteMancanti, "funzionali");
        }
        if ($oreConStudentiPrevisteMancanti > 0) {
            $dettagliPrevisteMancanti[] = formattaOrePreviste($oreConStudentiPrevisteMancanti, "con studenti");
        }
        $testoPrevisteDovute = "Le ore previste sono sotto il minimo dovuto di " . formattaOrePreviste($orePrevisteMancanti);
        if (!empty($dettagliPrevisteMancanti)) {
            $testoPrevisteDovute = $testoPrevisteDovute . ": " . implode(", ", $dettagliPrevisteMancanti);
        }
        $testoPrevisteDovute = $testoPrevisteDovute . ". Occorre prevedere altre ore per arrivare al minimo dovuto.";
        aggiungiMessaggioOrePreviste($messaggioPrevisteDovute, formattaMessaggioProspettoPreviste($testoPrevisteDovute, 'warning'));
        $messaggioPrevisteDovuteLivello = 'warning';
    } elseif ($orePrevisteFuis > 0) {
        $dettagliPrevisteFuis = [];
        if ($oreFunzionaliPrevisteFuis > 0) {
            $dettagliPrevisteFuis[] = formattaOrePreviste($oreFunzionaliPrevisteFuis, "funzionali");
        }
        if ($oreConStudentiPrevisteFuis > 0) {
            $dettagliPrevisteFuis[] = formattaOrePreviste($oreConStudentiPrevisteFuis, "con studenti");
        }
        $testoPrevisteDovute = "Le ore previste coprono il minimo dovuto. Le ore oltre il minimo andranno a FUIS";
        if (!empty($dettagliPrevisteFuis)) {
            $testoPrevisteDovute = $testoPrevisteDovute . ": (" . implode(", ", $dettagliPrevisteFuis) . ")";
        }
        $testoPrevisteDovute = $testoPrevisteDovute . ".";
        aggiungiMessaggioOrePreviste($messaggioPrevisteDovute, formattaMessaggioProspettoPreviste($testoPrevisteDovute));
        $messaggioPrevisteDovuteLivello = 'ok';
    } elseif (!empty($messaggioPrevisteDovute)) {
        aggiungiMessaggioOrePreviste($messaggioPrevisteDovute, formattaMessaggioProspettoPreviste("Le ore previste coprono esattamente il minimo dovuto dopo compensazione."));
        $messaggioPrevisteDovuteLivello = 'ok';
    }
    $messaggio = '';

    $fuisFunzionale = $bilancioFunzionali * $__importi['importo_ore_funzionali'];
    $fuisConStudenti = $bilancioConStudenti * $__importi['importo_ore_con_studenti'];

    if (!getSettingsValue('fuis', 'compensa_in_valore', false)) {
        $fuisFunzionale = max($fuisFunzionale, 0);
        $fuisConStudenti = max($fuisConStudenti, 0);
    }

    $fuisOrePreviste = $fuisFunzionale + $fuisConStudenti;
    $fuisOrePreviste = max($fuisOrePreviste, 0);

    $clilFatteFunzionaliBilancio = $oreClilFunzionaliPreviste;
    $clilFatteConStudentiBilancio = $oreClilConStudentiPreviste;

    $fuisClilFunzionalePreviste = $clilFatteFunzionaliBilancio * $__importi['importo_ore_funzionali'];
    $fuisClilConStudentiPreviste = $clilFatteConStudentiBilancio * $__importi['importo_ore_con_studenti'];

    $fuisOrientamentoFunzionalePreviste = $oreOrientamentoFunzionaliPreviste * $__importi['importo_ore_funzionali'];
    $fuisOrientamentoConStudentiPreviste = $oreOrientamentoConStudentiPreviste * $__importi['importo_ore_con_studenti'];

    $fuisExtraCorsiDiRecupero = $oreCorsoDiRecuperoExtra * $__importi['importo_ore_corsi_di_recupero'];

    debug("orePrevisteAggiorna(): calling dbGetValue fuis_assegnato docente_id=$docente_id");
    $fuisAssegnato = dbGetValue("SELECT COALESCE(SUM(importo), 0) FROM fuis_assegnato WHERE docente_id = $docente_id AND anno_scolastico_id = $__anno_scolastico_corrente_id;");

    $totale = $totale + compact(
        'messaggio',
        'messaggioEccesso',
        'messaggioPrevisteDovute',
        'messaggioPrevisteDovuteLivello',
        'fuisFunzionale',
        'fuisConStudenti',
        'fuisOrePreviste',
        'fuisClilFunzionalePreviste',
        'fuisClilConStudentiPreviste',
        'fuisOrientamentoFunzionalePreviste',
        'fuisOrientamentoConStudentiPreviste',
        'fuisExtraCorsiDiRecupero',
        'fuisAssegnato'
    );

    debug("orePrevisteAggiorna(): EXIT ok");
    return $totale;
}

// ===============================
// Handler POST: ritorna JSON
// ===============================
if (isset($_POST['richiesta']) && $_POST['richiesta'] == "orePrevisteAggiorna") {
    debug("orePrevisteAggiorna.php: POST handler ENTER " . json_encode(array_keys($_POST)));

    if (isset($_POST['docente_id']) && $_POST['docente_id'] != "") {
        $docente_id = $_POST['docente_id'];
    } else {
        global $__docente_id;
        $docente_id = $__docente_id;
    }

    $soloTotale = json_decode($_POST['soloTotale']);
    debug("orePrevisteAggiorna.php: doc_id=$docente_id soloTotale=" . json_encode($soloTotale));

    if (isset($_POST['operatore']) && $_POST['operatore'] == 'dirigente') {
        debug("orePrevisteAggiorna.php: operatore richiesto = dirigente -> ruoloRichiesto('dirigente')");
        ruoloRichiesto('dirigente');
        $operatore = 'dirigente';
        $modificabile = true;
        $ultimo_controllo = $_POST['ultimo_controllo'] ?? '';
    } else {
        debug("orePrevisteAggiorna.php: operatore = docente");
        global $__config;
        $operatore = 'docente';
        $ultimo_controllo = '';
        $modificabile = $__config->getOre_previsioni_aperto();
    }

    debug("orePrevisteAggiorna.php: calling orePrevisteAggiorna()...");
    $totale = orePrevisteAggiorna($soloTotale, $docente_id, $operatore, $ultimo_controllo, $modificabile);
    debug("orePrevisteAggiorna.php: orePrevisteAggiorna() DONE -> output JSON");

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($totale);
    exit;
}

debug("orePrevisteAggiorna.php: END (no POST handler hit)");
?>
