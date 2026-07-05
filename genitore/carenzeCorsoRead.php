<?php

/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
require_once '../common/connect.php';
require_once '../common/carenze_course_detail_lib.php';

ruoloRichiesto('genitore');

header('Content-Type: application/json; charset=utf-8');

if (!getSettingsValue('carenzeObiettiviMinimi', 'visibile_corsi_genitori', true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Calendario corso non visibile']);
    exit;
}

function carenzeGenitoreCanAccessStudente($idStudente, $idGenitore)
{
    $idStudente = intval($idStudente);
    $idGenitore = intval($idGenitore);
    if ($idStudente <= 0 || $idGenitore <= 0) {
        return false;
    }

    $count = dbGetValue("
        SELECT COUNT(*)
        FROM genitori_studenti
        WHERE id_genitore = {$idGenitore}
          AND id_studente = {$idStudente}
    ");
    return intval($count) > 0;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodo non consentito']);
    exit;
}

$idCorso = isset($_POST['id_corso']) ? intval($_POST['id_corso']) : 0;
$idStudente = isset($_POST['id_studente']) ? intval($_POST['id_studente']) : 0;

if (!carenzeGenitoreCanAccessStudente($idStudente, intval($__genitore_id))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Studente non disponibile per il genitore']);
    exit;
}

if ($idCorso <= 0 || !carenzeCourseStudentIsEnrolled($idCorso, $idStudente)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Corso non disponibile per lo studente']);
    exit;
}

echo json_encode(carenzeCourseBuildDetailHtml($idCorso, $idStudente), JSON_UNESCAPED_UNICODE);
