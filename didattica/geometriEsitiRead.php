<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';

header('Content-Type: application/json; charset=utf-8');
ruoloRichiesto('admin', 'esterno', 'docente', 'segreteria-didattica', 'dirigente');

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Sessione non valida']);
    exit;
}

$ruolo_eff = $__utente_ruolo ?? '';
if (impersonaRuolo('docente')) $ruolo_eff = 'docente';
if (impersonaRuolo('esterno')) $ruolo_eff = 'esterno';

$sessione = dbGetFirst("
    SELECT
        s.*,
        e.titolo AS esame_titolo,
        e.anno_corso
    FROM geometri_sessioni s
    INNER JOIN geometri_esami e ON e.id = s.id_esame
    WHERE s.id = " . dbI($id) . "
    LIMIT 1
");

if (!$sessione) {
    echo json_encode(['success' => false, 'error' => 'Sessione non trovata']);
    exit;
}

if ($ruolo_eff === 'docente') {
    $docente_id = intval($__docente_id ?? 0);
    $ok = $docente_id > 0 ? intval(dbGetValue("SELECT COUNT(*) FROM geometri_sessioni_docenti WHERE id_sessione=" . dbI($id) . " AND id_docente=" . dbI($docente_id))) : 0;
    if ($ok <= 0) {
        echo json_encode(['success' => false, 'error' => 'Non sei abilitato agli esiti di questa sessione']);
        exit;
    }
} elseif ($ruolo_eff === 'esterno') {
    $utente_id = intval($__utente_id ?? 0);
    $ok = $utente_id > 0 ? intval(dbGetValue("SELECT COUNT(*) FROM geometri_sessioni_esterni WHERE id_sessione=" . dbI($id) . " AND id_utente=" . dbI($utente_id))) : 0;
    if ($ok <= 0) {
        echo json_encode(['success' => false, 'error' => 'Non sei abilitato agli esiti di questa sessione']);
        exit;
    }
}

function geometri_esiti_table_exists($table)
{
    $table = dbEscape($table);
    return intval(dbGetValue("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$table'")) > 0;
}

$recuperiUnion = "";
if (geometri_esiti_table_exists('geometri_sessioni_studenti')) {
    $recuperiUnion = "
        UNION
        SELECT
            ss.id_studente,
            CONCAT('Recuperi', CASE WHEN c.classe IS NULL THEN '' ELSE CONCAT(' - ', c.classe) END) AS classe,
            1 AS gruppo_ordine
        FROM geometri_sessioni_studenti ss
        LEFT JOIN studente_frequenta sf
            ON sf.id_studente = ss.id_studente
           AND sf.id_anno_scolastico = " . dbI($sessione['id_anno_scolastico']) . "
        LEFT JOIN classi c ON c.id = sf.id_classe
        WHERE ss.id_sessione = " . dbI($id) . "
          AND NOT EXISTS (
              SELECT 1
              FROM geometri_sessioni_classi sc2
              INNER JOIN studente_frequenta sf2
                  ON sf2.id_classe = sc2.id_classe
                 AND sf2.id_anno_scolastico = " . dbI($sessione['id_anno_scolastico']) . "
                 AND sf2.id_studente = ss.id_studente
              WHERE sc2.id_sessione = ss.id_sessione
          )
    ";
}

$studenti = dbGetAll("
    SELECT
        st.id,
        st.cognome,
        st.nome,
        base.classe,
        MIN(base.gruppo_ordine) AS gruppo_ordine,
        COALESCE(es.presente, 1) AS presente,
        COALESCE(es.esito, 'da_valutare') AS esito,
        es.note
    FROM (
        SELECT
            sf.id_studente,
            c.classe,
            0 AS gruppo_ordine
        FROM geometri_sessioni_classi sc
        INNER JOIN studente_frequenta sf
            ON sf.id_classe = sc.id_classe
           AND sf.id_anno_scolastico = " . dbI($sessione['id_anno_scolastico']) . "
        INNER JOIN classi c ON c.id = sf.id_classe
        WHERE sc.id_sessione = " . dbI($id) . "
        $recuperiUnion
    ) base
    INNER JOIN studente st ON st.id = base.id_studente
    LEFT JOIN geometri_esiti es
        ON es.id_sessione = " . dbI($id) . "
       AND es.id_studente = st.id
    WHERE COALESCE(st.attivo, 1) = 1
    GROUP BY st.id, st.cognome, st.nome, base.classe, es.presente, es.esito, es.note
    ORDER BY gruppo_ordine ASC, base.classe ASC, st.cognome ASC, st.nome ASC
");

try {
    $data_label = (new DateTime((string)$sessione['data']))->format('d/m/Y');
} catch (Exception $e) {
    $data_label = (string)($sessione['data'] ?? '');
}

echo json_encode([
    'success' => true,
    'sessione' => [
        'id' => intval($sessione['id']),
        'esame_titolo' => $sessione['esame_titolo'],
        'data_label' => $data_label,
        'stato' => $sessione['stato'],
    ],
    'studenti' => $studenti ?: [],
], JSON_UNESCAPED_UNICODE);
