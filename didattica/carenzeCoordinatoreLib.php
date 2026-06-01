<?php

function carenzeCoordH($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function carenzeCoordCurrentDocenteId()
{
    global $__docente_id, $__username;

    $id = intval($__docente_id ?? 0);
    if ($id > 0) {
        return $id;
    }

    $username = dbEscape($__username ?? '');
    if ($username === '') {
        return 0;
    }

    $row = dbGetFirst("SELECT id FROM docente WHERE username='$username' LIMIT 1");
    return $row ? intval($row['id']) : 0;
}

function carenzeCoordClassi(int $docenteId, int $annoId)
{
    if ($docenteId <= 0 || $annoId <= 0) {
        return [];
    }

    $rows = dbGetAll("
        SELECT c.id, c.classe
        FROM coordinatori coord
        INNER JOIN classi c ON c.id = coord.id_classe
        WHERE coord.id_docente = $docenteId
          AND coord.id_anno_scolastico = $annoId
        ORDER BY c.classe ASC
    ");

    return $rows ?: [];
}

function carenzeCoordDateLabel($value, $aula = '')
{
    if (empty($value)) {
        return '';
    }

    try {
        $label = (new DateTime((string)$value))->format('d/m/Y H:i');
        if ((string)$aula !== '') {
            $label .= ' - aula ' . (string)$aula;
        }
        return $label;
    } catch (Exception $e) {
        return (string)$value;
    }
}

function carenzeCoordExamPlain($exam)
{
    if (!$exam) {
        return 'Nessuna sessione di esame';
    }

    if (intval($exam['firmato'] ?? 0) !== 1) {
        return 'In attesa esito';
    }

    if (($exam['presente'] ?? null) === null) {
        return 'Esito non registrato';
    }

    $presente = intval($exam['presente']);
    $assenzaGiustificata = intval($exam['assenza_giustificata'] ?? 0);
    $recuperato = intval($exam['recuperato'] ?? 0);

    if ($presente === 0) {
        return $assenzaGiustificata === 1 ? 'Assente giustificato' : 'Assente';
    }

    return $recuperato === 1 ? 'Recuperato' : 'Non recuperato';
}

function carenzeCoordOutcome(array $row, int $annoCorsiId)
{
    $idStudente = intval($row['studente_id'] ?? 0);
    $idMateria = intval($row['materia_id'] ?? 0);

    $result = [
        'stato' => 'Nessun corso',
        'tentativo' => '',
        'dettaglio' => 'Nessun corso di recupero trovato',
        'classe_css' => 'warning',
        'primo' => '',
        'secondo' => '',
    ];

    if ($idStudente <= 0 || $idMateria <= 0 || $annoCorsiId <= 0) {
        return $result;
    }

    $idCorso1 = dbGetValue("
        SELECT co.id
        FROM corso co
        INNER JOIN corso_iscritti ci ON ci.id_corso = co.id
        WHERE ci.id_studente = $idStudente
          AND co.id_materia = $idMateria
          AND co.id_anno_scolastico = $annoCorsiId
          AND co.carenza = 1
          AND COALESCE(co.carenza_sessione, 1) = 1
        ORDER BY co.id DESC
        LIMIT 1
    ");

    if (!$idCorso1) {
        return $result;
    }

    $idCorso1 = intval($idCorso1);
    $primo = dbGetFirst("
        SELECT
            ced.id,
            ced.data_inizio_esame,
            ced.aula,
            ced.firmato,
            ce.presente,
            ce.recuperato,
            ce.assenza_giustificata
        FROM corso_esami_date ced
        LEFT JOIN corso_esiti ce
               ON ce.id_esame_data = ced.id
              AND ce.id_studente = $idStudente
        WHERE ced.id_corso = $idCorso1
          AND COALESCE(ced.tentativo, 1) = 1
        ORDER BY ced.data_inizio_esame ASC
        LIMIT 1
    ");

    $result['primo'] = carenzeCoordExamPlain($primo);
    if ($primo && !empty($primo['data_inizio_esame'])) {
        $result['primo'] .= ' (' . carenzeCoordDateLabel($primo['data_inizio_esame'], $primo['aula'] ?? '') . ')';
    }

    if ($primo && intval($primo['firmato'] ?? 0) === 1 && intval($primo['presente'] ?? 0) === 1 && intval($primo['recuperato'] ?? 0) === 1) {
        $result['stato'] = 'Recuperata';
        $result['tentativo'] = 'Primo';
        $result['dettaglio'] = 'Recuperata al primo tentativo';
        $result['classe_css'] = 'success';
        return $result;
    }

    $map = dbGetFirst("
        SELECT ccs.id_corso_secondo, co2.titolo
        FROM corso_carenze_seconda ccs
        INNER JOIN corso co2 ON co2.id = ccs.id_corso_secondo
        WHERE ccs.id_studente = $idStudente
          AND ccs.id_corso_primo = $idCorso1
        LIMIT 1
    ");

    if (!$map || intval($map['id_corso_secondo'] ?? 0) <= 0) {
        if (!$primo) {
            $result['stato'] = 'Da svolgere';
            $result['dettaglio'] = 'Nessuna sessione di esame';
            $result['classe_css'] = 'warning';
        } elseif (intval($primo['firmato'] ?? 0) !== 1) {
            $result['stato'] = 'In attesa';
            $result['dettaglio'] = 'Esito del primo tentativo non ancora firmato';
            $result['classe_css'] = 'info';
        } else {
            $result['stato'] = 'Non recuperata';
            $result['tentativo'] = 'Primo';
            $result['dettaglio'] = 'Non recuperata al primo tentativo';
            $result['classe_css'] = 'danger';
        }
        return $result;
    }

    $idCorso2 = intval($map['id_corso_secondo']);
    $secondo = dbGetFirst("
        SELECT
            ced.id,
            ced.data_inizio_esame,
            ced.aula,
            ced.firmato,
            ce.presente,
            ce.recuperato,
            ce.assenza_giustificata
        FROM corso_esami_date ced
        LEFT JOIN corso_esiti ce
               ON ce.id_esame_data = ced.id
              AND ce.id_studente = $idStudente
        WHERE ced.id_corso = $idCorso2
          AND COALESCE(ced.tentativo, 1) = 1
        ORDER BY ced.data_inizio_esame ASC
        LIMIT 1
    ");

    $result['secondo'] = carenzeCoordExamPlain($secondo);
    if ($secondo && !empty($secondo['data_inizio_esame'])) {
        $result['secondo'] .= ' (' . carenzeCoordDateLabel($secondo['data_inizio_esame'], $secondo['aula'] ?? '') . ')';
    }

    if (!$secondo || intval($secondo['firmato'] ?? 0) !== 1) {
        $result['stato'] = 'In attesa';
        $result['tentativo'] = 'Secondo';
        $result['dettaglio'] = 'Iscritta al secondo tentativo, esito non ancora firmato';
        $result['classe_css'] = 'info';
        return $result;
    }

    if (intval($secondo['presente'] ?? 0) === 1 && intval($secondo['recuperato'] ?? 0) === 1) {
        $result['stato'] = 'Recuperata';
        $result['tentativo'] = 'Secondo';
        $result['dettaglio'] = 'Recuperata al secondo tentativo';
        $result['classe_css'] = 'warning';
        return $result;
    }

    $result['stato'] = 'Non recuperata';
    $result['tentativo'] = 'Secondo';
    $result['dettaglio'] = 'Non recuperata al secondo tentativo';
    $result['classe_css'] = 'danger';
    return $result;
}

function carenzeCoordRows(int $docenteId, int $annoCoordinatoriId, int $anniFiltroId, int $classeFiltroId = 0, int $materiaFiltroId = 0, int $studenteFiltroId = 0, int $annoClasseFiltro = 0)
{
    global $__anno_scolastico_corrente_id;

    $classi = carenzeCoordClassi($docenteId, $annoCoordinatoriId);
    if (!$classi) {
        return [];
    }

    $classIds = array_map(function ($row) {
        return intval($row['id']);
    }, $classi);
    $classIds = array_values(array_filter($classIds));
    if (!$classIds) {
        return [];
    }

    $classIn = implode(',', $classIds);
    $whereAnno = $anniFiltroId > 0 ? " AND car.id_anno_scolastico = $anniFiltroId " : "";
    $whereClasse = $classeFiltroId > 0 ? " AND sf.id_classe = $classeFiltroId " : "";
    $whereMateria = $materiaFiltroId > 0 ? " AND car.id_materia = $materiaFiltroId " : "";
    $whereStudente = $studenteFiltroId > 0 ? " AND car.id_studente = $studenteFiltroId " : "";
    $whereAnnoClasse = $annoClasseFiltro > 0 ? " AND ccur.classe LIKE '" . intval($annoClasseFiltro) . "%' " : "";
    $annoCorrente = intval($__anno_scolastico_corrente_id);

    $rows = dbGetAll("
        SELECT
            car.id AS carenza_id,
            car.id_studente AS studente_id,
            car.id_materia AS materia_id,
            car.id_anno_scolastico,
            s.cognome AS stud_cognome,
            s.nome AS stud_nome,
            ccur.classe AS classe_attuale,
            cdeb.classe AS classe_carenza,
            m.nome AS materia,
            a.anno AS anno_scolastico,
            d.cognome AS doc_cognome,
            d.nome AS doc_nome
        FROM carenze car
        INNER JOIN studente s ON s.id = car.id_studente
        INNER JOIN studente_frequenta sf
                ON sf.id_studente = s.id
               AND sf.id_anno_scolastico = $annoCorrente
        INNER JOIN classi ccur ON ccur.id = sf.id_classe
        INNER JOIN classi cdeb ON cdeb.id = car.id_classe
        INNER JOIN materia m ON m.id = car.id_materia
        INNER JOIN anno_scolastico a ON a.id = car.id_anno_scolastico
        LEFT JOIN docente d ON d.id = car.id_docente
        WHERE sf.id_classe IN ($classIn)
          $whereAnno
          $whereClasse
          $whereMateria
          $whereStudente
          $whereAnnoClasse
        ORDER BY ccur.classe ASC, s.cognome ASC, s.nome ASC, m.nome ASC
    ");

    $rows = $rows ?: [];
    foreach ($rows as $idx => $row) {
        $rows[$idx]['esito'] = carenzeCoordOutcome($row, $annoCorrente);
    }

    return $rows;
}

function carenzeCoordClassLabel(int $docenteId, int $annoId)
{
    $classi = carenzeCoordClassi($docenteId, $annoId);
    if (!$classi) {
        return '';
    }

    $labels = [];
    foreach ($classi as $classe) {
        $labels[] = (string)$classe['classe'];
    }
    return implode(', ', $labels);
}
