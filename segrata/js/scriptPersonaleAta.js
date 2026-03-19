/**
 *  This file is part of GestOre
 */

var soloAttivi = 1;

$('#testCheckBox').change(function () {
    soloAttivi = this.checked ? 1 : 0;
    personaleAtaReadRecords();
});

function personaleAtaReadRecords() {
    $.get("personaleAtaReadRecords.php?soloAttivi=" + soloAttivi, {}, function (data) {
        $(".records_content").html(data);
    });
}

function personaleAtaAddRecord() {
    $.post("personaleAtaAddRecord.php", {
        nome: $("#nome").val(),
        cognome: $("#cognome").val(),
        email: $("#email").val(),
        username: $("#username").val(),
        matricola: $("#matricola").val(),
        codice_fiscale: $("#codice_fiscale").val(),
        ruolo: $("#ruolo").val(),
        attivo: $("#attivo").is(':checked') ? 1 : 0
    }, function () {
        $("#add_new_record_modal").modal("hide");
        personaleAtaReadRecords();

        $("#nome,#cognome,#email,#username,#matricola,#codice_fiscale,#ruolo").val("");
        $("#attivo").prop("checked", true);
    });
}

function personaleAtaGetDetails(id) {
    $("#hidden_id").val(id);

    $.post("personaleAtaReadDetails.php", { id: id }, function (data) {
        var p = (typeof data === "string") ? JSON.parse(data) : data;

        $("#update_nome").val(p.nome || "");
        $("#update_cognome").val(p.cognome || "");
        $("#update_email").val(p.email || "");
        $("#update_username").val(p.username || "");
        $("#update_matricola").val(p.matricola || "");
        $("#update_codice_fiscale").val(p.codice_fiscale || "");
        $("#update_ruolo").val(p.ruolo || "");

        $('#update_attivo').bootstrapToggle((parseInt(p.attivo, 10) === 1) ? 'on' : 'off');
    });

    $("#update_modal").modal("show");
}

function personaleAtaUpdateDetails() {
    $.post("personaleAtaUpdateDetails.php", {
        id: $("#hidden_id").val(),
        nome: $("#update_nome").val(),
        cognome: $("#update_cognome").val(),
        email: $("#update_email").val(),
        username: $("#update_username").val(),
        matricola: $("#update_matricola").val(),
        codice_fiscale: $("#update_codice_fiscale").val(),
        ruolo: $("#update_ruolo").val(),
        attivo: $("#update_attivo").is(':checked') ? 1 : 0
    }, function () {
        $("#update_modal").modal("hide");
        personaleAtaReadRecords();
    });
}

function personaleAtaDelete(id, cognome, nome) {
    var conf = confirm("Sei sicuro di volere cancellare il personale ATA " + cognome + " " + nome + " ?");
    if (!conf) return;

    $.post("../common/deleteRecord.php", {
        id: id,
        table: 'personale_ata',
        name: "personale ATA " + cognome + " " + nome
    }, function () {
        personaleAtaReadRecords();
    });
}

$(document).ready(function () {
    personaleAtaReadRecords();
});
