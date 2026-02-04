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

var soloNuovi = 1;
var soloIscritto = 0;
var ancheCancellati = 0;
var docente_filtro_id = 0;
var materia_filtro_id = 0;
var categoria_filtro_id = 1; // sportello didattico

$('#soloNuoviCheckBox').change(function () {
    // this si riferisce al checkbox
    if (this.checked) {
        soloNuovi = 1;
    } else {
        soloNuovi = 0;
    }
    sportelloReadRecords();
});

$('#soloIscrittoCheckBox').change(function () {
    // this si riferisce al checkbox
    if (this.checked) {
        soloIscritto = 1;
    } else {
        soloIscritto = 0;
    }
    sportelloReadRecords();
});

$('#ancheCancellatiCheckBox').change(function () {
    // this si riferisce al checkbox
    if (this.checked) {
        ancheCancellati = 1;
    } else {
        ancheCancellati = 0;
    }
    sportelloReadRecords();
});

function sportelloReadRecords() {
    var endpoint = (device === "mobile")
        ? "sportelloReadRecords_mobile.php"
        : "sportelloReadRecords.php";

    $.get(endpoint + "?ancheCancellati=" + ancheCancellati + "&soloNuovi=" + soloNuovi + "&soloIscritto=" + soloIscritto + "&docente_filtro_id=" + docente_filtro_id + "&materia_filtro_id=" + materia_filtro_id + "&categoria_filtro_id=" + categoria_filtro_id, {}, function (data, status) {
        $(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            trigger: 'hover',
            container: 'body'
        });
    });
}

function sportelloCancellaIscrizione(sportello_id, materia, categoria, argomento, data, ora, numero_ore, luogo, docente_id, studente_id) {
    var conf = confirm("Sei sicuro di volere cancellare la tua iscrizione dallo sportello di " + materia + " ?");


    if (conf == true) {

        $.post("../studente/sportelloCancellaIscrizione.php", {
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
        },
            function (data, status) {
                sportelloReadRecords();
            });

    }
}

function sportelloIscriviti(sportello_id, materia, categoria, argomento, data, ora, numero_ore, luogo, docente_id, studente_id) {

    function doPromptAndEnroll(idsArray) {
        var unSoloArgomento = $("#hidden_unSoloArgomento").val() == 0 ? false : true;
        // ✅ normalizza ids (evita NaN/null/doppioni)
        idsArray = (idsArray || [])
            .map(function (x) { return parseInt(x, 10); })
            .filter(function (x) { return Number.isFinite(x) && x > 0; });

        // se per errore resta un solo id, comportati come singolo

        var primoIscritto = argomento ? false : true;
        var chiediArgomento = !unSoloArgomento || primoIscritto;

        if (argomento != null && argomento.length != 0) {
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
                    if (result != 1) return; // checkbox non confermato
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

                // ✅ singolo o multiplo
                // ✅ singolo o multiplo (dopo normalizzazione)
                if (idsArray.length > 1) {
                    payload.ids = JSON.stringify(idsArray);
                } else {
                    payload.id = idsArray[0] || sportello_id;   // ✅ usa l'id normalizzato
                    payload.data = data;
                    payload.ora = ora;
                    payload.numero_ore = numero_ore;
                    payload.luogo = luogo;
                }

                $.post("./sportelloIscriviStudente.php", payload, function (resp) {
                    // se il php risponde json (con la patch sopra), gestisco errori
                    try {
                        if (resp && resp.ok === false) {
                            bootbox.alert("Errore: " + (resp.error || "iscrizione non riuscita"));
                            return;
                        }
                    } catch (e) { }

                    sportelloReadRecords();
                }, "json").fail(function (xhr) {
                    // fallback
                    console.error("iscrizione fail", xhr && xhr.responseText);
                    bootbox.alert("Errore durante l'iscrizione.");
                });
            }
        });

        dialog.on('shown.bs.modal', function () {
            $(this).attr('aria-hidden', 'false');
        });
    }

    // ✅ nuova logica: se ora=13:50 controllo esistenza slot 14:40 uguale
    // ✅ nuova logica GENERICA: controllo slot successivo (se esiste)
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

        // nessun adiacente utile -> normale
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

        // opzionale: tutte e 3 se esistono entrambe
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

    return;


    // default: comportamento attuale
    doPromptAndEnroll([sportello_id]);
}


$(document).ready(function () {
    $(function () {
        $('.selectpicker').selectpicker(); // inizializza i select
    });
    sportelloReadRecords();

    function bindFiltro($el, setter) {
        $el.on("changed.bs.select change", function () {
            setter(this.value);
            sportelloReadRecords();
        });
    }

    bindFiltro($("#categoria_filtro"), v => { categoria_filtro_id = v; });
    bindFiltro($("#docente_filtro"), v => { docente_filtro_id = v; });
    bindFiltro($("#materia_filtro"), v => { materia_filtro_id = v; });
});
