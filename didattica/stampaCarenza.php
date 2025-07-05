<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

$pagina = '';

require_once '../common/checkSession.php';
ruoloRichiesto('studente', 'docente', 'dirigente', 'segreteria-docenti');
// program.php (in testa al file, prima di qualsiasi uso di mPDF)
require_once '../common/vendor/autoload.php';
require_once '../common/send-mail.php';
// 1) PARAMETRI POST
$carenzaId = isset($_POST['id']) ? (int) $_POST['id'] : -1;
$doView  = isset($_POST['view']) && ($_POST['view'] == '1' || $_POST['view'] === 'true');
$doPrint = isset($_POST['print']) && ($_POST['print'] == '1' || $_POST['print'] === 'true');
$doMail = isset($_POST['mail']) && ($_POST['mail'] == '1' || $_POST['mail'] === 'true');
$titolo = isset($_POST['titolo']) ? $_POST['titolo'] : 'Programma didattico';
$doGenera = isset($_POST['genera']) && ($_POST['genera'] == '1' || $_POST['genera'] === 'true');


// 2) RECUPERO DATI PROGRAMMA
$query = "SELECT  
        carenze.id AS carenza_id,
        carenze.id_studente AS stud_id,
        carenze.id_materia AS materia_id,
        carenze.id_classe AS classe_id,
        carenze.id_docente AS doc_id,
        carenze.stato AS stato,
        carenze.nota_docente AS nota,
        classi.id AS classi_id,
        classi.classe AS classe_nome,
        classi.anno AS classe_anno,
        classi.id_primo_indirizzo AS classe_primo,
        classi.id_secondo_indirizzo AS classe_secondo,
        indirizzo.nome AS ind_nome,
        materia.id AS mat_id,
        materia.nome AS materia_nome,
        studente.id AS studente_id,
        studente.cognome AS stud_cognome,
        studente.nome AS stud_nome, 
        studente.email AS stud_email,
        docente.id AS docente_id,
        docente.cognome AS doc_cognome,
        docente.nome AS doc_nome,
        programma_minimi.ID AS prog_id,
        programma_minimi.ID_INDIRIZZO as prog_id_indirizzo,
        programma_minimi.ID_MATERIA as prog_id_materia,
        programma_minimi.anno AS prog_anno
 FROM gvgtcyej_gestione_ore.carenze
		INNER JOIN gvgtcyej_gestione_ore.classi classi
		ON classi.id = carenze.id_classe
		INNER JOIN gvgtcyej_gestione_ore.materia materia
		ON materia.id = carenze.id_materia
		INNER JOIN gvgtcyej_gestione_ore.studente studente
		ON studente.id = carenze.id_studente
		INNER JOIN gvgtcyej_gestione_ore.docente docente
		ON docente.id = carenze.id_docente
        INNER JOIN gvgtcyej_gestione_ore.programma_minimi programma_minimi
        ON programma_minimi.ANNO = classi.anno AND programma_minimi.ID_MATERIA = materia.id AND (programma_minimi.ID_INDIRIZZO = classi.id_primo_indirizzo OR programma_minimi.ID_INDIRIZZO = classi.id_secondo_indirizzo)
		INNER JOIN gvgtcyej_gestione_ore.indirizzo indirizzo
		ON indirizzo.id = classi.id_primo_indirizzo
		WHERE carenze.id = $carenzaId";

$program = dbGetFirst($query);

// se devo inviare solo la mail non mi serve rigenerare la pagina

//RECUPERO MODULI
$id_programma_minimi = $program['prog_id'];
$query = "SELECT * from programma_minimi_moduli WHERE id_programma = $id_programma_minimi";
$modules = dbGetAll($query);
$studente_id = $program['studente_id'];
$nota_docente = $program['nota'];
$carenza_id = $program['carenza_id'];

$base64img = 'data:image/png;base64,' . base64_encode(dbGetValue("SELECT src FROM immagine WHERE nome = 'intestazione.png'"));

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
  // 1) Dividi in righe
  $lines = preg_split('/\r?\n/', $text);
  $items = [];
  $currentList = [];

  foreach ($lines as $line) {
    $trimmed = trim($line);
    if ($trimmed === '') {
      // riga vuota: chiudi eventuale lista
      if ($currentList) {
        $items[] = buildListHtml($currentList);
        $currentList = [];
      }
      continue;
    }

    if (preg_match('/^[\-\*]\s*(.+)$/', $trimmed, $m)) {
      // riga a list bullet
      $currentList[] = trim($m[1]);
    } else {
      // non è bullet: chiudi lista se aperta
      if ($currentList) {
        $items[] = buildListHtml($currentList);
        $currentList = [];
      }
      // spezza la riga su punto o tab
      $parts = preg_split('/\.\s*|\t+/', $trimmed);
      foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '') {
          $items[] = $p;
        }
      }
    }
  }
  // chiudi lista rimanente
  if ($currentList) {
    $items[] = buildListHtml($currentList);
  }

  return json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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
  // 1) Decodifica l’array JSON
  $items = json_decode(textToJsonArray($text), true) ?: [];

  $html = '<ul>';
  foreach ($items as $item) {
    $trim = ltrim($item);
    // 2) Se è già un blocco <ul> trattalo come sotto‐lista:
    if (str_starts_with($trim, '<ul')) {
      // Rimuovo la chiusura </li> dell'ultimo elemento
      // e ci incollo dentro la <ul> ... </ul> e poi riaggiungo </li>
      $html = preg_replace(
        '/<\/li>$/',
        $trim . '</li>',
        $html
      );
    } else {
      // 3) Altrimenti apro un nuovo <li>
      $html .= '<li>' . $item . '</li>';
    }
  }
  $html .= '</ul>';
  return $html;
}

// INIZIO OUTPUT HTML IN BUFFER
ob_start();
?>
<!DOCTYPE html>
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
      position: fixed;
      /* rispetto al viewport */
      top: 20px;
      /* 20px dal bordo superiore */
      left: 20px;
      /* 20px dal bordo sinistro */
      z-index: 9999;
      /* sopra a tutto (anche all’embed/pdf) */
      background: #FFA500;
      /* sfondo bianco per staccarsi dal pdf */
      padding: 6px 12px;
      border-radius: 4px;
      font-weight: 900;
      font-style: italic;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
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
  </style>
  <link rel="icon" href="../ore-32.png" />
</head>

<body>

<!-- // rendo visibile il pulsante scarica PDF -->

<?php if ($doView): ?>
    <div class="print-button">
      <form method="post" action="">
        <input type="hidden" name="id" value="<?= $carenzaId ?>">
        <input type="hidden" name="print" value="1">
        <input type="hidden" name="titolo" value="Programma carenza formativa">
        <input type="hidden" name="DoMail" value="0">
        <input type="hidden" name="DoGenera" value="0">
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
      <p>Studente <?= htmlspecialchars($program['stud_cognome'] . ' ' . $program['stud_nome']) ?> | 
        Classe <?= htmlspecialchars($program['classe_nome']) ?> | 
        Indirizzo <?= htmlspecialchars($program['ind_nome']) ?><br>
        Materia <?= htmlspecialchars($program['materia_nome']) ?><br>
        Docente <?= htmlspecialchars($program['doc_cognome'] . ' ' . $program['doc_nome']) ?> | 
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
          <?php foreach (
            [
              'Conoscenze' => asList($m['CONOSCENZE']),
              'Abilità' => asList($m['ABILITA'])
            ] as $th => $td
          ): ?>
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

  <!-- stampo la nota del docente -->
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
            Note del docente
          </th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (
          [
            'Note' => asList($nota_docente)
          ] as $th => $td
        ): ?>
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
</body>

</html>
<?php

// use TCPDF;

class MyPDF extends \TCPDF
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

// se devo solo visualizzare la pagina , la stampo ed esco
if ($doView)
{
  echo $html;
  exit;
}

if ( $doPrint ||  $doGenera ) 
{
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
  Studente ' . htmlspecialchars($program['stud_cognome'] . ' ' . $program['stud_nome']) . ' | 
  Classe ' . htmlspecialchars($program['classe_nome']) . ' | 
  Indirizzo ' . htmlspecialchars($program['ind_nome']) . '<br>
  Materia ' . htmlspecialchars($program['materia_nome']) . '<br>
  Docente ' . htmlspecialchars($program['doc_cognome'] . ' ' . $program['doc_nome']) . ' | 
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
      'Conoscenze' => asList($m['CONOSCENZE']),
      'Abilità' => asList($m['ABILITA']),
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

  // CAMPO NOTA DEL DOCENTE
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
            Note del docente
          </th>';
  $tbl .= '  </tr>';
  $tbl .= '</thead><tbody>';

  // quattro righe fisse
  $rows = [
    'Note' => asList($nota_docente)
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
  ob_end_clean();
  }
    // 4) output
  if ($doPrint)
  {
    $pdf->Output($titolo . ' ' . $program['stud_cognome'] . ' ' . $program['stud_nome'] . ' ' . $program['materia_nome'] . '  - Classe ' . $program['classe_nome'] . '° - Indirizzo ' . $program['ind_nome'] . '° - Docente ' . $program['doc_cognome'] . ' ' . $program['doc_nome'] . '.pdf', 'D');
    exit;
  }
  if ($doGenera) {
    $token = bin2hex(random_bytes(16)); // link anonimo, sicuro
    $randomFileName = bin2hex(random_bytes(12)) . '.pdf';
    $filename = __DIR__ . '/tmp/' . $randomFileName;
    $filePath = 'tmp/' . $randomFileName;
    $pdf->Output($filename, 'F'); // salva il file
    $created_at = date('Y-m-d H:i:s');
    $expires_at = date('Y-m-d H:i:s', strtotime('+3 months'));
    $query = "SELECT COUNT(*) FROM carenze_downloads WHERE student_id='" . $studente_id . "' AND file_path='" . $filePath . "'";
    $esiste = dbGetValue($query);
 

    // salva nel DB
    if ($esiste == 0) {
      $query = "INSERT INTO carenze_downloads (student_id, carenza_id, file_path, download_token,created_at,expires_at) VALUES ('$studente_id', '$carenza_id', '$filePath', '$token','$created_at','$expires_at')";
    } else {
      $query = "UPDATE carenze_downloads SET download_token = '$token', file_path = '$filePath', created_at = '$created_at', expires_at = '$expires_at', download_count = 0, last_ip='' WHERE student_id = '$studente_id' AND carenza_id = '$carenza_id'";
    }
    dbExec($query);

    date_default_timezone_set("Europe/Rome");
    $update = date("Y-m-d H-i-s");
    $query = "UPDATE carenze SET stato = '2' WHERE id = '" . $program['carenza_id'] . "'";
    dbExec($query);
    info("generato PDF carenza id=" . $program['carenza_id']);
    if ($esiste=0)
    { 
      echo 'generato';
    } 
    else 
    {
      echo 'aggiornato';
    }
    exit;
  }

if ($doMail) {
   
    $query = "SELECT * FROM carenze_downloads WHERE student_id='" . $studente_id . "' AND carenza_id='" . $carenza_id . "'";

    $esiste = dbGetFirst($query);
    if ($esiste == null)
    {
      echo ' File carenza non ancora generato';
      exit;
    }

    $download_token = $esiste['download_token'];
    $studente_cognome = $program['stud_cognome'];
    $studente_nome = $program['stud_nome'];
    $studente_email = $program['stud_email'];
    $docente_cognome = $program['doc_cognome'];
    $docente_nome = $program['doc_nome'];

    $full_mail_body = file_get_contents("../didattica/template_mail_carenza.html");

    $full_mail_body = str_replace("{titolo}", "CARENZA FORMATIVA", $full_mail_body);
    $full_mail_body = str_replace("{nome}", strtoupper($studente_cognome) . " " . strtoupper($studente_nome), $full_mail_body);
    $full_mail_body = str_replace("{messaggio}", "hai ricevuto questa mail perchè hai riportato la carenza formativa a fine anno secondo quanto qui riportato:", $full_mail_body);
    $full_mail_body = str_replace("{classe}", $program['classe_nome'], $full_mail_body);
    $full_mail_body = str_replace("{indirizzo}", $program['ind_nome'], $full_mail_body);
    $full_mail_body = str_replace("{docente}", strtoupper($docente_cognome . " " .  $docente_nome), $full_mail_body);
    $full_mail_body = str_replace("{materia}", $program['materia_nome'], $full_mail_body);
    $full_mail_body = str_replace("{nota}", $nota_docente, $full_mail_body);
    $full_mail_body = str_replace("{nome_istituto}", $__settings->local->nomeIstituto, $full_mail_body);

    $downloadLink = $__http_base_link . '/didattica/downloadCarenza.php?token=' . $download_token;

    $full_mail_body = str_replace("{messaggio_finale}", 
    'Nella tua area riservata su <a style="color:black" href="' . $__http_base_link . '/">GestOre</a> trovi il programma con gli obiettivi minimi da recuperare.<br><br>
     <p style="font-weight:bold; font-size: 16px; line-height: 140%; color:red;"> Il programma lo puoi scaricare direttamente da questo 
     <a href="' . $downloadLink . '">LINK</a></p>', 
    $full_mail_body);

    $to = $studente_email;
    $toName = $studente_nome . " " . $studente_cognome;
    info("Invio carenza via mail allo studente: " . $to . " " . $toName);
    $mailsubject = 'GestOre - Invio programma carenza formativa - materia ' . $program['materia_nome'];
    sendMail($to, $toName, $mailsubject, $full_mail_body);
    date_default_timezone_set("Europe/Rome");
    $update = date("Y-m-d H-i-s");
    $query = "UPDATE carenze SET stato = '3', data_invio = '$update' WHERE id = '" . $program['carenza_id'] . "'";
    dbExec($query);
    info("aggiornata data invio carenza id=" . $program['carenza_id']);
    echo 'sent';
    exit;
  } 





?>