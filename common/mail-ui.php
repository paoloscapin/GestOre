<?php
/**
 * UI helpers per email HTML (GestOre)
 */

function mailWrap(
    $titleHtml,
    $toName,
    $introHtml,
    $contentHtml,
    $footerHtml = '',
    string $theme = 'default'
): string
{
    // 🎨 palette per tipo di mail
    $themes = [
        'default' => [
            'brand' => '#1f5e3b', // verde istituto
        ],
        'docente' => [
            'brand' => '#1d4ed8', // blu
        ],
        'studente' => [
            'brand' => '#0f766e', // verde acqua
        ],
        'annullamento' => [
            'brand' => '#b91c1c', // rosso
        ],
        'mbapp' => [
            'brand' => '#7c3aed', // viola
        ],
        'warning' => [
            'brand' => '#b45309', // arancione
        ],
    ];

    $brand  = $themes[$theme]['brand'] ?? $themes['default']['brand'];

    $bg     = "#f3f6f8";
    $card   = "#ffffff";
    $txt    = "#1f2937";
    $muted  = "#6b7280";
    $border = "#e5e7eb";

    $toNameSafe = htmlspecialchars((string)$toName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return '
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width">
  <title>' . strip_tags($titleHtml) . '</title>
</head>
<body style="margin:0;background:' . $bg . ';font-family:Lato, Arial, Helvetica, sans-serif;color:' . $txt . ';">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:' . $bg . ';padding:24px 10px;">
    <tr>
      <td align="center">
        <table role="presentation" width="720" cellspacing="0" cellpadding="0" style="max-width:720px;width:100%;">
          <tr>
            <td style="padding:0 0 12px 0;">
              <div style="background:' . $brand . ';color:#fff;padding:18px 20px;border-radius:14px 14px 0 0;
                          font-size:18px;font-weight:800;letter-spacing:.3px;line-height:1.2;">
                ' . $titleHtml . '
              </div>
            </td>
          </tr>

          <tr>
            <td style="background:' . $card . ';border:1px solid ' . $border . ';
                       border-top:none;border-radius:0 0 14px 14px; padding:18px 20px;">

              <div style="font-size:14px;line-height:1.55;color:' . $txt . ';margin-bottom:10px;">
                <div style="font-weight:800;font-size:15px;">' . $toNameSafe . '</div>
                <div style="color:' . $muted . ';font-size:13px;">' . $introHtml . '</div>
              </div>

              <div style="height:1px;background:' . $border . ';margin:14px 0;"></div>

              ' . $contentHtml . '

              ' . ($footerHtml ? '<div style="height:1px;background:' . $border . ';margin:14px 0;"></div>
              <div style="font-size:12.5px;line-height:1.55;color:' . $muted . ';">' . $footerHtml . '</div>' : '') . '

            </td>
          </tr>

          <tr>
            <td style="padding:14px 4px 0 4px;text-align:center;color:' . $muted . ';font-size:11.5px;line-height:1.4;">
              Messaggio automatico da GestOre – ' . htmlspecialchars((string)date('d/m/Y H:i'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
}

function badge($text, $bg, $color = "#111827"): string
{
    $t = htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '<span style="display:inline-block;padding:6px 10px;border-radius:999px;background:' . $bg . ';
                  color:' . $color . ';font-weight:800;font-size:12px;letter-spacing:.2px;">' . $t . '</span>';
}

function kvRow($k, $v): string
{
    $k = htmlspecialchars((string)$k, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $v = htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '
      <tr>
        <td style="padding:8px 10px;border-top:1px solid #e5e7eb;color:#6b7280;font-size:12.5px;width:34%;">' . $k . '</td>
        <td style="padding:8px 10px;border-top:1px solid #e5e7eb;font-weight:800;font-size:13.5px;">' . $v . '</td>
      </tr>';
}

function studentiTableHtml(array $rows): string
{
    if (!$rows) return '';

    $thead = '
    <tr>
      <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;font-size:12.5px;color:#6b7280;">Classe</th>
      <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;font-size:12.5px;color:#6b7280;">Cognome</th>
      <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;font-size:12.5px;color:#6b7280;">Nome</th>
      <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;font-size:12.5px;color:#6b7280;">Argomento</th>
    </tr>';

    $body = '';
    foreach ($rows as $r) {
        $cls = htmlspecialchars((string)($r['studente_classe'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $cog = htmlspecialchars((string)($r['studente_cognome'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $nom = htmlspecialchars((string)($r['studente_nome'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $arg = htmlspecialchars((string)($r['sportello_argomento'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $body .= '
        <tr>
          <td style="padding:10px;border-bottom:1px solid #f1f5f9;font-weight:800;">' . $cls . '</td>
          <td style="padding:10px;border-bottom:1px solid #f1f5f9;">' . $cog . '</td>
          <td style="padding:10px;border-bottom:1px solid #f1f5f9;">' . $nom . '</td>
          <td style="padding:10px;border-bottom:1px solid #f1f5f9;color:#374151;">' . $arg . '</td>
        </tr>';
    }

    return '
    <div style="margin-top:14px;">
      <div style="font-weight:900;font-size:14px;margin:0 0 8px 0;">Elenco studenti iscritti</div>
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
        <thead style="background:#f8fafc;">' . $thead . '</thead>
        <tbody>' . $body . '</tbody>
      </table>
    </div>';
}

function mailMbappCancelHtml(string $aula, string $dataIt, string $ora, int $sportello_id, string $categoria, string $materia): string
{
    $title = "ANNULLAMENTO PRENOTAZIONE AULA (MBApp)";
    $intro = "Notifica automatica: la prenotazione aula è stata annullata perché lo sportello è stato annullato.";

    // nome destinatario “ufficio” (se disponibile in $__settings)
    $toName = 'Prenotazioni aule';
    if (isset($__GLOBALS['__settings']->MBApp->destPrenotazioniAule) && $__GLOBALS['__settings']->MBApp->destPrenotazioniAule != '') {
        $toName = (string)$__GLOBALS['__settings']->MBApp->destPrenotazioniAule;
    }

    $content = '
      <div style="margin:0 0 12px 0;">
        ' . badge('PRENOTAZIONE ANNULLATA', '#fee2e2', '#7f1d1d') . '
      </div>

      <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:12px 12px;margin:0 0 14px 0;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
          ' . kvRow('Aula', ($aula !== '' ? $aula : '—')) . '
          ' . kvRow('Data', $dataIt) . '
          ' . kvRow('Ora', $ora) . '
          ' . kvRow('Motivo', 'Sportello annullato (0 iscritti)') . '
          ' . kvRow('ID Sportello', (string)$sportello_id) . '
          ' . kvRow('Attività', $categoria) . '
          ' . kvRow('Materia', $materia) . '
        </table>
      </div>

      <div style="font-size:13.5px;line-height:1.55;color:#374151;">
        Non è richiesta alcuna azione. Questa email serve solo come tracciamento della modifica.
      </div>
    ';

    $footer = "Messaggio automatico: gestione prenotazioni aule (MBApp).";

    return mailWrap($title, $toName, $intro, $content, $footer);
}

