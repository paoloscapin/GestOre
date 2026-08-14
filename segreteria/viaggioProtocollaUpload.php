<?php

require_once '../common/checkSession.php';

$protocollo = $_POST['protocollo'];
$viaggio_id = $_POST['viaggio_id'];

debug('protocollo='.$protocollo);
debug('viaggio_id='.$viaggio_id);

$inputName = $_POST['inputName'];

// ricava i dati del file da caricare
$uploadedFileName = $_FILES[$inputName]['name'];
$fileSize = $_FILES[$inputName]['size'];
$fileTmpName  = $_FILES[$inputName]['tmp_name'];
$fileType = $_FILES[$inputName]['type'];

// la root directory in cui si trova la directory dell'applicazione
$applicationRoot = realpath($_SERVER['DOCUMENT_ROOT']);

// la location da configurazione in cui caricare gli upload
$uploadLocation = getSettingsValue('config', 'uploadLocation', 'GestOre.uploads');

// la directory di base in cui vengono caricati i files sotto le loro directory
$uploadBaseDirectory = $applicationRoot . '/' . $uploadLocation . '/viaggi/';

// la directory locale che si deve trovare sotto la generale directory di upload
$annoScolasticoName = str_replace("/","-",$__anno_scolastico_corrente_anno);
$uploadLocalDirectory = $annoScolasticoName . '/';

// mette tutto insieme per produrre la directory su cui va caricato il file: se non esiste la crea
$uploadDirectory = $uploadBaseDirectory . $uploadLocalDirectory;

if (!file_exists($uploadDirectory)) {
    mkdir($uploadDirectory, 0777, true);
}

$errors = [];

// ricava l'estensione del filename da quello caricato
$fileExtension = pathinfo($uploadedFileName, PATHINFO_EXTENSION);

$fileName = preg_replace('/[^a-z0-9.–-]/i', '_', $protocollo) . '.' . $fileExtension;

$uploadLocalPath = $uploadLocalDirectory . basename($fileName);
$uploadPath = $uploadDirectory . basename($fileName);

debug("uploadDirectory=".$uploadDirectory);
debug("uploadPath=".$uploadPath);
debug("uploadLocalDirectory=".$uploadLocalDirectory);
debug("uploadLocalPath=".$uploadLocalPath);
debug("basename=".basename($fileName));

debug("fileName=".$fileName);
debug("fileTmpName=".$fileTmpName);
debug("fileSize=".$fileSize);
debug("fileType=".$fileType);

if ($fileSize > 2000000) {
    $errors[] = "This file is more than 2MB. Sorry, it has to be less than or equal to 2MB";
}

if (empty($errors)) {
    $didUpload = move_uploaded_file($fileTmpName, $uploadPath);

    if ($didUpload) {
        debug("The file " . basename($fileName) . " has been uploaded");

        // a questo punto il file si puo' dire protocollato, ma cambia stato solo se era creato (altrimenti vuol dire che e' stato protocollato tardi, non dovrebbe succedere)
        $query = "UPDATE viaggio SET stato = 'protocollato' WHERE id = '$viaggio_id'";
		dbExec($query);
		info("aggiornato viaggio id=$viaggio_id stato=protocollato");
    } else {
        debug("An error occurred somewhere. Try again or contact the admin");
    }
} else {
    foreach ($errors as $error) {
        debug('error: '. $error);
    }
}

die($uploadLocalPath);
?>