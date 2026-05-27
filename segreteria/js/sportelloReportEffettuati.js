/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var periodo_filtro='tutti';
var materia_filtro_id=0;
var docente_filtro_id=0;
var iscritti_filtro='tutti';
var firmato_filtro='tutti';

function sportelloReportEffettuatiReadRecords() {
	$.get("sportelloReportEffettuatiReadRecords.php?" + sportelloReportEffettuatiQuery(), {}, function (data, status) {
		$(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body'
        });
	});
}

function sportelloReportEffettuatiQuery() {
    return $.param({
        ancheCancellati: false,
        periodo_filtro: periodo_filtro,
        docente_filtro_id: docente_filtro_id,
        materia_filtro_id: materia_filtro_id,
        iscritti_filtro: iscritti_filtro,
        firmato_filtro: firmato_filtro,
        _: new Date().getTime()
    });
}

function sportelloReportEffettuatiExport(format) {
    var overlay = document.getElementById('sportelliReportExportOverlay');
    var detail = document.getElementById('sportelliReportExportDetail');
    var progress = document.getElementById('sportelliReportExportProgress');
    var percentText = document.getElementById('sportelliReportExportPercent');
    var label = format === 'pdf' ? 'PDF' : 'Excel';
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

    if (detail) {
        detail.textContent = 'Sto generando il file ' + label + '. Attendi, il download partira automaticamente.';
    }
    setProgress(3);
    if (overlay) {
        overlay.style.display = 'flex';
    }
    progressTimer = window.setInterval(function() {
        if (percent < 45) {
            setProgress(percent + 4);
        } else if (percent < 75) {
            setProgress(percent + 2);
        } else if (percent < 90) {
            setProgress(percent + 1);
        }
    }, 900);

    fetch("sportelloReportEffettuatiExport.php?format=" + encodeURIComponent(format) + "&" + sportelloReportEffettuatiQuery(), {
        credentials: 'same-origin'
    })
    .then(function(response) {
        if (!response.ok) {
            throw new Error('Errore export: ' + response.status);
        }
        var disposition = response.headers.get('Content-Disposition') || '';
        var match = disposition.match(/filename\*=UTF-8''([^;]+)|filename="?([^"]+)"?/i);
        var filename = match ? decodeURIComponent(match[1] || match[2]) : ('report_sportelli.' + (format === 'pdf' ? 'pdf' : 'xlsx'));
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
            if (overlay) {
                overlay.style.display = 'none';
            }
            setProgress(0);
        }, 350);
    });
}

$(document).ready(function () {
    $("#periodo_filtro").on("changed.bs.select",
    function(e, clickedIndex, newValue, oldValue) {
        periodo_filtro = this.value;
        sportelloReportEffettuatiReadRecords();
    });

    $("#materia_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        materia_filtro_id = this.value;
        sportelloReportEffettuatiReadRecords();
    });
    
    $("#docente_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        docente_filtro_id = this.value;
        sportelloReportEffettuatiReadRecords();
    });

    $("#iscritti_filtro").on("changed.bs.select",
    function(e, clickedIndex, newValue, oldValue) {
        iscritti_filtro = this.value;
        sportelloReportEffettuatiReadRecords();
    });

    $("#firmato_filtro").on("changed.bs.select",
    function(e, clickedIndex, newValue, oldValue) {
        firmato_filtro = this.value;
        sportelloReportEffettuatiReadRecords();
    });

    sportelloReportEffettuatiReadRecords();
});
