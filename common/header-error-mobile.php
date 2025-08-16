<?php
require_once __DIR__ . '/path.php';
require_once __DIR__ . '/connect.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?php echo $__application_base_path; ?>/css/header-style.css">

<nav class="navbar navbar-expand-lg fixed-top shadow-sm" style="background-color:#f8f8f8; border-bottom:1px solid #ddd;">
    <div class="container-fluid">

        <!-- Logo -->
        <a class="navbar-brand" href="<?php echo $__application_base_path; ?>">
            <img style="height:44px;" 
                 src="data:image/png;base64,<?php echo base64_encode(dbGetValue("SELECT src FROM immagine WHERE nome = 'logo.png'")); ?>" 
                 alt="Logo">
        </a>

        <!-- Hamburger -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#errorNavbar" aria-controls="errorNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Menu collapsibile -->
        <div class="collapse navbar-collapse" id="errorNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <!-- Link Home -->
                <li class="nav-item">
                    <a class="nav-link text-dark" href="<?php echo $__application_base_path; ?>">
                        <i class="bi bi-house-door"></i> Home
                    </a>
                </li>

                <!-- Link Logout -->
                <li class="nav-item">
                    <a class="nav-link text-dark" href="<?php echo $__application_base_path; ?>/common/logout.php">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </li>

            </ul>

            <!-- Nome utente a destra -->
            <span class="navbar-text ms-lg-auto fw-bold text-dark">
                <?php if (!empty($__utente_nome)) echo htmlspecialchars($__utente_nome . ' ' . $__utente_cognome); ?>
            </span>
        </div>

    </div>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
