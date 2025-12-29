<?php
/**
 * Restituisce i dettagli degli esami (tentativi) di un corso
 * {
 *   success: true,
 *   esami: [ ... ],
 *   studenti: [ ... ]  // una riga per studente per ogni tentativo (se esiste l'esame)
 * }
 */

require_once '../common/checkSession.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['corso_id'])) {
    echo json_encode(['success' => false, 'error' => 'Parametro corso_id mancante']);
    exit;
}

$corso_id = intval($_POST['corso_id']);
$anno_corrente = intval($__anno_scolastico_corrente_id);

// ============================
// Sessioni esame del corso
// ============================
$esami = dbGetAll("
    SELECT 
        id,
        id_corso AS corso_id,
        tentativo,
        data_inizio_esame,
        data_fine_esame,
        aula,
        firmato
    FROM corso_esami_date
    WHERE id_corso = $corso_id
    ORDER BY tentativo ASC
");
if (!$esami) $esami = [];

// Se non ci sono esami, niente studenti (la UI mostrerà "nessun esame programmato")
if (count($esami) === 0) {
    echo json_encode([
        'success' => true,
        'esami' => [],
        'studenti' => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================
// Studenti + esiti per ciascun tentativo
// ============================
// Logica: per ogni studente iscritto e per ogni esame_date (tentativo) del corso,
// prendo l'eventuale esito collegato a quella sessione (ce.id_esame_data = ed.id).
$queryStudenti = "
    SELECT
        s.id AS stud_id,
        s.cognome,
        s.nome,
        c.classe,

        ed.tentativo,
        ed.id AS id_esame_data,

        COALESCE(ce.presente, 0) AS presente,
        ce.tipo_prova,
        ce.voto,
        COALESCE(ce.recuperato, 0) AS recuperato,
        ce.argomenti,

        COALESCE(ce.assenza_giustificata, 0) AS assenza_giustificata,
        ce.assenza_note

    FROM corso_iscritti ci
    INNER JOIN studente s
        ON s.id = ci.id_studente

    INNER JOIN studente_frequenta sf
        ON sf.id_studente = s.id
       AND sf.id_anno_scolastico = $anno_corrente

    INNER JOIN classi c
        ON c.id = sf.id_classe

    INNER JOIN corso_esami_date ed
        ON ed.id_corso = ci.id_corso

    LEFT JOIN corso_esiti ce
        ON ce.id_corso = ci.id_corso
       AND ce.id_studente = s.id
       AND ce.id_esame_data = ed.id

    WHERE ci.id_corso = $corso_id

    ORDER BY s.cognome, s.nome, ed.tentativo
";

$studenti = dbGetAll($queryStudenti);
if (!$studenti) $studenti = [];

echo json_encode([
    'success'  => true,
    'esami'    => $esami,
    'studenti' => $studenti
], JSON_UNESCAPED_UNICODE);
