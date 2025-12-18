<?php
require_once '../common/checkSession.php';
require_once '../common/connect.php';
ruoloRichiesto('segreteria-didattica', 'dirigente');

$query = "
    SELECT s.id, s.cognome, s.nome,
           (SELECT c.classe
              FROM studente_frequenta sf
              JOIN classi c ON c.id = sf.id_classe
             WHERE sf.id_studente = s.id
             ORDER BY sf.id_anno_scolastico DESC
             LIMIT 1) AS classe
    FROM studente s
    WHERE s.attivo = 1
    ORDER BY s.cognome, s.nome
";

echo json_encode(dbGetAll($query));
