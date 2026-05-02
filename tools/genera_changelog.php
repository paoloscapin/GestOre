<?php
/**
 * Genera una sezione di changelog partendo dagli ultimi commit Git.
 *
 * Uso:
 *   php tools/genera_changelog.php
 *   php tools/genera_changelog.php --days=15
 *   php tools/genera_changelog.php --commits=30
 *   php tools/genera_changelog.php --from-date=2026-04-01
 *   php tools/genera_changelog.php --from=abc1234
 *   php tools/genera_changelog.php --max-per-category=6
 *   php tools/genera_changelog.php --all
 *   php tools/genera_changelog.php --current-version
 *   php tools/genera_changelog.php --version=1.2.350
 *   php tools/genera_changelog.php --dry-run
 */

$rootDir = dirname(__DIR__);
$changelogPath = $rootDir . DIRECTORY_SEPARATOR . 'changelog.md';
$versionPath = $rootDir . DIRECTORY_SEPARATOR . 'version.php';

function argValue(array $argv, string $name, ?string $default = null): ?string
{
    $prefix = '--' . $name . '=';
    foreach ($argv as $arg) {
        if (strpos($arg, $prefix) === 0) {
            return substr($arg, strlen($prefix));
        }
    }
    return $default;
}

function hasFlag(array $argv, string $name): bool
{
    return in_array('--' . $name, $argv, true);
}

function shellArg(string $value): string
{
    if (DIRECTORY_SEPARATOR === '\\') {
        return '"' . str_replace('"', '\"', $value) . '"';
    }

    return escapeshellarg($value);
}

function readVersionInfo(string $versionPath): array
{
    $version = date('Y.m.d');
    $releaseDate = date('j M Y');

    if (is_file($versionPath)) {
        $content = (string)file_get_contents($versionPath);
        if (preg_match('/\$__software_version\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $m)) {
            $version = trim($m[1]);
        }
        if (preg_match('/\$__software_release_date\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $m)) {
            $releaseDate = trim($m[1]);
        }
    }

    return [$version, $releaseDate];
}

function nextPatchVersion(string $version): string
{
    $parts = explode('.', $version);
    if (count($parts) !== 3) {
        return $version;
    }

    $parts[2] = (string)((int)$parts[2] + 1);
    return implode('.', $parts);
}

function runGitCommand(string $rootDir, string $arguments, bool $allowEmpty = false): array
{
    $cmd = 'git -C ' . shellArg($rootDir) . ' ' . $arguments;
    $output = [];
    $exitCode = 0;
    exec($cmd, $output, $exitCode);
    if ($exitCode !== 0 && !$allowEmpty) {
        fwrite(STDERR, "Errore durante l'esecuzione di Git.\n");
        exit(1);
    }
    return $output;
}

function latestChangelogCommit(string $rootDir): string
{
    $output = runGitCommand(
        $rootDir,
        'log --no-merges -n 1 --pretty=format:%H -- ' . shellArg('changelog.md'),
        true
    );

    return trim((string)($output[0] ?? ''));
}

function versionAtCommit(string $rootDir, string $hash): string
{
    static $cache = [];
    if ($hash === '') {
        return '';
    }
    if (isset($cache[$hash])) {
        return $cache[$hash];
    }

    $output = runGitCommand(
        $rootDir,
        'show ' . shellArg($hash . ':version.php'),
        true
    );
    $content = implode("\n", $output);
    $version = '';
    if (preg_match('/\$__software_version\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $m)) {
        $version = trim($m[1]);
    }

    $cache[$hash] = $version;
    return $version;
}

function runGitLog(string $rootDir, ?int $days, ?int $commits, ?string $fromDate, ?string $fromHash, string $afterChangelogCommit): array
{
    $format = '%H%x09%h%x09%ad%x09%s';
    if ($commits !== null && $commits > 0) {
        $cmd = 'git -C ' . shellArg($rootDir) . ' log --no-merges -n ' . intval($commits)
            . ' --pretty=format:' . shellArg($format) . ' --date=short';
    } elseif ($days !== null && $days > 0) {
        $cmd = 'git -C ' . shellArg($rootDir) . ' log --no-merges --since=' . shellArg($days . ' days ago')
            . ' --pretty=format:' . shellArg($format) . ' --date=short';
    } elseif ($fromDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
        $cmd = 'git -C ' . shellArg($rootDir) . ' log --no-merges --since=' . shellArg($fromDate)
            . ' --pretty=format:' . shellArg($format) . ' --date=short';
    } elseif ($fromHash !== null && trim($fromHash) !== '') {
        $cmd = 'git -C ' . shellArg($rootDir) . ' log --no-merges '
            . shellArg(trim($fromHash) . '..HEAD')
            . ' --pretty=format:' . shellArg($format) . ' --date=short';
    } elseif ($afterChangelogCommit !== '') {
        $cmd = 'git -C ' . shellArg($rootDir) . ' log --no-merges '
            . shellArg($afterChangelogCommit . '..HEAD')
            . ' --pretty=format:' . shellArg($format) . ' --date=short';
    } else {
        $cmd = 'git -C ' . shellArg($rootDir) . ' log --no-merges -n 30'
            . ' --pretty=format:' . shellArg($format) . ' --date=short';
    }

    $output = [];
    $exitCode = 0;
    exec($cmd, $output, $exitCode);
    if ($exitCode !== 0) {
        fwrite(STDERR, "Errore nella lettura dei commit Git.\n");
        exit(1);
    }

    $items = [];
    foreach ($output as $line) {
        $parts = explode("\t", $line, 4);
        if (count($parts) !== 4) {
            continue;
        }
        $items[] = [
            'hash' => $parts[0],
            'short' => $parts[1],
            'date' => $parts[2],
            'subject' => $parts[3],
            'version' => versionAtCommit($rootDir, $parts[0]),
        ];
    }

    return $items;
}

function cleanCommitSubject(string $subject): string
{
    $subject = trim($subject);
    $subject = preg_replace('/^(feat|fix|docs|style|refactor|test|chore|perf|build|ci)(\([^)]+\))?:\s*/i', '', $subject);
    $subject = trim((string)$subject, " \t\n\r\0\x0B.-");
    if ($subject === '') {
        return '';
    }
    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
        return mb_strtoupper(mb_substr($subject, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($subject, 1, null, 'UTF-8');
    }
    return strtoupper(substr($subject, 0, 1)) . substr($subject, 1);
}

function categoryForCommit(string $subject): string
{
    $s = function_exists('mb_strtolower') ? mb_strtolower($subject, 'UTF-8') : strtolower($subject);

    $rules = [
        'Ticket, Telegram e comunicazioni' => ['ticket', 'telegram', 'mail', 'newsletter', 'messaggi'],
        'Programmi didattici' => ['programmi', 'programma', 'obiettivi minimi', 'live preview', 'stampa programma'],
        'MasterCom, studenti e NOIRC' => ['mastercom', 'noirc', 'studenti', 'genitori', 'classe', 'religione'],
        'Biglietti ed eventi' => ['biglietti', 'ticket trentino volley', 'eventi', 'prenotazioni'],
        'ATA, ferie, permessi e orario' => ['ata', 'segrata', 'ferie', 'permessi', 'orario'],
        'Utenti, ruoli e sessioni' => ['sessione', 'sessioni', 'utente', 'utenti', 'ruolo', 'coordinatore', 'admin', 'agisci'],
        'Interfaccia e mobile' => ['ui', 'mobile', 'telefono', 'header', 'visibilit'],
    ];

    foreach ($rules as $category => $needles) {
        foreach ($needles as $needle) {
            if (strpos($s, $needle) !== false) {
                return $category;
            }
        }
    }

    return 'Manutenzione e correzioni';
}

function summarizeItems(array $items, int $maxExamples = 2): string
{
    $examples = array_slice($items, 0, $maxExamples);
    $texts = [];
    foreach ($examples as $item) {
        $texts[] = rtrim((string)$item['text'], '.');
    }

    if (empty($texts)) {
        return '';
    }

    return implode('; ', $texts);
}

function commitDateRange(array $commits): string
{
    $dates = array_values(array_filter(array_map(function ($commit) {
        return (string)($commit['date'] ?? '');
    }, $commits)));

    if (empty($dates)) {
        return '';
    }

    sort($dates);
    $first = $dates[0];
    $last = $dates[count($dates) - 1];
    return $first === $last ? $first : $first . ' - ' . $last;
}

function formatItalianDate(string $date): string
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
        return $date;
    }

    $months = [
        '01' => 'gennaio',
        '02' => 'febbraio',
        '03' => 'marzo',
        '04' => 'aprile',
        '05' => 'maggio',
        '06' => 'giugno',
        '07' => 'luglio',
        '08' => 'agosto',
        '09' => 'settembre',
        '10' => 'ottobre',
        '11' => 'novembre',
        '12' => 'dicembre',
    ];

    return intval($m[3]) . ' ' . ($months[$m[2]] ?? $m[2]) . ' ' . $m[1];
}

function versionLabelForItems(array $items): string
{
    $versions = [];
    foreach ($items as $item) {
        $version = trim((string)($item['version'] ?? ''));
        if ($version !== '') {
            $versions[$version] = $version;
        }
    }

    if (empty($versions)) {
        return '';
    }

    $versions = array_values($versions);
    usort($versions, 'version_compare');
    if (count($versions) === 1) {
        return 'versione ' . $versions[0];
    }

    return 'versioni ' . $versions[0] . ' - ' . $versions[count($versions) - 1];
}

function buildChangelogSection(array $commits, string $version, string $releaseDate, string $rangeText, int $maxPerCategory, bool $showAll): string
{
    $groups = [];
    $groupsByDate = [];
    foreach ($commits as $commit) {
        $text = cleanCommitSubject($commit['subject']);
        if ($text === '') {
            continue;
        }
        $category = categoryForCommit($commit['subject']);
        $key = function_exists('mb_strtolower') ? mb_strtolower($category . '|' . $text, 'UTF-8') : strtolower($category . '|' . $text);
        $item = [
            'text' => $text,
            'date' => $commit['date'],
            'version' => $commit['version'] ?? '',
        ];
        $groups[$category][$key] = $item;
        $groupsByDate[$commit['date']][$category][$key] = $item;
    }

    $marker = 'gestore-git-changelog-' . preg_replace('/[^A-Za-z0-9._-]/', '-', $version);
    $lines = [];
    $lines[] = '<!-- ' . $marker . ':start -->';
    $lines[] = '## Version ' . $version . ' - ' . $releaseDate;
    $lines[] = '##### Sintesi';
    $lines[] = '- Aggiornamento generato automaticamente dai commit Git ' . $rangeText . '.';
    $dateRange = commitDateRange($commits);
    if ($dateRange !== '') {
        $lines[] = '- Periodo commit: ' . $dateRange . '.';
    }
    $lines[] = '- Commit analizzati: ' . count($commits) . '.';
    $lines[] = '';

    $preferredOrder = [
        'Ticket, Telegram e comunicazioni',
        'Programmi didattici',
        'MasterCom, studenti e NOIRC',
        'Biglietti ed eventi',
        'ATA, ferie, permessi e orario',
        'Utenti, ruoli e sessioni',
        'Interfaccia e mobile',
        'Manutenzione e correzioni',
    ];

    foreach ($preferredOrder as $category) {
        if (empty($groups[$category])) {
            continue;
        }
        $items = array_values($groups[$category]);
        $summary = summarizeItems($items);
        if ($summary !== '') {
            $count = count($items);
            $lines[] = '- ' . $category . ': ' . $count . ' ' . ($count === 1 ? 'modifica' : 'modifiche') . ', tra cui ' . $summary . '.';
        }
    }
    $lines[] = '';

    $lines[] = '##### Dettaglio';
    $lines[] = '';

    krsort($groupsByDate);
    foreach ($groupsByDate as $date => $dateGroups) {
        $dateItems = [];
        foreach ($dateGroups as $categoryItems) {
            foreach ($categoryItems as $item) {
                $dateItems[] = $item;
            }
        }
        $versionLabel = versionLabelForItems($dateItems);
        $lines[] = '##### ' . formatItalianDate((string)$date) . ($versionLabel !== '' ? ' - ' . $versionLabel : '');
        foreach ($preferredOrder as $category) {
            if (empty($dateGroups[$category])) {
                continue;
            }
            $items = array_values($dateGroups[$category]);
            $visibleItems = $showAll ? $items : array_slice($items, 0, $maxPerCategory);
            $lines[] = '**' . $category . '**';
            foreach ($visibleItems as $item) {
                $lines[] = '- ' . $item['text'];
            }
            if (!$showAll && count($items) > count($visibleItems)) {
                $lines[] = '- Altre ' . (count($items) - count($visibleItems)) . ' modifiche minori nella stessa area.';
            }
            $lines[] = '';
        }
        if (end($lines) !== '') {
            $lines[] = '';
        }
    }

    $lines[] = '<!-- ' . $marker . ':end -->';
    $lines[] = '';

    return implode("\n", $lines);
}

function updateChangelog(string $path, string $section, string $version): void
{
    $existing = is_file($path) ? (string)file_get_contents($path) : '';
    $marker = 'gestore-git-changelog-' . preg_replace('/[^A-Za-z0-9._-]/', '-', $version);
    $pattern = '/<!-- ' . preg_quote($marker, '/') . ':start -->(?:.|\R)*?<!-- ' . preg_quote($marker, '/') . ':end -->\R*/u';

    if (preg_match($pattern, $existing)) {
        $updated = preg_replace($pattern, $section, $existing, 1);
    } else {
        $updated = rtrim($section) . "\n" . ltrim($existing);
    }

    file_put_contents($path, $updated);
}

$daysArg = argValue($argv, 'days');
$days = $daysArg !== null ? intval($daysArg) : null;
$commitsArg = argValue($argv, 'commits');
$commitsLimit = $commitsArg !== null ? intval($commitsArg) : null;
$fromDate = argValue($argv, 'from-date');
$fromHash = argValue($argv, 'from');
$maxPerCategory = max(1, intval(argValue($argv, 'max-per-category', '6')));
$showAll = hasFlag($argv, 'all');
$dryRun = hasFlag($argv, 'dry-run');
[$currentVersion, $releaseDate] = readVersionInfo($versionPath);
$version = hasFlag($argv, 'current-version') ? $currentVersion : nextPatchVersion($currentVersion);

$versionOverride = argValue($argv, 'version');
if ($versionOverride !== null && trim($versionOverride) !== '') {
    $version = trim($versionOverride);
}

$afterChangelogCommit = latestChangelogCommit($rootDir);
$rangeText = 'successivi all\'ultimo aggiornamento del changelog';
if ($commitsLimit !== null && $commitsLimit > 0) {
    $rangeText = 'degli ultimi ' . intval($commitsLimit) . ' commit';
} elseif ($days !== null && $days > 0) {
    $rangeText = 'degli ultimi ' . intval($days) . ' giorni';
} elseif ($fromDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
    $rangeText = 'dal ' . $fromDate;
} elseif ($fromHash !== null && trim($fromHash) !== '') {
    $rangeText = 'successivi al commit ' . trim($fromHash);
} elseif ($afterChangelogCommit === '') {
    $rangeText = 'degli ultimi 30 commit';
}

$commitItems = runGitLog($rootDir, $days, $commitsLimit, $fromDate, $fromHash, $afterChangelogCommit);
if (empty($commitItems)) {
    echo "Nessun commit trovato dopo l'ultimo aggiornamento del changelog.\n";
    exit(0);
}

$section = buildChangelogSection($commitItems, $version, $releaseDate, $rangeText, $maxPerCategory, $showAll);

if ($dryRun) {
    echo $section;
    exit(0);
}

updateChangelog($changelogPath, $section, $version);
echo "Changelog aggiornato con " . count($commitItems) . " commit.\n";
echo "File: " . $changelogPath . "\n";
echo "Versione changelog: " . $version . "\n";
