<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('dirigente', 'segreteria-ata');

header('Content-Type: application/json; charset=utf-8');

function paEsc($s)
{
    if (isset($GLOBALS['__conn']) && $GLOBALS['__conn']) {
        return mysqli_real_escape_string($GLOBALS['__conn'], $s);
    }
    if (isset($GLOBALS['conn']) && $GLOBALS['conn']) {
        return mysqli_real_escape_string($GLOBALS['conn'], $s);
    }
    return addslashes($s);
}

function paJsonError($msg, $code = 400)
{
    http_response_code($code);
    echo json_encode([
        'ok' => false,
        'message' => $msg
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function paNorm($s)
{
    return trim((string)$s);
}
    
function paNormUpper($s)
{
    return mb_strtoupper(trim((string)$s), 'UTF-8');
}

function paCsvRowAssoc($header, $row)
{
    $assoc = [];
    foreach ($header as $i => $h) {
        $assoc[$h] = isset($row[$i]) ? trim((string)$row[$i]) : '';
    }
    return $assoc;
}

if (!isset($_FILES['import_file']) || !is_uploaded_file($_FILES['import_file']['tmp_name'])) {
    paJsonError('File non caricato.');
}

$hasHeader = isset($_POST['has_header']) ? intval($_POST['has_header']) : 1;
$tmp = $_FILES['import_file']['tmp_name'];

$fp = fopen($tmp, 'r');
if (!$fp) {
    paJsonError('Impossibile leggere il file.');
}

$header = [];
if ($hasHeader) {
    $header = fgetcsv($fp, 0, ';');
    if (!$header) {
        fclose($fp);
        paJsonError('Intestazione CSV non valida.');
    }
    $header = array_map(function ($v) {
        $v = (string)$v;

        // rimuove BOM UTF-8 / Unicode dalla prima intestazione
        $v = preg_replace('/^\xEF\xBB\xBF/', '', $v);
        $v = preg_replace('/^\x{FEFF}/u', '', $v);

        $v = trim($v);
        $v = mb_strtolower($v, 'UTF-8');

        return $v;
    }, $header);
} else {
    $header = ['cognome', 'nome', 'email', 'username', 'matricola', 'tipo_contratto', 'codice_fiscale', 'profilo', 'ufficio'];
}

$required = ['cognome', 'nome', 'username'];
foreach ($required as $req) {
    if (!in_array($req, $header, true)) {
        fclose($fp);
        paJsonError("Colonna obbligatoria mancante: $req");
    }
}

$allowedContratti = [
    '',
    'INDETERMINATO',
    'DETERMINATO ANNUALE',
    'DETERMINATO BREVE'
];

$inserted = 0;
$updated = 0;
$officeChanges = 0;
$errors = [];
$rowNum = $hasHeader ? 2 : 1;

while (($row = fgetcsv($fp, 0, ';')) !== false) {
    if ($row === [null] || !count(array_filter($row, fn($v) => trim((string)$v) !== ''))) {
        $rowNum++;
        continue;
    }

    $r = paCsvRowAssoc($header, $row);

    $cognome = paNorm($r['cognome'] ?? '');
    $nome = paNorm($r['nome'] ?? '');
    $email = paNorm($r['email'] ?? '');
    $username = paNorm($r['username'] ?? '');
    $matricola = paNorm($r['matricola'] ?? '');
    $tipoContratto = paNormUpper($r['tipo_contratto'] ?? '');
    $codiceFiscale = paNormUpper($r['codice_fiscale'] ?? '');
    $profiloInput = paNorm($r['profilo'] ?? '');
    $ufficioInput = paNorm($r['ufficio'] ?? '');

    if ($cognome === '' || $nome === '' || $username === '') {
        $errors[] = "Riga $rowNum: cognome, nome e username sono obbligatori.";
        $rowNum++;
        continue;
    }

    if (!in_array($tipoContratto, $allowedContratti, true)) {
        $errors[] = "Riga $rowNum: tipo_contratto non valido [$tipoContratto].";
        $rowNum++;
        continue;
    }

    // profilo: provo prima per codice, poi per nome
    $idProfiloSql = "NULL";
    if ($profiloInput !== '') {
        $profilo = dbGetFirst("
            SELECT id, codice, nome
            FROM personale_ata_profili
            WHERE UPPER(codice) = '" . paEsc(strtoupper($profiloInput)) . "'
               OR UPPER(nome) = '" . paEsc(strtoupper($profiloInput)) . "'
            LIMIT 1
        ");

        if (!$profilo) {
            $errors[] = "Riga $rowNum: profilo non trovato [$profiloInput].";
            $rowNum++;
            continue;
        }

        $idProfiloSql = intval($profilo['id']);
    }

    // ufficio: per nome o codice
    $idUfficio = 0;
    if ($ufficioInput !== '') {
        $ufficio = dbGetFirst("
            SELECT id, nome, codice
            FROM personale_ata_uffici
            WHERE UPPER(nome) = '" . paEsc(strtoupper($ufficioInput)) . "'
               OR UPPER(codice) = '" . paEsc(strtoupper($ufficioInput)) . "'
            LIMIT 1
        ");

        if (!$ufficio) {
            $errors[] = "Riga $rowNum: ufficio non trovato [$ufficioInput].";
            $rowNum++;
            continue;
        }

        $idUfficio = intval($ufficio['id']);
    }

    $existing = dbGetFirst("
        SELECT *
        FROM personale_ata
        WHERE username = '" . paEsc($username) . "'
        LIMIT 1
    ");

    if ($existing) {
        dbExec("
            UPDATE personale_ata SET
                cognome = '" . paEsc($cognome) . "',
                nome = '" . paEsc($nome) . "',
                email = '" . paEsc($email) . "',
                matricola = '" . paEsc($matricola) . "',
                tipo_contratto = '" . paEsc($tipoContratto) . "',
                codice_fiscale = '" . paEsc($codiceFiscale) . "',
                id_profilo = " . ($idProfiloSql === "NULL" ? "NULL" : $idProfiloSql) . "
            WHERE id = " . intval($existing['id']) . "
            LIMIT 1
        ");
        $updated++;

        // gestione ufficio corrente
        $current = dbGetFirst("
            SELECT *
            FROM personale_ata_assegnazioni
            WHERE username = '" . paEsc($username) . "'
              AND (attiva = 1 OR data_fine IS NULL)
            ORDER BY data_inizio DESC, id DESC
            LIMIT 1
        ");

        $currentUfficioId = $current ? intval($current['id_ufficio']) : 0;

        if ($idUfficio > 0 && $currentUfficioId !== $idUfficio) {
            $today = date('Y-m-d');

            if ($current) {
                $currentDataInizio = $current['data_inizio'] ?? '';

                if ($currentDataInizio === $today) {
                    dbExec("
                        UPDATE personale_ata_assegnazioni
                        SET id_ufficio = $idUfficio
                        WHERE id = " . intval($current['id']) . "
                        LIMIT 1
                    ");
                } else {
                    dbExec("
                        UPDATE personale_ata_assegnazioni
                        SET data_fine = DATE_SUB('$today', INTERVAL 1 DAY),
                            attiva = 0
                        WHERE id = " . intval($current['id']) . "
                        LIMIT 1
                    ");

                    dbExec("
                        INSERT INTO personale_ata_assegnazioni
                            (username, id_ufficio, data_inizio, data_fine, attiva)
                        VALUES
                            (
                                '" . paEsc($username) . "',
                                $idUfficio,
                                '$today',
                                NULL,
                                1
                            )
                    ");
                }

                $officeChanges++;
            } else {
                dbExec("
                    INSERT INTO personale_ata_assegnazioni
                        (username, id_ufficio, data_inizio, data_fine, attiva)
                    VALUES
                        (
                            '" . paEsc($username) . "',
                            $idUfficio,
                            '" . date('Y-m-d') . "',
                            NULL,
                            1
                        )
                ");
                $officeChanges++;
            }
        }
    } else {
        dbExec("
            INSERT INTO personale_ata
                (cognome, nome, email, username, matricola, tipo_contratto, codice_fiscale, id_profilo, attivo)
            VALUES
                (
                    '" . paEsc($cognome) . "',
                    '" . paEsc($nome) . "',
                    '" . paEsc($email) . "',
                    '" . paEsc($username) . "',
                    '" . paEsc($matricola) . "',
                    '" . paEsc($tipoContratto) . "',
                    '" . paEsc($codiceFiscale) . "',
                    " . ($idProfiloSql === "NULL" ? "NULL" : $idProfiloSql) . ",
                    1
                )
        ");

        if ($idUfficio > 0) {
            dbExec("
                INSERT INTO personale_ata_assegnazioni
                    (username, id_ufficio, data_inizio, data_fine, attiva)
                VALUES
                    (
                        '" . paEsc($username) . "',
                        $idUfficio,
                        '" . date('Y-m-d') . "',
                        NULL,
                        1
                    )
            ");
            $officeChanges++;
        }

        $inserted++;
    }

    $rowNum++;
}

fclose($fp);

echo json_encode([
    'ok' => true,
    'inserted' => $inserted,
    'updated' => $updated,
    'office_changes' => $officeChanges,
    'errors' => $errors
], JSON_UNESCAPED_UNICODE);
