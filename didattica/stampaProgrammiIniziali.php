<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

/**
 * 
 * REGOLE
 * Ogni riga di testo viene generata con un bullet (pallino nero) iniziale.
 * Ogni volta che c'è un '.' , il punto viene rimosso ed il testo successivo viene messo su una riga successiva
 * Nel caso di '..' e '...' il punto viene ignorato e rimane nel testo visibile.
 * Se alla fine di una riga se c'è ';' il simbolo viene rimosso ed il testo va a capo su una nuova riga
 * Se una riga contiene un testo tutto maiuscolo, oppure inizia con '>>' la riga viene generata senza bullet, in grassetto, seguita da una linea vuota che separa dal testo successivo
 * Se voglio che una voce di una riga sia un elemento di secondo livello (pallino vuoto con rientranza) il testo deve iniziare con '>' oppure '--'
 * Se una riga termina con ':' la riga successiva viene generata come elemento di secondo livello (pallino vuoto con rientranza) 
 */

$pagina = '';

require_once '../common/checkSession.php';
ruoloRichiesto('docente', 'dirigente', 'segreteria-didattica');
// program.php (in testa al file, prima di qualsiasi uso di mPDF)
require_once '../common/vendor/autoload.php';
require_once __DIR__ . '/programmiInizialiWordLikeUtils.php';

// 1) PARAMETRI POST
$programId = isset($_POST['id']) ? (int) $_POST['id'] : -1;
$doPrint = isset($_POST['print']) && ($_POST['print'] == '1' || $_POST['print'] === 'true');
$titolo = isset($_POST['titolo']) ? $_POST['titolo'] : 'Programma iniziale';

if ($programId==-1)
  exit;

// 2) RECUPERO DATI PROGRAMMA
$query = "SELECT
    pi.id,
    m.id   AS materia_id,
    c.anno AS anno,
    c.classe AS classe,
    m.nome AS materia_nome,
    i.nome AS indirizzo_nome
FROM gvgtcyej_gestione_ore.programmi_iniziali AS pi
INNER JOIN gvgtcyej_gestione_ore.materia AS m
        ON m.id = pi.id_materia
INNER JOIN gvgtcyej_gestione_ore.classi AS c
        ON c.id = pi.id_classe
LEFT  JOIN gvgtcyej_gestione_ore.indirizzo AS i
        ON i.id = CASE
                    WHEN c.id_primo_indirizzo  IS NOT NULL AND c.id_primo_indirizzo  <> 0 THEN c.id_primo_indirizzo
                    ELSE c.id_secondo_indirizzo
                  END
WHERE pi.id = $programId;
";

$program = dbGetFirst($query);

// 3) RECUPERO MODULI

$query = "SELECT * from programmi_iniziali_moduli WHERE id_programma = $programId ORDER BY ordine ASC";

$modules = dbGetAll($query);


$base64img = 'data:image/png;base64,'. base64_encode(dbGetValue("SELECT src FROM immagine WHERE nome = 'intestazione.png'"));

/**
 * Costruisce il markup HTML di una lista non ordinata.
 *
 * @param array $elements
 * @return string
 */
function buildListHtml(array $elements): string
{
  $html = '<ul>';
  foreach ($elements as $li) {
    $html .= '<li>' . htmlspecialchars($li) . '</li>';
  }
  $html .= '</ul>';
  return $html;
}

function isAllUppercase(string $s): bool
{
    $s = trim($s);
    return preg_match('/\p{L}/u', $s) && !preg_match('/\p{Ll}/u', $s);
}


/**
 * Rende una lista UL con al massimo due livelli.
 * Convenzioni:
 *  - Livello 1: riga che inizia con "- " o "* "
 *  - Livello 2: riga indentata (≥2 spazi o \t) prima del trattino, oppure che inizia con "-- " / "** "
 * Se non trova bullet, usa le righe non vuote come voci di primo livello.
 */
function buildTwoLevelListFromText(string $text): string
{
    $lines = preg_split('/\R/u', $text);
    $tree = [];
    $currentParent = null;
    $nextIsChild = false;

    foreach ($lines as $line) {
        $rawLine = rtrim((string)$line);
        if ($rawLine === '') {
            $nextIsChild = false;
            continue;
        }

        $literalDotMap = [];
        $rawLine = preg_replace_callback('/\.{2,}/u', function ($match) use (&$literalDotMap) {
            $token = '__GESTORE_LITERAL_DOTS_' . count($literalDotMap) . '__';
            $literalDotMap[$token] = $match[0];
            return $token;
        }, $rawLine);

        $segments = preg_split('/(?<!\.)\.(?!\.)\s*/u', $rawLine);
        foreach ($segments as $segment) {
            $raw = trim(strtr((string)$segment, $literalDotMap));
            if ($raw === '') {
                continue;
            }

            if (preg_match('/^>>\s*(.+)$/u', $raw, $hm)) {
                $headingText = preg_replace('/[.;:]\s*$/u', '', trim($hm[1]));
                $tree[] = ['type' => 'heading', 'text' => $headingText, 'children' => []];
                $currentParent = null;
                $nextIsChild = false;
                continue;
            }

            if (isAllUppercase($raw)) {
                $tree[] = ['type' => 'heading', 'text' => preg_replace('/[.;:]\s*$/u', '', $raw), 'children' => []];
                $currentParent = null;
                $nextIsChild = false;
                continue;
            }

            $raw = preg_replace('/;\s*$/u', '', $raw);
            $endsWithColon = preg_match('/:\s*$/u', $raw) === 1;
            $norm = str_replace("\t", "  ", $raw);
            $trimmedNorm = preg_replace('/^\s+/u', '', $norm);

            $level = 0;
            $textLi = trim($trimmedNorm);
            if (preg_match('/^(?:--\s+|>\s+|-\s+|\*\s+)(.+)$/u', $trimmedNorm, $m)) {
                $textLi = trim($m[1]);
                $level = $currentParent !== null ? 1 : 0;
            } elseif (preg_match('/^ {2,}(.+)$/u', $norm, $indentMatch)) {
                $textLi = trim($indentMatch[1]);
                $level = $currentParent !== null ? 1 : 0;
            }

            if ($nextIsChild && $level === 0 && $currentParent !== null) {
                $level = 1;
                $nextIsChild = false;
            }

            if ($level === 0) {
                $tree[] = ['text' => $textLi, 'children' => []];
                $currentParent = count($tree) - 1;
            } else {
                if ($currentParent === null) {
                    $tree[] = ['text' => '', 'children' => []];
                    $currentParent = count($tree) - 1;
                }
                $tree[$currentParent]['children'][] = ['text' => $textLi, 'children' => []];
            }

            if ($endsWithColon) {
                $nextIsChild = true;
            }
        }
    }

    return renderTwoLevelList($tree);
}

function buildTwoLevelListFromTextLegacy(string $text): string
{
    $lines = preg_split('/\R/u', $text);
    $tree = [];
    $currentParent = null;

    // Se true: il PROSSIMO elemento (uno solo) va come child del currentParent
    $nextIsChild = false;

    foreach ($lines as $line) {
        $rawLine = rtrim($line);
        if ($rawLine === '') {
            $nextIsChild = false;
            continue;
        }

        $literalDotMap = [];
        $rawLine = preg_replace_callback('/\.{2,}/u', function ($match) use (&$literalDotMap) {
            $token = '__GESTORE_LITERAL_DOTS_' . count($literalDotMap) . '__';
            $literalDotMap[$token] = $match[0];
            return $token;
        }, $rawLine);

        // Solo il punto singolo separa due voci; "..", "...", "...." restano punti veri.
        $segments = preg_split('/(?<!\.)\.(?!\.)\s*/u', $rawLine);

        foreach ($segments as $segment) {
            $raw = trim(strtr($segment, $literalDotMap));
            if ($raw === '') continue;

            // HEADING ESPLICITO con prefisso ">>" (top-level, no bullet, grassetto)
            if (preg_match('/^>>\s*(.+)$/u', $raw, $hm)) {
                $headingText = trim($hm[1]);

                // togli eventuali ., ; o : finali (come per le righe maiuscole)
                $headingText = preg_replace('/[.;:]\s*$/u', '', $headingText);

                $tree[] = ['type' => 'heading', 'text' => $headingText, 'children' => []];
                $currentParent = null;
                $nextIsChild = false;
                continue;
            }

            // Se è tutta maiuscola -> heading (no bullet)
            if (isAllUppercase($raw)) {
                // 👇 AGGIUNGI QUESTE DUE RIGHE
                $raw = preg_replace('/[.;:]\s*$/u', '', $raw);

                $tree[] = ['type' => 'heading', 'text' => $raw, 'children' => []];
                $currentParent = null;
                continue;
            }


            // Togli ';' solo se è alla fine
            $raw = preg_replace('/;\s*$/u', '', $raw);

            // Finisce con ':' ?
            $endsWithColon = preg_match('/:\s*$/u', $raw) === 1;

            // Normalizza tab
            $norm = str_replace("\t", "  ", $raw);

            $isBullet = preg_match('/^(?:( {2,}))?([\-*‐-‒–—−]{1,})\s*(.+)$/u', $norm, $m) === 1;

            if ($isBullet) {
                $indent  = $m[1] ?? '';
                $markers = $m[2];
                $textLi  = trim($m[3]);

                // livello naturale
                $level = 0;
                if ($indent !== '' || strlen($markers) >= 2) {
                    $level = 1;
                }

                // Se il prossimo deve essere child e sarebbe top-level -> forza child
                if ($nextIsChild && $level === 0 && $currentParent !== null) {
                    $level = 1;
                    $nextIsChild = false; // ✅ consuma la modalità
                }

                if ($level === 0) {
                    $tree[] = ['text' => $textLi, 'children' => []];
                    $currentParent = count($tree) - 1;
                } else {
                    if ($currentParent === null) {
                        $tree[] = ['text' => '', 'children' => []];
                        $currentParent = count($tree) - 1;
                    }
                    $tree[$currentParent]['children'][] = ['text' => $textLi, 'children' => []];
                }
            } else {
                // NON è bullet
                if ($nextIsChild && $currentParent !== null) {
                    $tree[$currentParent]['children'][] = ['text' => $raw, 'children' => []];
                    $nextIsChild = false; // ✅ consuma la modalità
                } else {
                    $tree[] = ['text' => $raw, 'children' => []];
                    $currentParent = count($tree) - 1;
                }
            }

            // Se questa riga finisce con ':' allora SOLO il prossimo elemento sarà child
            if ($endsWithColon) {
                $nextIsChild = true;
            }
        }
    }

    return renderTwoLevelList($tree);
}

/** Renderer ricorsivo per massimo due livelli */
/** Renderer per massimo due livelli + headings fuori lista */
function renderTwoLevelList(array $nodes): string
{
    if (empty($nodes)) return '';

    $html = '';
    $ulOpen = false;

    foreach ($nodes as $n) {
        $type = $n['type'] ?? 'item';

        // Heading: fuori dalla lista
        if ($type === 'heading') {
            if ($ulOpen) {
                $html .= '</ul>';
                $ulOpen = false;
            }
            $html .= '<p><strong>' . htmlspecialchars($n['text'] ?? '', ENT_QUOTES, 'UTF-8') . '</strong></p>';
            continue;
        }

        // Item normale: dentro la lista
        if (!$ulOpen) {
            $html .= '<ul>';
            $ulOpen = true;
        }

        $html .= '<li>' . htmlspecialchars($n['text'] ?? '', ENT_QUOTES, 'UTF-8');

        if (!empty($n['children'])) {
            $html .= '<ul>';
            foreach ($n['children'] as $c) {
                $html .= '<li>' . htmlspecialchars($c['text'] ?? '', ENT_QUOTES, 'UTF-8') . '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '</li>';
    }

    if ($ulOpen) $html .= '</ul>';

    return $html;
}

function programmaInizialeLooksLikeHtml(string $text): bool
{
    return preg_match('/<\/?(p|div|ul|ol|li|h[1-6]|strong|b|em|i|u|blockquote|span)\b/i', $text) === 1;
}

function sanitizeProgrammaInizialeRichHtml(string $html): string
{
    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $html = str_replace("\xc2\xa0", ' ', $html);
    $html = preg_replace('/&(nbsp|amp;nbsp);/i', ' ', $html);
    $html = str_replace(['__MODULE_TITLE__', '__SECTION_HEADING__'], '', $html);
    $html = preg_replace('/<\s*(script|style|meta|link|object|iframe)[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $html);
    $html = preg_replace('/\s+on[a-z]+\s*=\s*(["\']).*?\1/is', '', $html);
    $html = preg_replace('/\s+(class|id|style)\s*=\s*(["\']).*?\2/is', '', $html);
    $html = preg_replace('/<\s*\/?\s*(font)\b[^>]*>/i', '', $html);
    $html = preg_replace('/<\s*b\b[^>]*>/i', '<strong>', $html);
    $html = preg_replace('/<\s*\/\s*b\s*>/i', '</strong>', $html);
    $html = preg_replace('/<\s*i\b[^>]*>/i', '<em>', $html);
    $html = preg_replace('/<\s*\/\s*i\s*>/i', '</em>', $html);
    $html = preg_replace('/<\s*h[1-6]\b[^>]*>/i', '<h4>', $html);
    $html = preg_replace('/<\s*\/\s*h[1-6]\s*>/i', '</h4>', $html);
    $html = strip_tags($html, '<p><div><br><ul><ol><li><strong><em><u><h4><blockquote><span>');
    return trim($html);
}

function renderProgrammaInizialeRichHtml(string $html, bool $forPdf = false): string
{
    $html = sanitizeProgrammaInizialeRichHtml($html);
    if ($html === '') {
        return '';
    }

    $html = preg_replace('/<\s*div\b[^>]*>/i', '<p>', $html);
    $html = preg_replace('/<\s*\/\s*div\s*>/i', '</p>', $html);

    if ($forPdf) {
        $html = preg_replace('/<p>/i', '<p style="margin:0 0 3px 0;line-height:1.2;">', $html);
        $html = preg_replace('/<h4>/i', '<h4 style="margin:2px 0 2px 0;font-size:11px;line-height:1.12;font-weight:bold;color:#173f68;">', $html);
        $html = preg_replace('/<ul>/i', '<ul style="margin:0 0 3px 16px;padding-left:12px;line-height:1.2;">', $html);
        $html = preg_replace('/<ol>/i', '<ol style="margin:0 0 3px 16px;padding-left:12px;line-height:1.2;">', $html);
    }

    return $html;
}

function renderProgrammaInizialeText(string $text, bool $forPdf = false): string
{
    return renderProgrammaInizialeRichHtml(programmaInizialeWordLikeEnsureHtml($text), $forPdf);
}

// 5) INIZIO OUTPUT HTML IN BUFFER
ob_start();
?><!DOCTYPE html>
<html lang="it">

<head>
  <meta charset="UTF-8">
  <title><?php echo $titolo ?></title>
  <style>
    @page {
      size: A4 portrait;
      margin: 5mm;
    }

    body {
      margin: 0;
      padding: 0;
      font-family: DejaVu Sans, sans-serif;
      background: transparent;
      color: #2c3e50;
    }

  .print-button {
    position: fixed;    /* rispetto al viewport */
    top: 20px;          /* 20px dal bordo superiore */
    left: 20px;         /* 20px dal bordo sinistro */
    z-index: 9999;      /* sopra a tutto (anche all’embed/pdf) */
    background: #FFA500;   /* sfondo bianco per staccarsi dal pdf */
    padding: 6px 12px;
    border-radius: 4px;
    font-weight: 900;
    font-style: italic;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
  }
    /* logo centrato in alto solo sulla prima pagina */
    .first-logo {
      text-align: center;
      margin: 10mm 0 5mm;
    }

    .first-logo img {
      width: auto;
      /* almeno metà larghezza pagina */
      height: 100px;
      display: inline-block;
    }

    /* HEADER PRINCIPALE */
    .header {
      display: flex;
      align-items: center;
      background: linear-gradient(90deg, #0057b7, #3a8dd5);
      padding: 12px 20px;
      border-radius: 6px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
      margin-bottom: 15px;
    }

    .header .logo {
      width: 50px;
      height: 50px;
      margin-right: 15px;
      object-fit: contain;
    }

    .header .info {
      flex: 1;
      text-align: center;
      color: #000;
    }

    .header .info h1 {
      margin: 0;
      font-size: 30px;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .header .info p {
      margin: 4px 0 0;
      font-size: 20px;
      letter-spacing: 0.5px;
    }


    /* container dei moduli */
    .module-card {
      margin: 0 4mm 15px;
      page-break-inside: auto;
    }

    /* tabella dei moduli: niente border-radius, niente overflow */
    .module {
      width: 100%;
      border: 2px solid #0057b7;
      border-collapse: collapse;
      margin: 0;
    }

    table {
      page-break-before: avoid;
    }

    thead {
      display: table-header-group !important;
    }

    tbody {
      display: table-row-group !important;
    }

    /* THEAD deve avere questo display per essere ripetuto */
    .module thead {
      display: table-header-group;
    }

    /* TBODY deve avere questo display per continuare sotto il THEAD ripetuto */
    .module tbody {
      display: table-row-group;
    }

    /* tabella dei moduli compatibile TCPDF */
    table.module {
      width: 100%;
      border: 2px solid #0057b7;
      border-collapse: collapse;
      margin-bottom: 6mm;
    }

    thead.module-header th {
      background-color: #0057b7 !important;
      color: #ffffff !important;
      font-size: 16px;
      padding: 8px;
      text-align: left;
    }

    .module th,
    .module td {
      border: 1px solid #0057b7;
      padding: 6px 8px;
      vertical-align: top;
    }

    .module th {
      width: 25%;
      background-color: #d9eefa;
      color: #2c3e50;
    }

    .module td {
      background-color: #f7fbfe;
    }

    .module td h4 {
      margin: 6px 0 4px;
      font-size: 13px;
      line-height: 1.2;
      font-weight: 800;
      text-transform: uppercase;
      color: #173f68;
    }

    .module td p {
      margin: 0 0 4px;
      line-height: 1.3;
    }

    .module td ul,
    .module td ol {
      margin: 0 0 4px 20px;
      padding-left: 16px;
    }
  </style>
  <link rel="icon" href="../ore-32.png" />
</head>

<body>

  <?php if (!$doPrint): ?>
    <div class="print-button">
      <form method="post" action="">
        <input type="hidden" name="id" value="<?= $programId ?>">
        <input type="hidden" name="print" value="1">
        <button type="submit" style="font-family: Arial, sans-serif; font-size: 16px; font-weight: bold;">Scarica PDF</button>
      </form>
    </div>
  <?php endif; ?>

  <!-- logo grande e centrato, solo in cima alla prima pagina -->
  <div class="first-logo">
    <img src="<?php echo $base64img ?>" alt="Logo Buonarroti" style="height:80px; width:auto;">
  </div>

  <div class="header">
    <div class="info">
      <h1><?php echo $titolo ?></h1>
      <p>Classe <?= htmlspecialchars($program['classe']) ?>° | 
        Indirizzo <?= htmlspecialchars($program['indirizzo_nome']) ?><br>
        Materia <?= htmlspecialchars($program['materia_nome']) ?>| 
        Anno scolastico <?= $__anno_scolastico_corrente_anno ?></p>
    </div>
  </div>

  <?php foreach ($modules as $m): ?>
    <div class="module-card">
      <table width="100%" style="
    width:100%;               
    border-collapse:collapse;
    margin-bottom:6mm;
  " border="0" cellpadding="0" cellspacing="0">
        <thead>
          <tr>
            <th colspan="2" style="
                background-color: #0057b7;
                color:            #ffffff;
                font-size:        16px;
                padding:          8px;
                text-align:       left;
                border:           2px solid #0057b7;
              ">
              Modulo <?= (int) $m['ordine'] ?>:
              <?= htmlspecialchars($m['nome']) ?>
            </th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ([
            'Conoscenze' => renderProgrammaInizialeText($m['conoscenze']),
            'Abilità' => renderProgrammaInizialeText($m['abilita']),
            'Competenze' => renderProgrammaInizialeText($m['competenze']),
            'Periodo' => renderProgrammaInizialeText($m['periodo']),
          ] as $th => $td): ?>
            <tr>
              <td width="25%" style="
                width:            25%;
                background-color: #d9eefa;
                color:            #2c3e50;
                border:           1px solid #0057b7;
                padding:          6px 8px;
                vertical-align:   top;
              ">
                <?= $th ?>
              </td>
              <td width="75%" style="
                background-color: #f7fbfe;
                border:           1px solid #0057b7;
                padding:          6px 8px;
                vertical-align:   top;
              ">
                <?= $td ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endforeach; ?>

</body>

</html>
<?php

use TCPDF;

class MyPDF extends TCPDF
{
  /** Testo fisso che vuoi nel footer */
  public $footerText = 'Documento ufficiale – Segreteria Didattica - generato il ';

  // Questo viene chiamato automaticamente a fine pagina
  public function Footer()
  {
    // posizionati 10mm dal fondo
    $this->SetY(-10);

    // linea orizzontale sottile
    $this->SetDrawColor(200, 200, 200);
    $this->Line(8, $this->GetY(), $this->getPageWidth() - 8, $this->GetY());
    $this->Ln(2);

    // font e colore
    $this->SetFont('dejavusans', 'I', 8);
    $this->SetTextColor(100, 100, 100);

    // pagina corrente
    $current = $this->PageNo();

    // se esiste il placeholder totale, leggilo; altrimenti stringa vuota
    $total = method_exists($this, 'getAliasNbPages')
      ? $this->getAliasNbPages()
      : '';
    // data corrente (puoi cambiare formato: d/m/Y, j F Y, ecc.)
    $today = date('d/m/Y');

    // componi la riga: se hai il totale lo metti, altrimenti solo 'Pag. X'
    $line = $this->footerText
      . $today .
      ' – Pag. '
      . $current
      . ($total ? '/' . $total : '');

    // stampalo centrato
    $this->Cell(0, 4, $line, 0, 0, 'C');
  }
}

// 7) OTTENGO HTML COMPLETO
$html = ob_get_clean();
// … dopo ob_get_clean() e la preview HTML …
if ($doPrint) {
  // 1) autoloader e setup TCPDF
  require_once __DIR__ . '/../common/vendor/autoload.php';

  // istanzia la tua classe
  $pdf = new MyPDF('P', 'mm', 'A4', true, 'UTF-8', false);

  // disabilita header di default, abilita footer custom
  $pdf->setPrintHeader(false);
  $pdf->setPrintFooter(true);

  // margini e page break
  $pdf->SetMargins(8, 10, 8, 8);
  $pdf->SetAutoPageBreak(true, 15);

  // se la tua TCPDF supporta aliasNbPages(), registralo (opzionale)
  if (method_exists($pdf, 'AliasNbPages')) {
    $pdf->AliasNbPages();
  }

  // 2) Configura colori e font del footer
//    setFooterData(textColorRGB, lineColorRGB)
  $pdf->setFooterData(
    [100, 100, 100],   // colore testo (RGB)
    [200, 200, 200]    // colore linea orizzontale
  );

  // 3) Imposta il font del footer: famiglia, stile, dimensione
  $pdf->setFooterFont(['dejavusans', 'I', 8]);

  // 4) Distanza del footer dal bordo inferiore
  $pdf->SetFooterMargin(10);

  $pdf->AddPage();
  $pdf->SetFont('dejavusans', '', 10);

  // 1) LOGO in cima

  $htmlIntro = '
<div style="text-align:center;margin:0px">
  <img
    src="' . $base64img . '"
    style="height:50px;width:auto"
  />
</div>';

  // 2) TESTO INTESTAZIONE
  $htmlIntro .= '
<h1 style="
    font-family:dejavusans;
    font-size:24px;
    text-align:center;
    margin:0 0 0mm;
">' . $titolo . '</h1>
<p style="text-align:center;margin:0px;font-size:12px">
  Classe ' . htmlspecialchars($program['anno']) . ' | 
  Indirizzo ' . htmlspecialchars($program['indirizzo_nome']) . '<br>
  Materia ' . htmlspecialchars($program['materia_nome']) . ' | 
  Anno scolastico ' . $__anno_scolastico_corrente_anno . '</p><br>';

  // scrivo logo+intestazione
  $pdf->writeHTML($htmlIntro, true, false, true, false, '');
  // 2) ciclo sui moduli
  foreach ($modules as $m) {
    // costruisci l’HTML della tabella, stile INLINE per colori e bordi
    $tbl = '<table width="100%" border="0" cellpadding="0" cellspacing="0">';
    $tbl .= '<thead>';
    $tbl .= '  <tr>';
    $tbl .= '    <th colspan="2" style="
                          background-color:#0057b7;
                          color:#ffffff;
                          font-size:16px;
                          padding:8px;
                          text-align:left;
                          border:2px solid #0057b7;">
            Modulo ' . ((int) $m['ordine']) . ': ' . htmlspecialchars($m['nome']) . '
          </th>';
    $tbl .= '  </tr>';
    $tbl .= '</thead><tbody>';

    // quattro righe fisse
    $rows = [
      'Conoscenze' => renderProgrammaInizialeText($m['conoscenze'], true),
      'Abilità' => renderProgrammaInizialeText($m['abilita'], true),
      'Competenze' => renderProgrammaInizialeText($m['competenze'], true),
      'Periodo' => renderProgrammaInizialeText($m['periodo'], true),
    ];
    foreach ($rows as $label => $data) {
      $tbl .= '<tr>';
      $tbl .= '<td width="25%" style="
                          background-color:#d9eefa;
                          border:1px solid #0057b7;
                          padding:6px 8px;
                          vertical-align:top;">
                        ' . $label . '
                     </td>';
      $tbl .= '<td width="75%" style="
                          background-color:#f7fbfe;
                          border:1px solid #0057b7;
                          padding:6px 8px;
                          vertical-align:top;">
                        ' . $data . '
                     </td>';
      $tbl .= '</tr>';
    }

    $tbl .= '</tbody></table>';
    // un piccolo spazio fra una tabella e l’altra
    $tbl .= '<div style="height:4mm"></div>';

    // 3) scrivo la tabella
    $pdf->writeHTML($tbl, true, false, true, false, '');
  }

  // 4) output
  $pdf->Output($titolo . ' ' . $program['materia_nome'] . '  - Classe ' . $program['anno'] . '° - Indirizzo ' . $program['indirizzo_nome'] . '.pdf', 'D');
  exit;
}

// 7) Altrimenti mostra la preview HTML
echo $html;


?>
