<?php
/**
 *  Pagina per la visualizzazione dei Buoni Pasto dei docenti (Sola Lettura)
 */
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-select.php';

// Accesso consentito alla segreteria e al dirigente
ruoloRichiesto('segreteria-docenti', 'dirigente');
?>

<!DOCTYPE html>
<html>
<head>
	<title>Buoni Pasto - Seleziona Docente</title>
</head>
<body>
<?php
// Carica l'header della segreteria per mantenere i colori e i menu corretti
require_once '../common/header-segreteria.php';

// Prepara l'elenco dei docenti dal database
$docenteOptionList = '<option value="0"></option>';
$query = "SELECT * FROM docente WHERE docente.attivo = true ORDER BY docente.cognome, docente.nome ASC;";
foreach(dbGetAll($query) as $row) {
    $docenteOptionList .= '<option value="'.$row['id'].'" data-subtext="'.$row['username'].'">'.$row['cognome'].' '.$row['nome'].'</option>';
}
?>

<div class="container-fluid" style="margin-top:60px">
    <div class="panel panel-success">
        <div class="panel-heading container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <span class="glyphicon glyphicon-cutlery"></span>&emsp;Buoni Pasto: Seleziona il Docente per visualizzare il resoconto
                </div>
            </div>
        </div>

        <div class="panel-body text-center">
            <div class="form-group" style="margin-top: 20px;">
                <select id="docente_select" class="selectpicker" data-style="btn-success" data-live-search="true" data-noneSelectedText="Cerca un docente..." data-width="50%" onchange="apriProspettoDocente()">
                    <?php echo $docenteOptionList; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<script>
// Script per il reindirizzamento automatico alla pagina attività
function apriProspettoDocente() {
    var idDocente = document.getElementById('docente_select').value;
    if (idDocente > 0) {
        window.location.href = '../docente/attivita.php?docente_id=' + idDocente;
    }
}
</script>

</body>
</html>