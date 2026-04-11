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
  ) AS righe_count
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
    $righeCount = (int)$row['righe_count'];

    $tipoTitolo = $descrizione !== '' ? $descrizione : $codice;
    $tipoCompleto = $codice;
    if ($descrizione !== '' && strtoupper($descrizione) !== strtoupper($codice)) {
      $tipoCompleto .= ' - ' . $descrizione;
    }

    $extraInfo = '';
    if ($codice === 'FERIE' && $ferieSottotipo !== '') {
      if ($ferieSottotipo === 'ESTIVE') {
        $tipoTitolo = 'Ferie estive';
        $extraInfo .= '<div><strong>Tipologia:</strong> Ferie estive</div>';
        $extraInfo .= '<div><strong>Giorni selezionati:</strong> ' . h((string)$righeCount) . '</div>';
      } else {
        $extraInfo .= '<div><strong>Tipologia ferie:</strong> ' . h($ferieSottotipo) . '</div>';
      }
    }

    $stato = trim((string)$row['stato']);
    $statoClass = statoLabelClass($stato);

    $btnEditHref = ($codice === 'FERIE' && $ferieSottotipo !== '')
      ? 'ferieRichiesta.php?sottotipo=' . $ferieSottotipo . '&id=' . $id
      : null;

    $btnEdit = $btnEditHref
      ? '
      <a href="' . $btnEditHref . '" class="btn btn-warning btn-lg btn-block">
        <span class="glyphicon glyphicon-pencil"></span>&ensp;Apri
      </a>'
      : '
      <button type="button"
              class="btn btn-warning btn-lg btn-block btn-open-permesso"
              data-id="' . $id . '">
        <span class="glyphicon glyphicon-pencil"></span>&ensp;Apri
      </button>';

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

          <div style="font-size:14px; color:#6b7280; margin-bottom:10px;">
            Richiesta ' . $numeroVisuale . '
          </div>

          <div style="font-size:17px; line-height:1.4; margin-bottom:10px;">
            <strong>Tipo:</strong><br>' . h($tipoCompleto) . '
          </div>

          ' . ($extraInfo !== '' ? '
          <div style="font-size:15px; color:#4b5563; line-height:1.5; margin-bottom:10px;">
            ' . $extraInfo . '
          </div>' : '') . '

          <div style="margin-bottom:12px;">
            <span class="label ' . $statoClass . '" style="font-size:14px; padding:8px 10px;">' . h($stato) . '</span>
          </div>

          <div style="font-size:15px; color:#4b5563; line-height:1.5; margin-bottom:14px;">
            <div><strong>Creata:</strong> ' . h(formatDateTimeIt($row['created_at'])) . '</div>
            <div><strong>Aggiornata:</strong> ' . h(formatDateTimeIt($row['updated_at'])) . '</div>
          </div>

          <div class="row">
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
