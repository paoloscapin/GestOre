/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */


var categoria_filtro_id = 0;
var conSportelli = 0;
var conSportelliFatti = 0;
var senzaSportelli = 0;
var soloPassati = 0;
var soloFuturi = 0;

function export_to_csv()
{
    console.log("ciao");
    window.location="./sportelli_export.csv";
}

function sportelloStatisticheEffettuatiReadRecords() {
	$.get("sportelloStatisticheEffettuatiReadRecords.php?categoria_filtro_id=" + categoria_filtro_id + "&con_sportelli=" + conSportelli + "&senza_sportelli=" + senzaSportelli + "&con_sportelli_fatti=" + conSportelliFatti + "&soloFuturi=" + soloFuturi + "&soloPassati=" + soloPassati, {}, function (data, status) {
		$(".records_content").html(data);
        $('[data-toggle="tooltip"]').tooltip({
            container: 'body'
        });
	});
}

$(document).ready(function () {
    sportelloStatisticheEffettuatiReadRecords();
    $("#categoria_filtro").on("changed.bs.select", 
    function(e, clickedIndex, newValue, oldValue) {
        categoria_filtro_id = this.value;
        sportelloStatisticheEffettuatiReadRecords();
    });
    
    $('#conSportelliCheckBox').change(function () {
        // this si riferisce al checkbox
        if (this.checked) {
            conSportelli = 1;
            $('#senzaSportelliCheckBox').bootstrapToggle('off');
            $('#conSportelliFattiCheckBox').bootstrapToggle('off');
        } else {
            conSportelli = 0;
        }
        sportelloStatisticheEffettuatiReadRecords();
    });

    $('#senzaSportelliCheckBox').change(function () {
        // this si riferisce al checkbox
        if (this.checked) {
            senzaSportelli = 1;
            $('#conSportelliFattiCheckBox').bootstrapToggle('off');
            $('#conSportelliCheckBox').bootstrapToggle('off');
        } else {
            senzaSportelli = 0;
        }
        sportelloStatisticheEffettuatiReadRecords();
    });

    $('#conSportelliFattiCheckBox').change(function () {
        // this si riferisce al checkbox
        if (this.checked) {
            conSportelliFatti = 1;
            $('#senzaSportelliCheckBox').bootstrapToggle('off');
            $('#conSportelliCheckBox').bootstrapToggle('off');
        } else {
            conSportelliFatti = 0;
        }
        sportelloStatisticheEffettuatiReadRecords();
    });

    $('#soloPassatiCheckBox').change(function () {
        // this si riferisce al checkbox
        if (this.checked) {
            soloPassati = 1;
            $('#soloFuturiCheckBox').bootstrapToggle('off');
        } else {
            soloPassati = 0;
        }
        sportelloStatisticheEffettuatiReadRecords();
    });

    $('#soloFuturiCheckBox').change(function () {
        // this si riferisce al checkbox
        if (this.checked) {
            soloFuturi = 1;
            $('#soloPassatiCheckBox').bootstrapToggle('off');
        } else {
            soloFuturi = 0;
        }
        sportelloStatisticheEffettuatiReadRecords();
    });
});
