<?php
 require_once("__i18n.php");
    // TRANS: This is a comment in po file
    echo  __("Good Morning");

    $exp = 3;
    printf (__(
        'Your account will expire in %d day',
        'Your account will expire in %d days',
        $exp
    ),
    $exp
);
?>
<h1>
    Translating PHP pages with gettext    </h1>