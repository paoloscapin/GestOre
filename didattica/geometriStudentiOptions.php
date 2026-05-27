<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';

header('Content-Type: application/json; charset=utf-8');
ruoloRichiesto('admin', 'esterno', 'docente', 'segreteria-didattica', 'dirigente');

$anno_id = intval($_GET['anno_id'] ?? $__anno_scolastico_corrente_id);
if ($anno_id <= 0) $anno_id = intval($__anno_scolastico_corrente_id);

$esame_id = intval($_GET['esame_id'] ?? 0);

$rows = dbGetAll("
    SELECT DISTINCT
        st.id,
        st.cognome,
        st.nome,
        c.classe
    FROM studente_frequenta sf
    INNER JOIN studente st ON st.id = sf.id_studente
    INNER JOIN classi c ON c.id = sf.id_classe
    LEFT JOIN indirizzo i1 ON i1.id = c.id_primo_indirizzo
    LEFT JOIN indirizzo i2 ON i2.id = c.id_secondo_indirizzo
    WHERE sf.id_anno_scolastico = " . dbI($anno_id) . "
      AND COALESCE(st.attivo, 1) = 1
      AND (
        c.anno IN (3,4,5)
        OR c.classe LIKE '3%'
        OR c.classe LIKE '4%'
        OR c.classe LIKE '5%'
      )
      AND (
        UPPER(COALESCE(i1.nome_breve,'')) = 'CAT'
        OR UPPER(COALESCE(i2.nome_breve,'')) = 'CAT'
        OR UPPER(COALESCE(i1.nome,'')) LIKE '%COSTRU%'
        OR UPPER(COALESCE(i2.nome,'')) LIKE '%COSTRU%'
        OR UPPER(c.classe) LIKE '%CAT%'
      )
    ORDER BY c.classe ASC, st.cognome ASC, st.nome ASC
");

$studenti = [];
foreach ($rows ?: [] as $row) {
    $label = trim((string)$row['cognome'] . ' ' . (string)$row['nome']) . ' - ' . (string)$row['classe'];
    $studenti[intval($row['id'])] = [
        'id' => intval($row['id']),
        'label' => $label,
    ];
}

if ($esame_id > 0) {
    $recuperi = dbGetAll("
        SELECT DISTINCT
            st.id,
            st.cognome,
            st.nome,
            COALESCE(c.classe, 'classe non trovata') AS classe
        FROM geometri_esiti ge
        INNER JOIN geometri_sessioni gs ON gs.id = ge.id_sessione
        INNER JOIN studente st ON st.id = ge.id_studente
        LEFT JOIN studente_frequenta sf
            ON sf.id_studente = st.id
           AND sf.id_anno_scolastico = " . dbI($anno_id) . "
        LEFT JOIN classi c ON c.id = sf.id_classe
        WHERE gs.id_esame = " . dbI($esame_id) . "
          AND ge.esito IN ('assente', 'non_superato', 'da_valutare')
          AND NOT EXISTS (
              SELECT 1
              FROM geometri_esiti ok_esito
              INNER JOIN geometri_sessioni ok_s ON ok_s.id = ok_esito.id_sessione
              WHERE ok_esito.id_studente = ge.id_studente
                AND ok_s.id_esame = gs.id_esame
                AND ok_esito.esito = 'superato'
          )
        ORDER BY st.cognome ASC, st.nome ASC
    ");

    foreach ($recuperi ?: [] as $row) {
        $id = intval($row['id']);
        $label = trim((string)$row['cognome'] . ' ' . (string)$row['nome']) . ' - recupero';
        if (trim((string)$row['classe']) !== '') $label .= ' - ' . (string)$row['classe'];
        $studenti[$id] = [
            'id' => $id,
            'label' => $label,
        ];
    }
}

echo json_encode([
    'success' => true,
    'studenti' => array_values($studenti),
], JSON_UNESCAPED_UNICODE);
