<?php
// Disabilita i Warning per pulizia interfaccia
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

require_once __DIR__ . '/../common/checkSession.php';

// Controllo permessi
if (!haRuolo('dirigente')) {
    die("Accesso negato. Solo la Dirigente può visualizzare questo resoconto.");
}

require_once __DIR__ . '/../docente/oreFatteAggiorna.php';
// Includiamo il file contenente la funzione dedicata alle diarie
require_once __DIR__ . '/../docente/viaggioDiariaPrevistaReadRecords.php';

// Query estrazione docenti con ore di recupero
$query_docenti = "
    SELECT d.id, d.cognome, d.nome,
    (SELECT SUM(numero_ore) FROM corso_di_recupero cr WHERE cr.docente_id = d.id AND cr.anno_scolastico_id = $__anno_scolastico_corrente_id) as ore_recupero_tot
    FROM docente d WHERE attivo = 1 ORDER BY cognome, nome";
$docenti = dbGetAll($query_docenti);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Resoconto FUIS - Vista Dirigente</title>
    <?php 
    require_once __DIR__ . '/../common/header-common.php';
    require_once __DIR__ . '/../common/style.php'; 
    ?>
    <style>
        .table { font-size: 13px; }
        .highlight-import { font-size: 1.1em; color: #27ae60; font-weight: bold; }
        .badge-delta { font-size: 10px; padding: 2px 5px; margin-left: 5px; }
    </style>
</head>
<body>
    <div class="container-fluid" style="margin-top:20px;">
        <div class="panel panel-primary">
            <div class="panel-heading">
                <h3 class="panel-title">Prospetto Liquidazione FUIS - A.S. <?php echo htmlspecialchars($__anno_scolastico_corrente_anno); ?></h3>
            </div>
            <div class="panel-body">
                <table class="table table-striped table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Docente</th>
                            <th>Sostituzioni<br><small>(Fatte / Pianif.)</small></th>
                            <th>Funzionali<br><small>(Fatte / Pianif.)</small></th>
                            <th>Studenti<br><small>(Fatte / Pianif.)</small></th>
                            <th>Corsi Recupero<br><small>(h / €)</small></th>
                            <th>Diaria Viaggi<br><small>(€)</small></th>
                            <th>Totale Stimato</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        foreach ($docenti as $row) {
                            $dati = oreFatteAggiorna(true, $row['id'], 'dirigente', '', false);
                            
                            // --- UTILIZZO FUNZIONE CORE PER LA DIARIA ---
                            // Chiamiamo la funzione di sistema per recuperare i dati pronti
                            $diaria_data = viaggioDiariaPrevistaReadRecords(true, $row['id'], 'dirigente', '', true);

                            $euro_diaria = (float)($diaria_data['diariaImporto'] ?? 0);
                            
                            // 1. Calcolo ore eccedenti
                            $sost_delta = $dati['oreSostituzione'] - $dati['oreSostituzioniDovute'];
                            $stud_delta = $dati['oreConStudenti'] - $dati['oreConStudentiDovute'];
                            $funz_delta = $dati['oreFunzionali'] - $dati['oreFunzionaliDovute'];
                            
                            // Logica di compensazione
                            $tot_insegnamento = ($sost_delta > 0 ? $sost_delta : 0) + ($stud_delta > 0 ? $stud_delta : 0);
                            $tot_funz_deficit = ($funz_delta < 0 ? abs($funz_delta) : 0);
                            $tot_stud_deficit = ($stud_delta < 0 ? abs($stud_delta) : 0);
                            $tot_funz_eccedenza = ($funz_delta > 0 ? $funz_delta : 0);

                            $pagare_stud = 0;
                            $pagare_funz = 0;

                            if ($tot_insegnamento > $tot_funz_deficit) {
                                $pagare_stud = $tot_insegnamento - $tot_funz_deficit;
                            } elseif ($tot_funz_eccedenza > ($tot_stud_deficit * 2)) {
                                $pagare_funz = $tot_funz_eccedenza - ($tot_stud_deficit * 2);
                            }

                            $euro_ore = ($pagare_stud * 38.50) + ($pagare_funz * 19.225);
                            
                            // 2. Corsi Recupero
                            $ore_rec = (int)($row['ore_recupero_tot'] ?? 0);
                            $euro_rec = max(0, $ore_rec - 10) * 55;
                            
                            // 3. Totale Stimato
                            $totale_stimato = $euro_ore + $euro_rec + $euro_diaria;

                            $badge = function($fatte, $dovute) {
                                $delta = $fatte - $dovute;
                                if ($delta > 0) return "<span class='label label-danger badge-delta'>+$delta</span>";
                                if ($delta < 0) return "<span class='label label-warning badge-delta'>$delta</span>";
                                return "";
                            };
                            ?>
                            <tr>
                                <td class="text-left"><strong><?php echo htmlspecialchars($row['cognome'] . ' ' . $row['nome']); ?></strong></td>
                                <td><?php echo "{$dati['oreSostituzione']} / {$dati['oreSostituzioniDovute']}"; ?> <?php echo $badge($dati['oreSostituzione'], $dati['oreSostituzioniDovute']); ?></td>
                                <td><?php echo "{$dati['oreFunzionali']} / {$dati['oreFunzionaliDovute']}"; ?> <?php echo $badge($dati['oreFunzionali'], $dati['oreFunzionaliDovute']); ?></td>
                                <td><?php echo "{$dati['oreConStudenti']} / {$dati['oreConStudentiDovute']}"; ?> <?php echo $badge($dati['oreConStudenti'], $dati['oreConStudentiDovute']); ?></td>
                                <td>
                                    <?php echo $ore_rec . " h"; ?><br>
                                    <small class="text-muted"><?php echo number_format($euro_rec, 2, ',', '.'); ?> €</small>
                                </td>
                                <td>
                                    <?php echo number_format($euro_diaria, 2, ',', '.'); ?> €
                                </td>
                                <td class="highlight-import"><?php echo number_format($totale_stimato, 2, ',', '.'); ?> €</td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>