<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

$pagina = '';

require_once '../common/checkSession.php';
// program.php (in testa al file, prima di qualsiasi uso di mPDF)
require_once '../common/vendor/autoload.php';
require_once '../common/send-mail.php';
require_once '../common/carenzeMailLogLib.php';
require_once '../common/carenzeParentAccessLib.php';
require_once '../common/mail-ui.php';
require_once '../api/googleDriveLib.php';
require_once __DIR__ . '/carenzeDownloadLib.php';
ruoloRichiesto('admin', 'genitore', 'docente', 'studente', 'segreteria-didattica', 'dirigente');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect("/error/unauthorized.php");
    exit;
}

function carenzeFailUnauthorized()
{
    redirect("/error/unauthorized.php");
    exit;
}

function canGenitoreAccessStudente($idStudente, $idGenitore)
{
    $idStudente = (int)$idStudente;
    $idGenitore = (int)$idGenitore;

    if ($idStudente <= 0 || $idGenitore <= 0) {
        return false;
    }

    $q = "
        SELECT id_studente
        FROM genitori_studenti
        WHERE id_genitore = " . dbI($idGenitore) . "
          AND id_studente = " . dbI($idStudente) . "
        LIMIT 1
    ";

    $row = dbGetFirst($q);
    return is_array($row) && !empty($row['id_studente']);
}

function carenzeMailConfiguredAccounts(): array
{
    global $__settings;

    $mailConfig = $__settings->iscrizioniPrime->mail ?? null;
    $accounts = [];
    if ($mailConfig == null || empty($mailConfig->accounts) || !is_array($mailConfig->accounts)) {
        return $accounts;
    }

    foreach ($mailConfig->accounts as $account) {
        $email = strtolower(trim((string)($account->email ?? '')));
        if ($email === '') {
            continue;
        }
        $accounts[$email] = [
            'email' => $email,
            'password' => (string)($account->password ?? ''),
            'smtp_host' => trim((string)($mailConfig->smtpHost ?? $__settings->local->smtpHost)),
            'smtp_secure' => (string)($mailConfig->SMTPSecure ?? $__settings->local->SMTPSecure),
            'smtp_port' => intval($mailConfig->Port ?? $__settings->local->Port),
        ];
    }

    return $accounts;
}

function carenzeMailAccountByEmail(string $email): ?array
{
    $email = strtolower(trim($email));
    if ($email === '') {
        return null;
    }

    $accounts = carenzeMailConfiguredAccounts();
    return $accounts[$email] ?? null;
}

function carenzeMailNextConfiguredAccount(): ?array
{
    $accounts = carenzeMailConfiguredAccounts();
    if (!$accounts) {
        return null;
    }

    carenzeMailLogEnsureTable();
    $usageRows = dbGetAll("
        SELECT account_email, COUNT(*) AS n
        FROM carenze_mail_log
        WHERE DATE(created_at) = CURDATE()
        GROUP BY account_email
    ");
    $usage = [];
    foreach ($usageRows as $row) {
        $usage[strtolower(trim((string)($row['account_email'] ?? '')))] = intval($row['n'] ?? 0);
    }

    $bestAccount = null;
    $bestCount = PHP_INT_MAX;
    foreach ($accounts as $email => $account) {
        $count = $usage[$email] ?? 0;
        if ($count < $bestCount) {
            $bestCount = $count;
            $bestAccount = $account;
        }
    }

    return $bestAccount;
}

function carenzeMailOptions(array $ccGenitori, ?array $account = null, string $logToken = ''): array
{
    global $__settings;

    $fromEmail = trim((string)($__settings->local->emailNoReplyFrom ?? ''));
    $fromName = "GestOre " . (string)($__settings->local->nomeIstituto ?? '');
    $replyToEmail = trim((string)($__settings->local->emailCarenze ?? ''));
    if ($replyToEmail === '') {
        $replyToEmail = $fromEmail;
    }

    $options = [
        'from_email' => $fromEmail,
        'from_name' => $fromName,
        'reply_to_email' => $replyToEmail,
        'reply_to_name' => 'Segreteria didattica',
        'sender_email' => trim((string)($__settings->local->smtpMail ?? $fromEmail)),
        'sender_name' => $fromName,
        'cc' => $ccGenitori,
        'custom_headers' => [],
    ];

    if ($logToken !== '') {
        $options['custom_headers']['X-GestOre-Carenza-Log-Token'] = $logToken;
    }

    if ($account != null) {
        $options['sender_email'] = $account['email'];
        $options['smtp_username'] = $account['email'];
        $options['smtp_password'] = $account['password'];
        $options['smtp_host'] = $account['smtp_host'];
        $options['smtp_secure'] = $account['smtp_secure'];
        $options['smtp_port'] = $account['smtp_port'];
        $options['dispatch_sender_email'] = $account['email'];
    }

    return $options;
}

function getCarenzaAutorizzata($carenzaId, $utenteRuolo, $genitoreId = 0, $studenteId = 0)
{
    $carenzaId = (int)$carenzaId;
    $genitoreId = (int)$genitoreId;
    $studenteId = (int)$studenteId;

    if ($carenzaId <= 0) {
        return null;
    }

    $whereExtra = "";

    if ($utenteRuolo === 'genitore') {
        $whereExtra = "
            AND EXISTS (
                SELECT 1
                FROM genitori_studenti gs
                WHERE gs.id_genitore = " . dbI($genitoreId) . "
                  AND gs.id_studente = carenze.id_studente
            )
        ";
    } elseif ($utenteRuolo === 'studente') {
        $whereExtra = "
            AND carenze.id_studente = " . dbI($studenteId) . "
        ";
    }

    $query = "
        SELECT  
            carenze.id AS carenza_id,
            carenze.id_studente AS stud_id,
            carenze.id_materia AS materia_id,
            carenze.id_classe AS classe_id,
            carenze.id_docente AS doc_id,
            carenze.id_anno_scolastico AS carenza_anno_scolastico_id,
            carenze.stato AS stato,
            carenze.nota_docente AS nota,
            anno_scolastico.anno AS anno_scolastico,
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
        FROM carenze
        INNER JOIN classi classi
            ON classi.id = carenze.id_classe
        INNER JOIN materia materia
            ON materia.id = carenze.id_materia
        INNER JOIN studente studente
            ON studente.id = carenze.id_studente
        LEFT JOIN (
            SELECT id_classe, id_materia, id_anno_scolastico, MIN(id_docente) AS id_docente
            FROM docente_insegna
            GROUP BY id_classe, id_materia, id_anno_scolastico
        ) docente_carenza
            ON docente_carenza.id_classe = carenze.id_classe
           AND docente_carenza.id_materia = carenze.id_materia
           AND docente_carenza.id_anno_scolastico = carenze.id_anno_scolastico
        LEFT JOIN docente docente
            ON docente.id = COALESCE(NULLIF(carenze.id_docente, 0), docente_carenza.id_docente)
        INNER JOIN programma_minimi programma_minimi
            ON programma_minimi.ANNO = classi.anno
           AND programma_minimi.ID_MATERIA = materia.id
           AND (
                programma_minimi.ID_INDIRIZZO = classi.id_primo_indirizzo
                OR programma_minimi.ID_INDIRIZZO = classi.id_secondo_indirizzo
           )
        INNER JOIN indirizzo indirizzo
            ON indirizzo.id = classi.id_primo_indirizzo
        INNER JOIN anno_scolastico anno_scolastico
            ON anno_scolastico.id = carenze.id_anno_scolastico
        WHERE carenze.id = " . dbI($carenzaId) . "
        $whereExtra
        LIMIT 1
    ";

    return dbGetFirst($query);
}

function carenzeGetGenitoriCc(int $studenteId, string $excludeEmail = ''): array
{
    if ($studenteId <= 0) {
        return [];
    }

    $excludeEmail = strtolower(trim($excludeEmail));
    $query = "
        SELECT DISTINCT
            genitori.email,
            genitori.nome,
            genitori.cognome
        FROM genitori_studenti
        INNER JOIN genitori
            ON genitori.id = genitori_studenti.id_genitore
        WHERE genitori_studenti.id_studente = " . dbI($studenteId) . "
          AND genitori.email IS NOT NULL
          AND TRIM(genitori.email) <> ''
          AND genitori.attivo = 1
        ORDER BY genitori.cognome, genitori.nome
    ";

    $cc = [];
    foreach (dbGetAll($query) as $row) {
        $email = trim((string)($row['email'] ?? ''));
        if ($email === '' || strtolower($email) === $excludeEmail || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }

        $name = trim((string)($row['nome'] ?? '') . ' ' . (string)($row['cognome'] ?? ''));
        $cc[$email] = $name !== '' ? $name : $email;
    }

    return $cc;
}

function carenzePopFirstRecipient(array &$recipients): array
{
    foreach ($recipients as $email => $name) {
        unset($recipients[$email]);
        return ['email' => (string)$email, 'name' => trim((string)$name) !== '' ? (string)$name : (string)$email];
    }
    return ['email' => '', 'name' => ''];
}

// 1) PARAMETRI POST
$doView  = isset($_POST['view']) && ($_POST['view'] == '1' || $_POST['view'] === 'true');
$doPrint = isset($_POST['print']) && ($_POST['print'] == '1' || $_POST['print'] === 'true');
$doMail = isset($_POST['mail']) && ($_POST['mail'] == '1' || $_POST['mail'] === 'true');
$titolo = isset($_POST['titolo']) ? $_POST['titolo'] : 'Programma didattico';
$doGenera = isset($_POST['genera']) && ($_POST['genera'] == '1' || $_POST['genera'] === 'true');
$anno = isset($_POST['anno']) ? (int) $_POST['anno'] : 0;
$anno_scolastico = '';
$mailAccountEmail = isset($_POST['mail_account']) ? trim((string)$_POST['mail_account']) : '';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    carenzeFailUnauthorized();
}

$carenzaId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($carenzaId <= 0) {
    carenzeFailUnauthorized();
}

$program = getCarenzaAutorizzata(
    $carenzaId,
    $__utente_ruolo,
    (int)($__genitore_id ?? 0),
    (int)($__studente_id ?? 0)
);

if (!$program) {
    carenzeFailUnauthorized();
}

$anno = (int)($program['carenza_anno_scolastico_id'] ?? 0);
$anno_scolastico = trim((string)($program['anno_scolastico'] ?? ''));
if ($anno_scolastico === '' && $anno > 0) {
  $anno_scolastico = (string)dbGetValue("SELECT anno FROM anno_scolastico WHERE id = " . dbI($anno));
}

// se devo inviare solo la mail non mi serve rigenerare la pagina

//RECUPERO MODULI
$id_programma_minimi = $program['prog_id'];
if (!$program) carenzeFailUnauthorized();
$query = "SELECT * from programma_minimi_moduli WHERE id_programma = $id_programma_minimi ORDER BY ordine ASC";
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

/**
 * Rende una lista UL con al massimo due livelli.
 * Convenzioni:
 *  - Livello 1: riga che inizia con "- " o "* "
 *  - Livello 2: riga indentata (≥2 spazi o \t) prima del trattino, oppure che inizia con "-- " / "** "
 * Se non trova bullet, usa le righe non vuote come voci di primo livello.
 */
function buildTwoLevelListFromText(string $text): string
{
    $lines = preg_split('/\R/', $text);
    $tree = []; // array di ['text'=>..., 'children'=>[...]]
    $currentParent = null;

    foreach ($lines as $line) {
        $raw = rtrim($line);
        if ($raw === '') {
            continue;
        }

        // Normalizza tab a 2 spazi per il calcolo del livello
        $norm = str_replace("\t", "  ", $raw);

        // Pattern: opzionale indent (≥2 spazi), poi 1+ marker -,* e spazio, poi testo
        if (preg_match('/^(?:( {2,}))?([\-*]{1,})\s+(.+)$/u', $norm, $m)) {
            $indent  = $m[1] ?? '';
            $markers = $m[2];
            $textLi  = trim($m[3]);

            // Determina livello (0 = top, 1 = sotto)
            $level = 0;
            if ($indent !== '' || strlen($markers) >= 2) {
                $level = 1;
            }

            if ($level === 0) {
                // nuovo parent
                $tree[] = ['text' => $textLi, 'children' => []];
                $currentParent = count($tree) - 1;
            } else {
                // figlio: se non c’è un parent aperto, creane uno “vuoto”
                if ($currentParent === null) {
                    $tree[] = ['text' => '', 'children' => []];
                    $currentParent = count($tree) - 1;
                }
                $tree[$currentParent]['children'][] = ['text' => $textLi, 'children' => []];
            }
        } else {
            // Nessun bullet su questa riga: trattala come top-level
            $tree[] = ['text' => trim($raw), 'children' => []];
            $currentParent = count($tree) - 1;
        }
    }

    return renderTwoLevelList($tree);
}

/** Renderer ricorsivo per massimo due livelli */
function renderTwoLevelList(array $nodes): string
{
    if (empty($nodes)) return '';

    $html = '<ul>';
    foreach ($nodes as $n) {
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
  top: 20px;
  left: 20px;
  z-index: 9999;
  background: #FFA500;
  padding: 6px 12px;
  border-radius: 6px;
  font-weight: 900;
  font-style: italic;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
}

.print-button button {
  font-size: 16px;
  font-weight: bold;
  border: none;
  background: transparent;
}

/* Stile specifico per smartphone */
@media (max-width: 768px) {
  .print-button {
    top: auto;          /* disattivo il posizionamento in alto */
    left: auto;         /* disattivo il posizionamento a sinistra */
    bottom: 20px;       /* lo metto in basso */
    right: 20px;        /* lo metto a destra */
    padding: 14px 20px;
    border-radius: 50px;
  }

  .print-button button {
    font-size: 20px;
    padding: 18px 28px;
    background: #ff8800;
    color: white;
    border-radius: 8px;
  }
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
        <input type="hidden" name="mail" value="0">
        <input type="hidden" name="genera" value="0">
        <input type="hidden" name="anno" value="<?= (int)$anno ?>">
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
      <p>Studente <?= htmlspecialchars($program['stud_cognome'] . ' ' . $program['stud_nome'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> | 
        Classe <?= htmlspecialchars($program['classe_nome'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> | 
        Indirizzo <?= htmlspecialchars($program['ind_nome'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><br>
        Materia <?= htmlspecialchars($program['materia_nome'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><br>
        Docente <?= htmlspecialchars($program['doc_cognome'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' ' . htmlspecialchars($program['doc_nome'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> | 
        Anno scolastico <?= $anno_scolastico ?></p>
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
              'Conoscenze' => buildTwoLevelListFromText($m['CONOSCENZE']),
              'Abilità' => buildTwoLevelListFromText($m['ABILITA'])
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
            'Note' => buildTwoLevelListFromText($nota_docente)
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
if ($doView) {
  echo $html;
  exit;
}

if ($doPrint ||  $doGenera) {
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
  Anno scolastico ' . $anno_scolastico . '</p><br>';


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
      'Conoscenze' => buildTwoLevelListFromText($m['CONOSCENZE']),
      'Abilità' => buildTwoLevelListFromText($m['ABILITA']),
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
    'Note' => buildTwoLevelListFromText($nota_docente)
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
if ($doPrint) {
  $pdf->Output(carenzeDownloadBuildFilename($program + ['titolo' => $titolo]), 'D');
  exit;
}
if ($doGenera) {
  $token = bin2hex(random_bytes(16)); // link anonimo, sicuro
  $randomFileName = bin2hex(random_bytes(12)) . '.pdf';
  $localDir = carenzeDownloadEnsureLocalDir($anno_scolastico);
  $filename = $localDir . '/' . $randomFileName;
  $filePath = carenzeDownloadLocalRelativePath($randomFileName, $anno_scolastico);
  $originalFilename = carenzeDownloadBuildFilename($program + ['titolo' => $titolo]);
  $pdf->Output($filename, 'F'); // salva il file
  $created_at = date('Y-m-d H:i:s');
  $expires_at = date('Y-m-d H:i:s', strtotime('+3 months'));
  $query = "SELECT * FROM carenze_downloads WHERE student_id='" . $studente_id . "' AND carenza_id='" . $carenza_id . "' LIMIT 1";
  $downloadRow = dbGetFirst($query);
  $esiste = $downloadRow ? 1 : 0;
  $downloadTokenExpiresAt = strtotime((string)($downloadRow['expires_at'] ?? ''));
  if ($downloadRow && trim((string)($downloadRow['download_token'] ?? '')) !== '' && $downloadTokenExpiresAt !== false && $downloadTokenExpiresAt > time()) {
    $token = trim((string)$downloadRow['download_token']);
  }

  try {
    $upload = carenzeDownloadUploadToDrive($filename, $originalFilename, $anno_scolastico);
    $driveFileId = trim((string)($upload['id'] ?? ''));
    if ($driveFileId === '') {
      throw new Exception('Upload Drive completato senza ID file');
    }
    $driveWebViewLink = (string)($upload['webViewLink'] ?? '');
  } catch (Throwable $e) {
    if (is_file($filename)) {
      @unlink($filename);
    }
    error("upload Drive carenza id=" . $program['carenza_id'] . " fallito: " . $e->getMessage());
    echo 'PDF generato ma upload Drive fallito: ' . $e->getMessage();
    exit;
  }

  // salva nel DB
  if ($esiste == 0) {
    [$columns, $values] = carenzeDownloadInsertSqlFields([
      'student_id' => "'" . escapeString($studente_id) . "'",
      'carenza_id' => "'" . escapeString($carenza_id) . "'",
      'file_path' => "'" . escapeString($filePath) . "'",
      'download_token' => "'" . escapeString($token) . "'",
      'created_at' => "'" . escapeString($created_at) . "'",
      'expires_at' => "'" . escapeString($expires_at) . "'",
      'storage_type' => "'DRIVE'",
      'drive_file_id' => "'" . escapeString($driveFileId) . "'",
      'drive_web_view_link' => "'" . escapeString($driveWebViewLink) . "'",
      'original_filename' => "'" . escapeString($originalFilename) . "'",
      'migrated_at' => "NOW()",
    ]);
    $query = "INSERT INTO carenze_downloads (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";
  } else {
    $assignments = carenzeDownloadUpdateAssignments([
      'download_token' => "'" . escapeString($token) . "'",
      'file_path' => "'" . escapeString($filePath) . "'",
      'created_at' => "'" . escapeString($created_at) . "'",
      'expires_at' => "'" . escapeString($expires_at) . "'",
      'download_count' => "0",
      'last_ip' => "''",
      'storage_type' => "'DRIVE'",
      'drive_file_id' => "'" . escapeString($driveFileId) . "'",
      'drive_web_view_link' => "'" . escapeString($driveWebViewLink) . "'",
      'original_filename' => "'" . escapeString($originalFilename) . "'",
      'migrated_at' => "NOW()",
    ]);
    $query = "UPDATE carenze_downloads SET $assignments WHERE student_id = '$studente_id' AND carenza_id = '$carenza_id'";
  }
  dbExec($query);

  date_default_timezone_set("Europe/Rome");
  $update = date("Y-m-d H-i-s");
  $query = "UPDATE carenze SET stato = '2' WHERE id = '" . $program['carenza_id'] . "'";
  dbExec($query);
  info("generato PDF carenza id=" . $program['carenza_id']);
  if ((int)$esiste == 0) {
    echo 'generato';
  } else {
    echo 'aggiornato';
  }
  exit;
}

if ($doMail) {

  $query = "SELECT * FROM carenze_downloads WHERE student_id='" . $studente_id . "' AND carenza_id='" . $carenza_id . "'";

  $esiste = dbGetFirst($query);
  if ($esiste == null) {
    echo ' File carenza non ancora generato';
    exit;
  }

  $download_token = trim((string)($esiste['download_token'] ?? ''));
  $downloadTokenExpiresAt = strtotime((string)($esiste['expires_at'] ?? ''));
  if ($download_token === '' || $downloadTokenExpiresAt === false || $downloadTokenExpiresAt <= time()) {
    $download_token = bin2hex(random_bytes(16));
  }
  $mail_expires_at = date('Y-m-d H:i:s', strtotime('+3 months'));
  dbExec("
    UPDATE carenze_downloads
    SET download_token = '" . escapeString($download_token) . "',
        expires_at = '" . escapeString($mail_expires_at) . "'
    WHERE student_id = '" . escapeString($studente_id) . "'
      AND carenza_id = '" . escapeString($carenza_id) . "'
  ");
  $studente_cognome = $program['stud_cognome'];
  $studente_nome = $program['stud_nome'];
  $studente_email = $program['stud_email'];
  $docente_cognome = $program['doc_cognome'];
  $docente_nome = $program['doc_nome'];

  if ($__utente_ruolo == 'genitore') {
    $query = "SELECT * from genitori WHERE id = " . intval($__genitore_id);
    $genitore = dbGetFirst($query);
    $genitore_nome = $genitore['nome'];
    $genitore_cognome = $genitore['cognome'];
    $genitore_email = $genitore['email'];
  }

  $downloadLink = $__http_base_link . '/didattica/downloadCarenza.php?token=' . $download_token;
  $classeCarenza = strtoupper(trim((string)($program['classe_nome'] ?? '')));
  $parentAccessHtml = $classeCarenza === 'EE'
    ? carenzeParentAccessFooterHtml((int)$studente_id, $__http_base_link)
    : '';
  $full_mail_body = mailCarenzaHtml(
    strtoupper($studente_cognome) . " " . strtoupper($studente_nome),
    $program,
    strtoupper($docente_cognome . " " . $docente_nome),
    (string)$nota_docente,
    $downloadLink,
    $__http_base_link,
    $parentAccessHtml
  );

  $ccGenitori = [];
  if ($__utente_ruolo == "studente") {
    $to = $studente_email;
    $toName = $studente_nome . " " . $studente_cognome;
    info("Invio carenza via mail allo studente: " . $to . " " . $toName);
  } else
      if ($__utente_ruolo == "genitore") {
    $to = $genitore_email;
    $toName = $genitore_nome . " " . $genitore_cognome;
    info("Invio carenza via mail al genitore: " . $to . " " . $toName);
  } else {
    $ccGenitori = carenzeGetGenitoriCc((int)$studente_id, (string)$studente_email);
    if (trim((string)$studente_email) !== '' && filter_var($studente_email, FILTER_VALIDATE_EMAIL)) {
      $to = $studente_email;
      $toName = $studente_nome . " " . $studente_cognome;
      info("Invio carenza via mail allo studente: " . $to . " " . $toName . " da ruolo " . $__utente_ruolo . " con genitori in CC=" . count($ccGenitori));
    } else {
      $firstParent = carenzePopFirstRecipient($ccGenitori);
      $to = $firstParent['email'];
      $toName = $firstParent['name'];
      if ($to === '') {
        error("invio mail carenza id=" . $program['carenza_id'] . " impossibile: mail studente vuota e nessuna mail genitore valida");
        echo 'Invio mail fallito: mail studente vuota e nessuna mail genitore valida';
        exit;
      }
      info("Invio carenza via mail al genitore: " . $to . " " . $toName . " da ruolo " . $__utente_ruolo . " per studente senza email, altri genitori in CC=" . count($ccGenitori));
    }
  }
  $mailsubject = 'GestOre - Invio programma carenza formativa - materia ' . $program['materia_nome'];
  $mailAccount = null;
  if ($mailAccountEmail !== '') {
    if (!in_array($__utente_ruolo, ['admin', 'segreteria-didattica', 'dirigente'], true)) {
      carenzeFailUnauthorized();
    }
    $mailAccount = carenzeMailAccountByEmail($mailAccountEmail);
    if ($mailAccount == null) {
      error("invio mail carenza id=" . $program['carenza_id'] . " account massivo non configurato: " . $mailAccountEmail);
      echo 'Account di invio non configurato: ' . htmlspecialchars($mailAccountEmail, ENT_QUOTES, 'UTF-8');
      exit;
    }
    info("Invio carenza id=" . $program['carenza_id'] . " con account massivo " . $mailAccount['email']);
  } elseif (in_array($__utente_ruolo, ['admin', 'segreteria-didattica', 'dirigente'], true)) {
    $mailAccount = carenzeMailNextConfiguredAccount();
    if ($mailAccount != null) {
      info("Invio carenza id=" . $program['carenza_id'] . " con account massivo automatico " . $mailAccount['email']);
    }
  }
  $mailOptionsWithoutToken = carenzeMailOptions($ccGenitori, $mailAccount);
  $accountEmailForLog = strtolower(trim((string)($mailOptionsWithoutToken['dispatch_sender_email'] ?? $mailOptionsWithoutToken['sender_email'] ?? $mailOptionsWithoutToken['from_email'] ?? '')));
  $fromEmailForLog = strtolower(trim((string)($mailOptionsWithoutToken['from_email'] ?? '')));
  $mailLog = carenzeMailLogCreate(
    (int)$program['carenza_id'],
    (int)$studente_id,
    $accountEmailForLog,
    $fromEmailForLog,
    (string)$to,
    array_keys($ccGenitori),
    $mailsubject
  );
  $mailOptions = carenzeMailOptions($ccGenitori, $mailAccount, (string)$mailLog['token']);
  $mailOk = sendMailCustom(
    $to,
    $toName,
    $mailsubject,
    $full_mail_body,
    $mailOptions
  );
  carenzeMailLogUpdateSent((int)$mailLog['id'], (bool)$mailOk, sendMailLastDispatchResult());
  if (!$mailOk) {
    error("invio mail carenza id=" . $program['carenza_id'] . " fallito per destinatario " . $to);
    echo 'Invio mail fallito: controlla configurazione SMTP/OAuth e log di GestOre';
    exit;
  }
  date_default_timezone_set("Europe/Rome");
  $update = date("Y-m-d H-i-s");
  $query = "UPDATE carenze SET stato = '3', data_invio = '$update' WHERE id = '" . $program['carenza_id'] . "'";
  dbExec($query);
  info("aggiornata data invio carenza id=" . $program['carenza_id']);
  echo 'sent';
  exit;
}





?>
