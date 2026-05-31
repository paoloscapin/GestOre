<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/orario_builder_lib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti');

$idPiano = ob_int($_POST['id_piano'] ?? 0);
$nome = trim((string)($_POST['nome'] ?? ''));
$idAnno = ob_int($_POST['id_anno_scolastico'] ?? 0);
$annoClasse = trim((string)($_POST['anno_classe'] ?? ''));
$descrizione = trim((string)($_POST['descrizione'] ?? ''));
$attivo = ob_int($_POST['attivo'] ?? 1, 1);

if ($idPiano <= 0 || $nome === '' || $idAnno <= 0) {
    die('Dati non validi');
}

$nomeSql = dbQNotNull($nome);
$descrizioneSql = dbQ($descrizione);
$annoClasseSql = $annoClasse !== '' ? intval($annoClasse) : "NULL";

dbExec("
    UPDATE orario_piano_orario
    SET
        nome = $nomeSql,
        id_anno_scolastico = $idAnno,
        anno_classe = $annoClasseSql,
        descrizione = $descrizioneSql,
        attivo = $attivo,
        updated_at = NOW()
    WHERE id = $idPiano
");

ob_redirect('piani_orario.php');