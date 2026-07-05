<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

iscrizioniPrimeEnsureSchema();

function ipg_value(string $key): ?string
{
    $value = trim((string)($_POST[$key] ?? ''));
    return $value === '' ? null : $value;
}

function ipg_cf(string $key): ?string
{
    $value = strtoupper(preg_replace('/\s+/', '', (string)($_POST[$key] ?? '')));
    return $value === '' ? null : $value;
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Pratica non valida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$email1 = ipg_value('email_genitore_1');
$email2 = ipg_value('email_genitore_2');
foreach (['email_genitore_1' => $email1, 'email_genitore_2' => $email2] as $field => $email) {
    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Email non valida: ' . $field], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

try {
    $before = dbGetFirst("SELECT * FROM iscrizioni_prime_pratiche WHERE id = " . dbI($id) . " LIMIT 1");
    if (!$before) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Pratica non trovata.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $fields = [
        'responsabile_1_tipo' => ipg_value('responsabile_1_tipo'),
        'responsabile_1_cognome' => ipg_value('responsabile_1_cognome'),
        'responsabile_1_nome' => ipg_value('responsabile_1_nome'),
        'responsabile_1_codice_fiscale' => ipg_cf('responsabile_1_codice_fiscale'),
        'email_genitore_1' => $email1,
        'telefono_genitore_1' => ipg_value('telefono_genitore_1'),
        'responsabile_2_tipo' => ipg_value('responsabile_2_tipo'),
        'responsabile_2_cognome' => ipg_value('responsabile_2_cognome'),
        'responsabile_2_nome' => ipg_value('responsabile_2_nome'),
        'responsabile_2_codice_fiscale' => ipg_cf('responsabile_2_codice_fiscale'),
        'email_genitore_2' => $email2,
        'telefono_genitore_2' => ipg_value('telefono_genitore_2'),
        'bocciato_altra_scuola' => !empty($_POST['bocciato_altra_scuola']) ? 1 : 0,
    ];

    $sets = [];
    $changes = [];
    foreach ($fields as $field => $value) {
        $sets[] = $field . ' = ' . dbQ($value);
        if ((string)($before[$field] ?? '') !== (string)($value ?? '')) {
            $changes[$field] = [
                'prima' => $before[$field] ?? null,
                'dopo' => $value,
            ];
        }
    }
    $sets[] = 'updated_at = NOW()';

    dbExec("
        UPDATE iscrizioni_prime_pratiche SET
            " . implode(",\n            ", $sets) . "
        WHERE id = " . dbI($id) . "
        LIMIT 1
    ");

    if ($changes) {
        iscrizioniPrimeRecordEvent($id, 'aggiornamento_genitori', 'Dati genitori aggiornati dalla segreteria', [
            'dettagli' => $changes,
        ]);
    }
    iscrizioniPrimeSyncBocciatoAltraScuola(!empty($fields['bocciato_altra_scuola']), [
        'id_pratica_iscrizione' => $id,
        'codice_fiscale' => (string)($before['codice_fiscale'] ?? ''),
    ]);

    $after = dbGetFirst("SELECT * FROM iscrizioni_prime_pratiche WHERE id = " . dbI($id) . " LIMIT 1") ?: array_merge($before, $fields);
    try {
        iscrizioniPrimeSyncGestoreStudentAndParents($after);
    } catch (Throwable $syncError) {
        warning('[iscrizioni] dati genitori aggiornati ma sync anagrafiche non riuscita pratica ID ' . $id . ': ' . $syncError->getMessage());
    }

    echo json_encode(['ok' => true, 'message' => 'Dati genitori aggiornati.', 'changes' => count($changes)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
