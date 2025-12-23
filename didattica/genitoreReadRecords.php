<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2025 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// include Database connection file

require_once '../common/checkSession.php';
require_once '../common/connect.php';

$soloAttivi = isset($_GET["soloAttivi"]) ? $_GET["soloAttivi"] : 0;
$classeFiltroId = isset($_GET["classeFiltroId"]) ? (int)$_GET["classeFiltroId"] : 0;
$ancheSenzaStudenti = isset($_GET["ancheSenzaStudenti"]) ? $_GET["ancheSenzaStudenti"] : 1;

$soloAttivi = ($soloAttivi === 1 || $soloAttivi === "1" || $soloAttivi === true || $soloAttivi === "true") ? 1 : 0;
$ancheSenzaStudenti = ($ancheSenzaStudenti === 1 || $ancheSenzaStudenti === "1" || $ancheSenzaStudenti === true || $ancheSenzaStudenti === "true") ? 1 : 0;

// header tabella
$data = '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">
<thead>
<tr>
  <th class="text-center col-md-1">Cognome</th>
  <th class="text-center col-md-1">Nome</th>
  <th class="text-center col-md-1">Codice fiscale</th>
  <th class="text-center col-md-1">UserID MasterCom</th>
  <th class="text-center col-md-2">email</th>
  <th class="text-center col-md-3">Genitore di</th>
  <th class="text-center col-md-1">Relazione</th>
  <th class="text-center col-md-1">Attivo</th>
  <th class="text-center col-md-1"></th>
</tr>
</thead>';

// costruisco condizioni
$where = [];
if ($soloAttivi) {
    $where[] = "g.attivo = 1";
}

// filtro classe: si applica sulle righe figlio (JOIN), ma NON deve eliminare il genitore se ancheSenzaStudenti=1
// per gestire correttamente: mettiamo il filtro classe nella JOIN su classi (così il GROUP_CONCAT si svuota se non matcha).
$classeJoinFilter = "";
if ($classeFiltroId > 0) {
    $classeJoinFilter = " AND c.id = $classeFiltroId ";
}

$whereSql = "";
if (!empty($where)) {
    $whereSql = "WHERE " . implode(" AND ", $where);
}

// Query unica: prende genitori + figli attivi che frequentano anno corrente + classe, + relazione
// GROUP_CONCAT costruisce stringhe con <br> già pronte.
$query = "
SELECT
  g.id,
  g.cognome,
  g.nome,
  g.codice_fiscale,
  g.username,
  g.email,
  g.attivo,

  GROUP_CONCAT(
    DISTINCT CONCAT(
      s.cognome, ' ', s.nome, ' (', UPPER(c.classe), ')'
    )
    ORDER BY s.cognome, s.nome SEPARATOR '<br>'
  ) AS genitoriDi,

  GROUP_CONCAT(
    DISTINCT CONCAT(
      UCASE(LEFT(gr.relazione,1)), LCASE(SUBSTRING(gr.relazione,2))
    )
    ORDER BY s.cognome, s.nome SEPARATOR '<br>'
  ) AS relazioni

FROM genitori g
LEFT JOIN genitori_studenti gs ON gs.id_genitore = g.id
LEFT JOIN studente s ON s.id = gs.id_studente 
LEFT JOIN studente_frequenta sf
  ON sf.id_studente = s.id
  AND sf.id_anno_scolastico = $__anno_scolastico_corrente_id
LEFT JOIN classi c
  ON c.id = sf.id_classe
  AND sf.id_classe <> 0
  $classeJoinFilter
LEFT JOIN genitori_relazioni gr ON gr.id = gs.id_relazione

$whereSql
GROUP BY g.id
ORDER BY g.cognome ASC, g.nome ASC
";

$rows = dbGetAll($query);

foreach ($rows as $row) {
    $genitoriDi = $row['genitoriDi'] ?? '';
    $relazioni  = $row['relazioni'] ?? '';

    // Se non voglio mostrare genitori senza studenti, filtro qui (dopo la query)
    if (!$ancheSenzaStudenti && trim($genitoriDi) === '') {
        continue;
    }

    $data .= '<tr>
      <td style="text-align:center">'.ucwords(strtolower($row['cognome'])).'</td>
      <td style="text-align:center">'.ucwords(strtolower($row['nome'])).'</td>
      <td style="text-align:center">'.strtoupper($row['codice_fiscale']).'</td>
      <td style="text-align:center">'.$row['username'].'</td>
      <td style="text-align:center">'.strtolower($row['email']).'</td>
      <td style="text-align:center">'.$genitoriDi.'</td>
      <td style="text-align:center">'.$relazioni.'</td>
      <td class="text-center"><input type="checkbox" disabled data-toggle="toggle" data-onstyle="primary" '.($row['attivo'] ? 'checked' : '').'></td>
      <td class="text-center">
        <button onclick="genitoreGetDetails('.$row['id'].')" class="btn btn-warning btn-xs"><span class="glyphicon glyphicon-pencil"></span></button>
        <button onclick="genitoreDelete('.$row['id'].', \''.addslashes($row['cognome']).'\', \''.addslashes($row['nome']).'\')" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-trash"></span></button>
        <button onclick="genitoreImpersona('.$row['id'].', \''.addslashes($row['cognome']).'\', \''.addslashes($row['nome']).'\')" class="btn btn-teal4 btn-xs"><span class="glyphicon glyphicon-pawn"></span></button>
      </td>
    </tr>';
}

$data .= '</table></div>';
echo $data;
