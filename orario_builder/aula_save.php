<?php
require_once __DIR__ . '/orario_builder_lib.php';

$codice = dbEscape(trim($_POST['codice'] ?? ''));
$nome = dbEscape(trim($_POST['nome'] ?? ''));
$piano = dbEscape(trim($_POST['piano'] ?? 'R'));
$ala = trim($_POST['ala'] ?? '');
$tipo = dbEscape(trim($_POST['tipo'] ?? 'AULA'));
$capienza = ($_POST['capienza'] ?? '') !== '' ? intval($_POST['capienza']) : 'NULL';

if ($codice === '') {
    die('Codice aula mancante');
}

$alaSql = $ala !== '' ? "'" . dbEscape($ala) . "'" : "NULL";

dbExec("
    INSERT INTO aule (
        codice,
        nome,
        piano,
        ala,
        capienza,
        tipo,
        attiva
    ) VALUES (
        '$codice',
        '$nome',
        '$piano',
        $alaSql,
        $capienza,
        '$tipo',
        1
    )
    ON DUPLICATE KEY UPDATE
        nome = VALUES(nome),
        piano = VALUES(piano),
        ala = VALUES(ala),
        capienza = VALUES(capienza),
        tipo = VALUES(tipo),
        attiva = 1
");

ob_redirect('aule.php');