<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */
require_once '../common/checkSession.php';
?>

<!DOCTYPE html>
<html>
<head>

<?php

require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-toggle.php';
require_once '../common/_include_bootstrap-select.php';
require_once '../common/_include_bootstrap-notify.php';
require_once '../common/__Minuti.php';
ruoloRichiesto('admin', 'segreteria-didattica', 'segreteria-docenti', 'dirigente');

$anno_id = intval($_GET['anno_id'] ?? $__anno_scolastico_corrente_id);
if ($anno_id <= 0) {
    $anno_id = intval($__anno_scolastico_corrente_id);
}

$nome_anno_scolastico = dbGetValue("SELECT anno FROM anno_scolastico WHERE id=$anno_id");
echo '<title>Storico Sportelli ' . $nome_anno_scolastico.' - '.getSettingsValue('local','nomeIstituto', '') . '</title>';
?>
<style>
    #reportSportelliExportOverlay {
        position: fixed;
        z-index: 99999;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, .42);
    }
    #reportSportelliExportOverlay .export-wait-box {
        width: min(420px, calc(100vw - 32px));
        padding: 24px 28px;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 14px 40px rgba(0, 0, 0, .28);
        text-align: center;
    }
    #reportSportelliExportOverlay .export-title {
        margin-bottom: 10px;
        font-size: 20px;
        font-weight: 700;
        color: #263747;
    }
    #reportSportelliExportOverlay .export-detail {
        margin-bottom: 14px;
        color: #52616e;
    }
    #reportSportelliExportOverlay .export-percent {
        margin-bottom: 10px;
        font-size: 28px;
        font-weight: 700;
        color: #1f6f9f;
    }
    #reportSportelliExportOverlay .progress {
        margin-bottom: 0;
    }
</style>
</head>

<body >
<div class="hidden-print">
<?php
if (!empty($__utente_ruolo) && $__utente_ruolo === 'admin') {
    require_once '../common/header-admin.php';
} elseif (!empty($__utente_ruolo) && $__utente_ruolo === 'segreteria-didattica') {
    require_once '../common/header-didattica.php';
} elseif (!empty($__utente_ruolo) && $__utente_ruolo === 'segreteria-docenti') {
    require_once '../common/header-segreteria.php';
} elseif (!empty($__utente_ruolo) && $__utente_ruolo === 'dirigente') {
    require_once '../common/header-dirigente.php';
}
?>
</div>

<!-- Content Section -->
<div class="container-fluid">
<div class="panel panel-orange4 hidden-print" style="margin-top:12px;">
    <div class="panel-heading">
        <form class="form-inline" method="get" action="reportSportelli.php" style="display:flex; gap:12px; align-items:end; flex-wrap:wrap;">
            <div class="form-group">
                <label for="anno_id" style="display:block;">Anno scolastico</label>
                <select id="anno_id" name="anno_id" class="selectpicker" data-width="180px" data-style="btn-yellow4">
                    <?php
                    foreach (dbGetAll("SELECT id, anno FROM anno_scolastico ORDER BY id DESC") as $anno) {
                        $selected = intval($anno['id']) === intval($anno_id) ? ' selected' : '';
                        echo '<option value="' . intval($anno['id']) . '"' . $selected . '>' . htmlspecialchars($anno['anno'], ENT_QUOTES, 'UTF-8') . '</option>';
                    }
                    ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">
                <span class="glyphicon glyphicon-search"></span> Visualizza
            </button>
            <button type="button" class="btn btn-danger" onclick="reportSportelliDownload('pdf')">
                <span class="glyphicon glyphicon-file"></span> PDF
            </button>
            <button type="button" class="btn btn-success" onclick="reportSportelliDownload('xlsx')">
                <span class="glyphicon glyphicon-list-alt"></span> XLS
            </button>
        </form>
    </div>
</div>
<div id="reportSportelliExportOverlay">
    <div class="export-wait-box">
        <div class="export-title">Preparazione export</div>
        <div class="export-detail" id="reportSportelliExportDetail">Sto generando il file. Attendi qualche istante...</div>
        <div class="export-percent" id="reportSportelliExportPercent">0%</div>
        <div class="progress progress-striped active">
            <div id="reportSportelliExportProgress" class="progress-bar progress-bar-info" style="width: 0%;">0%</div>
        </div>
    </div>
</div>

<?php

function formatNoZero($value) {
    return ($value != 0) ? number_format($value,2) : ' ';
}

function formatDate($value) {
	$dateFormatter = '%e %b %Y';
	if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
		$dateFormatter = '%#d %b %Y';
	}
	$oldLocale = setlocale(LC_TIME, 'ita', 'it_IT');
	$result = utf8_encode(strftime($dateFormatter, strtotime($value)));
	setlocale(LC_TIME, $oldLocale);

    return $result;
}

// totali
$totaleOreSportelliIstituto = 0;

// tag
$accettato = '<td class="col-md-1 text-center"><span style="color:green !important;font-weight:bold">&#10004;</span></td>';
$contestataMarker = '<span style="color:red !important;font-weight:bold">&#10008;</span>';
$accettataMarker = '<span style="color:green !important;font-weight:bold">&#10004;</span>';

// Intestazione pagina
$dataContenuto = '';
$dataCopertina = '';
$dataConsuntivo = '';

// prima pagina
$dataCopertina .= '<h2 style="text-align: center; padding-bottom: 1cm;"><img style="text-align: center;" alt="" src="data:image/png;base64,'. base64_encode(dbGetValue("SELECT src FROM immagine WHERE nome = 'Logo.png'")).'" title=""></h2>';
$dataCopertina .= '<h3 style="text-align: center; padding-bottom: 3cm;">'.getSettingsValue('local','nomeIstituto', '').'</h3>';
$dataCopertina .= '<h2 style="text-align: center;">Report Sportelli anno scolastico '.$nome_anno_scolastico.'</h2>';

// classe corrente
$classe = '';
$listaNessunoSportello = '';

// cicla gli studenti
foreach(dbGetAll("
        SELECT 
        s.id,
        s.cognome,
        s.nome,
        c.classe AS classe 
        FROM studente s 
        INNER JOIN studente_frequenta sf ON sf.id_studente=s.id 
        INNER JOIN classi c ON sf.id_classe=c.id 
        WHERE sf.id_anno_scolastico=$anno_id
        ORDER BY c.classe ASC, s.cognome ASC, s.nome ASC;") as $studente) {

    // anche se non lo salto, controllo se effettivamente ha fatto sportelli
	$haFattoSportelli = false;
    $oreSportelli = 0;
    $studenteId = $studente['id'];
	$data = '';

    // per prima cosa controlla se è cambiata la classe
    if ($classe != $studente['classe']) {
        // tracrive la liste di nessuno sportello della classe precedente (se esiste)
        if ($listaNessunoSportello != '') {
            $data .= '<h4 style="background-color: #e3bf9b !important;">Nella classe '. $classe .' non hanno frequentato sportelli gli studenti:</h4>';
            $data .= $listaNessunoSportello;
        }

        // inserisce intestazione della nuova classe
        $data .= '<h2 style="page-break-before: always;text-align: center;">'. $studente['classe'].'</h2>';
        $classe = $studente['classe'];

        // resetta la lista di nessuno sportello
        $listaNessunoSportello = '';
    }


    $query = "SELECT
            sportello_studente.argomento AS argomento_studente,
            sportello_studente.presente AS studente_presente,
            sportello_studente.iscritto AS studente_iscritto,
            materia.nome AS nome_materia,
            docente.cognome AS cognome_docente,
            docente.nome AS nome_docente,
            sportello.*
        FROM sportello_studente
        INNER JOIN sportello ON sportello_studente.sportello_id = sportello.id
        INNER JOIN docente ON sportello.docente_id = docente.id
        INNER JOIN materia ON sportello.materia_id = materia.id
        WHERE sportello_studente.studente_id = $studenteId
        AND sportello.anno_scolastico_id = $anno_id
        AND (sportello_studente.presente = 1 OR sportello_studente.iscritto = 1)
        ORDER BY materia.nome ASC, sportello.data ASC
    ";

    $sportelloList = dbGetAll($query);
    if (!empty($sportelloList)) {
        // intestazione dello studente
        $data .= '<h4 style="background-color: #9be3bf !important;">'.$studente['cognome'] . ' ' . $studente['nome']. ' ('.$studente['classe'].')'.'</h4>';
		$data .= '<table class="table table-bordered table-striped table-green"><thead><tr><th class="col-md-2 text-left">Materia</th><th class="col-md-1 text-center">Data</th><th class="col-md-2 text-left">Docente</th><th class="col-md-4 text-center">Argomento</th><th class="col-md-1 text-center">Stato</th><th class="col-md-1 text-center">Ore</th></tr></thead><tbody>';
		foreach($sportelloList as $sportello) {
            $presente = intval($sportello['studente_presente']) === 1;
            $rowClass = $presente ? '' : ' class="danger"';
            $stato = $presente ? '<span class="label label-success">Presente</span>' : '<span class="label label-danger">Assente</span>';
			$data .= '<tr' . $rowClass . '><td>'.$sportello['nome_materia'].'</td><td class="text-center">'.formatDate($sportello['data']).'</td><td>'.$sportello['nome_docente'].' '.$sportello['cognome_docente'].'</td><td>'.$sportello['argomento_studente'].'</td><td class="text-center">'.$stato.'</td><td class="text-center">'.$sportello['numero_ore'].'</td></tr>';
            if ($presente) {
			    $oreSportelli = $oreSportelli + $sportello['numero_ore'];
            }
		}
		$data .= '</tbody><tfooter>';
		$data .='<tr><td colspan="5" class="text-right"><strong>Totale ore sportelli frequentate:</strong></td><td class="text-center funzionale"><strong>' . $oreSportelli . '</strong></td></tr>';
		$data .='</tfooter></table>';
		$data .= '<hr>';
		$haFattoSportelli = true;
        $totaleOreSportelliIstituto += $oreSportelli;
    } else {
        // intestazione dello studente in arancione
        $listaNessunoSportello .= '<p>' . $studente['cognome'] . ' ' . $studente['nome']. '</p>';
    }

	// se ha trovato degli sportelli, lo include normalmente
	if ($haFattoSportelli || true) {
		$dataContenuto = $dataContenuto . $data;
	}
}
// per l'ultima classe considerata
if ($listaNessunoSportello != '') {
    $data .= '<h4 style="background-color: #e3bf9b !important;">Nella classe '. $classe .' non hanno frequentato sportelli gli studenti:</h4>';
    $data .= $listaNessunoSportello;
	if ($haFattoSportelli || true) {
		$dataContenuto = $dataContenuto . $data;
	}
}

// stampa i totali di istituto
$dataConsuntivo .= '<hr style="page-break-before: always;">';
$dataConsuntivo .= '<h2 style="text-align: center; padding-top: 3cm; padding-bottom: 2cm;">Statistiche Sportelli '.$nome_anno_scolastico.'</h2>';

$dataConsuntivo .= '<h4 style="background-color: #9be3bf !important;">Ore complessive frequentate dagli studenti: ' . $totaleOreSportelliIstituto . '</h4>';
$dataConsuntivo .= '<hr>';

$dataConsuntivo .= '<table class="table table-bordered table-striped table-green">';

$dataConsuntivo .= '<thead><tr><th class="col-md-11 text-left">Materia</th><th class="col-md-1 text-center">ore</th></tr></thead><tbody>';

// calcola gli sportelli per ciascuna materia
$oreTotaliSportello = 0;
foreach(dbGetAll("SELECT * FROM materia ORDER BY nome;") as $materia) {
    $materiaId = $materia['id'];
    $oreMateria = dbGetValue("SELECT COALESCE(SUM(numero_ore), 0) FROM sportello WHERE materia_id = $materiaId AND anno_scolastico_id = $anno_id;");
    $dataConsuntivo .= '<tr><td class="col-md-11 text-left">'.$materia['nome'].'</td><td class="col-md-1 text-right">' . $oreMateria . '</td></tr>';
    $oreTotaliSportello += $oreMateria;
}

$dataConsuntivo .= '</tbody><tfooter><tr><td class="col-md-11 text-right"><strong>Totale Ore Sportelli</strong></td><td class="col-md-1 text-right"><strong>' . $oreTotaliSportello . '</strong></td></tr>';
$dataConsuntivo .= '</tfooter></table>';
$dataConsuntivo .= '<hr>';

// copertina, consuntivo, poi tutto il resto

echo $dataCopertina;
echo $dataConsuntivo;
echo $dataContenuto;
?>

<script>
function reportSportelliExportFilename(response, fallback) {
    var disposition = response.headers.get('Content-Disposition') || '';
    var match = disposition.match(/filename\*=UTF-8''([^;]+)|filename="?([^"]+)"?/i);
    if (match) {
        return decodeURIComponent(match[1] || match[2]);
    }
    return fallback;
}

function reportSportelliDownload(format) {
    var overlay = document.getElementById('reportSportelliExportOverlay');
    var detail = document.getElementById('reportSportelliExportDetail');
    var progress = document.getElementById('reportSportelliExportProgress');
    var percentText = document.getElementById('reportSportelliExportPercent');
    var anno = document.getElementById('anno_id').value || '<?php echo intval($anno_id); ?>';
    var label = format === 'pdf' ? 'PDF' : 'Excel';
    var fallback = 'report_sportelli_<?php echo preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$nome_anno_scolastico); ?>.' + (format === 'pdf' ? 'pdf' : 'xlsx');
    var percent = 0;
    var progressTimer = null;

    function setProgress(value) {
        percent = Math.max(percent, Math.min(100, value));
        if (progress) {
            progress.style.width = percent + '%';
            progress.textContent = percent + '%';
        }
        if (percentText) {
            percentText.textContent = percent + '%';
        }
    }

    detail.textContent = 'Sto generando il file ' + label + '. Attendi, il download partira automaticamente.';
    setProgress(3);
    overlay.style.display = 'flex';
    progressTimer = window.setInterval(function() {
        if (percent < 45) {
            setProgress(percent + 4);
        } else if (percent < 75) {
            setProgress(percent + 2);
        } else if (percent < 90) {
            setProgress(percent + 1);
        }
    }, 900);

    fetch('reportSportelliExport.php?format=' + encodeURIComponent(format) + '&anno_id=' + encodeURIComponent(anno), {
        credentials: 'same-origin'
    })
    .then(function(response) {
        if (!response.ok) {
            throw new Error('Errore export: ' + response.status);
        }
        var filename = reportSportelliExportFilename(response, fallback);
        return response.blob().then(function(blob) {
            return { blob: blob, filename: filename };
        });
    })
    .then(function(result) {
        setProgress(100);
        var url = window.URL.createObjectURL(result.blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = result.filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    })
    .catch(function(error) {
        alert('Non sono riuscito a generare il file. ' + error.message);
    })
    .finally(function() {
        if (progressTimer) {
            window.clearInterval(progressTimer);
        }
        window.setTimeout(function() {
            overlay.style.display = 'none';
            setProgress(0);
        }, 350);
    });
}
</script>
</body>
</html>
