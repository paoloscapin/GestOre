<?php

/**
 * Pratiche entrata/uscita studenti.
 *
 *  This file is part of GestOre
 *  @author     OpenAI Codex
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/mastercom/tabelloni_lib.php';
require_once __DIR__ . '/scuoleIstitutiLib.php';

function studentiMovimentiColumnExists(string $table, string $column): bool
{
    $row = dbGetFirst("SHOW COLUMNS FROM `$table` LIKE " . dbQ($column));
    return $row !== null;
}

function studentiMovimentiEnsureTables(): void
{
    scuoleIstitutiEnsureTable();

    dbExec("
        CREATE TABLE IF NOT EXISTS `studenti_movimenti_pratiche` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `tipo_pratica` ENUM('bocciato_reiscrizione','uscita','ritiro','entrata') NOT NULL,
            `stato_pratica` VARCHAR(60) NOT NULL DEFAULT 'da_verificare',
            `id_studente` INT NULL,
            `cognome` VARCHAR(120) NULL,
            `nome` VARCHAR(120) NULL,
            `codice_fiscale` VARCHAR(20) NULL,
            `classe_origine` VARCHAR(80) NULL,
            `classe_richiesta` VARCHAR(80) NULL,
            `anno_corso` TINYINT NULL,
            `id_istituto_destinazione` INT NULL,
            `scuola_destinazione` VARCHAR(255) NULL,
            `indirizzo_destinazione` VARCHAR(255) NULL,
            `id_istituto_provenienza` INT NULL,
            `scuola_provenienza` VARCHAR(255) NULL,
            `indirizzo_provenienza` VARCHAR(255) NULL,
            `esami_integrativi` TINYINT(1) NOT NULL DEFAULT 0,
            `fonte` VARCHAR(40) NOT NULL DEFAULT 'manuale',
            `id_pratica_iscrizione` INT NULL,
            `id_cambio_scuola_iscrizione` INT NULL,
            `note` TEXT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_movimenti_tipo_stato` (`tipo_pratica`, `stato_pratica`),
            KEY `idx_movimenti_studente` (`id_studente`),
            KEY `idx_movimenti_cf` (`codice_fiscale`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");

    if (!studentiMovimentiColumnExists('studenti_movimenti_pratiche', 'anno_corso')) {
        dbExec("ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `anno_corso` TINYINT NULL AFTER `classe_richiesta`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_pratiche', 'fonte')) {
        dbExec("ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `fonte` VARCHAR(40) NOT NULL DEFAULT 'manuale' AFTER `esami_integrativi`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_pratiche', 'id_pratica_iscrizione')) {
        dbExec("ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `id_pratica_iscrizione` INT NULL AFTER `fonte`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_pratiche', 'id_cambio_scuola_iscrizione')) {
        dbExec("ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `id_cambio_scuola_iscrizione` INT NULL AFTER `id_pratica_iscrizione`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_pratiche', 'id_istituto_destinazione')) {
        dbExec("ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `id_istituto_destinazione` INT NULL AFTER `anno_corso`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_pratiche', 'indirizzo_destinazione')) {
        dbExec("ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `indirizzo_destinazione` VARCHAR(255) NULL AFTER `scuola_destinazione`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_pratiche', 'id_istituto_provenienza')) {
        dbExec("ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `id_istituto_provenienza` INT NULL AFTER `indirizzo_destinazione`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_pratiche', 'indirizzo_provenienza')) {
        dbExec("ALTER TABLE studenti_movimenti_pratiche ADD COLUMN `indirizzo_provenienza` VARCHAR(255) NULL AFTER `scuola_provenienza`");
    }

    dbExec("
        CREATE TABLE IF NOT EXISTS `studenti_movimenti_allegati` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `id_pratica` INT NOT NULL,
            `tipo_allegato` VARCHAR(80) NOT NULL DEFAULT 'documento',
            `nome_file` VARCHAR(255) NOT NULL,
            `path_file` VARCHAR(500) NOT NULL,
            `mime_type` VARCHAR(120) NULL,
            `dimensione` INT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_movimenti_allegati_pratica` (`id_pratica`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");

    dbExec("
        CREATE TABLE IF NOT EXISTS `studenti_movimenti_eventi` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `id_pratica` INT NOT NULL,
            `tipo_evento` VARCHAR(80) NOT NULL DEFAULT 'salvataggio',
            `descrizione` VARCHAR(255) NULL,
            `stato_pratica` VARCHAR(60) NULL,
            `tipo_pratica` VARCHAR(60) NULL,
            `id_istituto_destinazione` INT NULL,
            `scuola_destinazione` VARCHAR(255) NULL,
            `indirizzo_destinazione` VARCHAR(255) NULL,
            `id_istituto_provenienza` INT NULL,
            `scuola_provenienza` VARCHAR(255) NULL,
            `indirizzo_provenienza` VARCHAR(255) NULL,
            `note` TEXT NULL,
            `created_by` VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_movimenti_eventi_pratica` (`id_pratica`),
            KEY `idx_movimenti_eventi_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");
    if (!studentiMovimentiColumnExists('studenti_movimenti_eventi', 'id_istituto_destinazione')) {
        dbExec("ALTER TABLE studenti_movimenti_eventi ADD COLUMN `id_istituto_destinazione` INT NULL AFTER `tipo_pratica`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_eventi', 'indirizzo_destinazione')) {
        dbExec("ALTER TABLE studenti_movimenti_eventi ADD COLUMN `indirizzo_destinazione` VARCHAR(255) NULL AFTER `scuola_destinazione`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_eventi', 'id_istituto_provenienza')) {
        dbExec("ALTER TABLE studenti_movimenti_eventi ADD COLUMN `id_istituto_provenienza` INT NULL AFTER `indirizzo_destinazione`");
    }
    if (!studentiMovimentiColumnExists('studenti_movimenti_eventi', 'indirizzo_provenienza')) {
        dbExec("ALTER TABLE studenti_movimenti_eventi ADD COLUMN `indirizzo_provenienza` VARCHAR(255) NULL AFTER `scuola_provenienza`");
    }
}

function studentiMovimentiTipi(): array
{
    return [
        'bocciato_reiscrizione' => 'Bocciato - reiscrizione',
        'uscita' => 'Cambio scuola / nulla osta',
        'ritiro' => 'Ritiro',
        'entrata' => 'Nuovo studente in entrata',
    ];
}

function studentiMovimentiStati(): array
{
    return [
        'da_verificare' => 'Da verificare',
        'reiscrizione_confermata' => 'Reiscrizione confermata',
        'cambia_scuola' => 'Cambia scuola',
        'si_ritira' => 'Si ritira',
        'richiesta_nulla_osta' => 'Richiesta nulla osta ricevuta',
        'firmato_un_genitore' => 'Firmato da un responsabile',
        'firmato_entrambi' => 'Firmato da entrambi i responsabili',
        'colloquio_uscita' => 'Colloquio uscita svolto',
        'nulla_osta_inviato' => 'Nulla osta inviato',
        'contatto_ricevuto' => 'Contatto ricevuto',
        'documenti_in_verifica' => 'Documenti in verifica',
        'esami_integrativi' => 'Esami integrativi da svolgere',
        'idoneo_iscrizione' => 'Idoneo all iscrizione',
        'non_idoneo' => 'Non idoneo',
        'chiusa' => 'Chiusa',
        'annullata' => 'Annullata',
    ];
}

function studentiMovimentiH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function studentiMovimentiCurrentActor(): string
{
    $name = trim((string)($GLOBALS['__utente_nome'] ?? '') . ' ' . (string)($GLOBALS['__utente_cognome'] ?? ''));
    if ($name !== '') {
        return $name;
    }
    return trim((string)($GLOBALS['__useremail'] ?? $GLOBALS['__username'] ?? ''));
}

function studentiMovimentiUploadDir(int $practiceId): string
{
    return dirname(__DIR__) . '/data/movimenti_studenti/' . $practiceId;
}

function studentiMovimentiPublicPath(string $absolutePath): string
{
    $base = str_replace('\\', '/', dirname(__DIR__));
    $path = str_replace('\\', '/', $absolutePath);
    if (strpos($path, $base . '/') === 0) {
        return '../' . substr($path, strlen($base) + 1);
    }
    return $absolutePath;
}

function studentiMovimentiSavePractice(array $data): int
{
    studentiMovimentiEnsureTables();

    $id = intval($data['id'] ?? 0);
    $createdBy = studentiMovimentiCurrentActor();
    $fields = [
        'tipo_pratica' => trim((string)($data['tipo_pratica'] ?? 'entrata')),
        'stato_pratica' => trim((string)($data['stato_pratica'] ?? 'da_verificare')),
        'id_studente' => intval($data['id_studente'] ?? 0) ?: null,
        'cognome' => trim((string)($data['cognome'] ?? '')),
        'nome' => trim((string)($data['nome'] ?? '')),
        'codice_fiscale' => strtoupper(trim((string)($data['codice_fiscale'] ?? ''))),
        'classe_origine' => trim((string)($data['classe_origine'] ?? '')),
        'classe_richiesta' => trim((string)($data['classe_richiesta'] ?? '')),
        'anno_corso' => intval($data['anno_corso'] ?? 0) ?: null,
        'id_istituto_destinazione' => intval($data['id_istituto_destinazione'] ?? 0) ?: null,
        'scuola_destinazione' => trim((string)($data['scuola_destinazione'] ?? '')),
        'indirizzo_destinazione' => trim((string)($data['indirizzo_destinazione'] ?? '')),
        'id_istituto_provenienza' => intval($data['id_istituto_provenienza'] ?? 0) ?: null,
        'scuola_provenienza' => trim((string)($data['scuola_provenienza'] ?? '')),
        'indirizzo_provenienza' => trim((string)($data['indirizzo_provenienza'] ?? '')),
        'esami_integrativi' => !empty($data['esami_integrativi']) ? 1 : 0,
        'fonte' => trim((string)($data['fonte'] ?? 'manuale')),
        'id_pratica_iscrizione' => intval($data['id_pratica_iscrizione'] ?? 0) ?: null,
        'id_cambio_scuola_iscrizione' => intval($data['id_cambio_scuola_iscrizione'] ?? 0) ?: null,
        'note' => trim((string)($data['note'] ?? '')),
    ];
    $nomeDestinazione = scuoleIstitutiNameById($fields['id_istituto_destinazione']);
    if ($nomeDestinazione !== '') {
        $fields['scuola_destinazione'] = $nomeDestinazione;
    }
    $nomeProvenienza = scuoleIstitutiNameById($fields['id_istituto_provenienza']);
    if ($nomeProvenienza !== '') {
        $fields['scuola_provenienza'] = $nomeProvenienza;
    }

    if ($fields['id_studente']) {
        $student = dbGetFirst("SELECT cognome, nome, codice_fiscale FROM studente WHERE id = " . dbI($fields['id_studente']) . " LIMIT 1");
        if ($student) {
            $fields['cognome'] = $fields['cognome'] !== '' ? $fields['cognome'] : (string)($student['cognome'] ?? '');
            $fields['nome'] = $fields['nome'] !== '' ? $fields['nome'] : (string)($student['nome'] ?? '');
            $fields['codice_fiscale'] = $fields['codice_fiscale'] !== '' ? $fields['codice_fiscale'] : (string)($student['codice_fiscale'] ?? '');
        }
    }

    if ($id > 0) {
        dbExec("
            UPDATE studenti_movimenti_pratiche
            SET tipo_pratica = " . dbQ($fields['tipo_pratica']) . ",
                stato_pratica = " . dbQ($fields['stato_pratica']) . ",
                id_studente = " . dbI($fields['id_studente']) . ",
                cognome = " . dbQ($fields['cognome']) . ",
                nome = " . dbQ($fields['nome']) . ",
                codice_fiscale = " . dbQ($fields['codice_fiscale']) . ",
                classe_origine = " . dbQ($fields['classe_origine']) . ",
                classe_richiesta = " . dbQ($fields['classe_richiesta']) . ",
                anno_corso = " . dbI($fields['anno_corso']) . ",
                id_istituto_destinazione = " . dbI($fields['id_istituto_destinazione']) . ",
                scuola_destinazione = " . dbQ($fields['scuola_destinazione']) . ",
                indirizzo_destinazione = " . dbQ($fields['indirizzo_destinazione']) . ",
                id_istituto_provenienza = " . dbI($fields['id_istituto_provenienza']) . ",
                scuola_provenienza = " . dbQ($fields['scuola_provenienza']) . ",
                indirizzo_provenienza = " . dbQ($fields['indirizzo_provenienza']) . ",
                esami_integrativi = " . dbI($fields['esami_integrativi']) . ",
                fonte = " . dbQ($fields['fonte']) . ",
                id_pratica_iscrizione = " . dbI($fields['id_pratica_iscrizione']) . ",
                id_cambio_scuola_iscrizione = " . dbI($fields['id_cambio_scuola_iscrizione']) . ",
                note = " . dbQ($fields['note']) . ",
                updated_at = NOW()
            WHERE id = " . dbI($id) . "
            LIMIT 1
        ");
        studentiMovimentiAddEvent($id, 'salvataggio', 'Pratica aggiornata', $fields, $createdBy);
        return $id;
    }

    dbExec("
        INSERT INTO studenti_movimenti_pratiche (
            tipo_pratica, stato_pratica, id_studente, cognome, nome, codice_fiscale,
            classe_origine, classe_richiesta, anno_corso,
            id_istituto_destinazione, scuola_destinazione, indirizzo_destinazione,
            id_istituto_provenienza, scuola_provenienza, indirizzo_provenienza,
            esami_integrativi, fonte, id_pratica_iscrizione, id_cambio_scuola_iscrizione, note, created_at, updated_at
        ) VALUES (
            " . dbQ($fields['tipo_pratica']) . ",
            " . dbQ($fields['stato_pratica']) . ",
            " . dbI($fields['id_studente']) . ",
            " . dbQ($fields['cognome']) . ",
            " . dbQ($fields['nome']) . ",
            " . dbQ($fields['codice_fiscale']) . ",
            " . dbQ($fields['classe_origine']) . ",
            " . dbQ($fields['classe_richiesta']) . ",
            " . dbI($fields['anno_corso']) . ",
            " . dbI($fields['id_istituto_destinazione']) . ",
            " . dbQ($fields['scuola_destinazione']) . ",
            " . dbQ($fields['indirizzo_destinazione']) . ",
            " . dbI($fields['id_istituto_provenienza']) . ",
            " . dbQ($fields['scuola_provenienza']) . ",
            " . dbQ($fields['indirizzo_provenienza']) . ",
            " . dbI($fields['esami_integrativi']) . ",
            " . dbQ($fields['fonte']) . ",
            " . dbI($fields['id_pratica_iscrizione']) . ",
            " . dbI($fields['id_cambio_scuola_iscrizione']) . ",
            " . dbQ($fields['note']) . ",
            NOW(),
            NOW()
        )
    ");
    $newId = intval(dblastId());
    studentiMovimentiAddEvent($newId, 'creazione', 'Pratica creata', $fields, $createdBy);
    return $newId;
}

function studentiMovimentiAddEvent(int $practiceId, string $type, string $description, array $fields = [], string $createdBy = ''): void
{
    if ($practiceId <= 0) {
        return;
    }
    dbExec("
        INSERT INTO studenti_movimenti_eventi (
            id_pratica, tipo_evento, descrizione, stato_pratica, tipo_pratica,
            id_istituto_destinazione, scuola_destinazione, indirizzo_destinazione,
            id_istituto_provenienza, scuola_provenienza, indirizzo_provenienza,
            note, created_by, created_at
        ) VALUES (
            " . dbI($practiceId) . ",
            " . dbQ($type) . ",
            " . dbQ($description) . ",
            " . dbQ($fields['stato_pratica'] ?? null) . ",
            " . dbQ($fields['tipo_pratica'] ?? null) . ",
            " . dbI($fields['id_istituto_destinazione'] ?? null) . ",
            " . dbQ($fields['scuola_destinazione'] ?? null) . ",
            " . dbQ($fields['indirizzo_destinazione'] ?? null) . ",
            " . dbI($fields['id_istituto_provenienza'] ?? null) . ",
            " . dbQ($fields['scuola_provenienza'] ?? null) . ",
            " . dbQ($fields['indirizzo_provenienza'] ?? null) . ",
            " . dbQ($fields['note'] ?? null) . ",
            " . dbQ($createdBy) . ",
            NOW()
        )
    ");
}

function studentiMovimentiHistoryForPractices(array $practiceIds): array
{
    $ids = array_values(array_filter(array_map('intval', $practiceIds), static fn($id) => $id > 0));
    if (!$ids) {
        return [];
    }
    $rows = dbGetAll("
        SELECT *
        FROM studenti_movimenti_eventi
        WHERE id_pratica IN (" . implode(',', $ids) . ")
        ORDER BY created_at DESC, id DESC
    ") ?: [];
    $history = [];
    foreach ($rows as $row) {
        $history[intval($row['id_pratica'] ?? 0)][] = $row;
    }
    return $history;
}

function studentiMovimentiCurrentYearId(): int
{
    global $__anno_scolastico_corrente_id;
    return intval($__anno_scolastico_corrente_id ?? 0);
}

function studentiMovimentiClassYear(string $classLabel): ?int
{
    $year = mastercomTabelloniClassYearFromName($classLabel);
    return $year > 0 ? $year : null;
}

function studentiMovimentiSyncBocciatiFromTabelloni(int $schoolYearId): array
{
    studentiMovimentiEnsureTables();
    mastercomTabelloniEnsureTables();
    mastercomTabelloniRefreshDerivedFields();

    $rows = dbGetAll("
        SELECT
            t.classe,
            t.classe_tabellone,
            s.id_studente_gestore,
            s.studente_nome,
            s.esito_key,
            st.cognome,
            st.nome,
            st.codice_fiscale,
            cls_summary.classe AS classe_gestore_studente,
            mcls_summary.nome AS classe_mastercom_studente
        FROM mastercom_tabelloni_scrutini t
        INNER JOIN mastercom_tabelloni_scrutini_studenti s ON s.tabellone_id = t.id
        LEFT JOIN studente st ON st.id = s.id_studente_gestore
        LEFT JOIN studente_frequenta sf_summary ON sf_summary.id = (
            SELECT sf2.id
            FROM studente_frequenta sf2
            WHERE sf2.id_studente = s.id_studente_gestore
              AND sf2.id_anno_scolastico = t.id_anno_scolastico
            ORDER BY sf2.id DESC
            LIMIT 1
        )
        LEFT JOIN classi cls_summary ON cls_summary.id = sf_summary.id_classe
        LEFT JOIN mastercom_studenti mstu_summary ON mstu_summary.id = (
            SELECT ms2.id
            FROM mastercom_studenti ms2
            WHERE ms2.mastercom_id_studente = s.mastercom_id_studente
            ORDER BY ms2.id DESC
            LIMIT 1
        )
        LEFT JOIN mastercom_classi mcls_summary ON mcls_summary.mastercom_id_classe = mstu_summary.mastercom_id_classe_corrente
        WHERE t.id_anno_scolastico = " . dbI($schoolYearId) . "
          AND t.periodo = '9'
          AND s.esito_key IN ('non_ammesso', 'in_corso')
        GROUP BY t.id, t.classe, t.classe_tabellone, s.id_studente_gestore, s.studente_nome, s.esito_key,
                 st.cognome, st.nome, st.codice_fiscale, cls_summary.classe, mcls_summary.nome
        ORDER BY t.classe ASC, st.cognome ASC, st.nome ASC, s.studente_nome ASC
    ") ?: [];

    $created = 0;
    $existing = 0;
    $updatedExisting = 0;
    $withoutGestoreId = 0;
    $withoutGestoreExamples = [];
    $byYear = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 0 => 0];
    foreach ($rows as $row) {
        $classLabel = mastercomTabelloniSummaryEffectiveClassLabel($row);
        $annoCorso = studentiMovimentiClassYear($classLabel);
        $yearKey = ($annoCorso !== null && $annoCorso >= 1 && $annoCorso <= 5) ? $annoCorso : 0;
        $byYear[$yearKey]++;

        $studentId = intval($row['id_studente_gestore'] ?? 0);
        if ($studentId <= 0) {
            $withoutGestoreId++;
            if (count($withoutGestoreExamples) < 20) {
                $withoutGestoreExamples[] = trim((string)($row['studente_nome'] ?? '') . ' - ' . $classLabel);
            }
            continue;
        }
        $already = dbGetFirst("
            SELECT id, anno_corso, classe_origine, cognome, nome, codice_fiscale
            FROM studenti_movimenti_pratiche
            WHERE id_studente = " . dbI($studentId) . "
              AND tipo_pratica IN ('bocciato_reiscrizione','uscita','ritiro')
              AND stato_pratica <> 'annullata'
            LIMIT 1
        ");
        if ($already !== null) {
            $updates = [];
            if (intval($already['anno_corso'] ?? 0) <= 0 && $annoCorso !== null) {
                $updates[] = "anno_corso = " . dbI($annoCorso);
            }
            if (trim((string)($already['classe_origine'] ?? '')) === '' && $classLabel !== '') {
                $updates[] = "classe_origine = " . dbQ($classLabel);
            }
            if (trim((string)($already['cognome'] ?? '')) === '' && trim((string)($row['cognome'] ?? '')) !== '') {
                $updates[] = "cognome = " . dbQ($row['cognome']);
            }
            if (trim((string)($already['nome'] ?? '')) === '' && trim((string)($row['nome'] ?? '')) !== '') {
                $updates[] = "nome = " . dbQ($row['nome']);
            }
            if (trim((string)($already['codice_fiscale'] ?? '')) === '' && trim((string)($row['codice_fiscale'] ?? '')) !== '') {
                $updates[] = "codice_fiscale = " . dbQ($row['codice_fiscale']);
            }
            if (!empty($updates)) {
                $updates[] = "updated_at = NOW()";
                dbExec("
                    UPDATE studenti_movimenti_pratiche
                    SET " . implode(", ", $updates) . "
                    WHERE id = " . dbI($already['id']) . "
                    LIMIT 1
                ");
                $updatedExisting++;
            }
            $existing++;
            continue;
        }

        studentiMovimentiSavePractice([
            'tipo_pratica' => 'bocciato_reiscrizione',
            'stato_pratica' => 'da_verificare',
            'id_studente' => $studentId,
            'cognome' => $row['cognome'] ?? '',
            'nome' => $row['nome'] ?? '',
            'codice_fiscale' => $row['codice_fiscale'] ?? '',
            'classe_origine' => $classLabel,
            'anno_corso' => $annoCorso,
            'fonte' => 'tabelloni',
            'note' => 'Pratica creata automaticamente da tabellone finale: studente non ammesso o con esito in corso.',
        ]);
        $created++;
    }

    return [
        'read' => count($rows),
        'created' => $created,
        'existing' => $existing,
        'updated_existing' => $updatedExisting,
        'without_gestore_id' => $withoutGestoreId,
        'without_gestore_examples' => $withoutGestoreExamples,
        'by_year' => $byYear,
    ];
}

function studentiMovimentiSyncCambioScuolaDaIscrizioni(): array
{
    studentiMovimentiEnsureTables();
    require_once __DIR__ . '/iscrizioniPrimeLib.php';
    iscrizioniPrimeEnsureSchema();

    $rows = dbGetAll("
        SELECT
            p.id AS pratica_iscrizione_id,
            p.tipo_iscrizione,
            p.cognome,
            p.nome,
            p.codice_fiscale,
            p.corso_studi,
            p.studente_interno,
            c.id AS cambio_id,
            c.richiesta_data,
            c.canale,
            c.id_istituto_destinazione,
            c.scuola_destinazione,
            c.indirizzo_destinazione,
            c.colloquio_stato,
            c.nulla_osta_stato,
            c.documenti_stato,
            c.pratica_stato,
            c.note,
            s.id AS id_studente,
            cls.classe AS classe_corrente
        FROM iscrizioni_prime_pratiche p
        INNER JOIN iscrizioni_prime_cambio_scuola c ON c.pratica_id = p.id
        LEFT JOIN studente s ON UPPER(TRIM(s.codice_fiscale)) = UPPER(TRIM(p.codice_fiscale))
          AND COALESCE(s.attivo, 1) = 1
        LEFT JOIN studente_frequenta sf ON sf.id = (
            SELECT sf2.id
            FROM studente_frequenta sf2
            WHERE sf2.id_studente = s.id
              AND sf2.id_anno_scolastico = " . dbI(studentiMovimentiCurrentYearId()) . "
            ORDER BY sf2.id DESC
            LIMIT 1
        )
        LEFT JOIN classi cls ON cls.id = sf.id_classe
        WHERE p.stato = 'annullata'
          AND p.tipo_iscrizione IN ('prime','terze')
        ORDER BY p.cognome ASC, p.nome ASC
    ") ?: [];

    $created = 0;
    $updated = 0;
    foreach ($rows as $row) {
        $tipoIscrizione = (string)($row['tipo_iscrizione'] ?? 'prime');
        $isCurrentStudent = intval($row['id_studente'] ?? 0) > 0
            && !in_array(strtoupper(trim((string)($row['classe_corrente'] ?? ''))), ['MEDIE', 'EE'], true);
        $annoCorso = studentiMovimentiClassYear((string)($row['classe_corrente'] ?? ''));
        if ($annoCorso === null) {
            $annoCorso = $tipoIscrizione === 'terze' ? 3 : 1;
        }
        $state = studentiMovimentiStateFromCambioScuola((string)($row['pratica_stato'] ?? ''), (string)($row['nulla_osta_stato'] ?? ''));
        $noteParts = [
            'Sincronizzato da cambio scuola iscrizioni ' . $tipoIscrizione . '.',
        ];
        if (!empty($row['richiesta_data'])) {
            $noteParts[] = 'Richiesta: ' . studentiMovimentiFormatDateIt((string)$row['richiesta_data']);
        }
        if (!empty($row['canale'])) {
            $noteParts[] = 'Canale: ' . $row['canale'];
        }
        if (!empty($row['note'])) {
            $noteParts[] = 'Note iscrizioni: ' . $row['note'];
        }
        $note = implode("\n", $noteParts);

        $existing = dbGetFirst("
            SELECT *
            FROM studenti_movimenti_pratiche
            WHERE (
                    id_pratica_iscrizione = " . dbI($row['pratica_iscrizione_id']) . "
                 OR id_cambio_scuola_iscrizione = " . dbI($row['cambio_id']) . "
                 OR (
                        codice_fiscale IS NOT NULL
                    AND codice_fiscale <> ''
                    AND UPPER(TRIM(codice_fiscale)) = " . dbQ(strtoupper(trim((string)$row['codice_fiscale']))) . "
                    AND tipo_pratica IN ('bocciato_reiscrizione','uscita','ritiro')
                    AND stato_pratica <> 'annullata'
                    )
            )
            LIMIT 1
        ");

        $data = [
            'id' => intval($existing['id'] ?? 0),
            'tipo_pratica' => 'uscita',
            'stato_pratica' => $state,
            'id_studente' => $isCurrentStudent ? intval($row['id_studente']) : null,
            'cognome' => $row['cognome'] ?? '',
            'nome' => $row['nome'] ?? '',
            'codice_fiscale' => $row['codice_fiscale'] ?? '',
            'classe_origine' => $row['classe_corrente'] ?: ($tipoIscrizione === 'prime' ? 'Prima iscrizione' : 'Terza iscrizione'),
            'classe_richiesta' => '',
            'anno_corso' => $annoCorso,
            'id_istituto_destinazione' => intval($row['id_istituto_destinazione'] ?? 0) ?: null,
            'scuola_destinazione' => $row['scuola_destinazione'] ?? '',
            'indirizzo_destinazione' => $row['indirizzo_destinazione'] ?? '',
            'id_istituto_provenienza' => null,
            'scuola_provenienza' => '',
            'indirizzo_provenienza' => '',
            'esami_integrativi' => 0,
            'fonte' => 'iscrizioni',
            'id_pratica_iscrizione' => intval($row['pratica_iscrizione_id']),
            'id_cambio_scuola_iscrizione' => intval($row['cambio_id']),
            'note' => $note,
        ];
        if ($existing && !studentiMovimentiPracticeNeedsUpdate($existing, $data)) {
            continue;
        }
        studentiMovimentiSavePractice($data);
        if ($existing) {
            $updated++;
        } else {
            $created++;
        }
    }

    return ['read' => count($rows), 'created' => $created, 'updated' => $updated];
}

function studentiMovimentiPracticeNeedsUpdate(array $existing, array $data): bool
{
    foreach ([
        'tipo_pratica',
        'stato_pratica',
        'id_studente',
        'cognome',
        'nome',
        'codice_fiscale',
        'classe_origine',
        'classe_richiesta',
        'anno_corso',
        'id_istituto_destinazione',
        'scuola_destinazione',
        'indirizzo_destinazione',
        'id_istituto_provenienza',
        'scuola_provenienza',
        'indirizzo_provenienza',
        'esami_integrativi',
        'fonte',
        'id_pratica_iscrizione',
        'id_cambio_scuola_iscrizione',
        'note',
    ] as $field) {
        $left = trim((string)($existing[$field] ?? ''));
        $right = trim((string)($data[$field] ?? ''));
        if ($left !== $right) {
            return true;
        }
    }
    return false;
}

function studentiMovimentiStateFromCambioScuola(string $praticaStato, string $nullaOstaStato): string
{
    if (in_array($nullaOstaStato, ['evaso_inviato'], true)) {
        return 'nulla_osta_inviato';
    }
    if (in_array($nullaOstaStato, ['richiesto', 'ricevuto'], true)) {
        return 'richiesta_nulla_osta';
    }
    if (in_array($praticaStato, ['completata'], true)) {
        return 'chiusa';
    }
    return 'cambia_scuola';
}

function studentiMovimentiFormatDateIt(?string $date): string
{
    $date = trim((string)$date);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $date, $match)) {
        return $date;
    }
    return $match[3] . '/' . $match[2] . '/' . $match[1];
}

function studentiMovimentiFormatDateTimeIt(?string $dateTime): string
{
    $dateTime = trim((string)$dateTime);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/', $dateTime, $match)) {
        return $dateTime;
    }
    return $match[3] . '/' . $match[2] . '/' . $match[1] . (isset($match[4]) && $match[4] !== '' ? ' ' . $match[4] . ':' . $match[5] : '');
}

function studentiMovimentiAttachFile(int $practiceId, array $file, string $type): void
{
    if ($practiceId <= 0 || empty($file['tmp_name']) || intval($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return;
    }
    $original = basename((string)($file['name'] ?? 'documento.pdf'));
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
        throw new RuntimeException('Allegato non valido: carica PDF o immagini JPG/PNG.');
    }

    $dir = studentiMovimentiUploadDir($practiceId);
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException('Impossibile creare la cartella allegati.');
    }
    $safeName = preg_replace('/[^A-Za-z0-9_.-]+/u', '_', $original);
    $target = $dir . '/' . date('Ymd_His') . '_' . $safeName;
    if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
        throw new RuntimeException('Impossibile salvare allegato.');
    }

    dbExec("
        INSERT INTO studenti_movimenti_allegati (
            id_pratica, tipo_allegato, nome_file, path_file, mime_type, dimensione, created_at
        ) VALUES (
            " . dbI($practiceId) . ",
            " . dbQ($type) . ",
            " . dbQ($original) . ",
            " . dbQ($target) . ",
            " . dbQ($file['type'] ?? '') . ",
            " . dbI($file['size'] ?? null) . ",
            NOW()
        )
    ");
    studentiMovimentiAddEvent($practiceId, 'allegato', 'Allegato aggiunto: ' . $original, [
        'note' => 'Tipo allegato: ' . $type,
    ], studentiMovimentiCurrentActor());
}
