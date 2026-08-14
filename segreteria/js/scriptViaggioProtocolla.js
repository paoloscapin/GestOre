/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

var ore_richieste = 0;
var diaria = 0;
var protocollo = '';
var viaggio_id = '';

function setProtocollo(p) {
	protocollo = p;
}

function setViaggioId(id) {
	viaggio_id = id;
}

// Read records on page load
$(document).ready(function () {

	$('input[type=file]').change(function() {
		inputName = $(this)[0].name;
        fileNameId = '#filename';
        fileNameValueId = '#fileNameValue';
        filePathValueId = '#filePathValue';
        progressBarId = '#progressBar';

		$(this).simpleUpload("./viaggioProtocollaUpload.php", {

            data: {'protocollo': protocollo,'inputName': inputName, 'viaggio_id': viaggio_id},
            maxFileSize: 5000000, //5MB in bytes

			start: function(file){
                console.log('protocollo='+protocollo);
                console.log('inputName='+inputName);
                console.log('viaggio_id='+viaggio_id);
                console.log(file);
                fileName = file.name;
				//upload started
				$(fileNameId).html('<b>'+fileName+'</b>');
                $(progressBarId).show();
				$(progressBarId).width(0);
			},

			progress: function(progress){
				//received progress
				$(progressBarId).html("Progress: " + Math.round(progress) + "%");
				$(progressBarId).width(progress + "%");
			},

			success: function(data){
                console.log("success: data="+data);
                filePath = data.trim();
                $(fileNameValueId).val(fileName);
                $(filePathValueId).val(filePath);

                //upload successful
                $(progressBarId).hide();
				$(fileNameId).html('<b>'+fileName+':</b> caricato con successo.');
//				$(fileNameId).html('<b>'+fileName+':</b> caricato con successo in path='+filePath);
			},

			error: function(error){
				//upload failed
                $(progressBarId).hide();
				$(fileNameId).html(fileName + '<b>' + " errore nel caricamento: " + error.name + ':</b>' + ': ' + error.message);
			}
		});
	});

});