<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('segreteria-didattica', 'docente');

if (!isset($_POST)) {
    exit;
}

$id = intval($_POST['id'] ?? 0);
$id_programma = intval($_POST['id_programma'] ?? 0);
$ordine = intval($_POST['ordine'] ?? 0);
$titolo = trim((string)($_POST['titolo'] ?? ''));
$contenuto = trim((string)($_POST['contenuto'] ?? ''));
$competenze_raggiunte = trim((string)($_POST['competenze_raggiunte'] ?? ''));
$contenuti_trattati = trim((string)($_POST['contenuti_trattati'] ?? ''));
$abilita_quinta = trim((string)($_POST['abilita_quinta'] ?? ''));

if ($id_programma <= 0 || $ordine <= 0 || $titolo === '') {
    exit;
}

$programma = dbGetFirst("
    SELECT classi.anno AS classe_anno
    FROM programmi_svolti
    INNER JOIN classi
    ON classi.id = programmi_svolti.id_classe
    WHERE programmi_svolti.id = " . intval($id_programma)
);

$classe_anno = intval($programma['classe_anno'] ?? 0);
$is_quinta = ($classe_anno === 5);

if ($is_quinta) {
    $contenuto = json_encode([
        'schema' => 'programma_svolto_quinta_v1',
        'competenze_raggiunte' => $competenze_raggiunte,
        'contenuti_trattati' => $contenuti_trattati,
        'abilita' => $abilita_quinta,
    ], JSON_UNESCAPED_UNICODE);
}

$titolo_sql = dbEscape($titolo);
$contenuto_sql = dbEscape($contenuto);

date_default_timezone_set("Europe/Rome");
$update = date("Y-m-d H-i-s");
$id_utente = $__utente_id;

if ($id > 0) {
    $query = "UPDATE programmi_svolti_moduli
        SET id_programma = '$id_programma',
            id_utente = '$id_utente',
            ordine = '$ordine',
            nome = '$titolo_sql',
            contenuto = '$contenuto_sql',
            updated = '$update'
        WHERE id = '$id'";
    dbExec($query);
    info("aggiornato programma svolto modulo id=$id id_programma=$id_programma id_utente=$id_utente updated=$update");
} else {
    $query = "INSERT INTO programmi_svolti_moduli(id_programma,ordine,nome,contenuto,id_utente,updated)
        VALUES('$id_programma', '$ordine', '$titolo_sql', '$contenuto_sql','$id_utente','$update')";
    dbExec($query);
    $id = dblastId();
    info("aggiunto programma svolto modulo id=$id  id_programma=$id_programma id_utente=$id_utente updated=$update");
}
?>
