<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('dirigente','segreteria-ata', 'ras');

header('Content-Type: application/json; charset=utf-8');

// per stato
$byStato = dbGetAll("
  SELECT stato, COUNT(*) AS n
  FROM permesso_ata_richiesta
  WHERE stato <> 'BOZZA'
  GROUP BY stato
");

// per tipo
$byTipo = dbGetAll("
  SELECT t.codice, t.descrizione, COUNT(*) AS n
  FROM permesso_ata_richiesta r
  JOIN permesso_ata_tipo t ON t.id = r.permesso_ata_tipo_id
  WHERE r.stato <> 'BOZZA'
  GROUP BY t.codice, t.descrizione
  ORDER BY t.codice
");

// per mese (ultimi 12)
$byMese = dbGetAll("
  SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym,
         SUM(stato='INVIATO') AS inviati,
         SUM(stato='AGGIORNATA') AS aggiornate,
         SUM(stato='MODIFICATA') AS modificate,
         SUM(stato='APPROVATO') AS approvati,
         SUM(stato='RESPINTO') AS respinti
  FROM permesso_ata_richiesta
  WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    AND stato <> 'BOZZA'
  GROUP BY ym
  ORDER BY ym
");

$daRegistrare = dbGetFirst("
  SELECT COUNT(*) AS n
  FROM permesso_ata_richiesta
  WHERE COALESCE(registrato_segreteria, 0) = 0
    AND stato <> 'BOZZA'
    AND stato <> 'ANNULLATO'
");

$registrato = dbGetFirst("
  SELECT COUNT(*) AS n
  FROM permesso_ata_richiesta
  WHERE COALESCE(registrato_segreteria, 0) = 1
    AND stato <> 'BOZZA'
    AND stato <> 'ANNULLATO'
");

echo json_encode([
  'ok' => true,
  'byStato' => $byStato,
  'byTipo' => $byTipo,
  'byMese' => $byMese,
  'daRegistrare' => intval($daRegistrare['n'] ?? 0),
  'registrato' => intval($registrato['n'] ?? 0)
], JSON_UNESCAPED_UNICODE);
