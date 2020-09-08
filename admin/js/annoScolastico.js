/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function salvaAnnoScolastico() {
    var anno_scolastico_corrente_id = $("#hidden_anno_scolastico_corrente_id").val();
    var anno_scolastico_corrente_anno = $("#hidden_anno_scolastico_corrente_anno").val();
    var anno_scolastico_nuovo_id = $("#anno").val();
    var anno_scolastico_nuovo_anno = $("#anno").find("option:selected").text();
    if (anno_scolastico_nuovo_id <= 0) {
        bootbox.alert('Attenzione: bisogna selezionare il nuovo anno scolastico');
        return;
    }

    bootbox.confirm({
        message: "<p><strong>Attenzione</strong></br></p>"
                + "<p>Attivando un nuovo anno scolastico il precedente viene chiuso.</br>"
                + "I suoi dati saranno accessibili solo in lettura diretta dal database storico</p>"
                + "<hr style=\"border-top: 2px solid #6699ff;\">"
                + "<p>Sei sicuro di volere attivare l'anno scolastico <strong>" + anno_scolastico_nuovo_anno + "</strong></p>",
        buttons: {
            confirm: {
                label: 'Si',
                className: 'btn-success'
            },
            cancel: {
                label: 'No',
                className: 'btn-danger'
            }
        },
        callback: function (result) {
            if (result === true) {
                $.post("../admin/annoScolasticoUpdate.php", {
                    anno_scolastico_corrente_id: anno_scolastico_corrente_id,
                    anno_scolastico_corrente_anno: anno_scolastico_corrente_anno,
                    anno_scolastico_nuovo_id: anno_scolastico_nuovo_id,
                    anno_scolastico_nuovo_anno: anno_scolastico_nuovo_anno
                },
                function (data, status) {
                    $("#annoCorrente").text(anno_scolastico_nuovo_anno);
                    $('#annoCorrente').css("font-weight", "bold");
                    $.notify({
                        icon: 'glyphicon glyphicon-ok',
                        title: '<Strong>Anno Scolastico</Strong></br>',
                        message: 'Anno scolastico modificato: nuovo anno <Strong>' + anno_scolastico_nuovo_anno + '</Strong>' 
                    },{
                        placement: {
                            from: "top",
                            align: "center"
                        },
                        delay: 3000,
                        timer: 1000,
                        mouse_over: "pause",
                        type: 'success'
                    });
                            });
            } else {
                bootbox.alert('Cambio anno scolastico: operazione cancellata');
            }
        }
    });
}
