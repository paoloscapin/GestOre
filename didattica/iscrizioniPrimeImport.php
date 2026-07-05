<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

function iscrizioniPrimeImportFail(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function iscrizioniPrimeImportJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        iscrizioniPrimeImportFail('Errore codifica JSON import iscrizioni: ' . json_last_error_msg(), 500);
    }
    echo $json;
}

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if (!$error) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int)($error['type'] ?? 0), $fatalTypes, true)) {
        return;
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    echo json_encode([
        'ok' => false,
        'message' => 'Errore fatale import iscrizioni: ' . (string)($error['message'] ?? 'errore sconosciuto'),
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
});

function iscrizioniPrimeImportUpload(string $field, string $label): array
{
    if (!isset($_FILES[$field])) {
        return [null, ''];
    }

    $error = intval($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return [null, ''];
    }

    $messages = [
        UPLOAD_ERR_INI_SIZE => 'supera la dimensione massima consentita dal server',
        UPLOAD_ERR_FORM_SIZE => 'supera la dimensione massima consentita dal form',
        UPLOAD_ERR_PARTIAL => 'e stato caricato solo parzialmente',
        UPLOAD_ERR_NO_TMP_DIR => 'manca la cartella temporanea del server',
        UPLOAD_ERR_CANT_WRITE => 'il server non riesce a scrivere il file temporaneo',
        UPLOAD_ERR_EXTENSION => 'una estensione PHP ha bloccato il caricamento',
    ];
    if ($error !== UPLOAD_ERR_OK) {
        iscrizioniPrimeImportFail($label . ': upload non riuscito, ' . ($messages[$error] ?? ('errore codice ' . $error)) . '.', 400);
    }

    $tmpName = (string)($_FILES[$field]['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        iscrizioniPrimeImportFail($label . ': file caricato non disponibile sul server.', 400);
    }

    return [$tmpName, (string)($_FILES[$field]['name'] ?? '')];
}

try {
    $createdBy = trim((string)($GLOBALS['__useremail'] ?? $GLOBALS['__username'] ?? ''));
    $tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($_POST['tipo_iscrizione'] ?? 'prime');
    [$primePath, $primeName] = iscrizioniPrimeImportUpload('prime_csv', 'CSV iscrizioni');
    [$dsaPath, $dsaName] = iscrizioniPrimeImportUpload('dsa_csv', 'CSV DSA');
    [$anagraficaPath, $anagraficaName] = iscrizioniPrimeImportUpload('anagrafica_csv', 'CSV anagrafica responsabili');
    [$licenzaMediaPath, $licenzaMediaName] = iscrizioniPrimeImportUpload('licenza_media_csv', 'CSV esame licenza media');
    [$dsaSchoolPath, $dsaSchoolName] = iscrizioniPrimeImportUpload('dsa_school_xls', 'Excel DSA/Fascia C/104 scuola');
    [$additionalPath, $additionalName] = iscrizioniPrimeImportUpload('dati_aggiuntivi_csv', 'CSV dati aggiuntivi');

    if (!$primePath && !$dsaPath && !$anagraficaPath && !$licenzaMediaPath && !$dsaSchoolPath && !$additionalPath) {
        iscrizioniPrimeImportFail('Caricare almeno un file da importare.');
    }

    $result = iscrizioniPrimeImport(
        $primePath,
        $dsaPath,
        $primeName,
        $dsaName,
        $createdBy,
        $anagraficaPath,
        $anagraficaName,
        $tipoIscrizione,
        $licenzaMediaPath,
        $licenzaMediaName,
        $dsaSchoolPath,
        $dsaSchoolName,
        $additionalPath,
        $additionalName
    );

    iscrizioniPrimeImportJson([
        'ok' => true,
        'prime_rows' => $result['prime_rows'],
        'dsa_rows' => $result['dsa_rows'],
        'contact_rows' => $result['contact_rows'],
        'licenza_media_rows' => $result['licenza_media_rows'],
        'dsa_updated' => $result['dsa_updated'],
        'dsa_ignored' => $result['dsa_ignored'],
        'licenza_media_updated' => $result['licenza_media_updated'],
        'licenza_media_ignored' => $result['licenza_media_ignored'],
        'inserted' => $result['inserted'],
        'updated' => $result['updated'],
        'inserted_details' => $result['inserted_details'],
        'updated_details' => $result['updated_details'],
        'contacts_updated' => $result['contacts_updated'],
        'contacts_ignored' => $result['contacts_ignored'],
        'contacts_internal_skipped' => $result['contacts_internal_skipped'],
        'school_attr_rows' => $result['school_attr_rows'],
        'school_attr_matched' => $result['school_attr_matched'],
        'school_attr_unmatched' => $result['school_attr_unmatched'],
        'school_attr_updated' => $result['school_attr_updated'],
        'school_attr_active_by_code' => $result['school_attr_active_by_code'],
        'school_attr_matched_examples' => $result['school_attr_matched_examples'],
        'school_attr_unmatched_examples' => $result['school_attr_unmatched_examples'],
        'licenza_media_linked' => $result['licenza_media_linked'],
        'licenza_media_linked_details' => $result['licenza_media_linked_details'],
        'movimenti_entrata_collegati' => $result['movimenti_entrata_collegati'],
        'movimenti_entrata_gia_collegati' => $result['movimenti_entrata_gia_collegati'],
        'movimenti_entrata_conflitti' => $result['movimenti_entrata_conflitti'],
        'movimenti_entrata_collegati_details' => $result['movimenti_entrata_collegati_details'],
        'movimenti_entrata_conflitti_details' => $result['movimenti_entrata_conflitti_details'],
        'licenza_media_updated_details' => $result['licenza_media_updated_details'],
        'licenza_media_ignored_details' => $result['licenza_media_ignored_details'],
        'additional_rows' => $result['additional_rows'],
        'additional_updated' => $result['additional_updated'],
        'additional_ignored' => $result['additional_ignored'],
        'additional_updated_details' => $result['additional_updated_details'],
        'additional_ignored_details' => $result['additional_ignored_details'],
        'dsa_updated_details' => $result['dsa_updated_details'],
        'dsa_ignored_details' => $result['dsa_ignored_details'],
        'tipo_iscrizione' => $result['tipo_iscrizione'],
        'interni' => $result['interni'],
        'esterni' => $result['esterni'],
        'interni_marcati_da_gestore' => $result['interni_marcati_da_gestore'] ?? 0,
        'errors' => $result['errors'],
        'generated_tokens' => count($result['generated_tokens']),
    ]);
} catch (Throwable $e) {
    iscrizioniPrimeImportFail($e->getMessage(), 500);
}
