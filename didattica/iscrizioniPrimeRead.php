<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

iscrizioniPrimeEnsureSchema();
$tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($_GET['tipo_iscrizione'] ?? 'prime');
$effectiveInternal = iscrizioniPrimeEffectiveInternalCondition('p');
$effectiveExternal = iscrizioniPrimeEffectiveExternalCondition('p');

$stats = dbGetFirst("
    SELECT
        COUNT(*) AS totale,
        SUM($effectiveInternal) AS interni,
        SUM($effectiveExternal) AS esterni,
        SUM(stato = 'importata') AS importate,
        SUM(stato = 'bozza') AS bozze,
        SUM(stato = 'bozza' AND $effectiveExternal) AS bozze_esterni,
        SUM(stato = 'inviata') AS domande_inviate,
        SUM(stato = 'inviata' AND $effectiveExternal) AS domande_inviate_esterni,
        SUM(stato = 'verificata') AS verificate,
        SUM(stato = 'annullata') AS annullate,
        SUM(email_genitore_1 IS NOT NULL OR email_genitore_2 IS NOT NULL) AS con_email,
        SUM((email_genitore_1 IS NOT NULL OR email_genitore_2 IS NOT NULL) AND $effectiveExternal) AS esterni_con_email
    FROM iscrizioni_prime_pratiche p
    WHERE p.tipo_iscrizione = " . dbQ($tipoIscrizione) . "
");

$mailStats = dbGetFirst("
    SELECT
        COUNT(DISTINCT CASE WHEN l.stato IN ('inviata','bounce') AND l.test_mode = 0 THEN l.pratica_id END) AS pratiche_mail_reali,
        COUNT(DISTINCT CASE WHEN l.stato = 'inviata' AND l.test_mode = 1 THEN l.pratica_id END) AS pratiche_mail_test,
        SUM(CASE WHEN l.stato IN ('inviata','bounce') AND l.test_mode = 0 THEN 1 ELSE 0 END) AS mail_reali,
        SUM(CASE WHEN l.stato = 'inviata' AND l.test_mode = 1 THEN 1 ELSE 0 END) AS mail_test,
        SUM(CASE WHEN l.stato = 'bounce' AND l.test_mode = 0 THEN 1 ELSE 0 END) AS mail_bounce
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
        CASE WHEN " . iscrizioniPrimeEffectiveInternalCondition('p') . " THEN 1 ELSE 0 END AS studente_interno_effettivo,
        classe_corrente.classe_corrente_gestore,
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
        COALESCE(mail_log.mail_bounce, 0) AS mail_bounce,
        CASE
            WHEN " . iscrizioniPrimeEffectiveInternalCondition('p') . " THEN 0
            WHEN p.stato NOT IN ('importata', 'bozza', 'da_integrare') THEN 0
            ELSE
                CASE
                    WHEN p.email_genitore_1 IS NOT NULL
                     AND TRIM(p.email_genitore_1) <> ''
                     AND NOT EXISTS (
                        SELECT 1
                        FROM iscrizioni_prime_mail_log l1
                        WHERE l1.pratica_id = p.id
                          AND LOWER(TRIM(l1.recipient_email)) = LOWER(TRIM(p.email_genitore_1))
                          AND l1.stato IN ('inviata','bounce')
                          AND l1.test_mode = 0
                        LIMIT 1
                     )
                    THEN 1 ELSE 0
                END
                +
                CASE
                    WHEN p.email_genitore_2 IS NOT NULL
                     AND TRIM(p.email_genitore_2) <> ''
                     AND LOWER(TRIM(p.email_genitore_2)) <> LOWER(TRIM(COALESCE(p.email_genitore_1, '')))
                     AND NOT EXISTS (
                        SELECT 1
                        FROM iscrizioni_prime_mail_log l2
                        WHERE l2.pratica_id = p.id
                          AND LOWER(TRIM(l2.recipient_email)) = LOWER(TRIM(p.email_genitore_2))
                          AND l2.stato IN ('inviata','bounce')
                          AND l2.test_mode = 0
                        LIMIT 1
                     )
                    THEN 1 ELSE 0
                END
        END AS mail_pending,
        CASE
            WHEN " . iscrizioniPrimeEffectiveInternalCondition('p') . " THEN 'studente interno'
            WHEN p.stato NOT IN ('importata', 'bozza', 'da_integrare') THEN CONCAT('pratica ', p.stato)
            WHEN (p.email_genitore_1 IS NULL OR TRIM(p.email_genitore_1) = '')
             AND (p.email_genitore_2 IS NULL OR TRIM(p.email_genitore_2) = '') THEN 'senza email responsabili'
            ELSE ''
        END AS mail_diagnosi,
        mail_log.last_real_sent_at,
        mail_log.last_test_sent_at,
        mail_log.last_bounced_at,
        mail_log.bounce_type,
        mail_log.bounce_reason,
        cambio.richiesta_data AS cambio_scuola_richiesta_data,
        cambio.canale AS cambio_scuola_canale,
        cambio.scuola_destinazione AS cambio_scuola_scuola_destinazione,
        cambio.pratica_stato AS cambio_scuola_pratica_stato
    FROM iscrizioni_prime_pratiche p
    LEFT JOIN (
        SELECT
            pratica_id,
            SUM(CASE WHEN stato IN ('inviata','bounce') AND test_mode = 0 THEN 1 ELSE 0 END) AS mail_reali,
            SUM(CASE WHEN stato = 'inviata' AND test_mode = 1 THEN 1 ELSE 0 END) AS mail_test,
            SUM(CASE WHEN stato = 'bounce' AND test_mode = 0 THEN 1 ELSE 0 END) AS mail_bounce,
            MAX(CASE WHEN stato IN ('inviata','bounce') AND test_mode = 0 THEN sent_at ELSE NULL END) AS last_real_sent_at,
            MAX(CASE WHEN stato = 'inviata' AND test_mode = 1 THEN sent_at ELSE NULL END) AS last_test_sent_at,
            MAX(CASE WHEN stato = 'bounce' AND test_mode = 0 THEN bounced_at ELSE NULL END) AS last_bounced_at,
            SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN stato = 'bounce' AND test_mode = 0 THEN bounce_type ELSE NULL END ORDER BY bounced_at DESC SEPARATOR '||'), '||', 1) AS bounce_type,
            SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN stato = 'bounce' AND test_mode = 0 THEN bounce_reason ELSE NULL END ORDER BY bounced_at DESC SEPARATOR '||'), '||', 1) AS bounce_reason
        FROM iscrizioni_prime_mail_log
        GROUP BY pratica_id
    ) mail_log ON mail_log.pratica_id = p.id
    LEFT JOIN (
        SELECT
            UPPER(TRIM(s.codice_fiscale)) AS codice_fiscale,
            GROUP_CONCAT(DISTINCT c.classe ORDER BY c.classe SEPARATOR ', ') AS classe_corrente_gestore
        FROM studente s
        INNER JOIN studente_frequenta sf
            ON sf.id_studente = s.id
           AND sf.id_anno_scolastico = " . dbI(intval($__anno_scolastico_corrente_id ?? 0)) . "
        INNER JOIN classi c
            ON c.id = sf.id_classe
        WHERE s.attivo = 1
        GROUP BY UPPER(TRIM(s.codice_fiscale))
    ) classe_corrente ON classe_corrente.codice_fiscale = UPPER(TRIM(p.codice_fiscale))
    LEFT JOIN iscrizioni_prime_cambio_scuola cambio ON cambio.pratica_id = p.id
    WHERE p.tipo_iscrizione = " . dbQ($tipoIscrizione) . "
    ORDER BY p.cognome ASC, p.nome ASC
");

echo json_encode(['ok' => true, 'stats' => $stats ?: [], 'mail_stats' => $mailStats ?: [], 'rows' => $rows], JSON_UNESCAPED_UNICODE);
