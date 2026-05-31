<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/orario_builder_lib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti');

$idPiano = ob_int($_POST['id_piano'] ?? 0);

if ($idPiano <= 0) {
    die('Piano non valido');
}

dbExec("
    UPDATE orario_piano_orario
    SET attivo = 0,
        updated_at = NOW()
    WHERE id = $idPiano
");

ob_redirect('piani_orario.php');