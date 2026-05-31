<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';
require_once __DIR__ . '/orario_builder_lib.php';

ruoloRichiesto('admin', 'dirigente', 'segreteria-docenti');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$idScenario = ob_int($_POST['id_scenario'] ?? 0);

if ($idScenario <= 0) {
    die('Scenario non valido');
}

if (empty($_FILES['csv_file']['tmp_name'])) {
    die('File CSV mancante');
}

$tmp = $_FILES['csv_file']['tmp_name'];

$handle = fopen($tmp, 'r');
if (!$handle) {
    die('Impossibile leggere il file');
}

$importate = 0;
$errori = [];

$header = fgetcsv($handle, 0, ';');

while (($row = fgetcsv($handle, 0, ';')) !== false) {
    $classeCsv = strtoupper(trim((string)($row[0] ?? '')));
    $codiceMateria = strtoupper(trim((string)($row[1] ?? '')));
    $nomeMateria = trim((string)($row[2] ?? ''));
    $ore = ob_float($row[3] ?? 0);
    $cognomeDocente = strtoupper(trim((string)($row[4] ?? '')));
    $nomeDocente = strtoupper(trim((string)($row[5] ?? '')));

    if ($classeCsv === '' || $codiceMateria === '' || $cognomeDocente === '') {
        continue;
    }

    $idClasse = trovaClasseImport($classeCsv);

    if ($idClasse <= 0) {
        $classeCsvSql = dbQNotNull($classeCsv);

        dbExec("
        INSERT INTO orario_import_classe_alias (
            alias_classe,
            id_classe,
            note
        ) VALUES (
            $classeCsvSql,
            NULL,
            'Da abbinare - rilevata automaticamente da import CSV'
        )
        ON DUPLICATE KEY UPDATE
            note = VALUES(note)
    ");

        $errori[] = "Classe non trovata, aggiunta agli alias da abbinare: $classeCsv";
        continue;
    }

    $idMateria = trovaMateriaImport($codiceMateria, $nomeMateria);

    if ($idMateria <= 0) {
        $errori[] = "Materia non trovata: $codiceMateria - $nomeMateria";
        continue;
    }

    $docente = trovaDocenteImport($cognomeDocente, $nomeDocente);

    if ($docente) {
        $idDocenteSql = intval($docente['id']);
        $docenteTemporaneoSql = "NULL";
        $docenteDaNominare = 0;
        $docenteKey = 'DOCENTE_' . intval($docente['id']);
    } else {
        $tmpName = trim($cognomeDocente . ' ' . $nomeDocente);
        $tmpName = preg_replace('/\s+/', ' ', $tmpName);
        $tmpKey = strtoupper(preg_replace('/\s+/', '', $tmpName));
        $tmpKeySql = dbQNotNull($tmpKey);

        $idDocenteAlias = dbGetValue("
    SELECT id_docente
    FROM orario_import_docente_temporaneo_alias
    WHERE docente_temporaneo = $tmpKeySql
    LIMIT 1
");

        if ($idDocenteAlias) {
            $idDocenteSql = intval($idDocenteAlias);
            $docenteTemporaneoSql = "NULL";
            $docenteDaNominare = 0;
            $docenteKey = 'DOCENTE_' . intval($idDocenteAlias);
        } else {
            $idDocenteSql = "NULL";
            $docenteTemporaneoSql = dbQNotNull($tmpName);
            $docenteDaNominare = 1;
            $docenteKey = 'TEMP_' . $tmpKey;
        }
    }

    $docenteKeySql = dbQNotNull($docenteKey);

    dbExec("
        INSERT INTO orario_docente_insegna_scenario (
            id_scenario,
            id_docente,
            docente_temporaneo,
            docente_da_nominare,
            docente_key,
            id_classe,
            id_materia,
            origine,
            note
        ) VALUES (
            $idScenario,
            $idDocenteSql,
            $docenteTemporaneoSql,
            $docenteDaNominare,
            $docenteKeySql,
            $idClasse,
            $idMateria,
            'IMPORT_FILE',
            " . dbQ("CSV: $classeCsv; $codiceMateria; $nomeMateria; ore=$ore") . "
        )
        ON DUPLICATE KEY UPDATE
            id_docente = VALUES(id_docente),
            docente_temporaneo = VALUES(docente_temporaneo),
            docente_da_nominare = VALUES(docente_da_nominare),
            docente_key = VALUES(docente_key),
            origine = VALUES(origine),
            note = VALUES(note),
            updated_at = NOW()
    ");

    $importate++;
}

fclose($handle);

$_SESSION['orario_import_msg'] = "Importate $importate righe. Errori: " . count($errori);
$_SESSION['orario_import_errori'] = $errori;

ob_redirect("docenti_materie.php?id_scenario=$idScenario");


function trovaClasseImport($classeCsv)
{
    $classeSql = dbQNotNull($classeCsv);

    $id = dbGetValue("
        SELECT id
        FROM classi
        WHERE UPPER(TRIM(classe)) = $classeSql
          AND attiva = 1
        LIMIT 1
    ");

    if ($id) return intval($id);

    $id = dbGetValue("
        SELECT id_classe
        FROM orario_import_classe_alias
        WHERE UPPER(TRIM(alias_classe)) = $classeSql
        LIMIT 1
    ");

    return $id ? intval($id) : 0;
}

function trovaMateriaImport($codiceMateria, $nomeMateria)
{
    $codiceSql = dbQNotNull($codiceMateria);

    $id = dbGetValue("
        SELECT id
        FROM materia
        WHERE UPPER(TRIM(codice)) = $codiceSql
        LIMIT 1
    ");

    if ($id) return intval($id);

    $nomePulito = preg_replace('/\s*\([TL]\)\s*$/i', '', $nomeMateria);
    $nomeSql = dbQNotNull($nomePulito);

    $id = dbGetValue("
        SELECT id
        FROM materia
        WHERE UPPER(TRIM(nome)) = UPPER($nomeSql)
        LIMIT 1
    ");

    return $id ? intval($id) : 0;
}

function trovaDocenteImport($cognome, $nome)
{
    $cognomeSql = dbQNotNull($cognome);
    $nomeSql = dbQNotNull($nome);

    return dbGetFirst("
        SELECT id, cognome, nome
        FROM docente
        WHERE UPPER(TRIM(cognome)) = $cognomeSql
          AND UPPER(TRIM(nome)) = $nomeSql
          AND attivo = 1
        LIMIT 1
    ");
}
