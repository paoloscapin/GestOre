<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

$pagina = '';

require_once '../common/checkSession.php';
ruoloRichiesto('docente', 'dirigente', 'segreteria-didattica');
// program.php (in testa al file, prima di qualsiasi uso di mPDF)
require_once '../common/vendor/autoload.php';

// 1) PARAMETRI POST
$programId = isset($_POST['id']) ? (int) $_POST['id'] : -1;
$doPrint = isset($_POST['print']) && ($_POST['print'] == '1' || $_POST['print'] === 'true');
$titolo = isset($_POST['titolo']) ? $_POST['titolo'] : 'Programma didattico';

if ($programId==-1)
  exit;

// 2) RECUPERO DATI PROGRAMMA
$query = "SELECT  programmi_svolti.id,
		programmi_svolti.id_materia AS svolti_id_materia,
    programmi_svolti.id_docente AS svolti_id_docente,
		programmi_svolti.id_classe AS svolti_id_classe,
		materia.id AS materia_id,
    materia.nome AS materia_nome,
    docente.cognome AS doc_cognome,
    docente.nome AS doc_nome,
    docente.id AS doc_id,
    classi.id AS classe_id,
    classi.classe AS classe_nome,
    classi.anno AS classe_anno,
    classi.id_primo_indirizzo AS classe_id_indirizzo,
    indirizzo.nome AS indirizzo_nome
 FROM gvgtcyej_gestione_ore.programmi_svolti
		INNER JOIN gvgtcyej_gestione_ore.materia materia
		ON materia.id = programmi_svolti.id_materia
		INNER JOIN gvgtcyej_gestione_ore.classi classi
		ON classi.id = programmi_svolti.id_classe
		INNER JOIN gvgtcyej_gestione_ore.docente docente
		ON docente.id = programmi_svolti.id_docente
		INNER JOIN gvgtcyej_gestione_ore.indirizzo indirizzo
		ON indirizzo.id = classi.id_primo_indirizzo
		WHERE programmi_svolti.id = $programId";

$program = dbGetFirst($query);

// 3) RECUPERO MODULI

$query = "SELECT * from programmi_svolti_moduli WHERE id_programma = $programId ORDER BY programmi_svolti_moduli.ordine ASC";

$modules = dbGetAll($query);


$base64img = 'data:image/png;base64,'. base64_encode(dbGetValue("SELECT src FROM immagine WHERE nome = 'intestazione.png'"));

/**
 * Converte una stringa in un JSON-array.
 * - Spezza su punto (.), tab (\t) o newline.
 * - Raggruppa righe che iniziano con “-” o “*” in un unico elemento HTML <ul><li>…</li></ul>.
 *
 * @param string $text
 * @return string JSON array
 */
function textToJsonArray(string $text): string
{
  $lines = preg_split('/\r?\n/', $text);
  $items = [];

  foreach ($lines as $line) {
    $trimmed = trim($line);
    if ($trimmed === '') continue;

    // Se è riga con elenco puntato
    if (preg_match('/^[\-\*]\s*(.+)$/', $trimmed, $m)) {
      $items[] = $m[1];
    } else {
      // Altrimenti, spezza su punti o tab
      $parts = preg_split('/\.\s*|\t+/', $trimmed);
      foreach ($parts as $part) {
        $p = trim($part);
        if ($p !== '') {
          $items[] = $p;
        }
      }
    }
  }

  return json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

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
// 4) HELPER PER LISTE (campi JSON → UL)
function asList(string $text): string
{
  $items = json_decode(textToJsonArray($text), true) ?: [];
  $html = '<ul>';
  foreach ($items as $item) {
    $html .= '<li>' . htmlspecialchars($item) . '</li>';
  }
  $html .= '</ul>';
  return $html;
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
      vertical-align: middle;
      text-align: center;
    }

    .module th {
      width: 25%;
      background-color: #d9eefa;
      color: #2c3e50;
    }

    .module td {
      background-color: #f7fbfe;
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
        <input type="hidden" name="titolo" value="<?php echo $titolo ?>">
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
      <p>Classe <?= htmlspecialchars($program['classe_nome']) ?> | 
        Indirizzo <?= htmlspecialchars($program['indirizzo_nome']) ?><br>
        Materia <?= htmlspecialchars($program['materia_nome']) ?> | 
        Docente <?= htmlspecialchars($program['doc_cognome'].' '.$program['doc_nome']) ?> | 
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
              Modulo <?= (int) $m['ORDINE'] ?>:
              <?= htmlspecialchars($m['NOME']) ?>
            </th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ([
            'Conoscenze degli argomenti svolti' => asList($m['CONTENUTO'])
            ] as $th => $td): ?>
            <tr>
              <td width="25%" style="
                width:            25%;
                background-color: #d9eefa;
                color:            #2c3e50;
                border:           1px solid #0057b7;
                padding:          6px 8px;
                vertical-align:   middle;
                text-align: center;
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
  Classe ' . htmlspecialchars($program['classe_nome']) . ' | 
  Indirizzo ' . htmlspecialchars($program['indirizzo_nome']) . '<br>
  Materia ' . htmlspecialchars($program['materia_nome']) . ' | 
  Docente ' . htmlspecialchars($program['doc_cognome'].' '.$program['doc_nome']) . ' |
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
            Modulo ' . ((int) $m['ORDINE']) . ': ' . htmlspecialchars($m['NOME']) . '
          </th>';
    $tbl .= '  </tr>';
    $tbl .= '</thead><tbody>';

    // quattro righe fisse
    $rows = [
      'Conoscenze degli argomenti svolti' => asList($m['CONTENUTO'])
    ];
    foreach ($rows as $label => $data) {
      $tbl .= '<tr>';
      $tbl .= '<td width="25%"  valign="middle" style="
                          background-color:#d9eefa;
                          border:1px solid #0057b7;
                          padding:6px 8px;
                          vertical-align: middle;
                          text-align: center;">
                        ' . $label . '
                     </td>';
      $tbl .= '<td width="75%" style="
                          background-color:#f7fbfe;
                          border:1px solid #0057b7;
                          padding:6px 8px;
                          vertical-align:middle;">
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
  $pdf->Output($titolo . ' ' . $program['materia_nome'] . '  - Classe ' . $program['classe_nome'] . ' - Indirizzo ' . $program['indirizzo_nome'] . ' - Docente ' . $program['doc_cognome'] . ' ' . $program['doc_nome'] . '.pdf', 'D');
  exit;
}

// 7) Altrimenti mostra la preview HTML
echo $html;


?>