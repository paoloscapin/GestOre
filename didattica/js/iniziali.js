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
var $anni_filtro_id = params.get("a") || "1"; // default 
console.log("anno scolastico corrente: " + $anni_filtro_id);

var $classi_filtro_id = 0;
var $materia_filtro_id = 0;
var $docenti_filtro_id = 0;
var $da_completare_filtro_id = 0;
var activeInizialiPreviewField = null;

function escapeInizialiPreviewHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function isInizialiPreviewUppercase(text) {
    var trimmed = $.trim(text);
    return /[\p{L}]/u.test(trimmed) && !/[\p{Ll}]/u.test(trimmed);
}

function detectInizialiPreviewLevel(raw, currentParent) {
    var normalized = String(raw || '').replace(/\t/g, '  ').replace(/\s+$/u, '');
    var trimmed = normalized.replace(/^\s+/u, '');

    var bulletMatch = trimmed.match(/^(?:[•●▪◦◦]\s+|--\s+|>\s+|-\s+|\*\s+)(.+)$/u);
    if (bulletMatch) {
        return {
            level: (currentParent !== null ? 1 : 0),
            text: $.trim(bulletMatch[1])
        };
    }

    var indentMatch = normalized.match(/^ {2,}(.+)$/u);
    if (indentMatch) {
        return {
            level: (currentParent !== null ? 1 : 0),
            text: $.trim(indentMatch[1])
        };
    }

    return {
        level: 0,
        text: $.trim(trimmed)
    };
}

function buildInizialiPreviewTree(text) {
    var lines = String(text || '').split(/\r\n|\r|\n/u);
    var tree = [];
    var currentParent = null;
    var nextIsChild = false;

    lines.forEach(function (line) {
        var rawLine = String(line || '').replace(/\s+$/u, '');
        if (rawLine === '') {
            nextIsChild = false;
            return;
        }

        var literalDotMap = {};
        rawLine = rawLine.replace(/\.{2,}/gu, function (match) {
            var token = '__GESTORE_LITERAL_DOTS_' + Object.keys(literalDotMap).length + '__';
            literalDotMap[token] = match;
            return token;
        });

        var segments = rawLine.split(/(?<!\.)\.(?!\.)\s*/u);

        segments.forEach(function (segment) {
            var raw = $.trim(String(segment || '').replace(/__GESTORE_LITERAL_DOTS_\d+__/g, function (token) {
                return literalDotMap[token] || token;
            }));
            if (!raw) {
                return;
            }

            var headingMatch = raw.match(/^>>\s*(.+)$/u);
            if (headingMatch) {
                tree.push({ type: 'heading', text: $.trim(headingMatch[1]).replace(/[.;:]\s*$/u, ''), children: [] });
                currentParent = null;
                nextIsChild = false;
                return;
            }

            if (isInizialiPreviewUppercase(raw)) {
                tree.push({ type: 'heading', text: raw.replace(/[.;:]\s*$/u, ''), children: [] });
                currentParent = null;
                nextIsChild = false;
                return;
            }

            raw = raw.replace(/;\s*$/u, '');
            var endsWithColon = /:\s*$/u.test(raw);
            var detected = detectInizialiPreviewLevel(raw, currentParent);
            var textLi = detected.text;
            var level = detected.level;

            if (nextIsChild && level === 0 && currentParent !== null) {
                level = 1;
                nextIsChild = false;
            }

            if (level === 0) {
                tree.push({ type: 'item', text: textLi, children: [] });
                currentParent = tree.length - 1;
            } else {
                if (currentParent === null) {
                    tree.push({ type: 'item', text: '', children: [] });
                    currentParent = tree.length - 1;
                }
                tree[currentParent].children.push({ text: textLi });
            }

            if (endsWithColon) {
                nextIsChild = true;
            }
        });
    });

    return tree;
}

function renderInizialiPreviewHtml(text) {
    var tree = buildInizialiPreviewTree(text);
    if (!tree.length) {
        return '<span class="text-muted">Anteprima non disponibile: inizia a scrivere.</span>';
    }

    var html = '';
    var ulOpen = false;

    tree.forEach(function (node) {
        var type = node.type || 'item';
        if (type === 'heading') {
            if (ulOpen) {
                html += '</ul>';
                ulOpen = false;
            }
            html += '<p><strong>' + escapeInizialiPreviewHtml(node.text || '') + '</strong></p>';
            return;
        }

        if (!ulOpen) {
            html += '<ul>';
            ulOpen = true;
        }

        html += '<li>' + escapeInizialiPreviewHtml(node.text || '');
        if (node.children && node.children.length) {
            html += '<ul>';
            node.children.forEach(function (child) {
                html += '<li>' + escapeInizialiPreviewHtml(child.text || '') + '</li>';
            });
            html += '</ul>';
        }
        html += '</li>';
    });

    if (ulOpen) {
        html += '</ul>';
    }

    return html;
}

function renderInizialiPreviewLinesHtml(text, activeLine) {
    var lines = String(text || '').split(/\r\n|\r|\n/u);
    if (!String(text || '').length) {
        return '<span class="text-muted">Qui vedi tutto il testo, con `↵` a fine riga.</span>';
    }

    var safeActiveLine = parseInt(activeLine, 10);
    if (!safeActiveLine || safeActiveLine < 1) {
        safeActiveLine = 1;
    }

    var html = [];
    for (var i = 1; i <= lines.length; i++) {
        var line = lines[i - 1];
        var classes = 'programma-preview-line';
        if (i === safeActiveLine) {
            classes += ' programma-preview-line-active';
        }
        if (line === '') {
            classes += ' programma-preview-line-empty';
            html.push('<div class="' + classes + '">riga vuota<span class="programma-preview-crlf">↵</span></div>');
            continue;
        }
        html.push('<div class="' + classes + '">' + escapeInizialiPreviewHtml(line) + '<span class="programma-preview-crlf">↵</span></div>');
    }

    return html.join('');
}

function getInizialiTextareaLine(textarea) {
    if (!textarea) {
        return 1;
    }
    var caret = typeof textarea.selectionStart === 'number' ? textarea.selectionStart : String(textarea.value || '').length;
    return String(textarea.value || '').slice(0, caret).split(/\r\n|\r|\n/u).length;
}

function syncInizialiPreviewScroll(textareaSelector, previewSelector, activeLine) {
    var textarea = $(textareaSelector).get(0);
    var preview = $(previewSelector).get(0);
    if (!textarea || !preview) {
        return;
    }

    var lines = String($(textareaSelector).val() || '').split(/\r\n|\r|\n/u);
    var totalLines = Math.max(lines.length, 1);
    var safeActiveLine = Math.max(1, Math.min(parseInt(activeLine, 10) || 1, totalLines));

    if (preview.scrollHeight <= preview.clientHeight) {
        preview.scrollTop = 0;
        return;
    }

    var ratio = totalLines <= 1 ? 0 : ((safeActiveLine - 1) / (totalLines - 1));
    var maxScroll = preview.scrollHeight - preview.clientHeight;
    preview.scrollTop = Math.max(0, Math.round(maxScroll * ratio) - 40);
}

function updateInizialiFieldPreview(textareaSelector, previewSelector, linesSelector, activeLine) {
    var value = $(textareaSelector).val() || '';
    $(previewSelector).html(renderInizialiPreviewHtml(value));
    $(linesSelector).html(renderInizialiPreviewLinesHtml(value, activeLine));
    syncInizialiPreviewScroll(textareaSelector, previewSelector, activeLine);
    syncInizialiPreviewScroll(textareaSelector, linesSelector, activeLine);
}

function hideInizialiFieldPreview() {
    activeInizialiPreviewField = null;
    $('.programma-preview-row').removeClass('is-active');
    $('.programma-active-edit-group').removeClass('programma-active-edit-group');
    $('[id$="_preview_top_actions"]').hide();
    $('#programma_modal, #modulo_modal').removeClass('programma-editing-mode');
}

function showInizialiFieldPreview(fieldId) {
    activeInizialiPreviewField = fieldId;

    $('.programma-preview-row').removeClass('is-active');
    $('.programma-active-edit-group').removeClass('programma-active-edit-group');
    $('[id$="_preview_top_actions"]').hide();

    $('#' + fieldId + '_preview_top_actions').show();
    
    $('#' + fieldId + '_preview_row').addClass('is-active');
    $('#' + fieldId).closest('.form-group').addClass('programma-active-edit-group');

    if ($('#' + fieldId).closest('#programma_modal').length) {
        $('#programma_modal').addClass('programma-editing-mode');
        $('#modulo_modal').removeClass('programma-editing-mode');
    }

    if ($('#' + fieldId).closest('#modulo_modal').length) {
        $('#modulo_modal').addClass('programma-editing-mode');
        $('#programma_modal').removeClass('programma-editing-mode');
    }
}

function syncInizialiFieldPreview(fieldId) {
    var $textarea = $('#' + fieldId);
    if (!$textarea.length) {
        return;
    }
    updateInizialiFieldPreview('#' + fieldId, '#' + fieldId + '_preview', '#' + fieldId + '_lines', getInizialiTextareaLine($textarea.get(0)));
}

function bindInizialiPreviewEvents() {
    $('#conoscenze, #abilita, #competenze, #periodo')
        .off('.programmaPreview')
        .on('focus.programmaPreview', function () {
            showInizialiFieldPreview(this.id);
            syncInizialiFieldPreview(this.id);
        })
        .on('input.programmaPreview keyup.programmaPreview click.programmaPreview', function () {
            showInizialiFieldPreview(this.id);
            syncInizialiFieldPreview(this.id);
        });

    $('.programma-preview-done')
        .off('click.programmaPreview')
        .on('click.programmaPreview', function () {
            var fieldId = $(this).data('preview-field');
            hideInizialiFieldPreview();
            if (fieldId) {
                $('#' + fieldId).blur();
            }
        });
}


$('#daCompletareCheckBox').change(function () {
    // this si riferisce al checkbox
    if (this.checked) {
        $da_completare_filtro_id = 1;
        $('#send_btn').show();
    } else {
        $da_completare_filtro_id = 0;
        $('#send_btn').hide();
    }
    programmiInizialiReadRecords();
});

function programmiInizialiReadRecords() {
    if ($("#hidden_docente_id").val() > 0)
        $docenti_filtro_id = $("#hidden_docente_id").val();
    $.get("programmiInizialiReadRecords.php?classi_id=" + $classi_filtro_id + "&materia_id=" + $materia_filtro_id + "&docenti_id=" + $docenti_filtro_id + "&da_completare_id=" + $da_completare_filtro_id + "&anni_id=" + $anni_filtro_id, {}, function (data, status) {
        $(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body'
        });
    });
}

function mostraOverlay() {
    $('#progressOverlay').show();
}

function nascondiOverlay() {
    $('#progressOverlay').hide();
}

function aggiornaProgressBar() {
    completati++;
    const percentuale = Math.round((completati / totale) * 100);
    $('#progressBar').css('width', percentuale + '%').text(percentuale + '%');

    if (completati === totale) {
        setTimeout(() => {
            nascondiOverlay();
            alert("Tutte le email sono state inviate!");
        }, 500);
    }
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function inviaSollecito(single_id) {
    if (single_id > 0) {
        totale = 1;
        completati = 0;
        await $.post("inviaSollecitoProgrammiIniziali.php", {
            id: single_id
        }).then(response => {
            if (response.trim() !== 'sent') {
                console.error(`Errore per programma ID ${single_id}: ${response}`);
            }
        }).catch(err => {
            console.error(`Errore AJAX per studente ID ${single_id}:`, err);
        });
        aggiornaProgressBar();
        await sleep(Math.floor(Math.random() * 5000) + 1000); // tra 1 e 2 secondi    
    }
    else {
        const sollecito = $('#hidden_sollecito').val();
        const sollecito_array = sollecito.split(',');
        totale = sollecito_array.length;
        completati = 0;

        if (totale > 0) {
            mostraOverlay();

            for (const soll of sollecito_array) {
                await $.post("inviaSollecitoProgrammiIniziali.php", {
                    id: soll
                }).then(response => {
                    if (response.trim() !== 'sent') {
                        console.error(`Errore per programma ID ${soll}: ${response}`);
                    }
                }).catch(err => {
                    console.error(`Errore AJAX per studente ID ${soll}:`, err);
                });

                aggiornaProgressBar();
                await sleep(Math.floor(Math.random() * 5000) + 1000); // tra 1 e 2 secondi
            }
        } else {
            alert("Nessun sollecito da inviare!");
        }
    }
}

function moduliInizialiReadRecords(programma_id) {
    $.get("../didattica/moduliInizialiReadRecords.php", {
        programma_id: programma_id
    }, function (data, status) {
        $(".moduli_content").val("");
        $(".moduli_content")
        $(".moduli_content").html(data);
    });

}

function programmiInizialiGetDetails(programma_id, duplica, share) {
    $("#hidden_programma_id").val(programma_id);
    $("#hidden_duplica").val(duplica);
    $("#hidden_share").val(share);
    const isDidattica = ($("#hidden_is_didattica").val() === "1");
    id_docente = $('#docente').val();
    if (duplica == 'true') {
        $("#myModalLabel1").html("Duplica il programma per un altra classe");
    }
    else
        if (share == 'true') {
            $("#myModalLabel1").html("Invia una copia del programma al codocente della classe");
        }
        else {
            $("#myModalLabel1").html("Programma iniziale");
        }
    if (programma_id > 0) {
        $.post("../didattica/programmiInizialiReadDetails.php", {
            programma_id: programma_id
        }, function (data, status) {
            var programma = JSON.parse(data);
            if (duplica == 'true') {
                $('#classe').selectpicker('val', 0);
            }
            else {
                $('#classe').selectpicker('val', programma.programma_classe);
            }
            if (share == 'true') {
                $('#docente').selectpicker('val', 0);
            }
            else {
                $('#docente').selectpicker('val', programma.programma_iddocente);
            }

            $('#materia').selectpicker('val', programma.programma_idmateria);

            // ✅ Disabilitazioni: il dirigente può sempre modificare i select
            if (!isDidattica) {
                if (duplica == 'false') {
                    $('#classe').attr('disabled', true);
                } else {
                    $('#classe').attr('disabled', false);
                }

                if (share == 'false') {
                    $('#docente').attr('disabled', true);
                } else {
                    $('#docente').attr('disabled', false);
                }

                $('#materia').attr('disabled', true);
            } else {
                // didattica: sempre editabili
                $('#classe').attr('disabled', false);
                $('#docente').attr('disabled', false);
                $('#materia').attr('disabled', false);
            }

            $('#classe').selectpicker('refresh');
            $('#materia').selectpicker('refresh');
            $('#docente').selectpicker('refresh');
        });
        moduliInizialiReadRecords(programma_id);
    }
    else {
        $('#classe').attr('disabled', false);

        const docenteUtenteId = parseInt($("#hidden_docente_id").val() || "0", 10);

        // Reset nuovo programma
        $('#classe').selectpicker('val', 0);
        $('#materia').selectpicker('val', 0);
        $(".moduli_content").html("");

        // Docente: se sono docente loggato -> preimposta e blocca
        // altrimenti (segreteria/dirigente) -> reset e sblocca
        if (docenteUtenteId > 0) {
            $('#docente').selectpicker('val', String(docenteUtenteId));
            $('#docente').attr('disabled', true);
        } else {
            $('#docente').selectpicker('val', 0);      // ✅ evita "ultimo docente visto"
            $('#docente').attr('disabled', false);
        }

        // Materia selezionabile nel nuovo
        $('#materia').attr('disabled', false);

        // Refresh
        $('#classe').selectpicker('refresh');
        $('#docente').selectpicker('refresh');
        $('#materia').selectpicker('refresh');
    }

    $("#_error-programma-part").hide();
    $("#programma_modal").modal("show");
}

function verificaCampiObbligatori() {
    const docente = $("#docente").val();
    const classe = $("#classe").val();
    const materia = $("#materia").val();

    if (!docente || docente === "0") {
        alert("⚠️ Devi selezionare un docente prima di procedere.");
        return false;
    }
    if (!classe || classe === "0") {
        alert("⚠️ Devi selezionare una classe prima di procedere.");
        return false;
    }
    if (!materia || materia === "0") {
        alert("⚠️ Devi selezionare una materia prima di procedere.");
        return false;
    }
    return true;
}


async function moduliInizialiImport() {
    if (!verificaCampiObbligatori()) return;

    try {
        let programma_id = parseInt($("#hidden_programma_id").val(), 10);
        if (isNaN(programma_id)) programma_id = -1;

        // Se non esiste ancora, salva prima
        if (programma_id < 0) {
            const saveResp = await $.ajax({
                url: "programmiInizialiSave.php",
                type: "POST",
                data: {
                    id: '-1',
                    docente_id: $("#docente").val(),
                    classe_id: $("#classe").val(),
                    materia_id: $("#materia").val(),
                    duplica: 'false',
                    share: 'false',
                    overwrite: 'false'
                }
            });
            $("#hidden_programma_id").val(saveResp);
            programma_id = parseInt(saveResp, 10);
        }

        console.log("programma ID after", programma_id);

        if (programma_id > 0) {
            const conf = confirm("Sei sicuro di volere importare il programma di dipartimento? Eventuali moduli già presenti saranno sovrascritti.");
            if (!conf) return;

            const importResp = await $.ajax({
                url: "../didattica/moduliInizialiImport.php",
                type: "POST",
                dataType: "json",
                data: {
                    programma_id: programma_id,
                    classe_id: $('#classe').val(),
                    materia_id: $('#materia').val()
                }
            });

            // Se arrivo qui, HTTP è 200. Controllo lo status applicativo:
            if (importResp.status === 'error') {
                alert('⚠️ ' + (importResp.message || 'Errore durante l\'importazione.'));
                return;
            }

            console.log("Importazione completata");
            $("#moduliTableContainer").html(importResp.html); // se usi questo contenitore
            moduliInizialiReadRecords($("#hidden_programma_id").val());
        }
    } catch (jqXHR) {
        // jqXHR può essere un oggetto XHR oppure un Error
        const status = jqXHR.status || '';
        const text = jqXHR.responseText || jqXHR.statusText || jqXHR.message || jqXHR.toString();
        console.error("Errore nell'importazione:", status, text);

        // Mostra un messaggio utile per capire il 500
        alert("❌ Errore durante l'importazione (HTTP " + status + ").\n" +
            "Dettagli: " + (text.length > 500 ? text.slice(0, 500) + '…' : text));
    }
}


async function moduliInizialiSvoltiImport() {
    if (!verificaCampiObbligatori()) return; // blocca l'esecuzione se mancano campi
    let programma_id = $("#hidden_programma_id").val();

    // 1️⃣ Clona le opzioni dal select #classe, ma ignora la prima (value=0)
    const $sourceSelect = $("#classe");
    const $targetSelect = $("#classeImportSelect");

    // Copia solo le opzioni con value diverso da 0
    const validOptions = $sourceSelect.find("option").filter(function () {
        return $(this).val() !== "0" && $(this).val() !== "";
    }).clone();

    $targetSelect.html(validOptions);
    $targetSelect.selectpicker('refresh');

    // 2️⃣ Mostra il modale di scelta
    return new Promise((resolve) => {
        $("#modalImportClasse").modal("show");

        // Rimuoviamo eventuali handler precedenti
        $("#btnConfermaImportClasse").off("click").on("click", async function () {
            const classeImportId = $("#classeImportSelect").val();
            const classeImportName = $("#classeImportSelect option:selected").text();

            if (!classeImportId || classeImportId === "0") {
                alert("⚠️ Seleziona una classe valida da cui importare il programma svolto.");
                return;
            }

            $("#modalImportClasse").modal("hide");

            // 3️⃣ Se il programma non è ancora salvato, salvalo prima
            if (programma_id < 0) {
                programma_id = await new Promise((resolve, reject) => {
                    $.post("programmiInizialiSave.php", {
                        id: '-1',
                        docente_id: $("#docente").val(),
                        classe_id: $("#classe").val(),
                        materia_id: $("#materia").val(),
                        duplica: 'false',
                        share: 'false',
                        overwrite: 'false'
                    }, function (data, status) {
                        $("#hidden_programma_id").val(data);
                        resolve(data);
                    }).fail(function (jqXHR, textStatus, errorThrown) {
                        console.error("Errore nel salvataggio:", textStatus, errorThrown);
                        reject(errorThrown);
                    });
                });
            }

            console.log("programma ID after " + programma_id);

            // 4️⃣ Chiedi conferma all’utente
            const confermaImport = confirm(
                `Sei sicuro di voler importare il programma svolto lo scorso anno dalla classe "${classeImportName}"?\n\nEventuali moduli già presenti saranno sovrascritti.`
            );
            if (!confermaImport) return;

            // 5️⃣ Esegui la chiamata AJAX per importare
            await new Promise((resolve2, reject2) => {
                $.post("../didattica/moduliInizialiSvoltiImport.php", {
                    programma_id: programma_id,
                    classe_id: classeImportId, // 👈 classe scelta nel modale
                    materia_id: $('#materia').val()
                }, function (data) {
                    try {
                        if (typeof data === "string") data = JSON.parse(data);

                        if (data.status === "error") {
                            alert(data.message);
                            reject2(data.message);
                        } else if (data.status === "success") {
                            console.log("Importazione completata");
                            $("#moduliTableContainer").html(data.html);
                            moduliInizialiReadRecords($("#hidden_programma_id").val());
                            resolve2();
                        } else {
                            console.error("Risposta non riconosciuta:", data);
                            reject2("Risposta non riconosciuta");
                        }
                    } catch (e) {
                        console.error("Errore parsing JSON:", e, data);
                        reject2(e);
                    }
                }).fail(function (jqXHR, textStatus, errorThrown) {
                    console.error("Errore nell'importazione:", textStatus, errorThrown);
                    reject2(errorThrown);
                });
            });

            resolve();
        });
    });
}



async function moduloInizialiGetDetails(modulo_id) {
    if (!verificaCampiObbligatori()) return; // blocca l'esecuzione se mancano campi
    let programma_id = $("#hidden_programma_id").val();

    // Se il programma id è negativo, salviamo prima
    if (programma_id < 0) {

        programma_id = await new Promise((resolve, reject) => {
            $.post("programmiInizialiSave.php", {
                id: '-1',
                docente_id: $("#docente").val(),
                classe_id: $("#classe").val(),
                materia_id: $("#materia").val(),
                duplica: 'false',
                share: 'false',
                overwrite: 'false'
            }, function (data, status) {
                console.log('data save ' + data);
                $("#hidden_programma_id").val(data);
                resolve(data);
            }).fail(function (jqXHR, textStatus, errorThrown) {
                console.error("Errore nel salvataggio:", textStatus, errorThrown);
                reject(errorThrown);
            });
        });
    }
    programma_id = $("#hidden_programma_id").val();
    $("#hidden_modulo_id").val(modulo_id);
    let nmoduli = $("#hidden_nmoduli").val();

    if (modulo_id > 0) {
        const data = await new Promise((resolve, reject) => {
            $.post("../didattica/moduloInizialiReadDetails.php", {
                modulo_id: modulo_id
            }, function (data, status) {
                resolve(data);
            }).fail(function (jqXHR, textStatus, errorThrown) {
                console.error("Errore nel recupero dettagli modulo:", textStatus, errorThrown);
                reject(errorThrown);
            });
        });
        const programma = JSON.parse(data);
        $('#titolo').val(programma.modulo_nome);
        $('#ordine').val(programma.modulo_ordine);
        $('#competenze').val(programma.modulo_competenze);
        $('#conoscenze').val(programma.modulo_conoscenze);
        $('#abilita').val(programma.modulo_abilita);
        $('#periodo').val(programma.modulo_periodo);
    }
    else {
        console.log("Nmoduli " + nmoduli);
        console.log("Nmoduli bis " + parseInt(nmoduli));
        $('#titolo').val("");
        $('#ordine').val(parseInt(nmoduli) + 1);
        $('#conoscenze').val("");
        $('#abilita').val("");
        $('#competenze').val("");
        $('#periodo').val("");
        $("#moduli_content").html("");
    }
    $("#_error-modulo-part").hide();
    hideInizialiFieldPreview();
    $("#modulo_modal").modal("show");
    syncInizialiFieldPreview('conoscenze');
    syncInizialiFieldPreview('abilita');
    syncInizialiFieldPreview('competenze');
    syncInizialiFieldPreview('periodo');
}


function programmiInizialiDelete(id, materia) {
    var conf = confirm("Sei sicuro di volere cancellare il programma di " + materia + " ?");
    if (conf == true) {
        $.post("../didattica/moduliElimina.php", { // da AGGIORNARE
            id: id
        });
        $.post("../common/deleteRecord.php", {
            id: id,
            table: 'programmi_iniziali',
            name: "materia" + materia
        },
            function (data, status) {
                programmiInizialiReadRecords();
            }
        );
    }
}

function programmiInizialiPrint(id_programma) {
    // creo form nascosto
    var form = $('<form>', {
        action: 'stampaProgrammiIniziali.php',
        method: 'POST',
        target: '_black'    // apre in un nuovo tab
    });
    // aggiungo i campi
    form.append($('<input>', { type: 'hidden', name: 'id', value: id_programma }));
    form.append($('<input>', { type: 'hidden', name: 'print', value: 0 }));
    form.append($('<input>', { type: 'hidden', name: 'titolo', value: 'Programma iniziale' }));
    // lo “submitto” e lo rimuovo
    form.appendTo('body').submit().remove();
}

function moduloInizialiDelete(id, id_programma, titolo) {
    var conf = confirm("Sei sicuro di volere cancellare il modulo  " + titolo + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
            id: id,
            table: 'programmi_iniziali_moduli',
            name: "nome" + titolo
        },
            function (data, status) {
                moduliInizialiReadRecords(id_programma);
                //$("#programma_modal").modal("hide");
            }
        );
    }
}

function programmiInizialiSave() {

    if ($("#docente").val() <= 0) {
        $("#_error-programma").text("Devi selezionare un docente");
        $("#_error-programma-part").show();
        return;
    }
    if ($("#classe").val() <= 0) {
        $("#_error-programma").text("Devi selezionare una classe");
        $("#_error-programma-part").show();
        return;
    }
    if ($("#materia").val() <= 0) {
        $("#_error-programma").text("Devi selezionare una materia");
        $("#_error-programma-part").show();
        return;
    }

    $("#_error-programma-part").hide();

    $.post("programmiInizialiSave.php", {
        id: $("#hidden_programma_id").val(),
        docente_id: $("#docente").val(),
        classe_id: $("#classe").val(),
        materia_id: $("#materia").val(),
        duplica: $("#hidden_duplica").val(),
        share: $("#hidden_share").val()
    }, function (data, status) {
        if (data == 'Programma già esistente') {
            if ($("#hidden_share").val() == 'true') {
                alert("Non puoi condividere il programma con il docente, perchè ha già un programma presente!")
            }
            else {
                alert("Esiste già il programma nella classe di destinazione!");
            }
        }
        else {
            $("#programma_modal").modal("hide");
            programmiInizialiReadRecords();
        }

    });
}

function moduloInizialiSave() {

    if ($.trim($("#ordine").val()).length <= 0) {
        $("#_error-modulo").text("Devi indicare l'ordine del modulo, ad es. 1");
        $("#_error-modulo-part").show();
        return;
    }
    if ($.trim($("#titolo").val()).length <= 0) {
        $("#_error-modulo").text("Devi indicare il titolo del modulo");
        $("#_error-modulo-part").show();
        return;
    }
    if ($.trim($("#competenze").val()).length <= 0) {
        $("#_error-modulo").text("Devi indicare le competenze del modulo");
        $("#_error-modulo-part").show();
        return;
    }
    if ($.trim($("#abilita").val()).length <= 0) {
        $("#_error-modulo").text("Devi indicare le abilita del modulo");
        $("#_error-modulo-part").show();
        return;
    }
    if ($.trim($("#conoscenze").val()).length <= 0) {
        $("#_error-modulo").text("Devi indicare le conoscenze del modulo");
        $("#_error-modulo-part").show();
        return;
    }
    if ($.trim($("#periodo").val()).length <= 0) {
        $("#_error-modulo").text("Devi indicare il periodo del modulo");
        $("#_error-modulo-part").show();
        return;
    }
    $("#_error-modulo-part").hide();
    console.log("salvataggio in corso");
    $.post("moduloInizialiSave.php", {
        id: $("#hidden_modulo_id").val(),
        id_programma: $("#hidden_programma_id").val(),
        ordine: $("#ordine").val(),
        titolo: $("#titolo").val(),
        conoscenze: $("#conoscenze").val(),
        abilita: $("#abilita").val(),
        competenze: $("#competenze").val(),
        periodo: $("#periodo").val()
    }, function (data, status) {
        $("#modulo_modal").modal("hide");
        hideInizialiFieldPreview();
        moduliInizialiReadRecords($("#hidden_programma_id").val());
    });

}


$(document).ready(function () {

    bindInizialiPreviewEvents();
    hideInizialiFieldPreview();

    $('#modulo_modal').on('show.bs.modal hidden.bs.modal', function () {
        hideInizialiFieldPreview();
    });

    programmiInizialiReadRecords();

    $("#classi_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $classi_filtro_id = this.value;
            programmiInizialiReadRecords();
        });

    $("#anni_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $anni_filtro_id = this.value;
            programmiInizialiReadRecords();
        });

    $('#send_btn').on('click', function (e) {
        inviaSollecito(-1);
    });

    $("#materia_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $materia_filtro_id = this.value;
            programmiInizialiReadRecords();
        });

    $("#docente_filtro").on("changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {
            $docenti_filtro_id = this.value;
            programmiInizialiReadRecords();
        });
    $('#send_btn').hide();
});     
