<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once '../common/checkSession.php';
ruoloRichiesto('admin');

function materiaSaveColumnExists(string $tableName, string $columnName): bool
{
    $row = dbGetFirst("SHOW COLUMNS FROM `$tableName` LIKE " . dbQ($columnName));
    return is_array($row) && !empty($row);
}

$tableName = "materia";
if(isset($_POST)) {
	$id = intval($_POST['id'] ?? 0);
    $nome = trim((string)($_POST['nome'] ?? ''));
	$codice = trim((string)($_POST['codice'] ?? ''));
    $idDipartimento = intval($_POST['id_dipartimento'] ?? 0);
    $hasDipartimento = materiaSaveColumnExists($tableName, 'id_dipartimento');

    if ($id > 0) {
        $setDipartimento = $hasDipartimento ? ", id_dipartimento = " . ($idDipartimento > 0 ? dbI($idDipartimento) : "NULL") : "";
        $query = "UPDATE $tableName SET nome = " . dbQ($nome) . ", codice = " . dbQ($codice) . "$setDipartimento WHERE id = " . dbI($id);
        dbExec($query);
        info("aggiornato $tableName id=$id nome=$nome codice=$codice id_dipartimento=$idDipartimento");
    } else {
        if ($hasDipartimento) {
            $query = "INSERT INTO $tableName(nome, codice, id_dipartimento) VALUES(" . dbQ($nome) . ", " . dbQ($codice) . ", " . ($idDipartimento > 0 ? dbI($idDipartimento) : "NULL") . ")";
        } else {
            $query = "INSERT INTO $tableName(nome, codice) VALUES(" . dbQ($nome) . ", " . dbQ($codice) . ")";
        }
        dbExec($query);
        $id = dblastId();
        info("aggiunto $tableName id=$id nome=$nome codice=$codice id_dipartimento=$idDipartimento");
    }
}
?>
