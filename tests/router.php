<?php
// Local PHP development server only. Invoke with the test site's document root.
$root = $_SERVER['DOCUMENT_ROOT'];
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = realpath($root . $path);
if ($file && str_starts_with($file, realpath($root) . DIRECTORY_SEPARATOR) && is_file($file)) {
    return false;
}
require $root . '/index.php';
