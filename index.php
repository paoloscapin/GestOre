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
<?php
require_once __DIR__ . '/common/checkSession.php';

// if the session contains the role, go to the home corresponding to that role
if (haRuolo('admin')) {
    redirect('/admin/index.php');
}
else if (haRuolo('docente')) {
    redirect('/docente/index.php');
}
else if (haRuolo('dirigente')) {
    redirect('/dirigente/index.php');
}
else if (haRuolo('segreteria-docenti')) {
    redirect('/segreteria/index.php');
}
else if (haRuolo('segreteria-didattica')) {
    redirect('/didattica/index.php');
}
else if (haRuolo('studente')) {
    redirect('/studente/index.php');
}
else if (haRuolo('admin')) {
    redirect('/admin/index.php');
}
?>

<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php
require_once __DIR__ . '/common/header-common.php';
require_once __DIR__ . '/common/style.php';
?>
<style type="text/css">
	.login-form {
		width: 340px;
    	margin: 50px auto;
	}
    .login-form form {
    	margin-bottom: 15px;
        background: #f7f7f7;
        box-shadow: 0px 2px 2px rgba(0, 0, 0, 0.3);
        padding: 30px;
    }
    .login-form h2 {
        margin: 0 0 15px;
    }
    .form-control, .btn {
        min-height: 38px;
        border-radius: 2px;
    }
    .btn {
        font-size: 15px;
        font-weight: bold;
    }
</style>
<title>GestOre Login</title>
</head>

<body >
    <div class="container-fluid" style="margin-top:60px">
        <div class="login-form text-center">
            <form action="login-check.php" method="post">
                <h2 class="text-center">GestOre</h2>
                <div class="btn-group">
                    <a href="<?php echo filter_var($authUrl, FILTER_SANITIZE_URL) ?>" class="btn btn-info btn-block active">
                        <i class="glyphicon glyphicon-log-in" aria-hidden="true"></i> &ensp;Log in with google
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
