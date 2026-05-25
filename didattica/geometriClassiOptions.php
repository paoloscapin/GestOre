<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';

header('Content-Type: application/json; charset=utf-8');
ruoloRichiesto('esterno', 'docente', 'segreteria-didattica', 'dirigente');

$anno_id = intval($_GET['anno_id'] ?? $__anno_scolastico_corrente_id);
if ($anno_id <= 0) $anno_id = intval($__anno_scolastico_corrente_id);

$rows = dbGetAll("
    SELECT DISTINCT
        c.id,
        c.classe,
        c.anno,
        i1.nome_breve AS ind1,
        i2.nome_breve AS ind2
    FROM studente_frequenta sf
    INNER JOIN classi c ON c.id = sf.id_classe
    LEFT JOIN indirizzo i1 ON i1.id = c.id_primo_indirizzo
    LEFT JOIN indirizzo i2 ON i2.id = c.id_secondo_indirizzo
    WHERE sf.id_anno_scolastico = " . dbI($anno_id) . "
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
    ORDER BY c.classe ASC
");

$classi = [];
foreach ($rows ?: [] as $row) {
    $indirizzo = trim((string)($row['ind1'] ?? ''));
    if (trim((string)($row['ind2'] ?? '')) !== '') {
        $indirizzo .= ($indirizzo !== '' ? '/' : '') . trim((string)$row['ind2']);
    }
    $label = trim((string)$row['classe']) . ($indirizzo !== '' ? ' - ' . $indirizzo : '');
    $classi[] = [
        'id' => intval($row['id']),
        'label' => $label,
    ];
}

echo json_encode([
    'success' => true,
    'classi' => $classi,
], JSON_UNESCAPED_UNICODE);

