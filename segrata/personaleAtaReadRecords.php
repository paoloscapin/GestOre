<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('dirigente', 'segreteria-ata');

function paEsc($s)
{
    if (isset($GLOBALS['__conn']) && $GLOBALS['__conn']) return mysqli_real_escape_string($GLOBALS['__conn'], $s);
    if (isset($GLOBALS['conn']) && $GLOBALS['conn']) return mysqli_real_escape_string($GLOBALS['conn'], $s);
    return addslashes($s);
}

function formatNome($str)
{
    $str = strtolower(trim($str));
    return ucwords($str);
}

$soloAttivi = isset($_GET["soloAttivi"]) ? intval($_GET["soloAttivi"]) : 1;
$search     = trim($_GET["search"] ?? '');
$idUfficio  = isset($_GET["id_ufficio"]) && $_GET["id_ufficio"] !== '' ? intval($_GET["id_ufficio"]) : 0;
$idProfilo  = isset($_GET["id_profilo"]) && $_GET["id_profilo"] !== '' ? intval($_GET["id_profilo"]) : 0;
$tipoContratto = trim($_GET["tipo_contratto"] ?? '');
$orderBy  = $_GET['order_by'] ?? 'cognome';
$orderDir = strtoupper($_GET['order_dir'] ?? 'ASC');
if (!in_array($orderDir, ['ASC','DESC'])) $orderDir = 'ASC';

$allowedOrder = [
    'cognome' => 'p.cognome',
    'nome' => 'p.nome',
    'email' => 'p.email',
    'tipo_contratto' => 'p.tipo_contratto',
    'profilo' => 'pr.nome',
    'ufficio' => 'u.nome',
    'attivo' => 'p.attivo'
];

$orderSql = $allowedOrder[$orderBy] ?? 'p.cognome';

function sortArrow($field, $currentField, $currentDir) {
    if ($field !== $currentField) return '';
    return $currentDir === 'ASC' ? ' <span style="font-size:11px;">▲</span>' : ' <span style="font-size:11px;">▼</span>';
}

$where = [];
if ($soloAttivi == 1) $where[] = "p.attivo = 1";

if ($search !== '') {
    $s = paEsc($search);
    $where[] = "(
        p.cognome LIKE '%$s%' OR
        p.nome LIKE '%$s%' OR
        p.email LIKE '%$s%' OR
        p.username LIKE '%$s%' OR
        p.matricola LIKE '%$s%' OR
        p.codice_fiscale LIKE '%$s%' OR
        p.tipo_contratto LIKE '%$s%' OR
        pr.codice LIKE '%$s%' OR
        pr.nome LIKE '%$s%' OR
        u.nome LIKE '%$s%'
    )";
}

$where = [];
if ($soloAttivi == 1) $where[] = "p.attivo = 1";

if ($search !== '') {
    $s = paEsc($search);
    $where[] = "(
        p.cognome LIKE '%$s%' OR
        p.nome LIKE '%$s%' OR
        p.email LIKE '%$s%' OR
        p.username LIKE '%$s%' OR
        p.matricola LIKE '%$s%' OR
        p.codice_fiscale LIKE '%$s%' OR
        p.tipo_contratto LIKE '%$s%' OR
        pr.codice LIKE '%$s%' OR
        pr.nome LIKE '%$s%' OR
        u.nome LIKE '%$s%'
    )";
}

if ($idUfficio > 0) $where[] = "curr.id_ufficio = $idUfficio";
if ($idProfilo > 0) $where[] = "p.id_profilo = $idProfilo";
if ($tipoContratto !== '') $where[] = "p.tipo_contratto = '".paEsc($tipoContratto)."'";

$sqlWhere = count($where) ? ("WHERE " . implode(" AND ", $where)) : "";

$query = "
SELECT
    p.*,
    pr.nome AS profilo_nome,
    curr.id AS assegnazione_id,
    curr.id_ufficio,
    u.nome AS ufficio_nome,
    (
        SELECT COUNT(*)
        FROM personale_ata_assegnazioni sx
        WHERE sx.username = p.username
    ) AS storico_count
FROM personale_ata p
LEFT JOIN personale_ata_profili pr ON pr.id = p.id_profilo
LEFT JOIN (
    SELECT a1.*
    FROM personale_ata_assegnazioni a1
    INNER JOIN (
        SELECT username, MAX(id) AS max_id
        FROM personale_ata_assegnazioni
        WHERE attiva = 1 OR data_fine IS NULL
        GROUP BY username
    ) a2 ON a1.username = a2.username AND a1.id = a2.max_id
) curr ON curr.username = p.username
LEFT JOIN personale_ata_uffici u ON u.id = curr.id_ufficio
$sqlWhere
ORDER BY $orderSql $orderDir, p.cognome, p.nome
";

$rows = dbGetAll($query);
if (!is_array($rows)) $rows = [];

$totale = count($rows);
$attivi = 0;
$conUfficio = 0;
$conProfilo = 0;

foreach ($rows as $r) {
    if (intval($r['attivo']) === 1) $attivi++;
    if (!empty($r['id_ufficio'])) $conUfficio++;
    if (!empty($r['id_profilo'])) $conProfilo++;
}

echo '<script>';
echo 'window.personaleAtaStats = ' . json_encode([
    'totale' => $totale,
    'attivi' => $attivi,
    'con_ufficio' => $conUfficio,
    'con_profilo' => $conProfilo
], JSON_UNESCAPED_UNICODE) . ';';
echo '</script>';

echo '<div class="table-wrapper"><table class="table table-bordered table-striped table-green">';
echo '<tr>
    <th style="cursor:pointer;" onclick="ordina(\'cognome\')">Cognome'.sortArrow('cognome', $orderBy, $orderDir).'</th>
    <th style="cursor:pointer;" onclick="ordina(\'nome\')">Nome'.sortArrow('nome', $orderBy, $orderDir).'</th>
    <th style="cursor:pointer;" onclick="ordina(\'email\')">Email'.sortArrow('email', $orderBy, $orderDir).'</th>
    <th style="cursor:pointer;" onclick="ordina(\'tipo_contratto\')">Tipo contratto'.sortArrow('tipo_contratto', $orderBy, $orderDir).'</th>
    <th style="cursor:pointer;" onclick="ordina(\'profilo\')">Profilo'.sortArrow('profilo', $orderBy, $orderDir).'</th>
    <th style="cursor:pointer;" onclick="ordina(\'ufficio\')">Ufficio'.sortArrow('ufficio', $orderBy, $orderDir).'</th>
    <th style="cursor:pointer;" onclick="ordina(\'attivo\')" class="text-center">Attivo'.sortArrow('attivo', $orderBy, $orderDir).'</th>
    <th class="text-center">Azioni</th>
</tr>';

if (!$rows) {
    echo '<tr><td colspan="8" class="text-center">Nessun dipendente trovato.</td></tr>';
} else {
    foreach ($rows as $row) {
        $storicoCount = intval($row['storico_count']);
        $storicoBadgeClass = $storicoCount > 0 ? 'label label-info' : 'label label-default';

        echo '<tr>';
        echo '<td>' . htmlspecialchars(formatNome($row['cognome'])) . '</td>';
        echo '<td>' . htmlspecialchars(formatNome($row['nome'])) . '</td>';
        echo '<td>' . htmlspecialchars($row['email']) . '</td>';
        echo '<td>' . htmlspecialchars($row['tipo_contratto']) . '</td>';
        echo '<td>' . htmlspecialchars($row['profilo_nome'] ?: '-') . '</td>';
        echo '<td>' . htmlspecialchars($row['ufficio_nome'] ?: '-') . '</td>';
        if (intval($row['attivo']) === 1) {
            echo '<td class="text-center"><span class="label label-success">Attivo</span></td>';
        } else {
            echo '<td class="text-center"><span class="label label-default">Non attivo</span></td>';
        }

        echo '<td class="text-center">
            <button onclick="personaleAtaGetDetails(' . intval($row['id']) . ')" class="btn btn-warning btn-xs" title="Apri scheda">
                <span class="glyphicon glyphicon-pencil"></span>
            </button>
            <button onclick="personaleAtaDelete(' . intval($row['id']) . ', ' . json_encode($row['cognome']) . ', ' . json_encode($row['nome']) . ')" class="btn btn-danger btn-xs" title="Elimina">
                <span class="glyphicon glyphicon-trash"></span>
            </button>
        </td>';
        echo '</tr>';
    }
}

echo '</table></div>';
