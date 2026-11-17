<?php
/**
 *  Pagina per la visualizzazione dei Buoni Pasto dei docenti (Sola Lettura)
 */
require_once '../common/checkSession.php';
require_once '../common/header-common.php';
require_once '../common/style.php';
require_once '../common/_include_bootstrap-select.php';
require_once '../common/_include_flatpickr.php';

// Accesso consentito alla segreteria e al dirigente
ruoloRichiesto('segreteria-docenti', 'dirigente');
?>

<!DOCTYPE html>
<html>
<head>
	<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/table-green-2.css">
	<title>Buoni Pasto - Seleziona Docente</title>

<style>
/* Forza il testo del placeholder a essere bianco dentro i bottoni btn-success */
.btn-success.bs-placeholder, 
.btn-success.bs-placeholder:hover, 
.btn-success.bs-placeholder:focus, 
.btn-success.bs-placeholder:active {
    color: rgba(255, 255, 255, 0.75) !important; /* Bianco leggermente opaco per sembrare un placeholder */
}
</style>

</head>
<body>
<?php
// Carica l'header della segreteria per mantenere i colori e i menu corretti
require_once '../common/header-segreteria.php';

// Prepara l'elenco dei docenti dal database
$docenteOptionList = '<option value="" title=""></option>';
$query = "SELECT * FROM docente WHERE docente.attivo = true ORDER BY docente.cognome, docente.nome ASC;";
foreach(dbGetAll($query) as $row) {
    $docenteOptionList .= '<option value="'.$row['id'].'" data-subtext="'.$row['username'].'">'.$row['cognome'].' '.$row['nome'].'</option>';
}

// Prepara l'elenco dei mesi
// Definiamo i mesi nell'ordine esatto da settembre a luglio
$mesi_scolastici = [
    9  => 'settembre',
    10 => 'ottobre',
    11 => 'novembre',
    12 => 'dicembre',
    1  => 'gennaio',
    2  => 'febbraio',
    3  => 'marzo',
    4  => 'aprile',
    5  => 'maggio',
    6  => 'giugno',
    7  => 'luglio'
];

$meseOptionList = "<option value=\"\" title=\"\"></option>";
foreach ($mesi_scolastici as $value => $nome_mese) {
    $meseOptionList .= "<option value='$value'>$nome_mese</option>";
}
?>

<?php if(getSettingsValue('config','buoniPasto', false)) : ?>
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
                <select id="docente_select" class="selectpicker" data-style="btn-success" data-live-search="true" data-none-selected-text="Cerca un docente..." data-width="50%" onchange="apriProspettoDocente()">
                    <?php echo $docenteOptionList; ?>
                </select>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if(getSettingsValue('config','buoniPastoExport', false)) : ?>
<div class="container-fluid" style="margin-top:60px">
    <div class="panel panel-success">
        <div class="panel-heading container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <span class="glyphicon glyphicon-cutlery"></span>&emsp;Buoni Pasto: Seleziona l'intervallo e il Docente
                </div>
            </div>
        </div>

        <div class="panel-body text-center">
            <div class="form-group" style="margin-top: 20px;">

                <!-- 2. MESE DI INIZIO -->
                <div class="col-md-3 form-group">
                    <label for="mese_da" style="display: block; text-align: left; font-weight: bold; margin-bottom: 5px;">Mese di inizio:</label>
                    <select id='mese_da' class="selectpicker" data-style="btn-success" data-none-selected-text="mese di inizio..." data-width="100%" onchange="ricalcola()">
                        <?php echo $meseOptionList; ?>
                    </select>
                </div>

                <!-- 3. MESE FINALE -->
                <div class="col-md-3 form-group">
                    <label for="mese_a" style="display: block; text-align: left; font-weight: bold; margin-bottom: 5px;">Mese finale:</label>
                    <select id='mese_a' class="selectpicker" data-style="btn-success" data-none-selected-text="mese finale..." data-width="100%" onchange="ricalcola()">
                        <?php echo $meseOptionList; ?>
                    </select>
                </div>

                <!-- 1. DOCENTE -->
                <div class="col-md-6 form-group">
                    <label for="docente_select-2" style="display: block; text-align: left; font-weight: bold; margin-bottom: 5px;">Docente:</label>
                    <select id="docente_select-2" class="selectpicker" data-style="btn-success" data-live-search="true" data-none-selected-text="Cerca un docente..." data-width="100%" onchange="ricalcola()">
                        <?php echo $docenteOptionList; ?>
                    </select>
                </div>
            </div>

            <div class="row" style="margin-top: 10px;">
                <div class="col-md-6"></div>
                <div class="col-md-6 text-center">
                    <button type="button" class="btn btn-primary btn-sm" onclick="scarica()">
                        <span class="glyphicon glyphicon-download-alt"></span>&nbsp;Scarica tutti i docenti
                    </button>
                </div>
            </div>

            <div class="row"  style="margin-bottom:10px;">
                <div class="col-md-12 text-center" id='result_text'>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="records_content"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Custom JS file -->
<script type="text/javascript" src="js/buoniPasto.js?v=<?php echo $__software_version; ?>"></script>

</body>
</html>