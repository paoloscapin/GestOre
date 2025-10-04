<?php
/**
 * Restituisce i dettagli degli esami (1° e 2° tentativo) di un corso
 * {
 *   success: true,
 *   esami: [ ... ],
 *   studenti: [ ... ]
 * }
 */

require_once '../common/checkSession.php';

header('Content-Type: application/json');

if (!isset($_POST['corso_id'])) {
    echo json_encode(['success' => false, 'error' => 'Parametro corso_id mancante']);
    exit;
}

$corso_id = intval($_POST['corso_id']);

// ============================
// Recupero le sessioni di esame
// ============================
$queryEsami = "
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
";
$esami = dbGetAll($queryEsami);

// ============================
// Recupero studenti con esiti
// ============================
$queryStudenti = "
    SELECT 
        s.id AS stud_id,
        s.cognome,
        s.nome,
        c.classe,
        ced.tentativo,
        ce.presente,
        ce.tipo_prova,
        ce.voto,
        ce.recuperato,
        ce.argomenti
    FROM corso_iscritti ci
    INNER JOIN studente s ON s.id = ci.id_studente
    INNER JOIN studente_frequenta sf 
        ON sf.id_studente = s.id 
       AND sf.id_anno_scolastico = (SELECT MAX(id) FROM anno_scolastico)
    INNER JOIN classi c ON c.id = sf.id_classe
    INNER JOIN corso_esiti ce ON ce.id_studente = s.id AND ce.id_corso = ci.id_corso
    INNER JOIN corso_esami_date ced ON ced.id = ce.id_esame_data
    WHERE ci.id_corso = $corso_id
    ORDER BY s.cognome, s.nome
";
$studenti = dbGetAll($queryStudenti);

echo json_encode([
    'success'  => true,
    'esami'    => $esami,
    'studenti' => $studenti
]);
