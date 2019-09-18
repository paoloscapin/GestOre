/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var soloAttivi = 1;

$('#testCheckBox').change(function() {
    // this si riferisce al checkbox 
    if (this.checked) {
		soloAttivi = 1;
        docenteReadRecords();
    } else {
		soloAttivi = 0;
        docenteReadRecords();
    }
});

function ricalcola() {
	var profilo_tipo_di_contratto = $("#profilo_tipo_di_contratto").val();
	var profilo_giorni_di_servizio = $("#profilo_giorni_di_servizio").val();
	var profilo_ore_di_cattedra = $("#profilo_ore_di_cattedra").val();
	var profilo_ore_eccedenti = 0;
	
    // Lettura della configurazione della divisione delle 80 0re
    var profilo_ore_max_collegi_docenti = $( "#profilo_ore_max_collegi_docenti" ).val();
    var profilo_ore_max_udienze_generali = $( "#profilo_ore_max_udienze_generali" ).val();
    var profilo_ore_max_dipartimenti = $( "#profilo_ore_max_dipartimenti" ).val();
    var profilo_ore_max_aggiornamento_facoltativo = $( "#profilo_ore_max_aggiornamento_facoltativo" ).val();
    var profilo_ore_max_consigli_di_classe = $( "#profilo_ore_max_consigli_di_classe" ).val();
    var profilo_ore_max_sostituzioni = $( "#profilo_ore_max_sostituzioni" ).val();
    var profilo_minuti_ore_con_studenti = $( "#profilo_minuti_ore_con_studenti" ).val();

	// cattedra + 18 ore, 33 eccedenti
	if (profilo_ore_di_cattedra > 18) {
		profilo_ore_eccedenti = Math.round(33 / 300 * profilo_giorni_di_servizio);
		profilo_ore_di_cattedra = 18;
	}
	$("#profilo_ore_eccedenti").val(profilo_ore_eccedenti);

	// calcola lo standard di 300 gg con 18 ore di cattedra rispetto ai giorni di servizio e ore effettive
	var coefficente = (profilo_giorni_di_servizio * profilo_ore_di_cattedra) / (18 * 300);

	// NB: Per evitare somme di errori decimali, calcola prima il totale (80, 40, 70)
	var totale_80 = Math.round(coefficente * 80);
	var totale_40 = Math.round(coefficente * 40);
	var totale_70 = Math.round(coefficente * 70);

	// scala le ore eccedenti dalle 70
	if (profilo_ore_eccedenti > 0) {
		totale_70 = Math.round(coefficente * 70 - (profilo_ore_eccedenti));
	}
	// se breve, le 70 diventano 0
	if (profilo_tipo_di_contratto.toUpperCase() === "BREVE") {
		// NB: non piu' valido
		// totale_70 = 0;
	}
	
	// le 80 non devono sommare ad 80, tanto ci sono anche min e max
    $( "#profilo_ore_80_collegi_docenti" ).val( Math.round( coefficente * profilo_ore_max_collegi_docenti ) );
    $( "#profilo_ore_80_udienze_generali" ).val( Math.round( coefficente * profilo_ore_max_udienze_generali ) );
    $( "#profilo_ore_80_aggiornamento_facoltativo" ).val( Math.round( coefficente * profilo_ore_max_aggiornamento_facoltativo ) );
    $( "#profilo_ore_80_dipartimenti_min" ).val( Math.round( coefficente * profilo_ore_max_dipartimenti ) );
    $( "#profilo_ore_80_dipartimenti_max" ).val( Math.round( coefficente * profilo_ore_max_dipartimenti ) );
	$( "#profilo_ore_80_consigli_di_classe" ).val( Math.round( coefficente * profilo_ore_max_consigli_di_classe ) );

	// comincio il calcolo delle 40 da aggiornamento che sono in effetti da 60 minuti
	var profilo_ore_40_aggiornamento = Math.round(coefficente * 10);

	// il numero di sostituzioni viene dichiatato in configurazione
	profilo_ore_40_sostituzioni_di_ufficio = Math.round( coefficente * profilo_ore_max_sostituzioni );

	// calcola quanti minuti ho tornato con aggiornamento e sostituzioni insieme
	var minutiRitornati = (profilo_ore_40_aggiornamento * 60) + (profilo_ore_40_sostituzioni_di_ufficio * profilo_minuti_ore_con_studenti);

	// le 40 devono sommare a 40, ma non sono tutte da 60 minuti: calcolo quanti minuti sono dovuti
	var totale_40_in_minuti = coefficente * 40 * 60;
	
	// e dunque ne devo tornare ancora...
	var minutiDaFare = totale_40_in_minuti - minutiRitornati;
	
	// minuti da tornare in sostituzioni da 50 (profilo_minuti_ore_con_studenti) minuti
	var profilo_ore_40_con_studenti = Math.round(minutiDaFare / profilo_minuti_ore_con_studenti);

	$("#profilo_ore_40_sostituzioni_di_ufficio").val(profilo_ore_40_sostituzioni_di_ufficio);
	$("#profilo_ore_40_con_studenti").val(profilo_ore_40_con_studenti);
	$("#profilo_ore_40_aggiornamento").val(profilo_ore_40_aggiornamento);

	// le 70 non hanno questo problema, considero 50 minuti con studenti e 30 funzionali
	var profilo_ore_70_funzionali = Math.round(coefficente * 30);
	// il resto con studenti
	var profilo_ore_70_con_studenti = totale_70 - profilo_ore_70_funzionali;
	$("#profilo_ore_70_funzionali").val(profilo_ore_70_funzionali);
	$("#profilo_ore_70_con_studenti").val(profilo_ore_70_con_studenti);

	if (profilo_tipo_di_contratto.toUpperCase() === "BREVE") {
		$("#profilo_ore_70_funzionali").val(0);
		$("#profilo_ore_70_con_studenti").val(0);
	}

	// le vere 40 si ricalcolano per eliminare errori dovuti ai minuti sospesi in giro
	var totale_40_vero = Math.round(profilo_ore_40_aggiornamento + ((profilo_ore_40_con_studenti + profilo_ore_40_sostituzioni_di_ufficio) / 60 * profilo_minuti_ore_con_studenti));
	$("#profilo_ore_80_totale").val(totale_80);
	$("#profilo_ore_40_totale").val(totale_40_vero);
	$("#profilo_ore_70_totale").val(totale_70);
}

function profiloGetDetails(docente_id) {
	$("#hidden_docente_id").val(docente_id);

	$.post("docenteProfiloReadDetails.php", {
			id: docente_id
		},
		function (data, status) {
			console.log(data);
			var profilo = JSON.parse(data);
			$("#profilo_cognome_e_nome").val(profilo.docente_cognome + " " + profilo.docente_nome);
			$("#profilo_tipo_di_contratto").val(profilo.tipo_di_contratto);
			$("#profilo_giorni_di_servizio").val(profilo.giorni_di_servizio);
			$("#profilo_ore_di_cattedra").val(profilo.ore_di_cattedra);
			$("#profilo_ore_eccedenti").val(profilo.ore_eccedenti);
			$("#profilo_ore_80_collegi_docenti").val(profilo.ore_80_collegi_docenti);
			$("#profilo_ore_80_udienze_generali").val(profilo.ore_80_udienze_generali);
			$("#profilo_ore_80_aggiornamento_facoltativo").val(profilo.ore_80_aggiornamento_facoltativo);
			$("#profilo_ore_80_dipartimenti_min").val(profilo.ore_80_dipartimenti);
			$("#profilo_ore_80_dipartimenti_max").val(profilo.ore_80_dipartimenti);
			$("#profilo_ore_80_consigli_di_classe").val(profilo.ore_80_consigli_di_classe);
			$("#profilo_ore_80_totale").val(profilo.ore_80_totale);
			$("#profilo_ore_40_sostituzioni_di_ufficio").val(profilo.ore_40_sostituzioni_di_ufficio);
			$("#profilo_ore_40_con_studenti").val(profilo.ore_40_con_studenti);
			$("#profilo_ore_40_aggiornamento").val(profilo.ore_40_aggiornamento);
			$("#profilo_ore_40_totale").val(profilo.ore_40_totale);
			$("#profilo_ore_70_funzionali").val(profilo.ore_70_funzionali);
			$("#profilo_ore_70_con_studenti").val(profilo.ore_70_con_studenti);
			$("#profilo_ore_70_totale").val(profilo.ore_70_totale);
			$("#profilo_note").val(profilo.note);
			
			// hidden fields
			$("#hidden_profilo_docente_id").val(profilo.profilo_docente_id);
			$("#hidden_ore_dovute_id").val(profilo.ore_dovute_id);
			$("#hidden_ore_previste_id").val(profilo.ore_previste_id);

            // Scritture della configurazione della divisione delle 80 0re
            $( "#profilo_ore_max_collegi_docenti" ).val( profilo.ore_max_collegi_docenti );
            $( "#profilo_ore_max_udienze_generali" ).val( profilo.ore_max_udienze_generali );
            $( "#profilo_ore_max_dipartimenti" ).val( profilo.ore_max_dipartimenti );
            $( "#profilo_ore_max_aggiornamento_facoltativo" ).val( profilo.ore_max_aggiornamento_facoltativo );
            $( "#profilo_ore_max_consigli_di_classe" ).val( profilo.ore_max_consigli_di_classe );
            $( "#profilo_ore_max_sostituzioni" ).val( profilo.ore_max_sostituzioni );
            $( "#profilo_minuti_ore_con_studenti" ).val( profilo.minuti_ore_con_studenti );
			}
	 );
	$("#update_profilo_modal").modal("show");
}

function profiloUpdateDetails() {
	$.post("docenteProfiloUpdateDetails.php", {
		profilo_id: $("#hidden_profilo_docente_id").val(),
		docente_id: $("#hidden_docente_id").val(),
		docente_cognome_e_nome: $("#profilo_cognome_e_nome").val(),
		ore_dovute_id: $("#hidden_ore_dovute_id").val(),
		ore_previste_id: $("#hidden_ore_previste_id").val(),
		tipo_di_contratto: $("#profilo_tipo_di_contratto").val(),
		giorni_di_servizio: $("#profilo_giorni_di_servizio").val(),
		ore_di_cattedra: $("#profilo_ore_di_cattedra").val(),
		ore_eccedenti: $("#profilo_ore_eccedenti").val(),
		ore_80_collegi_docenti: $("#profilo_ore_80_collegi_docenti").val(),
		ore_80_udienze_generali: $("#profilo_ore_80_udienze_generali").val(),
		ore_80_aggiornamento_facoltativo: $("#profilo_ore_80_aggiornamento_facoltativo").val(),
		ore_80_dipartimenti_min: $("#profilo_ore_80_dipartimenti_min").val(),
		ore_80_dipartimenti_max: $("#profilo_ore_80_dipartimenti_max").val(),
		ore_80_consigli_di_classe: $("#profilo_ore_80_consigli_di_classe").val(),
		ore_80_totale: $("#profilo_ore_80_totale").val(),

		ore_40_sostituzioni_di_ufficio: $("#profilo_ore_40_sostituzioni_di_ufficio").val(),
		ore_40_con_studenti: $("#profilo_ore_40_con_studenti").val(),
		ore_40_aggiornamento: $("#profilo_ore_40_aggiornamento").val(),
		ore_40_totale: $("#profilo_ore_40_totale").val(),

		ore_70_funzionali: $("#profilo_ore_70_funzionali").val(),
		ore_70_con_studenti: $("#profilo_ore_70_con_studenti").val(),
		ore_70_totale: $("#profilo_ore_70_totale").val(),
		note: $("#profilo_note").val()
     },
     function (data, status) {
         $("#update_profilo_modal").modal("hide");
         docenteReadRecords();
     }
 );
}

function docenteAddRecord() {
    $.post("docenteAddRecord.php", {
        nome: $("#nome").val(),
        cognome: $("#cognome").val(),
        email: $("#email").val(),
        username: $("#username").val(),
        matricola: $("#matricola").val(),
		attivo: $("#attivo").val()
    }, function (data, status) {
        $("#add_new_record_modal").modal("hide");

        docenteReadRecords();

        $("#nome").val("");
        $("#cognome").val("");
        $("#email").val("");
        $("#username").val("");
        $("#matricola").val("");
        $("#attivo").val("");
    });
}

function docenteReadRecords() {
	$.get("docenteReadRecords.php?soloAttivi=" + soloAttivi, {}, function (data, status) {
		$(".records_content").html(data);
	});
}

function docenteDelete(id, cognome, nome) {
    var conf = confirm("Sei sicuro di volere cancellare il docente " + cognome + " " + nome + " ?");
    if (conf == true) {
        $.post("../common/deleteRecord.php", {
				id: id,
				table: 'docente',
				name: "docente " + cognome + " " + nome
            },
            function (data, status) {
                docenteReadRecords();
            }
        );
    }
}

function docenteGetDetails(id) {
	$("#hidden_docente_id").val(id);
	$.post("docenteReadDetails.php", {
			id: id
		},
		function (data, status) {
			var docente = JSON.parse(data);
			$("#update_cognome").val(docente.cognome);
			$("#update_nome").val(docente.nome);
			$("#update_email").val(docente.email);
			$("#update_username").val(docente.username);
			$("#update_matricola").val(docente.matricola);
			$('#update_attivo').bootstrapToggle(docente.attivo == 1? 'on' : 'off');
			$("#hidden_era_attivo").val(docente.attivo);
		}
    );
	$("#update_docente_modal").modal("show");
}

function docenteUpdateDetails() {
    $.post("docenteUpdateDetails.php", {
            docente_id: $("#hidden_docente_id").val(),
            cognome: $("#update_cognome").val(),
            nome: $("#update_nome").val(),
            email: $("#update_email").val(),
            username: $("#update_username").val(),
            matricola: $("#update_matricola").val(),
			attivo: $("#update_attivo").is(':checked')? 1: 0,
			era_attivo: $("#hidden_era_attivo").val()
        },
        function (data, status) {
            $("#update_docente_modal").modal("hide");
            docenteReadRecords();
        }
    );
}

$(document).ready(function () {
    docenteReadRecords();
});