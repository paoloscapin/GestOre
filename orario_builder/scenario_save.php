<?php
require_once __DIR__ . '/orario_builder_lib.php';

$nome = dbEscape(trim($_POST['nome'] ?? ''));
$idAnno = ob_int($_POST['id_anno_scolastico'] ?? 0);
$descrizione = dbEscape(trim($_POST['descrizione'] ?? ''));

if ($nome === '' || $idAnno <= 0) {
    die('Dati scenario non validi');
}

dbExec("
    INSERT INTO orario_scenario (
        nome,
        id_anno_scolastico,
        descrizione,
        stato
    ) VALUES (
        '$nome',
        $idAnno,
        '$descrizione',
        'BOZZA'
    )
");

ob_redirect('scenari.php');