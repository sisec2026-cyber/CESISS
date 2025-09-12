<?php
if (isset($_GET['file'])) {
    // Evitamos rutas maliciosas con basename
    $file = basename($_GET['file']); 
    $path = __DIR__ . '/' . $file;   // Ruta física al archivo

    if (file_exists($path)) {
        header("Content-Description: File Transfer");
        header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
        header("Content-Disposition: attachment; filename=\"" . $file . "\"");
        header("Content-Length: " . filesize($path));
        readfile($path);
        exit;
    } else {
        echo "Archivo no encontrado.";
    }
} else {
    echo "No se especificó archivo.";
}
