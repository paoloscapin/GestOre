/**
 *  This file is part of GestOre
 */

var scripts = document.getElementsByTagName('script');
var myScript = scripts[scripts.length - 1];
var url = new URL(myScript.src);
var params = new URLSearchParams(url.search);
var device = params.get("d") || "desktop";

var soloNuovi = 1;
var soloIscritto = 0;
var ancheCancellati = 0;
var docente_filtro_id = 0;
var materia_filtro_id = 0;
var categoria_filtro_id = 1;

$('#soloNuoviCheckBox').change(function () {
    soloNuovi = this.checked ? 1 : 0;
    sportelloReadRecords();
});

$('#soloIscrittoCheckBox').change(function () {
    soloIscritto = this.checked ? 1 : 0;
    sportelloReadRecords();
});

$('#ancheCancellatiCheckBox').change(function () {
    ancheCancellati = this.checked ? 1 : 0;
    sportelloReadRecords();
});

function sportelloReadRecords() {
    var endpoint = (device === "mobile")
        ? "sportelloReadRecords_mobile.php"
        : "sportelloReadRecords.php";

    $.post(endpoint, {
        ancheCancellati: ancheCancellati,
        soloNuovi: soloNuovi,
        soloIscritto: soloIscritto,
        docente_filtro_id: docente_filtro_id,
        materia_filtro_id: materia_filtro_id,
        categoria_filtro_id: categoria_filtro_id,
        classe_filtro_id: 0
    }, function (data) {
        $(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            trigger: 'hover',
            container: 'body'
        });
    });
}

function sportelloCancellaIscrizione(sportello_id, materia, categoria, argomento, data, ora, numero_ore, luogo, docente_id, studente_id) {
    var conf = confirm("Sei sicuro di volere cancellare la tua iscrizione dallo sportello di " + materia + " ?");
    if (!conf) return;

    $.post("./sportelloCancellaIscrizione.php", {
        id: sportello_id,
        argomento: argomento,
        materia: materia,
        categoria: categoria,
        data: data,
        ora: ora,
        numero_ore: numero_ore,
        luogo: luogo,
        docente_id: docente_id,
        studente_id: studente_id
    }, function (resp) {
        if (!resp || resp.ok === false) {
            bootbox.alert("Errore: " + ((resp && resp.error) ? resp.error : "cancellazione non riuscita"));
            return;
        }
        sportelloReadRecords();
    }, "json").fail(function () {
        bootbox.alert("Errore durante la cancellazione dell'iscrizione.");
    });
}

function sportelloIscriviti(sportello_id, materia, categoria, argomento, data, ora, numero_ore, luogo, docente_id, studente_id) {

    function doPromptAndEnroll(idsArray) {
        var unSoloArgomento = $("#hidden_unSoloArgomento").val() == 0 ? false : true;

        idsArray = (idsArray || [])
            .map(function (x) { return parseInt(x, 10); })
            .filter(function (x) { return Number.isFinite(x) && x > 0; });

        var primoIscritto = argomento ? false : true;
        var chiediArgomento = !unSoloArgomento || primoIscritto;

        if (argomento != null && argomento.length !== 0) {
            chiediArgomento = false;
        }

        var titolo = "<p>Sportello: " + materia + "</p>";
        var messaggio = chiediArgomento
            ? "<p>Inserire l'argomento per lo sportello:</p>"
            : "<p>Confermare l'argomento per lo sportello:</p>" + argomento;

        var inputType = chiediArgomento ? 'textarea' : 'checkbox';
        var inputOptions = chiediArgomento ? [] : [{ text: 'Confermo', value: '1' }];
        var value = chiediArgomento ? [] : ['1'];

        var dialog = bootbox.prompt({
            title: titolo,
            message: messaggio,
            inputType: inputType,
            inputOptions: inputOptions,
            value: value,
            required: true,
            callback: function (result) {

                if (!result) return;

                if (argomento) {
                    if (result != 1) return;
                } else {
                    argomento = result;
                }

                var payload = {
                    materia: materia,
                    argomento: argomento,
                    categoria: categoria,
                    docente_id: docente_id,
                    studente_id: studente_id
                };

                if (idsArray.length > 1) {
                    payload.ids = JSON.stringify(idsArray);
                } else {
                    payload.id = idsArray[0] || sportello_id;
                    payload.data = data;
                    payload.ora = ora;
                    payload.numero_ore = numero_ore;
                    payload.luogo = luogo;
                }

                $.post("./sportelloIscriviStudente.php", payload, function (resp) {
                    if (resp && resp.ok === false) {
                        bootbox.alert("Errore: " + (resp.error || "iscrizione non riuscita"));
                        return;
                    }
                    sportelloReadRecords();
                }, "json").fail(function () {
                    bootbox.alert("Errore durante l'iscrizione.");
                });
            }
        });

        dialog.on('shown.bs.modal', function () {
            $(this).attr('aria-hidden', 'false');
        });
    }

    $.post("./sportelloIscriviStudente.php", {
        action: "check_adjacent",
        id: sportello_id
    }, function (resp) {

        if (!resp || !resp.ok) {
            doPromptAndEnroll([sportello_id]);
            return;
        }

        var prevOk = resp.prev_id && (resp.prev_posti || 0) > 0;
        var nextOk = resp.next_id && (resp.next_posti || 0) > 0;

        if (!prevOk && !nextOk) {
            doPromptAndEnroll([sportello_id]);
            return;
        }

        var oraPrev = (resp.prev_ora || "").trim();
        var oraNext = (resp.next_ora || "").trim();

        var buttons = {
            only: {
                label: "Solo " + (ora || ""),
                className: "btn-primary",
                callback: function () { doPromptAndEnroll([sportello_id]); }
            },
            cancel: {
                label: "Annulla",
                className: "btn-default"
            }
        };

        if (prevOk) {
            buttons.prev = {
                label: "Questa + ora prima (" + oraPrev + ")",
                className: "btn-success",
                callback: function () {
                    doPromptAndEnroll([resp.prev_id, sportello_id]);
                }
            };
        }

        if (nextOk) {
            buttons.next = {
                label: "Questa + ora dopo (" + oraNext + ")",
                className: "btn-success",
                callback: function () {
                    doPromptAndEnroll([sportello_id, resp.next_id]);
                }
            };
        }

        if (prevOk && nextOk) {
            buttons.all3 = {
                label: "Tutte e 3 (" + oraPrev + " + " + (ora || "") + " + " + oraNext + ")",
                className: "btn-warning",
                callback: function () {
                    doPromptAndEnroll([resp.prev_id, sportello_id, resp.next_id]);
                }
            };
        }

        bootbox.dialog({
            title: "Iscrizione",
            message:
                "<p>Ho trovato sportelli identici adiacenti:</p>" +
                (prevOk ? ("<p>• Ora prima: <b>" + oraPrev + "</b></p>") : "") +
                (nextOk ? ("<p>• Ora dopo: <b>" + oraNext + "</b></p>") : "") +
                "<p>Come vuoi iscriverti?</p>",
            buttons: buttons
        });

    }, "json").fail(function () {
        doPromptAndEnroll([sportello_id]);
    });
}

$(document).ready(function () {
    $('.selectpicker').selectpicker();

    sportelloReadRecords();

    function bindFiltro($el, setter) {
        $el.on("changed.bs.select change", function () {
            setter(this.value);
            sportelloReadRecords();
        });
    }

    bindFiltro($("#categoria_filtro"), function (v) { categoria_filtro_id = v; });
    bindFiltro($("#docente_filtro"), function (v) { docente_filtro_id = v; });
    bindFiltro($("#materia_filtro"), function (v) { materia_filtro_id = v; });
});