<?php

require_once '../common/checkSession.php';
require_once '../common/mastercom/admin_lib.php';

ruoloRichiesto('admin', 'segreteria-didattica');

function mastercomAbsenceDeleteClean($value): string
{
    return trim((string)(mastercomAdminCleanText($value) ?? ''));
}

function mastercomAbsenceDeleteStudent(int $studentId): ?array
{
    if ($studentId <= 0 || !mastercomAdminTableExists('mastercom_studenti')) {
        return null;
    }

    return dbGetFirst("SELECT * FROM mastercom_studenti WHERE mastercom_id_studente = " . intval($studentId) . " LIMIT 1");
}

function mastercomAbsenceDeleteClassName(int $classId): string
{
    if ($classId <= 0 || !mastercomAdminTableExists('mastercom_classi')) {
        return '';
    }

    return mastercomAbsenceDeleteClean(dbGetValue("SELECT nome FROM mastercom_classi WHERE mastercom_id_classe = " . intval($classId) . " LIMIT 1") ?? '');
}

$studentId = intval($_POST['student_id'] ?? 0);
$classId = intval($_POST['class_id'] ?? 0);
$absenceId = intval($_POST['id_assenza'] ?? 0);
$absenceDate = intval($_POST['data_assenza'] ?? 0);
$studentRow = mastercomAbsenceDeleteStudent($studentId);

$studentSurname = $studentRow != null ? mastercomAbsenceDeleteClean($studentRow['cognome'] ?? '') : mastercomAbsenceDeleteClean($_POST['cognome'] ?? '');
$studentName = $studentRow != null ? mastercomAbsenceDeleteClean($studentRow['nome'] ?? '') : mastercomAbsenceDeleteClean($_POST['nome'] ?? '');
if ($classId <= 0 && $studentRow != null) {
    $classId = intval($studentRow['mastercom_id_classe_corrente'] ?? 0);
}
$className = mastercomAbsenceDeleteClassName($classId);
if ($className === '') {
    $className = mastercomAbsenceDeleteClean($_POST['classe'] ?? '');
}

$redirectUrl = 'mastercom_presence.php?class_id=' . intval($classId);
$message = '';

if ($studentId <= 0 || $classId <= 0 || $absenceId <= 0 || $absenceDate <= 0) {
    $message = 'Parametri cancellazione assenza mancanti';
} else {
    $authResult = mastercomAuthenticateService([
        'profile' => 'MasterComAuth',
        'method' => 'POST',
        'timeout' => 60,
    ]);

    if (!$authResult['ok']) {
        $message = 'Autenticazione MasterCom amministratore fallita';
    } else {
        $payload = [
            'x' => '25',
            'y' => '16',
            'form_stato' => 'amministratore',
            'stato_principale' => 'assenze_principale',
            'stato_secondario' => 'elimina_assenza_studente',
            'id_classe' => $classId,
            'classe' => $className,
            'indirizzo' => '',
            'id_indirizzo' => '',
            'id_stud' => $studentId,
            'cognome_stud' => $studentSurname,
            'nome_stud' => $studentName,
            'id_assenza' => $absenceId,
            'data_assenza' => $absenceDate,
            'operazione_sorgente' => '',
            'parametro_nome' => '',
            'parametro_cognome' => '',
            'parametro_indirizzo_abitazione' => '',
            'parametro_luogo_nascita' => '',
            'parametro_citta' => '',
            'parametro_provincia' => '',
            'parametro_sesso' => '',
            'parametro_tel_cell' => '',
            'parametro_email' => '',
            'parametro_matricola' => '',
            'parametro_cap' => '',
            'parametro_nome_genitore' => '',
            'parametro_cognome_genitore' => '',
            'inserimento_diretto' => '',
        ];

        $submitResult = mastercomSubmitAdminAbsenceAction($authResult, $payload, [
            'method' => 'POST',
            'timeout' => 120,
            'send_in_body' => false,
        ]);

        $message = $submitResult['ok']
            ? 'Assenza eliminata da MasterCom'
            : ('Eliminazione assenza MasterCom fallita: ' . ($submitResult['error'] ?? 'SUBMIT_FAILED'));
    }
}

header('Location: ' . $redirectUrl . '&message=' . urlencode($message));
exit;
