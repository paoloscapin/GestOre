<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

iscrizioniPrimeEnsureSchema();
$tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($_GET['tipo_iscrizione'] ?? 'prime');

$stats = dbGetFirst("
    SELECT
        COUNT(*) AS totale,
        SUM(studente_interno = 1) AS interni,
        SUM(studente_interno = 0) AS esterni,
        SUM(stato = 'importata') AS importate,
        SUM(stato = 'bozza') AS bozze,
        SUM(stato = 'bozza' AND studente_interno = 0) AS bozze_esterni,
        SUM(stato = 'inviata') AS domande_inviate,
        SUM(stato = 'inviata' AND studente_interno = 0) AS domande_inviate_esterni,
        SUM(stato = 'verificata') AS verificate,
        SUM(email_genitore_1 IS NOT NULL OR email_genitore_2 IS NOT NULL) AS con_email,
        SUM((email_genitore_1 IS NOT NULL OR email_genitore_2 IS NOT NULL) AND studente_interno = 0) AS esterni_con_email
    FROM iscrizioni_prime_pratiche
    WHERE tipo_iscrizione = " . dbQ($tipoIscrizione) . "
");

$mailStats = dbGetFirst("
    SELECT
        COUNT(DISTINCT CASE WHEN l.stato = 'inviata' AND l.test_mode = 0 THEN l.pratica_id END) AS pratiche_mail_reali,
        COUNT(DISTINCT CASE WHEN l.stato = 'inviata' AND l.test_mode = 1 THEN l.pratica_id END) AS pratiche_mail_test,
        SUM(CASE WHEN l.stato = 'inviata' AND l.test_mode = 0 THEN 1 ELSE 0 END) AS mail_reali,
        SUM(CASE WHEN l.stato = 'inviata' AND l.test_mode = 1 THEN 1 ELSE 0 END) AS mail_test
    FROM iscrizioni_prime_mail_log l
    INNER JOIN iscrizioni_prime_pratiche p ON p.id = l.pratica_id
    WHERE p.tipo_iscrizione = " . dbQ($tipoIscrizione) . "
");

$rows = dbGetAll("
    SELECT
        p.id,
        p.anno_scolastico,
        p.tipo_iscrizione,
        p.studente_interno,
        p.codice_domanda,
        p.codice_fiscale,
        p.cognome,
        p.nome,
        p.corso_studi,
        p.sezione_richiesta,
        p.comune_residenza,
        p.scuola_provenienza,
        p.esami_integrativi_da_verificare,
        p.stato,
        p.email_genitore_1,
        p.email_genitore_2,
        p.telefono_genitore_1,
        p.telefono_genitore_2,
        p.token_last4,
        p.token_expires_at,
        p.updated_at,
        COALESCE(mail_log.mail_reali, 0) AS mail_reali,
        COALESCE(mail_log.mail_test, 0) AS mail_test,
        mail_log.last_real_sent_at,
        mail_log.last_test_sent_at
    FROM iscrizioni_prime_pratiche p
    LEFT JOIN (
        SELECT
            pratica_id,
            SUM(CASE WHEN stato = 'inviata' AND test_mode = 0 THEN 1 ELSE 0 END) AS mail_reali,
            SUM(CASE WHEN stato = 'inviata' AND test_mode = 1 THEN 1 ELSE 0 END) AS mail_test,
            MAX(CASE WHEN stato = 'inviata' AND test_mode = 0 THEN sent_at ELSE NULL END) AS last_real_sent_at,
            MAX(CASE WHEN stato = 'inviata' AND test_mode = 1 THEN sent_at ELSE NULL END) AS last_test_sent_at
        FROM iscrizioni_prime_mail_log
        GROUP BY pratica_id
    ) mail_log ON mail_log.pratica_id = p.id
    WHERE p.tipo_iscrizione = " . dbQ($tipoIscrizione) . "
    ORDER BY p.cognome ASC, p.nome ASC
");

echo json_encode(['ok' => true, 'stats' => $stats ?: [], 'mail_stats' => $mailStats ?: [], 'rows' => $rows], JSON_UNESCAPED_UNICODE);
