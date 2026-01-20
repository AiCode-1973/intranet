<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

$file = $_GET['file'] ?? '';

if (empty($file)) {
    die("Arquivo não especificado.");
}

// Segurança: Impedir path traversal
$file = basename($file);
$type = $_GET['type'] ?? 'documento';

if ($type == 'politica') {
    $base_dir = __DIR__ . '/uploads/rh/politicas/';
} else {
    $base_dir = __DIR__ . '/uploads/rh/documentos/';
}

$filepath = $base_dir . $file;

if (file_exists($filepath)) {
    $content_type = mime_content_type($filepath);
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: inline; filename="' . $file . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
} else {
    die("Arquivo não encontrado.");
}
?>
