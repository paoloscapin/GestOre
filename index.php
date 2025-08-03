<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

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

require_once __DIR__ . '/common/header-common.php';
require_once __DIR__ . '/common/style.php';
?>
<!DOCTYPE html>
<html>
<head></head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">

<style type="text/css">
	.login-form {
		width: 340px;
    	margin: 40px auto;
	}
    .login-form form {
    	margin-bottom: 10px;
        background: #f7f7f7;
        box-shadow: 0px 2px 2px rgba(0, 0, 0, 0.3);
        padding: 30px;
    }
    .form-group {
        margin: 10px auto;
        width: 70%;
        text-align: center;
    }
    .login-form h2 {
        margin: 0 0 15px;
    }
    .form-control, .btn {
        min-height: 38px;
        border-radius: 2px;
        margin: 0 auto;
    }
    .btn {
        font-size: 15px;
        font-weight: bold;
    }
</style>
<title>GestOre Login</title>
</head>

<body >
    <div class="container-fluid" style="margin-top:10px">
        <div class="login-form text-center">
            <form action="login-check.php" method="post">
                <h2 class="text-center">GestOre</h2>

                    <a href="<?php echo filter_var($authUrl, FILTER_SANITIZE_URL) ?>" class="btn btn-info btn-block active">
                        <i class="glyphicon glyphicon-log-in" aria-hidden="true"></i> &ensp;Log in with google
                    </a>

            </form>
        </div>
    </div>

    <div class="container-fluid" style="margin-top:10px">
        <div class="login-form text-center" style="margin: 10px auto;">
            <form action="login-check.php" method="post">
                <h2 class="text-center">Accesso genitori</h2>
    			<div class="form-group">
                    <input type="text" class="form-control" name="username" placeholder="Username" required>
                    <input type="password" class="form-control" name="password" placeholder="Password" required>
    			</div>
   				<button type="submit" class="btn btn-danger btn-block"><i class="glyphicon glyphicon-log-in" aria-hidden="true"></i> &ensp;Log in with MasterCom</button>

            </form>
        </div>
    </div>
</body>
</html>