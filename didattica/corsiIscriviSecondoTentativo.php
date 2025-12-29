<?php
/**
 * Secondo tentativo / recupero
 * - Aggancia a corso esistente (id_corso_secondo)
 * - Oppure crea/riusa un corso scegliendo un docente (new_docente_id)
 *
 * REGOLA:
 * - Se recupero_assenza=1 -> ammesso SOLO se allo svolgimento del tentativo=1 del corso1 lo studente è ASSENTE GIUSTIFICATO.
 *   In questo caso il corso creato/riusato è una "2ª sessione" tecnica (carenza_sessione=2) ma con titolo "Recupero carenze - recupero assenza".
 * - Se recupero_assenza=0 -> "2ª sessione normale": ammesso per:
 *      A) presente=1 e recuperato=0 (secondo tentativo)
 *      B) assente non giustificato (se vuoi mantenerlo)
 *
 * Salva:
 * - mapping corso1+studente -> corso2 in corso_carenze_seconda
 * - iscrizione in corso_iscritti sul corso2
 * - garantisce esame su corso2 (corso_esami_date tentativo=1)
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';

header('Content-Type: application/json; charset=utf-8');

$id_corso    = intval($_POST['id_corso'] ?? 0);        // corso1
$id_studente = intval($_POST['id_studente'] ?? 0);

$id_corso_secondo = intval($_POST['id_corso_secondo'] ?? 0);
$new_docente_id   = intval($_POST['new_docente_id'] ?? 0);
$new_titolo       = trim($_POST['new_titolo'] ?? '');

// ✅ nuovo: distingue recupero assenza vs seconda sessione "normale"
$recupero_assenza = intval($_POST['recupero_assenza'] ?? 0); // 0/1

try {
    if ($id_corso <= 0 || $id_studente <= 0) {
        echo json_encode(['success' => false, 'error' => 'Parametri mancanti']);
        exit;
    }

    mysqli_begin_transaction($__con);

    // 1) Carico corso1
    $corso1 = dbGetFirst("SELECT * FROM corso WHERE id = $id_corso LIMIT 1");
    if (!$corso1) {
        mysqli_rollback($__con);
        echo json_encode(['success' => false, 'error' => 'Corso non trovato']);
        exit;
    }
    if (intval($corso1['carenza']) !== 1) {
        mysqli_rollback($__con);
        echo json_encode(['success' => false, 'error' => 'Il corso non è un corso carenze']);
        exit;
    }

    $sessione1 = isset($corso1['carenza_sessione']) ? intval($corso1['carenza_sessione']) : 1;
    if ($sessione1 !== 1) {
        mysqli_rollback($__con);
        echo json_encode(['success' => false, 'error' => 'Operazione ammessa solo su corsi carenze di 1ª sessione']);
        exit;
    }

    // 2) Verifica studente iscritto al corso1
    $iscritto1 = dbGetValue("SELECT COUNT(*) FROM corso_iscritti WHERE id_corso=$id_corso AND id_studente=$id_studente");
    if (intval($iscritto1) <= 0) {
        mysqli_rollback($__con);
        echo json_encode(['success' => false, 'error' => 'Studente non iscritto al corso']);
        exit;
    }

    // 3) Recupero id esame tentativo 1 del corso1
    $id_esame1 = dbGetValue("
        SELECT id
        FROM corso_esami_date
        WHERE id_corso = $id_corso AND tentativo = 1
        LIMIT 1
    ");

    if (!$id_esame1) {
        mysqli_rollback($__con);
        echo json_encode(['success' => false, 'error' => 'Esame del 1° tentativo non presente sul corso']);
        exit;
    }

    // 4) Esito studente su tentativo 1
    $esito1 = dbGetFirst("
        SELECT presente, recuperato, assenza_giustificata
        FROM corso_esiti
        WHERE id_corso = $id_corso
          AND id_studente = $id_studente
          AND id_esame_data = $id_esame1
        LIMIT 1
    ");

    if (!$esito1) {
        mysqli_rollback($__con);
        echo json_encode(['success' => false, 'error' => 'Esito 1° tentativo non trovato per lo studente']);
        exit;
    }

    $presente = intval($esito1['presente'] ?? 0);
    $recuperato = intval($esito1['recuperato'] ?? 0);
    $ass_giust = intval($esito1['assenza_giustificata'] ?? 0);

    // 5) Validazione in base a recupero_assenza
    if ($recupero_assenza === 1) {
        // SOLO assente giustificato
        if (!($presente === 0 && $ass_giust === 1)) {
            mysqli_rollback($__con);
            echo json_encode([
                'success' => false,
                'error' => 'Recupero assenza consentito solo se lo studente risulta assente GIUSTIFICATO al 1° tentativo'
            ]);
            exit;
        }
    } else {
        // Seconda sessione "normale": presente ma non recuperato, oppure assente NON giustificato (se mantenuto)
        $ok_seconda_normale = ($presente === 1 && $recuperato === 0) || ($presente === 0 && $ass_giust === 0);
        if (!$ok_seconda_normale) {
            mysqli_rollback($__con);
            echo json_encode([
                'success' => false,
                'error' => 'Seconda sessione consentita solo se presente e non recuperato, oppure assente non giustificato'
            ]);
            exit;
        }
    }

    // 6) Idempotenza: se già c'è mapping, ritorno ok
    $mapp = dbGetFirst("
        SELECT id_corso_secondo
        FROM corso_carenze_seconda
        WHERE id_corso_primo=$id_corso AND id_studente=$id_studente
        LIMIT 1
    ");
    if ($mapp && intval($mapp['id_corso_secondo']) > 0) {
        mysqli_commit($__con);
        echo json_encode([
            'success' => true,
            'id_corso_secondo' => intval($mapp['id_corso_secondo']),
            'msg' => ($recupero_assenza === 1 ? 'Studente già assegnato al recupero assenza' : 'Studente già assegnato alla 2ª sessione')
        ]);
        exit;
    }

    // 7) Deve arrivare una scelta: o corso2 esistente o nuovo docente
    if ($id_corso_secondo <= 0 && $new_docente_id <= 0) {
        mysqli_rollback($__con);
        echo json_encode(['success' => false, 'error' => 'Seleziona un corso esistente oppure un docente per crearne uno nuovo']);
        exit;
    }

    if ($id_corso_secondo > 0 && $id_corso_secondo == $id_corso) {
        mysqli_rollback($__con);
        echo json_encode(['success' => false, 'error' => 'Il corso di destinazione non può coincidere con il corso di partenza']);
        exit;
    }

    $id_anno    = intval($corso1['id_anno_scolastico']);
    $id_materia = intval($corso1['id_materia']);

    // 8) Determino/creo corso2
    if ($id_corso_secondo > 0) {
        $corso2 = dbGetFirst("SELECT * FROM corso WHERE id=$id_corso_secondo LIMIT 1");
        if (!$corso2) {
            mysqli_rollback($__con);
            echo json_encode(['success' => false, 'error' => 'Corso selezionato non trovato']);
            exit;
        }

        // deve essere sempre una 2ª sessione tecnica (carenza_sessione=2)
        if (intval($corso2['carenza']) !== 1 || intval($corso2['carenza_sessione']) !== 2) {
            mysqli_rollback($__con);
            echo json_encode(['success' => false, 'error' => 'Il corso selezionato non è una 2ª sessione carenze']);
            exit;
        }

        if (intval($corso2['id_materia']) !== $id_materia || intval($corso2['id_anno_scolastico']) !== $id_anno) {
            mysqli_rollback($__con);
            echo json_encode(['success' => false, 'error' => 'Il corso deve avere stessa materia e stesso anno del corso di partenza']);
            exit;
        }

        // Se è recupero assenza e voglio "aggiustare" il titolo del corso selezionato, fallo solo se vuoto o standard
        if ($recupero_assenza === 1) {
            $titolo_att = trim($corso2['titolo'] ?? '');
            if ($new_titolo === '') $new_titolo = "Recupero carenze - recupero assenza";
            // aggiorno solo se è il titolo standard "2ª sessione" o vuoto (evito di sovrascrivere titoli personalizzati)
            if ($titolo_att === '' || stripos($titolo_att, '2ª sessione') !== false) {
                $titolo_esc = mysqli_real_escape_string($__con, $new_titolo);
                mysqli_query($__con, "UPDATE corso SET titolo='$titolo_esc' WHERE id=" . intval($corso2['id']) . " LIMIT 1");
            }
        }

        $id_corso2 = $id_corso_secondo;

    } else {
        $doc = dbGetFirst("SELECT id FROM docente WHERE id=$new_docente_id LIMIT 1");
        if (!$doc) {
            mysqli_rollback($__con);
            echo json_encode(['success' => false, 'error' => 'Docente non valido']);
            exit;
        }

        // ✅ titolo predefinito diverso a seconda del caso
        if ($new_titolo === '') {
            $new_titolo = ($recupero_assenza === 1)
                ? "Recupero carenze - recupero assenza"
                : "Recupero carenze - 2ª sessione";
        }
        $titolo_esc = mysqli_real_escape_string($__con, $new_titolo);

        // ✅ RIUSO: per evitare collisioni tra "2ª sessione" e "recupero assenza",
        // cerco un corso esistente con titolo coerente col flag.
        $like = ($recupero_assenza === 1)
            ? "%recupero assenza%"
            : "%2ª sessione%";

        $like_esc = mysqli_real_escape_string($__con, $like);

        $existing = dbGetFirst("
            SELECT id
            FROM corso
            WHERE id_anno_scolastico=$id_anno
              AND id_materia=$id_materia
              AND id_docente=$new_docente_id
              AND carenza=1
              AND carenza_sessione=2
              AND titolo LIKE '$like_esc'
            LIMIT 1
        ");

        if ($existing && intval($existing['id']) > 0) {
            $id_corso2 = intval($existing['id']);
        } else {
            $qIns = "
                INSERT INTO corso (id_materia,id_docente,id_anno_scolastico,titolo,carenza,carenza_sessione,in_itinere)
                VALUES ($id_materia,$new_docente_id,$id_anno,'$titolo_esc',1,2,0)
            ";
            $ok = mysqli_query($__con, $qIns);
            if (!$ok) {
                throw new Exception("Errore creazione corso: " . mysqli_error($__con));
            }
            $id_corso2 = intval(mysqli_insert_id($__con));
            if ($id_corso2 <= 0) throw new Exception("Impossibile ottenere ID corso creato");
        }
    }

    // 9) Garantisco esame su corso2 (tentativo=1)
    $esame_id = dbGetValue("
        SELECT id
        FROM corso_esami_date
        WHERE id_corso=$id_corso2 AND tentativo=1
        LIMIT 1
    ");
    if (!$esame_id) {
        $now = date('Y-m-d H:i:s');
        $qInsEsame = "
            INSERT INTO corso_esami_date (id_corso,data_inizio_esame,data_fine_esame,aula,firmato,tentativo)
            VALUES ($id_corso2,'$now','$now',NULL,0,1)
        ";
        $ok = mysqli_query($__con, $qInsEsame);
        if (!$ok) {
            throw new Exception("Errore creazione esame corso2: " . mysqli_error($__con));
        }
        $esame_id = intval(mysqli_insert_id($__con));
    }

    // 10) Iscrivo studente al corso2 (evito duplicati)
    $cntIscr2 = dbGetValue("SELECT COUNT(*) FROM corso_iscritti WHERE id_corso=$id_corso2 AND id_studente=$id_studente");
    if (intval($cntIscr2) <= 0) {
        $qInsIscr = "INSERT INTO corso_iscritti (id_studente,id_corso) VALUES ($id_studente,$id_corso2)";
        $ok = mysqli_query($__con, $qInsIscr);
        if (!$ok) {
            throw new Exception("Errore iscrizione studente al corso2: " . mysqli_error($__con));
        }
    }

    // 11) Mapping corso1->corso2
    $qMap = "INSERT INTO corso_carenze_seconda (id_corso_primo,id_studente,id_corso_secondo)
             VALUES ($id_corso,$id_studente,$id_corso2)";
    $ok = mysqli_query($__con, $qMap);
    if (!$ok) {
        $err = mysqli_error($__con);
        if (stripos($err, 'Duplicate') === false) {
            throw new Exception("Errore salvataggio mapping: " . $err);
        }
        // duplicate ok
    }

    mysqli_commit($__con);
    echo json_encode([
        'success' => true,
        'id_corso_secondo' => $id_corso2,
        'recupero_assenza' => $recupero_assenza
    ]);

} catch (Exception $e) {
    if (isset($__con)) @mysqli_rollback($__con);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
