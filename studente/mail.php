<?php
echo "ciao";
$sender = "noReplyGestOre@mbgest.it";
$headers  = 'MIME-Version: 1.0' . "\r\n";
$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
$headers .= "From: noReplyGestOre@mbgest.it\r\n";
$headers .= "Bcc: massimo.saiani@buonarroti.tn.it\r\n"."X-Mailer: php";
$mailsubject = 'GestOre - Prenotazione studente attività ';
mail("massimo.saiani@buonarroti.tn.it", "testo" ,  "testo", $headers, additional_params: "-f$sender");
?>