<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/orario_builder_lib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti');

$nome = trim((string)($_POST['nome'] ?? ''));
$idAnno = ob_int($_POST['id_anno_scolastico'] ?? 0);
$annoClasse = trim((string)($_POST['anno_classe'] ?? ''));
$descrizione = trim((string)($_POST['descrizione'] ?? ''));

if ($nome === '' || $idAnno <= 0) {
    die('Dati non validi');
}

$nomeSql = dbQNotNull($nome);
$descrizioneSql = dbQ($descrizione);
$annoClasseSql = $annoClasse !== '' ? intval($annoClasse) : "NULL";

dbExec("
    INSERT INTO orario_piano_orario (
        id_anno_scolastico,
        nome,
        descrizione,
        anno_classe,
        attivo
    ) VALUES (
        $idAnno,
        $nomeSql,
        $descrizioneSql,
        $annoClasseSql,
        1
    )
");

ob_redirect('piani_orario.php');