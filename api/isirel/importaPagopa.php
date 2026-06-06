<?php
declare(strict_types=1);

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [
    'https://istruzione.cloud.provincia.tn.it',
];

if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: https://istruzione.cloud.provincia.tn.it');
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 600');
header('Vary: Origin, Access-Control-Request-Headers');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../common/connect.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

function pagopaLog($msg) {
    $file = __DIR__ . '/../../log/isirel_pagopa_debug.log';
    @file_put_contents(
        $file,
        date('Y-m-d H:i:s') . ' - ' . $msg . PHP_EOL,
        FILE_APPEND
    );
}

set_error_handler(function($severity, $message, $file, $line) {
    pagopaLog("PHP ERROR [$severity] $message in $file:$line");
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function($e) {
    pagopaLog("FATAL: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});

pagopaLog('--- START importaPagopa.php ---');


function outJson($ok, $extra = [], $status = 200) {
    pagopaLog('OUT JSON status=' . $status . ' ok=' . ($ok ? 'true' : 'false') . ' extra=' . json_encode($extra));
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sqlExecOrThrow($query) {
    global $__con;

    if (!mysqli_query($__con, $query)) {
        throw new Exception(mysqli_error($__con) . "\n\nQUERY:\n" . $query);
    }
}

function sqlGetValueOrThrow($query) {
    global $__con;

    $result = mysqli_query($__con, $query);

    if (!$result) {
        throw new Exception(mysqli_error($__con) . "\n\nQUERY:\n" . $query);
    }

    $row = mysqli_fetch_array($result, MYSQLI_NUM);

    return is_array($row) ? $row[0] : null;
}

function readJsonBody() {
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        outJson(false, ['error' => 'Body JSON mancante'], 400);
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        outJson(false, ['error' => 'JSON non valido'], 400);
    }

    return $data;
}

function dateOrNull($value) {
    if ($value === null || $value === '') {
        return null;
    }

    $ts = strtotime((string)$value);

    if ($ts === false) {
        return null;
    }

    return date('Y-m-d', $ts);
}

function normalizzaCf($cf) {
    return strtoupper(str_replace(' ', '', trim((string)$cf)));
}

function trovaStudenteGestoreDaCf($cf) {
    $cf = normalizzaCf($cf);

    if ($cf === '') {
        return null;
    }

    $id = sqlGetValueOrThrow("
        SELECT id
        FROM studente
        WHERE UPPER(REPLACE(TRIM(codice_fiscale), ' ', '')) = " . dbQ($cf) . "
        LIMIT 1
    ");

    return $id ? intval($id) : null;
}

function rawJsonRidotto($recipient) {
    $student = $recipient['student'] ?? [];

    if (!is_array($student)) {
        $student = [];
    }

    $ridotto = [
        'idStudent' => $recipient['idStudent'] ?? null,
        'idClass' => $recipient['idClass'] ?? null,
        'idRecipient' => $recipient['idRecipient'] ?? null,
        'paymentState' => $recipient['paymentState'] ?? null,
        'paymentIuv' => $recipient['paymentIuv'] ?? null,
        'paymentAmount' => $recipient['paymentAmount'] ?? null,
        'paymentDate' => $recipient['paymentDate'] ?? null,
        'supplierCode' => $recipient['supplierCode'] ?? null,
        'assessmentCode' => $recipient['assessmentCode'] ?? null,
        'trasmissionState' => $recipient['trasmissionState'] ?? null,
        'cancelled' => $recipient['cancelled'] ?? null,
        'student' => [
            'id' => $student['id'] ?? null,
            'fiscalCode' => $student['fiscalCode'] ?? null,
            'lastName' => $student['lastName'] ?? null,
            'firstName' => $student['firstName'] ?? null,
            'email' => $student['email'] ?? null
        ]
    ];

    return json_encode($ridotto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

$data = readJsonBody();
pagopaLog('JSON letto. activities=' . (isset($data['activities']) && is_array($data['activities']) ? count($data['activities']) : 'NO'));

if (($data['source'] ?? '') !== 'ISIREL') {
    outJson(false, ['error' => 'Sorgente non valida'], 400);
}

$activities = $data['activities'] ?? null;

if (!is_array($activities)) {
    outJson(false, ['error' => 'Campo activities mancante o non valido'], 400);
}

$annoScolastico = $data['schoolYear'] ?? null;
$operatore = $data['operator'] ?? null;

$countAtt = 0;
$countAvv = 0;
$countMapped = 0;

try {
    sqlExecOrThrow("START TRANSACTION");

    foreach ($activities as $activity) {
        pagopaLog('Inizio activity');
        $idIsirel = intval($activity['id'] ?? 0);
        pagopaLog('Activity idIsirel=' . $idIsirel);
        if ($idIsirel <= 0) {
            continue;
        }

        $versione = intval($activity['version'] ?? 0);
        $idInstitute = $activity['idInstitute'] ?? null;
        $sendDate = dateOrNull($activity['sendDate'] ?? null);
        $dueDate = dateOrNull($activity['dueDate'] ?? null);
        $causal = $activity['causal'] ?? null;
        $descrizione = $activity['description'] ?? null;
        $importo = $activity['amount'] ?? null;
        $tipo = $activity['type'] ?? null;
        $tipoDescrizione = $activity['typeDescription'] ?? null;
        $tipologiaDescrizione = $activity['typologyDescription'] ?? null;
        pagopaLog('Prima INSERT pagopa_attivita idIsirel=' . $idIsirel);
        sqlExecOrThrow("
            INSERT INTO pagopa_attivita (
                id_isirel,
                versione,
                id_institute,
                send_date,
                due_date,
                causal,
                descrizione,
                importo,
                tipo,
                tipo_descrizione,
                tipologia_descrizione,
                anno_scolastico,
                origine
            ) VALUES (
                " . dbI($idIsirel) . ",
                " . dbI($versione) . ",
                " . dbI($idInstitute) . ",
                " . dbQ($sendDate) . ",
                " . dbQ($dueDate) . ",
                " . dbQ($causal) . ",
                " . dbQ($descrizione) . ",
                " . dbF($importo) . ",
                " . dbQ($tipo) . ",
                " . dbQ($tipoDescrizione) . ",
                " . dbQ($tipologiaDescrizione) . ",
                " . dbQ($annoScolastico) . ",
                'ISIREL'
            )
            ON DUPLICATE KEY UPDATE
                versione = VALUES(versione),
                id_institute = VALUES(id_institute),
                send_date = VALUES(send_date),
                due_date = VALUES(due_date),
                causal = VALUES(causal),
                descrizione = VALUES(descrizione),
                importo = VALUES(importo),
                tipo = VALUES(tipo),
                tipo_descrizione = VALUES(tipo_descrizione),
                tipologia_descrizione = VALUES(tipologia_descrizione),
                anno_scolastico = VALUES(anno_scolastico),
                updated_at = CURRENT_TIMESTAMP
        ");

        $idAttivitaGestore = sqlGetValueOrThrow("
            SELECT id
            FROM pagopa_attivita
            WHERE id_isirel = " . dbI($idIsirel) . "
            LIMIT 1
        ");
        pagopaLog('Dopo INSERT pagopa_attivita idIsirel=' . $idIsirel);
        if (!$idAttivitaGestore) {
            throw new Exception("Impossibile recuperare attività GestOre per ISIREL $idIsirel");
        }

        $idAttivitaGestore = intval($idAttivitaGestore);
        $countAtt++;

        $recipients = $activity['recipients'] ?? [];

        if (!is_array($recipients)) {
            $recipients = [];
        }

        foreach ($recipients as $recipient) {
            $idStudent = intval($recipient['idStudent'] ?? 0);
            $idRecipient = intval($recipient['idRecipient'] ?? 0);

            if ($idStudent <= 0 || $idRecipient <= 0) {
                continue;
            }

            $student = $recipient['student'] ?? [];

            if (!is_array($student)) {
                $student = [];
            }

            $idClass = $recipient['idClass'] ?? null;

            $codiceFiscale = $student['fiscalCode'] ?? null;
            $cognome = $student['lastName'] ?? null;
            $nome = $student['firstName'] ?? null;
            $email = $student['email'] ?? null;

            $idStudenteGestore = trovaStudenteGestoreDaCf($codiceFiscale);

            if ($idStudenteGestore) {
                $countMapped++;
            }

            $paymentState = $recipient['paymentState'] ?? null;
            $paymentLink = $recipient['paymentLink'] ?? null;
            $paymentIuv = $recipient['paymentIuv'] ?? null;
            $paymentAmount = $recipient['paymentAmount'] ?? null;
            $paymentDate = dateOrNull($recipient['paymentDate'] ?? null);

            $supplierCode = $recipient['supplierCode'] ?? null;
            $assessmentCode = $recipient['assessmentCode'] ?? null;
            $trasmissionState = $recipient['trasmissionState'] ?? null;
            $cancelled = !empty($recipient['cancelled']) ? 1 : 0;

            $rawJson = rawJsonRidotto($recipient);
            pagopaLog('Dopo INSERT avviso idRecipient=' . $idRecipient);
            sqlExecOrThrow("
                INSERT INTO pagopa_avvisi_studenti (
                    id_attivita,
                    id_isirel_attivita,
                    id_student_isirel,
                    id_class_isirel,
                    id_recipient_isirel,
                    id_studente_gestore,
                    codice_fiscale,
                    cognome,
                    nome,
                    email,
                    payment_state,
                    payment_link,
                    payment_iuv,
                    payment_amount,
                    payment_date,
                    supplier_code,
                    assessment_code,
                    trasmission_state,
                    cancelled,
                    raw_json
                ) VALUES (
                    " . dbI($idAttivitaGestore) . ",
                    " . dbI($idIsirel) . ",
                    " . dbI($idStudent) . ",
                    " . dbI($idClass) . ",
                    " . dbI($idRecipient) . ",
                    " . dbI($idStudenteGestore) . ",
                    " . dbQ($codiceFiscale) . ",
                    " . dbQ($cognome) . ",
                    " . dbQ($nome) . ",
                    " . dbQ($email) . ",
                    " . dbQ($paymentState) . ",
                    " . dbQ($paymentLink) . ",
                    " . dbQ($paymentIuv) . ",
                    " . dbF($paymentAmount) . ",
                    " . dbQ($paymentDate) . ",
                    " . dbQ($supplierCode) . ",
                    " . dbQ($assessmentCode) . ",
                    " . dbQ($trasmissionState) . ",
                    " . dbI($cancelled) . ",
                    " . dbQ($rawJson) . "
                )
                ON DUPLICATE KEY UPDATE
                    id_attivita = VALUES(id_attivita),
                    id_isirel_attivita = VALUES(id_isirel_attivita),
                    id_student_isirel = VALUES(id_student_isirel),
                    id_class_isirel = VALUES(id_class_isirel),
                    id_studente_gestore = VALUES(id_studente_gestore),
                    codice_fiscale = VALUES(codice_fiscale),
                    cognome = VALUES(cognome),
                    nome = VALUES(nome),
                    email = VALUES(email),
                    payment_state = VALUES(payment_state),
                    payment_link = VALUES(payment_link),
                    payment_iuv = VALUES(payment_iuv),
                    payment_amount = VALUES(payment_amount),
                    payment_date = VALUES(payment_date),
                    supplier_code = VALUES(supplier_code),
                    assessment_code = VALUES(assessment_code),
                    trasmission_state = VALUES(trasmission_state),
                    cancelled = VALUES(cancelled),
                    raw_json = VALUES(raw_json),
                    updated_at = CURRENT_TIMESTAMP
            ");

            $countAvv++;
        }
    }

    sqlExecOrThrow("
        INSERT INTO pagopa_import_log (
            origine,
            operatore,
            anno_scolastico,
            numero_attivita,
            numero_avvisi,
            esito,
            messaggio
        ) VALUES (
            'ISIREL',
            " . dbQ($operatore) . ",
            " . dbQ($annoScolastico) . ",
            " . dbI($countAtt) . ",
            " . dbI($countAvv) . ",
            'OK',
            " . dbQ("Importazione completata. Studenti mappati: $countMapped") . "
        )
    ");

    sqlExecOrThrow("COMMIT");

    outJson(true, [
        'activities' => $countAtt,
        'recipients' => $countAvv,
        'mappedStudents' => $countMapped
    ]);

} catch (Throwable $e) {
    global $__con;

    mysqli_query($__con, "ROLLBACK");

    outJson(false, [
        'error' => $e->getMessage()
    ], 500);
}
