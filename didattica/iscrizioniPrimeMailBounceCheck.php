<?php

require_once '../common/checkSession.php';
require_once '../common/iscrizioniPrimeLib.php';

ruoloRichiesto('admin', 'segreteria-didattica', 'dirigente');
header('Content-Type: application/json; charset=utf-8');
@set_time_limit(180);

$maxResults = isset($_POST['max']) ? intval($_POST['max']) : 40;
$maxResults = max(1, min(100, $maxResults));
$tipoIscrizione = isset($_POST['tipo_iscrizione']) ? iscrizioniPrimeNormalizeTipoIscrizione($_POST['tipo_iscrizione']) : '';

$summary = iscrizioniPrimeMailBounceSummary($maxResults, $tipoIscrizione);
$summary['export_url'] = 'iscrizioniPrimeMailBounceExport.php?tipo_iscrizione=' . rawurlencode($tipoIscrizione) . '&days=30';

echo json_encode($summary, JSON_UNESCAPED_UNICODE);

?>
