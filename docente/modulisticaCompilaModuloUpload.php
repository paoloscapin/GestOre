<?php

require_once '../common/checkSession.php';

// lo uuid ed il nome dell'input vengono passati
$uuid = $_POST['uuid'];
$inputName = $_POST['inputName'];

// debug('uuid='.$_POST['uuid']);
// debug('inputName='.$_POST['inputName']);

// ricava i dati del file da caricare
$fileName = $_FILES[$inputName]['name'];
$fileSize = $_FILES[$inputName]['size'];
$fileTmpName  = $_FILES[$inputName]['tmp_name'];
$fileType = $_FILES[$inputName]['type'];

// la root directory in cui si trova l'applicazione
$applicationRoot = $_SERVER['DOCUMENT_ROOT'] . '/' . APPLICATION_NAME;

// la directory di base in cui vengono caricati i files sotto le loro directory
$uploadBaseDirectory = $applicationRoot . '/uploads' . '/';

// la directory locale di base che si trova sotto la generale directory uploads
$annoScolasticoName = str_replace("/","-",$__anno_scolastico_corrente_anno);
$uploadLocalDirectory = $annoScolasticoName . '/' . $uuid . '/';

// la directory su cui va caricato il file: se non esiste la crea
$annoScolasticoName = str_replace("/","-",$__anno_scolastico_corrente_anno);
$uploadDirectory = $uploadBaseDirectory . $uploadLocalDirectory;

if (!file_exists($uploadDirectory)) {
    mkdir($uploadDirectory, 0777, true);
}

$errors = [];

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