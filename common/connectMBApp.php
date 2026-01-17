<?php

/**
 *  This file is part of GestOre
 *  @author     Paolo Scapin <paolo.scapin@gmail.com>
 *  @copyright  (C) 2018 Paolo Scapin
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

require_once __DIR__ . '/__Settings.php';
require_once __DIR__ . '/__Log.php';

global $__conMBApp;
$__conMBApp = new mysqli($__settings->dbMBApp->host, $__settings->dbMBApp->user, $__settings->dbMBApp->password, $__settings->dbMBApp->database);

if ($__conMBApp->connect_error) {
	die("Connection failed: " . $__conMBApp->connect_error);
}
mysqli_set_charset($__conMBApp, 'utf8');

// TODO: vecchia variabile, rimuovere se non piu usata


// ultimo id inserito
function mb_dblastId() {
	global $__conMBApp;
	return mysqli_insert_id($__conMBApp);
}

// esegue una query (che non ritorna valori)
function mb_dbExec($query) {
	global $__conMBApp;

	debug($query);
	// esegue la query
	if (!$result = mysqli_query($__conMBApp, $query)) {
		error('errore in esecuzione query.' . PHP_EOL . 'query=' . $query . PHP_EOL . 'error message=' . mysqli_error($__conMBApp));
		exit('Application Error');
	}
}

// esegue una query anche multipla (che non ritorna valori)
function mb_dbExecMulti($query) {
	global $__conMBApp;

	debug($query);
	// esegue la query
	if (!$result = mysqli_multi_query($__conMBApp, $query)) {
		error('errore in esecuzione query.' . PHP_EOL . 'query=' . $query . PHP_EOL . 'error message=' . mysqli_error($__conMBApp));
		exit('Application Error');
	}
}

// ritorna il primo risultato di una query
function mb_dbGetFirst($query) {
	global $__conMBApp;

	debug($query);
	// esegue la query
	if (!$result = mysqli_query($__conMBApp, $query)) {
		error('errore in esecuzione query.' . PHP_EOL . 'query=' . $query . PHP_EOL . 'error message=' . mysqli_error($__conMBApp));
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
function mb_dbGetAll($query) {
	global $__conMBApp;

	debug($query);
	// esegue la query
	if (!$result = mysqli_query($__conMBApp, $query)) {
		error('errore in esecuzione query.' . PHP_EOL . 'query=' . $query . PHP_EOL . 'error message=' . mysqli_error($__conMBApp));
		return null;
	}
	$value = $result->fetch_all(MYSQLI_ASSOC);
	return is_array($value) ? $value : [];
}

// ritorna un valore specifico se il risultato e' un solo valore
function mb_dbGetValue($query) {
	global $__conMBApp;

	debug($query);
	// esegue la query
	if (!$result = mysqli_query($__conMBApp, $query)) {
		error('errore in esecuzione query.' . PHP_EOL . 'query=' . $query . PHP_EOL . 'error message=' . mysqli_error($__conMBApp));
		return null;
	}
	$value = $result->fetch_array(MYSQLI_NUM);
	return is_array($value) ? $value[0] : null;
}

// ritorna un valore specifico se il risultato e' un solo valore
function mb_dbGetAllValues($query) {
	global $__conMBApp;

	debug($query);
	// esegue la query
	if (!$result = mysqli_query($__conMBApp, $query)) {
		error('errore in esecuzione query.' . PHP_EOL . 'query=' . $query . PHP_EOL . 'error message=' . mysqli_error($__conMBApp));
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
