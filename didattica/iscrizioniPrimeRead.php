<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

header('Content-Type: application/json; charset=utf-8');

function iscrizioniPrimeReadJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'message' => 'Errore codifica JSON iscrizioni prime: ' . json_last_error_msg(),
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        return;
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
    $payload = [
        'ok' => false,
        'message' => 'Errore fatale lettura iscrizioni prime: ' . (string)($error['message'] ?? 'errore sconosciuto'),
    ];
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
});

try {
iscrizioniPrimeEnsureSchema();
$tipoIscrizione = iscrizioniPrimeNormalizeTipoIscrizione($_GET['tipo_iscrizione'] ?? 'prime');
$effectiveInternal = iscrizioniPrimeEffectiveInternalCondition('p');
$effectiveExternal = iscrizioniPrimeEffectiveExternalCondition('p');
$movimentiRequiredColumns = ['id', 'tipo_pratica', 'stato_pratica', 'codice_fiscale', 'classe_origine', 'classe_richiesta', 'updated_at'];
$movimentiEnabled = dbGetFirst("SHOW TABLES LIKE 'studenti_movimenti_pratiche'") !== null;
if ($movimentiEnabled) {
    foreach ($movimentiRequiredColumns as $column) {
        if (dbGetFirst("SHOW COLUMNS FROM studenti_movimenti_pratiche LIKE " . dbQ($column)) === null) {
            $movimentiEnabled = false;
            break;
        }
    }
}
$movimentiSelect = $movimentiEnabled
    ? "movimento_reiscrizione.id AS movimento_reiscrizione_id,
        movimento_reiscrizione.stato_pratica AS movimento_reiscrizione_stato,
        movimento_reiscrizione.classe_origine AS movimento_reiscrizione_classe_origine,
        movimento_reiscrizione.classe_richiesta AS movimento_reiscrizione_classe_richiesta"
    : "NULL AS movimento_reiscrizione_id,
        NULL AS movimento_reiscrizione_stato,
        NULL AS movimento_reiscrizione_classe_origine,
        NULL AS movimento_reiscrizione_classe_richiesta";
$movimentiJoin = $movimentiEnabled ? "
    LEFT JOIN studenti_movimenti_pratiche movimento_reiscrizione
      ON movimento_reiscrizione.id = (
          SELECT m2.id
          FROM studenti_movimenti_pratiche m2
          WHERE m2.tipo_pratica = 'bocciato_reiscrizione'
            AND m2.stato_pratica <> 'annullata'
            AND m2.codice_fiscale IS NOT NULL
            AND m2.codice_fiscale <> ''
            AND UPPER(TRIM(m2.codice_fiscale)) = UPPER(TRIM(p.codice_fiscale))
          ORDER BY m2.updated_at DESC, m2.id DESC
          LIMIT 1
      )" : "";

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
        SUM(stato = 'verifica_iniziale_ok') AS verifica_iniziale_ok,
        SUM(stato = 'verificata') AS verificate,
        SUM(stato = 'annullata') AS annullate,
        SUM(tablet_scelto = 1) AS tablet_scelti,
        SUM(tablet_stato = 'confermato') AS tablet_confermati,
        SUM(tablet_stato = 'escluso') AS tablet_esclusi,
        SUM(tablet_stato = 'rinuncia') AS tablet_rinunce,
        SUM(tablet_stato = 'confermato' AND tablet_acquistato = 1) AS tablet_acquistati,
        SUM(tablet_stato = 'confermato' AND tablet_proprio = 1) AS tablet_propri,
        SUM(tablet_stato = 'confermato' AND tablet_acquistato = 0 AND tablet_proprio = 0) AS tablet_da_acquistare,
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
        p.id_indirizzo_gestore,
        ind_gestore.nome AS indirizzo_gestore_nome,
        p.note_genitori_iscrizione,
        p.curvatura_design,
        p.sezione_richiesta,
        p.comune_residenza,
        p.scuola_provenienza,
        p.bocciato_altra_scuola,
        p.esami_integrativi_da_verificare,
        p.stato,
        p.email_genitore_1,
        p.email_genitore_2,
        p.telefono_genitore_1,
        p.telefono_genitore_2,
        p.responsabile_1_tipo,
        p.responsabile_1_cognome,
        p.responsabile_1_nome,
        p.responsabile_1_codice_fiscale,
        p.responsabile_2_tipo,
        p.responsabile_2_cognome,
        p.responsabile_2_nome,
        p.responsabile_2_codice_fiscale,
        p.token_last4,
        p.token_expires_at,
        p.tablet_scelto,
        p.tablet_stato,
        p.tablet_gruppo,
        p.tablet_posizione,
        p.tablet_acquistato,
        p.tablet_acquistato_at,
        p.tablet_proprio,
        p.tablet_ripescato_da_pratica_id,
        p.tablet_note,
        p.tablet_rinuncia_allegato_original_name,
        p.raw_dsa_json,
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
        cambio.pratica_stato AS cambio_scuola_pratica_stato,
        $movimentiSelect
    FROM iscrizioni_prime_pratiche p
    LEFT JOIN indirizzo ind_gestore ON ind_gestore.id = p.id_indirizzo_gestore
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
    $movimentiJoin
    WHERE p.tipo_iscrizione = " . dbQ($tipoIscrizione) . "
    ORDER BY p.cognome ASC, p.nome ASC
");

$rows = $rows ?: [];
$fiscalCodes = [];
foreach ($rows as $row) {
    $cf = strtoupper(trim((string)($row['codice_fiscale'] ?? '')));
    if ($cf !== '') {
        $fiscalCodes[$cf] = true;
    }
}

$attrsByCf = [];
if (!empty($fiscalCodes)) {
    studentiAttrEnsureTables();
    $quotedFiscalCodes = implode(',', array_map(static fn($cf) => dbQ($cf), array_keys($fiscalCodes)));
    $attrRows = dbGetAll("
        SELECT
            UPPER(TRIM(s.codice_fiscale)) AS codice_fiscale,
            a.codice_attributo,
            a.fonte
        FROM studente s
        INNER JOIN studente_attributi_riservati a
            ON a.id_studente = s.id
           AND a.attivo = 1
        WHERE s.attivo = 1
          AND UPPER(TRIM(s.codice_fiscale)) IN ($quotedFiscalCodes)
        ORDER BY a.codice_attributo ASC
    ") ?: [];
    foreach ($attrRows as $attrRow) {
        $cf = strtoupper(trim((string)($attrRow['codice_fiscale'] ?? '')));
        if ($cf === '') {
            continue;
        }
        $attrsByCf[$cf][] = $attrRow;
    }
}

$rows = array_map(static function (array $row) use ($attrsByCf): array {
    $cf = strtoupper(trim((string)($row['codice_fiscale'] ?? '')));
    $result = [];
    foreach (studentiAttrRowsToDisplay($attrsByCf[$cf] ?? []) as $attr) {
        $result[(string)$attr['codice']] = $attr;
    }

    $rawDsa = trim((string)($row['raw_dsa_json'] ?? ''));
    if ($rawDsa !== '') {
        $decoded = json_decode($rawDsa, true);
        if (is_array($decoded)) {
            foreach (studentiAttrActiveFromDsaCsvRow($decoded) as $attr) {
                $code = (string)($attr['codice'] ?? '');
                if ($code === '') {
                    continue;
                }
                if (isset($result[$code]) && trim((string)($result[$code]['fonte'] ?? '')) !== '') {
                    if (strpos((string)$result[$code]['fonte'], 'csv_dsa') === false) {
                        $result[$code]['fonte'] .= '+csv_dsa';
                    }
                } else {
                    $result[$code] = $attr;
                }
            }
        }
    }

    $row['attributi_riservati'] = array_values($result);
    unset($row['raw_dsa_json']);
    return $row;
}, $rows);

$summary = iscrizioniPrimeSummary($tipoIscrizione);
iscrizioniPrimeReadJson(['ok' => true, 'stats' => $stats ?: [], 'mail_stats' => $mailStats ?: [], 'summary' => $summary['summary'] ?? [], 'rows' => $rows]);
} catch (Throwable $e) {
    iscrizioniPrimeReadJson([
        'ok' => false,
        'message' => 'Errore lettura iscrizioni prime: ' . $e->getMessage(),
    ], 500);
}
