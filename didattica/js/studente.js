/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var soloAttivi = 1;
var classe_filtro_id = 0;
var studenteFotoStream = null;
var studenteFotoDataUrl = "";
var studenteSelfieSegmentation = null;
var studenteFotoSegmentationError = "";

function studenteSessoDaCodiceFiscale(cf) {
    cf = String(cf || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
    if (cf.length < 11) return '';
    var day = parseInt(cf.substr(9, 2), 10);
    if (day >= 1 && day <= 31) return 'M';
    if (day >= 41 && day <= 71) return 'F';
    return '';
}

function studenteAggiornaSessoDaCodiceFiscale(force) {
    var $sesso = $("#sesso");
    if (!$sesso.length) return;
    if (!force && $sesso.val()) return;
    $sesso.val(studenteSessoDaCodiceFiscale($("#codice_fiscale").val()));
}

function studenteNormalizzaSesso(value) {
    value = String(value || '').trim().toUpperCase();
    if (value === 'MASCHIO' || value === 'MASCHILE') return 'M';
    if (value === 'FEMMINA' || value === 'FEMMINILE') return 'F';
    return (value === 'M' || value === 'F') ? value : '';
}

function studenteSetFotoMastercom(file) {
    file = String(file || '').trim();
    if (!file) {
        $("#foto_mastercom").attr("src", "");
        $("#foto_mastercom_part").show();
        return;
    }
    if (/^(https?:)?\/\//.test(file) || file.indexOf("uploads/") === 0) {
        $("#foto_mastercom").attr("src", file);
        $("#foto_mastercom_part").show();
        return;
    }
    $("#foto_mastercom")
        .attr("src", "../common/mastercom/photo.php?proxy=1&file=" + encodeURIComponent(file));
    $("#foto_mastercom_part").show();
}

function studenteFotoSetMsg(message, isError) {
    $("#foto_studente_msg")
        .toggleClass("text-danger", !!isError)
        .toggleClass("text-muted", !isError)
        .text(message || "");
}

function studenteFotoReset() {
    studenteFotoDataUrl = "";
    $("#foto_studente_salva_btn").prop("disabled", true);
    var canvas = document.getElementById("foto_studente_canvas");
    if (canvas) {
        var ctx = canvas.getContext("2d");
        ctx.fillStyle = "#fff";
        ctx.fillRect(0, 0, canvas.width, canvas.height);
    }
}

async function studenteFotoApriCamera() {
    if (!$("#hidden_studente_id").val() || parseInt($("#hidden_studente_id").val(), 10) <= 0) {
        $("#foto_studente_camera_part").show();
        studenteFotoSetMsg("Salva prima lo studente, poi puoi scattare la foto.", true);
        return;
    }
    try {
        studenteFotoReset();
        $("#foto_studente_camera_part").show();
        studenteFotoStream = await navigator.mediaDevices.getUserMedia({
            video: { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: "user" },
            audio: false
        });
        document.getElementById("foto_studente_video").srcObject = studenteFotoStream;
        studenteFotoSetMsg("Webcam attiva. Posiziona il volto e premi Scatta.", false);
    } catch (e) {
        studenteFotoSetMsg("Impossibile aprire la webcam: " + (e && e.message ? e.message : e), true);
    }
}

function studenteFotoChiudiCamera() {
    if (studenteFotoStream) {
        studenteFotoStream.getTracks().forEach(function (track) { track.stop(); });
        studenteFotoStream = null;
    }
    $("#foto_studente_camera_part").hide();
}

async function studenteFotoDetectFace(canvas) {
    if (!("FaceDetector" in window)) return null;
    try {
        var detector = new FaceDetector({ fastMode: true, maxDetectedFaces: 1 });
        var faces = await detector.detect(canvas);
        return faces && faces.length ? faces[0].boundingBox : null;
    } catch (e) {
        return null;
    }
}

function studenteFotoCropBox(sourceWidth, sourceHeight, face) {
    var targetRatio = 3 / 4;
    var cropH;
    var cx;
    var cy;

    if (face) {
        cx = face.x + face.width / 2;
        cy = face.y + face.height * 1.06;
        cropH = Math.max(face.height * 3.75, sourceHeight * 0.74);
    } else {
        cx = sourceWidth / 2;
        cy = sourceHeight * 0.56;
        cropH = sourceHeight * 0.94;
    }

    cropH = Math.min(cropH, sourceHeight);
    var cropW = cropH * targetRatio;
    if (cropW > sourceWidth) {
        cropW = sourceWidth;
        cropH = cropW / targetRatio;
    }

    var x = Math.max(0, Math.min(sourceWidth - cropW, cx - cropW / 2));
    var y = Math.max(0, Math.min(sourceHeight - cropH, cy - cropH * 0.50));
    return { x: x, y: y, width: cropW, height: cropH };
}

function studenteFotoAutoTono(canvas) {
    var ctx = canvas.getContext("2d");
    var img = ctx.getImageData(0, 0, canvas.width, canvas.height);
    var data = img.data;
    var count = 0;
    var sumR = 0;
    var sumG = 0;
    var sumB = 0;
    var sumL = 0;
    var minL = 255;
    var maxL = 0;

    for (var i = 0; i < data.length; i += 4) {
        var r = data[i];
        var g = data[i + 1];
        var b = data[i + 2];
        if (r > 246 && g > 246 && b > 246) continue;
        var l = 0.2126 * r + 0.7152 * g + 0.0722 * b;
        if (l < 18 || l > 245) continue;
        sumR += r;
        sumG += g;
        sumB += b;
        sumL += l;
        minL = Math.min(minL, l);
        maxL = Math.max(maxL, l);
        count++;
    }

    if (count < 200) return;

    var avgR = sumR / count;
    var avgG = sumG / count;
    var avgB = sumB / count;
    var avgL = sumL / count;
    var gray = (avgR + avgG + avgB) / 3;
    var gainR = Math.max(0.92, Math.min(1.08, gray / Math.max(1, avgR)));
    var gainG = Math.max(0.92, Math.min(1.08, gray / Math.max(1, avgG)));
    var gainB = Math.max(0.92, Math.min(1.08, gray / Math.max(1, avgB)));
    var brightness = Math.max(-12, Math.min(16, 132 - avgL));
    var contrast = Math.max(1.02, Math.min(1.16, 1 + (150 - Math.min(150, maxL - minL)) / 900));

    for (var j = 0; j < data.length; j += 4) {
        var rr = data[j];
        var gg = data[j + 1];
        var bb = data[j + 2];
        if (rr > 248 && gg > 248 && bb > 248) {
            data[j] = data[j + 1] = data[j + 2] = 255;
            continue;
        }
        data[j] = Math.max(0, Math.min(255, ((rr * gainR - 128) * contrast + 128) + brightness));
        data[j + 1] = Math.max(0, Math.min(255, ((gg * gainG - 128) * contrast + 128) + brightness));
        data[j + 2] = Math.max(0, Math.min(255, ((bb * gainB - 128) * contrast + 128) + brightness));
    }

    ctx.putImageData(img, 0, 0);
}

function studenteFotoSegmentaSfondo(inputCanvas, outputCanvas) {
    return new Promise(function (resolve) {
        studenteFotoSegmentationError = "";
        if (typeof SelfieSegmentation === "undefined") {
            studenteFotoSegmentationError = "MediaPipe Selfie Segmentation non caricato.";
            resolve(false);
            return;
        }

        try {
            if (!studenteSelfieSegmentation) {
                studenteSelfieSegmentation = new SelfieSegmentation({
                    locateFile: function (file) {
                        return "https://cdn.jsdelivr.net/npm/@mediapipe/selfie_segmentation/" + file;
                    }
                });
                studenteSelfieSegmentation.setOptions({ modelSelection: 0, selfieMode: true });
            }

            studenteSelfieSegmentation.onResults(function (results) {
                var ctx = outputCanvas.getContext("2d");
                try {
                    var maskCanvas = document.createElement("canvas");
                    var imageCanvas = document.createElement("canvas");
                    maskCanvas.width = imageCanvas.width = outputCanvas.width;
                    maskCanvas.height = imageCanvas.height = outputCanvas.height;

                    var maskCtx = maskCanvas.getContext("2d");
                    var imageCtx = imageCanvas.getContext("2d");
                    maskCtx.filter = "blur(7px)";
                    maskCtx.drawImage(results.segmentationMask, 0, 0, outputCanvas.width, outputCanvas.height);
                    maskCtx.filter = "none";
                    imageCtx.drawImage(results.image, 0, 0, outputCanvas.width, outputCanvas.height);

                    var mask = maskCtx.getImageData(0, 0, outputCanvas.width, outputCanvas.height);
                    var image = imageCtx.getImageData(0, 0, outputCanvas.width, outputCanvas.height);
                    var out = ctx.createImageData(outputCanvas.width, outputCanvas.height);
                    for (var i = 0; i < image.data.length; i += 4) {
                        var alpha = Math.max(mask.data[i], mask.data[i + 1], mask.data[i + 2], mask.data[i + 3]) / 255;
                        alpha = Math.max(0, Math.min(1, (alpha - 0.05) / 0.90));
                        alpha = alpha * alpha * (3 - 2 * alpha);
                        out.data[i] = Math.round(image.data[i] * alpha + 255 * (1 - alpha));
                        out.data[i + 1] = Math.round(image.data[i + 1] * alpha + 255 * (1 - alpha));
                        out.data[i + 2] = Math.round(image.data[i + 2] * alpha + 255 * (1 - alpha));
                        out.data[i + 3] = 255;
                    }
                    ctx.putImageData(out, 0, 0);
                    resolve(true);
                } catch (e) {
                    ctx.save();
                    ctx.clearRect(0, 0, outputCanvas.width, outputCanvas.height);
                    ctx.drawImage(results.segmentationMask, 0, 0, outputCanvas.width, outputCanvas.height);
                    ctx.globalCompositeOperation = "source-in";
                    ctx.drawImage(results.image, 0, 0, outputCanvas.width, outputCanvas.height);
                    ctx.globalCompositeOperation = "destination-over";
                    ctx.fillStyle = "#fff";
                    ctx.fillRect(0, 0, outputCanvas.width, outputCanvas.height);
                    ctx.restore();
                    resolve(true);
                }
            });
            var sendResult = studenteSelfieSegmentation.send({ image: inputCanvas });
            if (sendResult && typeof sendResult.catch === "function") {
                sendResult.catch(function (e) {
                    studenteFotoSegmentationError = e && e.message ? e.message : "Errore MediaPipe durante la segmentazione.";
                    resolve(false);
                });
            }
        } catch (e) {
            studenteFotoSegmentationError = e && e.message ? e.message : "Errore MediaPipe durante la segmentazione.";
            resolve(false);
        }
    });
}

async function studenteFotoScatta() {
    var video = document.getElementById("foto_studente_video");
    var outputCanvas = document.getElementById("foto_studente_canvas");
    if (!video || !video.videoWidth || !video.videoHeight) {
        studenteFotoSetMsg("La webcam non e ancora pronta.", true);
        return;
    }

    studenteFotoSetMsg("Elaborazione foto in corso...", false);
    var sourceCanvas = document.createElement("canvas");
    sourceCanvas.width = video.videoWidth;
    sourceCanvas.height = video.videoHeight;
    sourceCanvas.getContext("2d").drawImage(video, 0, 0, sourceCanvas.width, sourceCanvas.height);

    var face = await studenteFotoDetectFace(sourceCanvas);
    var crop = studenteFotoCropBox(sourceCanvas.width, sourceCanvas.height, face);
    var cropCanvas = document.createElement("canvas");
    cropCanvas.width = outputCanvas.width;
    cropCanvas.height = outputCanvas.height;
    var cropCtx = cropCanvas.getContext("2d");
    cropCtx.fillStyle = "#fff";
    cropCtx.fillRect(0, 0, cropCanvas.width, cropCanvas.height);
    cropCtx.drawImage(sourceCanvas, crop.x, crop.y, crop.width, crop.height, 0, 0, cropCanvas.width, cropCanvas.height);

    var segmented = await studenteFotoSegmentaSfondo(cropCanvas, outputCanvas);
    if (!segmented) {
        outputCanvas.getContext("2d").drawImage(cropCanvas, 0, 0);
    }

    studenteFotoAutoTono(outputCanvas);
    studenteFotoDataUrl = outputCanvas.toDataURL("image/jpeg", 0.92);
    $("#foto_studente_salva_btn").prop("disabled", false);
    studenteFotoSetMsg(segmented ? "Foto pronta: ritaglio 3:4 e sfondo bianco applicati." : ("Foto pronta: ritaglio 3:4 applicato. Sfondo non rimosso" + (studenteFotoSegmentationError ? ": " + studenteFotoSegmentationError : ".")).replace("..", "."), false);
}

function studenteFotoElimina() {
    var studenteId = parseInt($("#hidden_studente_id").val(), 10);
    if (!studenteId || studenteId <= 0) {
        studenteFotoSetMsg("Seleziona prima uno studente esistente.", true);
        return;
    }
    if (!confirm("Eliminare la foto dello studente?")) {
        return;
    }

    studenteFotoSetMsg("Eliminazione foto in corso...", false);
    $.post("studenteFotoDelete.php", {
        studente_id: studenteId
    }, function (response) {
        if (typeof response === "string") {
            response = JSON.parse(response);
        }
        if (!response.ok) {
            studenteFotoSetMsg(response.message || "Eliminazione foto fallita.", true);
            return;
        }
        studenteSetFotoMastercom("");
        studenteFotoReset();
        studenteFotoSetMsg(response.message || "Foto eliminata.", !response.mastercom_delete || !response.mastercom_delete.ok);
        studenteReadRecords();
    }).fail(function () {
        studenteFotoSetMsg("Eliminazione foto fallita.", true);
    });
}

function studenteFotoSalva() {
    if (!studenteFotoDataUrl) {
        studenteFotoSetMsg("Scatta prima una foto.", true);
        return;
    }

    $("#foto_studente_salva_btn").prop("disabled", true);
    studenteFotoSetMsg("Salvataggio foto in corso...", false);
    $.post("studenteFotoUpload.php", {
        studente_id: $("#hidden_studente_id").val(),
        image: studenteFotoDataUrl
    }, function (response) {
        if (typeof response === "string") {
            response = JSON.parse(response);
        }
        if (!response.ok) {
            studenteFotoSetMsg(response.message || "Salvataggio foto fallito.", true);
            $("#foto_studente_salva_btn").prop("disabled", false);
            return;
        }
        if (response.local_url) {
            studenteSetFotoMastercom(response.local_url);
        }
        studenteFotoSetMsg(response.message || "Foto salvata.", !response.mastercom_upload || !response.mastercom_upload.ok);
        studenteReadRecords();
    }).fail(function () {
        studenteFotoSetMsg("Salvataggio foto fallito.", true);
        $("#foto_studente_salva_btn").prop("disabled", false);
    });
}

function studenteReadRecords() {
    $.get("studenteReadRecords.php?soloAttivi=" + soloAttivi + "&classeFiltroId=" + classe_filtro_id, {}, function (data, status) {
        $(".records_content").html(data);
        recordsTextFilterApply();
    });
}

function recordsTextFilterApply() {
    var filter = String($("#records_text_filter").val() || "").toLowerCase().trim();
    $(".records_content table tbody tr").each(function () {
        var text = $(this).text().toLowerCase();
        $(this).toggle(filter === "" || text.indexOf(filter) !== -1);
    });
}

function getQueryParam(name) {
    return new URLSearchParams(window.location.search).get(name);
}

$('#soloAttiviCheckBox').change(function () {
    // this si riferisce al checkbox
    if (this.checked) {
        soloAttivi = 1;
    } else {
        soloAttivi = 0;
    }
    studenteReadRecords();
});

function studenteDelete(id, cognome, nome) {
    var conf = confirm("Sei sicuro di volere cancellare lo studente " + cognome + " " + nome + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
            id: id,
            table: 'studente',
            name: "cognome " + cognome
        },
            function (data, status) {
                studenteReadRecords();
            }
        );
    }
}

function studenteImpersona(id, cognome, nome) {
    $.post("agisciComeStudente.php", {
        studente_id: id
    }, function (data, status) {
        window.open('/GestOre/studente/index.php', '_blank');
    });
}

function studenteSave() {

    if ($("#classe_filtro_stud").val() <= 0) {
        $("#_error-classe").text("Devi selezionare una classe per lo studente.");
        $("#_error-classe-part").show();
        return;
    }

    attivo = $("#attivo").prop('checked') ? 1 : 0;

    if (attivo == 0 && ($("#hidden_attivo").val() == 1)) {
        var conf = confirm("Sei sicuro di volere disattivare lo studente " + $("#cognome").val() + " " + $("#nome").val() + "?");
        if (conf == false) {
            return;
        }
    }
    if (attivo == 1 && ($("#hidden_attivo").val() == 0)) {
        var conf = confirm("Sei sicuro di volere inserire per quest'anno lo studente " + $("#cognome").val() + " " + $("#nome").val() + "?");
        if (conf == false) {
            return;
        }
    }

    $("#_error-classe-part").hide();
    $.post("studenteSave.php", {
        id: $("#hidden_studente_id").val(),
        cognome: $("#cognome").val(),
        nome: $("#nome").val(),
        email: $("#email").val(),
        id_classe: $("#classe_filtro_stud").val(),
        id_anno: $("#hidden_anno_id").val(),
        codice_fiscale: $("#codice_fiscale").val(),
        sesso: $("#sesso").val(),
        userid: $("#userId").val(),
        attivo: $("#attivo").prop('checked') ? 1 : 0,
        esterno: $("#esterno").prop('checked') ? 1 : 0,
        era_attivo: $("#hidden_attivo").val()
    }, function (data, status) {
        $("#studente_modal").modal("hide");
        studenteReadRecords();
    });
}

function studenteGetDetails(studente_id, anno_id) {
    $("#hidden_studente_id").val(studente_id);

    // helper: evita "undefined"/null e normalizza
    function safeStr(v) {
        return (v === undefined || v === null) ? "" : String(v);
    }

    if (studente_id > 0) {
        $.post("studenteReadDetails.php", { id: studente_id }, function (data, status) {

            var studente = JSON.parse(data);

            // ✅ RESET preventivo dei campi che possono mancare
            $("#codice_fiscale").val("");
            $("#sesso").val("");
            $("#userId").val("");
            studenteSetFotoMastercom("");
            studenteFotoChiudiCamera();
            studenteFotoReset();
            $("#email").val("");
            $("#genitore_select").empty().append('<option value="">-- Seleziona genitore --</option>');
            $("#btn-passa-genitore").hide();
            $('#frequenta_table tbody').empty();

            // Valori base
            $("#cognome").val(safeStr(studente.cognome));
            $("#nome").val(safeStr(studente.nome));

            var email = safeStr(studente.email);
            $("#email").val(email ? email.toLowerCase() : "");

            var cf = safeStr(studente.codice_fiscale);
            $("#codice_fiscale").val(cf ? cf.toUpperCase() : "");
            $("#sesso").val(studenteNormalizzaSesso(studente.sesso) || studenteSessoDaCodiceFiscale(cf));

            $("#userId").val(safeStr(studente.username));
            studenteSetFotoMastercom(safeStr(studente.gestore_foto_url) || studente.mastercom_foto);

            $("#classe_filtro_stud").val(safeStr(studente.id_classe));
            $("#classe_filtro_stud").selectpicker('refresh');

            $('#hidden_anno_id').val(safeStr(studente.id_anno_scolastico));

            var attivo = (studente.attivo != 0 && studente.attivo != null);
            $("#attivo").prop('checked', attivo);
            $('#hidden_attivo').val(attivo ? 1 : 0);

            // Frequenze (se manca, resta vuoto grazie al reset)
            var markup = '';
            if (Array.isArray(studente.frequenze)) {
                studente.frequenze.forEach(function (frequenza) {
                    markup +=
                        "<tr>" +
                        "<td style=\"text-align: center; vertical-align: middle;\">" + safeStr(frequenza.anno) + "</td>" +
                        "<td style=\"text-align: center; vertical-align: middle;\">" + safeStr(frequenza.classe) + "</td>" +
                        "</tr>";
                });
            }
            $('#frequenta_table > tbody:last-child').append(markup);

            // Genitori: (se manca, resta placeholder + bottone nascosto grazie al reset)
            var $btnPassa = $("#btn-passa-genitore");
            var $sel = $("#genitore_select");

            if (Array.isArray(studente.genitori) && studente.genitori.length > 0) {
                $btnPassa.show();

                studente.genitori.forEach(function (g) {
                    $sel.append(
                        '<option value="' + safeStr(g.id) + '">' +
                        (safeStr(g.cognome) + ' ' + safeStr(g.nome)).trim() +
                        '</option>'
                    );
                });

                $sel.val(String(studente.genitori[0].id));
            } else {
                $btnPassa.hide();
                $sel.val("");
            }

            $sel.selectpicker('refresh');

            $("#btn-passa-genitore").off("click").on("click", function () {
                var genitoreId = $("#genitore_select").val();
                if (!genitoreId) return;
                window.location.href = "genitore.php?id=" + encodeURIComponent(genitoreId);
            });
        });

    } else {
        // già ok: qui stai facendo reset
        $("#cognome").val("");
        $("#nome").val("");
        $("#email").val("");
        $("#classe_filtro_stud").val("0");
        $("#classe_filtro_stud").selectpicker('refresh');
        $("#codice_fiscale").val("");
        $("#sesso").val("");
        $("#userId").val("");
        studenteSetFotoMastercom("");
        studenteFotoChiudiCamera();
        studenteFotoReset();
        $("#hidden_anno_id").val(anno_id);
        $("#attivo").prop('checked', true);
        $('#hidden_studente_id').val("-1");
        $('#frequenta_table tbody').empty();
        $("#genitore_select").empty()
            .append('<option value="">-- Seleziona genitore --</option>')
            .selectpicker('refresh');
        $('#btn-save').show();
        $("#btn-passa-genitore").hide(); // ✅ anche qui
    }

    $("#studente_modal").modal("show");
    $("#_error-classe-part").hide();
}

function importFile(file) {
    var contenuto = "";
    const reader = new FileReader();
    reader.addEventListener('load', (event) => {
        contenuto = event.target.result;
        $.post("studenteImport.php", {
            contenuto: contenuto
        },
            function (data, status) {
                $('#result_text').html(data);
                studenteReadRecords();

                // Dopo 10 secondi svuota il testo
                setTimeout(function () {
                    $('#result_text').html('');
                }, 10000);
            });
    });
    reader.readAsText(file);
}

$("#classe_filtro").on("changed.bs.select",
    function (e, clickedIndex, newValue, oldValue) {
        classe_filtro_id = this.value;
        studenteReadRecords();
    });

$(document).ready(function () {

    studenteReadRecords();

    $("#codice_fiscale").on("input change", function () {
        studenteAggiornaSessoDaCodiceFiscale(false);
    });

    $("#records_text_filter").on("input", recordsTextFilterApply);

    $('#file_select_id').off('change').on('change', function (e) {
        importFile(e.target.files[0]);
    });

    // IMPORTANTISSIMO: il modal di collegamento deve stare sotto <body>
    $("#collega_genitore_modal").appendTo("body");

    $("#studente_modal").on("hidden.bs.modal", function () {
        studenteFotoChiudiCamera();
        studenteFotoReset();
    });

    function cleanupBackdrops() {
        // rimuove QUALSIASI backdrop rimasto
        $(".modal-backdrop").remove();
        $("body").removeClass("modal-open").css("padding-right", "");
    }

    // click: collega genitore
    $(document).off("click", "#btn-collega-genitore").on("click", "#btn-collega-genitore", function () {
        var studenteId = parseInt($("#hidden_studente_id").val(), 10);
        if (!studenteId || studenteId <= 0) {
            alert("Seleziona prima uno studente esistente.");
            return;
        }

        // quando studente_modal è veramente chiuso...
        $("#studente_modal").one("hidden.bs.modal", function () {
            cleanupBackdrops();

            $("#collega_genitore_error").hide().find("div").text("");

            // carica genitori attivi
            $.get("genitoreListAttivi.php", {}, function (data) {
                var genitori = JSON.parse(data);
                var $sel = $("#genitore_select_link");
                $sel.empty().append('<option value=""></option>');
                genitori.forEach(function (g) {
                    $sel.append($("<option>", { value: g.id, text: g.cognome + " " + g.nome }));
                });
                $sel.selectpicker("refresh");
            });

            // carica relazioni
            $.get("relazioniList.php", {}, function (data) {
                var rel = JSON.parse(data);
                var $r = $("#relazione_select_link");
                $r.empty().append('<option value=""></option>');
                rel.forEach(function (x) {
                    $r.append($("<option>", { value: x.id, text: x.nome }));
                });
                $r.selectpicker("refresh");
            }).fail(function () {
                var $r = $("#relazione_select_link");
                $r.empty()
                    .append('<option value=""></option>')
                    .append('<option value="1">Padre</option><option value="2">Madre</option><option value="3">Tutore</option>');
                $r.selectpicker("refresh");
            });

            // apri il modal DOPO aver ripulito backdrop
            setTimeout(function () {
                $("#collega_genitore_modal").modal({ backdrop: "static", keyboard: false });
            }, 50);
        });

        // chiudi il modale studente
        $("#studente_modal").modal("hide");
    });

    // conferma collegamento (puoi lasciare il tuo, ma metto delegato)
    $(document).off("click", "#btn-conferma-collega-genitore").on("click", "#btn-conferma-collega-genitore", function () {
        var studenteId = parseInt($("#hidden_studente_id").val(), 10);
        var genitoreId = parseInt($("#genitore_select_link").val(), 10);
        var relazioneId = parseInt($("#relazione_select_link").val(), 10);

        if (!genitoreId) {
            $("#collega_genitore_error").show().find("div").text("Seleziona un genitore.");
            return;
        }
        if (!relazioneId) {
            $("#collega_genitore_error").show().find("div").text("Seleziona una relazione.");
            return;
        }

        $.post("genitoreCollegaStudente.php", {
            id_genitore: genitoreId,
            id_studente: studenteId,
            id_relazione: relazioneId
        }, function (data) {
            var res = JSON.parse(data);
            if (res.error) {
                $("#collega_genitore_error").show().find("div").text(res.error);
                return;
            }

            $("#collega_genitore_modal").modal("hide");

            var annoId = parseInt($("#hidden_anno_id").val(), 10) || 0;
            studenteGetDetails(studenteId, annoId);
        });

    });

// AUTO-OPEN da URL: studente.php?id=123
try {
    var idFromUrl = (typeof getQueryParam === "function") ? getQueryParam('id') : null;
    var openId = (idFromUrl && parseInt(idFromUrl, 10) > 0) ? parseInt(idFromUrl, 10) : null;

    if (openId) {
        var anno = (typeof window.anno_id_corrente !== "undefined") ? parseInt(window.anno_id_corrente, 10) : 0;

        // esegui dopo un tick, così la pagina è stabile
        setTimeout(function () {
            studenteGetDetails(openId, anno);
        }, 0);

        history.replaceState(null, '', 'studente.php');
    }
} catch (e) {
    console.error("Auto-open studente fallito:", e);
}



});
