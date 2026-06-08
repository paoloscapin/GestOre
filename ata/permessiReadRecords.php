<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

ruoloRichiesto('personale-ata');

function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function statoLabelClass($stato)
{
  $s = strtoupper(trim((string)$stato));
  if ($s === 'BOZZA') return 'label-default';
  if ($s === 'INVIATA' || $s === 'INVIATO') return 'label-info';
  if ($s === 'AGGIORNATA' || $s === 'MODIFICATA') return 'label-warning';
  if ($s === 'APPROVATA' || $s === 'APPROVATO') return 'label-success';
  if ($s === 'RESPINTA' || $s === 'RESPINTO') return 'label-danger';
  if ($s === 'APPROVATO_PARZIALE') return 'label-warning';
  return 'label-primary';
}

function formatDateTimeIt($value)
{
  $value = trim((string)$value);
  if ($value === '') return '';

  $ts = strtotime($value);
  if ($ts === false) return h($value);

  return date('d.m.Y H:i', $ts);
}

function formatDateIt($value)
{
  $value = trim((string)$value);
  if ($value === '') return '';

  $ts = strtotime($value);
  if ($ts === false) return h($value);

  return date('d.m.Y', $ts);
}

function formatPeriodoRichiesta($dataDal, $dataAl)
{
  $dataDal = trim((string)$dataDal);
  $dataAl = trim((string)$dataAl);

  if ($dataDal === '' && $dataAl === '') return '';
  if ($dataDal === '') $dataDal = $dataAl;
  if ($dataAl === '') $dataAl = $dataDal;

  if ($dataDal === $dataAl) {
    return formatDateIt($dataDal);
  }

  return 'dal ' . formatDateIt($dataDal) . ' al ' . formatDateIt($dataAl);
}

$html = '<div class="permessi-cards">';

$query = "
SELECT
  r.id,
  r.stato,
  r.created_at,
  r.updated_at,
  r.ferie_sottotipo,
  t.codice,
  t.descrizione,
  (
    SELECT COUNT(*)
    FROM permesso_ata_richiesta_riga rr
    WHERE rr.permesso_ata_richiesta_id = r.id
  ) AS righe_count,
  (
    SELECT COALESCE(SUM(
      CASE
        WHEN rr2.data_dal IS NULL OR rr2.data_dal = '' THEN 0
        WHEN rr2.data_al IS NULL OR rr2.data_al = '' THEN 1
        ELSE DATEDIFF(rr2.data_al, rr2.data_dal) + 1
      END
    ), 0)
    FROM permesso_ata_richiesta_riga rr2
    WHERE rr2.permesso_ata_richiesta_id = r.id
  ) AS ferie_giorni_count,
  (
    SELECT MIN(rr3.data_dal)
    FROM permesso_ata_richiesta_riga rr3
    WHERE rr3.permesso_ata_richiesta_id = r.id
  ) AS data_min,
  (
    SELECT MAX(COALESCE(NULLIF(rr4.data_al, ''), rr4.data_dal))
    FROM permesso_ata_richiesta_riga rr4
    WHERE rr4.permesso_ata_richiesta_id = r.id
  ) AS data_max
FROM permesso_ata_richiesta r
INNER JOIN permesso_ata_tipo t ON t.id = r.permesso_ata_tipo_id
WHERE r.personale_ata_id = $__ata_id
ORDER BY r.updated_at DESC, r.id DESC
";

$rows = dbGetAll($query);
if (!is_array($rows)) $rows = [];

if (count($rows) === 0) {
  $html .= '
    <div class="alert alert-info" style="border-radius:16px;">
      Non risultano ancora richieste inserite.
    </div>
  ';
} else {
  $numeroVisuale = 0;

  foreach ($rows as $row) {
    $numeroVisuale++;

    $id = (int)$row['id'];
    $codice = trim((string)$row['codice']);
    $descrizione = trim((string)$row['descrizione']);
    $ferieSottotipo = strtoupper(trim((string)$row['ferie_sottotipo']));
    $periodoRichiesta = formatPeriodoRichiesta($row['data_min'] ?? '', $row['data_max'] ?? '');

    $tipoTitolo = $descrizione !== '' ? $descrizione : $codice;
    if ($codice === 'FERIE' && $ferieSottotipo !== '') {
      if ($ferieSottotipo === 'ESTIVE') {
        $tipoTitolo = 'Ferie estive';
      }
    }

    $stato = trim((string)$row['stato']);
    $statoClass = statoLabelClass($stato);

    $btnEditHref = ($codice === 'FERIE' && $ferieSottotipo !== '')
      ? 'ferieRichiesta.php?sottotipo=' . $ferieSottotipo . '&id=' . $id
      : null;

    $btnEditLabel = ($codice === 'FERIE' && in_array(strtoupper($stato), ['APPROVATO', 'APPROVATA', 'APPROVATO_PARZIALE', 'PARZIALE', 'MODIFICATA'], true))
      ? 'Modifica'
      : 'Apri';

    $btnEdit = $btnEditHref
      ? '
      <a href="' . $btnEditHref . '" class="btn btn-warning btn-lg btn-block">
        <span class="glyphicon glyphicon-pencil"></span>&ensp;' . h($btnEditLabel) . '
      </a>'
      : '
      <button type="button"
              class="btn btn-warning btn-lg btn-block btn-open-permesso"
              data-id="' . $id . '">
        <span class="glyphicon glyphicon-pencil"></span>&ensp;Apri
      </button>';

    $btnSummary = ($codice === 'FERIE' && $ferieSottotipo !== '')
      ? '
      <button type="button"
              class="btn btn-info btn-lg btn-block btn-ferie-riepilogo"
              data-id="' . $id . '"
              data-sottotipo="' . h($ferieSottotipo) . '">
        <span class="glyphicon glyphicon-list-alt"></span>&ensp;Riepilogo
      </button>'
      : '';

    $btnDel = (strtoupper($stato) === 'BOZZA')
      ? '
      <button type="button"
              class="btn btn-danger btn-lg btn-block btn-delete-permesso"
              data-id="' . $id . '"
              data-codice="' . h($codice) . '"
              data-ferie-sottotipo="' . h($ferieSottotipo) . '">
        <span class="glyphicon glyphicon-trash"></span>&ensp;Elimina
      </button>'
      : '';

    $html .= '
      <div class="panel panel-default" style="border-radius:18px; overflow:hidden; margin-bottom:14px; box-shadow:0 2px 8px rgba(0,0,0,.06); border:1px solid #e5e7eb;">
        <div class="panel-body" style="padding:16px;">
          <div style="font-size:20px; font-weight:700; color:#2d3340; margin-bottom:6px;">
            ' . h($tipoTitolo) . '
          </div>

          ' . ($periodoRichiesta !== '' ? '
          <div style="font-size:16px; color:#4b5563; line-height:1.45; margin-bottom:12px;">
            <strong>Periodo:</strong> ' . h($periodoRichiesta) . '
          </div>' : '') . '

          <div style="margin-bottom:12px;">
            <span class="label ' . $statoClass . '" style="font-size:14px; padding:8px 10px;">' . h($stato) . '</span>
          </div>

          <div class="row">
            ' . ($btnSummary !== '' ? '
            <div class="col-xs-12 col-sm-6" style="margin-bottom:8px;">
              ' . $btnSummary . '
            </div>' : '') . '
            <div class="col-xs-12 col-sm-6" style="margin-bottom:8px;">
              ' . $btnEdit . '
            </div>
            <div class="col-xs-12 col-sm-6" style="margin-bottom:8px;">
              ' . $btnDel . '
            </div>
          </div>
        </div>
      </div>
    ';
  }
}

$html .= '</div>';

echo $html;
