/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

/*
Esempio di uso:
$('#minuti1').change(function() {
    var resultOre= trasformaInFloat($('#minuti1').val());
    $('#minuti1').val(trasformaFloatInStringa(resultOre));
});

// set valore iniziale:
$('#minuti1').val(trasformaFloatInStringa(valore));

// get valore corrente in float
minuti: trasformaInFloat($('#minuti1').val()),

*/

// trasforma in ore:minuti il contenuto degli input ogni volta che cambiano
function campiInMinuti() {
    // se non gestisco i minuti non devo fare niente
    if ( ! __minuti) {
        return;
    }
    for (i = 0; i < arguments.length; i++) {
        var fieldId = arguments[i];
//        console.log("campiInMinuti setup di fieldId=" + fieldId);
        $(fieldId).change(function(event) {
//            console.log('change id=' + event.target.id);
            var id = '#' + event.target.id;
            var resultOre= trasformaInFloat($(id).val());
//            console.log("campiInMinuti resultOre=" + resultOre);
            $(id).val(trasformaFloatInStringa(resultOre));
        });
    }
}

function setOre(fieldId, ore) {
    // se non gestisco i minuti scrivo direttamente le ore
    if ( ! __minuti) {
        $(fieldId).val(Math.round(ore));
        return;
    }
    var oreString = trasformaFloatInStringa(ore);
    $(fieldId).val(oreString);
}

function getOre(fieldId) {
    // prendo il valore del campo
    var valore = $(fieldId).val();
//    console.log("getMinuti valore letto=" + valore);
    // se non gestisco i minuti non devo fare niente
    if ( ! __minuti) {
        return valore;
    }
    var resultOre = trasformaInFloat(valore);
//    console.log("getMinuti resultOre=" + resultOre);
    return resultOre;
}

// trasforma un input in minuti
function trasformaInMinuti(src) {
    var ore = 0;
    var minuti = 0;
    var arr = $.trim(src).split(/[ ,.:-]+/);
    for (var i = 0; i < arr.length; i++) {
//        console.log("arr[" + i + "]=" + arr[i]);
    }

    // se c'e' un solo valore, sono ore
    ore = parseInt(arr[0], 10) || 0;

    // se esiste un secondo valore sono minuti
    if (arr.length > 1) {
        minuti = parseInt(arr[1], 10) || 0;
    }

    // minuti totali
    var minutiTotale = ore * 60 + minuti;
    return minutiTotale;
}

// trasforma i minuti in un formato da visualizzare
function trasformaInStringa(minutiTotale) {
    var ore = 0;
    var minuti = 0;

    // ore reali
    ore = Math.floor(minutiTotale / 60);

    // minuti reali
    minuti = minutiTotale % 60;
    return ore + ":" + ('' + minuti).padStart(2, '0');
}

function trasformaInFloat(src) {
    var minutiTotale = trasformaInMinuti(src);
    var oreFloat = minutiTotale / 60;
    return oreFloat;
}

function trasformaFloatInStringa(oreFloat) {
    if ( ! __minuti) {
        return Math.round(oreFloat);
    }
    var minutiTotale = Math.round(oreFloat * 60);
    return trasformaInStringa(minutiTotale);
}
