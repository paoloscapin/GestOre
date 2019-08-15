/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var previsteConStudentiChanged = false;

// regole di calcolo pre previste
$('#sportelli_sportelli_previste').change(function() {
    previsteConStudentiRecalc();
});
$('#sportelli_certificazioni_linguistiche_previste').change(function() {
    previsteConStudentiRecalc();
});
$('#sportelli_olimpiadi_previste').change(function() {
    previsteConStudentiRecalc();
});
$('#sportelli_certificazioni_informatiche_previste').change(function() {
    previsteConStudentiRecalc();
});
$('#sportelli_altro_1_previste').change(function() {
    previsteConStudentiRecalc();
});
$('#sportelli_altro_2_previste').change(function() {
    previsteConStudentiRecalc();
});
$('#corsi_recupero_settembre_previste').change(function() {
    previsteConStudentiRecalc();
});
$('#sostituzioni_previste').change(function() {
    previsteConStudentiRecalc();
});
$('#sorveglianza_ricreazioni_previste').change(function() {
    previsteConStudentiRecalc();
});
$('#corsi_recupero_potenziamento_previste').change(function() {
    previsteConStudentiRecalc();
});
$('#accompagnamento_previste').change(function() {
    previsteConStudentiRecalc();
});
$('#ulteriori_concordate_previste').change(function() {
    previsteConStudentiRecalc();
});

// regole di calcolo pre previste fuis
$('#sportelli_sportelli_previste_fuis').change(function() {
    previsteConStudentiRecalcFuis();
});
$('#sportelli_certificazioni_linguistiche_previste_fuis').change(function() {
    previsteConStudentiRecalcFuis();
});
$('#sportelli_olimpiadi_previste_fuis').change(function() {
    previsteConStudentiRecalcFuis();
});
$('#sportelli_certificazioni_informatiche_previste_fuis').change(function() {
    previsteConStudentiRecalcFuis();
});
$('#sportelli_altro_1_previste_fuis').change(function() {
    previsteConStudentiRecalcFuis();
});
$('#sportelli_altro_2_previste_fuis').change(function() {
    previsteConStudentiRecalcFuis();
});
$('#corsi_recupero_settembre_previste_fuis').change(function() {
    previsteConStudentiRecalcFuis();
});
$('#sostituzioni_previste_fuis').change(function() {
    previsteConStudentiRecalcFuis();
});
$('#sorveglianza_ricreazioni_previste_fuis').change(function() {
    previsteConStudentiRecalcFuis();
});
$('#corsi_recupero_potenziamento_previste_fuis').change(function() {
    previsteConStudentiRecalcFuis();
});
$('#accompagnamento_previste_fuis').change(function() {
    previsteConStudentiRecalcFuis();
});
$('#ulteriori_concordate_previste_fuis').change(function() {
    previsteConStudentiRecalcFuis();
});

function previsteConStudentiRecalc() {
	previsteConStudentiChanged = true;
// sportelli_totale_previste = sportelli_sportelli_previste + sportelli_certificazioni_linguistiche_previste + sportelli_olimpiadi_previste + sportelli_certificazioni_informatiche_previste + sportelli_altro_1_previste + sportelli_altro_2_previste
	$("#sportelli_totale_previste").val(+$("#sportelli_sportelli_previste").val() + +$("#sportelli_certificazioni_linguistiche_previste").val() + +$("#sportelli_olimpiadi_previste").val() + +$("#sportelli_certificazioni_informatiche_previste").val() + +$("#sportelli_altro_1_previste").val() + +$("#sportelli_altro_2_previste").val());
	var ore_previste_con_studenti = +$("#sportelli_totale_previste").val() + +$("#corsi_recupero_settembre_previste").val() + +$("#sostituzioni_previste").val() + +$("#sorveglianza_ricreazioni_previste").val() + +$("#corsi_recupero_potenziamento_previste").val() + +$("#accompagnamento_previste").val() + +$("#ulteriori_concordate_previste").val(); 

	$("#ore_previste_con_studenti_riepilogo").html(ore_previste_con_studenti);
	$("#ore_previste_con_studenti").html(ore_previste_con_studenti);
}

function previsteConStudentiRecalcFuis() {
	previsteConStudentiChanged = true;
// sportelli_totale_previste_fuis = sportelli_sportelli_previste_fuis + sportelli_certificazioni_linguistiche_previste_fuis + sportelli_olimpiadi_previste_fuis + sportelli_certificazioni_informatiche_previste_fuis + sportelli_altro_1_previste_fuis + sportelli_altro_2_previste_fuis
	$("#sportelli_totale_previste_fuis").val(+$("#sportelli_sportelli_previste_fuis").val() + +$("#sportelli_certificazioni_linguistiche_previste_fuis").val() + +$("#sportelli_olimpiadi_previste_fuis").val() + +$("#sportelli_certificazioni_informatiche_previste_fuis").val() + +$("#sportelli_altro_1_previste_fuis").val() + +$("#sportelli_altro_2_previste_fuis").val());
	var ore_previste_con_studenti_fuis = +$("#sportelli_totale_previste_fuis").val() + +$("#corsi_recupero_settembre_previste_fuis").val() + +$("#sostituzioni_previste_fuis").val() + +$("#sorveglianza_ricreazioni_previste_fuis").val() + +$("#corsi_recupero_potenziamento_previste_fuis").val() + +$("#accompagnamento_previste_fuis").val() + +$("#ulteriori_concordate_previste_fuis").val(); 

	// $("#ore_previste_con_studenti_fuis_riepilogo").html(ore_previste_con_studenti_fuis);
	$("#ore_previste_con_studenti_fuis").html(ore_previste_con_studenti_fuis);
}

function previsteConStudentiAnnulla() {
	previsteConStudentiChanged = false;
	window.location.assign('index.php');
}

function previsteConStudentiSalva() {
	alert('salva ancora da realizzare...');
	previsteConStudentiChanged = false;
	// TODO: salva
	window.location.assign('index.php');
}
/*
var formSubmitting = false;
var setFormSubmitting = function() { formSubmitting = true; };
function previsteConStudentiAnnulla() {
	alert('annulla');
	
	window.location.assign('index.php');
}

function previsteConStudentiSalva() {
	alert('salva');
	previsteConStudentiChanged = false;
	// TODO: salva
	window.location.assign('index.php');
}

window.onbeforeunload = function(e){
	if ( previsteConStudentiChanged ) {
		return confirm ('Le modifiche non sono state salvate. Vuoi davvero lasciare questa pagina?');
	}
	return true;
};*/