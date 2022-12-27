<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/__Settings.php';
require_once __DIR__ . '/__Log.php';

global $__con;
$__con = new mysqli($__settings->db->host, $__settings->db->user, $__settings->db->password, $__settings->db->database);

if ($__con->connect_error) {
	die("Connection failed: " . $__con->connect_error);
}
mysqli_set_charset($__con, 'utf8');

// TODO: vecchia variabile, rimuovere se non piu usata
$con = $__con;

// ultimo id inserito
function dblastId() {
	global $__con;
	return mysqli_insert_id($__con);
}

// esegue una query (che non ritorna valori)
function dbExec($query) {
	global $__con;

	debug($query);
	// esegue la query
	if (!$result = mysqli_query($__con, $query)) {
		error('errore in esecuzione query.' . PHP_EOL . 'query=' . $query . PHP_EOL . 'error message=' . mysqli_error($__con));
		exit('Application Error');
	}
}

// esegue una query anche multipla (che non ritorna valori)
function dbExecMulti($query) {
	global $__con;

	debug($query);
	// esegue la query
	if (!$result = mysqli_multi_query($__con, $query)) {
		error('errore in esecuzione query.' . PHP_EOL . 'query=' . $query . PHP_EOL . 'error message=' . mysqli_error($__con));
		exit('Application Error');
	}
}

// ritorna il primo risultato di una query
function dbGetFirst($query) {
	global $__con;

	debug($query);
	// esegue la query
	if (!$result = mysqli_query($__con, $query)) {
		error('errore in esecuzione query.' . PHP_EOL . 'query=' . $query . PHP_EOL . 'error message=' . mysqli_error($__con));
		return null;
	}

	// controlla che ci siano dei risultati
	if(mysqli_num_rows($result) <= 0) {
		debug('nessun risultato per la query: '.$query);
		return null;
	}

	// prende il primo risultato e lo ritorna
	return mysqli_fetch_assoc($result);
}

// ritorna i risultati di una query
function dbGetAll($query) {
	global $__con;

	debug($query);
	// esegue la query
	if (!$result = mysqli_query($__con, $query)) {
		error('errore in esecuzione query.' . PHP_EOL . 'query=' . $query . PHP_EOL . 'error message=' . mysqli_error($__con));
		return null;
	}
	$value = $result->fetch_all(MYSQLI_ASSOC);
	return is_array($value) ? $value : [];
}

// ritorna un valore specifico se il risultato e' un solo valore
function dbGetValue($query) {
	global $__con;

	debug($query);
	// esegue la query
	if (!$result = mysqli_query($__con, $query)) {
		error('errore in esecuzione query.' . PHP_EOL . 'query=' . $query . PHP_EOL . 'error message=' . mysqli_error($__con));
		return null;
	}
	$value = $result->fetch_array(MYSQLI_NUM);
	return is_array($value) ? $value[0] : null;
}

// ritorna un valore specifico se il risultato e' un solo valore
function dbGetAllValues($query) {
	global $__con;

	debug($query);
	// esegue la query
	if (!$result = mysqli_query($__con, $query)) {
		error('errore in esecuzione query.' . PHP_EOL . 'query=' . $query . PHP_EOL . 'error message=' . mysqli_error($__con));
		return null;
	}
	$valueList = $result->fetch_all(MYSQLI_NUM);
	if (! is_array($valueList)) {
		return [];
	}
	$values = [];
	foreach($valueList as $valueContainer) {
		$values[] = $valueContainer[0];
	}

	return $values;
}

?>
