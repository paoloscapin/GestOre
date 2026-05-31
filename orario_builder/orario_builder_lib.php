<?php
require_once __DIR__ . '/../common/checkSession.php';
require_once __DIR__ . '/../common/connect.php';

function ob_int($v, $default = 0) {
    return is_numeric($v) ? intval($v) : $default;
}

function ob_float($v, $default = 0) {
    $v = str_replace(',', '.', trim((string)$v));
    return is_numeric($v) ? floatval($v) : $default;
}

function ob_h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function ob_redirect($url) {
    header("Location: $url");
    exit;
}

function ob_get_scenari() {
    return dbGetAll("
        SELECT *
        FROM orario_scenario
        ORDER BY id DESC
    ") ?: [];
}

function ob_get_scenario($id) {
    $id = intval($id);
    return dbGetFirst("
        SELECT *
        FROM orario_scenario
        WHERE id = $id
        LIMIT 1
    ");
}

function ob_get_anni_scolastici() {
    return dbGetAll("
        SELECT *
        FROM anno_scolastico
        ORDER BY id DESC
    ") ?: [];
}

function ob_anno_label($a) {
    if (isset($a['descrizione']) && trim($a['descrizione']) !== '') {
        return $a['descrizione'];
    }
    if (isset($a['anno']) && trim($a['anno']) !== '') {
        return $a['anno'];
    }
    if (isset($a['nome']) && trim($a['nome']) !== '') {
        return $a['nome'];
    }
    return 'Anno ID ' . intval($a['id']);
}

function ob_get_classi_tutte() {
    return dbGetAll("
        SELECT id, classe, anno, attiva
        FROM classi
        ORDER BY anno, classe
    ") ?: [];
}

function ob_get_classi_attive() {
    return dbGetAll("
        SELECT id, classe, anno, id_primo_indirizzo, id_secondo_indirizzo
        FROM classi
        WHERE attiva = 1
        ORDER BY anno, classe
    ") ?: [];
}

function ob_get_materie() {
    return dbGetAll("
        SELECT id, nome, codice
        FROM materia
        ORDER BY nome
    ") ?: [];
}

function ob_get_aule() {
    return dbGetAll("
        SELECT *
        FROM aule
        ORDER BY piano, codice
    ") ?: [];
}

function ob_get_docenti() {
    return dbGetAll("
        SELECT id, cognome, nome, email, username
        FROM docente
        WHERE attivo = 1
        ORDER BY cognome, nome
    ") ?: [];
}