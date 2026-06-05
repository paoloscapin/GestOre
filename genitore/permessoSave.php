<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/permessi_uscita_lib.php';

ruoloRichiesto('segreteria-didattica', 'dirigente', 'genitore');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect("/error/unauthorized.php");
    exit;
}

function permessiFailUnauthorized()
{
    redirect("/error/unauthorized.php");
    exit;
}

function permessiFailJson(string $message)
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => $message
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function permessiGenitoreGiorniFestivi(): array
{
    $raw = getSettingsValue('permessi', 'giorni_festivi', []);
    if ($raw instanceof stdClass) {
        $raw = (array)$raw;
    }
    if (is_string($raw)) {
        $raw = preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);
    }
    if (!is_array($raw)) {
        return [];
    }

    return array_values(array_unique(array_filter(array_map('strval', $raw), function ($date) {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
    })));
}

function permessiGenitoreIsGiornoRichiedibile(DateTimeImmutable $date, array $holidays): bool
{
    if (in_array((int)$date->format('w'), [0, 6], true)) {
        return false;
    }
    return !in_array($date->format('Y-m-d'), $holidays, true);
}

function permessiGenitoreDateRichiedibili(): array
{
    $timezone = new DateTimeZone('Europe/Rome');
    $now = new DateTimeImmutable('now', $timezone);
    $limit = trim((string)getSettingsValue('permessi', 'ora_limite_genitori', '09:00'));
    $holidays = permessiGenitoreGiorniFestivi();

    if (!preg_match('/^\d{1,2}:\d{2}$/', $limit)) {
        $limit = '09:00';
    }

    [$limitHour, $limitMinute] = array_map('intval', explode(':', $limit));
    $cutoff = $now->setTime($limitHour, $limitMinute, 0);
    $firstDate = $now;

    if ($now >= $cutoff) {
        $firstDate = $firstDate->modify('+1 day');
    }

    $dates = [];
    $cursor = $firstDate;
    $guard = 0;
    while (count($dates) < 4 && $guard < 30) {
        if (permessiGenitoreIsGiornoRichiedibile($cursor, $holidays)) {
            $dates[] = $cursor->format('Y-m-d');
        }
        $cursor = $cursor->modify('+1 day');
        $guard++;
    }

    return $dates;
}

function permessiValidateGenitoreData(string $data): void
{
    $selected = DateTimeImmutable::createFromFormat('!Y-m-d', $data, new DateTimeZone('Europe/Rome'));
    $errors = DateTimeImmutable::getLastErrors();
    if (!$selected || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        permessiFailJson('Data del permesso non valida.');
    }

    $allowedDates = permessiGenitoreDateRichiedibili();
    if (!in_array($selected->format('Y-m-d'), $allowedDates, true)) {
        permessiFailJson('La data selezionata non e\' disponibile per la richiesta del permesso.');
    }
}

function canGenitoreAccessStudente($idStudente, $idGenitore)
{
    $idStudente = (int)$idStudente;
    $idGenitore = (int)$idGenitore;

    if ($idStudente <= 0 || $idGenitore <= 0) {
        return false;
    }

    $q = "
        SELECT id_studente
        FROM genitori_studenti
        WHERE id_genitore = " . dbI($idGenitore) . "
          AND id_studente = " . dbI($idStudente) . "
        LIMIT 1
    ";

    $row = dbGetFirst($q);
    return is_array($row) && !empty($row['id_studente']);
}

function getPermessoAutorizzato($idPermesso, $idGenitore, $isGenitore)
{
    $idPermesso = (int)$idPermesso;
    $idGenitore = (int)$idGenitore;

    if ($idPermesso <= 0) {
        return null;
    }

    $whereExtra = "";
    if ($isGenitore) {
        $whereExtra = "
            AND EXISTS (
                SELECT 1
                FROM genitori_studenti gs
                WHERE gs.id_genitore = " . dbI($idGenitore) . "
                  AND gs.id_studente = permessi_uscita.id_studente
            )
        ";
    }

    $q = "
        SELECT *
        FROM permessi_uscita
        WHERE id = " . dbI($idPermesso) . "
        $whereExtra
        LIMIT 1
    ";

    return dbGetFirst($q);
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$data = escapePost('data');
$ora_uscita = escapePost('ora_uscita');
$motivo = escapePost('motivo');
$ora_rientro = trim((string)escapePost('ora_rientro'));
$rientro = isset($_POST['rientro']) ? (int)$_POST['rientro'] : 0;

if ($rientro === 1) {
    if ($ora_rientro === '') {
        permessiFailUnauthorized();
    }
} else {
    $ora_rientro = '00:00:00';
}
$id_studente = isset($_POST['id_studente']) ? (int)$_POST['id_studente'] : 0;

if ($id_studente <= 0 || $data === '' || $ora_uscita === '' || $motivo === '') {
    permessiFailUnauthorized();
}

$isGenitore = impersonaRuolo('genitore');

if ($isGenitore && !canGenitoreAccessStudente($id_studente, (int)$__genitore_id)) {
    permessiFailUnauthorized();
}

if ($isGenitore) {
    permessiValidateGenitoreData($data);
}

if ($id > 0) {
    $permesso = getPermessoAutorizzato($id, (int)$__genitore_id, $isGenitore);
    if (!$permesso) {
        permessiFailUnauthorized();
    }

    if ($isGenitore) {
        if ((int)$permesso['stato'] !== 1) {
            permessiFailUnauthorized();
        }
        if ((int)$permesso['id_studente'] !== $id_studente) {
            permessiFailUnauthorized();
        }
    }

    $query = "
        UPDATE permessi_uscita
        SET data = " . dbQ($data) . ",
            ora_uscita = " . dbQ($ora_uscita) . ",
            ora_rientro = " . dbQ($ora_rientro) . ",
            motivo = " . dbQ($motivo) . ",
            rientro = " . dbI($rientro) . "
        WHERE id = " . dbI($id);

    dbExec($query);
    info("aggiornato permesso id=$id");
} else {
    $existingDuplicate = dbGetFirst("
        SELECT id
        FROM permessi_uscita
        WHERE id_genitore = " . dbI((int)$__genitore_id) . "
          AND id_studente = " . dbI($id_studente) . "
          AND data = " . dbQ($data) . "
          AND TIME_FORMAT(ora_uscita, '%H:%i') = " . dbQ(permessiUscitaFormatTime($ora_uscita)) . "
          AND rientro = " . dbI($rientro) . "
          AND TIME_FORMAT(ora_rientro, '%H:%i') = " . dbQ(permessiUscitaFormatTime($ora_rientro)) . "
          AND stato IN (1, 2)
        ORDER BY id ASC
        LIMIT 1
    ");

    if ($existingDuplicate) {
        $id = (int)$existingDuplicate['id'];
        info("permesso duplicato ignorato, uso id esistente=$id");
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'id' => $id,
            'duplicate' => true
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    $query = "
        INSERT INTO permessi_uscita (
            id_genitore,
            id_studente,
            data,
            ora_uscita,
            ora_rientro,
            rientro,
            motivo,
            stato
        ) VALUES (
            " . dbI((int)$__genitore_id) . ",
            " . dbI($id_studente) . ",
            " . dbQ($data) . ",
            " . dbQ($ora_uscita) . ",
            " . dbQ($ora_rientro) . ",
            " . dbI($rientro) . ",
            " . dbQ($motivo) . ",
            1
        )
    ";

    dbExec($query);
    $id = dbLastId();
    info("inserito nuovo permesso id=$id");
    permessiUscitaSendParentMail((int)$id, 'creazione');
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'id' => $id
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
