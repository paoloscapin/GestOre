<?php

declare(strict_types=1);

require_once __DIR__ . '/../send-mail.php';

const MAIL_TEST_OVERRIDE = 'massimo.saiani@buonarroti.tn.it'; // se non vuota, tutte le mail saranno inviate a questo indirizzo (utile per test)
const EVENTI_FROM_NAME = 'Il Team Eventi';

function buildMailSubject(array $assignment): string
{
    return count($assignment['posti']) > 1
        ? 'I tuoi biglietti per l’evento'
        : 'Il tuo biglietto per l’evento';
}

function buildMailBody(array $assignment): string
{
    $posti   = implode(', ', array_map('strval', $assignment['posti'] ?? []));
    $name    = htmlspecialchars((string)($assignment['display_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $blockLabel = ticketBlockDisplayLabel($assignment);
    $venueLabel = trim((string)($assignment['tribuna'] ?? ''));
    if ($venueLabel !== '' && $blockLabel !== '' && function_exists('ticketsVenueIsGradinata') && ticketsVenueIsGradinata($venueLabel)) {
        $venueLabel .= ' - ' . $blockLabel;
    }
    $tribuna = htmlspecialchars($venueLabel, ENT_QUOTES, 'UTF-8');
    $blockLabelHtml = htmlspecialchars($blockLabel, ENT_QUOTES, 'UTF-8');
    $fila    = (int)($assignment['fila'] ?? 0);
    $plural  = count($assignment['posti'] ?? []) > 1;

    $introBiglietto = $plural
        ? 'in allegato trovi i tuoi biglietti per l’evento.'
        : 'in allegato trovi il tuo biglietto per l’evento.';

    $mapsUrl = 'https://maps.app.goo.gl/RmnHi7vETKt1EpMr9';

    // URL immagini pubbliche reali
    $headerIcon = 'https://www.buonarroti.tn.it/GestOre/common/tickets/icon-header.png';
    $ticketIcon = 'https://www.buonarroti.tn.it/GestOre/common/tickets/icon-ticket.png';
    $trentinoVolleyLogo = TRENTINO_VOLLEY_LOGO_URL;

    return ''
        . '<div style="margin:0;padding:24px 0;background-color:#f3f6fb;">'
        . '  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;">'
        . '    <tr>'
        . '      <td align="left" style="padding:0 16px;">'
        . '        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:760px;border-collapse:separate;border-spacing:0;background:#ffffff;border:1px solid #dbe4f0;border-radius:18px;overflow:hidden;">'

                . '          <tr>'
                . '            <td style="padding:0;background:linear-gradient(135deg,#1d4ed8 0%,#60a5fa 100%);">'
                . '              <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;">'
                . '                <tr>'
                . '                  <td style="padding:22px 18px 22px 26px;width:84px;vertical-align:middle;">'
                . '                    <img src="' . htmlspecialchars($headerIcon, ENT_QUOTES, 'UTF-8') . '" alt="" width="58" height="58" style="display:block;border:0;outline:none;text-decoration:none;">'
                . '                  </td>'
                . '                  <td style="padding:22px 10px 22px 0;vertical-align:middle;">'
                . '                    <div style="font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:700;line-height:1.2;color:#ffffff;">GestOre ITT Buonarroti</div>'
                . '                  </td>'
                . '                  <td style="padding:18px 26px 18px 10px;vertical-align:middle;text-align:right;width:140px;">'
                . '                    <img src="' . htmlspecialchars($trentinoVolleyLogo, ENT_QUOTES, 'UTF-8') . '" alt="Trentino Volley" style="display:inline-block;max-height:52px;width:auto;background:#ffffff;border-radius:10px;padding:5px;border:0;outline:none;text-decoration:none;">'
                . '                  </td>'
                . '                </tr>'
                . '              </table>'
                . '            </td>'
                . '          </tr>'
        . '              </table>'
        . '            </td>'
        . '          </tr>'
        . '          <tr>'
        . '            <td style="padding:34px 38px 36px 38px;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">'
        . '              <p style="margin:0 0 18px 0;font-size:16px;line-height:1.65;">Buongiorno' . ($name !== '' ? ' <strong>' . $name . '</strong>' : '') . ',</p>'
        . '              <p style="margin:0 0 14px 0;font-size:16px;line-height:1.7;">' . $introBiglietto . '</p>'
        . '              <p style="margin:0 0 14px 0;font-size:16px;line-height:1.7;">in allegato il biglietto per la partita <strong>domenica 19 APRILE ore 18.00</strong> con accesso al palazzetto dalle ore <strong>17:10</strong>.</p>'
        . '              <p style="margin:0 0 22px 0;font-size:16px;line-height:1.7;">Si ricorda che la partita è presso la <strong>BTS Arena</strong>:<br>'
        . '                <a href="' . htmlspecialchars($mapsUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#2563eb;text-decoration:none;font-weight:700;">' . htmlspecialchars($mapsUrl, ENT_QUOTES, 'UTF-8') . '</a>'
        . '              </p>'

        . '              <div style="border:1px solid #d9e5f2;border-radius:18px;background:#f8fbff;padding:22px 24px;margin:0 0 24px 0;">'
        . '                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="200%" style="border-collapse:collapse;">'
        . '                  <tr>'
        . '                    <td style="width:88px;vertical-align:middle;padding:0 10px 12px 0;">'
        . '                      <img src="' . htmlspecialchars($ticketIcon, ENT_QUOTES, 'UTF-8') . '" 
     alt="" 
     width="90" 
     height="90" 
     style="display:block;border:0;outline:none;text-decoration:none;">'
        . '                    </td>'
        . '                    <td style="vertical-align:middle;padding:0 0 12px 0;">'
        . '                      <div style="font-size:18px;line-height:1.35;color:#0f172a;">'
        . '                        <strong>Tribuna</strong> <span style="font-weight:400;margin-left:10px;">' . $tribuna . '</span>'
        . '                      </div>'
        . '                    </td>'
        . '                  </tr>'
        . '                </table>'

        . '                <div style="height:1px;background:#e4ecf5;margin:4px 0 16px 0;"></div>'

        . '                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;width:100%;">'
        . '                  <tr>'
        . '                    <td style="padding:8px 14px 8px 0;font-size:15px;font-weight:700;color:#0f172a;width:120px;">Fila</td>'
        . '                    <td style="padding:8px 0;font-size:15px;color:#1f2937;">' . $fila . '</td>'
        . '                  </tr>'
        . ($blockLabel !== ''
            ? '                  <tr>'
            . '                    <td style="padding:8px 14px 8px 0;font-size:15px;font-weight:700;color:#0f172a;">' . (function_exists('ticketsVenueIsGradinata') && ticketsVenueIsGradinata((string)($assignment['tribuna'] ?? '')) ? 'Settore' : 'Blocco') . '</td>'
            . '                    <td style="padding:8px 0;font-size:15px;color:#1f2937;">' . $blockLabelHtml . '</td>'
            . '                  </tr>'
            : '')
        . '                  <tr>'
        . '                    <td style="padding:8px 14px 8px 0;font-size:15px;font-weight:700;color:#0f172a;">Posti</td>'
        . '                    <td style="padding:8px 0;font-size:15px;color:#1f2937;">' . htmlspecialchars($posti, ENT_QUOTES, 'UTF-8') . '</td>'
        . '                  </tr>'
        . '                </table>'
        . '              </div>'

        . '              <p style="margin:0 0 10px 0;font-size:15px;line-height:1.7;color:#334155;">Ti consigliamo di conservare il PDF allegato e di averlo disponibile il giorno dell’evento.</p>'
        . '              <p style="margin:26px 0 0 0;font-size:16px;line-height:1.7;">--<br>Un cordiale saluto<br><strong>Il Team Eventi</strong></p>'
        . '            </td>'
        . '          </tr>'

        . '        </table>'
        . '      </td>'
        . '    </tr>'
        . '  </table>'
        . '</div>';
}

function sendTicketMail(string $to, string $toName, string $subject, string $body, string $attachmentPath): bool
{
    if (!function_exists('sendMailwithAttachment')) {
        throw new RuntimeException('Funzione sendMailwithAttachment non trovata in send-mail.php');
    }

    if (!is_file($attachmentPath)) {
        throw new RuntimeException('Allegato non trovato: ' . $attachmentPath);
    }

    $destinatario = MAIL_TEST_OVERRIDE !== '' ? MAIL_TEST_OVERRIDE : $to;
    $destinatarioNome = MAIL_TEST_OVERRIDE !== '' ? 'Destinazione test' : $toName;

    return (bool) sendMailwithAttachment($destinatario, $destinatarioNome, $subject, $body, $attachmentPath);
}

function sendAllEmails(array $assignments, string $pdfOutDir): array
{
    $results = [];

    foreach ($assignments as $row) {
        $safeEmail = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $row['email']) ?: 'utente';
        $attachment = $pdfOutDir . '/' . $safeEmail . '.pdf';

        try {
            $ok = sendTicketMail(
                $row['email'],
                $row['display_name'],
                buildMailSubject($row),
                buildMailBody($row),
                $attachment
            );

            $results[] = [
                'email_originale' => $row['email'],
                'email_inviata_a' => MAIL_TEST_OVERRIDE !== '' ? MAIL_TEST_OVERRIDE : $row['email'],
                'ok' => $ok,
                'detail' => $ok ? 'Inviata' : 'Invio fallito',
            ];
        } catch (Throwable $e) {
            $results[] = [
                'email_originale' => $row['email'],
                'email_inviata_a' => MAIL_TEST_OVERRIDE !== '' ? MAIL_TEST_OVERRIDE : $row['email'],
                'ok' => false,
                'detail' => $e->getMessage(),
            ];
        }
    }

    return $results;
}
