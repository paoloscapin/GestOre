<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Gestionale Login</title>
<link rel="stylesheet" href="../common/bootstrap-3.3.7-dist/css/bootstrap.min.css">
<script src="../common/jquery-3.3.1-dist/jquery-3.3.1.min.js"></script>
<script src="../common/bootstrap-3.3.7-dist/js/bootstrap.min.js"></script>
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
</head>
    <body>
    	<div class="login-form">
    		<form action="login-check.php" method="post">
    			<h2 class="text-center">GestOre</h2>
    			<div class="form-group">
    				<button type="submit" class="btn btn-danger btn-block">Log in with google</button>
    			</div>
    		</form>
    	</div>
    </body>
</html>