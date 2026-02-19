<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('docente', 'segreteria-docenti', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST)) {
    echo json_encode(['ok' => false, 'error' => 'No POST']);
    exit;
}

$tableName = "sportello";

// -------------------------
// INPUT
// -------------------------
$classe_id = intval($_POST['classe_id'] ?? 0);
$classe    = (string)($_POST['classe'] ?? '');

if ($classe_id != 0) {
    // NB: nella tua base può essere classi/classe, tu avevi "classe"
    $classeDb = dbGetValue("SELECT nome FROM classe WHERE classe.id=" . (int)$classe_id);
    if ($classeDb !== null && $classeDb !== '') {
        $classe = (string)$classeDb;
    }
}

$id        = intval($_POST['id'] ?? 0);
$data      = (string)($_POST['data'] ?? '');
$ora       = (string)($_POST['ora'] ?? '');

$materia_id   = intval($_POST['materia_id'] ?? 0);
$categoria_id = intval($_POST['categoria_id'] ?? 0);
$numero_ore   = intval($_POST['numero_ore'] ?? 0);

$argomento = (string)($_POST['argomento'] ?? '');
$argomento = dbEscape($argomento);

// luogo: serve raw + escaped
$luogo_raw = trim((string)($_POST['luogo'] ?? ''));
$luogo     = dbEscape($luogo_raw);

$max_iscrizioni = intval($_POST['max_iscrizioni'] ?? 0);
$cancellato     = intval($_POST['cancellato'] ?? 0);
$firmato        = intval($_POST['firmato'] ?? 0);
$online         = intval($_POST['online'] ?? 0);
$clil           = intval($_POST['clil'] ?? 0);
$orientamento   = intval($_POST['orientamento'] ?? 0);

$studentiDaModificareIdList = json_decode($_POST['studentiDaModificareIdList'] ?? '[]', true);
if (!is_array($studentiDaModificareIdList)) $studentiDaModificareIdList = [];

$categoria = (string)dbGetValue("SELECT nome from sportello_categoria WHERE id = " . (int)$categoria_id);

// docente_id: quello loggato di default
$docente_id = intval($__docente_id ?? 0);
if ($docente_id <= 0) {
    $docente_id = intval($_POST['docente_id'] ?? 0);
}

// -------------------------
// UPDATE SPORTELLO ESISTENTE
// -------------------------
if ($id > 0) {

    // stato prima (fondamentale per logica cancellazione)
    $before = dbGetFirst("
        SELECT docente_id, attivo, cancellato, luogo
        FROM sportello
        WHERE id = " . (int)$id . "
        LIMIT 1
    ");

    $last_cancellato = (int)($before['cancellato'] ?? 0);
    $before_docente  = (int)($before['docente_id'] ?? 0);
    $before_luogo    = trim((string)($before['luogo'] ?? ''));
    $before_attivo   = (int)($before['attivo'] ?? 0);

    // ---------------------------------------
    // REGOLA ATTIVO/DOCENTE:
    // - se STO CANCELLANDO: NON applicare la regola "luogo vuoto => bozza"
    //   perché qui voglio:
    //     cancellato=1, attivo=1, docente_id mantenuto, luogo svuotato
    // - se NON sto cancellando: regola normale basata su luogo
    // ---------------------------------------
    if ($cancellato == 1) {
        // mantieni docente_id (quello attuale dello sportello se c'è, altrimenti quello loggato)
        if ($before_docente > 0) {
            $docente_id = $before_docente;
        }
        // sportello cancellato deve restare "attivo" (gestione storica / visibilità)
        $attivo = 1;

        // IMPORTANTISSIMO: svuota luogo nello sportello originale
        $luogo_raw = '';
        $luogo = '';

    } else {
        // regola standard (come prima)
        if (($luogo_raw === '')&&($before_luogo === '')){
            $docente_id = 0;
            $attivo = 0;
        } else {
            $attivo = ($docente_id > 0) ? 1 : 0;
        }
    }

    // costruisci query UPDATE (safe)
    $dataEsc = dbEscape($data);
    $oraEsc  = dbEscape($ora);
    $classeEsc = dbEscape($classe);

    $query = "
        UPDATE sportello SET
            data = '$dataEsc',
            ora = '$oraEsc',
            docente_id = " . (int)$docente_id . ",
            materia_id = " . (int)$materia_id . ",
            categoria = '" . dbEscape($categoria) . "',
            numero_ore = " . (int)$numero_ore . ",
            argomento = '$argomento',
            luogo = '" . dbEscape($luogo) . "',
            classe = '$classeEsc',
            classe_id = " . (int)$classe_id . ",
            max_iscrizioni = " . (int)$max_iscrizioni . ",
            cancellato = " . (int)$cancellato . ",
            firmato = " . (int)$firmato . ",
            online = " . (int)$online . ",
            clil = " . (int)$clil . ",
            orientamento = " . (int)$orientamento . ",
            attivo = " . (int)$attivo . "
        WHERE id = " . (int)$id . "
        LIMIT 1
    ";

    dbExec($query);

    info("sportelloAggiorna: aggiornato sportello id=$id data=$data ora=$ora docente_id=$docente_id materia_id=$materia_id categoria=$categoria numero_ore=$numero_ore luogo='$luogo_raw' classe='$classe' classe_id=$classe_id attivo=$attivo cancellato=$cancellato");

    // ---------------------------------------
    // SE CAMBIA FLAG CANCELLATO
    // ---------------------------------------
    if ($last_cancellato != $cancellato) {

        // ====== DIVENTA CANCELLATO ======
        if ($cancellato == 1) {

            // 1) Mail docente + studenti
            info("sportelloAggiorna: invio mail cancellazione docente sportello id=$id");
            require "sportelloInviaMailCancellazioneDocente.php";

            info("sportelloAggiorna: invio mail cancellazione studenti sportello id=$id");
            require "sportelloInviaMailCancellazioneStudente.php";

            // 2) Cancella prenotazione MBApp (deve usare il link, non il luogo)
            info("sportelloAggiorna: cancello prenotazione MBApp sportello id=$id");
            require "sportelloCancellaPrenotazioneAulaMBApp.php";

            // 3) NON eliminare iscrizioni
            $cnt = (int)dbGetValue("SELECT COUNT(*) FROM sportello_studente WHERE sportello_id = " . (int)$id);
            info("sportelloAggiorna: iscrizioni mantenute (count=$cnt) sportello id=$id");

            // 4) Crea gemello in BOZZA +14 giorni
            //    IMPORTANTISSIMO: luogo deve essere '' nel gemello
            $twin_id = 0;

            // anti-duplicato minimo (stessa data+14, ora, materia, categoria, classe/classe_id, attivo=0, docente_id=0)
            $targetDate = dbGetValue("SELECT DATE_ADD(DATE(data), INTERVAL 14 DAY) FROM sportello WHERE id=".(int)$id." LIMIT 1");
            $targetDate = $targetDate ? (string)$targetDate : '';

            $exists = 0;
            if ($targetDate !== '') {
                $exists = (int)dbGetValue("
                    SELECT COUNT(*)
                    FROM sportello s2
                    JOIN sportello s1 ON s1.id = " . (int)$id . "
                    WHERE DATE(s2.data) = DATE('" . dbEscape($targetDate) . "')
                      AND s2.ora = s1.ora
                      AND s2.materia_id = s1.materia_id
                      AND s2.categoria = s1.categoria
                      AND (
                            (s2.classe_id = s1.classe_id AND s1.classe_id <> 0)
                         OR (s2.classe = s1.classe AND (s1.classe_id = 0 OR s1.classe_id IS NULL))
                      )
                      AND s2.attivo = 0
                      AND s2.docente_id = 0
                      AND s2.cancellato = 0
                    LIMIT 1
                ");
            }

            if ($exists > 0) {
                info("sportelloAggiorna: gemello già presente -> skip (sportello_id=$id targetDate=$targetDate)");
            } else {

                $qTwin = "
                    INSERT INTO sportello
                        (data, ora, docente_id, materia_id, categoria,
                         numero_ore, argomento, luogo,
                         classe, classe_id,
                         max_iscrizioni, online, clil, orientamento,
                         attivo, cancellato, firmato,
                         anno_scolastico_id)
                    SELECT
                        DATE_ADD(DATE(data), INTERVAL 14 DAY) AS data,
                        ora,
                        0 AS docente_id,
                        materia_id,
                        categoria,
                        numero_ore,
                        argomento,
                        '' AS luogo,          -- ✅ FIX: il gemello deve nascere senza aula
                        classe,
                        classe_id,
                        max_iscrizioni,
                        online,
                        clil,
                        orientamento,
                        0 AS attivo,
                        0 AS cancellato,
                        0 AS firmato,
                        anno_scolastico_id
                    FROM sportello
                    WHERE id = " . (int)$id . "
                    LIMIT 1
                ";

                dbExec($qTwin);
                $twin_id = (int)dblastId();
                info("sportelloAggiorna: creato gemello BOZZA twin_id=$twin_id da sportello_id=$id (+14gg, luogo vuoto)");
            }

        } else {
            // (opzionale) da cancellato=1 a cancellato=0: qui per ora non facciamo nulla
            info("sportelloAggiorna: flag cancellato cambiato ma ora cancellato=0 (nessuna azione automatica)");
        }

    } else {
        // nessun cambio cancellato: aggiorna presenze come prima
        foreach ($studentiDaModificareIdList as $studente) {
            $studente = intval($studente);
            if ($studente <= 0) continue;
            $q = "UPDATE sportello_studente SET presente = IF (`presente`, 0, 1) WHERE sportello_studente.id = " . (int)$studente;
            dbExec($q);
            info("sportelloAggiorna: aggiornato presente sportello_studente.id=$studente");
        }
    }

} else {

    // -------------------------
    // INSERT NUOVO SPORTELLO
    // -------------------------
    // regola standard per attivo
    if ($luogo_raw === '') {
        $docente_id = 0;
        $attivo = 0;
    } else {
        $attivo = ($docente_id > 0) ? 1 : 0;
    }

    $dataEsc = dbEscape($data);
    $oraEsc  = dbEscape($ora);
    $classeEsc = dbEscape($classe);

    $query = "
        INSERT INTO sportello(
            data, ora, docente_id, materia_id, categoria, numero_ore, argomento, luogo, classe, classe_id,
            max_iscrizioni, online, clil, orientamento, attivo, anno_scolastico_id
        ) VALUES(
            '$dataEsc', '$oraEsc', " . (int)$docente_id . ", " . (int)$materia_id . ", '" . dbEscape($categoria) . "',
            " . (int)$numero_ore . ", '$argomento', '" . dbEscape($luogo) . "', '$classeEsc', " . (int)$classe_id . ",
            " . (int)$max_iscrizioni . ", " . (int)$online . ", " . (int)$clil . ", " . (int)$orientamento . ",
            " . (int)$attivo . ", " . (int)$__anno_scolastico_corrente_id . "
        )
    ";

    dbExec($query);
    $id = (int)dblastId();

    info("sportelloAggiorna: aggiunto sportello id=$id data=$data ora=$ora docente_id=$docente_id materia_id=$materia_id numero_ore=$numero_ore luogo='$luogo_raw' classe='$classe' attivo=$attivo");
}

// risposta
$materia = (string)dbGetValue("SELECT nome FROM materia WHERE id = " . (int)$materia_id);

echo json_encode([
    'ok' => true,
    'id' => (int)$id,
    'materia' => $materia,
    'data' => $data,
    'ora' => $ora,
    'luogo' => $luogo_raw,         // qui sarà '' se cancellato
    'numero_ore' => (int)$numero_ore,
    'attivo' => (int)($attivo ?? 0),
    'cancellato' => (int)$cancellato
]);
exit;
