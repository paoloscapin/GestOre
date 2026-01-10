<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

header('Content-Type: application/json; charset=utf-8');

$id_corso = intval($_GET['id_corso'] ?? 0);
$recupero_assenza = intval($_GET['recupero_assenza'] ?? 0); // 0/1

if ($id_corso <= 0) {
  echo json_encode(['success' => false, 'error' => 'Parametro mancante']);
  exit;
}

$corso1 = dbGetFirst("SELECT id_materia,id_anno_scolastico FROM corso WHERE id=$id_corso LIMIT 1");
if (!$corso1) {
  echo json_encode(['success' => false, 'error' => 'Corso non trovato']);
  exit;
}

$id_materia = intval($corso1['id_materia']);
$id_anno    = intval($corso1['id_anno_scolastico']);
$sessione_dest = ($recupero_assenza === 1) ? 1 : 2;

$rows = dbGetAll("
  SELECT c.id,
         c.titolo,
         d.cognome,
         d.nome,
         (
            SELECT ed.data_inizio_esame
            FROM corso_esami_date ed
            WHERE ed.id_corso = c.id AND ed.tentativo = 1
            ORDER BY ed.id ASC
            LIMIT 1
         ) AS esame_inizio,
         CASE
           WHEN LOWER(c.titolo) LIKE '%recupero assenza%' THEN 1
           ELSE 0
         END AS recupero_assenza
  FROM corso c
  INNER JOIN docente d ON d.id = c.id_docente
  WHERE c.carenza = 1
    AND c.carenza_sessione = 2
    AND c.id_materia = $id_materia
    AND c.id_anno_scolastico = $id_anno
  ORDER BY d.cognome, d.nome, c.titolo, c.id
");

$rows = $rows ?: [];

// filtro finale (0/1) coerente con la UI
$rows = array_values(array_filter($rows, function ($r) use ($recupero_assenza) {
    return intval($r['recupero_assenza']) === intval($recupero_assenza);
}));

echo json_encode(['success' => true, 'corsi' => $rows], JSON_UNESCAPED_UNICODE);
