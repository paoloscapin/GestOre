<?php

require_once __DIR__ . '/connect.php';

function gestoreNormalizeSesso($value): ?string
{
    $value = strtoupper(trim((string)$value));
    if (in_array($value, ['M', 'MASCHIO', 'MASCHILE'], true)) {
        return 'M';
    }
    if (in_array($value, ['F', 'FEMMINA', 'FEMMINILE'], true)) {
        return 'F';
    }
    return null;
}

function gestoreSessoDaCodiceFiscale($codiceFiscale): ?string
{
    $cf = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)$codiceFiscale));
    if (strlen($cf) < 11) {
        return null;
    }

    $day = intval(substr($cf, 9, 2));
    if ($day >= 1 && $day <= 31) {
        return 'M';
    }
    if ($day >= 41 && $day <= 71) {
        return 'F';
    }
    return null;
}

function gestoreSessoDaInputOCodiceFiscale($sesso, $codiceFiscale): ?string
{
    return gestoreNormalizeSesso($sesso) ?? gestoreSessoDaCodiceFiscale($codiceFiscale);
}

function gestoreEnsureStudenteSessoColumn(): void
{
    $column = dbGetFirst("SHOW COLUMNS FROM studente LIKE 'sesso'");
    if ($column == null) {
        dbExec("ALTER TABLE studente ADD COLUMN sesso CHAR(1) NULL AFTER codice_fiscale");
    }
}

