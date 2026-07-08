<?php
// Disabilita i Warning/Notice di PHP 8 per mantenere l'interfaccia pulita
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

require_once __DIR__ . '/../common/checkSession.php';

// Controllo permessi
if (!haRuolo('dirigente')) {
    die("Accesso negato. Solo la Dirigente può visualizzare questo resoconto.");
}

require_once __DIR__ . '/../docente/oreFatteAggiorna.php';

// Query per estrarre i docenti con le ore di recupero (alias ore_recupero_tot)
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
                            
                            // --- ALGORITMO DI COMPENSAZIONE FUIS ---
                            // 1. Dati base
                            $sost_fatte = $dati['oreSostituzione'];
                            $sost_dov = $dati['oreSostituzioniDovute'];
                            $funz_fatte = $dati['oreFunzionali'];
                            $funz_dov = $dati['oreFunzionaliDovute'];
                            $stud_fatte = $dati['oreConStudenti'];
                            $stud_dov = $dati['oreConStudentiDovute'];
                            
                            $delta_insegnamento = ($sost_fatte + $stud_fatte) - ($sost_dov + $stud_dov);
                            $delta_funz = $funz_fatte - $funz_dov;
                            
                            $pagare_stud = 0;
                            $pagare_funz = 0;
                            
                            // Logica di compensazione
                            if ($delta_insegnamento >= 0 && $delta_funz >= 0) {
                                $pagare_stud = $delta_insegnamento;
                                $pagare_funz = $delta_funz;
                            } elseif ($delta_insegnamento > 0 && $delta_funz < 0) {
                                // 1 ora Insegnamento copre 1 ora Funzionale di debito
                                $pagare_stud = max(0, $delta_insegnamento - abs($delta_funz));
                            } elseif ($delta_insegnamento < 0 && $delta_funz > 0) {
                                // 2 ore Funzionali coprono 1 ora di debito Insegnamento
                                $pagare_funz = max(0, $delta_funz - (abs($delta_insegnamento) * 2));
                            }
                            
                            $euro_ore = ($pagare_stud * 38.50) + ($pagare_funz * 19.225);
                            
                            // 2. Corsi Recupero
                            $ore_rec = (int)($row['ore_recupero_tot'] ?? 0);
                            $euro_rec = max(0, $ore_rec - 10) * 55;
                            
                            // 3. Estrai l'importo della Diaria Viaggi
                            // Assicurati che $dati['diariaImporto'] esista. Fai un cast a float per sicurezza.

                            $q = "SELECT giorni_senza_pernottamento, giorni_con_pernottamento 
                                  FROM viaggio_diaria_prevista 
                                  WHERE docente_id = {$row['id']} 
                                  AND anno_scolastico_id = $__anno_scolastico_corrente_id";
                            $res = dbGetAll($q);
                            
                            // Estraiamo i valori in modo sicuro dai risultati dell'array
                            $g = (int)($res[0]['giorni_senza_pernottamento'] ?? 0);
                            $n = (int)($res[0]['giorni_con_pernottamento'] ?? 0);
                            
                            $euro_diaria = ($g * 70) + ($n * 100);

                            #$euro_diaria = isset($dati['diariaImporto']) ? (float)$dati['diariaImporto'] : 0.0;
                            
                            // 4. Totale Stimato (Ore + Recupero + Diaria)
                            $totale_stimato = $euro_ore + $euro_rec + $euro_diaria;

                            // Logica badge delta
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