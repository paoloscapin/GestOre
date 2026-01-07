<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['corso_id'])) {
    echo json_encode(['success' => false, 'error' => 'Parametro corso_id mancante']);
    exit;
}

$corso_id = intval($_POST['corso_id']);
$anno_corrente = intval($__anno_scolastico_corrente_id);

// ruolo
$ruolo = strtolower(strval($__utente_ruolo ?? ''));
$is_docente = impersonaRuolo('docente');
$is_segreteria = ($ruolo === 'segreteria-didattica' || $ruolo === 'dirigente' || $ruolo === 'admin');

// docente corrente se docente
$id_docente = 0;
if ($is_docente) {
    $id_docente = intval($__docente_id ?? 0);
}

// docente principale (fallback legacy)
$doc_principale = intval(dbGetValue("SELECT id_docente FROM corso WHERE id=$corso_id LIMIT 1"));

// ============================
// Sessioni esame del corso
// ============================
$esami = dbGetAll("
    SELECT 
        ed.id,
        ed.id_corso AS corso_id,
        ed.tentativo,
        ed.data_inizio_esame,
        ed.data_fine_esame,
        ed.aula,
        ed.firmato AS firmato_any
    FROM corso_esami_date ed
    WHERE ed.id_corso = $corso_id
    ORDER BY ed.tentativo ASC
");
if (!$esami) $esami = [];

if (count($esami) === 0) {
    // crea esame bozza tentativo=1 per NON avere modale vuoto
    $now = date('Y-m-d H:i:s');

    dbExec("
        INSERT INTO corso_esami_date
            (id_corso, data_inizio_esame, data_fine_esame, aula, firmato, tentativo)
        VALUES
            ($corso_id, '$now', '$now', NULL, 0, 1)
    ");

    // ricarica
    $esami = dbGetAll("
        SELECT 
            ed.id,
            ed.id_corso AS corso_id,
            ed.tentativo,
            ed.data_inizio_esame,
            ed.data_fine_esame,
            ed.aula,
            ed.firmato AS firmato_any
        FROM corso_esami_date ed
        WHERE ed.id_corso = $corso_id
        ORDER BY ed.tentativo ASC
    ");
    if (!$esami) $esami = [];
}

// carico docenti del corso (per segreteria)
$docenti_corso = [];
if ($is_segreteria) {
    // se hai corso_docenti usalo, altrimenti fai fallback sul solo principale
    $docenti_corso = dbGetAll("
        SELECT cd.id_docente, d.cognome, d.nome, COALESCE(cd.principale,0) AS principale
        FROM corso_docenti cd
        JOIN docente d ON d.id = cd.id_docente
        WHERE cd.id_corso = $corso_id
        ORDER BY cd.principale DESC, d.cognome ASC, d.nome ASC
    ");
    if (!$docenti_corso) $docenti_corso = [];

    // fallback legacy: se non c'è corso_docenti, mostro almeno il principale
    if (count($docenti_corso) === 0 && $doc_principale > 0) {
        $one = dbGetFirst("SELECT id, cognome, nome FROM docente WHERE id=$doc_principale LIMIT 1");
        if ($one) {
            $docenti_corso = [[
                'id_docente' => intval($one['id']),
                'cognome' => $one['cognome'],
                'nome' => $one['nome'],
                'principale' => 1
            ]];
        }
    }
}

// ============================
// arricchisco esami con:
// - firmato_mio (docente)
// - firme (lista firme docenti per box)
// - docenti_firme (tabella segreteria)
// - campo firmato compatibile (docente: mio, segreteria: any)
// ============================
foreach ($esami as &$e) {
    $id_esame_data = intval($e['id']);
    $firmato_any = intval($e['firmato_any'] ?? 0);

    // firmato mio (docente)
    $firmato_mio = 0;
    if ($id_docente > 0) {
        $firmato_mio = intval(dbGetValue("
            SELECT EXISTS(
                SELECT 1 FROM corso_esami_date_firme
                WHERE id_esame_data = $id_esame_data AND id_docente = $id_docente
            )
        "));
    }

    // fallback legacy: firmato_any=1 ma nessuna firma registrata -> considero firmato_mio=1 per docente principale
    if ($id_docente > 0 && $firmato_mio === 0 && $firmato_any === 1) {
        if ($doc_principale === $id_docente) $firmato_mio = 1;
    }

    $e['firmato_mio'] = $firmato_mio;
    $e['firmato_any'] = $firmato_any;

    // compatibilità JS: campo "firmato"
    $e['firmato'] = ($id_docente > 0) ? $firmato_mio : $firmato_any;

    // elenco firme (per docente)
    $firme = dbGetAll("
        SELECT d.cognome, d.nome, f.firmato_il
        FROM corso_esami_date_firme f
        JOIN docente d ON d.id = f.id_docente
        WHERE f.id_esame_data = $id_esame_data
        ORDER BY d.cognome, d.nome
    ");
    if (!$firme) $firme = [];
    $e['firme'] = $firme;

    // tabella firme docenti (per segreteria)
    if ($is_segreteria) {
        $docenti_firme = [];

        foreach ($docenti_corso as $dc) {
            $did = intval($dc['id_docente']);
            $rowF = dbGetFirst("
                SELECT firmato_il
                FROM corso_esami_date_firme
                WHERE id_esame_data = $id_esame_data AND id_docente = $did
                LIMIT 1
            ");

            $docenti_firme[] = [
                'id_docente' => $did,
                'cognome' => $dc['cognome'],
                'nome' => $dc['nome'],
                'principale' => intval($dc['principale'] ?? 0),
                'firmato' => ($rowF && !empty($rowF['firmato_il'])) ? 1 : 0,
                'firmato_il' => ($rowF && !empty($rowF['firmato_il'])) ? $rowF['firmato_il'] : null,
            ];
        }

        $e['docenti_firme'] = $docenti_firme;
    }
}
unset($e);

// ============================
// Studenti + esiti per ciascun tentativo
// ============================
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
