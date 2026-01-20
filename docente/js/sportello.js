/**
 *  This file is part of GestOre
 *  @author     Massimo Saiani <massimo.saiani@buonarroti.tn.it>
 *  @copyright  (C) 2026 Massimo Saiani
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var soloNuovi = 1;
var ancheCancellati = 0;
var soloIMiei = 1;
var categoria_filtro_id = 1; // sportello didattico
var materia_filtro_id = 0;
var classe_filtro_id = 0;
var bozza_filtro_id = 0;
var aulaLocked = false;     // true => aula bloccata (sportello già con aula)
var aulaDbValue = "";       // aula letta da DB
var autoPickFirstAula = false; // true => se aula vuota, seleziona la prima aula libera


function resetAulaState() {
    aulaLocked = false;
    aulaDbValue = "";
    autoPickFirstAula = false;
}

function forceHideTooltips() {
    // chiude eventuali tooltip aperti
    $('[data-toggle="tooltip"]').tooltip('hide');

    // bootstrap a volte lascia tooltip "orfani" nel body
    $('.tooltip').remove();
}

var data_pickr = null;

function setDbDateToPickr(pickr, data_str) {
    var data = Date.parseExact(data_str, 'yyyy-MM-dd');
    pickr.setDate(data);
}

function getDbDateFromPickrId(pickrId) {
    var data_str = $(pickrId).val();
    var data_date = Date.parseExact(data_str, 'd/M/yyyy');
    return data_date.toString('yyyy-MM-dd');
}

$('#soloNuoviCheckBox').change(function () {
    // this si riferisce al checkbox
    if (this.checked) {
        soloNuovi = 1;
    } else {
        soloNuovi = 0;
    }
    sportelloReadRecords();
});

$('#bozzaCheckBox').change(function () {
    // this si riferisce al checkbox
    if (this.checked) {
        bozza_filtro_id = 1;
    } else {
        bozza_filtro_id = 0;
    }
    sportelloReadRecords();
});

$('#soloIMieiCheckBox').change(function () {
    // this si riferisce al checkbox
    if (this.checked) {
        soloIMiei = 1;
    } else {
        soloIMiei = 0;
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
    $.get("sportelloReadRecords.php?ancheCancellati=" + ancheCancellati + "&soloNuovi=" + soloNuovi + "&soloMiei=" + soloIMiei + "&categoria_filtro_id=" + categoria_filtro_id + "&classe_filtro_id=" + classe_filtro_id + "&materia_filtro_id=" + materia_filtro_id + "&bozza_filtro_id=" + bozza_filtro_id, {}, function (data, status) {
        $(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body'
        });
    });
}

function sportelloDelete(id, materia) {
    var conf = confirm("Sei sicuro di volere cancellare lo sportello di " + materia + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
            id: id,
            table: 'sportello',
            name: "materia" + materia
        },
            function (data, status) {
                if (data == 'Application Error') {
                    errorNotify('Impossibile cancellare lo sportello', 'Lo sportello della materia <Strong>' + materia + '</Strong> contiene probabilmente degli studenti iscritti o altri riferimenti');
                } else {
                    infoNotify('Cancellazione effettuata', 'Lo sportello della materia <Strong>' + materia + '</Strong> è stato cancellato regolarmente');
                }
                sportelloReadRecords();
            }
        );
    }
}
function sportelloAssegna(sportello_id) {
    if (!sportello_id || sportello_id <= 0) return;

    // opzionale: conferma
    if (!confirm("Vuoi assegnarti questo sportello?")) return;

    $.post("sportelloAssegna.php", { sportello_id: sportello_id }, function (resp) {
        try { if (typeof resp === "string") resp = JSON.parse(resp); } catch (e) { }

        if (resp && resp.ok) {
            // aggiorna lista (così sparisce dalla lista bozze / o diventa "mio")
            sportelloReadRecords();
            resetAulaState();
            // apri dettaglio in modalità modificabile
            // qui passiamo: modificabile=true, nstudenti=0, categoria=""
            sportelloGetDetails(sportello_id, true, 0, "");

            setTimeout(function () {
                // abilita AULA (select)
                $("#luogo").prop('disabled', false).selectpicker('refresh');

                // abilita ORA (select)
                $("#ora").prop('disabled', false).selectpicker('refresh');

                // abilita MAX ISCRIZIONI (input)
                $("#max_iscrizioni").prop('disabled', false).prop('readonly', false);

                // abilita NUMERO ORE (select)
                $("#numero_ore").prop('disabled', false).selectpicker('refresh');
                $("#numero_ore").selectpicker('val', "1").selectpicker('refresh');

                // focus sul select (come prima)
                $(".bootstrap-select[data-id='luogo'] button").focus();
            }, 300);


        } else {
            alert(resp && resp.msg ? resp.msg : "Errore durante l'assegnazione dello sportello.");
        }
    }, "json").fail(function (xhr) {
        alert("Errore di rete o server durante l'assegnazione.");
    });
}


function sportelloSave() {
    // controlla che ci siano la materia ed il numero di ore

    if ($("#materia").val() <= 0) {
        $("#_error-materia").text("Devi selezionare una materia");
        $("#_error-materia-part").show();
        return;
    }
    if ($("#categoria").val() <= 0) {
        $("#_error-materia").text("Devi selezionare una categoria");
        $("#_error-materia-part").show();
        return;
    }
    if ($("#classe").val() <= 0) {
        $("#_error-materia").text("Devi selezionare una classe");
        $("#_error-materia-part").show();
        return;
    }
    if ($("#numero_ore").val() <= 0) {
        $("#_error-materia").text("Il numero di ore non può essere 0");
        $("#_error-materia-part").show();
        return;
    }

    // se tutto bene nasconde il messaggio di errore e prosegue nel save
    $("#_error-materia-part").hide();

    // controlla la lista di studenti segnati presenti
    var studentiDaModificareIdList = [];
    $('#studenti_table tbody tr').each(function () {
        var row = $(this);
        var presenteCheckbox = row.find('input[type="checkbox"]');
        var presenteOriginal = presenteCheckbox.prop('defaultChecked');
        var presenteCorrente = presenteCheckbox.prop('checked');
        var id = row.children().eq(0).text();
        if (presenteCorrente != presenteOriginal) {
            studentiDaModificareIdList.push(id);
        }
    });

    // ⚠️ Se non c'è luogo, avviso che tornerà in BOZZA e NON assegnato
    var luogoTrim = ($("#luogo").val() || "").trim();
    if (luogoTrim === "") {
        var ok = confirm(
            "ATTENZIONE:\n" +
            "Non hai inserito il luogo/aula.\n\n" +
            "Se prosegui con il salvataggio, lo sportello tornerà in BOZZA e verrà DE-ASSEGNATO (docente = 0).\n\n" +
            "Vuoi proseguire?"
        );
        if (!ok) return;
    }

    var numeroOre = parseInt($("#numero_ore").val() || "1", 10);
    var isSplit2 = (numeroOre === 2);

    // ✅ se split 2 ore: obbligo aula
    if (isSplit2) {
        if (luogoTrim === "") {
            alert("Per fare uno sportello da 2 ore devi prima selezionare un'aula.");
            return;
        }

        var ok2 = confirm(
            "ATTENZIONE:\n" +
            "Hai scelto uno sportello da 2 ore.\n\n" +
            "Lo sportello verrà SPEZZATO e inserito come DUE sportelli contigui da 1 ora.\n" +
            "Anche su MBApp verranno create DUE prenotazioni separate.\n\n" +
            "Confermi?"
        );
        if (!ok2) return;

        var payload = {
            id: $("#hidden_sportello_id").val(),
            data: getDbDateFromPickrId("#data"),
            ora: $("#ora").val(),
            materia_id: $("#materia").val(),
            categoria_id: $("#categoria").val(),
            numero_ore: 2,
            argomento: $("#argomento").val(),
            luogo: $("#luogo").val(),
            max_iscrizioni: $("#max_iscrizioni").val(),
            cancellato: $("#cancellato").is(':checked') ? 1 : 0,
            firmato: $("#firmato").is(':checked') ? 1 : 0,
            studentiDaModificareIdList: JSON.stringify(studentiDaModificareIdList)
            // ⚠️ docente_id: meglio NON mandarlo, lo prende il PHP dalla sessione
        };

        if ($("#hidden_lista_classi").val() == "testo") {
            payload.classe = $("#classe").val();
            payload.classe_id = 0;
        } else {
            payload.classe_id = $("#classe").val();

            // ✅ manda anche il testo (retrocompat) invece di ""
            // con bootstrap-select funziona comunque perché l’option selezionata esiste
            payload.classe = ($("#classe option:selected").text() || "").trim();
        }

        if ($('#hidden_sezione_online_clil').val() == 'true') {
            payload.online = $("#online").is(':checked') ? 1 : 0;
            payload.clil = $("#clil").is(':checked') ? 1 : 0;
            payload.orientamento = $("#orientamento").is(':checked') ? 1 : 0;
        } else {
            payload.online = 0; payload.clil = 0; payload.orientamento = 0;
        }

        sportelloSplitDueOre(payload)
            .done(function (respSplit) {
                if (typeof respSplit === "string") {
                    try { respSplit = JSON.parse(respSplit); } catch (e) { respSplit = null; }
                }
                if (!respSplit || !respSplit.ok) {
                    errorNotify("Errore", (respSplit && respSplit.error) ? respSplit.error : "Split 2 ore fallito.");
                    return;
                }
                infoNotify("OK", "Sportello salvato come 2 sportelli contigui da 1 ora.");
                $("#sportello_modal").modal("hide");
                sportelloReadRecords();
            })
            .fail(function () {
                errorNotify("Errore", "Errore di rete o server nello split 2 ore.");
            });

        return;
    }

    // ✅ NON split: qui ha senso l’avviso bozza se manca l’aula
    if (luogoTrim === "") {
        var ok = confirm(
            "ATTENZIONE:\n" +
            "Non hai inserito il luogo/aula.\n\n" +
            "Se prosegui con il salvataggio, lo sportello tornerà in BOZZA e verrà DE-ASSEGNATO (docente = 0).\n\n" +
            "Vuoi proseguire?"
        );
        if (!ok) return;
    }

    if ($("#hidden_lista_classi").val() == "testo") // se la classe è una casella di testo
    {
        if ($('#hidden_sezione_online_clil').val() == 'true') {
            $.post("sportelloAggiorna.php", {
                id: $("#hidden_sportello_id").val(),
                data: getDbDateFromPickrId("#data"),
                ora: $("#ora").val(),
                docente_id: $("#docente").val(),
                materia_id: $("#materia").val(),
                categoria_id: $("#categoria").val(),
                numero_ore: $("#numero_ore").val(),
                argomento: $("#argomento").val(),
                luogo: $("#luogo").val(),
                max_iscrizioni: $("#max_iscrizioni").val(),
                classe: $("#classe").val(),
                classe_id: 0,
                cancellato: $("#cancellato").is(':checked') ? 1 : 0,
                firmato: $("#firmato").is(':checked') ? 1 : 0,
                online: $("#online").is(':checked') ? 1 : 0,
                clil: $("#clil").is(':checked') ? 1 : 0,
                orientamento: $("#orientamento").is(':checked') ? 1 : 0,
                studentiDaModificareIdList: JSON.stringify(studentiDaModificareIdList),
            }, function (respSave) {

                // se la risposta arriva come stringa, prova parse
                if (typeof respSave === "string") {
                    try { respSave = JSON.parse(respSave); } catch (e) { respSave = null; }
                }

                if (!respSave || !respSave.ok) {
                    errorNotify("Errore", "Salvataggio sportello fallito.");
                    return;
                }

                // 1) prenota aula (se serve)
                // ✅ NON prenotare di nuovo se è uno sportello esistente con aula già assegnata (locked)
                let req = null;
                if (!aulaLocked) {
                    req = prenotaAulaPerSportello(respSave);
                }


                // 2) chiudi e aggiorna UI (anche se prenotazione non serve)
                if (req && typeof req.then === "function") {
                    req.done(function (respPrenota) {
                        if (!respPrenota || respPrenota.ok !== true) {
                            errorNotify("Attenzione", "Sportello salvato ma prenotazione aula NON riuscita.");
                            return;
                        }
                        if (respPrenota.skip) {
                            infoNotify("OK", "Sportello salvato. Prenotazione MBApp già esistente (nessun duplicato).");
                            return;
                        }
                        infoNotify("OK", "Sportello salvato e aula prenotata.");
                    }).fail(function () {
                        errorNotify("Attenzione", "Sportello salvato ma errore di rete nella prenotazione aula.");
                    }).always(function () {
                        $("#sportello_modal").modal("hide");
                        sportelloReadRecords();
                    });
                } else {
                    $("#sportello_modal").modal("hide");
                    sportelloReadRecords();
                }

            }, "json").fail(function () {
                errorNotify("Errore", "Errore di rete o server durante il salvataggio.");
            });
        }
        else {
            $.post("sportelloAggiorna.php", {
                id: $("#hidden_sportello_id").val(),
                data: getDbDateFromPickrId("#data"),
                ora: $("#ora").val(),
                docente_id: $("#docente").val(),
                materia_id: $("#materia").val(),
                categoria_id: $("#categoria").val(),
                numero_ore: $("#numero_ore").val(),
                argomento: $("#argomento").val(),
                luogo: $("#luogo").val(),
                max_iscrizioni: $("#max_iscrizioni").val(),
                classe: $("#classe").val(),
                classe_id: 0,
                cancellato: $("#cancellato").is(':checked') ? 1 : 0,
                firmato: $("#firmato").is(':checked') ? 1 : 0,
                online: 0,
                clil: 0,
                orientamento: 0,
                studentiDaModificareIdList: JSON.stringify(studentiDaModificareIdList),
            }, function (respSave) {

                // se la risposta arriva come stringa, prova parse
                if (typeof respSave === "string") {
                    try { respSave = JSON.parse(respSave); } catch (e) { respSave = null; }
                }

                if (!respSave || !respSave.ok) {
                    errorNotify("Errore", "Salvataggio sportello fallito.");
                    return;
                }

                // 1) prenota aula (se serve)
                // ✅ NON prenotare di nuovo se è uno sportello esistente con aula già assegnata (locked)
                let req = null;
                if (!aulaLocked) {
                    req = prenotaAulaPerSportello(respSave);
                }


                // 2) chiudi e aggiorna UI (anche se prenotazione non serve)
                if (req && typeof req.then === "function") {
                    req.done(function (respPrenota) {
                        if (!respPrenota || respPrenota.ok !== true) {
                            errorNotify("Attenzione", "Sportello salvato ma prenotazione aula NON riuscita.");
                            return;
                        }
                        if (respPrenota.skip) {
                            infoNotify("OK", "Sportello salvato. Prenotazione MBApp già esistente (nessun duplicato).");
                            return;
                        }
                        infoNotify("OK", "Sportello salvato e aula prenotata.");
                    }).fail(function () {
                        errorNotify("Attenzione", "Sportello salvato ma errore di rete nella prenotazione aula.");
                    }).always(function () {
                        $("#sportello_modal").modal("hide");
                        sportelloReadRecords();
                    });
                } else {
                    $("#sportello_modal").modal("hide");
                    sportelloReadRecords();
                }

            }, "json").fail(function () {
                errorNotify("Errore", "Errore di rete o server durante il salvataggio.");
            });
        }
    }
    else {
        if ($('#hidden_sezione_online_clil').val() == 'true') {
            $.post("sportelloAggiorna.php", {
                id: $("#hidden_sportello_id").val(),
                data: getDbDateFromPickrId("#data"),
                ora: $("#ora").val(),
                docente_id: $("#docente").val(),
                materia_id: $("#materia").val(),
                categoria_id: $("#categoria").val(),
                numero_ore: $("#numero_ore").val(),
                argomento: $("#argomento").val(),
                luogo: $("#luogo").val(),
                max_iscrizioni: $("#max_iscrizioni").val(),
                classe: "",
                classe_id: $("#classe").val(),
                cancellato: $("#cancellato").is(':checked') ? 1 : 0,
                firmato: $("#firmato").is(':checked') ? 1 : 0,
                online: $("#online").is(':checked') ? 1 : 0,
                clil: $("#clil").is(':checked') ? 1 : 0,
                orientamento: $("#orientamento").is(':checked') ? 1 : 0,
                studentiDaModificareIdList: JSON.stringify(studentiDaModificareIdList),
            }, function (respSave) {

                // se la risposta arriva come stringa, prova parse
                if (typeof respSave === "string") {
                    try { respSave = JSON.parse(respSave); } catch (e) { respSave = null; }
                }

                if (!respSave || !respSave.ok) {
                    errorNotify("Errore", "Salvataggio sportello fallito.");
                    return;
                }

                // 1) prenota aula (se serve)
                // ✅ NON prenotare di nuovo se è uno sportello esistente con aula già assegnata (locked)
                let req = null;
                if (!aulaLocked) {
                    req = prenotaAulaPerSportello(respSave);
                }


                // 2) chiudi e aggiorna UI (anche se prenotazione non serve)
                if (req && typeof req.then === "function") {
                    req.done(function (respPrenota) {
                        if (!respPrenota || respPrenota.ok !== true) {
                            errorNotify("Attenzione", "Sportello salvato ma prenotazione aula NON riuscita.");
                            return;
                        }
                        if (respPrenota.skip) {
                            infoNotify("OK", "Sportello salvato. Prenotazione MBApp già esistente (nessun duplicato).");
                            return;
                        }
                        infoNotify("OK", "Sportello salvato e aula prenotata.");
                    }).fail(function () {
                        errorNotify("Attenzione", "Sportello salvato ma errore di rete nella prenotazione aula.");
                    }).always(function () {
                        $("#sportello_modal").modal("hide");
                        sportelloReadRecords();
                    });
                } else {
                    $("#sportello_modal").modal("hide");
                    sportelloReadRecords();
                }

            }, "json").fail(function () {
                errorNotify("Errore", "Errore di rete o server durante il salvataggio.");
            });
        }
        else {
            $.post("sportelloAggiorna.php", {
                id: $("#hidden_sportello_id").val(),
                data: getDbDateFromPickrId("#data"),
                ora: $("#ora").val(),
                docente_id: $("#docente").val(),
                materia_id: $("#materia").val(),
                categoria_id: $("#categoria").val(),
                numero_ore: $("#numero_ore").val(),
                argomento: $("#argomento").val(),
                luogo: $("#luogo").val(),
                max_iscrizioni: $("#max_iscrizioni").val(),
                classe: "",
                classe_id: $("#classe").val(),
                cancellato: $("#cancellato").is(':checked') ? 1 : 0,
                firmato: $("#firmato").is(':checked') ? 1 : 0,
                online: 0,
                clil: 0,
                orientamento: 0,
                studentiDaModificareIdList: JSON.stringify(studentiDaModificareIdList),
            }, function (respSave) {

                // se la risposta arriva come stringa, prova parse
                if (typeof respSave === "string") {
                    try { respSave = JSON.parse(respSave); } catch (e) { respSave = null; }
                }

                if (!respSave || !respSave.ok) {
                    errorNotify("Errore", "Salvataggio sportello fallito.");
                    return;
                }

                // 1) prenota aula (se serve)
                // ✅ NON prenotare di nuovo se è uno sportello esistente con aula già assegnata (locked)
                let req = null;
                if (!aulaLocked) {
                    req = prenotaAulaPerSportello(respSave);
                }


                // 2) chiudi e aggiorna UI (anche se prenotazione non serve)
                if (req && typeof req.then === "function") {
                    req.done(function (respPrenota) {
                        if (!respPrenota || respPrenota.ok !== true) {
                            errorNotify("Attenzione", "Sportello salvato ma prenotazione aula NON riuscita.");
                            return;
                        }
                        if (respPrenota.skip) {
                            infoNotify("OK", "Sportello salvato. Prenotazione MBApp già esistente (nessun duplicato).");
                            return;
                        }
                        infoNotify("OK", "Sportello salvato e aula prenotata.");
                    }).fail(function () {
                        errorNotify("Attenzione", "Sportello salvato ma errore di rete nella prenotazione aula.");
                    }).always(function () {
                        $("#sportello_modal").modal("hide");
                        sportelloReadRecords();
                    });
                } else {
                    $("#sportello_modal").modal("hide");
                    sportelloReadRecords();
                }

            }, "json").fail(function () {
                errorNotify("Errore", "Errore di rete o server durante il salvataggio.");
            });
        }
    }
}

function confermaCancellato() {
    if ($("#cancellato").is(':checked')) {
        if ($("#hidden_numero_studenti_iscritti").val() == 0) {
            let result = confirm("Non ci sono studenti iscritti, sei sicuro di voler cancellare lo sportello?\nPuoi eventualmente riprogrammare lo sportello ad un'altra data con una richiesta all'amministratore.");
            if (result === false) {
                $("#cancellato").prop('checked', false);
            }
        }
        else {
            let result = confirm("Sei sicuro di voler cancellare lo sportello? Agli studenti eventualmente iscritti arriverà un avviso di annullamento dello sportello.");
            if (result === false) {
                $("#cancellato").prop('checked', false);
            }
        }
    }
}

function confermaFirmato() {
    if ($("#firmato").is(':checked')) {
        if ($("#hidden_numero_studenti_iscritti").val() == 0) {
            alert("Non puoi firmare uno sportello a cui non ci sono studenti iscritti!");
            $("#firmato").prop('checked', false);
        }
        else {
            var conferma = confirm("Una volta firmato lo sportello non potrai più modificare nulla. Sei sicuro?");
            if (!conferma) {
                $("#firmato").prop('checked', false);
            }
        }

    }
}

function sportelloSplitDueOre(payload) {
    return $.post("sportelloSplitDueOre.php", payload, null, "json");
}

function verificaAulaCorrente() {
    var data = getDbDateFromPickrId("#data");
    var ora = $("#ora").val();
    if (!data || !ora) return;

    var durataOre = parseInt($("#numero_ore").val() || "1", 10);

    // aulaCorrente: se locked usa quella DB, altrimenti usa quella selezionata
    var aulaCorrente = (aulaLocked ? (aulaDbValue || "") : ($("#luogo").val() || "")).trim();

    // includeAula: SOLO se locked (altrimenti deve essere vuoto)
    var includeAula = (aulaLocked && aulaDbValue) ? aulaDbValue : "";

    console.log("Verifica aule: data=" + data + " ora=" + ora + " aulaCorrente=" + aulaCorrente + " locked=" + aulaLocked + " includeAula=" + includeAula);

    $.post("../common/checkAuleLibere.php", {
        dataGiorno: data,
        ora: ora,
        tipo: 'TUTTE',
        includeAula: includeAula,
        durataOre: durataOre   // ✅ NUOVO
    }, function (resp) {


        if (typeof resp === "string") {
            try { resp = JSON.parse(resp); } catch (e) { resp = null; }
        }

        $("#luogo").empty();
        $("#luogo").append('<option value="">Seleziona aula...</option>');

        if (!resp || resp.status !== "ok" || !resp.data || !resp.data.length) {
            // se locked e ho un'aula, la mostro comunque come unica opzione
            if (aulaLocked && aulaCorrente) {
                $("#luogo").empty().append(
                    $('<option>', { value: aulaCorrente, text: aulaCorrente + " (attuale)" })
                );
                $("#luogo").val(aulaCorrente);
            }
            $("#luogo").selectpicker('refresh');
            return;
        }

        var foundCurrent = false;

        resp.data.forEach(function (aula) {
            var label = aula.nroAula;
            if (aula.descrizione) label += " – " + aula.descrizione;
            if (parseInt(aula.is_current, 10) === 1) label += " (attuale)";
            if (aulaCorrente && String(aula.nroAula).trim() === String(aulaCorrente).trim()) {
                foundCurrent = true;
            }


            $("#luogo").append(
                $('<option>', { value: aula.nroAula, text: label })
            );
        });

        // IMPORTANTISSIMO:
        // - se locked: NON cambiare mai l'aula, forza sempre quella corrente
        // - se non locked: mantieni la corrente se valida, altrimenti vuota
        // scegli valore da selezionare
        if (aulaLocked) {
            // sportello con aula già assegnata: forza sempre quella
            if (aulaDbValue) $("#luogo").val(aulaDbValue);
            $("#luogo").prop('disabled', true);
        } else {
            // bozza / senza aula: seleziona prima aula libera SOLO se autoPickFirstAula è true
            if (autoPickFirstAula) {
                // prima option utile (escludo "" che è "Seleziona aula...")
                var first = $("#luogo option").filter(function () { return $(this).val() !== ""; }).first().val() || "";
                $("#luogo").val(first);

                // dopo che l'hai auto-pickata UNA VOLTA, non sovrascrivere più se l'utente cambia data/ora
                autoPickFirstAula = false;
            } else {
                var current = ($("#luogo").val() || "").trim();

                if (current && $("#luogo option[value='" + current.replace(/'/g, "\\'") + "']").length) {
                    // l’aula corrente è ancora valida → tienila
                    $("#luogo").val(current);
                } else {
                    // aula non più valida → auto-pick prima aula libera
                    var first = $("#luogo option").filter(function () {
                        return $(this).val() !== "";
                    }).first().val() || "";

                    $("#luogo").val(first);
                }
            }

            // abilita/disabilita in base alla tua logica (qui NON locked)
            // se vuoi tenerlo abilitato dopo assegna:
            // $("#luogo").prop('disabled', false);
        }

        $("#luogo").selectpicker('refresh');


        // se locked, assicurati che resti disabilitato
        if (aulaLocked) {
            $("#luogo").prop('disabled', true).selectpicker('refresh');
        }

    }, "json");
}


const ORARI = ["07:50", "08:40", "09:30", "10:30", "11:20", "12:10", "13:00", "13:50", "14:40", "15:30", "16:20", "17:10", "18:00", "18:50", "19:40", "20:30", "21:30", "22:20"];

function calcolaOraFine(oraInizio, numeroOre) {
    const i = ORARI.indexOf(oraInizio);
    if (i < 0) return oraInizio; // fallback
    const j = i + (parseInt(numeroOre, 10) || 1);
    return ORARI[Math.min(j, ORARI.length - 1)];
}

function prenotaAulaPerSportello(respSave) {
    if (!respSave || !respSave.ok) return;

    // prenota solo se sportello attivo e aula scelta
    if (parseInt(respSave.attivo, 10) !== 1) return;
    const aula = (respSave.luogo || "").trim();
    if (!aula) return;
    if (parseInt(respSave.cancellato, 10) === 1) return;

    const oraFine = calcolaOraFine(respSave.ora, respSave.numero_ore);

    const attivita = "SPORTELLO " + respSave.materia;
    const motivo = "IMPEGNO IN ISTITUTO";
    const dettagli = "SPORTELLO " + respSave.materia;

    // ✅ id sportello: PRENDILO SEMPRE DALL'HIDDEN
    const idSportello = parseInt($("#hidden_sportello_id").val(), 10) || 0;

    return $.post("../common/prenotaAula.php", {
        idSportello: idSportello,   // ✅ fondamentale
        nroAula: aula,
        dataInizio: respSave.data,
        oraInizio: respSave.ora,
        oraFine: oraFine,
        attivitaProgetto: attivita,
        motivo: motivo,
        dettagli: dettagli
    }, null, "json");
}


function sportelloGetDetails(sportello_id, modificabile, sportello_n_studenti, categoria) {
    resetAulaState();

    $("#hidden_sportello_id").val(sportello_id);
    //    $("#hidden_numero_studenti_iscritti").val(10);
    $("#hidden_numero_studenti_iscritti").val(sportello_n_studenti);
    // default: max iscrizioni bloccato (poi lo sblocca solo sportelloAssegna)
    $("#max_iscrizioni").prop('disabled', true).prop('readonly', true);

    if (sportello_id > 0) {
        $.post("../docente/sportelloReadDetails.php", {
            sportello_id: sportello_id
        }, function (data, status) {
            //console.log(data);
            var sportello = data;

            // ✅ aula dal DB (salvata nella variabile GLOBALE)
            aulaDbValue = (sportello.sportello_luogo || "").trim();

            // ✅ lock se esiste già ed ha aula
            aulaLocked = (sportello_id > 0 && aulaDbValue !== "");
            // ✅ se NON c'è aula salvata, per il docente vogliamo auto-selezionare la prima libera
            autoPickFirstAula = (aulaDbValue === "");

            // ✅ pre-imposta subito il select (anche se poi verrà ripopolato)
            if (aulaDbValue !== "") {
                $("#luogo").val(aulaDbValue);
                $("#luogo").selectpicker('refresh');
            }
            var cancellato = sportello.sportello_cancellato != 0 && sportello.sportello_cancellato != null;
            var firmato = sportello.sportello_firmato != 0 && sportello.sportello_firmato != null;
            setDbDateToPickr(data_pickr, sportello.sportello_data);
            $("#ora").selectpicker('val', sportello.sportello_ora).selectpicker('refresh');
            var docenteNomeDb = ((sportello.docente_cognome || "") + " " + (sportello.docente_nome || "")).trim();
            if (sportello.docente_id && parseInt(sportello.docente_id, 10) > 0 && docenteNomeDb !== "") {
                $("#docente").val(docenteNomeDb);
            } else {
                // bozza / non assegnato: mostra provvisoriamente il docente loggato
                $("#docente").val($("#hidden_docente_cognome_nome").val());
            }
            $('#materia').selectpicker('val', sportello.materia_id);
            $('#categoria').selectpicker('val', sportello.categoria_id);
            $("#numero_ore").selectpicker('val', String(sportello.sportello_numero_ore || "1")).selectpicker('refresh');
            $("#argomento").val(sportello.sportello_argomento);
            $("#ora")
                .selectpicker('val', sportello.sportello_ora || "13:50")
                .selectpicker('refresh');

            // ⏱️ aspetta che UI sia stabile
            setTimeout(function () {
                verificaAulaCorrente();
            }, 200);

            if ($('#hidden_lista_classi').val() == 'testo') {
                $("#classe").val(sportello.classe);
            }
            else {
                $('#classe').selectpicker('val', sportello.classe_id);
            }
            $("#max_iscrizioni").val(sportello.sportello_max_iscrizioni);
            $("#cancellato").prop('checked', sportello.sportello_cancellato != 0 && sportello.sportello_cancellato != null);
            $("#firmato").prop('checked', sportello.sportello_firmato != 0 && sportello.sportello_firmato != null);

            if ($('#hidden_sezione_online_clil').val() == 'true') {
                $("#online").prop('checked', sportello.sportello_online != 0 && sportello.sportello_online != null);
                $("#clil").prop('checked', sportello.sportello_clil != 0 && sportello.sportello_clil != null);
                $("#orientamento").prop('checked', sportello.sportello_orientamento != 0 && sportello.sportello_orientamento != null);
                $("#online").prop('disabled', $('#hidden_modifica_sportelli').val());
                $("#clil").prop('disabled', $('#hidden_modifica_sportelli').val());
                $("#orientamento").prop('disabled', $('#hidden_modifica_sportelli').val());
            }

            if (!modificabile) {
                $("#ora").prop('disabled', true);
                $("#data").prop('disabled', true);
                $("#max_iscrizioni").prop('disabled', true);
                $("#docente").prop('disabled', true);
                $("#materia").prop('disabled', true);
                $("#categoria").prop('disabled', true);
                $("#numero_ore").prop('disabled', true);
                $("#argomento").prop('disabled', true);
                $("#luogo").prop('disabled', true);
                $("#classe").prop('disabled', true);
                $("#firmato").prop('disabled', true);
                $("#cancellato").prop('disabled', true);
                $("#button_save").hide();
            }
            else {
                $("#ora").prop('disabled', $('#hidden_modifica_sportelli').val());
                $("#data").prop('disabled', $('#hidden_modifica_sportelli').val());
                $("#max_iscrizioni").prop('disabled', $('#hidden_modifica_sportelli').val());
                $("#docente").prop('disabled', $('#hidden_modifica_sportelli').val());
                $("#materia").prop('disabled', $('#hidden_modifica_sportelli').val());
                $("#categoria").prop('disabled', $('#hidden_modifica_sportelli').val());
                $("#numero_ore").prop('disabled', $('#hidden_modifica_sportelli').val());
                $("#argomento").prop('disabled', $('#hidden_modifica_sportelli').val());
                var baseDisabled = $('#hidden_modifica_sportelli').val(); // tua logica esistente
                $("#luogo").prop('disabled', baseDisabled || aulaLocked).selectpicker('refresh');
                $("#classe").prop('disabled', $('#hidden_modifica_sportelli').val());
                $("#firmato").prop('disabled', false);
                $("#cancellato").prop('disabled', false);
                $("#button_save").show();
            }

            // abilita la firma se non firmato
            if (!firmato && !cancellato) {
                $("#firma_sportello_button_id").show();
            } else {
                $("#firma_sportello_button_id").hide();
            }

            $('#studenti_table tbody').empty();
            var markup = '';
            // cicla su tutti gli studenti
            // console.log(sportello.studenti);
            sportello.studenti.forEach(function (studenti) {
                // console.log(studenti);
                markup = markup +
                    "<tr>" +
                    "<td>" + studenti.sportello_studente_id + "</td>" +
                    "<td>" + studenti.sportello_studente_presente + "</td>" +
                    "<td style=\"text-align: left; vertical-align: middle;\">" + studenti.studente_cognome + " " + studenti.studente_nome + "</td>" +
                    "<td style=\"text-align: left; vertical-align: middle;\">" + studenti.sportello_studente_argomento + "</td>" +
                    "<td style=\"text-align: center; vertical-align: middle;\">" +
                    "<input type=\"checkbox\" name=\"query_myTextEditBox\"" +
                    ((studenti.sportello_studente_presente == 0 || studenti.sportello_studente_presente == null) ? "" : " checked") +
                    "></td>" +
                    "</tr>";
            });
            $('#studenti_table > tbody:last-child').append(markup);
            $('#studenti_table td:nth-child(1),#studenti_table th:nth-child(1),#studenti_table td:nth-child(2),#studenti_table th:nth-child(2)').hide(); // nasconde la prima colonna con l'id
        });
    } else {
        resetAulaState();
        autoPickFirstAula = true;
        data_pickr.setDate(Date.today().toString('d/M/yyyy'));
        $("#ora").selectpicker('val', "13:50").selectpicker('refresh');
        $('#docente').val($("#hidden_docente_cognome_nome").val());
        $('#docente').selectpicker('refresh');
        $('#materia').val("0");
        $('#materia').selectpicker('refresh');
        $('#categoria').val("0");
        $('#categoria').selectpicker('refresh');
        $("#numero_ore").selectpicker('val', String(sportello.sportello_numero_ore || "1")).selectpicker('refresh');
        $("#argomento").val("");
        $("#luogo").empty().append('<option value="">Seleziona aula...</option>');
        $("#luogo").val("");
        $("#luogo").selectpicker('refresh');

        $('#classe').val("0");
        $("#classe").selectpicker('refresh');
        $("#max_iscrizioni").val($("#hidden_max_iscrizioni_default").val());
        $("#cancellato").prop('checked', false);
        $("#firmato").prop('checked', false);

        if ($('#hidden_sezione_online_clil').val() == 'true') {
            $("#onine").prop('checked', false);
            $("#clil").prop('checked', false);
            $("#orientamento").prop('checked', false);
        }
    }
    $("#_error-materia-part").hide();
    forceHideTooltips();
    $("#sportello_modal").modal("show");
}

$(document).ready(function () {
    data_pickr = flatpickr("#data", {
        locale: {
            firstDayOfWeek: 1
        },
        dateFormat: 'j/n/Y',
        onChange: function () {
            verificaAulaCorrente();
        }
    });

    $("#numero_ore").on("change", function () {
        verificaAulaCorrente();
    });

    $("#ora").on("change", function () {
        verificaAulaCorrente();
    });

    $('#sportello_modal').on('show.bs.modal', function () {
        forceHideTooltips();
    });

    $('#sportello_modal').on('hidden.bs.modal', function () {
        forceHideTooltips();
    });

    sportelloReadRecords();

    $("#categoria_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            categoria_filtro_id = this.value;
            sportelloReadRecords();
        });

    $("#materia_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            materia_filtro_id = this.value;
            sportelloReadRecords();
        });

    $("#classe_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            classe_filtro_id = this.value;
            sportelloReadRecords();
        });
});
