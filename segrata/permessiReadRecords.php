<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('dirigente', 'segreteria-ata');

function esc_sql_like(string $s): string
{
  global $__con;
  if (isset($__con) && $__con instanceof mysqli) {
    return mysqli_real_escape_string($__con, $s);
  }
  return addslashes($s);
}

function fmtDateTimeIT($dt): string
{
  if (!$dt) return '';
  $ts = strtotime($dt);
  if (!$ts) return (string)$dt;
  return date('d/m/Y H:i', $ts);
}

function fmtDateIT($d): string
{
  if (!$d) return '';
  $dt = DateTime::createFromFormat('Y-m-d', (string)$d);
  if ($dt) return $dt->format('d/m/Y');

  $ts = strtotime((string)$d);
  return $ts ? date('d/m/Y', $ts) : (string)$d;
}

function fmtOraIT($o): string
{
  $o = trim((string)$o);
  if ($o === '') return '';
  return substr($o, 0, 5);
}

function buildPermessoPeriodoLabel(array $righe, string $tipoCodice): string
{
  if (count($righe) === 0) return '';

  $tipoCodice = strtoupper(trim($tipoCodice));

  $prima = $righe[0];
  $ultima = $righe[count($righe) - 1];

  $dataDal = (string)($prima['data_dal'] ?? '');
  $dataAl  = (string)($ultima['data_al'] ?? '');
  if ($dataAl === '') $dataAl = $dataDal;

  if ($tipoCodice === 'FERIE') {
    if ($dataDal !== '' && $dataAl !== '' && $dataDal !== $dataAl) {
      return 'Dal giorno ' . fmtDateIT($dataDal) . ' al giorno ' . fmtDateIT($dataAl);
    }
    return 'Giorno ' . fmtDateIT($dataDal ?: $dataAl);
  }

  $oraDal = trim((string)($prima['ora_dal'] ?? ''));
  $oraAl  = trim((string)($prima['ora_al'] ?? ''));

  $testo = '';
  if ($dataDal !== '' && $dataAl !== '' && $dataDal !== $dataAl) {
    $testo = 'Dal ' . fmtDateIT($dataDal) . ' al ' . fmtDateIT($dataAl);
  } else {
    $testo = fmtDateIT($dataDal ?: $dataAl);
  }

  if ($oraDal !== '' && $oraAl !== '') {
    $testo .= ' dalle ' . fmtOraIT($oraDal) . ' alle ' . fmtOraIT($oraAl);
  }

  return $testo;
}

function ferieSottotipoLabel(string $sottotipo): string
{
  $map = [
    'ESTIVE' => 'Ferie estive',
    'NATALE' => 'Ferie Natale',
    'CARNEVALE' => 'Ferie Carnevale',
    'PASQUA' => 'Ferie Pasqua',
    'ORDINARIE' => 'Ferie ordinarie',
  ];

  $key = strtoupper(trim($sottotipo));
  if ($key === '') return '';
  if (isset($map[$key])) return $map[$key];

  return 'Ferie ' . ucfirst(strtolower($key));
}

$stato      = isset($_GET['stato']) ? trim((string)$_GET['stato']) : '';
$statiParam = isset($_GET['stati']) ? $_GET['stati'] : [];
$registrazioniParam = isset($_GET['registrazioni']) ? $_GET['registrazioni'] : [];
$tipo_id    = isset($_GET['tipo_id']) ? intval($_GET['tipo_id']) : 0;
$profilo_id = isset($_GET['profilo_id']) ? intval($_GET['profilo_id']) : 0;
$ufficio_id = isset($_GET['ufficio_id']) ? intval($_GET['ufficio_id']) : 0;
$search     = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

$stati = [];
if (is_array($statiParam)) {
  foreach ($statiParam as $statoItem) {
    $stati[] = strtoupper(trim((string)$statoItem));
  }
}

$statiValidi = ['INVIATO', 'AGGIORNATA', 'APPROVATO', 'PARZIALE', 'RESPINTO', 'ANNULLATO'];
$stati = array_values(array_unique(array_intersect($stati, $statiValidi)));

$registrazioni = [];
if (is_array($registrazioniParam)) {
  foreach ($registrazioniParam as $registrazioneItem) {
    $registrazioni[] = strtoupper(trim((string)$registrazioneItem));
  }
}

$registrazioniValide = ['DA_REGISTRARE', 'REGISTRATO'];
$registrazioni = array_values(array_unique(array_intersect($registrazioni, $registrazioniValide)));

$where = " WHERE 1=1 ";

// La segreteria NON deve vedere le bozze
$where .= " AND r.stato <> 'BOZZA' ";

if ($stato !== '') {
  $stato_esc = esc_sql_like($stato);
  $where .= " AND r.stato = '$stato_esc' ";
} elseif (isset($_GET['stati'])) {
  if (count($stati) === 0) {
    $where .= " AND 1=0 ";
  } else {
    $statiSql = array_map(function ($statoValido) {
      return "'" . esc_sql_like($statoValido) . "'";
    }, $stati);
    $where .= " AND r.stato IN (" . implode(', ', $statiSql) . ") ";
  }
}

if (isset($_GET['registrazioni'])) {
  if (count($registrazioni) === 0) {
    $where .= " AND 1=0 ";
  } elseif (count($registrazioni) === 1) {
    if ($registrazioni[0] === 'DA_REGISTRARE') {
      $where .= " AND COALESCE(r.registrato_segreteria, 0) = 0 ";
    } elseif ($registrazioni[0] === 'REGISTRATO') {
      $where .= " AND COALESCE(r.registrato_segreteria, 0) = 1 ";
    }
  }
}

if ($tipo_id > 0) {
  $where .= " AND r.permesso_ata_tipo_id = $tipo_id ";
}

if ($profilo_id > 0) {
  $where .= " AND pr.id = $profilo_id ";
}

if ($ufficio_id > 0) {
  $where .= " AND u.id = $ufficio_id ";
}

if ($search !== '') {
  $s = esc_sql_like($search);
  $where .= " AND (
      p.cognome   LIKE '%$s%' OR
      p.nome      LIKE '%$s%' OR
      p.matricola LIKE '%$s%' OR
      p.email     LIKE '%$s%' OR
      p.username  LIKE '%$s%' OR
      pr.nome     LIKE '%$s%' OR
      u.nome      LIKE '%$s%'
  ) ";
}

$query = "
SELECT
  r.id,
  r.stato,
  r.created_at,
  r.updated_at,
  r.ferie_sottotipo,
  r.registrato_segreteria,
  r.registrato_il,
  t.codice AS tipo_codice,
  t.descrizione AS tipo_descrizione,
  p.username,
  p.cognome,
  p.nome,
  p.email,
  p.matricola,
  p.tipo_contratto,
  pr.nome AS profilo_nome,
  u.nome  AS ufficio_nome,
  rragg.data_dal_min,
  rragg.data_al_max,
  rragg.ora_dal_min,
  rragg.ora_al_max
FROM permesso_ata_richiesta r
JOIN permesso_ata_tipo t ON t.id = r.permesso_ata_tipo_id
JOIN personale_ata p ON p.id = r.personale_ata_id
LEFT JOIN personale_ata_profili pr
       ON pr.id = p.id_profilo
LEFT JOIN personale_ata_assegnazioni pa
       ON pa.username = p.username
      AND pa.attiva = 1
LEFT JOIN personale_ata_uffici u
       ON u.id = pa.id_ufficio
LEFT JOIN (
  SELECT
    permesso_ata_richiesta_id,
    MIN(data_dal) AS data_dal_min,
    MAX(COALESCE(NULLIF(data_al, ''), data_dal)) AS data_al_max,
    MIN(NULLIF(ora_dal, '')) AS ora_dal_min,
    MAX(NULLIF(ora_al, '')) AS ora_al_max
  FROM permesso_ata_richiesta_riga
  GROUP BY permesso_ata_richiesta_id
) rragg
  ON rragg.permesso_ata_richiesta_id = r.id
$where
ORDER BY r.created_at DESC
LIMIT 500
";

$rows = dbGetAll($query);
if (!is_array($rows)) {
  $rows = [];
}
$count = count($rows);

$data = '
<div class="table-responsive">
  <table class="table table-bordered table-hover table-striped table-green permessi-table">
    <thead>
      <tr>
        <th class="text-center">ID</th>
        <th>Dipendente</th>
        <th>Matricola</th>
        <th>Profilo</th>
        <th>Ufficio</th>
        <th>Tipo</th>
        <th class="text-center">Stato</th>
        <th class="text-center">Inviato</th>
        <th class="text-center">Registro</th>
        <th class="text-center">Azioni</th>
      </tr>
    </thead>
    <tbody>';

foreach ($rows as $r) {
  $id      = intval($r['id']);
  $dip     = htmlspecialchars(trim(($r['cognome'] ?? '') . ' ' . ($r['nome'] ?? '')));
  $mat     = htmlspecialchars($r['matricola'] ?? '');
  $profilo = htmlspecialchars($r['profilo_nome'] ?? '');
  $ufficio = htmlspecialchars($r['ufficio_nome'] ?? '');
  $tipoBase = ($r['tipo_codice'] ?? '') . ' - ' . ($r['tipo_descrizione'] ?? '');
  if (strtoupper(trim((string)($r['tipo_codice'] ?? ''))) === 'FERIE') {
    $sottotipoLabel = ferieSottotipoLabel((string)($r['ferie_sottotipo'] ?? ''));
    if ($sottotipoLabel !== '') {
      $tipoBase = ($r['tipo_codice'] ?? '') . ' - ' . $sottotipoLabel;
    }
  }
  $tipo = htmlspecialchars($tipoBase);

  $righePeriodo = [[
    'data_dal' => $r['data_dal_min'] ?? '',
    'data_al'  => $r['data_al_max'] ?? '',
    'ora_dal'  => $r['ora_dal_min'] ?? '',
    'ora_al'   => $r['ora_al_max'] ?? '',
  ]];

  $registrato = intval($r['registrato_segreteria'] ?? 0);
$registratoIl = htmlspecialchars(fmtDateTimeIT($r['registrato_il'] ?? ''));

$badgeRegistro = '<span class="label label-default">
  <span class="glyphicon glyphicon-time"></span> DA REGISTRARE
</span>';


if ($registrato === 1) {
  $badgeRegistro = '<span class="label label-success" title="' . $registratoIl . '">
    <span class="glyphicon glyphicon-book"></span> REGISTRATO
  </span>';
}
  $periodoLabel = buildPermessoPeriodoLabel($righePeriodo, (string)($r['tipo_codice'] ?? ''));
  $periodoHtml = $periodoLabel !== ''
    ? '<div style="font-size:11px; color:#777; line-height:1.25; margin-top:3px;">' . htmlspecialchars($periodoLabel) . '</div>'
    : '';
  $st      = strtoupper(trim((string)($r['stato'] ?? '')));
  $created = htmlspecialchars(fmtDateTimeIT($r['created_at'] ?? ''));

  $badge = '<span class="label label-default">-</span>';

  if ($st === 'INVIATO') {
    $badge = '<span class="label label-info">
    <span class="glyphicon glyphicon-send"></span> INVIATO
  </span>';
  } elseif ($st === 'AGGIORNATA') {
    $badge = '<span class="label label-warning" style="background:#f59e0b;">
    <span class="glyphicon glyphicon-refresh"></span> AGGIORNATA
  </span>';
  } elseif ($st === 'APPROVATO') {
    $badge = '<span class="label label-success">
    <span class="glyphicon glyphicon-ok"></span> APPROVATO
  </span>';
  } elseif ($st === 'RESPINTO') {
    $badge = '<span class="label label-danger">
    <span class="glyphicon glyphicon-remove"></span> RESPINTO
  </span>';
  } elseif ($st === 'ANNULLATO') {
    $badge = '<span class="label label-warning">
    <span class="glyphicon glyphicon-ban-circle"></span> ANNULLATO
  </span>';
  } elseif ($st === 'BOZZA') {
    $badge = '<span class="label label-primary">
    <span class="glyphicon glyphicon-edit"></span> BOZZA
  </span>';
  } elseif ($st === 'PARZIALE') {
    $badge = '<span class="label label-warning" style="background:#f39c12;">
    <span class="glyphicon glyphicon-adjust"></span> PARZIALE
  </span>';
  }

  $data .= '
  <tr>
    <td class="text-center"><strong>' . $id . '</strong></td>

  
<td>
  <div style="font-weight:600; line-height:1.2;">' . $dip . '</div>
  <div style="font-size:11px; color:#777;">' . $mat . '</div>
</td>

    <td>' . $mat . '</td>
    <td>' . $profilo . '</td>
    <td>' . $ufficio . '</td>
    <td title="' . htmlspecialchars($tipoBase) . '">
    <div style="font-weight:600; line-height:1.2;">' . $tipo . '</div>
    ' . $periodoHtml . '
    </td>
    <td class="text-center">' . $badge . '</td>
    <td class="text-center">' . $created . '</td>
    <td class="text-center">' . $badgeRegistro . '</td>
    <td class="text-center">
      <button class="btn btn-primary btn-xs" type="button" onclick="permessoOpen(' . $id . ')" title="Apri dettaglio">
        <span class="glyphicon glyphicon-eye-open"></span>
      </button>
      <a href="permessoPdf.php?id=' . $id . '" 
   class="btn btn-default btn-xs" 
   target="_blank" 
   title="Genera PDF">
  <span class="glyphicon glyphicon-print"></span>
</a>
    </td>
  </tr>';
}

if ($count === 0) {
  $data .= '
    <tr>
      <td colspan="9" class="text-center text-muted" style="padding:25px 10px;">
        Nessuna richiesta trovata
      </td>
    </tr>';
}

$data .= '
    </tbody>
  </table>
</div>';

echo $data;
