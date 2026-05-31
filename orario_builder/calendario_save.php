<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/orario_builder_lib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti');

$azione = trim((string)($_POST['azione'] ?? ''));
$idScenario = ob_int($_POST['id_scenario'] ?? 0);

if ($idScenario <= 0) {
    die('Scenario non valido');
}

function backCalendario($idScenario) {
    ob_redirect("calendario.php?id_scenario=" . intval($idScenario));
}

if ($azione === 'salva_calendario') {
    $inizioLezioni = dbQNotNull($_POST['data_inizio_lezioni'] ?? '');
    $fineLezioni = dbQNotNull($_POST['data_fine_lezioni'] ?? '');

    $inizioP1 = dbQNotNull($_POST['data_inizio_primo_periodo'] ?? '');
    $fineP1 = dbQNotNull($_POST['data_fine_primo_periodo'] ?? '');

    $inizioP2 = dbQNotNull($_POST['data_inizio_secondo_periodo'] ?? '');
    $fineP2 = dbQNotNull($_POST['data_fine_secondo_periodo'] ?? '');

    $tipoPeriodo = dbQNotNull($_POST['tipo_periodo'] ?? 'TRIMESTRE_PENTAMESTRE');
    $note = dbQ($_POST['note'] ?? '');

    dbExec("
        INSERT INTO orario_calendario_scolastico (
            id_scenario,
            data_inizio_lezioni,
            data_fine_lezioni,
            data_inizio_primo_periodo,
            data_fine_primo_periodo,
            data_inizio_secondo_periodo,
            data_fine_secondo_periodo,
            tipo_periodo,
            note
        ) VALUES (
            $idScenario,
            $inizioLezioni,
            $fineLezioni,
            $inizioP1,
            $fineP1,
            $inizioP2,
            $fineP2,
            $tipoPeriodo,
            $note
        )
        ON DUPLICATE KEY UPDATE
            data_inizio_lezioni = VALUES(data_inizio_lezioni),
            data_fine_lezioni = VALUES(data_fine_lezioni),
            data_inizio_primo_periodo = VALUES(data_inizio_primo_periodo),
            data_fine_primo_periodo = VALUES(data_fine_primo_periodo),
            data_inizio_secondo_periodo = VALUES(data_inizio_secondo_periodo),
            data_fine_secondo_periodo = VALUES(data_fine_secondo_periodo),
            tipo_periodo = VALUES(tipo_periodo),
            note = VALUES(note),
            updated_at = NOW()
    ");

    backCalendario($idScenario);
}

if ($azione === 'salva_periodo') {
    $dataDalRaw = trim((string)($_POST['data_dal'] ?? ''));
    $dataAlRaw = trim((string)($_POST['data_al'] ?? ''));

    if ($dataDalRaw === '' || $dataAlRaw === '') {
        die('Date non valide');
    }

    $dal = new DateTime($dataDalRaw);
    $al = new DateTime($dataAlRaw);

    if ($al < $dal) {
        die('La data finale non può essere precedente alla data iniziale');
    }

    $tipo = dbQNotNull($_POST['tipo'] ?? 'VACANZA');
    $descrizione = dbQ($_POST['descrizione'] ?? '');
    $lezioniSospese = ob_int($_POST['lezioni_sospese'] ?? 1, 1);

    $giorno = clone $dal;

    while ($giorno <= $al) {
        $dataSql = dbQNotNull($giorno->format('Y-m-d'));

        dbExec("
            INSERT INTO orario_calendario_giorno_speciale (
                id_scenario,
                data_giorno,
                tipo,
                descrizione,
                lezioni_sospese
            ) VALUES (
                $idScenario,
                $dataSql,
                $tipo,
                $descrizione,
                $lezioniSospese
            )
            ON DUPLICATE KEY UPDATE
                descrizione = VALUES(descrizione),
                lezioni_sospese = VALUES(lezioni_sospese)
        ");

        $giorno->modify('+1 day');
    }

    backCalendario($idScenario);
}

if ($azione === 'salva_giorno') {
    $idGiorno = ob_int($_POST['id_giorno'] ?? 0);
    $dataGiorno = dbQNotNull($_POST['data_giorno'] ?? '');
    $tipo = dbQNotNull($_POST['tipo'] ?? 'VACANZA');
    $descrizione = dbQ($_POST['descrizione'] ?? '');
    $lezioniSospese = ob_int($_POST['lezioni_sospese'] ?? 1, 1);

    if ($idGiorno > 0) {
        dbExec("
            UPDATE orario_calendario_giorno_speciale
            SET
                data_giorno = $dataGiorno,
                tipo = $tipo,
                descrizione = $descrizione,
                lezioni_sospese = $lezioniSospese
            WHERE id = $idGiorno
              AND id_scenario = $idScenario
        ");
    } else {
        dbExec("
            INSERT INTO orario_calendario_giorno_speciale (
                id_scenario,
                data_giorno,
                tipo,
                descrizione,
                lezioni_sospese
            ) VALUES (
                $idScenario,
                $dataGiorno,
                $tipo,
                $descrizione,
                $lezioniSospese
            )
            ON DUPLICATE KEY UPDATE
                descrizione = VALUES(descrizione),
                lezioni_sospese = VALUES(lezioni_sospese)
        ");
    }

    backCalendario($idScenario);
}

if ($azione === 'elimina_giorno') {
    $idGiorno = ob_int($_POST['id_giorno'] ?? 0);

    dbExec("
        DELETE FROM orario_calendario_giorno_speciale
        WHERE id = $idGiorno
          AND id_scenario = $idScenario
    ");

    backCalendario($idScenario);
}

die('Azione non valida');