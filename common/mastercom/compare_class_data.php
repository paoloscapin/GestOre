<?php

/**
 *  This file is part of GestOre
 *  @author     OpenAI Codex
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/../checkSession.php';
require_once __DIR__ . '/../__MasterCom.php';

ruoloRichiesto('segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

$classId = intval($_GET['class_id'] ?? $_POST['class_id'] ?? 0);
if ($classId <= 0) {
    echo json_encode([
        'ok' => false,
        'message' => 'Parametro class_id mancante o non valido',
    ]);
    exit;
}

function mcNorm(?string $value): string
{
    $value = trim((string)$value);
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = mb_strtoupper($value, 'UTF-8');
    return preg_replace('/\s+/', ' ', $value);
}

function mcNormCompact(?string $value): string
{
    return preg_replace('/[^A-Z0-9]/', '', mcNorm($value));
}

function mcFirstRecord(?array $response): ?array
{
    if (!is_array($response)) {
        return null;
    }
    if (array_keys($response) === range(0, count($response) - 1)) {
        return $response[0] ?? null;
    }
    return $response;
}

function findLocalStudentForMastercom(array $masterDetail, array $masterSummary): ?array
{
    global $__anno_scolastico_corrente_id;

    $conditions = [];
    $cf = trim((string)($masterDetail['cf'] ?? ''));
    $email = trim((string)($masterSummary['email1'] ?? $masterDetail['email'] ?? ''));
    $cognome = trim((string)($masterSummary['cognome'] ?? $masterDetail['surname'] ?? ''));
    $nome = trim((string)($masterSummary['nome'] ?? $masterDetail['first_name'] ?? ''));

    if ($cf !== '') {
        $conditions[] = "LOWER(s.codice_fiscale) = LOWER(" . dbQ($cf) . ")";
    }
    if ($email !== '') {
        $conditions[] = "LOWER(s.email) = LOWER(" . dbQ($email) . ")";
    }
    if ($cognome !== '' && $nome !== '') {
        $conditions[] = "(LOWER(s.cognome) = LOWER(" . dbQ($cognome) . ") AND LOWER(s.nome) = LOWER(" . dbQ($nome) . "))";
    }

    if (empty($conditions)) {
        return null;
    }

    $query = "
        SELECT
            s.*,
            sf.id_classe AS id_classe_corrente,
            c.classe AS classe_corrente
        FROM studente s
        LEFT JOIN studente_frequenta sf
            ON sf.id_studente = s.id
            AND sf.id_anno_scolastico = " . intval($__anno_scolastico_corrente_id) . "
        LEFT JOIN classi c
            ON c.id = sf.id_classe
        WHERE " . implode(' OR ', $conditions) . "
        ORDER BY s.attivo DESC, s.id DESC
        LIMIT 1
    ";

    return dbGetFirst($query);
}

function findLocalParentForMastercom(array $masterSummary, array $masterDetail): ?array
{
    $conditions = [];
    $cf = trim((string)($masterSummary['codice_fiscale'] ?? $masterDetail['cf'] ?? ''));
    $email = trim((string)($masterDetail['email'] ?? ''));
    $cognome = trim((string)($masterSummary['cognome'] ?? $masterDetail['surname'] ?? ''));
    $nome = trim((string)($masterSummary['nome'] ?? $masterDetail['first_name'] ?? ''));

    if ($cf !== '') {
        $conditions[] = "LOWER(g.codice_fiscale) = LOWER(" . dbQ($cf) . ")";
    }
    if ($email !== '') {
        $conditions[] = "LOWER(g.email) = LOWER(" . dbQ($email) . ")";
    }
    if ($cognome !== '' && $nome !== '') {
        $conditions[] = "(LOWER(g.cognome) = LOWER(" . dbQ($cognome) . ") AND LOWER(g.nome) = LOWER(" . dbQ($nome) . "))";
    }

    if (empty($conditions)) {
        return null;
    }

    $query = "
        SELECT g.*
        FROM genitori g
        WHERE " . implode(' OR ', $conditions) . "
        ORDER BY g.attivo DESC, g.id DESC
        LIMIT 1
    ";

    return dbGetFirst($query);
}

function compareField(array &$diffs, string $field, $localValue, $masterValue, callable $normalizer = null): void
{
    $normalizer = $normalizer ?? function ($v) {
        return trim((string)$v);
    };

    $localNorm = $normalizer($localValue);
    $masterNorm = $normalizer($masterValue);
    if ($localNorm !== $masterNorm) {
        $diffs[$field] = [
            'local' => $localValue,
            'mastercom' => $masterValue,
        ];
    }
}

$teacherAuth = mastercomAuthenticateService([
    'profile' => 'MasterComDocenteAuth',
    'method' => 'POST',
    'timeout' => 60,
]);
if (!$teacherAuth['ok']) {
    echo json_encode([
        'ok' => false,
        'message' => 'Autenticazione MasterCom docente fallita',
        'error' => $teacherAuth['error'] ?? 'AUTH_FAILED',
    ]);
    exit;
}

$adminAuth = mastercomAuthenticateService([
    'profile' => 'MasterComAuth',
    'method' => 'POST',
    'timeout' => 60,
]);
if (!$adminAuth['ok']) {
    echo json_encode([
        'ok' => false,
        'message' => 'Autenticazione MasterCom amministratore fallita',
        'error' => $adminAuth['error'] ?? 'AUTH_FAILED',
    ]);
    exit;
}

$studentsResult = mastercomLoadStudentsList($teacherAuth, $classId, [
    'method' => 'POST',
    'timeout' => 120,
]);
if (!$studentsResult['ok']) {
    echo json_encode([
        'ok' => false,
        'message' => 'Caricamento studenti classe MasterCom fallito',
        'error' => $studentsResult['error'] ?? 'LOAD_FAILED',
    ]);
    exit;
}

$masterStudents = $studentsResult['response']['result'] ?? [];
$studentComparisons = [];
$masterStudentToLocal = [];

foreach ($masterStudents as $masterStudent) {
    $studentId = intval($masterStudent['id_studente'] ?? 0);
    $detailResult = mastercomLoadStudentDetails($teacherAuth, $studentId, [
        'method' => 'GET',
        'timeout' => 120,
    ]);
    $detail = mcFirstRecord($detailResult['response'] ?? null) ?? [];
    $local = findLocalStudentForMastercom($detail, $masterStudent);

    $diffs = [];
    if ($local == null) {
        $diffs['missing_local_student'] = true;
    } else {
        $masterStudentToLocal[$studentId] = intval($local['id']);

        compareField($diffs, 'cognome', $local['cognome'] ?? '', $masterStudent['cognome'] ?? $detail['surname'] ?? '', 'mcNorm');
        compareField($diffs, 'nome', $local['nome'] ?? '', $masterStudent['nome'] ?? $detail['first_name'] ?? '', 'mcNorm');
        compareField($diffs, 'email', $local['email'] ?? '', $masterStudent['email1'] ?? $detail['email'] ?? '', 'mcNorm');
        compareField($diffs, 'codice_fiscale', $local['codice_fiscale'] ?? '', $detail['cf'] ?? '', 'mcNormCompact');

        $expectedClass = mcNormCompact((string)($masterStudent['classe'] ?? '') . (string)($masterStudent['sezione'] ?? ''));
        $actualClass = mcNormCompact($local['classe_corrente'] ?? '');
        if ($expectedClass !== '' && $actualClass !== '' && strpos($actualClass, $expectedClass) !== 0) {
            $diffs['classe_corrente'] = [
                'local' => $local['classe_corrente'] ?? '',
                'mastercom' => ($masterStudent['classe'] ?? '') . ($masterStudent['sezione'] ?? ''),
            ];
        }
    }

    $studentComparisons[] = [
        'mastercom' => [
            'id_studente' => $studentId,
            'cognome' => $masterStudent['cognome'] ?? '',
            'nome' => $masterStudent['nome'] ?? '',
            'email' => $masterStudent['email1'] ?? $detail['email'] ?? '',
            'codice_fiscale' => $detail['cf'] ?? '',
            'classe' => ($masterStudent['classe'] ?? '') . ($masterStudent['sezione'] ?? ''),
            'foto' => $masterStudent['foto'] ?? '',
        ],
        'local' => $local,
        'diffs' => $diffs,
    ];
}

$parentsResult = mastercomLoadParents($adminAuth, [
    'method' => 'POST',
    'timeout' => 120,
]);
$masterParents = $parentsResult['response'] ?? [];
$classStudentIds = array_map(function ($row) {
    return intval($row['id_studente'] ?? 0);
}, $masterStudents);
$classStudentIds = array_values(array_filter($classStudentIds));

$relevantParents = [];
foreach ($masterParents as $parent) {
    $linked = $parent['studenti_abbinati'] ?? [];
    foreach ($linked as $child) {
        if (in_array(intval($child['id_studente'] ?? 0), $classStudentIds, true)) {
            $relevantParents[] = $parent;
            break;
        }
    }
}

$parentComparisons = [];
foreach ($relevantParents as $masterParent) {
    $parentId = intval($masterParent['id_parente'] ?? 0);
    $detailResult = mastercomLoadParentDetails($adminAuth, $parentId, [
        'method' => 'GET',
        'timeout' => 120,
    ]);
    $detail = mcFirstRecord($detailResult['response'] ?? null) ?? [];
    $local = findLocalParentForMastercom($masterParent, $detail);

    $diffs = [];
    if ($local == null) {
        $diffs['missing_local_parent'] = true;
    } else {
        compareField($diffs, 'cognome', $local['cognome'] ?? '', $masterParent['cognome'] ?? $detail['surname'] ?? '', 'mcNorm');
        compareField($diffs, 'nome', $local['nome'] ?? '', $masterParent['nome'] ?? $detail['first_name'] ?? '', 'mcNorm');
        compareField($diffs, 'email', $local['email'] ?? '', $detail['email'] ?? '', 'mcNorm');
        compareField($diffs, 'codice_fiscale', $local['codice_fiscale'] ?? '', $masterParent['codice_fiscale'] ?? $detail['cf'] ?? '', 'mcNormCompact');

        $localLinkedIds = dbGetAllValues("SELECT id_studente FROM genitori_studenti WHERE id_genitore = " . intval($local['id']));
        $expectedLinkedIds = [];
        foreach (($masterParent['studenti_abbinati'] ?? []) as $child) {
            $masterStudentId = intval($child['id_studente'] ?? 0);
            if (isset($masterStudentToLocal[$masterStudentId])) {
                $expectedLinkedIds[] = intval($masterStudentToLocal[$masterStudentId]);
            }
        }
        sort($localLinkedIds);
        sort($expectedLinkedIds);
        if ($localLinkedIds !== $expectedLinkedIds) {
            $diffs['studenti_collegati'] = [
                'local' => $localLinkedIds,
                'mastercom' => $expectedLinkedIds,
            ];
        }
    }

    $parentComparisons[] = [
        'mastercom' => [
            'id_parente' => $parentId,
            'cognome' => $masterParent['cognome'] ?? '',
            'nome' => $masterParent['nome'] ?? '',
            'email' => $detail['email'] ?? '',
            'codice_fiscale' => $masterParent['codice_fiscale'] ?? $detail['cf'] ?? '',
            'studenti_abbinati' => $masterParent['studenti_abbinati'] ?? [],
        ],
        'local' => $local,
        'diffs' => $diffs,
    ];
}

$summary = [
    'studenti_mastercom' => count($studentComparisons),
    'studenti_con_diff' => count(array_filter($studentComparisons, function ($row) {
        return !empty($row['diffs']);
    })),
    'genitori_mastercom' => count($parentComparisons),
    'genitori_con_diff' => count(array_filter($parentComparisons, function ($row) {
        return !empty($row['diffs']);
    })),
];

$suggestedFields = [
    'studente' => [
        'mastercom_id_studente',
        'data_nascita',
        'email_secondaria',
        'foto_mastercom',
        'necessita_sostegno',
        'esonero_religione',
        'esonero_ed_fisica',
        'servizio_mensa',
        'registro_numero',
        'esito_iscrizione',
        'mastercom_id_classe_corrente',
    ],
    'genitori' => [
        'mastercom_id_parente',
        'data_nascita',
        'email_mastercom_verificata',
    ],
    'genitori_studenti' => [
        'last_sync_mastercom',
        'source_mastercom',
    ],
];

echo json_encode([
    'ok' => true,
    'class_id' => $classId,
    'summary' => $summary,
    'studenti' => $studentComparisons,
    'genitori' => $parentComparisons,
    'suggested_fields' => $suggestedFields,
], JSON_UNESCAPED_UNICODE);

