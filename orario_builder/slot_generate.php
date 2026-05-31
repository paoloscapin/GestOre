<?php
require_once __DIR__ . '/orario_builder_lib.php';

$idScenario = ob_int($_POST['id_scenario'] ?? 0);
$giorni = ob_int($_POST['giorni'] ?? 6);
$oreGiorno = ob_int($_POST['ore_giorno'] ?? 6);

if ($idScenario <= 0) {
    die('Scenario non valido');
}

$orari = [
    1  => ['07:50:00', '08:40:00'],
    2  => ['08:40:00', '09:30:00'],
    3  => ['09:30:00', '10:20:00'],
    4  => ['10:30:00', '11:20:00'],
    5  => ['11:20:00', '12:10:00'],
    6  => ['12:10:00', '13:00:00'],
    7  => ['13:00:00', '13:50:00'],
    8  => ['13:50:00', '14:40:00'],
    9  => ['14:40:00', '15:30:00'],
    10 => ['15:30:00', '16:20:00'],
    11 => ['16:20:00', '17:10:00'],
    12 => ['17:10:00', '18:00:00'],
    13 => ['18:00:00', '18:50:00'],
    14 => ['18:50:00', '19:40:00'],
    15 => ['19:40:00', '20:30:00'],
    16 => ['20:30:00', '21:20:00'],
    17 => ['21:30:00', '22:20:00'],
    18 => ['22:20:00', '23:10:00']
];

for ($g = 1; $g <= $giorni; $g++) {
    for ($o = 1; $o <= $oreGiorno; $o++) {
        $inizio = $orari[$o][0];
        $fine = $orari[$o][1];

        dbExec("
            INSERT INTO orario_slot (
                id_scenario,
                giorno,
                ora_index,
                ora_inizio,
                ora_fine,
                attivo
            ) VALUES (
                $idScenario,
                $g,
                $o,
                '$inizio',
                '$fine',
                1
            )
            ON DUPLICATE KEY UPDATE
                ora_inizio = VALUES(ora_inizio),
                ora_fine = VALUES(ora_fine),
                attivo = 1
        ");
    }
}

ob_redirect("slot.php?id_scenario=$idScenario");