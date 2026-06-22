<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

iscrizioniPrimeEnsureSchema();
$tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($_GET['tipo_iscrizione'] ?? 'prime');

$rows = dbGetAll("
    SELECT
        id,
        anno_scolastico,
        tipo_iscrizione,
        studente_interno,
        codice_domanda,
        codice_fiscale,
        cognome,
        nome,
        corso_studi,
        sezione_richiesta,
        comune_residenza,
        scuola_provenienza,
        esami_integrativi_da_verificare,
        stato,
        email_genitore_1,
        email_genitore_2,
        telefono_genitore_1,
        telefono_genitore_2,
        token_last4,
        token_expires_at,
        updated_at
    FROM iscrizioni_prime_pratiche
    WHERE tipo_iscrizione = " . dbQ($tipoIscrizione) . "
    ORDER BY cognome ASC, nome ASC
");

echo json_encode(['ok' => true, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
