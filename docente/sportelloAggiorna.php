<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('docente','segreteria-docenti','dirigente');

$tableName = "sportello";

if (isset($_POST)) {

    $classe_id = intval($_POST['classe_id'] ?? 0);
    $classe    = $_POST['classe'] ?? '';

    if ($classe_id != 0) {
        $classe = dbGetValue("SELECT nome FROM classe WHERE classe.id=" . $classe_id);
    }

    $id          = intval($_POST['id'] ?? 0);
    $data        = $_POST['data'] ?? '';
    $ora         = $_POST['ora'] ?? '';

    $materia_id   = intval($_POST['materia_id'] ?? 0);
    $categoria_id = intval($_POST['categoria_id'] ?? 0);
    $numero_ore   = intval($_POST['numero_ore'] ?? 0);

    $argomento = escapePost('argomento');

    // 🔴 qui serve sia raw che escaped
    $luogo_raw = trim($_POST['luogo'] ?? '');
    $luogo     = escapePost('luogo');

    $max_iscrizioni = intval($_POST['max_iscrizioni'] ?? 0);
    $cancellato     = intval($_POST['cancellato'] ?? 0);
    $firmato        = intval($_POST['firmato'] ?? 0);
    $online         = intval($_POST['online'] ?? 0);
    $clil           = intval($_POST['clil'] ?? 0);
    $orientamento   = intval($_POST['orientamento'] ?? 0);

    $studentiDaModificareIdList = json_decode($_POST['studentiDaModificareIdList'] ?? '[]');

    $categoria = dbGetValue("SELECT nome from sportello_categoria WHERE id = " . $categoria_id);

    // ✅ docente_id: di default quello loggato
    $docente_id = intval($__docente_id ?? 0);
    if ($docente_id <= 0) {
        $docente_id = intval($_POST['docente_id'] ?? 0);
    }

    // ✅ REGOLA NUOVA:
    // se luogo vuoto => torna NON assegnato (docente_id=0) e BOZZA (attivo=0)
    // altrimenti => assegnato (docente_id presente) e attivo=1
    if ($luogo_raw === '') {
        $docente_id = 0;
        $attivo = 0;
    } else {
        $attivo = ($docente_id > 0) ? 1 : 0;
    }

    if ($id > 0) {

        $last_cancellato = dbGetValue("SELECT cancellato from sportello WHERE id = '$id'");

        $query = "UPDATE sportello SET
            data = '$data',
            ora = '$ora',
            docente_id = '$docente_id',
            materia_id = '$materia_id',
            categoria = '$categoria',
            numero_ore = '$numero_ore',
            argomento = '$argomento',
            luogo = '$luogo',
            classe = '$classe',
            classe_id = '$classe_id',
            max_iscrizioni = '$max_iscrizioni',
            cancellato = $cancellato,
            firmato = $firmato,
            online = $online,
            clil = $clil,
            orientamento = $orientamento,
            attivo = $attivo
        WHERE id = '$id'";

        dbExec($query);

        info("aggiornato sportello id=$id data=$data ora=$ora docente_id=$docente_id materia_id=$materia_id categoria=$categoria numero_ore=$numero_ore argomento=$argomento luogo=$luogo classe=$classe classe_id=$classe_id max_iscrizioni=$max_iscrizioni online=$online clil=$clil orientamento=$orientamento attivo=$attivo");

        if ($last_cancellato != $cancellato) {
            info("invio mail di cancellazione al docente");
            require "sportelloInviaMailCancellazioneDocente.php";
            info("invio mail di cancellazione agli studenti iscritti allo sportello");
            require "sportelloInviaMailCancellazioneStudente.php";

            $query = "DELETE FROM sportello_studente WHERE sportello_id = '$id'";
            dbExec($query);
            info("cancellati gli studenti iscritti allo sportello id=$id");
        } else {
            if (is_array($studentiDaModificareIdList)) {
                foreach ($studentiDaModificareIdList as $studente) {
                    $studente = intval($studente);
                    if ($studente <= 0) continue;
                    $query = "UPDATE sportello_studente SET presente = IF (`presente`, 0, 1) WHERE sportello_studente.id = $studente";
                    dbExec($query);
                    info("aggiornato id=$studente");
                }
            }
        }

    } else {

        $query = "INSERT INTO sportello(
                data, ora, docente_id, materia_id, categoria, numero_ore, argomento, luogo, classe, classe_id,
                max_iscrizioni, online, clil, orientamento, attivo, anno_scolastico_id
            ) VALUES(
                '$data', '$ora', '$docente_id', '$materia_id', '$categoria', '$numero_ore', '$argomento', '$luogo', '$classe', '$classe_id',
                '$max_iscrizioni', '$online', '$clil', '$orientamento', $attivo, $__anno_scolastico_corrente_id
            )";

        dbExec($query);
        $id = dblastId();
        info("aggiunto sportello id=$id data=$data ora=$ora docente_id=$docente_id materia_id=$materia_id numero_ore=$numero_ore argomento=$argomento luogo=$luogo classe=$classe classe_id=$classe_id max_iscrizioni=$max_iscrizioni online=$online clil=$clil orientamento=$orientamento attivo=$attivo");
    }
}
?>
