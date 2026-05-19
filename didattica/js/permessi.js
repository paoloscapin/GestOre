/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

// 🔽 Recupero parametro "d" passato nello <script src=...>
var scripts = document.getElementsByTagName('script');
var myScript = scripts[scripts.length - 1];
var url = new URL(myScript.src);
var params = new URLSearchParams(url.search);
var device = params.get("d") || "desktop"; // default "desktop"

// 🔹 Memorizza l’ultima colonna ordinata
var lastSort = { columnIndex: null, asc: true };

function permessiReadRecords(forcePresence) {
    forcePresence = forcePresence === true;
    var endpoint = (device === "mobile")
        ? "permessiReadRecords_mobile.php"
        : "permessiReadRecords.php";

    var studenteId = $('#studente_filtro').val() || $('#hidden_studente_id').val() || 0;
    var dataFiltro = $('#data_filtro').val(); // nuovo filtro data
    if (!dataFiltro) {
        dataFiltro = new Date().toISOString().slice(0, 10);
    }
    var soloRichiesti = $('#solo_richiesti').is(':checked') ? 1 : 0; // nuovo filtro

    hideAllTooltips();
    $.get(endpoint, {
        studente_filtro_id: studenteId,
        data_filtro: dataFiltro,
        solo_richiesti: soloRichiesti,
        live_presence: forcePresence ? 1 : 0
    }, function (data, status) {
        $(".records_content").html(data);

        $('[data-toggle="tooltip"]').tooltip({
            trigger: 'hover',
            container: 'body'
        });

        var $table = $(".records_content table");
        var $ths = $table.find("th.sortable");

        if (lastSort.columnIndex !== null) {
            sortTable($table, lastSort.columnIndex, lastSort.asc);
            $ths.removeClass("sorted-asc sorted-desc").removeData("asc");
            $ths.eq(lastSort.sortableIndex)
                .addClass(lastSort.asc ? "sorted-asc" : "sorted-desc")
                .data("asc", lastSort.asc);
        } else {
            var defaultColumnIndex = 2;      // colonna reale "Ora uscita"
            var defaultSortableIndex = 1;    // indice tra i soli th.sortable

            sortTable($table, defaultColumnIndex, true);
            $ths.removeClass("sorted-asc sorted-desc").removeData("asc");
            $ths.eq(defaultSortableIndex)
                .addClass("sorted-asc")
                .data("asc", true);

            lastSort = {
                columnIndex: defaultColumnIndex,
                sortableIndex: defaultSortableIndex,
                asc: true
            };
        }

        if (forcePresence) {
            permessiLoadPresenceBadges();
        } else {
            permessiPresenceOverlayHide();
        }
    });
}

function permessiRefreshPresence() {
    permessiReadRecords(true);
}


// Ricarica quando il checkbox cambia
$(document).on("change", "#solo_richiesti", function () {
    permessiReadRecords(false);
});

// Dropdown studenti mobile
$('#studente_filtro').on('change', function () {
    $('#hidden_studente_id').val(this.value);
    permessiReadRecords(false);
});

document.addEventListener("DOMContentLoaded", function () {
    const rientroCheckbox = document.getElementById("rientro");
    const oraRientroGroup = document.getElementById("ora_rientro_group");

    if (rientroCheckbox) {
        rientroCheckbox.addEventListener("change", function () {
            if (this.checked) {
                oraRientroGroup.style.display = "flex";
            } else {
                oraRientroGroup.style.display = "none";
                document.getElementById("ora_rientro").value = "";
            }
        });
    }
});

function impostaDataPermesso() {
    const inputData = document.getElementById("data");
    const avviso = document.getElementById("avvisoData");

    const now = new Date();
    const oggi = new Date();
    const domani = new Date();
    domani.setDate(oggi.getDate() + 1);

    const ore = now.getHours();

    function formatDate(date) {
        return date.toISOString().split("T")[0];
    }

    if (ore < 9) {
        inputData.value = formatDate(oggi);
        avviso.style.display = "none";
    } else {
        inputData.value = formatDate(domani);
        avviso.style.display = "block";
    }
}

function permessiDelete(id) {
    hideAllTooltips();
    var conf = confirm("Sei sicuro di volere cancellare il permesso ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
            id: id,
            table: 'permessi_uscita'
        }, function (data, status) {
            permessiReadRecords(false);
        });
    }
}

function permessoConfirm(id) {
    hideAllTooltips();
    $.post("permessoConfirm.php", { id: id }, function (data) {
        if (data.trim() === "ok") {
            permessiReadRecords(false);
        } else {
            alert("❌ Errore durante la conferma del permesso.");
        }
    }).fail(function() {
        alert("❌ Errore di connessione al server.");
    });
}

function permessiMastercomSync(id) {
    hideAllTooltips();
    var payload = {
        data: $("#data_filtro").val()
    };
    if (id) {
        payload.id = id;
    }

    $("#permessi_sync_status")
        .removeClass("text-danger text-success")
        .addClass("text-info")
        .text("Sync MasterCom in corso...")
        .show();

    $.post("permessiMastercomSync.php", payload, function (data) {
        var result = (typeof data === "string") ? JSON.parse(data) : data;
        if (!result || result.ok === false) {
            var err = result && (result.error || result.message) ? (result.error || result.message) : "Errore sync MasterCom";
            $("#permessi_sync_status").removeClass("text-info text-success").addClass("text-danger").text(err);
            alert(err);
            return;
        }

        var message = result.message || ("Sync completato: " + (result.count || 0) + " permessi elaborati.");
        if (Array.isArray(result.results)) {
            var errors = result.results.filter(function (r) { return r && r.ok === false; }).length;
            if (errors > 0) {
                message += " Errori: " + errors + ".";
            }
        }
        $("#permessi_sync_status").removeClass("text-info text-danger").addClass("text-success").text(message);
        permessiReadRecords(false);
    }).fail(function (xhr) {
        var msg = "Errore di connessione durante il sync MasterCom.";
        if (xhr.responseJSON && xhr.responseJSON.error) {
            msg = xhr.responseJSON.error;
        }
        $("#permessi_sync_status").removeClass("text-info text-success").addClass("text-danger").text(msg);
        alert(msg);
    });
}

function permessiPresenceColor(state) {
    state = String(state || '').toUpperCase();
    if (state === 'PRESENTE' || state === 'ENTRATA_RITARDO') return 'green';
    if (state === 'ASSENTE_MASTERCOM' || state === 'USCITA' || state === 'EVENTO' || state === 'PERMESSO') return 'red';
    return '#777';
}

function permessiEscape(text) {
    return $('<div>').text(text || '').html();
}

function permessiRenderPresence($cell, result) {
    var label = result && result.label ? result.label : 'Da verificare';
    var detail = result && result.detail ? result.detail : '';
    var color = result && result.color ? result.color : permessiPresenceColor(result && result.stato);
    $cell
        .css({ backgroundColor: color, color: 'white' })
        .text(label)
        .css({ whiteSpace: 'normal', lineHeight: '1.15' })
        .attr('title', detail)
        .attr('data-original-title', detail);
}

function permessiPresenceOverlayShow(text, pct) {
    pct = Math.max(0, Math.min(100, parseInt(pct, 10) || 0));
    if (!$('#permessi_presence_overlay').length) {
        $('body').append(
            '<div id="permessi_presence_overlay" style="display:none;position:fixed;z-index:9999;left:0;top:0;right:0;bottom:0;background:rgba(255,255,255,0.78);align-items:center;justify-content:center;">' +
            '<div style="min-width:320px;max-width:420px;background:#fff;border:1px solid #d7e3f0;border-radius:8px;box-shadow:0 12px 34px rgba(15,23,42,.18);padding:20px 22px;text-align:center;">' +
            '<div style="font-weight:800;color:#1f5e3b;margin-bottom:10px;">Caricamento presenze MasterCom</div>' +
            '<div id="permessi_presence_overlay_text">Preparazione...</div>' +
            '<div class="progress" style="margin:12px 0 8px 0;"><div id="permessi_presence_overlay_bar" class="progress-bar progress-bar-info" role="progressbar" style="width:0%">0%</div></div>' +
            '</div></div>'
        );
    }
    $('#permessi_presence_overlay_text').text(text || 'Caricamento...');
    $('#permessi_presence_overlay_bar').css('width', pct + '%').text(pct + '%');
    $('#permessi_presence_overlay').css('display', 'flex');
}

function permessiPresenceOverlayHide() {
    $('#permessi_presence_overlay').fadeOut(150);
}

function permessiLoadPresenceBadges() {
    var $cells = $('.permessi-presence-cell');
    var dataFiltro = $('#data_filtro').val();
    if (!$cells.length || !dataFiltro) {
        $('#permessi_presence_status').hide();
        permessiPresenceOverlayHide();
        if (dataFiltro) {
            alert('Non ci sono permessi richiesti con presenza MasterCom da aggiornare nella tabella corrente.');
        }
        return;
    }

    var byClass = {};
    $cells.each(function () {
        var $cell = $(this);
        var classId = String($cell.data('class-id') || '');
        var studentId = parseInt($cell.data('student-id'), 10) || 0;
        if (!classId || !studentId) return;
        if (!byClass[classId]) byClass[classId] = {};
        byClass[classId][studentId] = {
            mastercom_id_studente: studentId,
            mastercom_id_classe_corrente: parseInt(classId, 10) || 0,
            nome: String($cell.data('nome') || ''),
            cognome: String($cell.data('cognome') || ''),
            classe: String($cell.data('classe') || '')
        };
    });

    var classIds = Object.keys(byClass);
    if (!classIds.length) {
        $('#permessi_presence_status').hide();
        return;
    }

    var done = 0;
    $('#permessi_presence_status').hide().text('');
    permessiPresenceOverlayShow('Caricamento presenze MasterCom: 0%', 0);

    function next() {
        if (done >= classIds.length) {
            $('#permessi_presence_status').hide().text('');
            permessiPresenceOverlayShow('Presenze MasterCom caricate', 100);
            setTimeout(function () {
                permessiPresenceOverlayHide();
                permessiReadRecords(false);
            }, 350);
            return;
        }

        var classId = classIds[done];
        var students = Object.keys(byClass[classId]).map(function (studentId) {
            return byClass[classId][studentId];
        });
        var permitMap = {};
        students.forEach(function (student) {
            permitMap[String(student.mastercom_id_studente)] = [];
            $('.permessi-presence-cell[data-student-id="' + student.mastercom_id_studente + '"]').each(function () {
                var permitId = parseInt($(this).data('permit-id'), 10) || 0;
                if (permitId > 0 && permitMap[String(student.mastercom_id_studente)].indexOf(permitId) === -1) {
                    permitMap[String(student.mastercom_id_studente)].push(permitId);
                }
            });
        });

        $.post('permessiPresenceStatus.php', {
            data: dataFiltro,
            students: JSON.stringify(students),
            permit_ids: JSON.stringify(permitMap)
        }, function (data) {
            var result = (typeof data === 'string') ? JSON.parse(data) : data;
            if (result && result.results) {
                Object.keys(result.results).forEach(function (studentId) {
                    $('.permessi-presence-cell[data-student-id="' + permessiEscape(studentId) + '"]').each(function () {
                        permessiRenderPresence($(this), result.results[studentId]);
                    });
                });
            }
        }).fail(function () {
            students.forEach(function (student) {
                $('.permessi-presence-cell[data-student-id="' + student.mastercom_id_studente + '"]').each(function () {
                    permessiRenderPresence($(this), {
                        label: 'Errore',
                        detail: 'Errore caricamento presenza MasterCom',
                        color: 'red'
                    });
                });
            });
        }).always(function () {
            done++;
            var pct = Math.round((done / classIds.length) * 100);
            $('#permessi_presence_status').hide().text('');
            permessiPresenceOverlayShow('Caricamento presenze MasterCom: ' + pct + '%', pct);
            next();
        });
    }

    next();
}


function hideAllTooltips() {
    try { $('[data-toggle="tooltip"]').tooltip('hide'); } catch (e) { }
    $('.tooltip').remove();
}

function permessoSave() {
    if ($("#data").val() == "") {
        $("#_error-permesso").text("Devi selezionare una data per il permesso.");
        $("#_error-permesso-part").show();
        return;
    }
    if ($("#motivo").val() == "") {
        $("#_error-permesso").text("Devi indicare un motivo per il permesso.");
        $("#_error-permesso-part").show();
        return;
    }
    if ($("#ora_uscita").val() == "") {
        $("#_error-permesso").text("Devi selezionare un'ora di uscita per il permesso.");
        $("#_error-permesso-part").show();
        return;
    }
    rientro = $("#rientro").prop('checked') ? 1 : 0;

    if (rientro == 0 && ($("#hidden_rientro").val() == 1)) {
        var conf = confirm("Sei sicuro di volere disattivare il rientro per il permesso?");
        if (conf == false) return;
    }
    if (rientro == 1 && ($("#hidden_rientro").val() == 0)) {
        var conf = confirm("Sei sicuro di voler attivare il rientro per il permesso?");
        if (conf == false) return;
    }

    $("#_error-permessi-part").hide();

    $.post("permessoSave.php", {
        id: $("#hidden_permesso_id").val(),
        data: $("#data").val(),
        ora_uscita: $("#ora_uscita").val(),
        motivo: $("#motivo").val(),
        ora_rientro: $("#ora_rientro").val(),
        rientro: $("#rientro").prop('checked') ? 1 : 0,
        id_studente: $('#hidden_studente_id').val(),
        stato: $("#stato").val(),
        note_segreteria: $("#note_segreteria").val()
    }, function (data, status) {
        $("#permesso_modal").modal("hide");
        permessiReadRecords(false);
    });
}

function permessiGetDetails(permesso_id) {
    $("#hidden_permesso_id").val(permesso_id);

    if (permesso_id > 0) {
        $.post(device === "mobile" ? "permessiReadDetails_mobile.php" : "permessiReadDetails.php", {
            id: permesso_id
        }, function (data, status) {
            var permesso = JSON.parse(data);

            if ($("#data").length) $("#data").val(permesso.permesso_data);
            if ($("#ora_uscita").length) $("#ora_uscita").val(permesso.permesso_ora_uscita);
            if ($("#rientro").length) $("#rientro").val(permesso.permesso_rientro);
            if ($("#motivo").length) $("#motivo").val(permesso.permesso_motivo);
            if ($("#ora_rientro").length) $("#ora_rientro").val(permesso.permesso_ora_rientro);
            if ($("#stato").length) $("#stato").val(permesso.permesso_stato);
            if ($("#note_segreteria").length) $("#note_segreteria").val(permesso.permesso_note_segreteria);
            if ($("#studente_nome").length) $("#studente_nome").val(permesso.studente_nome + " " + permesso.studente_cognome);
            if ($("#genitore_nome").length) $("#genitore_nome").val(permesso.genitore_nome + " " + permesso.genitore_cognome);
            if ($("#studente_classe").length) $("#studente_classe").val(permesso.studente_classe);
        });
    } else {
        if ($("#ora_uscita").length) $("#ora_uscita").val("");
        if ($("#rientro").length) $("#rientro").prop('checked', false);
        if ($("#motivo").length) $("#motivo").val("");
        if ($("#ora_rientro").length) $("#ora_rientro").val("");
        if ($("#stato").length) $("#stato").val("in_attesa");
        if ($("#note_segreteria").length) $("#note_segreteria").val("");
        if ($("#studente_nome").length) $("#studente_nome").val("");
        if ($("#genitore_nome").length) $("#genitore_nome").val("");
        if ($("#studente_classe").length) $("#studente_classe").val("");
        impostaDataPermesso();
        $('#btn-save').show();
    }

    $("#permesso_modal").modal("show");
    $("#_error-permesso-part").hide();
}

// 🔹 Ordinamento
function sortTable($table, columnIndex, asc) {
    var rows = $table.find("tbody tr").toArray().sort(function (a, b) {
        var A = $(a).children("td").eq(columnIndex).text().trim().toUpperCase();
        var B = $(b).children("td").eq(columnIndex).text().trim().toUpperCase();

        if (/^\d{2}:\d{2}$/.test(A) && /^\d{2}:\d{2}$/.test(B)) {
            A = parseInt(A.split(":")[0], 10) * 60 + parseInt(A.split(":")[1], 10);
            B = parseInt(B.split(":")[0], 10) * 60 + parseInt(B.split(":")[1], 10);
        }

        if (A < B) return asc ? -1 : 1;
        if (A > B) return asc ? 1 : -1;
        return 0;
    });

    $.each(rows, function (index, row) {
        $table.children("tbody").append(row);
    });
}

// 🔹 Gestione click sulle intestazioni
$(document).on("click", "th.sortable", function () {
    var $th = $(this);
    var $table = $th.closest("table");
    var columnIndex = $th.index();

    // toggle asc/desc
    var asc = !$th.hasClass("sorted-asc");

    // reset classi su tutte le intestazioni
    $th.closest("tr").find("th.sortable").removeClass("sorted-asc sorted-desc");

    // aggiungi la nuova classe
    $th.addClass(asc ? "sorted-asc" : "sorted-desc");

    // salva stato
    lastSort = { columnIndex: columnIndex, asc: asc };

    // ordina
    sortTable($table, columnIndex, asc);
});

$(document).ready(function () {
    // Flatpickr su #data_filtro
    flatpickr("#data_filtro", {
        altInput: true,
        altFormat: "d/m/Y",
        dateFormat: "Y-m-d",
        defaultDate: new Date(),
        locale: "it",
        onChange: function () {
            permessiReadRecords(false); // aggiorna tabella al cambio data
        }
    });

    permessiReadRecords(true);

    $("#studente_filtro").on("changed.bs.select", function (e, clickedIndex, newValue, oldValue) {
        $('#hidden_studente_id').val(this.value);
        permessiReadRecords(false);
    });

});
