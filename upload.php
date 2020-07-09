<?php

function _log($str) {
    $log_str = date('d.m.Y').": {$str}\r\n";
    echo $log_str;
    // log to file
    if (($fp = fopen('upload_log.txt', 'a+')) !== false) {
        fputs($fp, $log_str);
        fclose($fp);
    }
}
function rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (filetype($dir."/".$object) == "dir")
                    rrmdir($dir."/".$object); 
                else
                    unlink($dir."/".$object);
            }
        }
        reset($objects);
        rmdir($dir);
    }
}
function createFileFromChunks($temp_dir, $fileName, $chunkSize, $totalSize,$total_files) {
    $total_files_on_server_size = 0;
    $temp_total = 0;
    foreach(scandir($temp_dir) as $file) {
        $temp_total = $total_files_on_server_size;
        $tempfilesize = filesize($temp_dir.'/'.$file);
        $total_files_on_server_size = $temp_total + $tempfilesize;
    }
    if ($total_files_on_server_size >= $totalSize) {
        if (($fp = fopen($temp_dir.'/../'.$fileName, 'w')) !== false) {
            for ($i=1; $i<=$total_files; $i++) {
                fwrite($fp, file_get_contents($temp_dir.'/'.$fileName.'.part'.$i));
                _log('writing chunk '.$i);
            }
            fclose($fp);
        }
        else {
            _log('cannot create the destination file');
            return false;
        }
        // rename the temporary directory (to avoid access from other concurrent chunks uploads) and than delete it
        if (rename($temp_dir, $temp_dir.'_UNUSED'))
            rrmdir($temp_dir.'_UNUSED');
        else
            rrmdir($temp_dir);
    }
}
//========================================
//check if request is GET and the requested chunk exists or not. this makes testChunks work
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if(!(isset($_GET['resumableIdentifier']) && trim($_GET['resumableIdentifier'])!='')){
        $_GET['resumableIdentifier'] = '';
    }
    $temp_dir = 'temp/'.$_GET['resumableIdentifier'];
    if(!(isset($_GET['resumableFilename']) && trim($_GET['resumableFilename'])!='')){
        $_GET['resumableFilename'] = '';
    }
    if(!(isset($_GET['resumableChunkNumber']) && trim($_GET['resumableChunkNumber'])!='')){
        $_GET['resumableChunkNumber'] = '';
    }
    $chunk_file = $temp_dir.'/'.$_GET['resumableFilename'].'.part'.$_GET['resumableChunkNumber'];
    if (file_exists($chunk_file)) {
         header("HTTP/1.0 200 Ok");
       } else {
         header("HTTP/1.0 404 Not Found");
       }
}

// loop through files and move the chunks to a temporarily created directory
if (!empty($_FILES)) foreach ($_FILES as $file) {
    // check the error status
    if ($file['error'] != 0) {
        _log('error '.$file['error'].' in file '.$_POST['resumableFilename']);
        continue;
    }
    // init the destination file (format <filename.ext>.part<#chunk>
    // the file is stored in a temporary directory
    if(isset($_POST['resumableIdentifier']) && trim($_POST['resumableIdentifier'])!=''){
        $temp_dir = 'temp/'.$_POST['resumableIdentifier'];
    }
    $dest_file = $temp_dir.'/'.$_POST['resumableFilename'].'.part'.$_POST['resumableChunkNumber'];
    // create the temporary directory
    if (!is_dir($temp_dir)) {
        mkdir($temp_dir, 0777, true);
    }
    // move the temporary file
    if (!move_uploaded_file($file['tmp_name'], $dest_file)) {
        _log('Error saving (move_uploaded_file) chunk '.$_POST['resumableChunkNumber'].' for file '.$_POST['resumableFilename']);
    } else {
        // check if all the parts present, and create the final destination file
        createFileFromChunks($temp_dir, $_POST['resumableFilename'],$_POST['resumableChunkSize'], $_POST['resumableTotalSize'],$_POST['resumableTotalChunks']);
    }
}