<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/orario_builder_lib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti');

$azione = trim((string)($_POST['azione'] ?? ''));
$idScenario = ob_int($_POST['id_scenario'] ?? 0);
$id = ob_int($_POST['id'] ?? 0);

if ($idScenario <= 0) {
    die('Scenario non valido');
}

function csvPost($name) {
    $arr = $_POST[$name] ?? [];
    if (!is_array($arr)) return null;

    $clean = [];
    foreach ($arr as $v) {
        $i = intval($v);
        if ($i > 0) $clean[] = $i;
    }

    return empty($clean) ? null : implode(',', array_unique($clean));
}

if ($azione === 'salva_gruppo') {
    $nome = dbQNotNull($_POST['nome'] ?? '');
    $filtro = dbQNotNull($_POST['filtro_anno_classe'] ?? '');
    $pomeriggi = ob_int($_POST['pomeriggi_settimanali'] ?? 2, 2);
    $ammessi = dbQ(csvPost('giorni_ammessi'));
    $obbligatori = dbQ(csvPost('giorni_obbligatori'));
    $distribuzione = dbQNotNull($_POST['distribuzione'] ?? 'UNIFORME');
    $note = dbQ($_POST['note'] ?? '');

    if ($id > 0) {
        dbExec("
            UPDATE orario_vincolo_pomeriggi_gruppo
            SET
                nome = $nome,
                filtro_anno_classe = $filtro,
                pomeriggi_settimanali = $pomeriggi,
                giorni_ammessi = $ammessi,
                giorni_obbligatori = $obbligatori,
                distribuzione = $distribuzione,
                note = $note,
                updated_at = NOW()
            WHERE id = $id
              AND id_scenario = $idScenario
        ");
    } else {
        dbExec("
            INSERT INTO orario_vincolo_pomeriggi_gruppo (
                id_scenario,
                nome,
                filtro_anno_classe,
                pomeriggi_settimanali,
                giorni_ammessi,
                giorni_obbligatori,
                distribuzione,
                note,
                attivo
            ) VALUES (
                $idScenario,
                $nome,
                $filtro,
                $pomeriggi,
                $ammessi,
                $obbligatori,
                $distribuzione,
                $note,
                1
            )
        ");
    }

    ob_redirect("vincoli_pomeriggi.php?id_scenario=$idScenario");
}

if ($azione === 'elimina_gruppo') {
    dbExec("
        UPDATE orario_vincolo_pomeriggi_gruppo
        SET attivo = 0,
            updated_at = NOW()
        WHERE id = $id
          AND id_scenario = $idScenario
    ");

    ob_redirect("vincoli_pomeriggi.php?id_scenario=$idScenario");
}

if ($azione === 'salva_bilanciamento') {
    $nome = dbQNotNull($_POST['nome'] ?? 'Bilanciamento pomeriggi');
    $giorni = dbQNotNull(csvPost('giorni_da_bilanciare') ?: '1,2,3,4,5');
    $livello = dbQNotNull($_POST['livello'] ?? 'MORBIDO');
    $peso = ob_int($_POST['peso'] ?? 100, 100);
    $scarto = trim((string)($_POST['scarto_massimo'] ?? ''));
    $scartoSql = $scarto === '' ? "NULL" : intval($scarto);
    $note = dbQ($_POST['note'] ?? '');

    if ($id > 0) {
        dbExec("
            UPDATE orario_vincolo_bilanciamento_pomeriggi
            SET
                nome = $nome,
                giorni_da_bilanciare = $giorni,
                livello = $livello,
                peso = $peso,
                scarto_massimo = $scartoSql,
                note = $note,
                updated_at = NOW()
            WHERE id = $id
              AND id_scenario = $idScenario
        ");
    } else {
        dbExec("
            INSERT INTO orario_vincolo_bilanciamento_pomeriggi (
                id_scenario,
                nome,
                giorni_da_bilanciare,
                livello,
                peso,
                scarto_massimo,
                note,
                attivo
            ) VALUES (
                $idScenario,
                $nome,
                $giorni,
                $livello,
                $peso,
                $scartoSql,
                $note,
                1
            )
        ");
    }

    ob_redirect("vincoli_pomeriggi.php?id_scenario=$idScenario");
}

if ($azione === 'elimina_bilanciamento') {
    dbExec("
        UPDATE orario_vincolo_bilanciamento_pomeriggi
        SET attivo = 0,
            updated_at = NOW()
        WHERE id = $id
          AND id_scenario = $idScenario
    ");

    ob_redirect("vincoli_pomeriggi.php?id_scenario=$idScenario");
}

die('Azione non valida');