<?php

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../api/googleDriveLib.php';
require_once '../common/iscrizioniPrimeLib.php';
require_once '../common/studentiMovimentiLib.php';
require_once '../common/genitoriColloquiLib.php';

ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');

iscrizioniPrimeEnsureSchema();
studentiMovimentiEnsureTables();
genitoriColloquiEnsureTables();

function mad_h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mad_root_path(): string
{
    return realpath(__DIR__ . '/..') ?: dirname(__DIR__);
}

function mad_absolute_path(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    $normalized = str_replace('\\', '/', $path);
    if (preg_match('/^[A-Za-z]:\//', $normalized) || strpos($normalized, '/') === 0) {
        return realpath($path) ?: $path;
    }
    return realpath(mad_root_path() . '/' . ltrim($path, '/\\')) ?: (mad_root_path() . '/' . ltrim($path, '/\\'));
}

function mad_is_local_row(array $row, string $storageField, string $driveField, string $pathField): bool
{
    $path = trim((string)($row[$pathField] ?? ''));
    if ($path === '') {
        return false;
    }
    if (trim((string)($row[$driveField] ?? '')) !== '') {
        return false;
    }
    return strtoupper(trim((string)($row[$storageField] ?? 'LOCAL'))) !== 'DRIVE';
}

function mad_candidates(): array
{
    $sources = [];

    $sources[] = [
        'key' => 'movimenti_allegati',
        'label' => 'Movimenti - allegati pratica',
        'sql' => "
            SELECT 'movimenti_allegati' AS kind, id, id_pratica AS pratica_id, path_file AS path, nome_file AS name, mime_type AS mime
            FROM studenti_movimenti_allegati
            WHERE path_file IS NOT NULL AND path_file <> ''
              AND (drive_file_id IS NULL OR drive_file_id = '')
              AND (storage_type IS NULL OR storage_type = '' OR storage_type = 'LOCAL')
            ORDER BY id ASC
        ",
    ];
    $sources[] = [
        'key' => 'movimenti_eventi',
        'label' => 'Movimenti - allegati storico',
        'sql' => "
            SELECT 'movimenti_eventi' AS kind, id, id_pratica AS pratica_id, allegato_path AS path, allegato_original_name AS name, '' AS mime
            FROM studenti_movimenti_eventi
            WHERE allegato_path IS NOT NULL AND allegato_path <> ''
              AND (allegato_drive_file_id IS NULL OR allegato_drive_file_id = '')
              AND (allegato_storage_type IS NULL OR allegato_storage_type = '' OR allegato_storage_type = 'LOCAL')
            ORDER BY id ASC
        ",
    ];
    $sources[] = [
        'key' => 'colloqui_allegati',
        'label' => 'Colloqui - allegato scheda',
        'sql' => "
            SELECT 'colloqui_allegati' AS kind, id, id AS colloquio_id, allegato_path AS path, allegato_original_name AS name, '' AS mime
            FROM genitori_colloqui
            WHERE allegato_path IS NOT NULL AND allegato_path <> ''
              AND (allegato_drive_file_id IS NULL OR allegato_drive_file_id = '')
              AND (allegato_storage_type IS NULL OR allegato_storage_type = '' OR allegato_storage_type = 'LOCAL')
            ORDER BY id ASC
        ",
    ];
    $sources[] = [
        'key' => 'colloqui_ricevute',
        'label' => 'Colloqui - ricevute libri',
        'sql' => "
            SELECT 'colloqui_ricevute' AS kind, id, id AS colloquio_id, ricevuta_libri_path AS path, ricevuta_libri_original_name AS name, '' AS mime
            FROM genitori_colloqui
            WHERE ricevuta_libri_path IS NOT NULL AND ricevuta_libri_path <> ''
              AND (ricevuta_libri_drive_file_id IS NULL OR ricevuta_libri_drive_file_id = '')
              AND (ricevuta_libri_storage_type IS NULL OR ricevuta_libri_storage_type = '' OR ricevuta_libri_storage_type = 'LOCAL')
            ORDER BY id ASC
        ",
    ];
    $sources[] = [
        'key' => 'colloqui_incontri',
        'label' => 'Colloqui - allegati incontri',
        'sql' => "
            SELECT 'colloqui_incontri' AS kind, a.id, i.colloquio_id, a.path_file AS path, a.nome_file AS name, a.mime_type AS mime
            FROM genitori_colloqui_incontri_allegati a
            JOIN genitori_colloqui_incontri i ON i.id = a.incontro_id
            WHERE a.path_file IS NOT NULL AND a.path_file <> ''
              AND (a.drive_file_id IS NULL OR a.drive_file_id = '')
              AND (a.storage_type IS NULL OR a.storage_type = '' OR a.storage_type = 'LOCAL')
            ORDER BY a.id ASC
        ",
    ];
    $sources[] = [
        'key' => 'colloqui_eventi',
        'label' => 'Colloqui - allegati storico',
        'sql' => "
            SELECT 'colloqui_eventi' AS kind, id, colloquio_id, allegato_path AS path, allegato_original_name AS name, '' AS mime
            FROM genitori_colloqui_eventi
            WHERE allegato_path IS NOT NULL AND allegato_path <> ''
              AND (allegato_drive_file_id IS NULL OR allegato_drive_file_id = '')
              AND (allegato_storage_type IS NULL OR allegato_storage_type = '' OR allegato_storage_type = 'LOCAL')
            ORDER BY id ASC
        ",
    ];
    $sources[] = [
        'key' => 'iscrizioni_documenti',
        'label' => 'Iscrizioni - documenti pratica',
        'sql' => "
            SELECT 'iscrizioni_documenti' AS kind, id, pratica_id, file_path AS path, original_name AS name, mime_type AS mime
            FROM iscrizioni_prime_documenti
            WHERE file_path IS NOT NULL AND file_path <> ''
              AND (drive_file_id IS NULL OR drive_file_id = '')
              AND (storage_type IS NULL OR storage_type = '' OR storage_type = 'LOCAL')
            ORDER BY id ASC
        ",
    ];
    $sources[] = [
        'key' => 'iscrizioni_eventi',
        'label' => 'Iscrizioni - allegati storico',
        'sql' => "
            SELECT 'iscrizioni_eventi' AS kind, id, pratica_id, allegato_path AS path, allegato_original_name AS name, '' AS mime
            FROM iscrizioni_prime_eventi
            WHERE allegato_path IS NOT NULL AND allegato_path <> ''
              AND (allegato_drive_file_id IS NULL OR allegato_drive_file_id = '')
              AND (allegato_storage_type IS NULL OR allegato_storage_type = '' OR allegato_storage_type = 'LOCAL')
            ORDER BY id ASC
        ",
    ];
    $sources[] = [
        'key' => 'iscrizioni_cambio',
        'label' => 'Iscrizioni - cambio scuola riepilogo',
        'sql' => "
            SELECT 'iscrizioni_cambio' AS kind, id, pratica_id, allegato_path AS path, allegato_original_name AS name, 'application/pdf' AS mime
            FROM iscrizioni_prime_cambio_scuola
            WHERE allegato_path IS NOT NULL AND allegato_path <> ''
              AND (allegato_drive_file_id IS NULL OR allegato_drive_file_id = '')
              AND (allegato_storage_type IS NULL OR allegato_storage_type = '' OR allegato_storage_type = 'LOCAL')
            ORDER BY id ASC
        ",
    ];
    $sources[] = [
        'key' => 'iscrizioni_cambio_eventi',
        'label' => 'Iscrizioni - cambio scuola storico',
        'sql' => "
            SELECT 'iscrizioni_cambio_eventi' AS kind, id, pratica_id, allegato_path AS path, allegato_original_name AS name, 'application/pdf' AS mime
            FROM iscrizioni_prime_cambio_scuola_eventi
            WHERE allegato_path IS NOT NULL AND allegato_path <> ''
              AND (allegato_drive_file_id IS NULL OR allegato_drive_file_id = '')
              AND (allegato_storage_type IS NULL OR allegato_storage_type = '' OR allegato_storage_type = 'LOCAL')
            ORDER BY id ASC
        ",
    ];
    $sources[] = [
        'key' => 'iscrizioni_tablet',
        'label' => 'Iscrizioni - rinunce tablet',
        'sql' => "
            SELECT 'iscrizioni_tablet' AS kind, id, id AS pratica_id, tablet_rinuncia_allegato_path AS path, tablet_rinuncia_allegato_original_name AS name, 'application/pdf' AS mime
            FROM iscrizioni_prime_pratiche
            WHERE tablet_rinuncia_allegato_path IS NOT NULL AND tablet_rinuncia_allegato_path <> ''
              AND (tablet_rinuncia_allegato_drive_file_id IS NULL OR tablet_rinuncia_allegato_drive_file_id = '')
              AND (tablet_rinuncia_allegato_storage_type IS NULL OR tablet_rinuncia_allegato_storage_type = '' OR tablet_rinuncia_allegato_storage_type = 'LOCAL')
            ORDER BY id ASC
        ",
    ];

    return $sources;
}

function mad_count_source(array $source): int
{
    $rows = dbGetAll($source['sql']) ?: [];
    return count($rows);
}

function mad_path_key(array $row): string
{
    $path = trim((string)($row['path'] ?? ''));
    return strtolower(str_replace('\\', '/', $path));
}

function mad_unique_file_count(): int
{
    $seen = [];
    foreach (mad_candidates() as $source) {
        $part = dbGetAll($source['sql']) ?: [];
        foreach ($part as $row) {
            $key = mad_path_key($row);
            if ($key !== '') {
                $seen[$key] = true;
            }
        }
    }
    return count($seen);
}

function mad_next_rows(int $limit): array
{
    $rows = [];
    $seen = [];
    foreach (mad_candidates() as $source) {
        if (count($rows) >= $limit) {
            break;
        }
        $part = dbGetAll($source['sql']) ?: [];
        foreach ($part as $row) {
            if (count($rows) >= $limit) {
                break;
            }
            $key = mad_path_key($row);
            if ($key !== '' && isset($seen[$key])) {
                continue;
            }
            if ($key !== '') {
                $seen[$key] = true;
            }
            $row['source_label'] = $source['label'];
            $rows[] = $row;
        }
    }
    return $rows;
}

function mad_upload_for_row(array $row, string $absolute): array
{
    $kind = (string)$row['kind'];
    $name = trim((string)($row['name'] ?? ''));
    if ($name === '') {
        $name = basename($absolute);
    }
    $mime = trim((string)($row['mime'] ?? ''));

    if (strpos($kind, 'movimenti_') === 0) {
        return studentiMovimentiUploadMetadata(intval($row['pratica_id'] ?? 0), $absolute, $name, $mime);
    }
    if (strpos($kind, 'colloqui_') === 0) {
        return genitoriColloquiUploadMetadata(intval($row['colloquio_id'] ?? 0), $absolute, $name, $mime);
    }
    return iscrizioniPrimeDriveAttachmentMetadata(intval($row['pratica_id'] ?? 0), $absolute, $name, 'MIGRAZIONE', $mime);
}

function mad_update_row(array $row, array $storage): void
{
    $id = intval($row['id'] ?? 0);
    $path = (string)($row['path'] ?? '');
    switch ((string)$row['kind']) {
        case 'movimenti_allegati':
            dbExec("
                UPDATE studenti_movimenti_allegati
                SET storage_type = 'DRIVE',
                    drive_file_id = " . dbQ($storage['drive_file_id']) . ",
                    drive_web_view_link = " . dbQ($storage['drive_web_view_link']) . ",
                    drive_folder_id = " . dbQ($storage['drive_folder_id']) . "
                WHERE id = " . dbI($id) . "
                LIMIT 1
            ");
            dbExec("
                UPDATE studenti_movimenti_eventi
                SET allegato_storage_type = 'DRIVE',
                    allegato_drive_file_id = " . dbQ($storage['drive_file_id']) . ",
                    allegato_drive_web_view_link = " . dbQ($storage['drive_web_view_link']) . ",
                    allegato_drive_folder_id = " . dbQ($storage['drive_folder_id']) . "
                WHERE allegato_path = " . dbQ($path) . "
                  AND (allegato_drive_file_id IS NULL OR allegato_drive_file_id = '')
            ");
            break;
        case 'movimenti_eventi':
            dbExec("
                UPDATE studenti_movimenti_eventi
                SET allegato_storage_type = 'DRIVE',
                    allegato_drive_file_id = " . dbQ($storage['drive_file_id']) . ",
                    allegato_drive_web_view_link = " . dbQ($storage['drive_web_view_link']) . ",
                    allegato_drive_folder_id = " . dbQ($storage['drive_folder_id']) . "
                WHERE id = " . dbI($id) . "
                LIMIT 1
            ");
            break;
        case 'colloqui_allegati':
            dbExec("
                UPDATE genitori_colloqui
                SET allegato_storage_type = 'DRIVE',
                    allegato_drive_file_id = " . dbQ($storage['drive_file_id']) . ",
                    allegato_drive_web_view_link = " . dbQ($storage['drive_web_view_link']) . ",
                    allegato_drive_folder_id = " . dbQ($storage['drive_folder_id']) . "
                WHERE id = " . dbI($id) . "
                LIMIT 1
            ");
            dbExec("
                UPDATE genitori_colloqui_eventi
                SET allegato_storage_type = 'DRIVE',
                    allegato_drive_file_id = " . dbQ($storage['drive_file_id']) . ",
                    allegato_drive_web_view_link = " . dbQ($storage['drive_web_view_link']) . ",
                    allegato_drive_folder_id = " . dbQ($storage['drive_folder_id']) . "
                WHERE allegato_path = " . dbQ($path) . "
                  AND (allegato_drive_file_id IS NULL OR allegato_drive_file_id = '')
            ");
            break;
        case 'colloqui_ricevute':
            dbExec("
                UPDATE genitori_colloqui
                SET ricevuta_libri_storage_type = 'DRIVE',
                    ricevuta_libri_drive_file_id = " . dbQ($storage['drive_file_id']) . ",
                    ricevuta_libri_drive_web_view_link = " . dbQ($storage['drive_web_view_link']) . ",
                    ricevuta_libri_drive_folder_id = " . dbQ($storage['drive_folder_id']) . "
                WHERE id = " . dbI($id) . "
                LIMIT 1
            ");
            break;
        case 'colloqui_incontri':
            dbExec("
                UPDATE genitori_colloqui_incontri_allegati
                SET storage_type = 'DRIVE',
                    drive_file_id = " . dbQ($storage['drive_file_id']) . ",
                    drive_web_view_link = " . dbQ($storage['drive_web_view_link']) . ",
                    drive_folder_id = " . dbQ($storage['drive_folder_id']) . "
                WHERE id = " . dbI($id) . "
                LIMIT 1
            ");
            break;
        case 'colloqui_eventi':
            dbExec("
                UPDATE genitori_colloqui_eventi
                SET allegato_storage_type = 'DRIVE',
                    allegato_drive_file_id = " . dbQ($storage['drive_file_id']) . ",
                    allegato_drive_web_view_link = " . dbQ($storage['drive_web_view_link']) . ",
                    allegato_drive_folder_id = " . dbQ($storage['drive_folder_id']) . "
                WHERE id = " . dbI($id) . "
                LIMIT 1
            ");
            break;
        case 'iscrizioni_documenti':
            dbExec("
                UPDATE iscrizioni_prime_documenti
                SET storage_type = 'DRIVE',
                    drive_file_id = " . dbQ($storage['drive_file_id']) . ",
                    drive_web_view_link = " . dbQ($storage['drive_web_view_link']) . ",
                    drive_folder_id = " . dbQ($storage['drive_folder_id']) . "
                WHERE id = " . dbI($id) . "
                LIMIT 1
            ");
            break;
        case 'iscrizioni_eventi':
            dbExec("
                UPDATE iscrizioni_prime_eventi
                SET allegato_storage_type = 'DRIVE',
                    allegato_drive_file_id = " . dbQ($storage['drive_file_id']) . ",
                    allegato_drive_web_view_link = " . dbQ($storage['drive_web_view_link']) . ",
                    allegato_drive_folder_id = " . dbQ($storage['drive_folder_id']) . "
                WHERE id = " . dbI($id) . "
                LIMIT 1
            ");
            break;
        case 'iscrizioni_cambio':
            dbExec("
                UPDATE iscrizioni_prime_cambio_scuola
                SET allegato_storage_type = 'DRIVE',
                    allegato_drive_file_id = " . dbQ($storage['drive_file_id']) . ",
                    allegato_drive_web_view_link = " . dbQ($storage['drive_web_view_link']) . ",
                    allegato_drive_folder_id = " . dbQ($storage['drive_folder_id']) . "
                WHERE id = " . dbI($id) . "
                LIMIT 1
            ");
            break;
        case 'iscrizioni_cambio_eventi':
            dbExec("
                UPDATE iscrizioni_prime_cambio_scuola_eventi
                SET allegato_storage_type = 'DRIVE',
                    allegato_drive_file_id = " . dbQ($storage['drive_file_id']) . ",
                    allegato_drive_web_view_link = " . dbQ($storage['drive_web_view_link']) . ",
                    allegato_drive_folder_id = " . dbQ($storage['drive_folder_id']) . "
                WHERE id = " . dbI($id) . "
                LIMIT 1
            ");
            break;
        case 'iscrizioni_tablet':
            dbExec("
                UPDATE iscrizioni_prime_pratiche
                SET tablet_rinuncia_allegato_storage_type = 'DRIVE',
                    tablet_rinuncia_allegato_drive_file_id = " . dbQ($storage['drive_file_id']) . ",
                    tablet_rinuncia_allegato_drive_web_view_link = " . dbQ($storage['drive_web_view_link']) . ",
                    tablet_rinuncia_allegato_drive_folder_id = " . dbQ($storage['drive_folder_id']) . "
                WHERE id = " . dbI($id) . "
                LIMIT 1
            ");
            break;
    }
}

function mad_run_batch(int $limit): array
{
    $rows = mad_next_rows($limit);
    $result = [
        'read' => count($rows),
        'migrated' => 0,
        'missing' => 0,
        'errors' => [],
    ];
    foreach ($rows as $row) {
        $absolute = mad_absolute_path((string)($row['path'] ?? ''));
        if ($absolute === '' || !is_file($absolute)) {
            $result['missing']++;
            $result['errors'][] = [
                'source' => $row['source_label'] ?? $row['kind'],
                'id' => intval($row['id'] ?? 0),
                'file' => $row['path'] ?? '',
                'error' => 'File locale non trovato',
            ];
            continue;
        }
        try {
            $storage = mad_upload_for_row($row, $absolute);
            if (strtoupper((string)($storage['storage_type'] ?? 'LOCAL')) !== 'DRIVE' || trim((string)($storage['drive_file_id'] ?? '')) === '') {
                throw new RuntimeException('Drive non ha restituito un file valido.');
            }
            mad_update_row($row, $storage);
            $result['migrated']++;
        } catch (Throwable $e) {
            $result['errors'][] = [
                'source' => $row['source_label'] ?? $row['kind'],
                'id' => intval($row['id'] ?? 0),
                'file' => $row['path'] ?? '',
                'error' => $e->getMessage(),
            ];
        }
    }
    return $result;
}

function mad_realign_movimenti_drive_folders(): array
{
    $rows = dbGetAll("
        SELECT DISTINCT id_pratica
        FROM studenti_movimenti_allegati
        WHERE storage_type = 'DRIVE'
          AND drive_file_id IS NOT NULL
          AND drive_file_id <> ''
        ORDER BY id_pratica ASC
    ") ?: [];
    $result = [
        'pratiche' => count($rows),
        'files' => 0,
        'errors' => [],
    ];
    foreach ($rows as $row) {
        $practiceId = intval($row['id_pratica'] ?? 0);
        if ($practiceId <= 0) {
            continue;
        }
        try {
            $result['files'] += studentiMovimentiMoveDriveAttachmentsToCurrentFolder($practiceId);
        } catch (Throwable $e) {
            $result['errors'][] = [
                'id_pratica' => $practiceId,
                'error' => $e->getMessage(),
            ];
        }
    }
    return $result;
}

function mad_cleanup_movimenti_empty_drive_folders(): array
{
    require_once '../api/googleDriveLib.php';
    $rows = dbGetAll("
        SELECT DISTINCT drive_folder_id
        FROM studenti_movimenti_allegati
        WHERE storage_type = 'DRIVE'
          AND drive_folder_id IS NOT NULL
          AND drive_folder_id <> ''
        UNION
        SELECT DISTINCT allegato_drive_folder_id AS drive_folder_id
        FROM studenti_movimenti_eventi
        WHERE allegato_storage_type = 'DRIVE'
          AND allegato_drive_folder_id IS NOT NULL
          AND allegato_drive_folder_id <> ''
    ") ?: [];
    $result = [
        'read' => count($rows),
        'deleted' => 0,
        'not_empty' => 0,
        'errors' => [],
    ];
    foreach ($rows as $row) {
        $folderId = trim((string)($row['drive_folder_id'] ?? ''));
        if ($folderId === '') {
            continue;
        }
        try {
            if (googleDriveDeleteFolderIfEmpty($folderId)) {
                $result['deleted']++;
            } else {
                $result['not_empty']++;
            }
        } catch (Throwable $e) {
            $result['errors'][] = [
                'folder_id' => $folderId,
                'error' => $e->getMessage(),
            ];
        }
    }
    return $result;
}

$driveEnabled = googleDriveIsEnabled();
$limit = max(1, min(200, intval($_POST['limit'] ?? $_GET['limit'] ?? 25)));
$action = (string)($_POST['action'] ?? ($_GET['action'] ?? ''));
$run = $action === 'run' || (string)($_GET['run'] ?? '') === '1';
$runResult = null;
$realignResult = null;
$cleanupResult = null;
if ($run && $driveEnabled) {
    $runResult = mad_run_batch($limit);
}
if ($action === 'realign_movimenti' && $driveEnabled) {
    $realignResult = mad_realign_movimenti_drive_folders();
}
if ($action === 'cleanup_movimenti_empty' && $driveEnabled) {
    $cleanupResult = mad_cleanup_movimenti_empty_drive_folders();
}

$sources = mad_candidates();
$counts = [];
$total = 0;
foreach ($sources as $source) {
    $count = mad_count_source($source);
    $counts[] = ['label' => $source['label'], 'count' => $count];
    $total += $count;
}
$uniqueTotal = mad_unique_file_count();
$previewRows = mad_next_rows(20);

?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Migrazione allegati Drive</title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <style>
        body { padding: 18px; background: #f6f8fb; }
        .panel { border-radius: 6px; }
        .metric { display: inline-block; padding: 8px 12px; border-radius: 16px; background: #e8f4ff; color: #11466f; font-weight: 700; margin-right: 8px; }
        .metric.warn { background: #fff4db; color: #7a4a00; }
        .metric.ok { background: #e8f8e8; color: #1d6b27; }
        .table > tbody > tr > td { vertical-align: middle; }
        code { white-space: normal; }
    </style>
</head>
<body>
<div class="container-fluid">
    <h2>Migrazione allegati locali su Google Drive</h2>
    <p class="text-muted">
        La migrazione carica su Drive gli allegati ancora locali e aggiorna il database.
        I file locali non vengono cancellati.
    </p>

    <?php if (!$driveEnabled): ?>
        <div class="alert alert-danger">
            Google Drive non risulta abilitato in <code>GestOre.json</code>. Abilitalo e compila gli ID cartella prima di migrare.
        </div>
    <?php endif; ?>

    <div class="panel panel-default">
        <div class="panel-heading"><strong>Stato</strong></div>
        <div class="panel-body">
            <span class="metric <?php echo $driveEnabled ? 'ok' : 'warn'; ?>">Drive: <?php echo $driveEnabled ? 'attivo' : 'non attivo'; ?></span>
            <span class="metric <?php echo $total > 0 ? 'warn' : 'ok'; ?>">Record da aggiornare: <?php echo intval($total); ?></span>
            <span class="metric <?php echo $uniqueTotal > 0 ? 'warn' : 'ok'; ?>">File unici da caricare: <?php echo intval($uniqueTotal); ?></span>
            <form method="post" action="migraAllegatiDrive.php" class="form-inline" style="margin-top:14px;">
                <input type="hidden" name="action" value="run">
                <div class="form-group">
                    <label for="limit">Blocco</label>
                    <input type="number" min="1" max="200" name="limit" id="limit" class="form-control" value="<?php echo intval($limit); ?>" style="width:90px;">
                </div>
                <button type="submit" class="btn btn-primary" <?php echo (!$driveEnabled || $total <= 0) ? 'disabled' : ''; ?>>
                    Migra prossimo blocco
                </button>
                <a class="btn btn-warning <?php echo (!$driveEnabled || $total <= 0) ? 'disabled' : ''; ?>"
                   href="migraAllegatiDrive.php?action=run&limit=<?php echo intval($limit); ?>">
                    Avvia con link diretto
                </a>
                <a class="btn btn-default" href="migraAllegatiDrive.php">Aggiorna anteprima</a>
            </form>
            <form method="post" action="migraAllegatiDrive.php" class="form-inline" style="margin-top:8px;">
                <input type="hidden" name="action" value="realign_movimenti">
                <button type="submit" class="btn btn-info" <?php echo !$driveEnabled ? 'disabled' : ''; ?>>
                    Riallinea cartelle Drive movimenti
                </button>
                <span class="text-muted">Sposta gli allegati Drive nella cartella coerente con il tipo pratica attuale.</span>
            </form>
            <form method="post" action="migraAllegatiDrive.php" class="form-inline" style="margin-top:8px;">
                <input type="hidden" name="action" value="cleanup_movimenti_empty">
                <button type="submit" class="btn btn-default" <?php echo !$driveEnabled ? 'disabled' : ''; ?>>
                    Cancella cartelle movimenti vuote
                </button>
                <span class="text-muted">Elimina solo cartelle Drive vuote gia registrate sugli allegati movimenti.</span>
            </form>
        </div>
    </div>

    <?php if (!$runResult && !$run): ?>
        <div class="alert alert-warning">
            Questa e solo l'anteprima: nessun file e stato ancora caricato su Drive. Usa <strong>Migra prossimo blocco</strong>
            oppure <strong>Avvia con link diretto</strong>.
        </div>
    <?php endif; ?>

    <?php if ($run && !$driveEnabled): ?>
        <div class="alert alert-danger">
            Migrazione non eseguita: Google Drive non e attivo.
        </div>
    <?php endif; ?>

    <?php if ($runResult): ?>
        <div class="alert alert-info">
            Letti <?php echo intval($runResult['read']); ?> file unici,
            caricati su Drive <?php echo intval($runResult['migrated']); ?>,
            file locali mancanti <?php echo intval($runResult['missing']); ?>,
            errori <?php echo count($runResult['errors']); ?>.
        </div>
        <?php if (!empty($runResult['errors'])): ?>
            <div class="panel panel-danger">
                <div class="panel-heading"><strong>Errori / file mancanti</strong></div>
                <table class="table table-condensed">
                    <thead><tr><th>Fonte</th><th>ID</th><th>File</th><th>Errore</th></tr></thead>
                    <tbody>
                    <?php foreach ($runResult['errors'] as $error): ?>
                        <tr>
                            <td><?php echo mad_h($error['source']); ?></td>
                            <td><?php echo intval($error['id']); ?></td>
                            <td><code><?php echo mad_h($error['file']); ?></code></td>
                            <td><?php echo mad_h($error['error']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($realignResult): ?>
        <div class="alert alert-info">
            Riallineamento movimenti completato: pratiche lette <?php echo intval($realignResult['pratiche']); ?>,
            file spostati/controllati <?php echo intval($realignResult['files']); ?>,
            errori <?php echo count($realignResult['errors']); ?>.
        </div>
        <?php if (!empty($realignResult['errors'])): ?>
            <div class="panel panel-danger">
                <div class="panel-heading"><strong>Errori riallineamento movimenti</strong></div>
                <table class="table table-condensed">
                    <thead><tr><th>Pratica</th><th>Errore</th></tr></thead>
                    <tbody>
                    <?php foreach ($realignResult['errors'] as $error): ?>
                        <tr>
                            <td><?php echo intval($error['id_pratica']); ?></td>
                            <td><?php echo mad_h($error['error']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($cleanupResult): ?>
        <div class="alert alert-info">
            Pulizia cartelle movimenti: cartelle controllate <?php echo intval($cleanupResult['read']); ?>,
            cancellate <?php echo intval($cleanupResult['deleted']); ?>,
            non vuote <?php echo intval($cleanupResult['not_empty']); ?>,
            errori <?php echo count($cleanupResult['errors']); ?>.
        </div>
        <?php if (!empty($cleanupResult['errors'])): ?>
            <div class="panel panel-danger">
                <div class="panel-heading"><strong>Errori pulizia cartelle</strong></div>
                <table class="table table-condensed">
                    <thead><tr><th>Cartella Drive</th><th>Errore</th></tr></thead>
                    <tbody>
                    <?php foreach ($cleanupResult['errors'] as $error): ?>
                        <tr>
                            <td><code><?php echo mad_h($error['folder_id']); ?></code></td>
                            <td><?php echo mad_h($error['error']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-5">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Conteggi per area</strong></div>
                <table class="table table-condensed">
                    <thead><tr><th>Area</th><th class="text-right">Record locali</th></tr></thead>
                    <tbody>
                    <?php foreach ($counts as $row): ?>
                        <tr>
                            <td><?php echo mad_h($row['label']); ?></td>
                            <td class="text-right"><?php echo intval($row['count']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-md-7">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Prossimi file in coda</strong></div>
                <table class="table table-condensed table-striped">
                    <thead><tr><th>Area</th><th>ID</th><th>Nome</th><th>File locale</th></tr></thead>
                    <tbody>
                    <?php if (!$previewRows): ?>
                        <tr><td colspan="4" class="text-muted">Nessun allegato locale da migrare.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($previewRows as $row): ?>
                        <tr>
                            <td><?php echo mad_h($row['source_label']); ?></td>
                            <td><?php echo intval($row['id']); ?></td>
                            <td><?php echo mad_h($row['name'] ?: basename((string)$row['path'])); ?></td>
                            <td><code><?php echo mad_h($row['path']); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
