<?php

require_once __DIR__ . '/connect.php';

function programmiSvoltiCopertineTableExists(): bool
{
    static $exists = null;
    if ($exists === null) {
        $exists = dbGetFirst("SHOW TABLES LIKE 'programmi_svolti_copertine'") != null;
    }
    return $exists;
}

function programmiSvoltiCopertinaByProgramma(int $programmaId): ?array
{
    if (!programmiSvoltiCopertineTableExists()) {
        return null;
    }

    $row = dbGetFirst("SELECT * FROM programmi_svolti_copertine WHERE id_programma_svolto=" . intval($programmaId) . " LIMIT 1");
    return $row ?: null;
}

function programmiSvoltiCopertinaColumnExists(string $columnName, bool $refresh = false): bool
{
    static $cache = [];
    $columnName = trim($columnName);
    if ($columnName === '') {
        return false;
    }
    if ($refresh || !array_key_exists($columnName, $cache)) {
        $cache[$columnName] = dbGetFirst("SHOW COLUMNS FROM programmi_svolti_copertine LIKE '" . dbEscape($columnName) . "'") != null;
    }
    return (bool)$cache[$columnName];
}

function programmiSvoltiCopertineEnsureConsegnaColumns(): void
{
    if (!programmiSvoltiCopertineTableExists()) {
        return;
    }

    if (!programmiSvoltiCopertinaColumnExists('verifiche_consegnate')) {
        $afterColumn = programmiSvoltiCopertinaColumnExists('printed_at') ? 'printed_at' : 'updated_at';
        dbExec("ALTER TABLE programmi_svolti_copertine ADD COLUMN verifiche_consegnate TINYINT(1) NOT NULL DEFAULT 0 AFTER " . $afterColumn);
        programmiSvoltiCopertinaColumnExists('verifiche_consegnate', true);
    }
    if (!programmiSvoltiCopertinaColumnExists('verifiche_consegnate_at')) {
        dbExec("ALTER TABLE programmi_svolti_copertine ADD COLUMN verifiche_consegnate_at DATETIME NULL AFTER verifiche_consegnate");
        programmiSvoltiCopertinaColumnExists('verifiche_consegnate_at', true);
    }
    if (!programmiSvoltiCopertinaColumnExists('verifiche_consegnate_by_user_id')) {
        dbExec("ALTER TABLE programmi_svolti_copertine ADD COLUMN verifiche_consegnate_by_user_id INT NULL AFTER verifiche_consegnate_at");
        programmiSvoltiCopertinaColumnExists('verifiche_consegnate_by_user_id', true);
    }
}

function programmiSvoltiCopertineAnnoFine(string $annoLabel): int
{
    if (preg_match('/(\d{4})\s*[\/-]\s*(\d{4})/', $annoLabel, $m)) {
        return intval($m[2]);
    }
    if (preg_match('/(\d{4})/', $annoLabel, $m)) {
        return intval($m[1]);
    }
    return intval(date('Y'));
}

function programmiSvoltiCopertineAnnoCartella(string $annoLabel): string
{
    $annoLabel = trim($annoLabel);
    if ($annoLabel === '') {
        $annoLabel = date('Y');
    }
    return 'AS ' . str_replace('/', '-', $annoLabel);
}

function programmiSvoltiCopertinaLoadProgramma(int $programmaId): ?array
{
    $query = "SELECT
            ps.id AS programma_id,
            ps.id_anno_scolastico,
            ps.id_docente,
            ps.id_classe,
            ps.id_materia,
            classi.classe AS classe_nome,
            classi.anno AS classe_anno,
            materia.nome AS materia_nome,
            docente.nome AS docente_nome,
            docente.cognome AS docente_cognome,
            indirizzo.nome AS indirizzo_nome,
            anno_scolastico.anno AS anno_scolastico_label,
            (
                SELECT GROUP_CONCAT(c2.classe ORDER BY c2.classe SEPARATOR ' / ')
                FROM programmi_svolti_classi psc2
                INNER JOIN classi c2 ON c2.id = psc2.id_classe
                WHERE psc2.id_programma_svolto = ps.id
            ) AS classi_collegate_nome,
            (
                SELECT GROUP_CONCAT(DISTINCT i2.nome ORDER BY i2.nome SEPARATOR ' / ')
                FROM programmi_svolti_classi psc3
                INNER JOIN classi c3 ON c3.id = psc3.id_classe
                INNER JOIN indirizzo i2 ON i2.id = c3.id_primo_indirizzo
                WHERE psc3.id_programma_svolto = ps.id
            ) AS indirizzi_collegati_nome
        FROM programmi_svolti ps
        INNER JOIN classi ON classi.id = ps.id_classe
        INNER JOIN materia ON materia.id = ps.id_materia
        INNER JOIN docente ON docente.id = ps.id_docente
        INNER JOIN indirizzo ON indirizzo.id = classi.id_primo_indirizzo
        LEFT JOIN anno_scolastico ON anno_scolastico.id = ps.id_anno_scolastico
        WHERE ps.id = " . intval($programmaId);

    $row = dbGetFirst($query);
    if (!$row) {
        return null;
    }

    $row['classe_label'] = trim((string)($row['classi_collegate_nome'] ?? '')) !== ''
        ? (string)$row['classi_collegate_nome']
        : (string)$row['classe_nome'];
    $row['percorso_label'] = trim((string)($row['indirizzi_collegati_nome'] ?? '')) !== ''
        ? (string)$row['indirizzi_collegati_nome']
        : (string)$row['indirizzo_nome'];
    $row['docente_label'] = trim((string)$row['docente_cognome'] . ' ' . (string)$row['docente_nome']);

    return $row;
}

function programmiSvoltiCopertinaUserCanRequest(array $programma, int $docenteId): bool
{
    if (haRuolo('admin') || haRuolo('dirigente') || haRuolo('segreteria-didattica')) {
        return true;
    }
    return $docenteId > 0 && intval($programma['id_docente'] ?? 0) === $docenteId;
}

function programmiSvoltiCopertinaRequest(int $programmaId, int $utenteId): array
{
    if (!programmiSvoltiCopertineTableExists()) {
        return ['ok' => false, 'message' => 'Tabella programmi_svolti_copertine non presente. Esegui la migrazione SQL.'];
    }
    programmiSvoltiCopertineEnsureConsegnaColumns();

    $programma = programmiSvoltiCopertinaLoadProgramma($programmaId);
    if (!$programma) {
        return ['ok' => false, 'message' => 'Programma svolto non trovato.'];
    }

    $existing = programmiSvoltiCopertinaByProgramma($programmaId);
    if ($existing && in_array((string)$existing['stato'], ['RICHIESTA', 'GENERATA', 'STAMPATA'], true)) {
        if ((string)$existing['stato'] === 'STAMPATA') {
            return ['ok' => true, 'message' => 'Copertina gia stampata.'];
        }
        return ['ok' => true, 'message' => (string)$existing['stato'] === 'GENERATA' ? 'Copertina gia generata.' : 'Copertina gia richiesta.'];
    }

    $now = date('Y-m-d H:i:s');
    if ($existing) {
        dbExec("UPDATE programmi_svolti_copertine
            SET stato='RICHIESTA',
                requested_by_user_id=" . intval($utenteId) . ",
                requested_at='" . dbEscape($now) . "',
                error_message=NULL,
                updated_at='" . dbEscape($now) . "'
            WHERE id=" . intval($existing['id']));
    } else {
        dbExec("INSERT INTO programmi_svolti_copertine
            (id_programma_svolto, id_anno_scolastico, stato, requested_by_user_id, requested_at, created_at, updated_at)
            VALUES
            (" . intval($programmaId) . ", " . intval($programma['id_anno_scolastico']) . ", 'RICHIESTA', " . intval($utenteId) . ", '" . dbEscape($now) . "', '" . dbEscape($now) . "', '" . dbEscape($now) . "')");
    }

    return ['ok' => true, 'message' => 'Copertina richiesta.'];
}

function programmiSvoltiCopertinaSetVerificheConsegnate(int $copertinaId, bool $consegnata, int $utenteId): array
{
    if (!programmiSvoltiCopertineTableExists()) {
        return ['ok' => false, 'message' => 'Tabella programmi_svolti_copertine non presente.'];
    }
    programmiSvoltiCopertineEnsureConsegnaColumns();

    $row = dbGetFirst("SELECT id, stato FROM programmi_svolti_copertine WHERE id=" . intval($copertinaId) . " LIMIT 1");
    if (!$row) {
        return ['ok' => false, 'message' => 'Copertina non trovata.'];
    }

    if ($consegnata) {
        dbExec("UPDATE programmi_svolti_copertine
            SET verifiche_consegnate=1,
                verifiche_consegnate_at=NOW(),
                verifiche_consegnate_by_user_id=" . intval($utenteId) . ",
                updated_at=NOW()
            WHERE id=" . intval($copertinaId));
        return ['ok' => true, 'message' => 'Verifiche segnate come consegnate.'];
    }

    dbExec("UPDATE programmi_svolti_copertine
        SET verifiche_consegnate=0,
            verifiche_consegnate_at=NULL,
            verifiche_consegnate_by_user_id=NULL,
            updated_at=NOW()
        WHERE id=" . intval($copertinaId));
    return ['ok' => true, 'message' => 'Consegna verifiche rimossa.'];
}

function programmiSvoltiCopertinaDeleteRequest(int $copertinaId): array
{
    if (!programmiSvoltiCopertineTableExists()) {
        return ['ok' => false, 'message' => 'Tabella programmi_svolti_copertine non presente.'];
    }

    $row = dbGetFirst("SELECT id, stato FROM programmi_svolti_copertine WHERE id=" . intval($copertinaId) . " LIMIT 1");
    if (!$row) {
        return ['ok' => false, 'message' => 'Copertina non trovata.'];
    }

    dbExec("DELETE FROM programmi_svolti_copertine WHERE id=" . intval($copertinaId) . " LIMIT 1");
    return ['ok' => true, 'message' => 'Richiesta copertina annullata. Il programma torna allo stato iniziale.'];
}

function programmiSvoltiCopertinaNextCode(int $annoFine): array
{
    $usedRows = dbGetAll("SELECT fascicolo_numero
        FROM programmi_svolti_copertine
        WHERE fascicolo_anno=" . intval($annoFine) . "
          AND fascicolo_numero IS NOT NULL
          AND fascicolo_numero > 0
        ORDER BY fascicolo_numero ASC");

    $next = 1;
    foreach ($usedRows as $row) {
        $numero = intval($row['fascicolo_numero'] ?? 0);
        if ($numero < $next) {
            continue;
        }
        if ($numero > $next) {
            break;
        }
        $next++;
    }

    return [
        'numero' => $next,
        'anno' => $annoFine,
        'codice' => 'Fascicolo A' . str_pad((string)$next, 4, '0', STR_PAD_LEFT) . '-' . $annoFine,
    ];
}

function programmiSvoltiCopertinaSafeFilePart(string $value): string
{
    $value = preg_replace('/[\\\\\/:*?"<>|]+/', ' ', $value) ?? $value;
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return trim($value);
}

function programmiSvoltiCopertinaFileName(array $programma, string $codice): string
{
    return programmiSvoltiCopertinaSafeFilePart(strtoupper($codice)
        . ' - ' . (string)$programma['classe_label']
        . ' - ' . (string)$programma['materia_nome']
        . ' - ' . (string)$programma['docente_label']) . '.pdf';
}
