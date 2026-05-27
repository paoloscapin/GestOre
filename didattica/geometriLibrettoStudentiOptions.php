<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';

header('Content-Type: application/json; charset=utf-8');
ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

$rows = dbGetAll("
    SELECT DISTINCT
        st.id,
        st.cognome,
        st.nome,
        COALESCE(last_c.classe, '') AS classe
    FROM geometri_esiti ge
    INNER JOIN studente st ON st.id = ge.id_studente
    LEFT JOIN (
        SELECT sf1.id_studente, sf1.id_classe
        FROM studente_frequenta sf1
        INNER JOIN (
            SELECT id_studente, MAX(id_anno_scolastico) AS max_anno
            FROM studente_frequenta
            GROUP BY id_studente
        ) latest
            ON latest.id_studente = sf1.id_studente
           AND latest.max_anno = sf1.id_anno_scolastico
    ) last_sf ON last_sf.id_studente = st.id
    LEFT JOIN classi last_c ON last_c.id = last_sf.id_classe
    WHERE ge.esito = 'superato'
    ORDER BY st.cognome ASC, st.nome ASC
");

$studenti = [];
foreach ($rows ?: [] as $row) {
    $label = trim((string)$row['cognome'] . ' ' . (string)$row['nome']);
    if (trim((string)$row['classe']) !== '') $label .= ' - ' . (string)$row['classe'];
    $studenti[] = [
        'id' => intval($row['id']),
        'label' => $label,
    ];
}

echo json_encode(['success' => true, 'studenti' => $studenti], JSON_UNESCAPED_UNICODE);
