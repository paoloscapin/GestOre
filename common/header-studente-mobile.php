<?php
/**
 *  Mobile header per Studente
 */
?>

<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/header-style.css">

<nav class="navbar navbar-default navbar-fixed-top" style="background-color: #f8f8f8; border-color: #ddd;">
    <div class="container-fluid">

        <div class="navbar-header" style="position:relative; width:100%; display:flex; align-items:center;">

            <!-- Logo a sinistra -->
            <a href="<?php echo $__application_base_path; ?>/index.php" class="navbar-brand top-navbar-brand" style="padding: 5px 15px;">
                <img style="height: 44px;" 
                     src="data:image/png;base64,<?php echo base64_encode(dbGetValue("SELECT src FROM immagine WHERE nome = 'logo.png'")); ?>" 
                     alt="Logo">
            </a>

            <!-- Nome utente centrato -->
            <div class="navbar-center" style="position:absolute; left:50%; transform:translateX(-50%); font-weight:bold; color:#333; white-space:nowrap;">
                <?php if (haRuolo('admin')) echo "(A) "; ?>
                <?php echo $__studente_nome.' '.$__studente_cognome ?>
            </div>

            <!-- Hamburger a destra -->
            <button type="button" class="navbar-toggle collapsed navbar-toggle-right" 
                    data-toggle="collapse" data-target="#mobile-navbar" aria-expanded="false">
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>

        </div>

        <!-- Menu Mobile -->
        <div class="collapse navbar-collapse" id="mobile-navbar">
            <ul class="nav navbar-nav navbar-right">

                <?php if(getSettingsValue('config','sportelli', false)) : ?>
                    <li><a class="btn btn-orange4" href="<?php echo $__application_base_path; ?>/studente/sportello_mobile.php">
                        <span class="glyphicon glyphicon-blackboard"></span> Sportelli
                    </a></li>
                <?php endif; ?>

                <?php if((getSettingsValue('config','carenzeObiettiviMinimi', false)) && (getSettingsValue('carenzeObiettiviMinimi','visibile_studenti', false))) : ?>
                    <li><a class="btn btn-lightblue4" href="<?php echo $__application_base_path; ?>/studente/carenze_mobile.php">
                        <span class="glyphicon glyphicon-film"></span> Carenze
                    </a></li>
                <?php endif; ?>

                <li><a class="btn btn-lightblue4" href="<?php echo $__application_base_path; ?>/help/GestOre - Guida Studenti.pdf" target="_blank">
                    <span class="glyphicon glyphicon-question-sign"></span> Guida
                </a></li>

                <li>
                    <?php if (haRuolo('admin')) : ?>
                        <a class="btn btn-lightblue4" href="<?php echo $__application_base_path; ?>/admin/index.php">
                            <span class="glyphicon glyphicon-log-out"></span> Logout
                        </a>
                    <?php else : ?>
                        <a class="btn btn-lightblue4" href="<?php echo $__application_base_path; ?>/common/logout.php?base=studente">
                            <span class="glyphicon glyphicon-log-out"></span> Logout
                        </a>
                    <?php endif; ?>
                </li>

            </ul>
        </div>

    </div>
</nav>

<style>
/* hamburger a destra */
.navbar-toggle.navbar-toggle-right {
    float: none !important;
    margin-left: auto;
    margin-right: 15px;
}
.navbar-toggle {
    margin-right: 0 !important;
}
</style>
