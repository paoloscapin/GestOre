<?php

/**
 *  Invia gli esiti degli esami ai coordinatori di classe
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/__Util.php';
require_once '../common/send-mail.php';

// Query principale
$sql = "
SELECT 
    coord.id_docente             AS coordinatore_id,
    d.email                      AS coordinatore_email,
    d.nome                       AS coordinatore_nome,
    d.cognome                    AS coordinatore_cognome,
    
    s.id                         AS studente_id,
    s.cognome                    AS studente_cognome,
    s.nome                       AS studente_nome,
    cl.classe                    AS classe_nome,
    
    m.nome                       AS materia_nome,
    ced.data_inizio_esame        AS data_inizio_esame,
    ced.data_fine_esame          AS data_fine_esame,
    ce.tipo_prova,
    ce.presente,
    ce.recuperato
    
FROM corso_esiti ce
INNER JOIN corso_esami_date ced  ON ced.id = ce.id_esame_data
INNER JOIN corso c              ON c.id = ce.id_corso
INNER JOIN materia m            ON m.id = c.id_materia
INNER JOIN studente s           ON s.id = ce.id_studente
INNER JOIN studente_frequenta sf ON sf.id_studente = s.id 
                                 AND sf.id_anno_scolastico = 7
INNER JOIN classi cl            ON cl.id = sf.id_classe
INNER JOIN coordinatori coord   ON coord.id_classe = cl.id
                                 AND coord.id_anno_scolastico = 7
INNER JOIN docente d            ON d.id = coord.id_docente
WHERE c.carenza = 1
ORDER BY coord.id_docente, cl.classe, s.cognome, s.nome, m.nome, ced.data_inizio_esame
";

$righe = dbGetAll($sql);

if (!$righe) {
    echo json_encode(["success" => false, "msg" => "Nessun esito trovato"]);
    exit;
}

// Raggruppo per coordinatore e classe
$gruppo = [];
foreach ($righe as $riga) {
    $key = $riga['coordinatore_id'] . "_" . $riga['classe_nome'];
    $gruppo[$key]['coordinatore_id'] = $riga['coordinatore_id'];
    $gruppo[$key]['coordinatore_email'] = $riga['coordinatore_email'];
    $gruppo[$key]['coordinatore_nome'] = $riga['coordinatore_nome'];
    $gruppo[$key]['coordinatore_cognome'] = $riga['coordinatore_cognome'];
    $gruppo[$key]['classe_nome'] = $riga['classe_nome'];
    $gruppo[$key]['esiti'][] = $riga;
}

// Invio mail a ciascun coordinatore
foreach ($gruppo as $key => $dati) {
    //$to = "massimo.saiani@scuole.provincia.tn.it"; // Per test
    $to = $dati['coordinatore_email'];
    $toName = $dati['coordinatore_nome'] . " " . $dati['coordinatore_cognome'];
    $classe = $dati['classe_nome'];

    $subject = "Esiti esami studenti della classe $classe";

    // Costruisco il corpo della mail
    $body = "<h3>Esiti degli esami carenze - Classe $classe</h3>";
    $body .= "<table border='1' cellspacing='0' cellpadding='5'>";
    $body .= "<tr>
                <th>Studente</th>
                <th>Materia</th>
                <th>Data inizio</th>
                <th>Data fine</th>
                <th>Tipo prova</th>
                <th>Presenza</th>
                <th>Recupero</th>
              </tr>";

    foreach ($dati['esiti'] as $esito) {
        $presenza = $esito['presente'] ? "Presente" : "Assente";
        $recupero = $esito['recuperato'] ? "Recuperato" : "Non recuperato";

        // Converto data esame in formato italiano
        $dataInizioEsameIt = date("d/m/Y H:i", strtotime($esito['data_inizio_esame']));
        $dataFineEsameIt = date("d/m/Y H:i", strtotime($esito['data_fine_esame']));

        $body .= "<tr>
                    <td>{$esito['studente_cognome']} {$esito['studente_nome']}</td>
                    <td>{$esito['materia_nome']}</td>
                    <td>$dataInizioEsameIt</td>
                    <td>$dataFineEsameIt</td>
                    <td>{$esito['tipo_prova']}</td>
                    <td>$presenza</td>
                    <td>$recupero</td>
                  </tr>";
    }

    $body .= "</table>";

    // Invio mail
    sendMail($to, $toName, $subject, $body);
    info("Mail inviata a $toName ($to) per la classe $classe<br>");
}
 echo json_encode(["success" => true, "msg" => "Tutte le mail sono state inviate"]);
