<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

iscrizioniPrimeEnsureSchema();

$rows = dbGetAll("
    SELECT
        id,
        anno_scolastico,
        codice_domanda,
        codice_fiscale,
        cognome,
        nome,
        corso_studi,
        stato,
        email_genitore_1,
        email_genitore_2,
        telefono_genitore_1,
        telefono_genitore_2,
        token_last4,
        token_expires_at,
        updated_at
    FROM iscrizioni_prime_pratiche
    ORDER BY cognome ASC, nome ASC
");

echo json_encode(['ok' => true, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
