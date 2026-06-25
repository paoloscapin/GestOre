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
    !is_uploaded_file($_FILES['prime_csv']['tmp_name'])
) {
    iscrizioniPrimeImportFail('Caricare il file CSV iscrizioni.');
}

try {
    $createdBy = trim((string)($GLOBALS['__useremail'] ?? $GLOBALS['__username'] ?? ''));
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($_POST['tipo_iscrizione'] ?? 'prime');
    $dsaPath = null;
    $dsaName = '';
    if (!empty($_FILES['dsa_csv']['tmp_name']) && is_uploaded_file($_FILES['dsa_csv']['tmp_name'])) {
        $dsaPath = $_FILES['dsa_csv']['tmp_name'];
        $dsaName = $_FILES['dsa_csv']['name'] ?? '';
    }
    $anagraficaPath = null;
    $anagraficaName = '';
    if (!empty($_FILES['anagrafica_csv']['tmp_name']) && is_uploaded_file($_FILES['anagrafica_csv']['tmp_name'])) {
        $anagraficaPath = $_FILES['anagrafica_csv']['tmp_name'];
        $anagraficaName = $_FILES['anagrafica_csv']['name'] ?? '';
    }
    $licenzaMediaPath = null;
    $licenzaMediaName = '';
    if (!empty($_FILES['licenza_media_csv']['tmp_name']) && is_uploaded_file($_FILES['licenza_media_csv']['tmp_name'])) {
        $licenzaMediaPath = $_FILES['licenza_media_csv']['tmp_name'];
        $licenzaMediaName = $_FILES['licenza_media_csv']['name'] ?? '';
    }

    $result = iscrizioniPrimeImport(
        $_FILES['prime_csv']['tmp_name'],
        $dsaPath,
        $_FILES['prime_csv']['name'] ?? '',
        $dsaName,
        $createdBy,
        $anagraficaPath,
        $anagraficaName,
        $tipoIscrizione,
        $licenzaMediaPath,
        $licenzaMediaName
    );

    echo json_encode([
        'ok' => true,
        'prime_rows' => $result['prime_rows'],
        'dsa_rows' => $result['dsa_rows'],
        'contact_rows' => $result['contact_rows'],
        'licenza_media_rows' => $result['licenza_media_rows'],
        'inserted' => $result['inserted'],
        'updated' => $result['updated'],
        'contacts_updated' => $result['contacts_updated'],
        'contacts_ignored' => $result['contacts_ignored'],
        'contacts_internal_skipped' => $result['contacts_internal_skipped'],
        'tipo_iscrizione' => $result['tipo_iscrizione'],
        'interni' => $result['interni'],
        'esterni' => $result['esterni'],
        'interni_marcati_da_gestore' => $result['interni_marcati_da_gestore'] ?? 0,
        'errors' => $result['errors'],
        'generated_tokens' => count($result['generated_tokens']),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    iscrizioniPrimeImportFail($e->getMessage(), 500);
}
