/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

$('.checkbox-inline').change(function() {
	saveConfigurazione();
});

function saveConfigurazione() {
	$.post("configurazioneUpdate.php", {
			bonus_adesione_aperto: $('#bonus_adesione_checkbox').prop("checked"),
			bonus_rendiconto_aperto: $('#bonus_rendiconto_checkbox').prop("checked"),
			ore_previsioni_aperto: $('#ore_previsioni_checkbox').prop("checked"),
			ore_fatte_aperto: $('#ore_fatte_checkbox').prop("checked"),
			voti_recupero_settembre_aperto: $('#voti_recupero_settembre_checkbox').prop("checked"),
			voti_recupero_novembre_aperto: $('#voti_recupero_novembre_checkbox').prop("checked")
		},
		function (data, status) {
			// console.log(data);
		}
	);
}


function salvaImporti() {

	$.post("../common/importi_save.php", {
			importo_id: $('#hidden_importo_id').val(),
			importo_fuis: $('#importo_fuis').val(),
			importo_fuis_clil: $('#importo_fuis_clil').val(),
			importo_bonus: $('#importo_bonus').val()
		},
		function (data, status) {
			$.notify({
				icon: 'glyphicon glyphicon-ok',
				title: '<Strong>Importi</Strong></br>',
				message: '<p>I nuovi importi sono stati salvati!</p></br>FUIS: ' + $('#importo_fuis').val() + '</br>FUIS Clil: ' +$('#importo_fuis_clil').val() + '</br>Bonus: ' + $('#importo_bonus').val()
			},{
				placement: {
					from: "top",
					align: "center"
				},
				delay: 3000,
				timer: 100,
				mouse_over: "pause",
				type: 'success'
			});	
		}
	);
}


