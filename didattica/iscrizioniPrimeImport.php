<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

function iscrizioniPrimeImportFail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (
    empty($_FILES['prime_csv']['tmp_name']) ||
    empty($_FILES['dsa_csv']['tmp_name']) ||
    !is_uploaded_file($_FILES['prime_csv']['tmp_name']) ||
    !is_uploaded_file($_FILES['dsa_csv']['tmp_name'])
) {
    iscrizioniPrimeImportFail('Caricare i due file CSV iscrizioni e DSA.');
}

try {
    $createdBy = trim((string)($GLOBALS['__useremail'] ?? $GLOBALS['__username'] ?? ''));
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($_POST['tipo_iscrizione'] ?? 'prime');
    $anagraficaPath = null;
    $anagraficaName = '';
    if (!empty($_FILES['anagrafica_csv']['tmp_name']) && is_uploaded_file($_FILES['anagrafica_csv']['tmp_name'])) {
        $anagraficaPath = $_FILES['anagrafica_csv']['tmp_name'];
        $anagraficaName = $_FILES['anagrafica_csv']['name'] ?? '';
    } elseif ($tipoIscrizione === 'prime') {
        iscrizioniPrimeImportFail('Caricare anche il CSV anagrafica responsabili.');
    }

    $result = iscrizioniPrimeImport(
        $_FILES['prime_csv']['tmp_name'],
        $_FILES['dsa_csv']['tmp_name'],
        $_FILES['prime_csv']['name'] ?? '',
        $_FILES['dsa_csv']['name'] ?? '',
        $createdBy,
        $anagraficaPath,
        $anagraficaName,
        $tipoIscrizione
    );

    echo json_encode([
        'ok' => true,
        'prime_rows' => $result['prime_rows'],
        'dsa_rows' => $result['dsa_rows'],
        'contact_rows' => $result['contact_rows'],
        'inserted' => $result['inserted'],
        'updated' => $result['updated'],
        'contacts_updated' => $result['contacts_updated'],
        'contacts_ignored' => $result['contacts_ignored'],
        'tipo_iscrizione' => $result['tipo_iscrizione'],
        'interni' => $result['interni'],
        'esterni' => $result['esterni'],
        'errors' => $result['errors'],
        'generated_tokens' => count($result['generated_tokens']),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    iscrizioniPrimeImportFail($e->getMessage(), 500);
}
