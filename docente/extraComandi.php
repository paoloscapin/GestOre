<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

?>

<!DOCTYPE html>
<html>
<head>
	<title>Modulistica Docenti</title>
<?php
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
ruoloRichiesto('docente','segreteria-docenti','dirigente');

function startsWith($haystack, $needle) {
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

function endsWith($haystack, $needle) {
    $length = strlen($needle);
    return $length === 0 ||  (substr($haystack, -$length) === $needle);
}

// funzione helper per alterare la luminosità
function modificaLuminositaHex($hex, $percentuale) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) == 3) { $hex = $hex.$hex.$hex.$hex.$hex.$hex; }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $fattore = $percentuale / 100;
    if ($percentuale > 0) {
        $r = $r + ((255 - $r) * $fattore); $g = $g + ((255 - $g) * $fattore); $b = $b + ((255 - $b) * $fattore);
    } else {
        $r = $r + ($r * $fattore); $g = $g + ($g * $fattore); $b = $b + ($b * $fattore);
    }
    return sprintf("#%02x%02x%02x", max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
}

function isColoreChiaro($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) == 3) { $hex = $hex.$hex.$hex.$hex.$hex.$hex; }
    
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    // Formula standard di luminanza percepita dall'occhio umano (W3C)
    // Il verde pesa di più, il blu pesa di meno
    $luminanza = (0.299 * $r) + (0.587 * $g) + (0.114 * $b);

    // Se la luminanza è maggiore di 180 (su un massimo di 255), il colore è considerato "chiaro"
    return ($luminanza > 180); 
}
?>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-3.css">
<!-- Custom JS file moved to the end -->
</head>

<body >
<?php
require_once '../common/header-docente.php';
require_once '../common/connect.php';
?>

<div class="container-fluid" style="margin-top:60px">
<div class="panel panel-yellow4">
<div class="panel-heading container-fluid">
	<div class="row">
		<div class="col-md-11">
			<span class="glyphicon glyphicon-option-horizontal"></span>&emsp;<strong>Altri Comandi</strong>
		</div>
		<div class="col-md-1 text-right" id="page_refresh">
		</div>
	</div>
</div>
<div class="panel-body">
    <div class="row">
    <div class="col-md-12">

<?php
$openTabMode = getSettingsValue('interfaccia','apriModuloInNuovoTab', false) ? '_blank' : '_self';
$docente_id = $__docente_id;

foreach(dbGetAll("SELECT * FROM extra_comando_categoria ORDER BY posizione;") as $categoria) {
    $categoriaId = $categoria["id"];
    $categoriaNome = $categoria["nome"];
    $categoriaColore = $categoria["colore"];

    echo('<div class="table-wrapper"><table id="modulistica_docenti_table" class="table table-bordered table-striped table-green">
        <thead> <tr> <th class="text-center col-md-12" style="'.$categoriaColore.'" >'.$categoriaNome.'</th> </tr> </thead> <tbody>');

    foreach(dbGetAll("SELECT * FROM extra_comando WHERE extra_comando.valido = true AND extra_comando.extra_comando_categoria_id = $categoriaId ORDER BY posizione;") as $extra_comando) {
        $nome = $extra_comando['nome'];
        $comando = $extra_comando['comando'];
        $colore = $extra_comando['colore'];

        // Generiamo i colori per la sfumatura di questa specifica riga
        $colore_base   = $colore;
        $colore_chiaro = modificaLuminositaHex($colore_base, 15);
        $colore_scuro  = modificaLuminositaHex($colore_base, -20);
        $bordo_fondo   = modificaLuminositaHex($colore_base, -35);
        $gradient      = "linear-gradient(180deg, $colore_chiaro 0%, $colore_scuro 100%)";

        // Controllo accessibilità: se il bottone è giallo, usiamo il testo scuro
        $colore_di_sfondo_chiaro = isColoreChiaro($colore_base);
        $colore_testo  = $colore_di_sfondo_chiaro ? '#222222' : '#ffffff';
        $ombra_testo   = $colore_di_sfondo_chiaro ? '0 1px 0 rgba(255,255,255,0.6)' : '0 1px 2px rgba(0,0,0,0.5)';
        $ombra_interna = $colore_di_sfondo_chiaro ? 'inset 0 1px 0 rgba(255,255,255,0.7)' : 'inset 0 1px 0 rgba(255,255,255,0.4)';

        echo '<tr><td style="vertical-align: middle;">';
        // a seconda del comando:
        if (startsWith($comando, "link ")) {
            $resto = substr($comando, 5);
            echo '<a href="' . htmlspecialchars($resto) . '" target="_blank" rel="noopener noreferrer"';
            echo 'class="btn btn-lg" style="';
            echo 'background-image: '.$gradient.'; ';
            echo 'background-color: '.$colore_base.'; ';
            echo 'color: '.$colore_testo.'; ';
            echo 'border: 1px solid  '.$colore_scuro.'; ';
            echo 'border-bottom-color: '.$bordo_fondo.'; ';
            echo 'font-weight: bold; ';
            echo 'text-shadow: '.$ombra_testo.'; ';
            echo 'box-shadow: '.$ombra_interna.', 0 2px 4px rgba(0,0,0,0.15); ';
            echo '">'.$nome.'</a>';
            // echo '<a href="' . htmlspecialchars($resto) . '" target="_blank" rel="noopener noreferrer">'.$nome.'</a>';
        } elseif (startsWith($comando, "img ")) {
            // Esempio per il futuro
            $resto = substr($comando, 4);
            echo '<img src="' . htmlspecialchars($resto) . '">';
        } else {
            echo "Comando non riconosciuto.";
        }
        echo '</td></tr>';
    }
}
?>
        </tbody>
        </table>
        </div>
    </div>
    </div>
</div>

<!-- <div class="panel-footer"></div> -->
</div>
</div>

</body>
</html>